<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_API', false)) {
    return;
}

require_once __DIR__ . '/traits/trait-navai-voice-api-helpers.php';

class Navai_Voice_API
{
    private Navai_Voice_Settings $settings;
    private const OPENAI_CLIENT_SECRETS_URL = 'https://api.openai.com/v1/realtime/client_secrets';
    use Navai_Voice_API_Helpers_Trait;

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
                'permission_callback' => [$this, 'can_access_functions'],
            ]
        );

        register_rest_route(
            'navai/v1',
            '/routes',
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_routes'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'navai/v1',
            '/functions/execute',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'execute_function'],
                'permission_callback' => [$this, 'can_access_functions'],
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

    public function can_access_functions(WP_REST_Request $request): bool
    {
        $settings = $this->settings->get_settings();
        if (!empty($settings['allow_public_functions'])) {
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

    public function list_functions(WP_REST_Request $request)
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

    public function list_routes(WP_REST_Request $request)
    {
        $routes = $this->build_allowed_routes_catalog();
        $response = new WP_REST_Response(
            [
                'items' => $routes,
                'count' => count($routes),
            ],
            200
        );
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('X-NAVAI-Routes-Count', (string) count($routes));

        return $response;
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
}
