<?php

if (!defined('ABSPATH')) {
    exit;
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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
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
        wp_enqueue_style('navai-voice');
        wp_enqueue_script('navai-voice');

        $defaults = $this->settings->get_settings();
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

        $startLabel = is_string($attributes['label']) ? $attributes['label'] : __('Start Voice', 'navai-voice');
        $stopLabel = is_string($attributes['stop_label']) ? $attributes['stop_label'] : __('Stop Voice', 'navai-voice');
        $debug = $this->to_bool($attributes['debug'] ?? '0');

        $widgetClass = 'navai-voice-widget';
        if (is_string($attributes['class']) && trim($attributes['class']) !== '') {
            $extra = array_filter(array_map('sanitize_html_class', preg_split('/\s+/', trim($attributes['class'])) ?: []));
            if (!empty($extra)) {
                $widgetClass .= ' ' . implode(' ', $extra);
            }
        }

        $data = [
            'start-label' => $startLabel,
            'stop-label' => $stopLabel,
            'model' => is_string($attributes['model']) ? $attributes['model'] : '',
            'voice' => is_string($attributes['voice']) ? $attributes['voice'] : '',
            'instructions' => is_string($attributes['instructions']) ? $attributes['instructions'] : '',
            'language' => is_string($attributes['language']) ? $attributes['language'] : '',
            'voice-accent' => is_string($attributes['voice_accent']) ? $attributes['voice_accent'] : '',
            'voice-tone' => is_string($attributes['voice_tone']) ? $attributes['voice_tone'] : '',
            'debug' => $debug ? '1' : '0',
        ];

        ob_start();
        ?>
        <div class="<?php echo esc_attr($widgetClass); ?>"
            <?php foreach ($data as $key => $value) : ?>
                <?php printf(' data-%s="%s"', esc_attr($key), esc_attr($value)); ?>
            <?php endforeach; ?>
        >
            <button type="button" class="navai-voice-toggle"><?php echo esc_html($startLabel); ?></button>
            <p class="navai-voice-status" aria-live="polite"><?php echo esc_html__('Idle', 'navai-voice'); ?></p>
            <pre class="navai-voice-log" <?php echo $debug ? '' : 'hidden'; ?>></pre>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    private function resolve_public_routes(): array
    {
        $settings = $this->settings->get_settings();
        $hasSelectedMenuItems = $this->has_selected_menu_items($settings);
        $baseRoutes = [
            [
                'name' => 'inicio',
                'path' => home_url('/'),
                'description' => __('Pagina principal del sitio.', 'navai-voice'),
                'synonyms' => ['home', 'home page', 'pagina principal', 'inicio'],
            ],
        ];

        $menuRoutes = $this->get_menu_routes_from_settings($settings);
        $baseRoutes = array_merge($baseRoutes, $menuRoutes);

        // Only fallback to published pages when user did not select specific menu items.
        if (!$hasSelectedMenuItems && count($baseRoutes) <= 1) {
            $baseRoutes = array_merge($baseRoutes, $this->get_published_page_routes());
        }

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
            return array_values($routesById);
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
     * @param array<string, mixed> $settings
     */
    private function has_selected_menu_items(array $settings): bool
    {
        return count($this->get_selected_menu_item_ids($settings)) > 0;
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
        $dedupe = [];
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

                $dedupeKey = $this->build_route_dedupe_key((string) $route['name'], (string) $route['path']);
                if (isset($dedupe[$dedupeKey])) {
                    continue;
                }

                $dedupe[$dedupeKey] = true;
                $routesById[$itemId] = $route;
            }
        }

        return $routesById;
    }

    /**
     * @return array<int, array{name: string, path: string, description: string, synonyms: array<int, string>}>
     */
    private function get_published_page_routes(): array
    {
        if (!function_exists('get_pages') || !function_exists('get_permalink')) {
            return [];
        }

        $pages = get_pages(
            [
                'post_status' => 'publish',
                'sort_column' => 'menu_order,post_title',
                'number' => 40,
            ]
        );
        if (!is_array($pages)) {
            return [];
        }

        $routes = [];
        $dedupe = [];
        foreach ($pages as $page) {
            if (!is_object($page) || !isset($page->ID, $page->post_title)) {
                continue;
            }

            $title = trim(wp_strip_all_tags((string) $page->post_title));
            if ($title === '') {
                continue;
            }

            $url = get_permalink((int) $page->ID);
            if (!is_string($url) || !$this->is_navigable_url($url)) {
                continue;
            }

            $key = $this->build_route_dedupe_key($title, $url);
            if (isset($dedupe[$key])) {
                continue;
            }

            $dedupe[$key] = true;
            $routes[] = [
                'name' => $title,
                'path' => $url,
                'description' => __('Pagina publicada en WordPress.', 'navai-voice'),
                'synonyms' => $this->build_route_synonyms($title, $url),
            ];
        }

        return $routes;
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

    private function resolve_admin_icon_url(): string
    {
        $transparentPath = NAVAI_VOICE_PATH . 'assets/img/icon_navai_transparent.png';
        if (file_exists($transparentPath)) {
            return NAVAI_VOICE_URL . 'assets/img/icon_navai_transparent.png';
        }

        return NAVAI_VOICE_URL . 'assets/img/icon_navai.jpg';
    }
}
