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
        $selectedRouteKeys = $this->get_selected_route_keys($settings);
        if (count($selectedRouteKeys) === 0) {
            return [];
        }

        $catalog = $this->get_navigation_catalog();
        $index = is_array($catalog['index'] ?? null) ? $catalog['index'] : [];
        $currentRoles = $this->get_current_user_roles();

        $routes = [];
        $dedupe = [];
        foreach ($selectedRouteKeys as $routeKey) {
            if (!isset($index[$routeKey]) || !is_array($index[$routeKey])) {
                continue;
            }

            $item = $index[$routeKey];
            $visibility = isset($item['visibility']) ? (string) $item['visibility'] : 'public';
            $roles = is_array($item['roles'] ?? null) ? array_map('sanitize_key', $item['roles']) : [];

            if ($visibility === 'private') {
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
        if (!in_array($activeTab, ['navigation', 'plugins', 'settings'], true)) {
            $activeTab = 'navigation';
        }
        if (!in_array($frontendDisplayMode, ['global', 'shortcode'], true)) {
            $frontendDisplayMode = 'global';
        }
        if (!in_array($frontendButtonSide, ['left', 'right'], true)) {
            $frontendButtonSide = 'left';
        }

        $navigationCatalog = $this->get_navigation_catalog();
        $publicRoutes = is_array($navigationCatalog['public'] ?? null) ? $navigationCatalog['public'] : [];
        $privateRoutesByRole = is_array($navigationCatalog['private_roles'] ?? null) ? $navigationCatalog['private_roles'] : [];
        $installedPlugins = $this->get_installed_plugins();
        $availableRoles = $this->get_available_roles();
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

                    <div class="navai-admin-card">
                        <h3><?php echo esc_html__('Menus publicos', 'navai-voice'); ?></h3>
                        <p class="navai-admin-description"><?php echo esc_html__('Disponibles para visitantes, usuarios e invitados.', 'navai-voice'); ?></p>

                        <?php if (count($publicRoutes) === 0) : ?>
                            <p><?php echo esc_html__('No se encontraron menus publicos de WordPress.', 'navai-voice'); ?></p>
                        <?php else : ?>
                            <div class="navai-admin-menu-grid">
                                <?php foreach ($publicRoutes as $item) : ?>
                                    <?php
                                    $routeKey = (string) ($item['key'] ?? '');
                                    if ($routeKey === '') {
                                        continue;
                                    }
                                    $isChecked = in_array($routeKey, $allowedRouteKeys, true);
                                    ?>
                                    <label class="navai-admin-check navai-admin-check-block">
                                        <input
                                            type="checkbox"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_route_keys][]"
                                            value="<?php echo esc_attr($routeKey); ?>"
                                            <?php checked($isChecked, true); ?>
                                        />
                                        <span>
                                            <strong><?php echo esc_html((string) ($item['title'] ?? '')); ?></strong><br />
                                            <small><?php echo esc_html((string) ($item['url'] ?? '')); ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="navai-admin-card">
                        <h3><?php echo esc_html__('Menus privados', 'navai-voice'); ?></h3>
                        <p class="navai-admin-description"><?php echo esc_html__('Rutas privadas del panel, desglosadas por rol.', 'navai-voice'); ?></p>

                        <?php if (count($privateRoutesByRole) === 0) : ?>
                            <p><?php echo esc_html__('No se encontraron rutas privadas por rol.', 'navai-voice'); ?></p>
                        <?php else : ?>
                            <?php foreach ($privateRoutesByRole as $roleKey => $roleGroup) : ?>
                                <?php
                                $roleLabel = (string) ($roleGroup['label'] ?? $roleKey);
                                $roleItems = is_array($roleGroup['items'] ?? null) ? $roleGroup['items'] : [];
                                ?>
                                <div class="navai-admin-role-section">
                                    <h4>
                                        <?php echo esc_html($roleLabel); ?>
                                        <small>(<?php echo esc_html((string) $roleKey); ?>)</small>
                                    </h4>

                                    <?php if (count($roleItems) === 0) : ?>
                                        <p class="navai-admin-description"><?php echo esc_html__('Sin rutas privadas para este rol.', 'navai-voice'); ?></p>
                                    <?php else : ?>
                                        <div class="navai-admin-menu-grid">
                                            <?php foreach ($roleItems as $item) : ?>
                                                <?php
                                                $routeKey = (string) ($item['key'] ?? '');
                                                if ($routeKey === '') {
                                                    continue;
                                                }
                                                $isChecked = in_array($routeKey, $allowedRouteKeys, true);
                                                ?>
                                                <label class="navai-admin-check navai-admin-check-block">
                                                    <input
                                                        type="checkbox"
                                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_route_keys][]"
                                                        value="<?php echo esc_attr($routeKey); ?>"
                                                        <?php checked($isChecked, true); ?>
                                                    />
                                                    <span>
                                                        <strong><?php echo esc_html((string) ($item['title'] ?? '')); ?></strong><br />
                                                        <small><?php echo esc_html((string) ($item['url'] ?? '')); ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
        $publicRoutes = $this->collect_public_menu_routes();
        $privateRoles = $this->collect_private_role_routes();

        $index = [];
        $legacyMap = [];

        foreach ($publicRoutes as $item) {
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
        }

        foreach ($privateRoles as $roleGroup) {
            $items = is_array($roleGroup['items'] ?? null) ? $roleGroup['items'] : [];
            foreach ($items as $item) {
                $key = (string) ($item['key'] ?? '');
                if ($key !== '') {
                    $index[$key] = $item;
                }
            }
        }

        return [
            'public' => $publicRoutes,
            'private_roles' => $privateRoles,
            'index' => $index,
            'legacy_menu_id_map' => $legacyMap,
        ];
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
                    ];
                    continue;
                }

                if (!in_array($legacyId, $itemsByDedupeKey[$dedupeKey]['legacy_ids'], true)) {
                    $itemsByDedupeKey[$dedupeKey]['legacy_ids'][] = $legacyId;
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
     * @return array<string, array{label: string, items: array<int, array<string, mixed>>}>
     */
    private function collect_private_role_routes(): array
    {
        $roles = $this->get_available_roles();
        if (count($roles) === 0) {
            return [];
        }

        $adminRoutes = $this->collect_admin_panel_routes();
        if (count($adminRoutes) === 0) {
            return [];
        }

        $privateByRole = [];
        foreach ($roles as $roleKey => $roleLabel) {
            $includeAllPanelRoutes = in_array($roleKey, ['administrator', 'editor'], true);
            $seen = [];
            $items = [];

            foreach ($adminRoutes as $route) {
                $title = (string) ($route['title'] ?? '');
                $url = (string) ($route['url'] ?? '');
                $capability = (string) ($route['capability'] ?? 'read');

                if ($title === '' || !$this->is_navigable_url($url)) {
                    continue;
                }

                if (!$includeAllPanelRoutes && !$this->role_can_access_capability((string) $roleKey, $capability)) {
                    continue;
                }

                $baseDedupeKey = $this->build_route_dedupe_key($title, $url);
                if (isset($seen[$baseDedupeKey])) {
                    continue;
                }
                $seen[$baseDedupeKey] = true;

                $itemKey = 'private:' . sanitize_key((string) $roleKey) . ':' . md5($baseDedupeKey);

                $items[] = [
                    'key' => $itemKey,
                    'title' => $title,
                    'url' => $url,
                    'description' => sprintf(__('Ruta privada de panel para el rol %s.', 'navai-voice'), (string) $roleLabel),
                    'synonyms' => is_array($route['synonyms'] ?? null)
                        ? array_values(array_unique(array_map('sanitize_text_field', $route['synonyms'])))
                        : $this->build_route_synonyms($title, $url),
                    'visibility' => 'private',
                    'roles' => [sanitize_key((string) $roleKey)],
                    'legacy_ids' => [],
                ];
            }

            if (count($items) === 0) {
                continue;
            }

            usort(
                $items,
                static fn(array $a, array $b): int => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''))
            );

            $privateByRole[(string) $roleKey] = [
                'label' => (string) $roleLabel,
                'items' => $items,
            ];
        }

        return $privateByRole;
    }

    /**
     * @return array<int, array{title: string, url: string, capability: string, synonyms: array<int, string>}>
     */
    private function collect_admin_panel_routes(): array
    {
        $routes = [];
        $seen = [];

        global $menu, $submenu;

        if (is_array($menu)) {
            foreach ($menu as $entry) {
                $route = $this->build_admin_route_from_menu_entry($entry);
                if (is_array($route)) {
                    $this->append_admin_route_if_new($routes, $seen, $route);
                }
            }
        }

        if (is_array($submenu)) {
            foreach ($submenu as $entries) {
                if (!is_array($entries)) {
                    continue;
                }

                foreach ($entries as $entry) {
                    $route = $this->build_admin_route_from_menu_entry($entry);
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
     * @param array<int, array{title: string, url: string, capability: string, synonyms: array<int, string>}> $routes
     * @param array<string, bool> $seen
     * @param array{title: string, url: string, capability: string, synonyms: array<int, string>} $route
     */
    private function append_admin_route_if_new(array &$routes, array &$seen, array $route): void
    {
        $title = (string) ($route['title'] ?? '');
        $url = (string) ($route['url'] ?? '');
        if ($title === '' || !$this->is_navigable_url($url)) {
            return;
        }

        $dedupeKey = $this->build_route_dedupe_key($title, $url);
        if (isset($seen[$dedupeKey])) {
            return;
        }

        $seen[$dedupeKey] = true;
        $routes[] = $route;
    }

    /**
     * @param mixed $entry
     * @return array{title: string, url: string, capability: string, synonyms: array<int, string>}|null
     */
    private function build_admin_route_from_menu_entry($entry): ?array
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

        $url = $this->build_admin_menu_url($slug);
        if (!$this->is_navigable_url($url)) {
            return null;
        }

        $capability = isset($entry[1]) ? $this->normalize_capability((string) $entry[1]) : 'read';

        return [
            'title' => $title,
            'url' => $url,
            'capability' => $capability,
            'synonyms' => $this->build_route_synonyms($title, $url),
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

    /**
     * @return array<int, array{title: string, url: string, capability: string, synonyms: array<int, string>}>
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

    private function build_route_dedupe_key(string $name, string $path): string
    {
        return strtolower(trim($name)) . '|' . strtolower(untrailingslashit($path));
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
            'frontend_allowed_roles' => $this->get_default_frontend_roles(),
            'active_tab' => 'navigation',
        ];
    }
}
