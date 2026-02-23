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
        add_options_page(
            __('NAVAI Voice', 'navai-voice'),
            __('NAVAI Voice', 'navai-voice'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
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

        add_settings_section(
            'navai_voice_main',
            __('Configuracion principal', 'navai-voice'),
            '__return_null',
            self::PAGE_SLUG
        );

        $this->add_text_field('openai_api_key', __('OpenAI API Key', 'navai-voice'), 'password');
        $this->add_text_field('default_model', __('Modelo Realtime', 'navai-voice'));
        $this->add_text_field('default_voice', __('Voz', 'navai-voice'));
        $this->add_textarea_field('default_instructions', __('Instrucciones base', 'navai-voice'));
        $this->add_text_field('default_language', __('Idioma', 'navai-voice'));
        $this->add_text_field('default_voice_accent', __('Acento de voz', 'navai-voice'));
        $this->add_text_field('default_voice_tone', __('Tono de voz', 'navai-voice'));
        $this->add_number_field('client_secret_ttl', __('TTL client_secret (segundos)', 'navai-voice'));
        $this->add_checkbox_field(
            'allow_public_client_secret',
            __('Permitir client_secret publico (anonimos)', 'navai-voice')
        );
        $this->add_checkbox_field(
            'allow_public_functions',
            __('Permitir funciones backend publicas (anonimos)', 'navai-voice')
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
            // Evita borrar la API key accidentalmente al guardar el formulario vacio.
            $apiKey = (string) ($previous['openai_api_key'] ?? '');
        }

        $ttl = isset($source['client_secret_ttl']) ? (int) $source['client_secret_ttl'] : (int) $defaults['client_secret_ttl'];
        if ($ttl < 10 || $ttl > 7200) {
            $ttl = (int) $defaults['client_secret_ttl'];
        }

        return [
            'openai_api_key' => $apiKey,
            'default_model' => sanitize_text_field((string) ($source['default_model'] ?? $defaults['default_model'])),
            'default_voice' => sanitize_text_field((string) ($source['default_voice'] ?? $defaults['default_voice'])),
            'default_instructions' => sanitize_textarea_field(
                (string) ($source['default_instructions'] ?? $defaults['default_instructions'])
            ),
            'default_language' => sanitize_text_field((string) ($source['default_language'] ?? $defaults['default_language'])),
            'default_voice_accent' => sanitize_text_field(
                (string) ($source['default_voice_accent'] ?? $defaults['default_voice_accent'])
            ),
            'default_voice_tone' => sanitize_text_field(
                (string) ($source['default_voice_tone'] ?? $defaults['default_voice_tone'])
            ),
            'client_secret_ttl' => $ttl,
            'allow_public_client_secret' => !empty($source['allow_public_client_secret']),
            'allow_public_functions' => !empty($source['allow_public_functions']),
        ];
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('NAVAI Voice', 'navai-voice'); ?></h1>
            <p><?php echo esc_html__('Configura credenciales y defaults del runtime de voz.', 'navai-voice'); ?></p>
            <form action="options.php" method="post">
                <?php
                settings_fields('navai_voice_settings_group');
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private function add_text_field(string $key, string $label, string $type = 'text'): void
    {
        add_settings_field(
            $key,
            $label,
            function () use ($key, $type): void {
                $settings = $this->get_settings();
                $value = (string) ($settings[$key] ?? '');
                printf(
                    '<input class="regular-text" type="%s" name="%s[%s]" value="%s" autocomplete="off" />',
                    esc_attr($type),
                    esc_attr(self::OPTION_KEY),
                    esc_attr($key),
                    esc_attr($value)
                );
            },
            self::PAGE_SLUG,
            'navai_voice_main'
        );
    }

    private function add_textarea_field(string $key, string $label): void
    {
        add_settings_field(
            $key,
            $label,
            function () use ($key): void {
                $settings = $this->get_settings();
                $value = (string) ($settings[$key] ?? '');
                printf(
                    '<textarea class="large-text" rows="5" name="%s[%s]">%s</textarea>',
                    esc_attr(self::OPTION_KEY),
                    esc_attr($key),
                    esc_textarea($value)
                );
            },
            self::PAGE_SLUG,
            'navai_voice_main'
        );
    }

    private function add_number_field(string $key, string $label): void
    {
        add_settings_field(
            $key,
            $label,
            function () use ($key): void {
                $settings = $this->get_settings();
                $value = (int) ($settings[$key] ?? 600);
                printf(
                    '<input type="number" min="10" max="7200" step="1" name="%s[%s]" value="%d" />',
                    esc_attr(self::OPTION_KEY),
                    esc_attr($key),
                    $value
                );
            },
            self::PAGE_SLUG,
            'navai_voice_main'
        );
    }

    private function add_checkbox_field(string $key, string $label): void
    {
        add_settings_field(
            $key,
            $label,
            function () use ($key): void {
                $settings = $this->get_settings();
                $checked = !empty($settings[$key]);
                printf(
                    '<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label>',
                    esc_attr(self::OPTION_KEY),
                    esc_attr($key),
                    checked($checked, true, false),
                    esc_html__('Habilitado', 'navai-voice')
                );
            },
            self::PAGE_SLUG,
            'navai_voice_main'
        );
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
        ];
    }
}
