<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Navai_Voice_Settings_Render_Page_Trait
{
    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        $allowedRouteKeys = $this->get_selected_route_keys($settings);
        $allowedFrontendRoles = array_map('strval', is_array($settings['frontend_allowed_roles']) ? $settings['frontend_allowed_roles'] : []);
        $activeTab = 'navigation';
        $frontendDisplayMode = is_string($settings['frontend_display_mode'] ?? null) ? (string) $settings['frontend_display_mode'] : 'global';
        $frontendButtonSide = is_string($settings['frontend_button_side'] ?? null) ? (string) $settings['frontend_button_side'] : 'left';
        $frontendButtonColorIdle = $this->sanitize_color_value($settings['frontend_button_color_idle'] ?? null, '#1263dc');
        $frontendButtonColorActive = $this->sanitize_color_value($settings['frontend_button_color_active'] ?? null, '#10883f');
        $frontendShowButtonText = !empty($settings['frontend_show_button_text']);
        $frontendButtonTextIdle = sanitize_text_field((string) ($settings['frontend_button_text_idle'] ?? 'Talk to NAVAI'));
        if (trim($frontendButtonTextIdle) === '') {
            $frontendButtonTextIdle = 'Talk to NAVAI';
        }
        $frontendButtonTextActive = sanitize_text_field((string) ($settings['frontend_button_text_active'] ?? 'Stop NAVAI'));
        if (trim($frontendButtonTextActive) === '') {
            $frontendButtonTextActive = 'Stop NAVAI';
        }
        $dashboardLanguage = $this->sanitize_dashboard_language($settings['dashboard_language'] ?? 'en');
        $guardrailsEnabled = !array_key_exists('enable_guardrails', $settings) || !empty($settings['enable_guardrails']);
        $normalizeSearchableOptions = static function (array $options, string $selected): array {
            $cleanOptions = array_values(array_unique(array_filter(array_map(
                static fn($value): string => sanitize_text_field((string) $value),
                $options
            ))));
            if ($selected !== '' && !in_array($selected, $cleanOptions, true)) {
                array_unshift($cleanOptions, $selected);
                $cleanOptions = array_values(array_unique($cleanOptions));
            }

            return $cleanOptions;
        };
        $defaultModel = sanitize_text_field((string) ($settings['default_model'] ?? 'gpt-realtime'));
        if (trim($defaultModel) === '') {
            $defaultModel = 'gpt-realtime';
        }
        $defaultVoice = sanitize_text_field((string) ($settings['default_voice'] ?? 'marin'));
        if (trim($defaultVoice) === '') {
            $defaultVoice = 'marin';
        }
        $defaultLanguage = sanitize_text_field((string) ($settings['default_language'] ?? ''));
        if (trim($defaultLanguage) === '') {
            $defaultLanguage = 'English';
        }
        $realtimeModelOptions = $normalizeSearchableOptions($this->get_realtime_model_options(), $defaultModel);
        $realtimeVoiceOptions = $normalizeSearchableOptions($this->get_realtime_voice_options(), $defaultVoice);
        $realtimeLanguageOptions = $normalizeSearchableOptions($this->get_realtime_language_options(), $defaultLanguage);

        if (!in_array($frontendDisplayMode, ['global', 'shortcode'], true)) {
            $frontendDisplayMode = 'global';
        }
        if (!in_array($frontendButtonSide, ['left', 'right'], true)) {
            $frontendButtonSide = 'left';
        }

        $availableRoles = $this->get_available_roles();
        $privateCustomRoutes = $this->get_private_custom_routes($settings);
        $privateRoutePluginCatalog = $this->get_private_route_plugin_catalog($privateCustomRoutes);
        $pluginFunctionPluginCatalog = $this->get_plugin_function_plugin_catalog($settings['plugin_custom_functions'] ?? []);
        $pluginCustomFunctions = $this->get_plugin_custom_functions($settings);
        $allowedPluginFunctionKeys = $this->get_selected_plugin_function_keys($settings, $pluginCustomFunctions);
        $routeDescriptions = $this->sanitize_route_descriptions($settings['route_descriptions'] ?? []);
        $navigationCatalog = $this->get_navigation_catalog($settings);
        $publicRoutes = is_array($navigationCatalog['public'] ?? null) ? $navigationCatalog['public'] : [];
        $privateRoutes = is_array($navigationCatalog['private'] ?? null) ? $navigationCatalog['private'] : [];

        $publicRouteGroups = $this->build_navigation_route_groups($publicRoutes);
        $privateRouteGroups = $this->build_navigation_route_groups($privateRoutes);
        $publicPluginOptions = $this->build_navigation_plugin_options($publicRouteGroups);
        $privatePluginOptions = $this->build_navigation_plugin_options($privateRouteGroups);
        $privateRoleOptions = $this->build_navigation_private_role_options($privateRoutes, $availableRoles);
        $pluginFunctionGroups = $this->build_plugin_function_groups($pluginCustomFunctions, $availableRoles);
        $pluginFunctionPluginOptions = $this->build_plugin_function_plugin_options($pluginFunctionGroups);
        $pluginFunctionRoleOptions = $this->build_plugin_function_role_options($pluginCustomFunctions, $availableRoles);
        ?>
        <div class="wrap navai-admin-wrap">
            <form action="options.php" method="post" class="navai-admin-form">
                <?php settings_fields('navai_voice_settings_group'); ?>
                <input
                    type="hidden"
                    id="navai-active-tab-input"
                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[active_tab]"
                    value="<?php echo esc_attr($activeTab); ?>"
                />

                <div class="navai-admin-hero">
                    <div class="navai-admin-hero-top">
                        <div class="navai-admin-banner-wrap">
                            <img
                                class="navai-admin-banner"
                                src="<?php echo esc_url(NAVAI_VOICE_URL . 'assets/img/navai.png'); ?>"
                                alt="<?php echo esc_attr__('NAVAI', 'navai-voice'); ?>"
                            />
                        </div>
                        <div class="navai-admin-header-controls">
                            <div class="navai-admin-tab-buttons" role="tablist" aria-label="<?php echo esc_attr__('NAVAI sections', 'navai-voice'); ?>">
                                <button type="button" class="button button-secondary navai-admin-tab-button" data-navai-tab="navigation">
                                    <?php echo esc_html__('Navegacion', 'navai-voice'); ?>
                                </button>
                                <button type="button" class="button button-secondary navai-admin-tab-button" data-navai-tab="plugins">
                                    <?php echo esc_html__('Funciones', 'navai-voice'); ?>
                                </button>
                                <button type="button" class="button button-secondary navai-admin-tab-button" data-navai-tab="safety">
                                    <?php echo esc_html__('Seguridad', 'navai-voice'); ?>
                                </button>
                                <button type="button" class="button button-secondary navai-admin-tab-button" data-navai-tab="settings">
                                    <?php echo esc_html__('Ajustes', 'navai-voice'); ?>
                                </button>
                                <a
                                    class="button button-secondary navai-admin-doc-link"
                                    href="https://navai.luxisoft.com/documentation/installation-wordpress"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <?php echo esc_html__('Documentacion', 'navai-voice'); ?>
                                </a>
                            </div>
                            <label class="navai-admin-language-select">
                                <select
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[dashboard_language]"
                                    id="navai-dashboard-language"
                                    aria-label="<?php echo esc_attr__('Idioma del panel', 'navai-voice'); ?>"
                                >
                                    <option value="en" <?php selected($dashboardLanguage, 'en'); ?>>&#127482;&#127480; English</option>
                                    <option value="es" <?php selected($dashboardLanguage, 'es'); ?>>&#127466;&#127480; Spanish</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </div>

                <hr class="navai-admin-divider" />

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
                                                    $routeDescription = isset($routeDescriptions[$routeKey])
                                                        ? sanitize_text_field((string) $routeDescriptions[$routeKey])
                                                        : '';
                                                    $isChecked = in_array($routeKey, $allowedRouteKeys, true);
                                                    $searchText = trim(implode(' ', array_filter([
                                                        $routeTitle,
                                                        $routeUrl,
                                                        $routeDescription,
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
                                                            <input
                                                                type="text"
                                                                class="regular-text navai-nav-route-description"
                                                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[route_descriptions][<?php echo esc_attr($routeKey); ?>]"
                                                                value="<?php echo esc_attr($routeDescription); ?>"
                                                                placeholder="<?php echo esc_attr__('Describe when NAVAI should use this route', 'navai-voice'); ?>"
                                                            />
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
                                        $rowDescription = sanitize_text_field((string) ($privateRouteConfig['description'] ?? ''));
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

                                            <label class="navai-private-route-description">
                                                <span><?php echo esc_html__('Descripcion', 'navai-voice'); ?></span>
                                                <input
                                                    type="text"
                                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][<?php echo esc_attr((string) $routeIndex); ?>][description]"
                                                    value="<?php echo esc_attr($rowDescription); ?>"
                                                    placeholder="<?php echo esc_attr__('Describe when NAVAI should use this route', 'navai-voice'); ?>"
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

                                        <label class="navai-private-route-description">
                                            <span><?php echo esc_html__('Descripcion', 'navai-voice'); ?></span>
                                            <input
                                                type="text"
                                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[private_custom_routes][__INDEX__][description]"
                                                value=""
                                                placeholder="<?php echo esc_attr__('Describe when NAVAI should use this route', 'navai-voice'); ?>"
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
                                                    $routeDescription = isset($routeDescriptions[$routeKey])
                                                        ? sanitize_text_field((string) $routeDescriptions[$routeKey])
                                                        : '';
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
                                                        $routeDescription,
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
                                                            <input
                                                                type="text"
                                                                class="regular-text navai-nav-route-description"
                                                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[route_descriptions][<?php echo esc_attr($routeKey); ?>]"
                                                                value="<?php echo esc_attr($routeDescription); ?>"
                                                                placeholder="<?php echo esc_attr__('Describe when NAVAI should use this route', 'navai-voice'); ?>"
                                                            />
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

                <?php require __DIR__ . '/../views/admin/navai-settings-panel-plugins.php'; ?>
                <?php require __DIR__ . '/../views/admin/navai-settings-panel-safety.php'; ?>
                <section class="navai-admin-panel" data-navai-panel="settings">
                    <h2><?php echo esc_html__('Ajustes', 'navai-voice'); ?></h2>
                    <p><?php echo esc_html__('Configuracion principal del runtime de voz.', 'navai-voice'); ?></p>

                    <div class="navai-admin-card navai-admin-settings-sections">
                        <section class="navai-admin-settings-section">
                            <div class="navai-admin-settings-section-head">
                                <h3><?php echo esc_html__('Conexion y runtime', 'navai-voice'); ?></h3>
                                <p class="navai-admin-description">
                                    <?php echo esc_html__('Configura la API, el modelo y el comportamiento base del agente de voz.', 'navai-voice'); ?>
                                </p>
                            </div>
                            <div class="navai-admin-settings-grid">
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

                        <label class="navai-admin-searchable-field">
                            <span><?php echo esc_html__('Modelo Realtime', 'navai-voice'); ?></span>
                            <div class="navai-searchable-select" data-navai-searchable-select>
                                <button
                                    type="button"
                                    class="navai-searchable-select-toggle"
                                    aria-expanded="false"
                                >
                                    <span class="navai-searchable-select-value"><?php echo esc_html($defaultModel); ?></span>
                                </button>
                                <div class="navai-searchable-select-dropdown" hidden>
                                    <input
                                        type="search"
                                        class="regular-text navai-searchable-select-search"
                                        placeholder="<?php echo esc_attr__('Buscar modelo...', 'navai-voice'); ?>"
                                        autocomplete="off"
                                    />
                                    <div class="navai-searchable-select-options">
                                        <?php foreach ($realtimeModelOptions as $modelOption) : ?>
                                            <?php
                                            $modelId = sanitize_text_field((string) $modelOption);
                                            if ($modelId === '') {
                                                continue;
                                            }
                                            $isSelectedModel = $modelId === $defaultModel;
                                            ?>
                                            <button
                                                type="button"
                                                class="navai-searchable-select-option<?php echo $isSelectedModel ? ' is-selected' : ''; ?>"
                                                data-navai-searchable-option
                                                data-value="<?php echo esc_attr($modelId); ?>"
                                                data-label="<?php echo esc_attr($modelId); ?>"
                                            >
                                                <?php echo esc_html($modelId); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="navai-searchable-select-empty" hidden>
                                        <?php echo esc_html__('No se encontraron modelos.', 'navai-voice'); ?>
                                    </p>
                                </div>
                                <select
                                    class="navai-searchable-select-native"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_model]"
                                    hidden
                                >
                                    <?php foreach ($realtimeModelOptions as $modelOption) : ?>
                                        <?php
                                        $modelId = sanitize_text_field((string) $modelOption);
                                        if ($modelId === '') {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($modelId); ?>" <?php selected($defaultModel, $modelId); ?>>
                                            <?php echo esc_html($modelId); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </label>

                        <label class="navai-admin-searchable-field">
                            <span><?php echo esc_html__('Voz', 'navai-voice'); ?></span>
                            <div class="navai-searchable-select" data-navai-searchable-select>
                                <button
                                    type="button"
                                    class="navai-searchable-select-toggle"
                                    aria-expanded="false"
                                >
                                    <span class="navai-searchable-select-value"><?php echo esc_html($defaultVoice); ?></span>
                                </button>
                                <div class="navai-searchable-select-dropdown" hidden>
                                    <input
                                        type="search"
                                        class="regular-text navai-searchable-select-search"
                                        placeholder="<?php echo esc_attr__('Buscar voz...', 'navai-voice'); ?>"
                                        autocomplete="off"
                                    />
                                    <div class="navai-searchable-select-options">
                                        <?php foreach ($realtimeVoiceOptions as $voiceOption) : ?>
                                            <?php
                                            $voiceId = sanitize_text_field((string) $voiceOption);
                                            if ($voiceId === '') {
                                                continue;
                                            }
                                            $isSelectedVoice = $voiceId === $defaultVoice;
                                            ?>
                                            <button
                                                type="button"
                                                class="navai-searchable-select-option<?php echo $isSelectedVoice ? ' is-selected' : ''; ?>"
                                                data-navai-searchable-option
                                                data-value="<?php echo esc_attr($voiceId); ?>"
                                                data-label="<?php echo esc_attr($voiceId); ?>"
                                            >
                                                <?php echo esc_html($voiceId); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="navai-searchable-select-empty" hidden>
                                        <?php echo esc_html__('No se encontraron voces.', 'navai-voice'); ?>
                                    </p>
                                </div>
                                <select
                                    class="navai-searchable-select-native"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_voice]"
                                    hidden
                                >
                                    <?php foreach ($realtimeVoiceOptions as $voiceOption) : ?>
                                        <?php
                                        $voiceId = sanitize_text_field((string) $voiceOption);
                                        if ($voiceId === '') {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($voiceId); ?>" <?php selected($defaultVoice, $voiceId); ?>>
                                            <?php echo esc_html($voiceId); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </label>

                        <label class="navai-admin-full-width">
                            <span><?php echo esc_html__('Instrucciones base', 'navai-voice'); ?></span>
                            <textarea
                                class="large-text"
                                rows="4"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_instructions]"
                            ><?php echo esc_textarea((string) ($settings['default_instructions'] ?? '')); ?></textarea>
                        </label>

                        <label class="navai-admin-language-field">
                            <span><?php echo esc_html__('Idioma', 'navai-voice'); ?></span>
                            <div class="navai-searchable-select" data-navai-searchable-select>
                                <button
                                    type="button"
                                    class="navai-searchable-select-toggle"
                                    aria-expanded="false"
                                >
                                    <span class="navai-searchable-select-value"><?php echo esc_html($defaultLanguage); ?></span>
                                </button>
                                <div class="navai-searchable-select-dropdown" hidden>
                                    <input
                                        type="search"
                                        class="regular-text navai-searchable-select-search"
                                        placeholder="<?php echo esc_attr__('Buscar idioma...', 'navai-voice'); ?>"
                                        autocomplete="off"
                                    />
                                    <div class="navai-searchable-select-options">
                                        <?php foreach ($realtimeLanguageOptions as $languageOption) : ?>
                                            <?php
                                            $languageLabel = sanitize_text_field((string) $languageOption);
                                            if ($languageLabel === '') {
                                                continue;
                                            }
                                            $isSelectedLanguage = $languageLabel === $defaultLanguage;
                                            ?>
                                            <button
                                                type="button"
                                                class="navai-searchable-select-option<?php echo $isSelectedLanguage ? ' is-selected' : ''; ?>"
                                                data-navai-searchable-option
                                                data-value="<?php echo esc_attr($languageLabel); ?>"
                                                data-label="<?php echo esc_attr($languageLabel); ?>"
                                            >
                                                <?php echo esc_html($languageLabel); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="navai-searchable-select-empty" hidden>
                                        <?php echo esc_html__('No se encontraron idiomas.', 'navai-voice'); ?>
                                    </p>
                                </div>
                                <select
                                    class="navai-searchable-select-native"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_language]"
                                    hidden
                                >
                                    <?php foreach ($realtimeLanguageOptions as $languageOption) : ?>
                                        <?php
                                        $languageLabel = sanitize_text_field((string) $languageOption);
                                        if ($languageLabel === '') {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo esc_attr($languageLabel); ?>" <?php selected($defaultLanguage, $languageLabel); ?>>
                                            <?php echo esc_html($languageLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
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
                            </div>
                        </section>

                        <section class="navai-admin-settings-section">
                            <div class="navai-admin-settings-section-head">
                                <h3><?php echo esc_html__('Widget global', 'navai-voice'); ?></h3>
                                <p class="navai-admin-description">
                                    <?php echo esc_html__('Configura el modo de render, posicion, colores y textos del boton de NAVAI.', 'navai-voice'); ?>
                                </p>
                            </div>
                            <div class="navai-admin-settings-grid">

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
                            </div>
                        </section>

                        <section class="navai-admin-settings-section">
                            <div class="navai-admin-settings-section-head">
                                <h3><?php echo esc_html__('Visibilidad y shortcode', 'navai-voice'); ?></h3>
                                <p class="navai-admin-description">
                                    <?php echo esc_html__('Define quienes pueden ver el widget y copia el shortcode para uso manual.', 'navai-voice'); ?>
                                </p>
                            </div>
                            <div class="navai-admin-settings-grid">
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
}
