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
        add_shortcode('navai_voice', [$this, 'render_voice_shortcode']);
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
        $defaultRoutes = [
            [
                'name' => 'inicio',
                'path' => home_url('/'),
                'description' => __('Pagina principal del sitio.', 'navai-voice'),
                'synonyms' => ['home', 'home page', 'pagina principal', 'inicio'],
            ],
        ];

        /** @var mixed $raw */
        $raw = apply_filters('navai_voice_routes', $defaultRoutes);
        if (!is_array($raw)) {
            $raw = $defaultRoutes;
        }

        $routes = [];
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
            if ($path === '') {
                continue;
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
}
