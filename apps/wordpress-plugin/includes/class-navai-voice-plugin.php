<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Plugin', false)) {
    return;
}

class Navai_Voice_Plugin
{
    private Navai_Voice_Settings $settings;
    private Navai_Voice_API $api;
    private const DEFAULT_WEBRTC_URL = 'https://api.openai.com/v1/realtime/calls';

    public function __construct()
    {
        $this->settings = new Navai_Voice_Settings();
        $this->api = new Navai_Voice_API($this->settings);
    }

    public function init(): void
    {
        add_action('admin_menu', [$this->settings, 'register_menu']);
        add_action('admin_init', [$this->settings, 'register_settings']);
        add_action('rest_api_init', [$this->api, 'register_routes']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_footer', [$this, 'render_global_voice_widget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_head', [$this, 'inject_admin_menu_icon_css']);
        add_action('admin_head-plugins.php', [$this, 'inject_plugins_page_logo_css']);

        add_filter('plugin_action_links_' . NAVAI_VOICE_BASENAME, [$this, 'add_plugin_action_links']);
        add_shortcode('navai_voice', [$this, 'render_voice_shortcode']);
    }

    /**
     * @param array<int, string> $links
     * @return array<int, string>
     */
    public function add_plugin_action_links(array $links): array
    {
        $settingsUrl = admin_url('admin.php?page=' . Navai_Voice_Settings::PAGE_SLUG);
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url($settingsUrl),
            esc_html__('Ajustes', 'navai-voice')
        );

        array_unshift($links, $settingsLink);
        return $links;
    }

    public function inject_plugins_page_logo_css(): void
    {
        $pluginRowSelector = sprintf(
            'tr[data-plugin="%s"] .plugin-title strong',
            esc_attr(NAVAI_VOICE_BASENAME)
        );
        $logoUrl = esc_url($this->resolve_admin_icon_url());
        ?>
        <style>
            <?php echo $pluginRowSelector; ?> {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            <?php echo $pluginRowSelector; ?>::before {
                content: "";
                width: 20px;
                height: 20px;
                background: url('<?php echo $logoUrl; ?>') center/contain no-repeat;
                display: inline-block;
            }
        </style>
        <?php
    }

    public function inject_admin_menu_icon_css(): void
    {
        $menuId = 'toplevel_page_' . Navai_Voice_Settings::PAGE_SLUG;
        ?>
        <style>
            #<?php echo esc_attr($menuId); ?> .wp-menu-image img {
                width: 23px !important;
                height: 23px !important;
                max-width: 23px !important;
                max-height: 23px !important;
                padding-top: 5px !important;
                object-fit: contain !important;
            }
        </style>
        <?php
    }

    /**
     * @param string $hookSuffix
     */
    public function enqueue_admin_assets(string $hookSuffix): void
    {
        $validHookSuffixes = [
            'toplevel_page_' . Navai_Voice_Settings::PAGE_SLUG,
            'settings_page_' . Navai_Voice_Settings::PAGE_SLUG,
        ];
        if (!in_array($hookSuffix, $validHookSuffixes, true)) {
            return;
        }

        wp_enqueue_style(
            'navai-voice-admin',
            NAVAI_VOICE_URL . 'assets/css/navai-admin.css',
            [],
            NAVAI_VOICE_VERSION
        );

        wp_enqueue_script(
            'navai-voice-admin',
            NAVAI_VOICE_URL . 'assets/js/navai-admin.js',
            [],
            NAVAI_VOICE_VERSION,
            true
        );

        $settings = $this->settings->get_settings();
        $activeTab = isset($settings['active_tab']) && is_string($settings['active_tab'])
            ? $settings['active_tab']
            : 'navigation';

        wp_localize_script(
            'navai-voice-admin',
            'NAVAI_VOICE_ADMIN_CONFIG',
            [
                'activeTab' => $activeTab,
            ]
        );
    }

