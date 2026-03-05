<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_MCP_Service', false)) {
    return;
}

class Navai_Voice_MCP_Service
{
    private Navai_Voice_MCP_Repository $repository;

    public function __construct(?Navai_Voice_MCP_Repository $repository = null)
    {
        $this->repository = $repository ?: new Navai_Voice_MCP_Repository();
    }

    public function get_runtime_config(array $settings = []): array
    {
        return [
            'enabled' => !array_key_exists('enable_mcp', $settings) || !empty($settings['enable_mcp']),
        ];
    }

    public function list_servers(array $filters = []): array
    {
        $rows = $this->repository->list_servers($filters);
        return array_map(fn(array $row): array => $this->normalize_server_row($row, false), $rows);
    }

    public function get_server(int $id): ?array
    {
        $row = $this->repository->get_server($id);
        return is_array($row) ? $this->normalize_server_row($row, false) : null;
    }

    public function get_server_by_key(string $serverKey): ?array
    {
        $row = $this->repository->get_server_by_key($this->sanitize_server_key($serverKey));
        return is_array($row) ? $this->normalize_server_row($row, false) : null;
    }

    public function create_server(array $payload)
    {
        $normalized = $this->normalize_server_payload($payload, null);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $existing = $this->repository->get_server_by_key((string) $normalized['server_key']);
        if (is_array($existing)) {
            return new WP_Error('navai_mcp_server_key_exists', 'server_key already exists.', ['status' => 409]);
        }

        $created = $this->repository->create_server($normalized);
        if (!is_array($created)) {
            return new WP_Error('navai_mcp_server_create_failed', 'Failed to create MCP server.', ['status' => 500]);
        }

        return $this->normalize_server_row($created, false);
    }

    public function update_server(int $id, array $payload)
    {
        $existingRow = $this->repository->get_server($id);
        if (!is_array($existingRow)) {
            return new WP_Error('navai_mcp_server_not_found', 'MCP server not found.', ['status' => 404]);
        }

        $existing = $this->normalize_server_row($existingRow, true);
        $normalized = $this->normalize_server_payload($payload, $existing);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $other = $this->repository->get_server_by_key((string) $normalized['server_key']);
        if (is_array($other) && (int) ($other['id'] ?? 0) !== $id) {
            return new WP_Error('navai_mcp_server_key_exists', 'server_key already exists.', ['status' => 409]);
        }

        $updated = $this->repository->update_server($id, $normalized);
        if (!is_array($updated)) {
            return new WP_Error('navai_mcp_server_update_failed', 'Failed to update MCP server.', ['status' => 500]);
        }

        return $this->normalize_server_row($updated, false);
    }

    public function delete_server(int $id): bool
    {
        return $this->repository->delete_server($id);
    }

    public function list_tool_policies(array $filters = []): array
    {
        $rows = $this->repository->list_policies($filters);
        if (count($rows) === 0) {
            return [];
        }

        $serversById = [];
        foreach ($this->list_servers(['limit' => 2000]) as $server) {
            $sid = isset($server['id']) && is_numeric($server['id']) ? (int) $server['id'] : 0;
            if ($sid > 0) {
                $serversById[$sid] = $server;
            }
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->normalize_policy_row($row, $serversById);
        }

        return $items;
    }

    public function create_tool_policy(array $payload)
    {
        $normalized = $this->normalize_policy_payload($payload, null);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $created = $this->repository->create_policy($normalized);
        if (!is_array($created)) {
            return new WP_Error('navai_mcp_policy_create_failed', 'Failed to create MCP policy.', ['status' => 500]);
        }

        return $this->get_policy_or_fallback((int) ($created['id'] ?? 0), $created);
    }

    public function update_tool_policy(int $id, array $payload)
    {
        $existingRow = $this->repository->get_policy($id);
        if (!is_array($existingRow)) {
            return new WP_Error('navai_mcp_policy_not_found', 'MCP policy not found.', ['status' => 404]);
        }

        $existing = $this->normalize_policy_row($existingRow, []);
        $normalized = $this->normalize_policy_payload($payload, $existing);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $updated = $this->repository->update_policy($id, $normalized);
        if (!is_array($updated)) {
            return new WP_Error('navai_mcp_policy_update_failed', 'Failed to update MCP policy.', ['status' => 500]);
        }

        return $this->get_policy_or_fallback((int) ($updated['id'] ?? 0), $updated);
    }

    public function delete_tool_policy(int $id): bool
    {
        return $this->repository->delete_policy($id);
    }

