<?php

if (!defined('ABSPATH')) {
    exit;
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
        if (!in_array($frontendDisplayMode, ['global', 'shortcode', 'both'], true)) {
            $frontendDisplayMode = (string) $defaults['frontend_display_mode'];
        }

        $frontendButtonSide = isset($source['frontend_button_side'])
            ? sanitize_key((string) $source['frontend_button_side'])
            : (string) ($previous['frontend_button_side'] ?? $defaults['frontend_button_side']);
        if (!in_array($frontendButtonSide, ['left', 'right'], true)) {
            $frontendButtonSide = (string) $defaults['frontend_button_side'];
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
            'allowed_plugin_files' => $this->sanitize_plugin_files($source['allowed_plugin_files'] ?? []),
            'manual_plugins' => $this->sanitize_manual_plugins((string) ($source['manual_plugins'] ?? '')),
            'frontend_display_mode' => $frontendDisplayMode,
            'frontend_button_side' => $frontendButtonSide,
            'frontend_allowed_roles' => $this->sanitize_frontend_roles($source['frontend_allowed_roles'] ?? []),
            'active_tab' => $activeTab,
        ];
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        $allowedMenuItemIds = array_map('strval', is_array($settings['allowed_menu_item_ids']) ? $settings['allowed_menu_item_ids'] : []);
        $allowedPluginFiles = array_map('strval', is_array($settings['allowed_plugin_files']) ? $settings['allowed_plugin_files'] : []);
        $allowedFrontendRoles = array_map('strval', is_array($settings['frontend_allowed_roles']) ? $settings['frontend_allowed_roles'] : []);
        $activeTab = is_string($settings['active_tab'] ?? null) ? (string) $settings['active_tab'] : 'navigation';
        $frontendDisplayMode = is_string($settings['frontend_display_mode'] ?? null) ? (string) $settings['frontend_display_mode'] : 'global';
        $frontendButtonSide = is_string($settings['frontend_button_side'] ?? null) ? (string) $settings['frontend_button_side'] : 'left';
        if (!in_array($activeTab, ['navigation', 'plugins', 'settings'], true)) {
            $activeTab = 'navigation';
        }
        if (!in_array($frontendDisplayMode, ['global', 'shortcode', 'both'], true)) {
            $frontendDisplayMode = 'global';
        }
        if (!in_array($frontendButtonSide, ['left', 'right'], true)) {
            $frontendButtonSide = 'left';
        }

        $menuGroups = $this->get_menu_groups();
        $installedPlugins = $this->get_installed_plugins();
        $availableRoles = $this->get_available_roles();
        ?>
        <div class="wrap navai-admin-wrap">
            <div class="navai-admin-hero">
                <div class="navai-admin-brand">
                    <img
                        class="navai-admin-icon"
                        src="<?php echo esc_url($this->resolve_admin_icon_url()); ?>"
                        alt="<?php echo esc_attr__('NAVAI icon', 'navai-voice'); ?>"
                    />
                    <div>
                        <h1><?php echo esc_html__('NAVAI Voice Dashboard', 'navai-voice'); ?></h1>
                        <p><?php echo esc_html__('Gestiona navegacion, plugins permitidos y runtime principal de NAVAI.', 'navai-voice'); ?></p>
                    </div>
                </div>
                <div class="navai-admin-banner-wrap">
                    <img
                        class="navai-admin-banner"
                        src="<?php echo esc_url(NAVAI_VOICE_URL . 'assets/img/navai.png'); ?>"
                        alt="<?php echo esc_attr__('NAVAI', 'navai-voice'); ?>"
                    />
                </div>
            </div>

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
                    <p><?php echo esc_html__('Selecciona los items de menu permitidos para la tool navigate_to.', 'navai-voice'); ?></p>

                    <?php if (count($menuGroups) === 0) : ?>
                        <div class="notice notice-warning inline">
                            <p><?php echo esc_html__('No se encontraron menus de WordPress. Crea menus en Apariencia > Menus.', 'navai-voice'); ?></p>
                        </div>
                    <?php else : ?>
                        <?php foreach ($menuGroups as $group) : ?>
                            <div class="navai-admin-card">
                                <h3><?php echo esc_html($group['menu_name']); ?></h3>
                                <div class="navai-admin-menu-grid">
                                    <?php foreach ($group['items'] as $item) : ?>
                                        <?php
                                        $itemIdString = (string) $item['id'];
                                        $isChecked = in_array($itemIdString, $allowedMenuItemIds, true);
                                        ?>
                                        <label class="navai-admin-check navai-admin-check-block">
                                            <input
                                                type="checkbox"
                                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[allowed_menu_item_ids][]"
                                                value="<?php echo esc_attr($itemIdString); ?>"
                                                <?php checked($isChecked, true); ?>
                                            />
                                            <span>
                                                <strong><?php echo esc_html($item['title']); ?></strong><br />
                                                <small><?php echo esc_html($item['url']); ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                                <option value="both" <?php selected($frontendDisplayMode, 'both'); ?>>
                                    <?php echo esc_html__('Global + shortcode', 'navai-voice'); ?>
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
                                <?php echo esc_html__('Puedes pegar este shortcode en cualquier pagina o bloque cuando uses modo manual o combinado.', 'navai-voice'); ?>
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
     * @return array<int, array{menu_name: string, items: array<int, array{id: int, title: string, url: string}>}>
     */
    private function get_menu_groups(): array
    {
        if (!function_exists('wp_get_nav_menus') || !function_exists('wp_get_nav_menu_items')) {
            return [];
        }

        $menus = wp_get_nav_menus();
        if (!is_array($menus) || count($menus) === 0) {
            return [];
        }

        $groups = [];
        foreach ($menus as $menu) {
            if (!isset($menu->term_id)) {
                continue;
            }

            $items = wp_get_nav_menu_items((int) $menu->term_id, ['update_post_term_cache' => false]);
            if (!is_array($items) || count($items) === 0) {
                continue;
            }

            $groupItems = [];
            foreach ($items as $item) {
                if (!isset($item->ID, $item->title, $item->url)) {
                    continue;
                }

                $url = esc_url_raw((string) $item->url);
                if (!$this->is_navigable_url($url)) {
                    continue;
                }

                $title = trim(wp_strip_all_tags((string) $item->title));
                if ($title === '') {
                    $title = sprintf(__('Menu item %d', 'navai-voice'), (int) $item->ID);
                }

                $groupItems[] = [
                    'id' => (int) $item->ID,
                    'title' => $title,
                    'url' => $url,
                ];
            }

            if (count($groupItems) === 0) {
                continue;
            }

            $groups[] = [
                'menu_name' => (string) $menu->name,
                'items' => $groupItems,
            ];
        }

        return $groups;
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
            'allowed_plugin_files' => [],
            'manual_plugins' => '',
            'frontend_display_mode' => 'global',
            'frontend_button_side' => 'left',
            'frontend_allowed_roles' => $this->get_default_frontend_roles(),
            'active_tab' => 'navigation',
        ];
    }
}
