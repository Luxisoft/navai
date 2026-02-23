<?php

if (!defined('ABSPATH')) {
    exit;
}

class Navai_Voice_API
{
    private Navai_Voice_Settings $settings;
    private const OPENAI_CLIENT_SECRETS_URL = 'https://api.openai.com/v1/realtime/client_secrets';

    public function __construct(Navai_Voice_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register_routes(): void
    {
        register_rest_route(
            'navai/v1',
            '/realtime/client-secret',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_client_secret'],
                'permission_callback' => [$this, 'can_create_client_secret'],
            ]
        );

        register_rest_route(
            'navai/v1',
            '/functions',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_functions'],
                'permission_callback' => [$this, 'can_access_functions'],
            ]
        );

        register_rest_route(
            'navai/v1',
            '/routes',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_routes'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'navai/v1',
            '/functions/execute',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'execute_function'],
                'permission_callback' => [$this, 'can_access_functions'],
            ]
        );
    }

    public function can_create_client_secret(WP_REST_Request $request): bool
    {
        $settings = $this->settings->get_settings();
        if (!empty($settings['allow_public_client_secret'])) {
            return true;
        }

        return current_user_can('manage_options');
    }

    public function can_access_functions(WP_REST_Request $request): bool
    {
        $settings = $this->settings->get_settings();
        if (!empty($settings['allow_public_functions'])) {
            return true;
        }

        return current_user_can('manage_options');
    }

    public function create_client_secret(WP_REST_Request $request)
    {
        if (!$this->check_rate_limit()) {
            return new WP_Error(
                'navai_rate_limit',
                'Too many requests. Try again in a moment.',
                ['status' => 429]
            );
        }

        $settings = $this->settings->get_settings();
        $apiKey = trim((string) ($settings['openai_api_key'] ?? ''));

        if ($apiKey === '') {
            return new WP_Error(
                'navai_missing_api_key',
                'Missing OpenAI API key in NAVAI settings.',
                ['status' => 500]
            );
        }

        $input = $request->get_json_params();
        if (!is_array($input)) {
            $input = [];
        }

        $model = $this->read_input_or_setting($input, 'model', (string) $settings['default_model']);
        $voice = $this->read_input_or_setting($input, 'voice', (string) $settings['default_voice']);
        $baseInstructions = $this->read_input_or_setting(
            $input,
            'instructions',
            (string) $settings['default_instructions']
        );
        $language = $this->read_input_or_setting($input, 'language', (string) $settings['default_language']);
        $voiceAccent = $this->read_input_or_setting($input, 'voiceAccent', (string) $settings['default_voice_accent']);
        $voiceTone = $this->read_input_or_setting($input, 'voiceTone', (string) $settings['default_voice_tone']);

        $ttl = (int) ($settings['client_secret_ttl'] ?? 600);
        if ($ttl < 10 || $ttl > 7200) {
            $ttl = 600;
        }

        $payload = [
            'expires_after' => [
                'anchor' => 'created_at',
                'seconds' => $ttl,
            ],
            'session' => [
                'type' => 'realtime',
                'model' => $model,
                'instructions' => $this->build_session_instructions(
                    $baseInstructions,
                    $language,
                    $voiceAccent,
                    $voiceTone
                ),
                'audio' => [
                    'output' => [
                        'voice' => $voice,
                    ],
                ],
            ],
        ];

        $response = wp_remote_post(
            self::OPENAI_CLIENT_SECRETS_URL,
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('navai_openai_error', $response->get_error_message(), ['status' => 500]);
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($data) && isset($data['error']['message']) ? (string) $data['error']['message'] : $body;
            return new WP_Error(
                'navai_openai_http_error',
                sprintf('OpenAI client_secrets failed (%d): %s', $statusCode, $message),
                ['status' => 502]
            );
        }

        if (!is_array($data) || empty($data['value']) || !is_string($data['value'])) {
            return new WP_Error('navai_invalid_openai_response', 'Invalid response from OpenAI.', ['status' => 502]);
        }

        return rest_ensure_response(
            [
                'value' => $data['value'],
                'expires_at' => isset($data['expires_at']) ? (int) $data['expires_at'] : null,
            ]
        );
    }

    public function list_functions(WP_REST_Request $request)
    {
        $registry = $this->get_functions_registry();

        $items = [];
        foreach ($registry['ordered'] as $item) {
            $items[] = [
                'name' => $item['name'],
                'description' => $item['description'],
                'source' => $item['source'],
            ];
        }

        return rest_ensure_response(
            [
                'items' => $items,
                'warnings' => $registry['warnings'],
            ]
        );
    }

    public function list_routes(WP_REST_Request $request)
    {
        $routes = $this->build_allowed_routes_catalog();
        $response = new WP_REST_Response(
            [
                'items' => $routes,
                'count' => count($routes),
            ],
            200
        );
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('X-NAVAI-Routes-Count', (string) count($routes));

        return $response;
    }

    public function execute_function(WP_REST_Request $request)
    {
        $input = $request->get_json_params();
        if (!is_array($input)) {
            $input = [];
        }

        $functionName = isset($input['function_name']) ? strtolower(trim((string) $input['function_name'])) : '';
        if ($functionName === '') {
            return new WP_Error('navai_invalid_function_name', 'function_name is required.', ['status' => 400]);
        }

        $payload = isset($input['payload']) && is_array($input['payload']) ? $input['payload'] : [];
        $registry = $this->get_functions_registry();

        if (!isset($registry['by_name'][$functionName])) {
            return new WP_REST_Response(
                [
                    'error' => 'Unknown or disallowed function.',
                    'available_functions' => array_map(
                        static fn(array $item): string => $item['name'],
                        $registry['ordered']
                    ),
                ],
                404
            );
        }

        /** @var array<string, mixed> $definition */
        $definition = $registry['by_name'][$functionName];
        $callback = $definition['callback'];

        try {
            $result = call_user_func(
                $callback,
                $payload,
                [
                    'request' => $request,
                ]
            );
        } catch (Throwable $error) {
            return new WP_REST_Response(
                [
                    'ok' => false,
                    'function_name' => $definition['name'],
                    'error' => 'Function execution failed.',
                    'details' => $error->getMessage(),
                ],
                500
            );
        }

        return rest_ensure_response(
            [
                'ok' => true,
                'function_name' => $definition['name'],
                'source' => $definition['source'],
                'result' => $result,
            ]
        );
    }

    /**
     * @return array{
     *   by_name: array<string, array{name: string, description: string, source: string, callback: callable}>,
     *   ordered: array<int, array{name: string, description: string, source: string, callback: callable}>,
     *   warnings: array<int, string>
     * }
     */
    private function get_functions_registry(): array
    {
        $warnings = [];
        $byName = [];
        $ordered = [];

        $builtinDefinitions = $this->build_plugin_bridge_functions();
        $rawDefinitions = apply_filters('navai_voice_functions_registry', []);
        if (!is_array($rawDefinitions)) {
            $rawDefinitions = [];
        }

        $allDefinitions = array_merge($builtinDefinitions, $rawDefinitions);
        foreach ($allDefinitions as $index => $item) {
            if (!is_array($item)) {
                $warnings[] = sprintf('[navai] Ignored function #%d: invalid definition.', (int) $index);
                continue;
            }

            $rawName = isset($item['name']) ? (string) $item['name'] : '';
            $name = $this->normalize_function_name($rawName);
            if ($name === '') {
                $warnings[] = sprintf('[navai] Ignored function #%d: invalid name.', (int) $index);
                continue;
            }

            if (isset($byName[$name])) {
                $warnings[] = sprintf('[navai] Ignored duplicated function "%s".', $name);
                continue;
            }

            $callback = $item['callback'] ?? null;
            if (!is_callable($callback)) {
                $warnings[] = sprintf('[navai] Ignored function "%s": callback is not callable.', $name);
                continue;
            }

            $definition = [
                'name' => $name,
                'description' => isset($item['description']) && is_string($item['description'])
                    ? $item['description']
                    : 'Execute backend function.',
                'source' => isset($item['source']) && is_string($item['source']) ? $item['source'] : 'wp-filter',
                'callback' => $callback,
            ];

            $byName[$name] = $definition;
            $ordered[] = $definition;
        }

        return [
            'by_name' => $byName,
            'ordered' => $ordered,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<int, array{name: string, description: string, source: string, callback: callable}>
     */
    private function build_plugin_bridge_functions(): array
    {
        $catalog = $this->build_allowed_plugins_catalog();
        $actionsRegistry = $this->get_plugin_actions_registry();

        return [
            [
                'name' => 'list_allowed_plugins',
                'description' => 'List plugins allowed in NAVAI dashboard.',
                'source' => 'navai-dashboard',
                'callback' => static function (array $payload, array $context) use ($catalog) {
                    return [
                        'ok' => true,
                        'items' => array_values($catalog),
                    ];
                },
            ],
            [
                'name' => 'get_plugin_information',
                'description' => 'Get details from an allowed plugin by slug or plugin_file.',
                'source' => 'navai-dashboard',
                'callback' => function (array $payload, array $context) use ($catalog) {
                    $pluginInput = '';
                    if (isset($payload['plugin']) && is_string($payload['plugin'])) {
                        $pluginInput = $payload['plugin'];
                    } elseif (isset($payload['slug']) && is_string($payload['slug'])) {
                        $pluginInput = $payload['slug'];
                    } elseif (isset($payload['plugin_file']) && is_string($payload['plugin_file'])) {
                        $pluginInput = $payload['plugin_file'];
                    }

                    $resolved = $this->resolve_plugin_from_catalog($pluginInput, $catalog);
                    if (!$resolved) {
                        return [
                            'ok' => false,
                            'error' => 'Unknown or disallowed plugin.',
                            'available_plugins' => array_map(
                                static fn(array $item): string => $item['plugin_file'],
                                $catalog
                            ),
                        ];
                    }

                    return [
                        'ok' => true,
                        'plugin' => $resolved,
                    ];
                },
            ],
            [
                'name' => 'list_plugin_actions',
                'description' => 'List registered NAVAI plugin bridge actions for an allowed plugin.',
                'source' => 'navai-dashboard',
                'callback' => function (array $payload, array $context) use ($catalog, $actionsRegistry) {
                    $pluginInput = '';
                    if (isset($payload['plugin']) && is_string($payload['plugin'])) {
                        $pluginInput = $payload['plugin'];
                    } elseif (isset($payload['slug']) && is_string($payload['slug'])) {
                        $pluginInput = $payload['slug'];
                    } elseif (isset($payload['plugin_file']) && is_string($payload['plugin_file'])) {
                        $pluginInput = $payload['plugin_file'];
                    }

                    $resolved = $this->resolve_plugin_from_catalog($pluginInput, $catalog);
                    if (!$resolved) {
                        return [
                            'ok' => false,
                            'error' => 'Unknown or disallowed plugin.',
                            'available_plugins' => array_map(
                                static fn(array $item): string => $item['plugin_file'],
                                $catalog
                            ),
                        ];
                    }

                    $actions = $this->resolve_plugin_actions($resolved, $actionsRegistry);
                    return [
                        'ok' => true,
                        'plugin' => $resolved['plugin_file'],
                        'actions' => array_keys($actions),
                    ];
                },
            ],
            [
                'name' => 'run_plugin_action',
                'description' => 'Run an allowed plugin action (registered by filter navai_voice_plugin_actions).',
                'source' => 'navai-dashboard',
                'callback' => function (array $payload, array $context) use ($catalog, $actionsRegistry) {
                    $pluginInput = '';
                    if (isset($payload['plugin']) && is_string($payload['plugin'])) {
                        $pluginInput = $payload['plugin'];
                    } elseif (isset($payload['slug']) && is_string($payload['slug'])) {
                        $pluginInput = $payload['slug'];
                    } elseif (isset($payload['plugin_file']) && is_string($payload['plugin_file'])) {
                        $pluginInput = $payload['plugin_file'];
                    }

                    $resolved = $this->resolve_plugin_from_catalog($pluginInput, $catalog);
                    if (!$resolved) {
                        return [
                            'ok' => false,
                            'error' => 'Unknown or disallowed plugin.',
                        ];
                    }

                    $actionName = isset($payload['action']) && is_string($payload['action'])
                        ? strtolower(trim($payload['action']))
                        : '';
                    if ($actionName === '') {
                        return [
                            'ok' => false,
                            'error' => 'action is required.',
                        ];
                    }

                    $actions = $this->resolve_plugin_actions($resolved, $actionsRegistry);
                    if (!isset($actions[$actionName]) || !is_callable($actions[$actionName])) {
                        return [
                            'ok' => false,
                            'error' => 'Unknown action for plugin.',
                            'available_actions' => array_keys($actions),
                        ];
                    }

                    $args = [];
                    if (isset($payload['args']) && is_array($payload['args'])) {
                        $args = $payload['args'];
                    } elseif (isset($payload['payload']) && is_array($payload['payload'])) {
                        $args = $payload['payload'];
                    }

                    $result = call_user_func(
                        $actions[$actionName],
                        $args,
                        [
                            'request' => $context['request'] ?? null,
                            'plugin' => $resolved,
                        ]
                    );

                    return [
                        'ok' => true,
                        'plugin' => $resolved['plugin_file'],
                        'action' => $actionName,
                        'result' => $result,
                    ];
                },
            ],
        ];
    }

    /**
     * @return array<int, array{
     *   plugin_file: string,
     *   slug: string,
     *   name: string,
     *   description: string,
     *   version: string,
     *   author: string,
     *   active: bool,
     *   installed: bool,
     *   source: string
     * }>
     */
    private function build_allowed_plugins_catalog(): array
    {
        if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installedPlugins = get_plugins();
        if (!is_array($installedPlugins)) {
            $installedPlugins = [];
        }

        $settings = $this->settings->get_settings();
        $selectedPluginFiles = is_array($settings['allowed_plugin_files'] ?? null)
            ? array_values(array_unique(array_map(
                static fn($item): string => trim(plugin_basename((string) $item)),
                $settings['allowed_plugin_files']
            )))
            : [];
        $manualTokens = $this->parse_manual_plugins((string) ($settings['manual_plugins'] ?? ''));

        $catalogByFile = [];

        foreach ($selectedPluginFiles as $pluginFile) {
            if ($pluginFile === '') {
                continue;
            }

            $entry = $this->resolve_plugin_entry($pluginFile, $installedPlugins, 'dashboard_selection');
            $catalogByFile[$entry['plugin_file']] = $entry;
        }

        foreach ($manualTokens as $token) {
            if ($token === '') {
                continue;
            }

            $matchedPluginFile = $this->resolve_plugin_file_from_token($token, $installedPlugins);
            if ($matchedPluginFile !== null) {
                $entry = $this->resolve_plugin_entry($matchedPluginFile, $installedPlugins, 'manual');
                if (!isset($catalogByFile[$entry['plugin_file']])) {
                    $catalogByFile[$entry['plugin_file']] = $entry;
                }
                continue;
            }

            $slug = $this->plugin_file_to_slug($token);
            $pluginFile = str_contains($token, '/') ? plugin_basename($token) : $slug . '/' . $slug . '.php';
            if (!isset($catalogByFile[$pluginFile])) {
                $catalogByFile[$pluginFile] = [
                    'plugin_file' => $pluginFile,
                    'slug' => $slug,
                    'name' => $slug,
                    'description' => 'Manual plugin entry (not detected in installed plugins).',
                    'version' => '',
                    'author' => '',
                    'active' => false,
                    'installed' => false,
                    'source' => 'manual',
                ];
            }
        }

        return array_values($catalogByFile);
    }

    /**
     * @param array<string, array<string, mixed>> $installedPlugins
     * @return array{
     *   plugin_file: string,
     *   slug: string,
     *   name: string,
     *   description: string,
     *   version: string,
     *   author: string,
     *   active: bool,
     *   installed: bool,
     *   source: string
     * }
     */
    private function resolve_plugin_entry(string $pluginFile, array $installedPlugins, string $source): array
    {
        $normalizedFile = plugin_basename($pluginFile);
        $data = isset($installedPlugins[$normalizedFile]) && is_array($installedPlugins[$normalizedFile])
            ? $installedPlugins[$normalizedFile]
            : [];

        $name = isset($data['Name']) && is_string($data['Name']) && trim($data['Name']) !== ''
            ? trim($data['Name'])
            : $this->plugin_file_to_slug($normalizedFile);
        $description = isset($data['Description']) && is_string($data['Description'])
            ? wp_strip_all_tags($data['Description'])
            : '';
        $version = isset($data['Version']) && is_string($data['Version']) ? trim($data['Version']) : '';
        $author = isset($data['AuthorName']) && is_string($data['AuthorName']) && trim($data['AuthorName']) !== ''
            ? trim($data['AuthorName'])
            : (isset($data['Author']) && is_string($data['Author']) ? wp_strip_all_tags($data['Author']) : '');

        return [
            'plugin_file' => $normalizedFile,
            'slug' => $this->plugin_file_to_slug($normalizedFile),
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'author' => $author,
            'active' => function_exists('is_plugin_active') ? is_plugin_active($normalizedFile) : false,
            'installed' => isset($installedPlugins[$normalizedFile]),
            'source' => $source,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $installedPlugins
     */
    private function resolve_plugin_file_from_token(string $token, array $installedPlugins): ?string
    {
        $normalizedToken = strtolower(trim($token));
        if ($normalizedToken === '') {
            return null;
        }

        if (isset($installedPlugins[$token])) {
            return $token;
        }

        $normalizedBasename = strtolower(plugin_basename($token));
        foreach ($installedPlugins as $pluginFile => $data) {
            if (strtolower($pluginFile) === $normalizedBasename) {
                return $pluginFile;
            }
        }

        foreach ($installedPlugins as $pluginFile => $data) {
            $slug = $this->plugin_file_to_slug($pluginFile);
            if ($slug === $normalizedToken) {
                return $pluginFile;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function parse_manual_plugins(string $value): array
    {
        $parts = preg_split('/[\r\n,]+/', $value) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }

    private function plugin_file_to_slug(string $pluginFile): string
    {
        $normalized = strtolower(plugin_basename($pluginFile));
        if (str_contains($normalized, '/')) {
            $pieces = explode('/', $normalized);
            return sanitize_title($pieces[0]);
        }

        return sanitize_title(preg_replace('/\.php$/', '', $normalized) ?: $normalized);
    }

    /**
     * @param array<int, array<string, mixed>> $catalog
     * @return array<string, mixed>|null
     */
    private function resolve_plugin_from_catalog(string $input, array $catalog): ?array
    {
        $needle = strtolower(trim($input));
        if ($needle === '') {
            return null;
        }

        foreach ($catalog as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $pluginFile = strtolower((string) ($entry['plugin_file'] ?? ''));
            $slug = strtolower((string) ($entry['slug'] ?? ''));
            $name = strtolower((string) ($entry['name'] ?? ''));

            if ($needle === $pluginFile || $needle === $slug || $needle === $name) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, callable>>
     */
    private function get_plugin_actions_registry(): array
    {
        $raw = apply_filters('navai_voice_plugin_actions', []);
        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $pluginKey => $actions) {
            if (!is_array($actions)) {
                continue;
            }

            $pluginId = strtolower(trim((string) $pluginKey));
            if ($pluginId === '') {
                continue;
            }

            foreach ($actions as $actionName => $actionCallback) {
                if (!is_callable($actionCallback)) {
                    continue;
                }

                $actionId = strtolower(trim((string) $actionName));
                if ($actionId === '') {
                    continue;
                }

                if (!isset($normalized[$pluginId])) {
                    $normalized[$pluginId] = [];
                }

                $normalized[$pluginId][$actionId] = $actionCallback;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $plugin
     * @param array<string, array<string, callable>> $actionsRegistry
     * @return array<string, callable>
     */
    private function resolve_plugin_actions(array $plugin, array $actionsRegistry): array
    {
        $pluginFile = strtolower((string) ($plugin['plugin_file'] ?? ''));
        $slug = strtolower((string) ($plugin['slug'] ?? ''));
        $actions = [];

        if ($pluginFile !== '' && isset($actionsRegistry[$pluginFile]) && is_array($actionsRegistry[$pluginFile])) {
            $actions = array_merge($actions, $actionsRegistry[$pluginFile]);
        }

        if ($slug !== '' && isset($actionsRegistry[$slug]) && is_array($actionsRegistry[$slug])) {
            $actions = array_merge($actions, $actionsRegistry[$slug]);
        }

        return $actions;
    }

    /**
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    private function build_allowed_routes_catalog(): array
    {
        $settings = $this->settings->get_settings();
        $selectedIds = $this->get_selected_menu_item_ids($settings);
        if (count($selectedIds) === 0) {
            return [];
        }

        $routes = [];
        $dedupe = [];

        if (function_exists('wp_get_nav_menus') && function_exists('wp_get_nav_menu_items')) {
            $routesById = $this->get_menu_routes_index();
            foreach ($selectedIds as $id) {
                if (!isset($routesById[$id]) || !is_array($routesById[$id])) {
                    continue;
                }

                $this->append_route_if_new($routes, $dedupe, $routesById[$id]);
            }
        }

        return $routes;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, int>
     */
    private function get_selected_menu_item_ids(array $settings): array
    {
        $idsRaw = is_array($settings['allowed_menu_item_ids'] ?? null)
            ? $settings['allowed_menu_item_ids']
            : [];

        $ids = array_map('absint', $idsRaw);
        return array_values(array_filter(array_unique($ids), static fn(int $id): bool => $id > 0));
    }

    /**
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    private function get_menu_routes_index(): array
    {
        $menus = wp_get_nav_menus();
        if (!is_array($menus) || count($menus) === 0) {
            return [];
        }

        $routesById = [];
        foreach ($menus as $menu) {
            if (!isset($menu->term_id)) {
                continue;
            }

            $items = wp_get_nav_menu_items((int) $menu->term_id, ['update_post_term_cache' => false]);
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_object($item) || !isset($item->ID)) {
                    continue;
                }

                $itemId = absint((int) $item->ID);
                if ($itemId <= 0) {
                    continue;
                }

                $route = $this->build_route_from_menu_item($item);
                if (!is_array($route)) {
                    continue;
                }

                $routesById[$itemId] = $route;
            }
        }

        return $routesById;
    }

    /**
     * @param mixed $item
     * @return array{name: string, path: string, description: string, synonyms: array<int, string>}|null
     */
    private function build_route_from_menu_item($item): ?array
    {
        if (!is_object($item) || !isset($item->url, $item->title)) {
            return null;
        }

        $path = esc_url_raw((string) $item->url);
        if (!$this->is_navigable_url($path)) {
            return null;
        }

        if (str_starts_with($path, '/')) {
            $path = home_url($path);
        }

        $name = trim(wp_strip_all_tags((string) $item->title));
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'path' => $path,
            'description' => 'Ruta de menu seleccionada en WordPress.',
            'synonyms' => $this->build_route_synonyms($name, $path),
        ];
    }

    /**
     * @param array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}> $routes
     * @param array<string, bool> $dedupe
     * @param array{name: string, path: string, description: string, synonyms: array<int, string>} $route
     */
    private function append_route_if_new(array &$routes, array &$dedupe, array $route): void
    {
        $dedupeKey = $this->build_route_dedupe_key((string) $route['name'], (string) $route['path']);
        if (isset($dedupe[$dedupeKey])) {
            return;
        }

        $dedupe[$dedupeKey] = true;
        $routes[] = $route;
    }

    private function build_route_dedupe_key(string $name, string $path): string
    {
        return strtolower(trim($name)) . '|' . strtolower(untrailingslashit($path));
    }

    private function is_navigable_url(string $url): bool
    {
        $value = trim($url);
        if ($value === '' || $value === '#') {
            return false;
        }

        $normalized = strtolower($value);
        if (str_starts_with($normalized, 'javascript:')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function build_route_synonyms(string $name, string $path): array
    {
        $synonyms = [];
        $cleanName = sanitize_text_field($name);
        if ($cleanName !== '') {
            $synonyms[] = strtolower($cleanName);
        }

        $pathPart = wp_parse_url($path, PHP_URL_PATH);
        if (is_string($pathPart) && trim($pathPart) !== '') {
            $parts = explode('/', trim($pathPart, '/'));
            foreach ($parts as $part) {
                $token = sanitize_title($part);
                if ($token !== '') {
                    $synonyms[] = str_replace('-', ' ', $token);
                }
            }
        }

        return array_values(array_unique(array_filter($synonyms, static fn(string $value): bool => $value !== '')));
    }

    private function normalize_function_name(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized);
        if (!is_string($normalized)) {
            return '';
        }

        $normalized = trim($normalized, '_');
        if ($normalized === '') {
            return '';
        }

        return substr($normalized, 0, 64);
    }

    private function read_input_or_setting(array $input, string $key, string $fallback): string
    {
        if (isset($input[$key]) && is_string($input[$key])) {
            $value = trim($input[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        $cleanFallback = trim($fallback);
        return $cleanFallback !== '' ? $cleanFallback : '';
    }

    private function build_session_instructions(
        string $baseInstructions,
        string $language,
        string $voiceAccent,
        string $voiceTone
    ): string {
        $lines = [trim($baseInstructions) !== '' ? trim($baseInstructions) : 'You are a helpful assistant.'];

        $language = trim($language);
        if ($language !== '') {
            $lines[] = sprintf('Always reply in %s.', $language);
        }

        $voiceAccent = trim($voiceAccent);
        if ($voiceAccent !== '') {
            $lines[] = sprintf('Use a %s accent while speaking.', $voiceAccent);
        }

        $voiceTone = trim($voiceTone);
        if ($voiceTone !== '') {
            $lines[] = sprintf('Use a %s tone while speaking.', $voiceTone);
        }

        return implode("\n", $lines);
    }

    private function check_rate_limit(): bool
    {
        $ip = $this->get_client_ip();
        $key = 'navai_voice_rl_' . md5($ip);
        $bucket = get_transient($key);
        $now = time();

        if (!is_array($bucket) || !isset($bucket['count'], $bucket['started_at'])) {
            $bucket = [
                'count' => 0,
                'started_at' => $now,
            ];
        }

        $windowSeconds = 60;
        $maxRequestsPerWindow = 30;
        $elapsed = $now - (int) $bucket['started_at'];
        if ($elapsed >= $windowSeconds) {
            $bucket = [
                'count' => 0,
                'started_at' => $now,
            ];
        }

        if ((int) $bucket['count'] >= $maxRequestsPerWindow) {
            return false;
        }

        $bucket['count'] = (int) $bucket['count'] + 1;
        set_transient($key, $bucket, $windowSeconds);

        return true;
    }

    private function get_client_ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_CLIENT_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        foreach ($candidates as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $parts = explode(',', $value);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return 'unknown';
    }
}