    public function health_check_server(int $id, bool $syncTools = true)
    {
        $serverRow = $this->repository->get_server($id);
        if (!is_array($serverRow)) {
            return new WP_Error('navai_mcp_server_not_found', 'MCP server not found.', ['status' => 404]);
        }

        $server = $this->normalize_server_row($serverRow, true);
        $checkedAt = current_time('mysql');
        $healthStatus = 'error';
        $healthMessage = 'Health check failed.';
        $httpStatus = 0;
        $tools = is_array($server['tools'] ?? null) ? $server['tools'] : [];
        $toolsSynced = false;

        $toolsResult = $this->request_tools_list($server);
        if (is_wp_error($toolsResult)) {
            $errorData = $toolsResult->get_error_data();
            if (is_array($errorData) && isset($errorData['http_status']) && is_numeric($errorData['http_status'])) {
                $httpStatus = (int) $errorData['http_status'];
            }
            $healthMessage = $toolsResult->get_error_message();
        } else {
            $httpStatus = isset($toolsResult['http_status']) && is_numeric($toolsResult['http_status']) ? (int) $toolsResult['http_status'] : 200;
            $healthStatus = 'healthy';
            $healthMessage = 'MCP tools/list OK.';
            if ($syncTools) {
                $tools = is_array($toolsResult['tools'] ?? null) ? $toolsResult['tools'] : [];
                $toolsSynced = true;
            }
        }

        $update = [
            'last_health_status' => $healthStatus,
            'last_health_message' => $healthMessage,
            'last_http_status' => $httpStatus,
            'last_health_checked_at' => $checkedAt,
        ];

        if ($syncTools && $toolsSynced) {
            $update['tools'] = $tools;
            $update['tools_hash'] = md5((string) wp_json_encode($tools));
            $update['tool_count'] = count($tools);
            $update['last_tools_sync_at'] = $checkedAt;
        }

        $updatedRow = $this->repository->update_server($id, $update);
        if (!is_array($updatedRow)) {
            return new WP_Error('navai_mcp_server_health_update_failed', 'Failed to persist MCP health check.', ['status' => 500]);
        }

        $updated = $this->normalize_server_row($updatedRow, false);
        return [
            'ok' => $healthStatus === 'healthy',
            'server' => $updated,
            'health' => [
                'status' => $healthStatus,
                'message' => $healthMessage,
                'http_status' => $httpStatus,
                'checked_at' => $checkedAt,
                'tools_synced' => $toolsSynced && $syncTools,
                'tool_count' => count(is_array($updated['tools'] ?? null) ? $updated['tools'] : []),
            ],
        ];
    }

    public function list_server_tools(int $id, bool $refresh = false)
    {
        if ($refresh) {
            $health = $this->health_check_server($id, true);
            if (is_wp_error($health)) {
                return $health;
            }
            $server = is_array($health['server'] ?? null) ? $health['server'] : null;
            if (!is_array($server)) {
                return new WP_Error('navai_mcp_server_not_found', 'MCP server not found.', ['status' => 404]);
            }
            $items = is_array($server['tools'] ?? null) ? $server['tools'] : [];
            $items = $this->decorate_runtime_tool_list((string) ($server['server_key'] ?? ''), $items);
            return [
                'ok' => !empty($health['ok']),
                'refreshed' => true,
                'server' => $server,
                'items' => $items,
                'count' => count($items),
                'health' => $health['health'] ?? null,
            ];
        }

        $server = $this->get_server($id);
        if (!is_array($server)) {
            return new WP_Error('navai_mcp_server_not_found', 'MCP server not found.', ['status' => 404]);
        }

        $items = is_array($server['tools'] ?? null) ? $server['tools'] : [];
        $items = $this->decorate_runtime_tool_list((string) ($server['server_key'] ?? ''), $items);
        return [
            'ok' => true,
            'refreshed' => false,
            'server' => $server,
            'items' => $items,
            'count' => count($items),
        ];
    }

    public function build_runtime_tool_definitions(array $settings = [], array $roles = []): array
    {
        $config = $this->get_runtime_config($settings);
        if (!$config['enabled']) {
            return [];
        }

        $servers = $this->list_servers(['enabled' => true, 'limit' => 1000]);
        if (count($servers) === 0) {
            return [];
        }

        $definitions = [];
        foreach ($servers as $server) {
            $serverId = isset($server['id']) && is_numeric($server['id']) ? (int) $server['id'] : 0;
            $serverKey = sanitize_text_field((string) ($server['server_key'] ?? ''));
            $serverName = sanitize_text_field((string) ($server['name'] ?? ''));
            $tools = is_array($server['tools'] ?? null) ? $server['tools'] : [];
            if ($serverId <= 0 || $serverKey === '' || count($tools) === 0) {
                continue;
            }

            foreach ($tools as $tool) {
                if (!is_array($tool)) {
                    continue;
                }

                $toolName = sanitize_text_field((string) ($tool['name'] ?? ''));
                if ($toolName === '') {
                    continue;
                }

                $authz = $this->authorize_tool_call([
                    'server_id' => $serverId,
                    'server_key' => $serverKey,
                    'tool_name' => $toolName,
                    'roles' => $roles,
                    'agent_key' => '',
                    'ignore_agent_scope' => true,
                ]);
                if (empty($authz['allowed'])) {
                    continue;
                }

                $runtimeName = $this->build_runtime_function_name($serverKey, $toolName);
                $description = sanitize_text_field((string) ($tool['description'] ?? ''));
                if ($description === '') {
                    $description = 'MCP remote tool.';
                }
                if ($serverName !== '') {
                    $description .= ' (' . $serverName . ')';
                }

                $definitions[] = [
                    'name' => $runtimeName,
                    'description' => $description,
                    'source' => 'mcp:' . $serverKey,
                    'argument_schema' => is_array($tool['input_schema'] ?? null) ? $tool['input_schema'] : null,
                    'mcp_server_id' => $serverId,
                    'mcp_server_key' => $serverKey,
                    'mcp_server_name' => $serverName,
                    'mcp_tool_name' => $toolName,
                    'mcp_tool' => $tool,
                    'callback' => function (array $payload, array $context) use ($serverKey, $toolName) {
                        $arguments = [];
                        if (isset($payload['args']) && is_array($payload['args'])) {
                            $arguments = $payload['args'];
                        } elseif (isset($payload['payload']) && is_array($payload['payload'])) {
                            $arguments = $payload['payload'];
                        } elseif (is_array($payload)) {
                            $arguments = $payload;
                        }

                        return $this->invoke_tool_by_keys($serverKey, $toolName, $arguments, is_array($context) ? $context : []);
                    },
                ];
            }
        }

        return $definitions;
    }

