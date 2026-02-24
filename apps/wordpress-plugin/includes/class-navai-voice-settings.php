<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Settings', false)) {
    return;
}

require_once __DIR__ . '/traits/trait-navai-voice-settings-render-page.php';
require_once __DIR__ . '/traits/trait-navai-voice-settings-internals.php';

class Navai_Voice_Settings
{
    public const OPTION_KEY = 'navai_voice_settings';
    public const PAGE_SLUG = 'navai-voice-settings';
    use Navai_Voice_Settings_Render_Page_Trait;
    use Navai_Voice_Settings_Internals_Trait;

    /**
     * @return array<string, mixed>
     */
    public function get_settings(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return wp_parse_args($stored, $this->get_defaults());
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('NAVAI Voice', 'navai-voice'),
            __('NAVAI Voice', 'navai-voice'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page'],
            $this->resolve_admin_menu_icon_url(),
            58
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'navai_voice_settings_group',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->get_defaults(),
            ]
        );
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize_settings($input): array
    {
        $defaults = $this->get_defaults();
        $previous = $this->get_settings();
        $source = is_array($input) ? $input : [];

        $apiKey = isset($source['openai_api_key']) ? trim((string) $source['openai_api_key']) : '';
        if ($apiKey === '') {
            // Avoid accidental key deletion when saving without API key.
            $apiKey = (string) ($previous['openai_api_key'] ?? '');
        }

        $ttlFallback = (int) ($previous['client_secret_ttl'] ?? $defaults['client_secret_ttl']);
        $ttlInput = isset($source['client_secret_ttl']) ? (int) $source['client_secret_ttl'] : $ttlFallback;
        $ttl = ($ttlInput >= 10 && $ttlInput <= 7200) ? $ttlInput : (int) $defaults['client_secret_ttl'];

        $activeTab = isset($source['active_tab']) ? sanitize_key((string) $source['active_tab']) : 'navigation';
        if (!in_array($activeTab, ['navigation', 'plugins', 'settings'], true)) {
            $activeTab = 'navigation';
        }
        $dashboardLanguage = $this->sanitize_dashboard_language(
            $source['dashboard_language'] ?? ($previous['dashboard_language'] ?? $defaults['dashboard_language'])
        );

        $frontendDisplayMode = isset($source['frontend_display_mode'])
            ? sanitize_key((string) $source['frontend_display_mode'])
            : (string) ($previous['frontend_display_mode'] ?? $defaults['frontend_display_mode']);
        if (!in_array($frontendDisplayMode, ['global', 'shortcode'], true)) {
            $frontendDisplayMode = (string) $defaults['frontend_display_mode'];
        }

        $frontendButtonSide = isset($source['frontend_button_side'])
            ? sanitize_key((string) $source['frontend_button_side'])
            : (string) ($previous['frontend_button_side'] ?? $defaults['frontend_button_side']);
        if (!in_array($frontendButtonSide, ['left', 'right'], true)) {
            $frontendButtonSide = (string) $defaults['frontend_button_side'];
        }

        $frontendButtonColorIdle = $this->sanitize_color_value(
            $source['frontend_button_color_idle'] ?? ($previous['frontend_button_color_idle'] ?? $defaults['frontend_button_color_idle']),
            (string) $defaults['frontend_button_color_idle']
        );
        $frontendButtonColorActive = $this->sanitize_color_value(
            $source['frontend_button_color_active'] ?? ($previous['frontend_button_color_active'] ?? $defaults['frontend_button_color_active']),
            (string) $defaults['frontend_button_color_active']
        );
        $frontendShowButtonText = !empty($source['frontend_show_button_text']);
        $frontendButtonTextIdle = $this->read_text_value(
            $source,
            $previous,
            $defaults,
            'frontend_button_text_idle',
            true
        );
        $frontendButtonTextActive = $this->read_text_value(
            $source,
            $previous,
            $defaults,
            'frontend_button_text_active',
            true
        );
        $privateRoutePluginCatalog = $this->get_private_route_plugin_catalog($previous['private_custom_routes'] ?? []);
        $availableRoles = $this->get_available_roles();
        $privateCustomRoutes = $this->sanitize_private_custom_routes(
            $source['private_custom_routes'] ?? [],
            $privateRoutePluginCatalog,
            $availableRoles
        );
        $pluginFunctionPluginCatalog = $this->get_plugin_function_plugin_catalog($previous['plugin_custom_functions'] ?? []);
        $pluginCustomFunctions = $this->sanitize_plugin_custom_functions(
            $source['plugin_custom_functions'] ?? [],
            $pluginFunctionPluginCatalog,
            $availableRoles
        );
        $draftSettings = $previous;
        $draftSettings['private_custom_routes'] = $privateCustomRoutes;
        $draftCatalog = $this->get_navigation_catalog($draftSettings);
        $draftIndex = is_array($draftCatalog['index'] ?? null) ? $draftCatalog['index'] : [];
        $routeDescriptions = $this->sanitize_route_descriptions(
            $source['route_descriptions'] ?? ($previous['route_descriptions'] ?? []),
            array_keys($draftIndex)
        );
        $pluginFunctionKeys = array_values(array_filter(array_map(
            static fn(array $item): string => 'pluginfn:' . sanitize_key((string) ($item['id'] ?? '')),
            $pluginCustomFunctions
        )));
        $allowedPluginFunctionKeys = $this->sanitize_plugin_function_keys(
            $source['allowed_plugin_function_keys'] ?? [],
            $pluginFunctionKeys
        );
        $allowedPluginFilesInput = array_key_exists('allowed_plugin_files', $source)
            ? $source['allowed_plugin_files']
            : ($previous['allowed_plugin_files'] ?? []);
        $manualPluginsInput = array_key_exists('manual_plugins', $source)
            ? (string) $source['manual_plugins']
            : (string) ($previous['manual_plugins'] ?? '');

        $allowedRouteKeys = $this->sanitize_route_keys($source['allowed_route_keys'] ?? []);
        if (count($allowedRouteKeys) === 0 && array_key_exists('allowed_menu_item_ids', $source)) {
            $allowedRouteKeys = $this->map_legacy_menu_item_ids_to_route_keys(
                $this->sanitize_menu_item_ids($source['allowed_menu_item_ids'])
            );
        }

        return [
            'openai_api_key' => $apiKey,
            'default_model' => $this->read_text_value($source, $previous, $defaults, 'default_model', true),
            'default_voice' => $this->read_text_value($source, $previous, $defaults, 'default_voice', true),
            'default_instructions' => $this->read_textarea_value($source, $previous, $defaults, 'default_instructions', true),
            'default_language' => $this->read_text_value($source, $previous, $defaults, 'default_language', false),
            'default_voice_accent' => $this->read_text_value($source, $previous, $defaults, 'default_voice_accent', false),
            'default_voice_tone' => $this->read_text_value($source, $previous, $defaults, 'default_voice_tone', false),
            'client_secret_ttl' => $ttl,
            'allow_public_client_secret' => !empty($source['allow_public_client_secret']),
            'allow_public_functions' => !empty($source['allow_public_functions']),
            'allowed_menu_item_ids' => $this->sanitize_menu_item_ids($source['allowed_menu_item_ids'] ?? []),
            'allowed_route_keys' => $allowedRouteKeys,
            'allowed_plugin_files' => $this->sanitize_plugin_files($allowedPluginFilesInput),
            'manual_plugins' => $this->sanitize_manual_plugins($manualPluginsInput),
            'plugin_custom_functions' => $pluginCustomFunctions,
            'allowed_plugin_function_keys' => $allowedPluginFunctionKeys,
            'frontend_display_mode' => $frontendDisplayMode,
            'frontend_button_side' => $frontendButtonSide,
            'frontend_button_color_idle' => $frontendButtonColorIdle,
            'frontend_button_color_active' => $frontendButtonColorActive,
            'frontend_show_button_text' => $frontendShowButtonText,
            'frontend_button_text_idle' => $frontendButtonTextIdle,
            'frontend_button_text_active' => $frontendButtonTextActive,
            'private_custom_routes' => $privateCustomRoutes,
            'route_descriptions' => $routeDescriptions,
            'frontend_allowed_roles' => $this->sanitize_frontend_roles($source['frontend_allowed_roles'] ?? []),
            'dashboard_language' => $dashboardLanguage,
            'active_tab' => $activeTab,
        ];
    }

