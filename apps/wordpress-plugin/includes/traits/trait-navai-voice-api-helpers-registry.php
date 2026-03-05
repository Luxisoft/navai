<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Navai_Voice_API_Helpers_Registry_Trait
{
    private function get_functions_registry(): array
    {
        $warnings = [];
        $byName = [];
        $ordered = [];

        $builtinDefinitions = $this->build_plugin_bridge_functions();
        $mcpDefinitions = [];
        if (
            isset($this->mcpService)
            && $this->mcpService instanceof Navai_Voice_MCP_Service
            && method_exists($this, 'should_enforce_mcp')
            && $this->should_enforce_mcp()
        ) {
            $settings = isset($this->settings) && $this->settings instanceof Navai_Voice_Settings
                ? $this->settings->get_settings()
                : [];
            $roles = method_exists($this, 'get_request_roles') ? $this->get_request_roles() : [];
            $mcpDefinitions = $this->mcpService->build_runtime_tool_definitions($settings, $roles);
        }
        $rawDefinitions = apply_filters('navai_voice_functions_registry', []);
        if (!is_array($rawDefinitions)) {
            $rawDefinitions = [];
        }

        $allDefinitions = array_merge($builtinDefinitions, $mcpDefinitions, $rawDefinitions);
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
            if (array_key_exists('requires_approval', $item)) {
                $definition['requires_approval'] = !empty($item['requires_approval']);
            }
            if (array_key_exists('timeout_seconds', $item)) {
                $definition['timeout_seconds'] = is_numeric($item['timeout_seconds']) ? (int) $item['timeout_seconds'] : 0;
            }
            if (array_key_exists('execution_scope', $item)) {
                $definition['execution_scope'] = is_string($item['execution_scope']) ? $item['execution_scope'] : 'both';
            }
            if (array_key_exists('retries', $item)) {
                $definition['retries'] = is_numeric($item['retries']) ? (int) $item['retries'] : 0;
            }
            if (array_key_exists('argument_schema', $item) && is_array($item['argument_schema'])) {
                $definition['argument_schema'] = $item['argument_schema'];
            }
            if (array_key_exists('function_id', $item)) {
                $definition['function_id'] = is_numeric($item['function_id']) ? (int) $item['function_id'] : null;
            }

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
        $customPluginFunctions = $this->settings->get_allowed_plugin_functions_for_current_user();
        $allowedCustomActions = $this->build_allowed_custom_actions_map($customPluginFunctions);
        $customDefinitions = $this->build_custom_plugin_function_definitions(
            $customPluginFunctions,
            $catalog,
            $actionsRegistry
        );

        $builtinDefinitions = [
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
                    if (count($allowedCustomActions) > 0) {
                        $actions = $this->filter_actions_by_allowed_map($resolved, $actions, $allowedCustomActions);
                    }
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
                    if (count($allowedCustomActions) > 0) {
                        $actions = $this->filter_actions_by_allowed_map($resolved, $actions, $allowedCustomActions);
                    }
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

        // Do not expose plugin bridge helper functions to the AI runtime.
        // The recommended path is custom dashboard functions (JavaScript) controlled by the user.
        return $customDefinitions;
    }

    /**
     * @param array<int, array<string, mixed>> $customPluginFunctions
     * @return array<string, array<string, bool>>
     */
    private function build_allowed_custom_actions_map(array $customPluginFunctions): array
    {
        $map = [];

        foreach ($customPluginFunctions as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pluginKey = strtolower(trim((string) ($item['plugin_key'] ?? '')));
            $actionName = $this->extract_custom_action_name($item);
            if ($pluginKey === '' || $actionName === '') {
                continue;
            }

            if (!isset($map[$pluginKey])) {
                $map[$pluginKey] = [];
            }
            $map[$pluginKey][$actionName] = true;

            if (str_starts_with($pluginKey, 'plugin:')) {
                $slug = substr($pluginKey, 7);
                if ($slug !== '') {
                    if (!isset($map[$slug])) {
                        $map[$slug] = [];
                    }
                    $map[$slug][$actionName] = true;

                    $pluginFile = $slug . '/' . $slug . '.php';
                    if (!isset($map[$pluginFile])) {
                        $map[$pluginFile] = [];
                    }
                    $map[$pluginFile][$actionName] = true;
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extract_custom_action_name(array $item): string
    {
        $legacy = strtolower(trim((string) ($item['plugin_function'] ?? '')));
        if ($legacy !== '') {
            return $legacy;
        }

        $functionCode = trim((string) ($item['function_code'] ?? ''));
        if ($functionCode === '') {
            return '';
        }

        if (preg_match('/^\s*@action:\s*([a-z0-9_.:-]+)/i', $functionCode, $matches) === 1) {
            return strtolower(trim((string) ($matches[1] ?? '')));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $plugin
     * @param array<string, callable> $actions
     * @param array<string, array<string, bool>> $allowedMap
     * @return array<string, callable>
     */
    private function filter_actions_by_allowed_map(array $plugin, array $actions, array $allowedMap): array
    {
        if (count($actions) === 0 || count($allowedMap) === 0) {
            return [];
        }

        $identifiers = $this->resolve_plugin_identifiers_for_map($plugin);
        if (count($identifiers) === 0) {
            return [];
        }

        $allowedActions = [];
        foreach ($identifiers as $identifier) {
            if (isset($allowedMap[$identifier]) && is_array($allowedMap[$identifier])) {
                $allowedActions = array_merge($allowedActions, array_keys($allowedMap[$identifier]));
            }
        }
        $allowedLookup = array_fill_keys(array_values(array_unique($allowedActions)), true);

        $filtered = [];
        foreach ($actions as $actionName => $callback) {
            $actionId = strtolower(trim((string) $actionName));
            if ($actionId !== '' && isset($allowedLookup[$actionId])) {
                $filtered[$actionId] = $callback;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $plugin
     * @return array<int, string>
     */
    private function resolve_plugin_identifiers_for_map(array $plugin): array
    {
        $identifiers = [];

        $pluginKey = strtolower(trim((string) ($plugin['plugin_key'] ?? '')));
        if ($pluginKey !== '') {
            $identifiers[] = $pluginKey;
            if (str_starts_with($pluginKey, 'plugin:')) {
                $slugFromKey = substr($pluginKey, 7);
                if ($slugFromKey !== '') {
                    $identifiers[] = $slugFromKey;
                    $identifiers[] = $slugFromKey . '/' . $slugFromKey . '.php';
                }
            }
        }

        $pluginFile = strtolower(trim((string) ($plugin['plugin_file'] ?? '')));
        if ($pluginFile !== '') {
            $identifiers[] = $pluginFile;
        }

        $slug = strtolower(trim((string) ($plugin['slug'] ?? '')));
        if ($slug !== '') {
            $identifiers[] = $slug;
            $identifiers[] = $slug . '/' . $slug . '.php';
        }

        return array_values(array_unique(array_filter($identifiers, static fn(string $value): bool => $value !== '')));
    }

    /**
     * @param array<int, array<string, mixed>> $customPluginFunctions
     * @param array<int, array<string, mixed>> $catalog
     * @param array<string, array<string, callable>> $actionsRegistry
     * @return array<int, array{name: string, description: string, source: string, callback: callable}>
     */
    private function build_custom_plugin_function_definitions(
        array $customPluginFunctions,
        array $catalog,
        array $actionsRegistry
    ): array {
        $definitions = [];

        foreach ($customPluginFunctions as $item) {
            if (!is_array($item)) {
                continue;
            }

            $functionName = $this->normalize_function_name((string) ($item['function_name'] ?? ''));
            $functionCode = trim((string) ($item['function_code'] ?? ''));
            $description = isset($item['description']) && is_string($item['description'])
                ? trim($item['description'])
                : '';
            if ($functionName === '' || $functionCode === '') {
                continue;
            }
            if ($description === '') {
                $description = 'Custom plugin function configured in NAVAI dashboard.';
            }

            $pluginConfig = $this->resolve_plugin_from_custom_function($item, $catalog);
            if ($pluginConfig === null) {
                continue;
            }

            $definitions[] = [
                'name' => $functionName,
                'description' => $description,
                'source' => 'navai-dashboard-custom',
                'requires_approval' => !empty($item['requires_approval']),
                'timeout_seconds' => isset($item['timeout_seconds']) && is_numeric($item['timeout_seconds'])
                    ? (int) $item['timeout_seconds']
                    : 0,
                'execution_scope' => isset($item['execution_scope']) && is_string($item['execution_scope'])
                    ? $item['execution_scope']
                    : 'both',
                'retries' => isset($item['retries']) && is_numeric($item['retries'])
                    ? (int) $item['retries']
                    : 0,
                'argument_schema' => isset($item['argument_schema']) && is_array($item['argument_schema'])
                    ? $item['argument_schema']
                    : null,
                'callback' => function (array $payload, array $context) use ($pluginConfig, $functionCode, $actionsRegistry) {
                    $args = [];
                    if (isset($payload['args']) && is_array($payload['args'])) {
                        $args = $payload['args'];
                    } elseif (isset($payload['payload']) && is_array($payload['payload'])) {
                        $args = $payload['payload'];
                    } elseif (is_array($payload)) {
                        $args = $payload;
                    }

                    $normalizedCode = trim($functionCode);
                    $legacyAction = '';
                    if (preg_match('/^\s*@action:\s*([a-z0-9_.:-]+)/i', $normalizedCode, $matches) === 1) {
                        $legacyAction = strtolower(trim((string) ($matches[1] ?? '')));
                    }

                    if ($legacyAction !== '') {
                        $actions = $this->resolve_plugin_actions($pluginConfig, $actionsRegistry);
                        if (!isset($actions[$legacyAction]) || !is_callable($actions[$legacyAction])) {
                            return [
                                'ok' => false,
                                'error' => 'Configured plugin function is not registered or callable.',
                                'plugin' => $pluginConfig['plugin_file'] ?? '',
                                'action' => $legacyAction,
                            ];
                        }

                        $result = call_user_func(
                            $actions[$legacyAction],
                            $args,
                            [
                                'request' => $context['request'] ?? null,
                                'plugin' => $pluginConfig,
                            ]
                        );

                        return [
                            'ok' => true,
                            'plugin' => $pluginConfig['plugin_file'] ?? '',
                            'action' => $legacyAction,
                            'result' => $result,
                        ];
                    }

                    if (preg_match('/^\s*(<\?(php)?|php\s*:)/i', $normalizedCode) === 1) {
                        return [
                            'ok' => false,
                            'error' => 'Only JavaScript custom code is supported.',
                        ];
                    }

                    $jsCode = preg_replace('/^\s*js\s*:/i', '', $normalizedCode, 1);
                    $jsCode = is_string($jsCode) ? trim($jsCode) : '';
                    if ($jsCode === '') {
                        return [
                            'ok' => false,
                            'error' => 'Empty JavaScript code.',
                        ];
                    }

                    return [
                        'ok' => true,
                        'plugin' => $pluginConfig['plugin_file'] ?? '',
                        'execution_mode' => 'client_js',
                        'code' => $jsCode,
                        'payload' => $args,
                    ];
                },
            ];
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @param array<string, mixed> $pluginConfig
     * @return array<string, mixed>
     */
    private function execute_php_custom_function_code(
        string $code,
        array $payload,
        array $context,
        array $pluginConfig
    ): array {
        $phpCode = preg_replace('/^\s*php\s*:/i', '', $code, 1);
        $phpCode = is_string($phpCode) ? $phpCode : $code;
        $phpCode = preg_replace('/^\s*<\?(php)?/i', '', $phpCode, 1);
        $phpCode = is_string($phpCode) ? $phpCode : '';
        $phpCode = preg_replace('/\?>\s*$/', '', $phpCode, 1);
        $phpCode = is_string($phpCode) ? trim($phpCode) : '';

        if ($phpCode === '') {
            return [
                'ok' => false,
                'error' => 'Empty PHP code.',
            ];
        }

        try {
            $request = $context['request'] ?? null;
            $plugin = $pluginConfig;
            $payloadData = $payload;
            $contextData = $context;

            $stdout = '';
            ob_start();
            try {
                // Admin-configured trusted code. Variables available: $payloadData, $contextData, $plugin, $request.
                $result = eval($phpCode);
                $stdout = (string) ob_get_clean();
            } catch (Throwable $throwable) {
                ob_end_clean();
                throw $throwable;
            }

            $response = [
                'ok' => true,
                'plugin' => $pluginConfig['plugin_file'] ?? '',
                'execution_mode' => 'php',
                'result' => $result,
            ];
            if ($stdout !== '') {
                $response['stdout'] = $stdout;
            }

            return $response;
        } catch (Throwable $error) {
            return [
                'ok' => false,
                'plugin' => $pluginConfig['plugin_file'] ?? '',
                'execution_mode' => 'php',
                'error' => 'Custom PHP code execution failed.',
                'details' => $error->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $customFunction
     * @param array<int, array<string, mixed>> $catalog
     * @return array<string, mixed>|null
     */
    private function resolve_plugin_from_custom_function(array $customFunction, array $catalog): ?array
    {
        $pluginKey = strtolower(trim((string) ($customFunction['plugin_key'] ?? '')));
        if ($pluginKey === '') {
            return null;
        }

        foreach ($catalog as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryPluginKey = strtolower(trim((string) ($entry['plugin_key'] ?? '')));
            if ($entryPluginKey !== '' && $entryPluginKey === $pluginKey) {
                return $entry;
            }
        }

        if ($pluginKey === 'wp-core') {
            return [
                'plugin_key' => 'wp-core',
                'plugin_file' => 'wp-core/wp-core.php',
                'slug' => 'wp-core',
                'name' => 'WordPress / Sitio',
                'description' => '',
                'version' => '',
                'author' => '',
                'active' => true,
                'installed' => true,
                'source' => 'custom',
            ];
        }

        $slug = str_starts_with($pluginKey, 'plugin:') ? substr($pluginKey, 7) : $pluginKey;
        if ($slug === '') {
            return null;
        }

        $name = isset($customFunction['plugin_label']) && is_string($customFunction['plugin_label'])
            ? trim($customFunction['plugin_label'])
            : $slug;
        if ($name === '') {
            $name = $slug;
        }

        return [
            'plugin_key' => 'plugin:' . $slug,
            'plugin_file' => $slug . '/' . $slug . '.php',
            'slug' => $slug,
            'name' => $name,
            'description' => '',
            'version' => '',
            'author' => '',
            'active' => false,
            'installed' => false,
            'source' => 'custom',
        ];
    }

    /**
     * @return array<int, array{
     *   plugin_key: string,
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
}

