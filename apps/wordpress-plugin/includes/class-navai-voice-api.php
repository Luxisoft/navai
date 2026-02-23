<?php

if (!defined('ABSPATH')) {
    exit;
}

class Navai_Voice_API
{
    private Navai_Voice_Settings $settings;
    private const OPENAI_CLIENT_SECRETS_URL = 'https://api.openai.com/v1/realtime/client_secrets';

    public function __construct(Navai_Voice_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register_routes(): void
    {
        register_rest_route(
            'navai/v1',
            '/realtime/client-secret',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_client_secret'],
                'permission_callback' => [$this, 'can_create_client_secret'],
            ]
        );

        register_rest_route(
            'navai/v1',
            '/functions',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_functions'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'navai/v1',
            '/functions/execute',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'execute_function'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function can_create_client_secret(WP_REST_Request $request): bool
    {
        $settings = $this->settings->get_settings();
        if (!empty($settings['allow_public_client_secret'])) {
            return true;
        }

        return current_user_can('manage_options');
    }

    public function create_client_secret(WP_REST_Request $request)
    {
        if (!$this->check_rate_limit()) {
            return new WP_Error(
                'navai_rate_limit',
                'Too many requests. Try again in a moment.',
                ['status' => 429]
            );
        }

        $settings = $this->settings->get_settings();
        $apiKey = trim((string) ($settings['openai_api_key'] ?? ''));

        if ($apiKey === '') {
            return new WP_Error(
                'navai_missing_api_key',
                'Missing OpenAI API key in NAVAI settings.',
                ['status' => 500]
            );
        }

        $input = $request->get_json_params();
        if (!is_array($input)) {
            $input = [];
        }

        $model = $this->read_input_or_setting($input, 'model', (string) $settings['default_model']);
        $voice = $this->read_input_or_setting($input, 'voice', (string) $settings['default_voice']);
        $baseInstructions = $this->read_input_or_setting(
            $input,
            'instructions',
            (string) $settings['default_instructions']
        );
        $language = $this->read_input_or_setting($input, 'language', (string) $settings['default_language']);
        $voiceAccent = $this->read_input_or_setting($input, 'voiceAccent', (string) $settings['default_voice_accent']);
        $voiceTone = $this->read_input_or_setting($input, 'voiceTone', (string) $settings['default_voice_tone']);

        $ttl = (int) ($settings['client_secret_ttl'] ?? 600);
        if ($ttl < 10 || $ttl > 7200) {
            $ttl = 600;
        }

        $payload = [
            'expires_after' => [
                'anchor' => 'created_at',
                'seconds' => $ttl,
            ],
            'session' => [
                'type' => 'realtime',
                'model' => $model,
                'instructions' => $this->build_session_instructions(
                    $baseInstructions,
                    $language,
                    $voiceAccent,
                    $voiceTone
                ),
                'audio' => [
                    'output' => [
                        'voice' => $voice,
                    ],
                ],
            ],
        ];

        $response = wp_remote_post(
            self::OPENAI_CLIENT_SECRETS_URL,
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error('navai_openai_error', $response->get_error_message(), ['status' => 500]);
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($data) && isset($data['error']['message']) ? (string) $data['error']['message'] : $body;
            return new WP_Error(
                'navai_openai_http_error',
                sprintf('OpenAI client_secrets failed (%d): %s', $statusCode, $message),
                ['status' => 502]
            );
        }

        if (!is_array($data) || empty($data['value']) || !is_string($data['value'])) {
            return new WP_Error('navai_invalid_openai_response', 'Invalid response from OpenAI.', ['status' => 502]);
        }

        return rest_ensure_response(
            [
                'value' => $data['value'],
                'expires_at' => isset($data['expires_at']) ? (int) $data['expires_at'] : null,
            ]
        );
    }

    public function list_functions()
    {
        $registry = $this->get_functions_registry();

        $items = [];
        foreach ($registry['ordered'] as $item) {
            $items[] = [
                'name' => $item['name'],
                'description' => $item['description'],
                'source' => $item['source'],
            ];
        }

        return rest_ensure_response(
            [
                'items' => $items,
                'warnings' => $registry['warnings'],
            ]
        );
    }

    public function execute_function(WP_REST_Request $request)
    {
        $input = $request->get_json_params();
        if (!is_array($input)) {
            $input = [];
        }

        $functionName = isset($input['function_name']) ? strtolower(trim((string) $input['function_name'])) : '';
        if ($functionName === '') {
            return new WP_Error('navai_invalid_function_name', 'function_name is required.', ['status' => 400]);
        }

        $payload = isset($input['payload']) && is_array($input['payload']) ? $input['payload'] : [];
        $registry = $this->get_functions_registry();

        if (!isset($registry['by_name'][$functionName])) {
            return new WP_REST_Response(
                [
                    'error' => 'Unknown or disallowed function.',
                    'available_functions' => array_map(
                        static fn(array $item): string => $item['name'],
                        $registry['ordered']
                    ),
                ],
                404
            );
        }

        /** @var array<string, mixed> $definition */
        $definition = $registry['by_name'][$functionName];
        $callback = $definition['callback'];

        try {
            $result = call_user_func(
                $callback,
                $payload,
                [
                    'request' => $request,
                ]
            );
        } catch (Throwable $error) {
            return new WP_REST_Response(
                [
                    'ok' => false,
                    'function_name' => $definition['name'],
                    'error' => 'Function execution failed.',
                    'details' => $error->getMessage(),
                ],
                500
            );
        }

        return rest_ensure_response(
            [
                'ok' => true,
                'function_name' => $definition['name'],
                'source' => $definition['source'],
                'result' => $result,
            ]
        );
    }

    /**
     * @return array{
     *   by_name: array<string, array{name: string, description: string, source: string, callback: callable}>,
     *   ordered: array<int, array{name: string, description: string, source: string, callback: callable}>,
     *   warnings: array<int, string>
     * }
     */
    private function get_functions_registry(): array
    {
        $warnings = [];
        $byName = [];
        $ordered = [];

        $raw = apply_filters('navai_voice_functions_registry', []);
        if (!is_array($raw)) {
            $raw = [];
        }

        foreach ($raw as $index => $item) {
            if (!is_array($item)) {
                $warnings[] = sprintf('[navai] Ignored function #%d: invalid definition.', (int) $index);
                continue;
            }

            $rawName = isset($item['name']) ? (string) $item['name'] : '';
            $name = $this->normalize_function_name($rawName);
            if ($name === '') {
                $warnings[] = sprintf('[navai] Ignored function #%d: invalid name.', (int) $index);
                continue;
            }

            if (isset($byName[$name])) {
                $warnings[] = sprintf('[navai] Ignored duplicated function "%s".', $name);
                continue;
            }

            $callback = $item['callback'] ?? null;
            if (!is_callable($callback)) {
                $warnings[] = sprintf('[navai] Ignored function "%s": callback is not callable.', $name);
                continue;
            }

            $definition = [
                'name' => $name,
                'description' => isset($item['description']) && is_string($item['description'])
                    ? $item['description']
                    : 'Execute backend function.',
                'source' => isset($item['source']) && is_string($item['source']) ? $item['source'] : 'wp-filter',
                'callback' => $callback,
            ];

            $byName[$name] = $definition;
            $ordered[] = $definition;
        }

        return [
            'by_name' => $byName,
            'ordered' => $ordered,
            'warnings' => $warnings,
        ];
    }

    private function normalize_function_name(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized);
        if (!is_string($normalized)) {
            return '';
        }

        $normalized = trim($normalized, '_');
        if ($normalized === '') {
            return '';
        }

        return substr($normalized, 0, 64);
    }

    private function read_input_or_setting(array $input, string $key, string $fallback): string
    {
        if (isset($input[$key]) && is_string($input[$key])) {
            $value = trim($input[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        $cleanFallback = trim($fallback);
        return $cleanFallback !== '' ? $cleanFallback : '';
    }

    private function build_session_instructions(
        string $baseInstructions,
        string $language,
        string $voiceAccent,
        string $voiceTone
    ): string {
        $lines = [trim($baseInstructions) !== '' ? trim($baseInstructions) : 'You are a helpful assistant.'];

        $language = trim($language);
        if ($language !== '') {
            $lines[] = sprintf('Always reply in %s.', $language);
        }

        $voiceAccent = trim($voiceAccent);
        if ($voiceAccent !== '') {
            $lines[] = sprintf('Use a %s accent while speaking.', $voiceAccent);
        }

        $voiceTone = trim($voiceTone);
        if ($voiceTone !== '') {
            $lines[] = sprintf('Use a %s tone while speaking.', $voiceTone);
        }

        return implode("\n", $lines);
    }

    private function check_rate_limit(): bool
    {
        $ip = $this->get_client_ip();
        $key = 'navai_voice_rl_' . md5($ip);
        $bucket = get_transient($key);
        $now = time();

        if (!is_array($bucket) || !isset($bucket['count'], $bucket['started_at'])) {
            $bucket = [
                'count' => 0,
                'started_at' => $now,
            ];
        }

        $windowSeconds = 60;
        $maxRequestsPerWindow = 30;
        $elapsed = $now - (int) $bucket['started_at'];
        if ($elapsed >= $windowSeconds) {
            $bucket = [
                'count' => 0,
                'started_at' => $now,
            ];
        }

        if ((int) $bucket['count'] >= $maxRequestsPerWindow) {
            return false;
        }

        $bucket['count'] = (int) $bucket['count'] + 1;
        set_transient($key, $bucket, $windowSeconds);

        return true;
    }

    private function get_client_ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_CLIENT_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        foreach ($candidates as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $parts = explode(',', $value);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return 'unknown';
    }
}