    /**
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    public function get_allowed_routes_for_current_user(): array
    {
        $settings = $this->get_settings();
        $catalog = $this->get_navigation_catalog($settings);
        $index = is_array($catalog['index'] ?? null) ? $catalog['index'] : [];
        $currentRoles = $this->get_current_user_roles();
        $isAdministrator = in_array('administrator', $currentRoles, true);

        $selectedRouteKeys = $this->get_selected_route_keys($settings);
        if (!$isAdministrator && count($selectedRouteKeys) === 0) {
            return [];
        }

        $routeKeys = $isAdministrator
            ? array_values(array_filter(array_keys($index), static fn($key): bool => is_string($key) && trim((string) $key) !== ''))
            : $selectedRouteKeys;

        $routes = [];
        $dedupe = [];
        foreach ($routeKeys as $routeKey) {
            if (!isset($index[$routeKey]) || !is_array($index[$routeKey])) {
                continue;
            }

            $item = $index[$routeKey];
            $visibility = isset($item['visibility']) ? (string) $item['visibility'] : 'public';
            $roles = is_array($item['roles'] ?? null) ? array_map('sanitize_key', $item['roles']) : [];

            if ($visibility === 'private' && !$isAdministrator) {
                if (count($currentRoles) === 0 || count(array_intersect($currentRoles, $roles)) === 0) {
                    continue;
                }
            }

            $name = sanitize_text_field((string) ($item['title'] ?? ''));
            $path = esc_url_raw((string) ($item['url'] ?? ''));
            if ($name === '' || !$this->is_navigable_url($path)) {
                continue;
            }
            if (str_starts_with($path, '/')) {
                $path = home_url($path);
            }

            $description = sanitize_text_field((string) ($item['description'] ?? ''));
            if ($description === '') {
                $description = $visibility === 'private'
                    ? __('Ruta privada seleccionada en WordPress.', 'navai-voice')
                    : __('Ruta publica seleccionada en WordPress.', 'navai-voice');
            }

            $synonyms = [];
            if (isset($item['synonyms']) && is_array($item['synonyms'])) {
                foreach ($item['synonyms'] as $synonym) {
                    if (!is_string($synonym)) {
                        continue;
                    }
                    $clean = sanitize_text_field($synonym);
                    if ($clean !== '') {
                        $synonyms[] = $clean;
                    }
                }
            }
            if (count($synonyms) === 0) {
                $synonyms = $this->build_route_synonyms($name, $path);
            }

            $dedupeKey = $this->build_route_dedupe_key($name, $path);
            if (isset($dedupe[$dedupeKey])) {
                continue;
            }
            $dedupe[$dedupeKey] = true;

            $routes[] = [
                'name' => $name,
                'path' => $path,
                'description' => $description,
                'synonyms' => array_values(array_unique($synonyms)),
            ];
        }

        return $routes;
    }

    /**
     * @return array<int, array{
     *   key: string,
     *   function_name: string,
     *   function_code: string,
     *   plugin_key: string,
     *   plugin_label: string,
     *   role: string,
     *   description: string
     * }>
     */
    public function get_allowed_plugin_functions_for_current_user(): array
    {
        $settings = $this->get_settings();
        $customFunctions = $this->get_plugin_custom_functions($settings);
        if (count($customFunctions) === 0) {
            return [];
        }

        $selectedKeys = $this->get_selected_plugin_function_keys($settings, $customFunctions);
        if (count($selectedKeys) === 0) {
            return [];
        }

        $selectedLookup = array_fill_keys($selectedKeys, true);
        $currentRoles = $this->get_current_user_roles();
        $isAdministrator = in_array('administrator', $currentRoles, true);

        $items = [];
        foreach ($customFunctions as $item) {
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $itemKey = 'pluginfn:' . $id;
            if (!isset($selectedLookup[$itemKey])) {
                continue;
            }

            $role = sanitize_key((string) ($item['role'] ?? ''));
            if (!$isAdministrator) {
                if ($role === '' || !in_array($role, $currentRoles, true)) {
                    continue;
                }
            }

            $functionName = $this->sanitize_plugin_function_name((string) ($item['function_name'] ?? ''));
            if ($functionName === '') {
                $functionName = $this->build_plugin_custom_function_name($id);
            }
            $functionCode = $this->sanitize_plugin_function_code((string) ($item['function_code'] ?? ''));
            $pluginKey = $this->sanitize_private_plugin_key((string) ($item['plugin_key'] ?? 'wp-core'));
            $pluginLabel = sanitize_text_field((string) ($item['plugin_label'] ?? ''));
            $description = sanitize_text_field((string) ($item['description'] ?? ''));
            if ($functionName === '' || $functionCode === '' || $pluginKey === '') {
                continue;
            }
            if ($pluginLabel === '') {
                $pluginLabel = $this->resolve_private_plugin_label($pluginKey, '', []);
            }
            if ($description === '') {
                $description = __('Funcion personalizada de plugin.', 'navai-voice');
            }

            $items[] = [
                'key' => $itemKey,
                'function_name' => $functionName,
                'function_code' => $functionCode,
                'plugin_key' => $pluginKey,
                'plugin_label' => $pluginLabel,
                'role' => $role,
                'description' => $description,
            ];
        }

        return $items;
    }
}