    public function authorize_tool_call(array $input): array
    {
        $serverId = isset($input['server_id']) && is_numeric($input['server_id']) ? (int) $input['server_id'] : 0;
        $serverKey = sanitize_text_field((string) ($input['server_key'] ?? ''));
        $toolName = sanitize_text_field((string) ($input['tool_name'] ?? ''));
        $roles = $this->normalize_string_list($input['roles'] ?? [], 50, 40, true);
        $agentKey = $this->sanitize_agent_key((string) ($input['agent_key'] ?? ''));
        $ignoreAgentScope = !empty($input['ignore_agent_scope']);

        if ($toolName === '') {
            return ['allowed' => false, 'reason' => 'MCP tool name is required.', 'matched_policy' => null, 'allowlist_present' => false];
        }

        if ($serverId <= 0 && $serverKey !== '') {
            $row = $this->repository->get_server_by_key($this->sanitize_server_key($serverKey));
            if (is_array($row) && isset($row['id']) && is_numeric($row['id'])) {
                $serverId = (int) $row['id'];
            }
        }

        $policies = $this->list_tool_policies(['enabled' => true, 'limit' => 5000]);
        if (count($policies) === 0) {
            return ['allowed' => true, 'reason' => '', 'matched_policy' => null, 'allowlist_present' => false];
        }

        $toolKey = strtolower(trim($toolName));
        $candidates = [];
        foreach ($policies as $policy) {
            if (!is_array($policy)) {
                continue;
            }

            $policyServerId = isset($policy['server_id']) && is_numeric($policy['server_id']) ? (int) $policy['server_id'] : 0;
            if ($policyServerId > 0 && ($serverId <= 0 || $policyServerId !== $serverId)) {
                continue;
            }

            $policyToolKey = strtolower(trim((string) ($policy['tool_name'] ?? '*')));
            if ($policyToolKey !== '*' && $policyToolKey !== $toolKey) {
                continue;
            }

            $candidates[] = $policy;
        }

        if (count($candidates) === 0) {
            return ['allowed' => true, 'reason' => '', 'matched_policy' => null, 'allowlist_present' => false];
        }

        foreach ($candidates as $policy) {
            if (sanitize_key((string) ($policy['mode'] ?? '')) !== 'deny') {
                continue;
            }
            if ($this->policy_applies_to_actor($policy, $roles, $agentKey, $ignoreAgentScope)) {
                return [
                    'allowed' => false,
                    'reason' => 'Blocked by MCP deny policy.',
                    'matched_policy' => $policy,
                    'allowlist_present' => true,
                ];
            }
        }

        $allowPolicies = array_values(array_filter(
            $candidates,
            static fn($policy): bool => is_array($policy) && sanitize_key((string) ($policy['mode'] ?? '')) === 'allow'
        ));

        if (count($allowPolicies) === 0) {
            return ['allowed' => true, 'reason' => '', 'matched_policy' => null, 'allowlist_present' => false];
        }

        foreach ($allowPolicies as $policy) {
            if ($this->policy_applies_to_actor($policy, $roles, $agentKey, $ignoreAgentScope)) {
                return ['allowed' => true, 'reason' => '', 'matched_policy' => $policy, 'allowlist_present' => true];
            }
        }

        return [
            'allowed' => false,
            'reason' => 'MCP tool is not included in the matching allowlist.',
            'matched_policy' => null,
            'allowlist_present' => true,
        ];
    }

