<?php

if (!defined('ABSPATH')) {
    exit;
}

class Navai_Voice_Plugin
{
    private Navai_Voice_Settings $settings;
    private Navai_Voice_API $api;

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

        wp_localize_script(
            'navai-voice',
            'NAVAI_VOICE_CONFIG',
            [
                'restBaseUrl' => esc_url_raw(rest_url('navai/v1')),
                'restNonce' => wp_create_nonce('wp_rest'),
                'messages' => [
                    'idle' => __('Idle', 'navai-voice'),
                    'connecting' => __('Connecting...', 'navai-voice'),
                    'connected' => __('Connected', 'navai-voice'),
                    'stopped' => __('Stopped', 'navai-voice'),
                    'failed' => __('Failed to request client_secret.', 'navai-voice'),
                ],
            ]
        );
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render_voice_shortcode(array $atts = []): string
    {
        wp_enqueue_style('navai-voice');
        wp_enqueue_script('navai-voice');

        $attributes = shortcode_atts(
            [
                'label' => __('Start Voice', 'navai-voice'),
            ],
            $atts
        );

        $buttonLabel = is_string($attributes['label']) ? $attributes['label'] : __('Start Voice', 'navai-voice');

        ob_start();
        ?>
        <div class="navai-voice-widget">
            <button type="button" class="navai-voice-toggle"><?php echo esc_html($buttonLabel); ?></button>
            <p class="navai-voice-status" aria-live="polite"><?php echo esc_html__('Idle', 'navai-voice'); ?></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
