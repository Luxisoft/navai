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
    private ?Navai_Voice_Guardrail_Service $guardrailService = null;
    private ?Navai_Voice_Trace_Service $traceService = null;
    private const OPENAI_CLIENT_SECRETS_URL = 'https://api.openai.com/v1/realtime/client_secrets';
    use Navai_Voice_API_Helpers_Trait;

    public function __construct(Navai_Voice_Settings $settings)
    {
        $this->settings = $settings;
        if (class_exists('Navai_Voice_Guardrail_Service', false)) {
            $this->guardrailService = new Navai_Voice_Guardrail_Service();
        }
        if (class_exists('Navai_Voice_Trace_Service', false)) {
            $this->traceService = new Navai_Voice_Trace_Service();
        }
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

        register_rest_route(
            'navai/v1',
            '/guardrails',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'list_guardrails'],
                    'permission_callback' => [$this, 'can_manage_guardrails'],
                ],
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'create_guardrail'],
                    'permission_callback' => [$this, 'can_manage_guardrails'],
                ],
            ]
        );

        register_rest_route(
            'navai/v1',
            '/guardrails/(?P<id>\d+)',
            [
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'update_guardrail'],
                    'permission_callback' => [$this, 'can_manage_guardrails'],
                ],
                [
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => [$this, 'delete_guardrail'],
                    'permission_callback' => [$this, 'can_manage_guardrails'],
                ],
            ]
        );

        register_rest_route(
            'navai/v1',
            '/guardrails/test',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'test_guardrail_match'],
                'permission_callback' => [$this, 'can_manage_guardrails'],
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

    public function can_manage_guardrails(WP_REST_Request $request): bool
    {
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
        $functionSource = isset($definition['source']) && is_string($definition['source'])
            ? $definition['source']
            : 'unknown';

        $inputGuardrail = $this->evaluate_guardrails(
            'input',
            [
                'request' => $request,
                'function_name' => $functionName,
                'function_source' => $functionSource,
                'payload' => $payload,
            ]
        );
        if (!empty($inputGuardrail['blocked'])) {
            return $this->build_guardrail_block_response('input', $functionName, $inputGuardrail, 403);
        }

        $toolGuardrail = $this->evaluate_guardrails(
            'tool',
            [
                'request' => $request,
                'function_name' => $functionName,
                'function_source' => $functionSource,
                'payload' => $payload,
            ]
        );
        if (!empty($toolGuardrail['blocked'])) {
            return $this->build_guardrail_block_response('tool', $functionName, $toolGuardrail, 403);
        }

        try {
            $result = call_user_func(
                $callback,
                $payload,
                [
                    'request' => $request,
                ]
            );
        } catch (Throwable $error) {
            $this->log_trace_event(
                'tool_error',
                [
                    'function_name' => $functionName,
                    'function_source' => $functionSource,
                    'error' => $error->getMessage(),
                    'scope' => 'tool',
                ],
                'error'
            );
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

        $outputGuardrail = $this->evaluate_guardrails(
            'output',
            [
                'request' => $request,
                'function_name' => $functionName,
                'function_source' => $functionSource,
                'result' => $result,
            ]
        );
        if (!empty($outputGuardrail['blocked'])) {
            return $this->build_guardrail_block_response('output', $functionName, $outputGuardrail, 403);
        }

        return rest_ensure_response(
            [
                'ok' => true,
                'function_name' => $definition['name'],
                'source' => $definition['source'],
                'guardrails' => [
                    'input' => [
                        'matched_count' => isset($inputGuardrail['matched_count']) ? (int) $inputGuardrail['matched_count'] : 0,
                    ],
                    'tool' => [
                        'matched_count' => isset($toolGuardrail['matched_count']) ? (int) $toolGuardrail['matched_count'] : 0,
                    ],
                    'output' => [
                        'matched_count' => isset($outputGuardrail['matched_count']) ? (int) $outputGuardrail['matched_count'] : 0,
                    ],
                ],
                'result' => $result,
            ]
        );
    }

    public function list_guardrails(WP_REST_Request $request)
    {
        $service = $this->guardrailService;
        if (!$service) {
            return new WP_Error('navai_guardrails_unavailable', 'Guardrails service is unavailable.', ['status' => 500]);
        }

        $filters = [];
        $scope = sanitize_key((string) $request->get_param('scope'));
        if ($scope !== '') {
            $filters['scope'] = $scope;
        }
        if ($request->has_param('enabled')) {
            $enabledRaw = strtolower(trim((string) $request->get_param('enabled')));
            $filters['enabled'] = in_array($enabledRaw, ['1', 'true', 'yes', 'on'], true);
        }

        $items = $service->list_rules($filters);
        return rest_ensure_response(
            [
                'items' => $items,
                'count' => count($items),
            ]
        );
    }

    public function create_guardrail(WP_REST_Request $request)
    {
        $service = $this->guardrailService;
        if (!$service) {
            return new WP_Error('navai_guardrails_unavailable', 'Guardrails service is unavailable.', ['status' => 500]);
        }

        $input = $request->get_json_params();
        if (!is_array($input)) {
            $input = [];
        }

        $created = $service->create_rule($input);
        if (is_wp_error($created)) {
            return $created;
        }

        return new WP_REST_Response(
            [
                'ok' => true,
                'item' => $created,
            ],
            201
        );
    }

    public function update_guardrail(WP_REST_Request $request)
    {
        $service = $this->guardrailService;
        if (!$service) {
            return new WP_Error('navai_guardrails_unavailable', 'Guardrails service is unavailable.', ['status' => 500]);
        }

        $id = (int) $request['id'];
        $input = $request->get_json_params();
        if (!is_array($input)) {
            $input = [];
        }

        $updated = $service->update_rule($id, $input);
        if (is_wp_error($updated)) {
            return $updated;
        }

        return rest_ensure_response(
            [
                'ok' => true,
                'item' => $updated,
            ]
        );
    }

    public function delete_guardrail(WP_REST_Request $request)
    {
        $service = $this->guardrailService;
        if (!$service) {
            return new WP_Error('navai_guardrails_unavailable', 'Guardrails service is unavailable.', ['status' => 500]);
        }

        $id = (int) $request['id'];
        $deleted = $service->delete_rule($id);

        if (!$deleted) {
            return new WP_Error('navai_guardrail_delete_failed', 'Failed to delete guardrail rule.', ['status' => 404]);
        }

        return rest_ensure_response(
            [
                'ok' => true,
                'id' => $id,
            ]
        );
    }

    public function test_guardrail_match(WP_REST_Request $request)
    {
        $service = $this->guardrailService;
        if (!$service) {
            return new WP_Error('navai_guardrails_unavailable', 'Guardrails service is unavailable.', ['status' => 500]);
        }

        $input = $request->get_json_params();
        if (!is_array($input)) {
            $input = [];
        }

        $scope = sanitize_key((string) ($input['scope'] ?? 'input'));
        $subject = [
            'function_name' => isset($input['function_name']) ? (string) $input['function_name'] : '',
            'function_source' => isset($input['function_source']) ? (string) $input['function_source'] : '',
            'payload' => isset($input['payload']) ? $input['payload'] : [],
            'result' => isset($input['result']) ? $input['result'] : null,
            'text' => isset($input['text']) ? (string) $input['text'] : '',
            'roles' => is_array($input['roles'] ?? null) ? $input['roles'] : $this->get_request_roles(),
        ];

        return rest_ensure_response(
            [
                'ok' => true,
                'evaluation' => $service->evaluate($scope, $subject),
            ]
        );
    }

    /**
     * @param array<string, mixed> $subject
     * @return array<string, mixed>
     */
    private function evaluate_guardrails(string $scope, array $subject): array
    {
        if (!$this->should_enforce_guardrails() || !$this->guardrailService) {
            return [
                'scope' => $scope,
                'blocked' => false,
                'matched_count' => 0,
                'matches' => [],
                'warnings' => [],
            ];
        }

        $subject['roles'] = $this->get_request_roles();
        return $this->guardrailService->evaluate($scope, $subject);
    }

    private function should_enforce_guardrails(): bool
    {
        $settings = $this->settings->get_settings();
        return !array_key_exists('enable_guardrails', $settings) || !empty($settings['enable_guardrails']);
    }

    /**
     * @return array<int, string>
     */
    private function get_request_roles(): array
    {
        if (!is_user_logged_in()) {
            return ['guest'];
        }

        $user = wp_get_current_user();
        if (!($user instanceof WP_User) || !is_array($user->roles)) {
            return ['authenticated'];
        }

        $roles = array_values(array_filter(array_map('sanitize_key', $user->roles)));
        if (count($roles) === 0) {
            $roles[] = 'authenticated';
        }

        return $roles;
    }

    /**
     * @param array<string, mixed> $guardrail
     */
    private function build_guardrail_block_response(string $scope, string $functionName, array $guardrail, int $status): WP_REST_Response
    {
        $event = [
            'scope' => $scope,
            'function_name' => $functionName,
            'matched_count' => isset($guardrail['matched_count']) ? (int) $guardrail['matched_count'] : 0,
            'matches' => is_array($guardrail['matches'] ?? null) ? $guardrail['matches'] : [],
            'roles' => is_array($guardrail['roles'] ?? null) ? $guardrail['roles'] : $this->get_request_roles(),
            'function_source' => isset($guardrail['function_source']) ? (string) $guardrail['function_source'] : '',
        ];

        $traceId = $this->log_trace_event('guardrail_blocked', $event, 'warning');
        $event['trace_id'] = $traceId;

        do_action('navai_guardrail_blocked', $scope, $functionName, $guardrail, $traceId);

        return new WP_REST_Response(
            [
                'ok' => false,
                'function_name' => $functionName,
                'error' => 'Blocked by NAVAI guardrail.',
                'guardrail' => [
                    'scope' => $scope,
                    'matched_count' => $event['matched_count'],
                    'matches' => $event['matches'],
                    'trace_id' => $traceId,
                ],
            ],
            $status
        );
    }

    /**
     * @param array<string, mixed> $event
     */
    private function log_trace_event(string $eventType, array $event, string $severity = 'info'): string
    {
        $traceId = wp_generate_uuid4();
        $event['trace_id'] = $traceId;

        if ($this->traceService) {
            $this->traceService->log_event($eventType, $event, $severity);
        }

        return $traceId;
    }
}