    public function invoke_tool_by_keys(string $serverKey, string $toolName, array $arguments = [], array $context = []): array
    {
        $serverRow = $this->repository->get_server_by_key($this->sanitize_server_key($serverKey));
        if (!is_array($serverRow)) {
            return ['ok' => false, 'error' => 'MCP server not found.', 'server_key' => $serverKey, 'tool_name' => $toolName];
        }

        $server = $this->normalize_server_row($serverRow, true);
        if (empty($server['enabled'])) {
            return ['ok' => false, 'error' => 'MCP server is disabled.', 'server_key' => (string) ($server['server_key'] ?? $serverKey), 'tool_name' => $toolName];
        }

        $agent = is_array($context['agent'] ?? null) ? $context['agent'] : [];
        $agentKey = $this->sanitize_agent_key((string) ($agent['agent_key'] ?? ''));
        $roles = is_array($context['roles'] ?? null)
            ? $this->normalize_string_list($context['roles'], 50, 40, true)
            : $this->detect_current_roles();

        $authz = $this->authorize_tool_call([
            'server_id' => isset($server['id']) ? (int) $server['id'] : 0,
            'server_key' => (string) ($server['server_key'] ?? $serverKey),
            'tool_name' => $toolName,
            'roles' => $roles,
            'agent_key' => $agentKey,
        ]);
        if (empty($authz['allowed'])) {
            return [
                'ok' => false,
                'blocked' => true,
                'error' => (string) ($authz['reason'] ?? 'MCP tool blocked by policy.'),
                'server_key' => (string) ($server['server_key'] ?? $serverKey),
                'server_name' => (string) ($server['name'] ?? ''),
                'tool_name' => $toolName,
                'policy' => $authz['matched_policy'] ?? null,
            ];
        }

        $rpc = $this->jsonrpc_request($server, 'tools/call', [
            'name' => $toolName,
            'arguments' => is_array($arguments) ? $arguments : [],
        ]);
        if (is_wp_error($rpc)) {
            $errorData = $rpc->get_error_data();
            $httpStatus = is_array($errorData) && isset($errorData['http_status']) && is_numeric($errorData['http_status'])
                ? (int) $errorData['http_status']
                : 0;
            return [
                'ok' => false,
                'error' => $rpc->get_error_message(),
                'server_key' => (string) ($server['server_key'] ?? $serverKey),
                'server_name' => (string) ($server['name'] ?? ''),
                'tool_name' => $toolName,
                'http_status' => $httpStatus > 0 ? $httpStatus : null,
            ];
        }

        $result = $rpc['result'] ?? null;
        $content = is_array($result) && isset($result['content']) ? $result['content'] : null;
        return [
            'ok' => true,
            'server_key' => (string) ($server['server_key'] ?? $serverKey),
            'server_name' => (string) ($server['name'] ?? ''),
            'tool_name' => $toolName,
            'http_status' => isset($rpc['http_status']) && is_numeric($rpc['http_status']) ? (int) $rpc['http_status'] : 200,
            'content_text' => $this->extract_mcp_content_text($content),
            'mcp' => ['result' => $result],
            'result' => $result,
        ];
    }

    private function policy_applies_to_actor(array $policy, array $roles, string $agentKey, bool $ignoreAgentScope = false): bool
    {
        $policyRoles = is_array($policy['roles'] ?? null) ? $this->normalize_string_list($policy['roles'], 100, 40, true) : [];
        $policyAgentKeys = is_array($policy['agent_keys'] ?? null) ? $this->normalize_string_list($policy['agent_keys'], 100, 64, true) : [];

        if (count($policyRoles) > 0 && count(array_intersect($policyRoles, $roles)) === 0) {
            return false;
        }
        if ($ignoreAgentScope) {
            return true;
        }
        if (count($policyAgentKeys) > 0 && ($agentKey === '' || !in_array($agentKey, $policyAgentKeys, true))) {
            return false;
        }
        return true;
    }

    private function detect_current_roles(): array
    {
        if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
            return ['guest'];
        }
        if (!function_exists('wp_get_current_user')) {
            return ['authenticated'];
        }

        $user = wp_get_current_user();
        if (!($user instanceof WP_User) || !is_array($user->roles)) {
            return ['authenticated'];
        }

