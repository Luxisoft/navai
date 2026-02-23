<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Settings', false)) {
    return;
}

class Navai_Voice_Settings
{
    public const OPTION_KEY = 'navai_voice_settings';
    public const PAGE_SLUG = 'navai-voice-settings';

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
            'allowed_plugin_files' => $this->sanitize_plugin_files($source['allowed_plugin_files'] ?? []),
            'manual_plugins' => $this->sanitize_manual_plugins((string) ($source['manual_plugins'] ?? '')),
            'frontend_display_mode' => $frontendDisplayMode,
            'frontend_button_side' => $frontendButtonSide,
            'frontend_button_color_idle' => $frontendButtonColorIdle,
            'frontend_button_color_active' => $frontendButtonColorActive,
            'frontend_show_button_text' => $frontendShowButtonText,
            'frontend_button_text_idle' => $frontendButtonTextIdle,
            'frontend_button_text_active' => $frontendButtonTextActive,
            'private_custom_routes' => $privateCustomRoutes,
            'frontend_allowed_roles' => $this->sanitize_frontend_roles($source['frontend_allowed_roles'] ?? []),
            'active_tab' => $activeTab,
        ];
    }

    /**
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    public function get_allowed_routes_for_current_user(): array
    {
        $settings = $this->get_settings();
        $catalog = $this->get_navigation_catalog();
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

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        $allowedRouteKeys = $this->get_selected_route_keys($settings);
        $allowedPluginFiles = array_map('strval', is_array($settings['allowed_plugin_files']) ? $settings['allowed_plugin_files'] : []);
        $allowedFrontendRoles = array_map('strval', is_array($settings['frontend_allowed_roles']) ? $settings['frontend_allowed_roles'] : []);
        $activeTab = is_string($settings['active_tab'] ?? null) ? (string) $settings['active_tab'] : 'navigation';
        $frontendDisplayMode = is_string($settings['frontend_display_mode'] ?? null) ? (string) $settings['frontend_display_mode'] : 'global';
        $frontendButtonSide = is_string($settings['frontend_button_side'] ?? null) ? (string) $settings['frontend_button_side'] : 'left';
        $frontendButtonColorIdle = $this->sanitize_color_value($settings['frontend_button_color_idle'] ?? null, '#1263dc');
        $frontendButtonColorActive = $this->sanitize_color_value($settings['frontend_button_color_active'] ?? null, '#10883f');
        $frontendShowButtonText = !empty($settings['frontend_show_button_text']);
        $frontendButtonTextIdle = sanitize_text_field((string) ($settings['frontend_button_text_idle'] ?? 'Hablar con NAVAI'));
        if (trim($frontendButtonTextIdle) === '') {
            $frontendButtonTextIdle = 'Hablar con NAVAI';
        }
        $frontendButtonTextActive = sanitize_text_field((string) ($settings['frontend_button_text_active'] ?? 'Detener NAVAI'));
        if (trim($frontendButtonTextActive) === '') {
            $frontendButtonTextActive = 'Detener NAVAI';
        }
        if (!in_array($activeTab, ['navigation', 'plugins', 'settings'], true)) {
            $activeTab = 'navigation';
        }
        if (!in_array($frontendDisplayMode, ['global', 'shortcode'], true)) {
            $frontendDisplayMode = 'global';
        }
        if (!in_array($frontendButtonSide, ['left', 'right'], true)) {
            $frontendButtonSide = 'left';
        }

        $availableRoles = $this->get_available_roles();
        $privateCustomRoutes = $this->get_private_custom_routes($settings);
        $privateRoutePluginCatalog = $this->get_private_route_plugin_catalog($privateCustomRoutes);
        $navigationCatalog = $this->get_navigation_catalog();
        $publicRoutes = is_array($navigationCatalog['public'] ?? null) ? $navigationCatalog['public'] : [];
        $privateRoutes = is_array($navigationCatalog['private'] ?? null) ? $navigationCatalog['private'] : [];

        $publicRouteGroups = $this->build_navigation_route_groups($publicRoutes);
        $privateRouteGroups = $this->build_navigation_route_groups($privateRoutes);
        $publicPluginOptions = $this->build_navigation_plugin_options($publicRouteGroups);
        $privatePluginOptions = $this->build_navigation_plugin_options($privateRouteGroups);
        $privateRoleOptions = $this->build_navigation_private_role_options($privateRoutes, $availableRoles);
        $installedPlugins = $this->get_installed_plugins();
        ?>
        <div class="wrap navai-admin-wrap">
            <div class="navai-admin-hero">
                <div class="navai-admin-banner-wrap">
                    <img
                        class="navai-admin-banner"
                        src="<?php echo esc_url(NAVAI_VOICE_URL . 'assets/img/navai.png'); ?>"
                        alt="<?php echo esc_attr__('NAVAI', 'navai-voice'); ?>"
                    />
                </div>
            </div>

            <hr class="navai-admin-divider" />

            <form action="options.php" method="post" class="navai-admin-form">
                <?php settings_fields('navai_voice_settings_group'); ?>
                <input
                    type="hidden"
                    id="navai-active-tab-input"
                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[active_tab]"
                    value="<?php echo esc_attr($activeTab); ?>"
                />

                <div class="navai-admin-tab-buttons" role="tablist" aria-label="<?php echo esc_attr__('NAVAI sections', 'navai-voice'); ?>">
                    <button type="button" class="button button-secondary navai-admin-tab-button" data-navai-tab="navigation">
                        <?php echo esc_html__('Navegacion', 'navai-voice'); ?>
                    </button>
                    <button type="button" class="button button-secondary navai-admin-tab-button" data-navai-tab="plugins">
                        <?php echo esc_html__('Plugins', 'navai-voice'); ?>
                    </button>
                    <button type="button" class="button button-secondary navai-admin-tab-button" data-navai-tab="settings">
                        <?php echo esc_html__('Ajustes', 'navai-voice'); ?>
                    </button>
                </div>

                <section class="navai-admin-panel" data-navai-panel="navigation">
                    <h2><?php echo esc_html__('Navegacion', 'navai-voice'); ?></h2>
                    <p><?php echo esc_html__('Selecciona rutas permitidas para la tool navigate_to.', 'navai-voice'); ?></p>

                    <div class="navai-admin-card navai-nav-card">
                        <div class="navai-nav-tabs" role="tablist" aria-label="<?php echo esc_attr__('Tipos de menus', 'navai-voice'); ?>">
                            <button type="button" class="button button-secondary navai-nav-tab-button" data-navai-nav-tab="public">
                                <?php echo esc_html__('Menus publicos', 'navai-voice'); ?>
                            </button>
                            <button type="button" class="button button-secondary navai-nav-tab-button" data-navai-nav-tab="private">
                                <?php echo esc_html__('Menus privados', 'navai-voice'); ?>
                            </button>
                        </div>

                        <div class="navai-nav-subpanel" data-navai-nav-panel="public">
                            <p class="navai-admin-description"><?php echo esc_html__('Disponibles para visitantes, usuarios e invitados.', 'navai-voice'); ?></p>

                            <div class="navai-nav-actions">
                                <button
                                    type="button"
                                    class="button button-secondary navai-nav-check-action"
                                    data-navai-check-action="scope-select"
                                    data-navai-nav-scope="public"
                                >
                                    <?php echo esc_html__('Seleccionar todo', 'navai-voice'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="button button-secondary navai-nav-check-action"
                                    data-navai-check-action="scope-deselect"
                                    data-navai-nav-scope="public"
                                >
                                    <?php echo esc_html__('Deseleccionar todo', 'navai-voice'); ?>
                                </button>
                            </div>

                            <div class="navai-nav-filters">
                                <label>
                                    <span><?php echo esc_html__('Buscar', 'navai-voice'); ?></span>
                                    <input
                                        type="search"
                                        class="regular-text navai-nav-filter-text"
                                        data-navai-nav-scope="public"
                                        placeholder="<?php echo esc_attr__('Filtrar por texto...', 'navai-voice'); ?>"
                                    />
                                </label>
                                <label>
                                    <span><?php echo esc_html__('Plugin', 'navai-voice'); ?></span>
                                    <select class="navai-nav-filter-plugin" data-navai-nav-scope="public">
                                        <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                                        <?php foreach ($publicPluginOptions as $pluginOption) : ?>
                                            <option value="<?php echo esc_attr((string) ($pluginOption['key'] ?? '')); ?>">
                                                <?php echo esc_html((string) ($pluginOption['label'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>

                            <?php if (count($publicRouteGroups) === 0) : ?>
                                <p><?php echo esc_html__('No se encontraron menus publicos de WordPress.', 'navai-voice'); ?></p>
                            <?php else : ?>
                                <div class="navai-nav-groups" data-navai-nav-scope="public">
                                    <?php foreach ($publicRouteGroups as $group) : ?>
                                        <?php
                                        $groupKey = (string) ($group['plugin_key'] ?? '');
                                        $groupLabel = (string) ($group['plugin_label'] ?? '');
                                        $groupRoutes = is_array($group['routes'] ?? null) ? $group['routes'] : [];
                                        if ($groupKey === '' || count($groupRoutes) === 0) {
                                            continue;
                                        }
                                        ?>
                                        <section class="navai-nav-route-group" data-nav-plugin="<?php echo esc_attr($groupKey); ?>">
                                            <div class="navai-nav-group-head">
                                                <h4><?php echo esc_html($groupLabel); ?></h4>
                                                <div class="navai-nav-actions navai-nav-actions--inline">
                                                    <button
                                                        type="button"
                                                        class="button button-secondary navai-nav-check-action"
                                                        data-navai-check-action="group-select"
                                                        data-navai-nav-scope="public"
                                                    >
                                                        <?php echo esc_html__('Seleccionar', 'navai-voice'); ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="button button-secondary navai-nav-check-action"
                                                        data-navai-check-action="group-deselect"
                                                        data-navai-nav-scope="public"
                                                    >
                                                        <?php echo esc_html__('Deseleccionar', 'navai-voice'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="navai-admin-menu-grid">
                                                <?php foreach ($groupRoutes as $item) : ?>
                                                    <?php
                                                    $routeKey = (string) ($item['key'] ?? '');
                                                    if ($routeKey === '') {
                                                        continue;
                                                    }
                                                    $routeTitle = sanitize_text_field((string) ($item['title'] ?? ''));
                                                    $routeUrl = esc_url_raw((string) ($item['url'] ?? ''));
                                                    $routeSynonyms = is_array($item['synonyms'] ?? null) ? $item['synonyms'] : [];
                                                    $isChecked = in_array($routeKey, $allowedRouteKeys, true);
                                                    $searchText = trim(implode(' ', array_filter([
                                                        $routeTitle,
                                                        $routeUrl,
                                                        implode(' ', array_map('sanitize_text_field', $routeSynonyms)),
                                                    ])));
                                                    $urlBoxId = 'navai-route-url-' . md5('public|' . $routeKey);
                                                    ?>
                                                    <label
                                                        class="navai-admin-check navai-admin-check-block navai-nav-route-item"
                                                        data-nav-plugin="<?php echo esc_attr($groupKey); ?>"
                                                        data-nav-roles=""
                                                        data-nav-search="<?php echo esc_attr($searchText); ?>"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_route_keys][]"
                                                            value="<?php echo esc_attr($routeKey); ?>"
                                                            <?php checked($isChecked, true); ?>
                                                        />
                                                        <span class="navai-nav-route-main">
                                                            <strong><?php echo esc_html($routeTitle); ?></strong>
                                                        </span>
                                                        <button
                                                            type="button"
                                                            class="button-link navai-nav-url-button"
                                                            data-navai-url-target="<?php echo esc_attr($urlBoxId); ?>"
                                                        >
                                                            <?php echo esc_html__('URL', 'navai-voice'); ?>
                                                        </button>
                                                        <div class="navai-nav-url-box" id="<?php echo esc_attr($urlBoxId); ?>" hidden>
                                                            <code><?php echo esc_html($routeUrl); ?></code>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </section>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="navai-nav-subpanel" data-navai-nav-panel="private">
                            <p class="navai-admin-description"><?php echo esc_html__('Rutas privadas personalizadas por rol.', 'navai-voice'); ?></p>

                            <div class="navai-private-routes-builder" data-next-index="<?php echo esc_attr((string) count($privateCustomRoutes)); ?>">
                                <h4><?php echo esc_html__('Menus privados personalizados', 'navai-voice'); ?></h4>
                                <p class="navai-admin-description">
                                    <?php echo esc_html__('Agrega rutas manuales seleccionando plugin, rol y URL. Puedes editar o eliminar cada fila.', 'navai-voice'); ?>
                                </p>

                                <div class="navai-private-routes-list">
                                    <?php foreach ($privateCustomRoutes as $routeIndex => $privateRouteConfig) : ?>
                                        <?php
                                        $rowId = sanitize_text_field((string) ($privateRouteConfig['id'] ?? ''));
                                        $rowPluginKey = sanitize_text_field((string) ($privateRouteConfig['plugin_key'] ?? 'wp-core'));
                                        if ($rowPluginKey === '') {
                                            $rowPluginKey = 'wp-core';
                                        }
                                        $rowRole = sanitize_key((string) ($privateRouteConfig['role'] ?? ''));
                                        $rowUrl = esc_url_raw((string) ($privateRouteConfig['url'] ?? ''));
                                        ?>
                                        <div class="navai-private-route-row">
                                            <input
                                                type="hidden"
                                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][<?php echo esc_attr((string) $routeIndex); ?>][id]"
                                                value="<?php echo esc_attr($rowId); ?>"
                                            />

                                            <label>
                                                <span><?php echo esc_html__('Plugin', 'navai-voice'); ?></span>
                                                <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][<?php echo esc_attr((string) $routeIndex); ?>][plugin_key]">
                                                    <?php foreach ($privateRoutePluginCatalog as $pluginKey => $pluginLabel) : ?>
                                                        <option value="<?php echo esc_attr((string) $pluginKey); ?>" <?php selected($rowPluginKey, (string) $pluginKey); ?>>
                                                            <?php echo esc_html((string) $pluginLabel); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label>
                                                <span><?php echo esc_html__('Rol', 'navai-voice'); ?></span>
                                                <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][<?php echo esc_attr((string) $routeIndex); ?>][role]">
                                                    <?php foreach ($availableRoles as $roleKey => $roleLabel) : ?>
                                                        <option value="<?php echo esc_attr((string) $roleKey); ?>" <?php selected($rowRole, (string) $roleKey); ?>>
                                                            <?php echo esc_html((string) $roleLabel); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label class="navai-private-route-url">
                                                <span><?php echo esc_html__('URL', 'navai-voice'); ?></span>
                                                <input
                                                    type="url"
                                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][<?php echo esc_attr((string) $routeIndex); ?>][url]"
                                                    value="<?php echo esc_attr($rowUrl); ?>"
                                                    placeholder="<?php echo esc_attr('https://example.com/wp-admin/admin.php?page=slug'); ?>"
                                                />
                                            </label>

                                            <button type="button" class="button-link-delete navai-private-route-remove">
                                                <?php echo esc_html__('Eliminar', 'navai-voice'); ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="button" class="button button-secondary navai-private-route-add">
                                    <?php echo esc_html__('Anadir URL', 'navai-voice'); ?>
                                </button>

                                <template class="navai-private-route-template">
                                    <div class="navai-private-route-row">
                                        <input
                                            type="hidden"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][__INDEX__][id]"
                                            value=""
                                        />

                                        <label>
                                            <span><?php echo esc_html__('Plugin', 'navai-voice'); ?></span>
                                            <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][__INDEX__][plugin_key]">
                                                <?php foreach ($privateRoutePluginCatalog as $pluginKey => $pluginLabel) : ?>
                                                    <option value="<?php echo esc_attr((string) $pluginKey); ?>">
                                                        <?php echo esc_html((string) $pluginLabel); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>

                                        <label>
                                            <span><?php echo esc_html__('Rol', 'navai-voice'); ?></span>
                                            <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][__INDEX__][role]">
                                                <?php foreach ($availableRoles as $roleKey => $roleLabel) : ?>
                                                    <option value="<?php echo esc_attr((string) $roleKey); ?>">
                                                        <?php echo esc_html((string) $roleLabel); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>

                                        <label class="navai-private-route-url">
                                            <span><?php echo esc_html__('URL', 'navai-voice'); ?></span>
                                            <input
                                                type="url"
                                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][__INDEX__][url]"
                                                value=""
                                                placeholder="<?php echo esc_attr('https://example.com/wp-admin/admin.php?page=slug'); ?>"
                                            />
                                        </label>

                                        <button type="button" class="button-link-delete navai-private-route-remove">
                                            <?php echo esc_html__('Eliminar', 'navai-voice'); ?>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div class="navai-nav-actions">
                                <button
                                    type="button"
                                    class="button button-secondary navai-nav-check-action"
                                    data-navai-check-action="scope-select"
                                    data-navai-nav-scope="private"
                                >
                                    <?php echo esc_html__('Seleccionar todo', 'navai-voice'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="button button-secondary navai-nav-check-action"
                                    data-navai-check-action="scope-deselect"
                                    data-navai-nav-scope="private"
                                >
                                    <?php echo esc_html__('Deseleccionar todo', 'navai-voice'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="button button-secondary navai-nav-check-action"
                                    data-navai-check-action="role-select"
                                    data-navai-nav-scope="private"
                                >
                                    <?php echo esc_html__('Seleccionar rol', 'navai-voice'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="button button-secondary navai-nav-check-action"
                                    data-navai-check-action="role-deselect"
                                    data-navai-nav-scope="private"
                                >
                                    <?php echo esc_html__('Deseleccionar rol', 'navai-voice'); ?>
                                </button>
                            </div>

                            <div class="navai-nav-filters">
                                <label>
                                    <span><?php echo esc_html__('Buscar', 'navai-voice'); ?></span>
                                    <input
                                        type="search"
                                        class="regular-text navai-nav-filter-text"
                                        data-navai-nav-scope="private"
                                        placeholder="<?php echo esc_attr__('Filtrar por texto...', 'navai-voice'); ?>"
                                    />
                                </label>
                                <label>
                                    <span><?php echo esc_html__('Plugin', 'navai-voice'); ?></span>
                                    <select class="navai-nav-filter-plugin" data-navai-nav-scope="private">
                                        <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                                        <?php foreach ($privatePluginOptions as $pluginOption) : ?>
                                            <option value="<?php echo esc_attr((string) ($pluginOption['key'] ?? '')); ?>">
                                                <?php echo esc_html((string) ($pluginOption['label'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span><?php echo esc_html__('Rol', 'navai-voice'); ?></span>
                                    <select class="navai-nav-filter-role" data-navai-nav-scope="private">
                                        <option value=""><?php echo esc_html__('Todos', 'navai-voice'); ?></option>
                                        <?php foreach ($privateRoleOptions as $roleOption) : ?>
                                            <option value="<?php echo esc_attr((string) ($roleOption['key'] ?? '')); ?>">
                                                <?php echo esc_html((string) ($roleOption['label'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>

                            <?php if (count($privateRouteGroups) === 0) : ?>
                                <p><?php echo esc_html__('No hay rutas privadas personalizadas. Usa el formulario de arriba para agregarlas.', 'navai-voice'); ?></p>
                            <?php else : ?>
                                <div class="navai-nav-groups" data-navai-nav-scope="private">
                                    <?php foreach ($privateRouteGroups as $group) : ?>
                                        <?php
                                        $groupKey = (string) ($group['plugin_key'] ?? '');
                                        $groupLabel = (string) ($group['plugin_label'] ?? '');
                                        $groupRoutes = is_array($group['routes'] ?? null) ? $group['routes'] : [];
                                        if ($groupKey === '' || count($groupRoutes) === 0) {
                                            continue;
                                        }
                                        ?>
                                        <section class="navai-nav-route-group" data-nav-plugin="<?php echo esc_attr($groupKey); ?>">
                                            <div class="navai-nav-group-head">
                                                <h4><?php echo esc_html($groupLabel); ?></h4>
                                                <div class="navai-nav-actions navai-nav-actions--inline">
                                                    <button
                                                        type="button"
                                                        class="button button-secondary navai-nav-check-action"
                                                        data-navai-check-action="group-select"
                                                        data-navai-nav-scope="private"
                                                    >
                                                        <?php echo esc_html__('Seleccionar', 'navai-voice'); ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="button button-secondary navai-nav-check-action"
                                                        data-navai-check-action="group-deselect"
                                                        data-navai-nav-scope="private"
                                                    >
                                                        <?php echo esc_html__('Deseleccionar', 'navai-voice'); ?>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="navai-admin-menu-grid">
                                                <?php foreach ($groupRoutes as $item) : ?>
                                                    <?php
                                                    $routeKey = (string) ($item['key'] ?? '');
                                                    if ($routeKey === '') {
                                                        continue;
                                                    }
                                                    $routeTitle = sanitize_text_field((string) ($item['title'] ?? ''));
                                                    $routeUrl = esc_url_raw((string) ($item['url'] ?? ''));
                                                    $routeSynonyms = is_array($item['synonyms'] ?? null) ? $item['synonyms'] : [];
                                                    $routeRoles = is_array($item['roles'] ?? null)
                                                        ? array_values(array_filter(array_map('sanitize_key', $item['roles'])))
                                                        : [];
                                                    $routeRoleLabels = [];
                                                    $routeRoleBadges = [];
                                                    foreach ($routeRoles as $routeRole) {
                                                        $roleLabel = $routeRole;
                                                        if (isset($availableRoles[$routeRole])) {
                                                            $roleLabel = (string) $availableRoles[$routeRole];
                                                        }
                                                        $routeRoleLabels[] = $roleLabel;
                                                        $routeRoleBadges[] = [
                                                            'label' => $roleLabel,
                                                            'color' => $this->build_role_badge_color($routeRole),
                                                        ];
                                                    }
                                                    $isChecked = in_array($routeKey, $allowedRouteKeys, true);
                                                    $searchText = trim(implode(' ', array_filter([
                                                        $routeTitle,
                                                        $routeUrl,
                                                        implode(' ', $routeRoleLabels),
                                                        implode(' ', array_map('sanitize_text_field', $routeSynonyms)),
                                                    ])));
                                                    $rolesAttr = implode('|', $routeRoles);
                                                    $urlBoxId = 'navai-route-url-' . md5('private|' . $routeKey);
                                                    ?>
                                                    <label
                                                        class="navai-admin-check navai-admin-check-block navai-nav-route-item"
                                                        data-nav-plugin="<?php echo esc_attr($groupKey); ?>"
                                                        data-nav-roles="<?php echo esc_attr($rolesAttr); ?>"
                                                        data-nav-search="<?php echo esc_attr($searchText); ?>"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_route_keys][]"
                                                            value="<?php echo esc_attr($routeKey); ?>"
                                                            <?php checked($isChecked, true); ?>
                                                        />
                                                        <span class="navai-nav-route-main">
                                                            <strong><?php echo esc_html($routeTitle); ?></strong>
                                                            <?php if (count($routeRoleBadges) > 0) : ?>
                                                                <small class="navai-nav-route-roles">
                                                                    <?php foreach ($routeRoleBadges as $roleBadge) : ?>
                                                                        <span
                                                                            class="navai-nav-role-badge"
                                                                            style="--navai-role-badge-color: <?php echo esc_attr((string) ($roleBadge['color'] ?? '#526077')); ?>;"
                                                                        >
                                                                            <?php echo esc_html((string) ($roleBadge['label'] ?? '')); ?>
                                                                        </span>
                                                                    <?php endforeach; ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </span>
                                                        <button
                                                            type="button"
                                                            class="button-link navai-nav-url-button"
                                                            data-navai-url-target="<?php echo esc_attr($urlBoxId); ?>"
                                                        >
                                                            <?php echo esc_html__('URL', 'navai-voice'); ?>
                                                        </button>
                                                        <div class="navai-nav-url-box" id="<?php echo esc_attr($urlBoxId); ?>" hidden>
                                                            <code><?php echo esc_html($routeUrl); ?></code>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </section>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="navai-admin-panel" data-navai-panel="plugins">
                    <h2><?php echo esc_html__('Plugins', 'navai-voice'); ?></h2>
                    <p><?php echo esc_html__('Selecciona plugins permitidos para consulta por funciones backend de NAVAI.', 'navai-voice'); ?></p>

                    <div class="navai-admin-card">
                        <h3><?php echo esc_html__('Plugins instalados', 'navai-voice'); ?></h3>
                        <?php if (count($installedPlugins) === 0) : ?>
                            <p><?php echo esc_html__('No se encontraron plugins instalados.', 'navai-voice'); ?></p>
                        <?php else : ?>
                            <div class="navai-admin-plugin-grid">
                                <?php foreach ($installedPlugins as $plugin) : ?>
                                    <?php
                                    $pluginFile = (string) $plugin['file'];
                                    $isAllowed = in_array($pluginFile, $allowedPluginFiles, true);
                                    ?>
                                    <label class="navai-admin-check navai-admin-check-block">
                                        <input
                                            type="checkbox"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_plugin_files][]"
                                            value="<?php echo esc_attr($pluginFile); ?>"
                                            <?php checked($isAllowed, true); ?>
                                        />
                                        <span>
                                            <strong><?php echo esc_html($plugin['name']); ?></strong>
                                            <small><?php echo esc_html($pluginFile); ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="navai-admin-card">
                        <h3><?php echo esc_html__('Plugins manuales', 'navai-voice'); ?></h3>
                        <p><?php echo esc_html__('Agrega plugin files o slugs (uno por linea o separados por coma).', 'navai-voice'); ?></p>
                        <textarea
                            class="large-text code"
                            rows="5"
                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[manual_plugins]"
                        ><?php echo esc_textarea((string) ($settings['manual_plugins'] ?? '')); ?></textarea>
                    </div>
                </section>

                <section class="navai-admin-panel" data-navai-panel="settings">
                    <h2><?php echo esc_html__('Ajustes', 'navai-voice'); ?></h2>
                    <p><?php echo esc_html__('Configuracion principal del runtime de voz.', 'navai-voice'); ?></p>

                    <div class="navai-admin-card navai-admin-settings-grid">
                        <label>
                            <span><?php echo esc_html__('OpenAI API Key', 'navai-voice'); ?></span>
                            <input
                                class="regular-text"
                                type="password"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[openai_api_key]"
                                value="<?php echo esc_attr((string) ($settings['openai_api_key'] ?? '')); ?>"
                                autocomplete="off"
                            />
                        </label>

                        <label>
                            <span><?php echo esc_html__('Modelo Realtime', 'navai-voice'); ?></span>
                            <input
                                class="regular-text"
                                type="text"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_model]"
                                value="<?php echo esc_attr((string) ($settings['default_model'] ?? 'gpt-realtime')); ?>"
                            />
                        </label>

                        <label>
                            <span><?php echo esc_html__('Voz', 'navai-voice'); ?></span>
                            <input
                                class="regular-text"
                                type="text"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_voice]"
                                value="<?php echo esc_attr((string) ($settings['default_voice'] ?? 'marin')); ?>"
                            />
                        </label>

                        <label class="navai-admin-full-width">
                            <span><?php echo esc_html__('Instrucciones base', 'navai-voice'); ?></span>
                            <textarea
                                class="large-text"
                                rows="4"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_instructions]"
                            ><?php echo esc_textarea((string) ($settings['default_instructions'] ?? '')); ?></textarea>
                        </label>

                        <label>
                            <span><?php echo esc_html__('Idioma', 'navai-voice'); ?></span>
                            <input
                                class="regular-text"
                                type="text"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_language]"
                                value="<?php echo esc_attr((string) ($settings['default_language'] ?? '')); ?>"
                            />
                        </label>

                        <label>
                            <span><?php echo esc_html__('Acento de voz', 'navai-voice'); ?></span>
                            <input
                                class="regular-text"
                                type="text"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_voice_accent]"
                                value="<?php echo esc_attr((string) ($settings['default_voice_accent'] ?? '')); ?>"
                            />
                        </label>

                        <label>
                            <span><?php echo esc_html__('Tono de voz', 'navai-voice'); ?></span>
                            <input
                                class="regular-text"
                                type="text"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_voice_tone]"
                                value="<?php echo esc_attr((string) ($settings['default_voice_tone'] ?? '')); ?>"
                            />
                        </label>

                        <label>
                            <span><?php echo esc_html__('TTL client_secret (10-7200)', 'navai-voice'); ?></span>
                            <input
                                type="number"
                                min="10"
                                max="7200"
                                step="1"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[client_secret_ttl]"
                                value="<?php echo esc_attr((string) ((int) ($settings['client_secret_ttl'] ?? 600))); ?>"
                            />
                        </label>

                        <label class="navai-admin-check">
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[allow_public_client_secret]"
                                value="1"
                                <?php checked(!empty($settings['allow_public_client_secret']), true); ?>
                            />
                            <span><?php echo esc_html__('Permitir client_secret publico (anonimos)', 'navai-voice'); ?></span>
                        </label>

                        <label class="navai-admin-check">
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[allow_public_functions]"
                                value="1"
                                <?php checked(!empty($settings['allow_public_functions']), true); ?>
                            />
                            <span><?php echo esc_html__('Permitir funciones backend publicas (anonimos)', 'navai-voice'); ?></span>
                        </label>

                        <label>
                            <span><?php echo esc_html__('Render del componente', 'navai-voice'); ?></span>
                            <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[frontend_display_mode]">
                                <option value="global" <?php selected($frontendDisplayMode, 'global'); ?>>
                                    <?php echo esc_html__('Boton global flotante', 'navai-voice'); ?>
                                </option>
                                <option value="shortcode" <?php selected($frontendDisplayMode, 'shortcode'); ?>>
                                    <?php echo esc_html__('Solo shortcode manual', 'navai-voice'); ?>
                                </option>
                            </select>
                        </label>

                        <label>
                            <span><?php echo esc_html__('Lado del boton flotante', 'navai-voice'); ?></span>
                            <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[frontend_button_side]">
                                <option value="left" <?php selected($frontendButtonSide, 'left'); ?>>
                                    <?php echo esc_html__('Izquierda', 'navai-voice'); ?>
                                </option>
                                <option value="right" <?php selected($frontendButtonSide, 'right'); ?>>
                                    <?php echo esc_html__('Derecha', 'navai-voice'); ?>
                                </option>
                            </select>
                        </label>

                        <label>
                            <span><?php echo esc_html__('Color boton inactivo', 'navai-voice'); ?></span>
                            <input
                                type="color"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[frontend_button_color_idle]"
                                value="<?php echo esc_attr($frontendButtonColorIdle); ?>"
                            />
                        </label>

                        <label>
                            <span><?php echo esc_html__('Color boton activo', 'navai-voice'); ?></span>
                            <input
                                type="color"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[frontend_button_color_active]"
                                value="<?php echo esc_attr($frontendButtonColorActive); ?>"
                            />
                        </label>

                        <label class="navai-admin-check">
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[frontend_show_button_text]"
                                value="1"
                                <?php checked($frontendShowButtonText, true); ?>
                            />
                            <span><?php echo esc_html__('Mostrar texto en el boton', 'navai-voice'); ?></span>
                        </label>

                        <label>
                            <span><?php echo esc_html__('Texto boton inactivo', 'navai-voice'); ?></span>
                            <input
                                class="regular-text"
                                type="text"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[frontend_button_text_idle]"
                                value="<?php echo esc_attr($frontendButtonTextIdle); ?>"
                            />
                        </label>

                        <label>
                            <span><?php echo esc_html__('Texto boton activo', 'navai-voice'); ?></span>
                            <input
                                class="regular-text"
                                type="text"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[frontend_button_text_active]"
                                value="<?php echo esc_attr($frontendButtonTextActive); ?>"
                            />
                        </label>

                        <div class="navai-admin-full-width">
                            <span class="navai-admin-field-title"><?php echo esc_html__('Roles permitidos para mostrar el componente', 'navai-voice'); ?></span>
                            <div class="navai-admin-role-grid">
                                <?php $guestChecked = in_array('guest', $allowedFrontendRoles, true); ?>
                                <label class="navai-admin-check navai-admin-check-block">
                                    <input
                                        type="checkbox"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[frontend_allowed_roles][]"
                                        value="guest"
                                        <?php checked($guestChecked, true); ?>
                                    />
                                    <span>
                                        <strong><?php echo esc_html__('Invitados (no autenticados)', 'navai-voice'); ?></strong>
                                    </span>
                                </label>
                                <?php foreach ($availableRoles as $roleKey => $roleLabel) : ?>
                                    <?php $isRoleChecked = in_array((string) $roleKey, $allowedFrontendRoles, true); ?>
                                    <label class="navai-admin-check navai-admin-check-block">
                                        <input
                                            type="checkbox"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[frontend_allowed_roles][]"
                                            value="<?php echo esc_attr((string) $roleKey); ?>"
                                            <?php checked($isRoleChecked, true); ?>
                                        />
                                        <span>
                                            <strong><?php echo esc_html((string) $roleLabel); ?></strong><br />
                                            <small><?php echo esc_html((string) $roleKey); ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description navai-admin-description">
                                <?php echo esc_html__('Si no seleccionas ningun rol, el componente no se mostrara a nadie.', 'navai-voice'); ?>
                            </p>
                        </div>

                        <div class="navai-admin-full-width">
                            <span class="navai-admin-field-title"><?php echo esc_html__('Shortcode manual', 'navai-voice'); ?></span>
                            <input class="regular-text code navai-admin-code" type="text" value="[navai_voice]" readonly />
                            <p class="description navai-admin-description">
                                <?php echo esc_html__('Puedes pegar este shortcode en cualquier pagina o bloque cuando uses modo manual.', 'navai-voice'); ?>
                            </p>
                        </div>
                    </div>
                </section>

                <?php submit_button(__('Guardar cambios', 'navai-voice')); ?>
            </form>

            <footer class="navai-admin-footer">
                <?php echo esc_html__('by', 'navai-voice'); ?>
                <a href="https://luxisoft.com/en/" target="_blank" rel="noopener noreferrer">LUXISOFT</a>
            </footer>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $defaults
     */
    private function read_text_value(
        array $source,
        array $previous,
        array $defaults,
        string $key,
        bool $fallbackToDefaultWhenEmpty
    ): string {
        $raw = array_key_exists($key, $source) ? (string) $source[$key] : (string) ($previous[$key] ?? $defaults[$key] ?? '');
        $value = sanitize_text_field($raw);

        if ($fallbackToDefaultWhenEmpty && trim($value) === '') {
            $value = sanitize_text_field((string) ($defaults[$key] ?? ''));
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function sanitize_color_value($value, string $fallback): string
    {
        $sanitizedFallback = sanitize_hex_color($fallback);
        if (!is_string($sanitizedFallback) || trim($sanitizedFallback) === '') {
            $sanitizedFallback = '#1263dc';
        }

        $sanitized = sanitize_hex_color((string) $value);
        if (!is_string($sanitized) || trim($sanitized) === '') {
            return $sanitizedFallback;
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $defaults
     */
    private function read_textarea_value(
        array $source,
        array $previous,
        array $defaults,
        string $key,
        bool $fallbackToDefaultWhenEmpty
    ): string {
        $raw = array_key_exists($key, $source) ? (string) $source[$key] : (string) ($previous[$key] ?? $defaults[$key] ?? '');
        $value = sanitize_textarea_field($raw);

        if ($fallbackToDefaultWhenEmpty && trim($value) === '') {
            $value = sanitize_textarea_field((string) ($defaults[$key] ?? ''));
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function sanitize_menu_item_ids($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $id = absint($item);
            if ($id > 0) {
                $clean[] = $id;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitize_route_keys($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $key = strtolower(trim((string) $item));
            $key = preg_replace('/[^a-z0-9:_-]/', '', $key);
            if (is_string($key) && $key !== '') {
                $clean[] = $key;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitize_plugin_files($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $item) {
            $pluginFile = plugin_basename((string) $item);
            $pluginFile = trim($pluginFile);
            if ($pluginFile !== '') {
                $clean[] = $pluginFile;
            }
        }

        return array_values(array_unique($clean));
    }

    private function sanitize_manual_plugins(string $value): string
    {
        $parts = preg_split('/[\r\n,]+/', $value) ?: [];
        $clean = [];
        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token !== '') {
                $clean[] = sanitize_text_field($token);
            }
        }

        return implode("\n", array_values(array_unique($clean)));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function sanitize_frontend_roles($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $allowed = array_merge(['guest'], array_keys($this->get_available_roles()));
        $allowedLookup = array_fill_keys($allowed, true);
        $clean = [];

        foreach ($value as $item) {
            $role = sanitize_key((string) $item);
            if ($role === '' || !isset($allowedLookup[$role])) {
                continue;
            }

            $clean[] = $role;
        }

        return array_values(array_unique($clean));
    }

    /**
     * @return array<string, string>
     */
    private function get_available_roles(): array
    {
        if (!function_exists('wp_roles')) {
            return [];
        }

        $roles = wp_roles();
        if (!is_object($roles) || !isset($roles->roles) || !is_array($roles->roles)) {
            return [];
        }

        $items = [];
        foreach ($roles->roles as $roleKey => $roleData) {
            $key = sanitize_key((string) $roleKey);
            if ($key === '') {
                continue;
            }

            $label = is_array($roleData) && isset($roleData['name']) ? (string) $roleData['name'] : $key;
            $items[$key] = translate_user_role($label);
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private function get_default_frontend_roles(): array
    {
        $roles = array_keys($this->get_available_roles());
        array_unshift($roles, 'guest');
        return array_values(array_unique($roles));
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, string>
     */
    private function get_selected_route_keys(array $settings): array
    {
        $keys = $this->sanitize_route_keys($settings['allowed_route_keys'] ?? []);
        $keys = $this->map_legacy_route_keys($keys);
        if (count($keys) > 0) {
            return $keys;
        }

        $legacyIds = $this->sanitize_menu_item_ids($settings['allowed_menu_item_ids'] ?? []);
        if (count($legacyIds) === 0) {
            return [];
        }

        return $this->map_legacy_menu_item_ids_to_route_keys($legacyIds);
    }

    /**
     * @param array<int, string> $keys
     * @return array<int, string>
     */
    private function map_legacy_route_keys(array $keys): array
    {
        if (count($keys) === 0) {
            return [];
        }

        $catalog = $this->get_navigation_catalog();
        $legacyMap = is_array($catalog['legacy_route_key_map'] ?? null) ? $catalog['legacy_route_key_map'] : [];
        if (count($legacyMap) === 0) {
            return array_values(array_unique($keys));
        }

        $mapped = [];
        foreach ($keys as $key) {
            if (isset($legacyMap[$key])) {
                $legacyTarget = $legacyMap[$key];
                if (is_string($legacyTarget) && $legacyTarget !== '') {
                    $mapped[] = $legacyTarget;
                    continue;
                }

                if (is_array($legacyTarget)) {
                    foreach ($legacyTarget as $mappedKey) {
                        if (is_string($mappedKey) && trim($mappedKey) !== '') {
                            $mapped[] = $mappedKey;
                        }
                    }
                    continue;
                }

                continue;
            }

            $mapped[] = $key;
        }

        return array_values(array_unique($mapped));
    }

    /**
     * @param array<int, int> $legacyIds
     * @return array<int, string>
     */
    private function map_legacy_menu_item_ids_to_route_keys(array $legacyIds): array
    {
        if (count($legacyIds) === 0) {
            return [];
        }

        $catalog = $this->get_navigation_catalog();
        $legacyMap = is_array($catalog['legacy_menu_id_map'] ?? null) ? $catalog['legacy_menu_id_map'] : [];

        $keys = [];
        foreach ($legacyIds as $legacyId) {
            if (isset($legacyMap[$legacyId]) && is_string($legacyMap[$legacyId])) {
                $keys[] = $legacyMap[$legacyId];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, string>
     */
    private function get_current_user_roles(): array
    {
        if (!is_user_logged_in()) {
            return [];
        }

        $user = wp_get_current_user();
        if (!($user instanceof WP_User) || !is_array($user->roles)) {
            return [];
        }

        $roles = [];
        foreach ($user->roles as $role) {
            $key = sanitize_key((string) $role);
            if ($key !== '') {
                $roles[] = $key;
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * @return array<string, mixed>
     */
    private function get_navigation_catalog(): array
    {
        $settings = $this->get_settings();
        $publicRoutes = $this->collect_public_menu_routes();
        $privateRoutes = $this->collect_private_routes($settings);

        $index = [];
        $legacyMap = [];
        $legacyRouteKeyMap = [];

        foreach (array_merge($publicRoutes, $privateRoutes) as $item) {
            $key = (string) ($item['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $index[$key] = $item;

            $legacyIds = is_array($item['legacy_ids'] ?? null) ? $item['legacy_ids'] : [];
            foreach ($legacyIds as $legacyId) {
                $id = absint($legacyId);
                if ($id > 0) {
                    $legacyMap[$id] = $key;
                }
            }

            $legacyKeys = is_array($item['legacy_keys'] ?? null) ? $item['legacy_keys'] : [];
            foreach ($legacyKeys as $legacyKey) {
                $cleanLegacyKey = strtolower(trim((string) $legacyKey));
                if ($cleanLegacyKey !== '' && $cleanLegacyKey !== $key) {
                    if (!isset($legacyRouteKeyMap[$cleanLegacyKey])) {
                        $legacyRouteKeyMap[$cleanLegacyKey] = [];
                    }

                    if (is_array($legacyRouteKeyMap[$cleanLegacyKey]) && !in_array($key, $legacyRouteKeyMap[$cleanLegacyKey], true)) {
                        $legacyRouteKeyMap[$cleanLegacyKey][] = $key;
                    }
                }
            }
        }

        return [
            'public' => $publicRoutes,
            'private' => $privateRoutes,
            'private_roles' => [],
            'index' => $index,
            'legacy_menu_id_map' => $legacyMap,
            'legacy_route_key_map' => $legacyRouteKeyMap,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     * @return array<int, array{
     *   plugin_key: string,
     *   plugin_label: string,
     *   routes: array<int, array<string, mixed>>
     * }>
     */
    private function build_navigation_route_groups(array $routes): array
    {
        $groups = [];

        foreach ($routes as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pluginKey = sanitize_text_field((string) ($item['plugin_key'] ?? ''));
            if ($pluginKey === '') {
                $pluginKey = 'wp-core';
            }
            $pluginLabel = sanitize_text_field((string) ($item['plugin_label'] ?? ''));
            if ($pluginLabel === '') {
                $pluginLabel = __('WordPress / Sitio', 'navai-voice');
            }

            if (!isset($groups[$pluginKey])) {
                $groups[$pluginKey] = [
                    'plugin_key' => $pluginKey,
                    'plugin_label' => $pluginLabel,
                    'routes' => [],
                ];
            }

            $groups[$pluginKey]['routes'][] = $item;
        }

        foreach ($groups as &$group) {
            $routesInGroup = is_array($group['routes'] ?? null) ? $group['routes'] : [];
            usort(
                $routesInGroup,
                static fn(array $a, array $b): int => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''))
            );
            $group['routes'] = $routesInGroup;
        }
        unset($group);

        uasort(
            $groups,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['plugin_label'] ?? ''), (string) ($b['plugin_label'] ?? ''))
        );

        return array_values($groups);
    }

    /**
     * @param array<int, array{plugin_key: string, plugin_label: string, routes: array<int, array<string, mixed>>}> $groups
     * @return array<int, array{key: string, label: string}>
     */
    private function build_navigation_plugin_options(array $groups): array
    {
        $items = [];
        foreach ($groups as $group) {
            $key = isset($group['plugin_key']) ? sanitize_text_field((string) $group['plugin_key']) : '';
            if ($key === '') {
                continue;
            }

            $label = isset($group['plugin_label']) ? sanitize_text_field((string) $group['plugin_label']) : '';
            if ($label === '') {
                $label = __('WordPress / Sitio', 'navai-voice');
            }

            $items[$key] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        uasort(
            $items,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''))
        );

        return array_values($items);
    }

    /**
     * @param array<int, array<string, mixed>> $privateRoutes
     * @param array<string, string> $availableRoles
     * @return array<int, array{key: string, label: string}>
     */
    private function build_navigation_private_role_options(array $privateRoutes, array $availableRoles): array
    {
        $options = [];

        foreach ($privateRoutes as $item) {
            if (!is_array($item)) {
                continue;
            }

            $roles = is_array($item['roles'] ?? null) ? $item['roles'] : [];
            foreach ($roles as $role) {
                $roleKey = sanitize_key((string) $role);
                if ($roleKey === '') {
                    continue;
                }

                $options[$roleKey] = [
                    'key' => $roleKey,
                    'label' => isset($availableRoles[$roleKey]) ? (string) $availableRoles[$roleKey] : $roleKey,
                ];
            }
        }

        uasort(
            $options,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''))
        );

        return array_values($options);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array{id: string, plugin_key: string, plugin_label: string, role: string, url: string}>
     */
    private function get_private_custom_routes(array $settings): array
    {
        $raw = is_array($settings['private_custom_routes'] ?? null)
            ? $settings['private_custom_routes']
            : [];
        $availableRoles = $this->get_available_roles();
        $pluginCatalog = $this->get_private_route_plugin_catalog($raw);

        return $this->sanitize_private_custom_routes($raw, $pluginCatalog, $availableRoles);
    }

    /**
     * @param mixed $existingRoutes
     * @return array<string, string>
     */
    private function get_private_route_plugin_catalog($existingRoutes = []): array
    {
        $catalog = [
            'wp-core' => __('WordPress / Sitio', 'navai-voice'),
        ];

        foreach ($this->get_installed_plugins() as $plugin) {
            $pluginFile = isset($plugin['file']) ? (string) $plugin['file'] : '';
            $slug = $this->plugin_file_to_slug($pluginFile);
            if ($slug === '') {
                continue;
            }

            $pluginKey = 'plugin:' . $slug;
            $pluginLabel = sanitize_text_field((string) ($plugin['name'] ?? $slug));
            if ($pluginLabel === '') {
                $pluginLabel = $slug;
            }

            $catalog[$pluginKey] = $pluginLabel;
        }

        if (is_array($existingRoutes)) {
            foreach ($existingRoutes as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $pluginKey = $this->sanitize_private_plugin_key((string) ($item['plugin_key'] ?? ''));
                if ($pluginKey === '' || isset($catalog[$pluginKey])) {
                    continue;
                }

                $catalog[$pluginKey] = $this->resolve_private_plugin_label(
                    $pluginKey,
                    (string) ($item['plugin_label'] ?? ''),
                    $catalog
                );
            }
        }

        uasort(
            $catalog,
            static fn(string $a, string $b): int => strcasecmp($a, $b)
        );

        return $catalog;
    }

    /**
     * @param mixed $value
     * @param array<string, string> $pluginCatalog
     * @param array<string, string> $availableRoles
     * @return array<int, array{id: string, plugin_key: string, plugin_label: string, role: string, url: string}>
     */
    private function sanitize_private_custom_routes($value, array $pluginCatalog, array $availableRoles): array
    {
        if (!is_array($value)) {
            return [];
        }

        $routes = [];
        $dedupe = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $pluginKey = $this->sanitize_private_plugin_key((string) ($item['plugin_key'] ?? 'wp-core'));
            if ($pluginKey === '') {
                $pluginKey = 'wp-core';
            }

            $role = sanitize_key((string) ($item['role'] ?? ''));
            if ($role === '' || !isset($availableRoles[$role])) {
                continue;
            }

            $url = trim((string) ($item['url'] ?? ''));
            if (str_starts_with($url, '/')) {
                $url = home_url($url);
            }
            $url = esc_url_raw($url);
            if (!$this->is_navigable_url($url) || !$this->is_internal_site_url($url)) {
                continue;
            }

            $dedupeKey = $pluginKey . '|' . $role . '|' . $this->build_url_dedupe_key($url);
            if (isset($dedupe[$dedupeKey])) {
                continue;
            }
            $dedupe[$dedupeKey] = true;

            $rawId = (string) ($item['id'] ?? '');
            $routeId = $this->sanitize_private_custom_route_id($rawId);
            if ($routeId === '') {
                $routeId = $this->generate_private_custom_route_id($pluginKey, $role, $url, (string) $index);
            }

            $routes[] = [
                'id' => $routeId,
                'plugin_key' => $pluginKey,
                'plugin_label' => $this->resolve_private_plugin_label(
                    $pluginKey,
                    (string) ($item['plugin_label'] ?? ''),
                    $pluginCatalog
                ),
                'role' => $role,
                'url' => $url,
            ];
        }

        return $routes;
    }

    private function sanitize_private_plugin_key(string $value): string
    {
        $key = strtolower(trim(sanitize_text_field($value)));
        if ($key === '') {
            return '';
        }

        $key = preg_replace('/[^a-z0-9:_-]/', '', $key);
        if (!is_string($key) || trim($key) === '') {
            return '';
        }

        if (in_array($key, ['core', 'wordpress', 'wp'], true)) {
            return 'wp-core';
        }

        return $key;
    }

    private function sanitize_private_custom_route_id(string $value): string
    {
        $id = strtolower(trim($value));
        if ($id === '') {
            return '';
        }

        $id = preg_replace('/[^a-z0-9_-]/', '', $id);
        if (!is_string($id) || trim($id) === '') {
            return '';
        }

        return substr($id, 0, 48);
    }

    private function generate_private_custom_route_id(string $pluginKey, string $role, string $url, string $index): string
    {
        if (function_exists('wp_generate_uuid4')) {
            $uuid = (string) wp_generate_uuid4();
            $cleanUuid = $this->sanitize_private_custom_route_id($uuid);
            if ($cleanUuid !== '') {
                return $cleanUuid;
            }
        }

        return substr(md5($pluginKey . '|' . $role . '|' . $url . '|' . $index . '|' . (string) microtime(true)), 0, 32);
    }

    /**
     * @param array<string, string> $pluginCatalog
     */
    private function resolve_private_plugin_label(string $pluginKey, string $providedLabel, array $pluginCatalog): string
    {
        if (isset($pluginCatalog[$pluginKey])) {
            return (string) $pluginCatalog[$pluginKey];
        }

        $cleanProvided = sanitize_text_field($providedLabel);
        if ($cleanProvided !== '') {
            return $cleanProvided;
        }

        $normalized = str_starts_with($pluginKey, 'plugin:') ? substr($pluginKey, 7) : $pluginKey;
        $normalized = str_replace(['-', '_', ':'], ' ', (string) $normalized);
        $normalized = trim($normalized);
        if ($normalized === '') {
            return __('WordPress / Sitio', 'navai-voice');
        }

        return ucwords($normalized);
    }

    private function build_private_route_title_from_url(string $url): string
    {
        $query = wp_parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && trim($query) !== '') {
            $args = [];
            parse_str($query, $args);
            if (isset($args['page']) && is_string($args['page']) && trim($args['page']) !== '') {
                return ucwords(str_replace(['-', '_'], ' ', sanitize_text_field((string) $args['page'])));
            }
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (is_string($path) && trim($path) !== '') {
            $base = basename(trim($path, '/'));
            $clean = sanitize_text_field((string) preg_replace('/\.php$/i', '', $base));
            if ($clean !== '') {
                return ucwords(str_replace(['-', '_'], ' ', $clean));
            }
        }

        return __('Ruta privada', 'navai-voice');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collect_public_menu_routes(): array
    {
        if (!function_exists('wp_get_nav_menus') || !function_exists('wp_get_nav_menu_items')) {
            return [];
        }

        $menus = wp_get_nav_menus();
        if (!is_array($menus) || count($menus) === 0) {
            return [];
        }

        $itemsByDedupeKey = [];
        foreach ($menus as $menu) {
            if (!isset($menu->term_id)) {
                continue;
            }

            $items = wp_get_nav_menu_items((int) $menu->term_id, ['update_post_term_cache' => false]);
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_object($item) || !isset($item->ID, $item->title, $item->url)) {
                    continue;
                }

                $legacyId = absint((int) $item->ID);
                if ($legacyId <= 0) {
                    continue;
                }

                $url = esc_url_raw((string) $item->url);
                if (str_starts_with($url, '/')) {
                    $url = home_url($url);
                }
                if (!$this->is_navigable_url($url)) {
                    continue;
                }

                $title = trim(wp_strip_all_tags((string) $item->title));
                if ($title === '') {
                    $title = sprintf(__('Menu item %d', 'navai-voice'), $legacyId);
                }

                $pluginHintParts = [];
                if (isset($item->type) && is_string($item->type)) {
                    $pluginHintParts[] = (string) $item->type;
                }
                if (isset($item->object) && is_string($item->object)) {
                    $pluginHintParts[] = (string) $item->object;
                }
                if (isset($item->type_label) && is_string($item->type_label)) {
                    $pluginHintParts[] = (string) $item->type_label;
                }
                $pluginMeta = $this->resolve_route_plugin_group($url, implode(' ', $pluginHintParts));

                $dedupeKey = $this->build_route_dedupe_key($title, $url);
                if (!isset($itemsByDedupeKey[$dedupeKey])) {
                    $routeKey = 'public:' . md5($dedupeKey);
                    $itemsByDedupeKey[$dedupeKey] = [
                        'key' => $routeKey,
                        'title' => $title,
                        'url' => $url,
                        'description' => __('Ruta publica seleccionada en menus de WordPress.', 'navai-voice'),
                        'synonyms' => $this->build_route_synonyms($title, $url),
                        'visibility' => 'public',
                        'roles' => [],
                        'legacy_ids' => [$legacyId],
                        'legacy_keys' => [],
                        'plugin_key' => $pluginMeta['key'],
                        'plugin_label' => $pluginMeta['label'],
                    ];
                    continue;
                }

                if (!in_array($legacyId, $itemsByDedupeKey[$dedupeKey]['legacy_ids'], true)) {
                    $itemsByDedupeKey[$dedupeKey]['legacy_ids'][] = $legacyId;
                }

                if (
                    $this->is_core_plugin_key((string) ($itemsByDedupeKey[$dedupeKey]['plugin_key'] ?? '')) &&
                    !$this->is_core_plugin_key((string) ($pluginMeta['key'] ?? ''))
                ) {
                    $itemsByDedupeKey[$dedupeKey]['plugin_key'] = (string) ($pluginMeta['key'] ?? 'wp-core');
                    $itemsByDedupeKey[$dedupeKey]['plugin_label'] = (string) ($pluginMeta['label'] ?? __('WordPress / Sitio', 'navai-voice'));
                }
            }
        }

        $items = array_values($itemsByDedupeKey);
        usort(
            $items,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''))
        );

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collect_private_routes(array $settings): array
    {
        $customRoutes = $this->get_private_custom_routes($settings);
        if (count($customRoutes) === 0) {
            return [];
        }

        $availableRoles = $this->get_available_roles();
        $items = [];
        $seen = [];

        foreach ($customRoutes as $customRoute) {
            $roleToken = sanitize_key((string) ($customRoute['role'] ?? ''));
            if ($roleToken === '') {
                continue;
            }

            $roleLabel = isset($availableRoles[$roleToken]) ? (string) $availableRoles[$roleToken] : $roleToken;
            $url = esc_url_raw((string) ($customRoute['url'] ?? ''));
            if (!$this->is_navigable_url($url) || !$this->is_internal_site_url($url)) {
                continue;
            }

            $pluginKey = $this->sanitize_private_plugin_key((string) ($customRoute['plugin_key'] ?? 'wp-core'));
            if ($pluginKey === '') {
                $pluginKey = 'wp-core';
            }

            $pluginLabel = sanitize_text_field((string) ($customRoute['plugin_label'] ?? ''));
            if ($pluginLabel === '') {
                $pluginLabel = $this->resolve_private_plugin_label($pluginKey, '', []);
            }

            $routeId = $this->sanitize_private_custom_route_id((string) ($customRoute['id'] ?? ''));
            if ($routeId === '') {
                $routeId = $this->generate_private_custom_route_id($pluginKey, $roleToken, $url, 'persisted');
            }

            $baseDedupeKey = $this->build_url_dedupe_key($url);
            $dedupeKey = $pluginKey . '|' . $roleToken . '|' . $baseDedupeKey;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $title = $this->build_private_route_title_from_url($url);
            $items[] = [
                'key' => 'private_custom:' . $routeId,
                'title' => $title,
                'url' => $url,
                'description' => sprintf(__('Ruta privada personalizada para el rol %s.', 'navai-voice'), (string) $roleLabel),
                'synonyms' => $this->build_route_synonyms($title, $url),
                'visibility' => 'private',
                'roles' => [$roleToken],
                'legacy_ids' => [],
                'legacy_keys' => [
                    'private:' . $roleToken . ':' . md5($baseDedupeKey),
                    'private:' . md5($baseDedupeKey),
                ],
                'plugin_key' => $pluginKey,
                'plugin_label' => $pluginLabel,
            ];
        }

        usort(
            $items,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''))
        );

        return $items;
    }

    /**
     * @return array<int, array{
     *   title: string,
     *   url: string,
     *   capability: string,
     *   synonyms: array<int, string>,
     *   plugin_key: string,
     *   plugin_label: string
     * }>
     */
    private function collect_admin_panel_routes(): array
    {
        $routes = [];
        $seen = [];

        global $menu, $submenu;

        if (is_array($menu)) {
            foreach ($menu as $entry) {
                $route = $this->build_admin_route_from_menu_entry($entry, '', false);
                if (is_array($route)) {
                    $this->append_admin_route_if_new($routes, $seen, $route);
                }
            }
        }

        if (is_array($submenu)) {
            foreach ($submenu as $parentSlug => $entries) {
                if (!is_array($entries)) {
                    continue;
                }

                foreach ($entries as $entry) {
                    $route = $this->build_admin_route_from_menu_entry($entry, (string) $parentSlug, true);
                    if (is_array($route)) {
                        $this->append_admin_route_if_new($routes, $seen, $route);
                    }
                }
            }
        }

        foreach ($this->get_admin_fallback_routes() as $route) {
            $this->append_admin_route_if_new($routes, $seen, $route);
        }

        usort(
            $routes,
            static fn(array $a, array $b): int => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''))
        );

        return $routes;
    }

    /**
     * @param array<int, array{
     *   title: string,
     *   url: string,
     *   capability: string,
     *   synonyms: array<int, string>,
     *   plugin_key: string,
     *   plugin_label: string
     * }> $routes
     * @param array<string, bool> $seen
     * @param array{
     *   title: string,
     *   url: string,
     *   capability: string,
     *   synonyms: array<int, string>,
     *   plugin_key: string,
     *   plugin_label: string
     * } $route
     */
    private function append_admin_route_if_new(array &$routes, array &$seen, array $route): void
    {
        $title = (string) ($route['title'] ?? '');
        $url = (string) ($route['url'] ?? '');
        if ($title === '' || !$this->is_navigable_url($url)) {
            return;
        }

        $dedupeKey = $this->build_url_dedupe_key($url);
        if (isset($seen[$dedupeKey])) {
            return;
        }

        $seen[$dedupeKey] = true;
        $routes[] = $route;
    }

    /**
     * @param mixed $entry
     * @return array{
     *   title: string,
     *   url: string,
     *   capability: string,
     *   synonyms: array<int, string>,
     *   plugin_key: string,
     *   plugin_label: string
     * }|null
     */
    private function build_admin_route_from_menu_entry($entry, string $parentSlug = '', bool $isSubmenu = false): ?array
    {
        if (!is_array($entry) || !isset($entry[0], $entry[2])) {
            return null;
        }

        $title = trim(wp_strip_all_tags((string) $entry[0]));
        if ($title === '') {
            return null;
        }

        $slug = trim((string) $entry[2]);
        if ($slug === '' || str_starts_with(strtolower($slug), 'separator')) {
            return null;
        }
        if ($this->is_non_clickable_admin_menu_slug($slug, $parentSlug, $isSubmenu)) {
            return null;
        }

        $url = $this->build_admin_menu_url($slug);
        if (!$this->is_navigable_url($url)) {
            return null;
        }
        if (!$this->is_internal_site_url($url)) {
            return null;
        }

        $capability = isset($entry[1]) ? $this->normalize_capability((string) $entry[1]) : 'read';
        $pluginMeta = $this->resolve_route_plugin_group($url, $this->sanitize_admin_menu_slug_for_plugin_hint($slug));

        return [
            'title' => $title,
            'url' => $url,
            'capability' => $capability,
            'synonyms' => $this->build_route_synonyms($title, $url),
            'plugin_key' => $pluginMeta['key'],
            'plugin_label' => $pluginMeta['label'],
        ];
    }

    private function build_admin_menu_url(string $slug): string
    {
        $cleanSlug = ltrim(trim($slug), '/');
        if ($cleanSlug === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $cleanSlug) === 1) {
            return esc_url_raw($cleanSlug);
        }

        if (str_starts_with($cleanSlug, '#')) {
            return '';
        }

        if (str_contains($cleanSlug, '.php')) {
            return esc_url_raw(admin_url($cleanSlug));
        }

        return esc_url_raw(add_query_arg('page', $cleanSlug, admin_url('admin.php')));
    }

    private function is_non_clickable_admin_menu_slug(string $slug, string $parentSlug, bool $isSubmenu): bool
    {
        $normalizedSlug = strtolower(trim($slug));
        if ($normalizedSlug === '') {
            return true;
        }

        if (preg_match('#^https?://#i', $normalizedSlug) === 1) {
            return true;
        }

        if (str_starts_with($normalizedSlug, '#')) {
            return true;
        }

        if (str_contains($normalizedSlug, '.php')) {
            return false;
        }

        if (!$isSubmenu) {
            global $submenu;
            if (is_array($submenu) && isset($submenu[$slug]) && is_array($submenu[$slug]) && count($submenu[$slug]) > 0) {
                return true;
            }
        }

        return !$this->is_registered_plugin_menu_slug($slug, $parentSlug);
    }

    private function is_registered_plugin_menu_slug(string $slug, string $parentSlug): bool
    {
        if (!function_exists('get_plugin_page_hookname')) {
            return true;
        }

        global $_registered_pages;
        if (!is_array($_registered_pages)) {
            return true;
        }

        $candidateParents = [];
        if ($parentSlug !== '') {
            $candidateParents[] = $parentSlug;
        }
        $candidateParents[] = '';

        foreach ($candidateParents as $candidateParent) {
            $hook = get_plugin_page_hookname($slug, $candidateParent);
            if (is_string($hook) && isset($_registered_pages[$hook])) {
                return true;
            }
        }

        return false;
    }

    private function sanitize_admin_menu_slug_for_plugin_hint(string $slug): string
    {
        $clean = trim($slug);
        if ($clean === '') {
            return '';
        }

        $parts = explode('?', $clean, 2);
        $pathPart = trim((string) ($parts[0] ?? ''));
        if ($pathPart === '') {
            return '';
        }

        if (!isset($parts[1])) {
            return $pathPart;
        }

        $query = trim((string) $parts[1]);
        if ($query === '') {
            return $pathPart;
        }

        $queryArgs = [];
        parse_str($query, $queryArgs);
        $hintArgs = [];
        foreach (['page', 'post_type', 'taxonomy'] as $allowedKey) {
            if (isset($queryArgs[$allowedKey]) && is_string($queryArgs[$allowedKey])) {
                $hintArgs[$allowedKey] = $queryArgs[$allowedKey];
            }
        }

        if (count($hintArgs) === 0) {
            return $pathPart;
        }

        return $pathPart . '?' . http_build_query($hintArgs);
    }

    /**
     * @return array<int, array{
     *   title: string,
     *   url: string,
     *   capability: string,
     *   synonyms: array<int, string>,
     *   plugin_key: string,
     *   plugin_label: string
     * }>
     */
    private function get_admin_fallback_routes(): array
    {
        $items = [
            ['title' => __('Escritorio', 'navai-voice'), 'url' => admin_url('index.php'), 'capability' => 'read'],
            ['title' => __('Entradas', 'navai-voice'), 'url' => admin_url('edit.php'), 'capability' => 'edit_posts'],
            ['title' => __('Medios', 'navai-voice'), 'url' => admin_url('upload.php'), 'capability' => 'upload_files'],
            ['title' => __('Paginas', 'navai-voice'), 'url' => admin_url('edit.php?post_type=page'), 'capability' => 'edit_pages'],
            ['title' => __('Comentarios', 'navai-voice'), 'url' => admin_url('edit-comments.php'), 'capability' => 'moderate_comments'],
            ['title' => __('Apariencia', 'navai-voice'), 'url' => admin_url('themes.php'), 'capability' => 'edit_theme_options'],
            ['title' => __('Plugins', 'navai-voice'), 'url' => admin_url('plugins.php'), 'capability' => 'activate_plugins'],
            ['title' => __('Usuarios', 'navai-voice'), 'url' => admin_url('users.php'), 'capability' => 'list_users'],
            ['title' => __('Herramientas', 'navai-voice'), 'url' => admin_url('tools.php'), 'capability' => 'edit_posts'],
            ['title' => __('Ajustes', 'navai-voice'), 'url' => admin_url('options-general.php'), 'capability' => 'manage_options'],
        ];

        $routes = [];
        foreach ($items as $item) {
            $title = sanitize_text_field((string) $item['title']);
            $url = esc_url_raw((string) $item['url']);
            if ($title === '' || !$this->is_navigable_url($url)) {
                continue;
            }

            $routes[] = [
                'title' => $title,
                'url' => $url,
                'capability' => $this->normalize_capability((string) ($item['capability'] ?? 'read')),
                'synonyms' => $this->build_route_synonyms($title, $url),
                'plugin_key' => 'wp-core',
                'plugin_label' => __('WordPress / Sitio', 'navai-voice'),
            ];
        }

        return $routes;
    }

    private function role_can_access_capability(string $roleKey, string $capability): bool
    {
        $cap = $this->normalize_capability($capability);
        if ($cap === '' || $cap === 'read') {
            return true;
        }

        if ($roleKey === 'administrator') {
            return true;
        }

        if (!function_exists('wp_roles')) {
            return false;
        }

        $roles = wp_roles();
        if (!is_object($roles) || !isset($roles->roles[$roleKey]) || !is_array($roles->roles[$roleKey])) {
            return false;
        }

        $roleData = $roles->roles[$roleKey];
        $capabilities = is_array($roleData['capabilities'] ?? null) ? $roleData['capabilities'] : [];
        if (count($capabilities) === 0) {
            return false;
        }

        $parts = preg_split('/[\s,|]+/', $cap) ?: [];
        foreach ($parts as $part) {
            $token = $this->normalize_capability((string) $part);
            if ($token !== '' && !empty($capabilities[$token])) {
                return true;
            }
        }

        return false;
    }

    private function normalize_capability(string $capability): string
    {
        $value = strtolower(trim($capability));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^a-z0-9_,|\s-]/', '', $value);
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function is_core_plugin_key(string $pluginKey): bool
    {
        $normalized = strtolower(trim($pluginKey));
        return $normalized === '' || $normalized === 'wp-core' || $normalized === 'core' || $normalized === 'wordpress';
    }

    /**
     * @return array{key: string, label: string}
     */
    private function resolve_route_plugin_group(string $url, string $hint = ''): array
    {
        $default = [
            'key' => 'wp-core',
            'label' => __('WordPress / Sitio', 'navai-voice'),
        ];

        $lookup = $this->get_route_plugin_lookup();
        if (count($lookup) === 0) {
            return $default;
        }

        $tokens = [];
        if ($hint !== '') {
            $tokens = array_merge($tokens, $this->extract_plugin_tokens_from_string($hint));
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        if (is_string($path) && trim($path) !== '') {
            $tokens = array_merge($tokens, $this->extract_plugin_tokens_from_string($path));
            $fileName = strtolower((string) basename($path));
            if ($fileName !== '' && !in_array($fileName, ['index.php', 'admin.php'], true)) {
                $tokens[] = sanitize_title((string) preg_replace('/\.php$/i', '', $fileName));
            }
        }

        $query = wp_parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && trim($query) !== '') {
            $queryArgs = [];
            parse_str($query, $queryArgs);

            foreach (['page', 'post_type', 'taxonomy'] as $queryKey) {
                if (!isset($queryArgs[$queryKey]) || !is_string($queryArgs[$queryKey])) {
                    continue;
                }

                $tokens = array_merge($tokens, $this->extract_plugin_tokens_from_string($queryArgs[$queryKey]));
            }
        }

        $tokens = array_values(array_unique(array_filter(array_map('sanitize_title', $tokens))));
        $matched = $this->detect_plugin_from_tokens($tokens, $lookup);
        if (is_array($matched)) {
            return $matched;
        }

        return $default;
    }

    /**
     * @param array<int, string> $tokens
     * @param array<string, array{key: string, label: string}> $lookup
     * @return array{key: string, label: string}|null
     */
    private function detect_plugin_from_tokens(array $tokens, array $lookup): ?array
    {
        foreach ($tokens as $token) {
            if (isset($lookup[$token])) {
                return $lookup[$token];
            }
        }

        foreach ($tokens as $token) {
            if (strlen($token) < 4) {
                continue;
            }

            foreach ($lookup as $slug => $meta) {
                if (
                    str_starts_with($token, $slug) ||
                    str_starts_with($slug, $token) ||
                    str_contains($token, $slug)
                ) {
                    return $meta;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extract_plugin_tokens_from_string(string $value): array
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return [];
        }

        $normalized = str_replace('\\', '/', $normalized);
        $segments = explode('/', trim($normalized, '/'));
        $tokens = [];

        foreach ($segments as $segment) {
            $segmentToken = sanitize_title($segment);
            if ($segmentToken !== '') {
                $tokens[] = $segmentToken;
            }

            $parts = preg_split('/[^a-z0-9]+/', $segment) ?: [];
            foreach ($parts as $part) {
                $clean = sanitize_key((string) $part);
                if ($clean !== '') {
                    $tokens[] = $clean;
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return array<string, array{key: string, label: string}>
     */
    private function get_route_plugin_lookup(): array
    {
        static $lookup = null;
        if (is_array($lookup)) {
            return $lookup;
        }

        $lookup = [];
        foreach ($this->get_installed_plugins() as $plugin) {
            $pluginFile = isset($plugin['file']) ? (string) $plugin['file'] : '';
            $pluginName = isset($plugin['name']) ? sanitize_text_field((string) $plugin['name']) : '';
            $slug = $this->plugin_file_to_slug($pluginFile);
            if ($slug === '') {
                continue;
            }

            $meta = [
                'key' => 'plugin:' . $slug,
                'label' => $pluginName !== '' ? $pluginName : $slug,
            ];
            $lookup[$slug] = $meta;

            $slugParts = preg_split('/[-_]+/', $slug) ?: [];
            foreach ($slugParts as $part) {
                $cleanPart = sanitize_key((string) $part);
                if (strlen($cleanPart) >= 4 && !isset($lookup[$cleanPart])) {
                    $lookup[$cleanPart] = $meta;
                }
            }
        }

        return $lookup;
    }

    private function plugin_file_to_slug(string $pluginFile): string
    {
        $normalized = strtolower(plugin_basename($pluginFile));
        if (str_contains($normalized, '/')) {
            $pieces = explode('/', $normalized);
            return sanitize_title($pieces[0]);
        }

        return sanitize_title((string) (preg_replace('/\.php$/', '', $normalized) ?: $normalized));
    }

    private function build_route_dedupe_key(string $name, string $path): string
    {
        return strtolower(trim($name)) . '|' . strtolower(untrailingslashit($path));
    }

    private function build_url_dedupe_key(string $url): string
    {
        return strtolower(untrailingslashit(trim($url)));
    }

    private function build_role_badge_color(string $roleKey): string
    {
        $token = sanitize_key($roleKey);
        if ($token === '') {
            $token = 'role';
        }

        $seed = (int) hexdec(substr(md5($token), 0, 6));
        $hue = $seed % 360;
        $saturation = 62 + ($seed % 19);
        $lightness = 38 + (((int) floor($seed / 19)) % 14);

        return sprintf('hsl(%d %d%% %d%%)', $hue, $saturation, $lightness);
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

        foreach ($this->extract_path_segments($path) as $segment) {
            $synonyms[] = strtolower($segment);
        }

        return array_values(array_unique(array_filter($synonyms, static fn(string $value): bool => $value !== '')));
    }

    /**
     * @return array<int, string>
     */
    private function extract_path_segments(string $path): array
    {
        $segments = [];

        $relativePath = wp_parse_url($path, PHP_URL_PATH);
        if (!is_string($relativePath) || trim($relativePath) === '') {
            return $segments;
        }

        $parts = explode('/', trim($relativePath, '/'));
        foreach ($parts as $part) {
            $token = sanitize_title($part);
            if ($token !== '') {
                $segments[] = str_replace('-', ' ', $token);
            }
        }

        return array_values(array_unique($segments));
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

    private function is_internal_site_url(string $url): bool
    {
        $value = trim($url);
        if ($value === '' || str_starts_with($value, '/')) {
            return true;
        }

        $targetHost = wp_parse_url($value, PHP_URL_HOST);
        if (!is_string($targetHost) || trim($targetHost) === '') {
            return true;
        }

        $targetHost = strtolower($targetHost);
        $homeHost = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $adminHost = strtolower((string) wp_parse_url(admin_url('/'), PHP_URL_HOST));

        if ($targetHost === $homeHost || $targetHost === $adminHost) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, array{file: string, name: string}>
     */
    private function get_installed_plugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        if (!is_array($plugins) || count($plugins) === 0) {
            return [];
        }

        $items = [];
        foreach ($plugins as $pluginFile => $data) {
            $name = isset($data['Name']) ? (string) $data['Name'] : $pluginFile;
            $name = trim($name) !== '' ? $name : $pluginFile;

            $items[] = [
                'file' => (string) $pluginFile,
                'name' => $name,
            ];
        }

        usort(
            $items,
            static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name'])
        );

        return $items;
    }

    private function resolve_admin_icon_url(): string
    {
        $transparentPath = NAVAI_VOICE_PATH . 'assets/img/icon_navai_transparent.png';
        if (file_exists($transparentPath)) {
            return NAVAI_VOICE_URL . 'assets/img/icon_navai_transparent.png';
        }

        return NAVAI_VOICE_URL . 'assets/img/icon_navai.jpg';
    }

    private function resolve_admin_menu_icon_url(): string
    {
        $iconUrl = $this->resolve_admin_icon_url();
        return trim($iconUrl) !== '' ? $iconUrl : 'dashicons-format-audio';
    }

    /**
     * @return array<string, mixed>
     */
    private function get_defaults(): array
    {
        return [
            'openai_api_key' => '',
            'default_model' => 'gpt-realtime',
            'default_voice' => 'marin',
            'default_instructions' => 'You are a helpful assistant.',
            'default_language' => 'Spanish',
            'default_voice_accent' => 'neutral Latin American Spanish',
            'default_voice_tone' => 'friendly and professional',
            'client_secret_ttl' => 600,
            'allow_public_client_secret' => true,
            'allow_public_functions' => true,
            'allowed_menu_item_ids' => [],
            'allowed_route_keys' => [],
            'allowed_plugin_files' => [],
            'manual_plugins' => '',
            'frontend_display_mode' => 'global',
            'frontend_button_side' => 'left',
            'frontend_button_color_idle' => '#1263dc',
            'frontend_button_color_active' => '#10883f',
            'frontend_show_button_text' => true,
            'frontend_button_text_idle' => 'Hablar con NAVAI',
            'frontend_button_text_active' => 'Detener NAVAI',
            'private_custom_routes' => [],
            'frontend_allowed_roles' => $this->get_default_frontend_roles(),
            'active_tab' => 'navigation',
        ];
    }
}