    public function register_assets(): void
    {
        wp_register_style(
            'navai-voice',
            NAVAI_VOICE_URL . 'assets/css/navai-voice.css',
            [],
            NAVAI_VOICE_VERSION
        );

        wp_register_script(
            'navai-voice',
            NAVAI_VOICE_URL . 'assets/js/navai-voice.js',
            [],
            NAVAI_VOICE_VERSION,
            true
        );

        $settings = $this->settings->get_settings();
        $config = [
            'restBaseUrl' => esc_url_raw(rest_url('navai/v1')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'realtimeWebrtcUrl' => self::DEFAULT_WEBRTC_URL,
            'defaults' => [
                'model' => (string) ($settings['default_model'] ?? 'gpt-realtime'),
                'voice' => (string) ($settings['default_voice'] ?? 'marin'),
                'instructions' => (string) ($settings['default_instructions'] ?? 'You are a helpful assistant.'),
                'language' => (string) ($settings['default_language'] ?? ''),
                'voiceAccent' => (string) ($settings['default_voice_accent'] ?? ''),
                'voiceTone' => (string) ($settings['default_voice_tone'] ?? ''),
            ],
            'routes' => $this->resolve_public_routes(),
            'messages' => [
                'idle' => __('Idle', 'navai-voice'),
                'requestingSecret' => __('Requesting client secret...', 'navai-voice'),
                'requestingMicrophone' => __('Requesting microphone permission...', 'navai-voice'),
                'connectingRealtime' => __('Connecting realtime session...', 'navai-voice'),
                'connected' => __('Connected', 'navai-voice'),
                'stopping' => __('Stopping...', 'navai-voice'),
                'stopped' => __('Stopped', 'navai-voice'),
                'failed' => __('Failed to start voice session.', 'navai-voice'),
            ],
        ];

        /**
         * @param array<string, mixed> $config
         * @param array<string, mixed> $settings
         */
        $config = apply_filters('navai_voice_frontend_config', $config, $settings);

        wp_localize_script('navai-voice', 'NAVAI_VOICE_CONFIG', $config);
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render_voice_shortcode(array $atts = []): string
    {
        $settings = $this->settings->get_settings();
        $displayMode = $this->resolve_frontend_display_mode($settings);
        if ($displayMode !== 'shortcode') {
            return '';
        }

        if (!$this->can_render_widget_for_current_user($settings)) {
            return '';
        }

        wp_enqueue_style('navai-voice');
        wp_enqueue_style('dashicons');
        wp_enqueue_script('navai-voice');

        $defaults = $settings;
        $attributes = shortcode_atts(
            [
                'label' => __('Start Voice', 'navai-voice'),
                'stop_label' => __('Stop Voice', 'navai-voice'),
                'model' => (string) ($defaults['default_model'] ?? ''),
                'voice' => (string) ($defaults['default_voice'] ?? ''),
                'instructions' => (string) ($defaults['default_instructions'] ?? ''),
                'language' => (string) ($defaults['default_language'] ?? ''),
                'voice_accent' => (string) ($defaults['default_voice_accent'] ?? ''),
                'voice_tone' => (string) ($defaults['default_voice_tone'] ?? ''),
                'debug' => '0',
                'class' => '',
            ],
            $atts
        );

        return $this->render_widget_markup(
            $attributes,
            [
                'floating' => false,
                'persist_active' => false,
                'button_side' => 'left',
                'button_color_idle' => $this->resolve_frontend_button_color($settings, 'frontend_button_color_idle', '#1263dc'),
                'button_color_active' => $this->resolve_frontend_button_color($settings, 'frontend_button_color_active', '#10883f'),
                'widget_mode' => 'shortcode',
                'show_status' => true,
            ]
        );
    }

    public function render_global_voice_widget(): void
    {
        if (is_admin()) {
            return;
        }

        $settings = $this->settings->get_settings();
        if (!$this->can_render_widget_for_current_user($settings)) {
            return;
        }

        $displayMode = $this->resolve_frontend_display_mode($settings);
        if ($displayMode !== 'global') {
            return;
        }

        wp_enqueue_style('navai-voice');
        wp_enqueue_style('dashicons');
        wp_enqueue_script('navai-voice');

        echo $this->render_widget_markup(
            [
                'label' => __('Hablar con NAVAI', 'navai-voice'),
                'stop_label' => __('Detener NAVAI', 'navai-voice'),
                'model' => (string) ($settings['default_model'] ?? ''),
                'voice' => (string) ($settings['default_voice'] ?? ''),
                'instructions' => (string) ($settings['default_instructions'] ?? ''),
                'language' => (string) ($settings['default_language'] ?? ''),
                'voice_accent' => (string) ($settings['default_voice_accent'] ?? ''),
                'voice_tone' => (string) ($settings['default_voice_tone'] ?? ''),
                'debug' => '0',
                'class' => '',
            ],
            [
                'floating' => true,
                'persist_active' => true,
                'button_side' => $this->resolve_frontend_button_side($settings),
                'button_color_idle' => $this->resolve_frontend_button_color($settings, 'frontend_button_color_idle', '#1263dc'),
                'button_color_active' => $this->resolve_frontend_button_color($settings, 'frontend_button_color_active', '#10883f'),
                'widget_mode' => 'global',
                'show_status' => false,
            ]
        );
    }

    /**
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    private function resolve_public_routes(): array
    {
        $settings = $this->settings->get_settings();
        $baseRoutes = $this->settings->get_allowed_routes_for_current_user();

        /** @var mixed $raw */
        $raw = apply_filters('navai_voice_routes', $baseRoutes, $settings);
        if (!is_array($raw)) {
            $raw = $baseRoutes;
        }

        $routes = [];
        $seenKeys = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = isset($item['name']) ? sanitize_text_field((string) $item['name']) : '';
            $description = isset($item['description'])
                ? sanitize_text_field((string) $item['description'])
                : 'Allowed route.';
            $path = isset($item['path']) ? trim((string) $item['path']) : '';

            if ($name === '' || $path === '') {
                continue;
            }

            if (str_starts_with($path, '/')) {
                $path = home_url($path);
            }

            $path = esc_url_raw($path);
            if (!$this->is_navigable_url($path)) {
                continue;
            }

            $dedupeKey = strtolower($name) . '|' . strtolower(untrailingslashit($path));
            if (isset($seenKeys[$dedupeKey])) {
                continue;
            }
            $seenKeys[$dedupeKey] = true;

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
            $synonyms = array_merge($synonyms, $this->build_route_synonyms($name, $path));

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
     * @param array<string, mixed> $settings
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    private function get_menu_routes_from_settings(array $settings): array
    {
        if (!function_exists('wp_get_nav_menus') || !function_exists('wp_get_nav_menu_items')) {
            return [];
        }

        $selectedIds = $this->get_selected_menu_item_ids($settings);
        $routesById = $this->get_menu_routes_index();

        if (count($selectedIds) === 0) {
            return [];
        }

        $selectedRoutes = [];
        foreach ($selectedIds as $id) {
            if (isset($routesById[$id])) {
                $selectedRoutes[] = $routesById[$id];
            }
        }

        return $selectedRoutes;
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

        $url = esc_url_raw((string) $item->url);
        if (!$this->is_navigable_url($url)) {
            return null;
        }

        $title = trim(wp_strip_all_tags((string) $item->title));
        if ($title === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            $url = home_url($url);
        }

        return [
            'name' => $title,
            'path' => $url,
            'description' => __('Ruta de menu seleccionada en WordPress.', 'navai-voice'),
            'synonyms' => $this->build_route_synonyms($title, $url),
        ];
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

    /**
     * @param mixed $value
     */
    private function to_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function resolve_frontend_display_mode(array $settings): string
    {
        $mode = isset($settings['frontend_display_mode']) ? sanitize_key((string) $settings['frontend_display_mode']) : '';
        if (!in_array($mode, ['global', 'shortcode'], true)) {
            return 'global';
        }

        return $mode;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function resolve_frontend_button_side(array $settings): string
    {
        $side = isset($settings['frontend_button_side']) ? sanitize_key((string) $settings['frontend_button_side']) : '';
        if (!in_array($side, ['left', 'right'], true)) {
            return 'left';
        }

        return $side;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function resolve_frontend_button_color(array $settings, string $key, string $fallback): string
    {
        $sanitizedFallback = sanitize_hex_color($fallback);
        if (!is_string($sanitizedFallback) || trim($sanitizedFallback) === '') {
            $sanitizedFallback = '#1263dc';
        }

        $raw = isset($settings[$key]) ? (string) $settings[$key] : '';
        $color = sanitize_hex_color($raw);
        if (!is_string($color) || trim($color) === '') {
            return $sanitizedFallback;
        }

        return $color;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function can_render_widget_for_current_user(array $settings): bool
    {
        $allowedRoles = $this->normalize_allowed_frontend_roles($settings['frontend_allowed_roles'] ?? []);
        if (count($allowedRoles) === 0) {
            return false;
        }

        if (!is_user_logged_in()) {
            return in_array('guest', $allowedRoles, true);
        }

        $user = wp_get_current_user();
        if (!($user instanceof WP_User)) {
            return false;
        }

        if (!is_array($user->roles)) {
            return false;
        }

        foreach ($user->roles as $role) {
            $roleKey = sanitize_key((string) $role);
            if ($roleKey !== '' && in_array($roleKey, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalize_allowed_frontend_roles($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $roles = [];
        foreach ($value as $item) {
            $role = sanitize_key((string) $item);
            if ($role !== '') {
                $roles[] = $role;
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $options
     */
    private function render_widget_markup(array $attributes, array $options): string
    {
        $startLabel = is_string($attributes['label'] ?? null) ? (string) $attributes['label'] : __('Start Voice', 'navai-voice');
        $stopLabel = is_string($attributes['stop_label'] ?? null) ? (string) $attributes['stop_label'] : __('Stop Voice', 'navai-voice');
        $debug = $this->to_bool($attributes['debug'] ?? '0');

        $floating = !empty($options['floating']);
        $persistActive = !empty($options['persist_active']);
        $showStatus = array_key_exists('show_status', $options) ? !empty($options['show_status']) : true;
        $widgetMode = isset($options['widget_mode']) ? sanitize_key((string) $options['widget_mode']) : 'shortcode';
        if (!in_array($widgetMode, ['global', 'shortcode'], true)) {
            $widgetMode = 'shortcode';
        }
        $buttonSide = isset($options['button_side']) ? sanitize_key((string) $options['button_side']) : 'left';
        if (!in_array($buttonSide, ['left', 'right'], true)) {
            $buttonSide = 'left';
        }
        $buttonColorIdle = isset($options['button_color_idle']) ? sanitize_hex_color((string) $options['button_color_idle']) : null;
        if (!is_string($buttonColorIdle) || trim($buttonColorIdle) === '') {
            $buttonColorIdle = '#1263dc';
        }
        $buttonColorActive = isset($options['button_color_active']) ? sanitize_hex_color((string) $options['button_color_active']) : null;
        if (!is_string($buttonColorActive) || trim($buttonColorActive) === '') {
            $buttonColorActive = '#10883f';
        }
        $widgetInlineStyle = '--navai-btn-idle-color:' . $buttonColorIdle . ';--navai-btn-connected-color:' . $buttonColorActive . ';';

        $widgetClass = 'navai-voice-widget';
        if ($floating) {
            $widgetClass .= ' navai-voice-widget--floating';
            $widgetClass .= $buttonSide === 'right'
                ? ' navai-voice-widget--side-right'
                : ' navai-voice-widget--side-left';
        }

        if (is_string($attributes['class'] ?? null) && trim((string) $attributes['class']) !== '') {
            $extra = array_filter(
                array_map(
                    'sanitize_html_class',
                    preg_split('/\s+/', trim((string) $attributes['class'])) ?: []
                )
            );
            if (!empty($extra)) {
                $widgetClass .= ' ' . implode(' ', $extra);
            }
        }

        $data = [
            'start-label' => $startLabel,
            'stop-label' => $stopLabel,
            'model' => is_string($attributes['model'] ?? null) ? (string) $attributes['model'] : '',
            'voice' => is_string($attributes['voice'] ?? null) ? (string) $attributes['voice'] : '',
            'instructions' => is_string($attributes['instructions'] ?? null) ? (string) $attributes['instructions'] : '',
            'language' => is_string($attributes['language'] ?? null) ? (string) $attributes['language'] : '',
            'voice-accent' => is_string($attributes['voice_accent'] ?? null) ? (string) $attributes['voice_accent'] : '',
            'voice-tone' => is_string($attributes['voice_tone'] ?? null) ? (string) $attributes['voice_tone'] : '',
            'debug' => $debug ? '1' : '0',
            'widget-mode' => $widgetMode,
            'floating' => $floating ? '1' : '0',
            'button-side' => $buttonSide,
            'persist-active' => $persistActive ? '1' : '0',
        ];

        ob_start();
        ?>
        <div class="<?php echo esc_attr($widgetClass); ?>" style="<?php echo esc_attr($widgetInlineStyle); ?>"
            <?php foreach ($data as $key => $value) : ?>
                <?php printf(' data-%s="%s"', esc_attr($key), esc_attr($value)); ?>
            <?php endforeach; ?>
        >
            <button type="button" class="navai-voice-toggle" aria-pressed="false">
                <span class="navai-voice-toggle-icon dashicons dashicons-microphone" aria-hidden="true"></span>
                <span class="navai-voice-toggle-text"><?php echo esc_html($startLabel); ?></span>
            </button>
            <?php if ($showStatus) : ?>
                <p class="navai-voice-status" aria-live="polite"><?php echo esc_html__('Idle', 'navai-voice'); ?></p>
            <?php endif; ?>
            <pre class="navai-voice-log" <?php echo $debug ? '' : 'hidden'; ?>></pre>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function resolve_admin_icon_url(): string
    {
        $transparentPath = NAVAI_VOICE_PATH . 'assets/img/icon_navai_transparent.png';
        if (file_exists($transparentPath)) {
            return NAVAI_VOICE_URL . 'assets/img/icon_navai_transparent.png';
        }

        return NAVAI_VOICE_URL . 'assets/img/icon_navai.jpg';
    }
}