        $roles = array_values(array_filter(array_map('sanitize_key', $user->roles)));
        return count($roles) > 0 ? $roles : ['authenticated'];
    }

    private function request_tools_list(array $server)
    {
        $rpc = $this->jsonrpc_request($server, 'tools/list', []);
        if (is_wp_error($rpc)) {
            return $rpc;
        }

        $rawResult = $rpc['result'] ?? null;
        $rawTools = [];
        if (is_array($rawResult) && is_array($rawResult['tools'] ?? null)) {
            $rawTools = $rawResult['tools'];
        } elseif (is_array($rawResult) && $this->is_list_array($rawResult)) {
            $rawTools = $rawResult;
        }

        return [
            'http_status' => isset($rpc['http_status']) ? (int) $rpc['http_status'] : 200,
            'tools' => $this->normalize_tools_payload($rawTools),
            'result' => $rawResult,
        ];
    }

    private function jsonrpc_request(array $server, string $method, array $params = [])
    {
        $url = esc_url_raw((string) ($server['base_url'] ?? ''));
        if ($url === '') {
            return new WP_Error('navai_mcp_invalid_url', 'MCP server URL is required.', ['http_status' => 0]);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
        ];

        $authType = sanitize_key((string) ($server['auth_type'] ?? 'none'));
        $authValue = (string) ($server['auth_value_raw'] ?? '');
        $authHeaderName = sanitize_text_field((string) ($server['auth_header_name'] ?? ''));

        if ($authType === 'bearer' && $authValue !== '') {
            $headers['Authorization'] = 'Bearer ' . $authValue;
        } elseif ($authType === 'basic' && $authValue !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($authValue);
        } elseif ($authType === 'header' && $authValue !== '') {
            $headers[$authHeaderName !== '' ? $authHeaderName : 'Authorization'] = $authValue;
        }

        $extraHeaders = is_array($server['extra_headers'] ?? null) ? $server['extra_headers'] : [];
        foreach ($extraHeaders as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $headerName = sanitize_text_field($key);
            if ($headerName === '') {
                continue;
            }
            $headers[$headerName] = sanitize_text_field((string) $value);
        }

        $timeoutRead = isset($server['timeout_read_seconds']) && is_numeric($server['timeout_read_seconds']) ? (int) $server['timeout_read_seconds'] : 20;
        $timeoutRead = max(1, min(120, $timeoutRead));

        $body = [
            'jsonrpc' => '2.0',
            'id' => 'navai-' . wp_generate_uuid4(),
            'method' => $method,
            'params' => is_array($params) ? $params : [],
        ];

        $response = wp_remote_post($url, [
            'timeout' => $timeoutRead,
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'sslverify' => !empty($server['verify_ssl']),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('navai_mcp_request_failed', $response->get_error_message(), ['http_status' => 0]);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $rawBody = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($rawBody, true);

        if ($status < 200 || $status >= 300) {
            $message = '';
            if (is_array($decoded) && isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                $message = $decoded['error']['message'];
            } elseif (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
                $message = $decoded['message'];
            } else {
                $message = $rawBody !== '' ? $rawBody : ('HTTP ' . $status);
            }
            return new WP_Error('navai_mcp_http_error', sprintf('MCP request failed (%d): %s', $status, $message), ['http_status' => $status]);
        }

        if (!is_array($decoded)) {
            return new WP_Error('navai_mcp_invalid_response', 'Invalid JSON response from MCP server.', ['http_status' => $status]);
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $msg = isset($decoded['error']['message']) && is_string($decoded['error']['message'])
                ? (string) $decoded['error']['message']
                : 'Unknown MCP error.';
            return new WP_Error('navai_mcp_rpc_error', $msg, ['http_status' => $status, 'rpc_error' => $decoded['error']]);
        }

        return [
            'http_status' => $status,
            'result' => $decoded['result'] ?? null,
            'raw' => $decoded,
        ];
    }

    private function normalize_server_payload(array $payload, ?array $existing)
    {
        $name = sanitize_text_field((string) ($payload['name'] ?? ($existing['name'] ?? '')));
        if ($name === '') {
            return new WP_Error('navai_invalid_mcp_server_name', 'name is required.', ['status' => 400]);
        }

        $serverKeyRaw = array_key_exists('server_key', $payload) ? (string) $payload['server_key'] : (string) ($existing['server_key'] ?? '');
        $serverKey = $this->sanitize_server_key($serverKeyRaw);
        if ($serverKey === '') {
            $serverKey = $this->sanitize_server_key($this->slugify_name($name));
        }
        if ($serverKey === '') {
            return new WP_Error('navai_invalid_mcp_server_key', 'server_key is required.', ['status' => 400]);
        }

        $baseUrl = array_key_exists('base_url', $payload) ? esc_url_raw((string) $payload['base_url']) : esc_url_raw((string) ($existing['base_url'] ?? ''));
        if ($baseUrl === '') {
            return new WP_Error('navai_invalid_mcp_server_url', 'base_url is required.', ['status' => 400]);
        }

        $transport = sanitize_key((string) ($payload['transport'] ?? ($existing['transport'] ?? 'http_jsonrpc')));
        if (!in_array($transport, ['http_jsonrpc'], true)) {
            $transport = 'http_jsonrpc';
        }

        $authType = sanitize_key((string) ($payload['auth_type'] ?? ($existing['auth_type'] ?? 'none')));
        if (!in_array($authType, ['none', 'bearer', 'basic', 'header'], true)) {
            $authType = 'none';
        }

        $authHeaderName = sanitize_text_field((string) ($payload['auth_header_name'] ?? ($existing['auth_header_name'] ?? '')));
        if ($authType !== 'header') {
            $authHeaderName = '';
        } elseif ($authHeaderName === '') {
            $authHeaderName = 'Authorization';
        }

        $authValueProvided = array_key_exists('auth_value', $payload);
        $authValue = $authValueProvided ? (string) ($payload['auth_value'] ?? '') : (string) ($existing['auth_value_raw'] ?? '');
        if ($authType === 'none') {
            $authValue = '';
        } elseif ($authValue === '' && $existing === null) {
            return new WP_Error('navai_invalid_mcp_auth', 'auth_value is required for the selected auth_type.', ['status' => 400]);
        } elseif ($authValue === '' && $authValueProvided) {
            $authValue = (string) ($existing['auth_value_raw'] ?? '');
        }

        $extraHeaders = array_key_exists('extra_headers', $payload)
            ? $this->normalize_headers_object($payload['extra_headers'])
            : (is_array($existing['extra_headers'] ?? null) ? $existing['extra_headers'] : []);
        if (is_wp_error($extraHeaders)) {
            return $extraHeaders;
        }

        $timeoutConnect = array_key_exists('timeout_connect_seconds', $payload) ? (int) $payload['timeout_connect_seconds'] : (int) ($existing['timeout_connect_seconds'] ?? 10);
        $timeoutRead = array_key_exists('timeout_read_seconds', $payload) ? (int) $payload['timeout_read_seconds'] : (int) ($existing['timeout_read_seconds'] ?? 20);
        $timeoutConnect = max(1, min(120, $timeoutConnect));
        $timeoutRead = max(1, min(120, $timeoutRead));

        $enabled = array_key_exists('enabled', $payload) ? !empty($payload['enabled']) : !empty($existing['enabled']);
        $verifySsl = array_key_exists('verify_ssl', $payload)
            ? !empty($payload['verify_ssl'])
            : (!array_key_exists('verify_ssl', (array) $existing) || !empty($existing['verify_ssl']));

        $tools = is_array($existing['tools'] ?? null) ? $existing['tools'] : [];
        $toolsHash = sanitize_text_field((string) ($existing['tools_hash'] ?? ''));
        $toolCount = isset($existing['tool_count']) && is_numeric($existing['tool_count']) ? (int) $existing['tool_count'] : count($tools);

        return [
            'server_key' => $serverKey,
            'name' => $name,
            'base_url' => $baseUrl,
            'transport' => $transport,
            'enabled' => $enabled,
            'auth_type' => $authType,
            'auth_header_name' => $authHeaderName,
            'auth_value' => $authValue,
            'extra_headers' => $extraHeaders,
            'timeout_connect_seconds' => $timeoutConnect,
            'timeout_read_seconds' => $timeoutRead,
            'verify_ssl' => $verifySsl,
            'tools' => $tools,
            'tools_hash' => $toolsHash,
            'tool_count' => $toolCount,
            'last_health_status' => sanitize_key((string) ($existing['last_health_status'] ?? 'unknown')),
            'last_health_message' => sanitize_textarea_field((string) ($existing['last_health_message'] ?? '')),
            'last_http_status' => isset($existing['last_http_status']) && is_numeric($existing['last_http_status']) ? (int) $existing['last_http_status'] : 0,
            'last_health_checked_at' => (string) ($existing['last_health_checked_at'] ?? ''),
            'last_tools_sync_at' => (string) ($existing['last_tools_sync_at'] ?? ''),
        ];
    }

    private function normalize_policy_payload(array $payload, ?array $existing)
    {
        $serverId = array_key_exists('server_id', $payload) ? (int) $payload['server_id'] : (int) ($existing['server_id'] ?? 0);
        $serverId = max(0, $serverId);
        if ($serverId > 0 && !is_array($this->repository->get_server($serverId))) {
            return new WP_Error('navai_invalid_mcp_policy_server', 'server_id does not exist.', ['status' => 400]);
        }

        $toolName = array_key_exists('tool_name', $payload) ? (string) $payload['tool_name'] : (string) ($existing['tool_name'] ?? '*');
        $toolName = $this->sanitize_policy_tool_name($toolName);
        if ($toolName === '') {
            $toolName = '*';
        }

        $mode = sanitize_key((string) ($payload['mode'] ?? ($existing['mode'] ?? 'allow')));
        if (!in_array($mode, ['allow', 'deny'], true)) {
            return new WP_Error('navai_invalid_mcp_policy_mode', 'mode must be allow or deny.', ['status' => 400]);
        }

        $priority = array_key_exists('priority', $payload) ? (int) $payload['priority'] : (int) ($existing['priority'] ?? 100);
        $priority = max(1, min(9999, $priority));

        $roles = array_key_exists('roles', $payload)
            ? $this->normalize_string_list($payload['roles'], 100, 40, true)
            : (is_array($existing['roles'] ?? null) ? $this->normalize_string_list($existing['roles'], 100, 40, true) : []);

        $agentKeys = array_key_exists('agent_keys', $payload)
            ? $this->normalize_string_list($payload['agent_keys'], 100, 64, true)
            : (is_array($existing['agent_keys'] ?? null) ? $this->normalize_string_list($existing['agent_keys'], 100, 64, true) : []);

        return [
            'server_id' => $serverId,
            'tool_name' => $toolName,
            'mode' => $mode,
            'enabled' => array_key_exists('enabled', $payload) ? !empty($payload['enabled']) : !empty($existing['enabled']),
            'priority' => $priority,
            'roles' => $roles,
            'agent_keys' => $agentKeys,
            'notes' => sanitize_textarea_field((string) ($payload['notes'] ?? ($existing['notes'] ?? ''))),
        ];
    }

    private function normalize_policy_row(array $row, array $serversById): array
    {
        $serverId = isset($row['server_id']) && is_numeric($row['server_id']) ? (int) $row['server_id'] : 0;
        $server = $serverId > 0 && isset($serversById[$serverId]) ? $serversById[$serverId] : null;

        return [
            'id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0,
            'server_id' => $serverId,
            'server_key' => is_array($server) ? (string) ($server['server_key'] ?? '') : '',
            'server_name' => is_array($server) ? (string) ($server['name'] ?? '') : '',
            'tool_name' => $this->sanitize_policy_tool_name((string) ($row['tool_name'] ?? '*')),
            'mode' => sanitize_key((string) ($row['mode'] ?? 'allow')) === 'deny' ? 'deny' : 'allow',
            'enabled' => !empty($row['enabled']),
            'priority' => isset($row['priority']) && is_numeric($row['priority']) ? (int) $row['priority'] : 100,
            'roles' => $this->decode_json_string_list((string) ($row['roles_json'] ?? '[]'), 100, 40, true),
            'agent_keys' => $this->decode_json_string_list((string) ($row['agent_keys_json'] ?? '[]'), 100, 64, true),
            'notes' => sanitize_textarea_field((string) ($row['notes'] ?? '')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function normalize_server_row(array $row, bool $includeSecret): array
    {
        $authType = sanitize_key((string) ($row['auth_type'] ?? 'none'));
        if (!in_array($authType, ['none', 'bearer', 'basic', 'header'], true)) {
            $authType = 'none';
        }
        $authValue = (string) ($row['auth_value'] ?? '');
        $tools = $this->normalize_tools_payload($this->decode_json_list((string) ($row['tools_json'] ?? '[]')));
        $toolCount = isset($row['tool_count']) && is_numeric($row['tool_count']) ? (int) $row['tool_count'] : count($tools);

        $server = [
            'id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0,
            'server_key' => $this->sanitize_server_key((string) ($row['server_key'] ?? '')),
            'name' => sanitize_text_field((string) ($row['name'] ?? '')),
            'base_url' => esc_url_raw((string) ($row['base_url'] ?? '')),
            'transport' => sanitize_key((string) ($row['transport'] ?? 'http_jsonrpc')),
            'enabled' => !empty($row['enabled']),
            'auth_type' => $authType,
            'auth_header_name' => sanitize_text_field((string) ($row['auth_header_name'] ?? '')),
            'auth_configured' => $authType !== 'none' && $authValue !== '',
            'auth_preview' => $this->mask_secret($authValue, $authType),
            'timeout_connect_seconds' => isset($row['timeout_connect_seconds']) && is_numeric($row['timeout_connect_seconds']) ? (int) $row['timeout_connect_seconds'] : 10,
            'timeout_read_seconds' => isset($row['timeout_read_seconds']) && is_numeric($row['timeout_read_seconds']) ? (int) $row['timeout_read_seconds'] : 20,
            'verify_ssl' => !array_key_exists('verify_ssl', $row) || !empty($row['verify_ssl']),
            'extra_headers' => $this->decode_json_object((string) ($row['extra_headers_json'] ?? '{}')),
            'tools' => $tools,
            'tools_hash' => sanitize_text_field((string) ($row['tools_hash'] ?? '')),
            'tool_count' => max(0, $toolCount),
            'last_health_status' => sanitize_key((string) ($row['last_health_status'] ?? 'unknown')),
            'last_health_message' => sanitize_textarea_field((string) ($row['last_health_message'] ?? '')),
            'last_http_status' => isset($row['last_http_status']) && is_numeric($row['last_http_status']) ? (int) $row['last_http_status'] : 0,
            'last_health_checked_at' => (string) ($row['last_health_checked_at'] ?? ''),
            'last_tools_sync_at' => (string) ($row['last_tools_sync_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];

        if ($includeSecret) {
            $server['auth_value_raw'] = $authValue;
        }

        return $server;
    }

    private function normalize_headers_object($value)
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode(trim($value), true);
            if (!is_array($decoded) || $this->is_list_array($decoded)) {
                return new WP_Error('navai_invalid_mcp_headers', 'extra_headers must be a JSON object.', ['status' => 400]);
            }
            $value = $decoded;
        }
        if (!is_array($value) || $this->is_list_array($value)) {
            return new WP_Error('navai_invalid_mcp_headers', 'extra_headers must be a JSON object.', ['status' => 400]);
        }

        $headers = [];
        foreach ($value as $key => $headerValue) {
            if (!is_string($key)) {
                continue;
            }
            $cleanKey = sanitize_text_field($key);
            if ($cleanKey === '') {
                continue;
            }
            $headers[$cleanKey] = sanitize_text_field((string) $headerValue);
        }

        return $headers;
    }

    private function normalize_tools_payload(array $items): array
    {
        $tools = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = sanitize_text_field((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $inputSchema = null;
            if (is_array($item['inputSchema'] ?? null) && !$this->is_list_array($item['inputSchema'])) {
                $inputSchema = $item['inputSchema'];
            } elseif (is_array($item['input_schema'] ?? null) && !$this->is_list_array($item['input_schema'])) {
                $inputSchema = $item['input_schema'];
            }
            $annotations = is_array($item['annotations'] ?? null) && !$this->is_list_array($item['annotations']) ? $item['annotations'] : [];
            $tools[] = [
                'name' => $name,
                'title' => sanitize_text_field((string) ($item['title'] ?? '')),
                'description' => sanitize_text_field((string) ($item['description'] ?? '')),
                'input_schema' => $inputSchema,
                'annotations' => $annotations,
            ];
            if (count($tools) >= 2000) {
                break;
            }
        }
        return $tools;
    }

    private function get_policy_or_fallback(int $id, array $fallbackRow): ?array
    {
        if ($id > 0) {
            foreach ($this->list_tool_policies(['limit' => 5000]) as $item) {
                if ((int) ($item['id'] ?? 0) === $id) {
                    return $item;
                }
            }
        }
        return $this->normalize_policy_row($fallbackRow, []);
    }

    private function decorate_runtime_tool_list(string $serverKey, array $items): array
    {
        $decorated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $toolName = sanitize_text_field((string) ($item['name'] ?? ''));
            if ($toolName === '') {
                continue;
            }
            $item['runtime_function_name'] = $this->build_runtime_function_name($serverKey, $toolName);
            $decorated[] = $item;
        }

        return $decorated;
    }

    private function build_runtime_function_name(string $serverKey, string $toolName): string
    {
        $serverSlug = $this->sanitize_server_key($serverKey);
        if ($serverSlug === '') {
            $serverSlug = 'server';
        }
        $toolSlug = strtolower(trim($toolName));
        $toolSlug = preg_replace('/[^a-z0-9_]+/', '_', $toolSlug);
        if (!is_string($toolSlug)) {
            $toolSlug = 'tool';
        }
        $toolSlug = trim($toolSlug, '_');
        if ($toolSlug === '') {
            $toolSlug = 'tool';
        }
        $hash = substr(md5(strtolower($serverKey) . '|' . strtolower($toolName)), 0, 8);
        return substr('mcp_' . substr($serverSlug, 0, 16) . '_' . substr($toolSlug, 0, 26) . '_' . $hash, 0, 64);
    }

    private function sanitize_server_key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        if (!is_string($value)) {
            return '';
        }
        return substr($value, 0, 64);
    }

    private function sanitize_agent_key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        if (!is_string($value)) {
            return '';
        }
        return substr($value, 0, 64);
    }

    private function sanitize_policy_tool_name(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '*') {
            return '*';
        }
        $value = preg_replace('/[^a-zA-Z0-9_.:\/-]/', '', $value);
        if (!is_string($value) || trim($value) === '') {
            return '*';
        }
        return substr(trim($value), 0, 191);
    }

    private function slugify_name(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        if (!is_string($value)) {
            return '';
        }
        return trim($value, '_');
    }

    private function normalize_string_list($value, int $maxItems = 100, int $maxLen = 64, bool $forceKey = false): array
    {
        if (is_string($value)) {
            $items = preg_split('/[\r\n,]+/', $value) ?: [];
        } elseif (is_array($value)) {
            $items = $value;
        } else {
            return [];
        }

        $clean = [];
        foreach ($items as $item) {
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            if ($forceKey) {
                $text = strtolower($text);
                $text = preg_replace('/[^a-z0-9_.:-]/', '', $text);
                if (!is_string($text)) {
                    continue;
                }
            } else {
                $text = sanitize_text_field($text);
            }
            if ($text === '') {
                continue;
            }
            if (strlen($text) > $maxLen) {
                $text = substr($text, 0, $maxLen);
            }
            $clean[] = $text;
            if (count($clean) >= $maxItems) {
                break;
            }
        }
        return array_values(array_unique($clean));
    }

    private function mask_secret(string $value, string $authType): string
    {
        if ($authType === 'none' || trim($value) === '') {
            return '';
        }
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', max(1, $len));
        }
        return substr($value, 0, 2) . str_repeat('*', max(3, $len - 4)) . substr($value, -2);
    }

    private function decode_json_list(string $json): array
    {
        $decoded = json_decode(trim($json), true);
        return (is_array($decoded) && $this->is_list_array($decoded)) ? $decoded : [];
    }

    private function decode_json_object(string $json): array
    {
        $decoded = json_decode(trim($json), true);
        if (!is_array($decoded) || $this->is_list_array($decoded)) {
            return [];
        }
        $clean = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $cleanKey = sanitize_text_field($key);
            if ($cleanKey === '') {
                continue;
            }
            $clean[$cleanKey] = is_scalar($value) || $value === null ? sanitize_text_field((string) $value) : $value;
        }
        return $clean;
    }

    private function decode_json_string_list(string $json, int $maxItems, int $maxLen, bool $forceKey): array
    {
        $decoded = json_decode(trim($json), true);
        if (!is_array($decoded)) {
            return [];
        }
        return $this->normalize_string_list($decoded, $maxItems, $maxLen, $forceKey);
    }

    private function extract_mcp_content_text($content): string
    {
        if (!is_array($content)) {
            return '';
        }
        $parts = [];
        foreach ($content as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (isset($entry['text']) && is_string($entry['text'])) {
                $parts[] = $entry['text'];
            }
        }
        $text = trim(implode("\n", array_filter(array_map('strval', $parts))));
        return strlen($text) > 2000 ? (substr($text, 0, 2000) . '...') : $text;
    }

    private function is_list_array(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }
        $expected = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }
}
