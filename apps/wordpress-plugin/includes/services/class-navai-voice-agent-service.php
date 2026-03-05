<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Agent_Service', false)) {
    return;
}

class Navai_Voice_Agent_Service
{
    private Navai_Voice_Agent_Repository $repository;

    public function __construct(?Navai_Voice_Agent_Repository $repository = null)
    {
        $this->repository = $repository ?: new Navai_Voice_Agent_Repository();
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{enabled: bool}
     */
    public function get_runtime_config(array $settings = []): array
    {
        return [
            'enabled' => !array_key_exists('enable_agents', $settings) || !empty($settings['enable_agents']),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_agents(array $filters = []): array
    {
        $rows = $this->repository->list_agents($filters);
        return array_map([$this, 'normalize_agent_row'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_agent(int $id): ?array
    {
        $row = $this->repository->get_agent($id);
        return is_array($row) ? $this->normalize_agent_row($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_agent_by_key(string $agentKey): ?array
    {
        $row = $this->repository->get_agent_by_key($this->sanitize_agent_key($agentKey));
        return is_array($row) ? $this->normalize_agent_row($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function create_agent(array $payload)
    {
        $normalized = $this->normalize_agent_payload($payload, null);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $existing = $this->repository->get_agent_by_key((string) $normalized['agent_key']);
        if (is_array($existing)) {
            return new WP_Error('navai_agent_key_exists', 'agent_key already exists.', ['status' => 409]);
        }

        $created = $this->repository->create_agent($normalized);
        if (!is_array($created)) {
            return new WP_Error('navai_agent_create_failed', 'Failed to create agent.', ['status' => 500]);
        }

        $agent = $this->normalize_agent_row($created);
        if (!empty($agent['is_default'])) {
            $this->repository->clear_default_flag_except((int) $agent['id']);
            $refetched = $this->repository->get_agent((int) $agent['id']);
            if (is_array($refetched)) {
                $agent = $this->normalize_agent_row($refetched);
            }
        }

        return $agent;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function update_agent(int $id, array $payload)
    {
        $existingRow = $this->repository->get_agent($id);
        if (!is_array($existingRow)) {
            return new WP_Error('navai_agent_not_found', 'Agent not found.', ['status' => 404]);
        }

        $existing = $this->normalize_agent_row($existingRow);
        $normalized = $this->normalize_agent_payload($payload, $existing);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $agentKey = (string) $normalized['agent_key'];
        $other = $this->repository->get_agent_by_key($agentKey);
        if (is_array($other) && (int) ($other['id'] ?? 0) !== $id) {
            return new WP_Error('navai_agent_key_exists', 'agent_key already exists.', ['status' => 409]);
        }

        $updated = $this->repository->update_agent($id, $normalized);
        if (!is_array($updated)) {
            return new WP_Error('navai_agent_update_failed', 'Failed to update agent.', ['status' => 500]);
        }

        $agent = $this->normalize_agent_row($updated);
        if (!empty($agent['is_default'])) {
            $this->repository->clear_default_flag_except((int) $agent['id']);
            $refetched = $this->repository->get_agent((int) $agent['id']);
            if (is_array($refetched)) {
                $agent = $this->normalize_agent_row($refetched);
            }
        }

        return $agent;
    }

    public function delete_agent(int $id): bool
    {
        return $this->repository->delete_agent($id);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_handoff_rules(array $filters = []): array
    {
        $rows = $this->repository->list_handoffs($filters);
        if (count($rows) === 0) {
            return [];
        }

        $agentsById = $this->build_agents_by_id_lookup($this->list_agents(['limit' => 1000]));

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->normalize_handoff_row($row, $agentsById);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_handoff_rule(int $id): ?array
    {
        $row = $this->repository->get_handoff($id);
        if (!is_array($row)) {
            return null;
        }

        $agentsById = $this->build_agents_by_id_lookup($this->list_agents(['limit' => 1000]));
        return $this->normalize_handoff_row($row, $agentsById);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function create_handoff_rule(array $payload)
    {
        $normalized = $this->normalize_handoff_payload($payload, null);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $created = $this->repository->create_handoff($normalized);
        if (!is_array($created)) {
            return new WP_Error('navai_handoff_create_failed', 'Failed to create handoff rule.', ['status' => 500]);
        }

        $item = $this->get_handoff_rule((int) ($created['id'] ?? 0));
        return is_array($item) ? $item : $this->normalize_handoff_row($created, []);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function update_handoff_rule(int $id, array $payload)
    {
        $existing = $this->repository->get_handoff($id);
        if (!is_array($existing)) {
            return new WP_Error('navai_handoff_not_found', 'Handoff rule not found.', ['status' => 404]);
        }

        $existingNormalized = $this->normalize_handoff_row($existing, []);
        $normalized = $this->normalize_handoff_payload($payload, $existingNormalized);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $updated = $this->repository->update_handoff($id, $normalized);
        if (!is_array($updated)) {
            return new WP_Error('navai_handoff_update_failed', 'Failed to update handoff rule.', ['status' => 500]);
        }

        $item = $this->get_handoff_rule((int) ($updated['id'] ?? 0));
        return is_array($item) ? $item : $this->normalize_handoff_row($updated, []);
    }

    public function delete_handoff_rule(int $id): bool
    {
        return $this->repository->delete_handoff($id);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function resolve_tool_runtime(array $input = []): array
    {
        $settings = is_array($input['settings'] ?? null) ? $input['settings'] : [];
        $config = $this->get_runtime_config($settings);

        $functionName = sanitize_key((string) ($input['function_name'] ?? ''));
        $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
        $requestContext = is_array($input['request_context'] ?? null) ? $input['request_context'] : [];
        $sessionContext = is_array($input['session_context'] ?? null) ? $input['session_context'] : [];
        $roles = $this->normalize_keyword_list($input['roles'] ?? [], 20, 40, true);

        if (!$config['enabled']) {
            return [
                'enabled' => false,
                'agent' => $this->build_synthetic_default_agent(),
                'source_agent' => null,
                'handoff' => null,
                'tool_allowed' => true,
                'tool_allowed_reason' => '',
            ];
        }

        $agents = $this->list_agents([
            'enabled' => true,
            'limit' => 1000,
        ]);
        if (count($agents) === 0) {
            return [
                'enabled' => true,
                'agent' => $this->build_synthetic_default_agent(),
                'source_agent' => null,
                'handoff' => null,
                'tool_allowed' => true,
                'tool_allowed_reason' => '',
            ];
        }

        $agentsById = $this->build_agents_by_id_lookup($agents);
        $agentsByKey = [];
        foreach ($agents as $agent) {
            $agentKey = sanitize_text_field((string) ($agent['agent_key'] ?? ''));
            if ($agentKey !== '') {
                $agentsByKey[$agentKey] = $agent;
            }
        }

        $requestedAgentKey = $this->sanitize_agent_key((string) ($input['requested_agent_key'] ?? ''));
        $sessionAgentKey = $this->sanitize_agent_key((string) ($sessionContext['active_agent_key'] ?? ''));

        $currentAgent = null;
        if ($requestedAgentKey !== '' && isset($agentsByKey[$requestedAgentKey])) {
            $currentAgent = $agentsByKey[$requestedAgentKey];
        } elseif ($sessionAgentKey !== '' && isset($agentsByKey[$sessionAgentKey])) {
            $currentAgent = $agentsByKey[$sessionAgentKey];
        } else {
            $currentAgent = $this->find_default_agent($agents);
        }
        if (!is_array($currentAgent)) {
            $currentAgent = $this->build_synthetic_default_agent();
        }

        $effectiveAgent = $currentAgent;
        $handoff = null;

        $rules = $this->list_handoff_rules([
            'enabled' => true,
            'limit' => 2000,
        ]);

        $payloadJson = wp_json_encode($payload);
        if (!is_string($payloadJson)) {
            $payloadJson = '';
        }

        $requestIntent = sanitize_text_field((string) ($requestContext['intent'] ?? ''));
        $requestText = sanitize_textarea_field((string) ($requestContext['text'] ?? ''));
        $combinedContext = $sessionContext;
        foreach ($requestContext as $key => $value) {
            if (is_string($key) && trim($key) !== '') {
                $combinedContext[$key] = $value;
            }
        }

        foreach ($rules as $rule) {
            if (!$this->handoff_rule_source_matches($rule, $currentAgent)) {
                continue;
            }

            $targetAgentId = isset($rule['target_agent_id']) && is_numeric($rule['target_agent_id'])
                ? (int) $rule['target_agent_id']
                : 0;
            if ($targetAgentId <= 0 || !isset($agentsById[$targetAgentId])) {
                continue;
            }

            $matchResult = $this->handoff_rule_matches(
                $rule,
                [
                    'function_name' => $functionName,
                    'payload_json' => $payloadJson,
                    'intent' => $requestIntent,
                    'text' => $requestText,
                    'roles' => $roles,
                    'context' => $combinedContext,
                ]
            );
            if (!$matchResult['matched']) {
                continue;
            }

            $targetAgent = $agentsById[$targetAgentId];
            if ((int) ($targetAgent['id'] ?? 0) === (int) ($currentAgent['id'] ?? 0)) {
                continue;
            }

            $effectiveAgent = $targetAgent;
            $handoff = [
                'rule_id' => isset($rule['id']) ? (int) $rule['id'] : 0,
                'rule_name' => sanitize_text_field((string) ($rule['name'] ?? '')),
                'source_agent_id' => isset($currentAgent['id']) ? (int) $currentAgent['id'] : null,
                'source_agent_key' => sanitize_text_field((string) ($currentAgent['agent_key'] ?? '')),
                'source_agent_name' => sanitize_text_field((string) ($currentAgent['name'] ?? '')),
                'target_agent_id' => isset($targetAgent['id']) ? (int) $targetAgent['id'] : null,
                'target_agent_key' => sanitize_text_field((string) ($targetAgent['agent_key'] ?? '')),
                'target_agent_name' => sanitize_text_field((string) ($targetAgent['name'] ?? '')),
                'matched' => $matchResult['details'],
            ];
            break;
        }

        $toolAllowed = true;
        $toolAllowedReason = '';
        $allowedTools = is_array($effectiveAgent['allowed_tools'] ?? null) ? $effectiveAgent['allowed_tools'] : [];
        if ($functionName !== '' && count($allowedTools) > 0 && !in_array($functionName, $allowedTools, true)) {
            $toolAllowed = false;
            $toolAllowedReason = 'Function is not allowed for the active agent.';
        }

        return [
            'enabled' => true,
            'agent' => $effectiveAgent,
            'source_agent' => $currentAgent,
            'handoff' => $handoff,
            'tool_allowed' => $toolAllowed,
            'tool_allowed_reason' => $toolAllowedReason,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $agents
     * @return array<int, array<string, mixed>>
     */
    private function build_agents_by_id_lookup(array $agents): array
    {
        $items = [];
        foreach ($agents as $agent) {
            $id = isset($agent['id']) && is_numeric($agent['id']) ? (int) $agent['id'] : 0;
            if ($id > 0) {
                $items[$id] = $agent;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>|WP_Error
     */
    private function normalize_agent_payload(array $payload, ?array $existing)
    {
        $name = sanitize_text_field((string) ($payload['name'] ?? ($existing['name'] ?? '')));
        if ($name === '') {
            return new WP_Error('navai_invalid_agent_name', 'name is required.', ['status' => 400]);
        }

        $agentKeyRaw = array_key_exists('agent_key', $payload)
            ? (string) $payload['agent_key']
            : (string) ($existing['agent_key'] ?? '');
        $agentKey = $this->sanitize_agent_key($agentKeyRaw);
        if ($agentKey === '') {
            $agentKey = $this->sanitize_agent_key($this->slugify_agent_name($name));
        }
        if ($agentKey === '') {
            return new WP_Error('navai_invalid_agent_key', 'agent_key is required.', ['status' => 400]);
        }

        $priority = array_key_exists('priority', $payload)
            ? (int) $payload['priority']
            : (int) ($existing['priority'] ?? 100);
        if ($priority < 1) {
            $priority = 1;
        }
        if ($priority > 9999) {
            $priority = 9999;
        }

        $enabled = array_key_exists('enabled', $payload)
            ? !empty($payload['enabled'])
            : !empty($existing['enabled']);
        $isDefault = array_key_exists('is_default', $payload)
            ? !empty($payload['is_default'])
            : !empty($existing['is_default']);

        $allowedTools = array_key_exists('allowed_tools', $payload)
            ? $this->normalize_keyword_list($payload['allowed_tools'], 300, 128, true)
            : (is_array($existing['allowed_tools'] ?? null) ? $existing['allowed_tools'] : []);
        $allowedRoutes = array_key_exists('allowed_routes', $payload)
            ? $this->normalize_route_key_list($payload['allowed_routes'])
            : (is_array($existing['allowed_routes'] ?? null) ? $existing['allowed_routes'] : []);

        $context = array_key_exists('context', $payload)
            ? $this->normalize_context_object($payload['context'])
            : (is_array($existing['context'] ?? null) ? $existing['context'] : []);
        if (is_wp_error($context)) {
            return $context;
        }

        return [
            'agent_key' => $agentKey,
            'name' => $name,
            'description' => sanitize_textarea_field((string) ($payload['description'] ?? ($existing['description'] ?? ''))),
            'instructions_text' => sanitize_textarea_field((string) ($payload['instructions_text'] ?? ($existing['instructions_text'] ?? ''))),
            'enabled' => $enabled,
            'is_default' => $isDefault,
            'allowed_tools' => $allowedTools,
            'allowed_routes' => $allowedRoutes,
            'context' => $context,
            'priority' => $priority,
        ];
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>|WP_Error
     */
    private function normalize_handoff_payload(array $payload, ?array $existing)
    {
        $targetAgentId = array_key_exists('target_agent_id', $payload)
            ? (int) $payload['target_agent_id']
            : (int) ($existing['target_agent_id'] ?? 0);
        if ($targetAgentId <= 0) {
            return new WP_Error('navai_invalid_handoff_target', 'target_agent_id is required.', ['status' => 400]);
        }

        $targetAgent = $this->repository->get_agent($targetAgentId);
        if (!is_array($targetAgent)) {
            return new WP_Error('navai_invalid_handoff_target', 'Target agent not found.', ['status' => 400]);
        }

        $sourceAgentId = array_key_exists('source_agent_id', $payload)
            ? (int) $payload['source_agent_id']
            : (int) ($existing['source_agent_id'] ?? 0);
        if ($sourceAgentId > 0) {
            $sourceAgent = $this->repository->get_agent($sourceAgentId);
            if (!is_array($sourceAgent)) {
                return new WP_Error('navai_invalid_handoff_source', 'Source agent not found.', ['status' => 400]);
            }
        } else {
            $sourceAgentId = 0;
        }

        $priority = array_key_exists('priority', $payload)
            ? (int) $payload['priority']
            : (int) ($existing['priority'] ?? 100);
        if ($priority < 1) {
            $priority = 1;
        }
        if ($priority > 9999) {
            $priority = 9999;
        }

        $match = $this->normalize_handoff_match($payload, $existing);
        if (is_wp_error($match)) {
            return $match;
        }

        return [
            'source_agent_id' => $sourceAgentId > 0 ? $sourceAgentId : null,
            'target_agent_id' => $targetAgentId,
            'name' => sanitize_text_field((string) ($payload['name'] ?? ($existing['name'] ?? ''))),
            'enabled' => array_key_exists('enabled', $payload) ? !empty($payload['enabled']) : !empty($existing['enabled']),
            'priority' => $priority,
            'match' => $match,
        ];
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>|WP_Error
     */
    private function normalize_handoff_match(array $payload, ?array $existing)
    {
        $existingMatch = is_array($existing['match'] ?? null) ? $existing['match'] : [];
        $rawMatch = array_key_exists('match', $payload) ? $payload['match'] : null;

        $intentKeywords = array_key_exists('intent_keywords', $payload)
            ? $this->normalize_keyword_list($payload['intent_keywords'], 200, 120, false)
            : (is_array($existingMatch['intent_keywords'] ?? null) ? $this->normalize_keyword_list($existingMatch['intent_keywords'], 200, 120, false) : []);
        $functionNames = array_key_exists('function_names', $payload)
            ? $this->normalize_keyword_list($payload['function_names'], 200, 120, true)
            : (is_array($existingMatch['function_names'] ?? null) ? $this->normalize_keyword_list($existingMatch['function_names'], 200, 120, true) : []);
        $payloadKeywords = array_key_exists('payload_keywords', $payload)
            ? $this->normalize_keyword_list($payload['payload_keywords'], 200, 120, false)
            : (is_array($existingMatch['payload_keywords'] ?? null) ? $this->normalize_keyword_list($existingMatch['payload_keywords'], 200, 120, false) : []);
        $roles = array_key_exists('roles', $payload)
            ? $this->normalize_keyword_list($payload['roles'], 50, 40, true)
            : (is_array($existingMatch['roles'] ?? null) ? $this->normalize_keyword_list($existingMatch['roles'], 50, 40, true) : []);

        $contextEqualsInput = array_key_exists('context_equals', $payload)
            ? $payload['context_equals']
            : ($existingMatch['context_equals'] ?? []);
        $contextEquals = $this->normalize_context_object($contextEqualsInput);
        if (is_wp_error($contextEquals)) {
            return new WP_Error('navai_invalid_handoff_context', 'context_equals must be a JSON object.', ['status' => 400]);
        }

        if (is_array($rawMatch)) {
            if (!array_key_exists('intent_keywords', $payload)) {
                $intentKeywords = $this->normalize_keyword_list($rawMatch['intent_keywords'] ?? [], 200, 120, false);
            }
            if (!array_key_exists('function_names', $payload)) {
                $functionNames = $this->normalize_keyword_list($rawMatch['function_names'] ?? [], 200, 120, true);
            }
            if (!array_key_exists('payload_keywords', $payload)) {
                $payloadKeywords = $this->normalize_keyword_list($rawMatch['payload_keywords'] ?? [], 200, 120, false);
            }
            if (!array_key_exists('roles', $payload)) {
                $roles = $this->normalize_keyword_list($rawMatch['roles'] ?? [], 50, 40, true);
            }
            if (!array_key_exists('context_equals', $payload)) {
                $contextFromMatch = $this->normalize_context_object($rawMatch['context_equals'] ?? []);
                if (is_wp_error($contextFromMatch)) {
                    return new WP_Error('navai_invalid_handoff_context', 'context_equals must be a JSON object.', ['status' => 400]);
                }
                $contextEquals = $contextFromMatch;
            }
        }

        if (count($intentKeywords) === 0 && count($functionNames) === 0 && count($payloadKeywords) === 0 && count($roles) === 0 && count($contextEquals) === 0) {
            return new WP_Error(
                'navai_invalid_handoff_match',
                'Define at least one condition (intent_keywords, function_names, payload_keywords, roles or context_equals).',
                ['status' => 400]
            );
        }

        return [
            'intent_keywords' => $intentKeywords,
            'function_names' => $functionNames,
            'payload_keywords' => $payloadKeywords,
            'roles' => $roles,
            'context_equals' => $contextEquals,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize_agent_row(array $row): array
    {
        $allowedTools = $this->decode_json_array_of_strings($row['allowed_tools_json'] ?? '[]', 300, 128, true);
        $allowedRoutes = $this->decode_json_array_of_strings($row['allowed_routes_json'] ?? '[]', 500, 191, false, true);
        $context = $this->decode_json_object((string) ($row['context_json'] ?? '{}'));

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'agent_key' => $this->sanitize_agent_key((string) ($row['agent_key'] ?? '')),
            'name' => sanitize_text_field((string) ($row['name'] ?? '')),
            'description' => sanitize_textarea_field((string) ($row['description'] ?? '')),
            'instructions_text' => sanitize_textarea_field((string) ($row['instructions_text'] ?? '')),
            'enabled' => !empty($row['enabled']),
            'is_default' => !empty($row['is_default']),
            'allowed_tools' => $allowedTools,
            'allowed_routes' => $allowedRoutes,
            'context' => $context,
            'priority' => isset($row['priority']) && is_numeric($row['priority']) ? (int) $row['priority'] : 100,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, array<string, mixed>> $agentsById
     * @return array<string, mixed>
     */
    private function normalize_handoff_row(array $row, array $agentsById): array
    {
        $sourceAgentId = isset($row['source_agent_id']) && is_numeric($row['source_agent_id']) ? (int) $row['source_agent_id'] : 0;
        $targetAgentId = isset($row['target_agent_id']) && is_numeric($row['target_agent_id']) ? (int) $row['target_agent_id'] : 0;
        $match = $this->decode_json_object((string) ($row['match_json'] ?? '{}'));

        $sourceAgent = $sourceAgentId > 0 && isset($agentsById[$sourceAgentId]) ? $agentsById[$sourceAgentId] : null;
        $targetAgent = $targetAgentId > 0 && isset($agentsById[$targetAgentId]) ? $agentsById[$targetAgentId] : null;

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'source_agent_id' => $sourceAgentId > 0 ? $sourceAgentId : null,
            'source_agent_key' => is_array($sourceAgent) ? (string) ($sourceAgent['agent_key'] ?? '') : '',
            'source_agent_name' => is_array($sourceAgent) ? (string) ($sourceAgent['name'] ?? '') : '',
            'target_agent_id' => $targetAgentId > 0 ? $targetAgentId : null,
            'target_agent_key' => is_array($targetAgent) ? (string) ($targetAgent['agent_key'] ?? '') : '',
            'target_agent_name' => is_array($targetAgent) ? (string) ($targetAgent['name'] ?? '') : '',
            'name' => sanitize_text_field((string) ($row['name'] ?? '')),
            'enabled' => !empty($row['enabled']),
            'priority' => isset($row['priority']) && is_numeric($row['priority']) ? (int) $row['priority'] : 100,
            'match' => [
                'intent_keywords' => $this->normalize_keyword_list($match['intent_keywords'] ?? [], 200, 120, false),
                'function_names' => $this->normalize_keyword_list($match['function_names'] ?? [], 200, 120, true),
                'payload_keywords' => $this->normalize_keyword_list($match['payload_keywords'] ?? [], 200, 120, false),
                'roles' => $this->normalize_keyword_list($match['roles'] ?? [], 50, 40, true),
                'context_equals' => is_array($match['context_equals'] ?? null) ? $this->sanitize_context_array($match['context_equals']) : [],
            ],
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function sanitize_agent_key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]/', '', $value);
        if (!is_string($value)) {
            return '';
        }
        if (strlen($value) > 64) {
            $value = substr($value, 0, 64);
        }

        return $value;
    }

    private function slugify_agent_name(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        if (!is_string($value)) {
            return '';
        }

        return trim($value, '_');
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalize_route_key_list($value): array
    {
        return $this->normalize_keyword_list($value, 500, 191, false, true);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalize_keyword_list($value, int $maxItems = 200, int $maxLen = 120, bool $forceKey = false, bool $allowColon = false): array
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
                $pattern = $allowColon ? '/[^a-z0-9:_-]/' : '/[^a-z0-9_-]/';
                $text = preg_replace($pattern, '', $text);
                if (!is_string($text)) {
                    continue;
                }
            } else {
                $text = sanitize_text_field($text);
                if ($allowColon) {
                    $text = strtolower($text);
                    $text = preg_replace('/[^a-z0-9:_-]/', '', $text);
                    if (!is_string($text)) {
                        continue;
                    }
                }
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

    /**
     * @param mixed $value
     * @return array<string, mixed>|WP_Error
     */
    private function normalize_context_object($value)
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $raw = trim($value);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || $this->is_list_array($decoded)) {
                return new WP_Error('navai_invalid_json_object', 'Invalid JSON object.', ['status' => 400]);
            }

            return $this->sanitize_context_array($decoded);
        }

        if (!is_array($value) || $this->is_list_array($value)) {
            return new WP_Error('navai_invalid_json_object', 'Invalid JSON object.', ['status' => 400]);
        }

        return $this->sanitize_context_array($value);
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function sanitize_context_array(array $value, int $depth = 0): array
    {
        if ($depth > 4) {
            return [];
        }

        $clean = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            $cleanKey = sanitize_key($key);
            if ($cleanKey === '') {
                $cleanKey = sanitize_text_field($key);
            }
            if ($cleanKey === '') {
                continue;
            }

            if (is_string($item)) {
                $clean[$cleanKey] = sanitize_text_field($item);
                continue;
            }
            if (is_bool($item) || is_int($item) || is_float($item) || $item === null) {
                $clean[$cleanKey] = $item;
                continue;
            }
            if (!is_array($item)) {
                continue;
            }

            if ($this->is_list_array($item)) {
                $list = [];
                foreach ($item as $listItem) {
                    if (is_string($listItem)) {
                        $list[] = sanitize_text_field($listItem);
                    } elseif (is_bool($listItem) || is_int($listItem) || is_float($listItem) || $listItem === null) {
                        $list[] = $listItem;
                    }
                }
                $clean[$cleanKey] = array_values($list);
                continue;
            }

            $clean[$cleanKey] = $this->sanitize_context_array($item, $depth + 1);
        }

        return $clean;
    }

    /**
     * @param mixed $json
     * @return array<int, string>
     */
    private function decode_json_array_of_strings($json, int $maxItems, int $maxLen, bool $forceKey = false, bool $allowColon = false): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return $this->normalize_keyword_list(is_array($decoded) ? $decoded : [], $maxItems, $maxLen, $forceKey, $allowColon);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode_json_object(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || $this->is_list_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<int, array<string, mixed>> $agents
     * @return array<string, mixed>|null
     */
    private function find_default_agent(array $agents): ?array
    {
        foreach ($agents as $agent) {
            if (!empty($agent['is_default'])) {
                return $agent;
            }
        }

        return $agents[0] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function build_synthetic_default_agent(): array
    {
        return [
            'id' => 0,
            'agent_key' => 'default',
            'name' => 'Default',
            'description' => '',
            'instructions_text' => '',
            'enabled' => true,
            'is_default' => true,
            'allowed_tools' => [],
            'allowed_routes' => [],
            'context' => [],
            'priority' => 100,
            'created_at' => '',
            'updated_at' => '',
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $currentAgent
     */
    private function handoff_rule_source_matches(array $rule, array $currentAgent): bool
    {
        $ruleSourceId = isset($rule['source_agent_id']) && is_numeric($rule['source_agent_id'])
            ? (int) $rule['source_agent_id']
            : 0;
        if ($ruleSourceId <= 0) {
            return true;
        }

        return $ruleSourceId === (int) ($currentAgent['id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $runtime
     * @return array{matched: bool, details: array<string, mixed>}
     */
    private function handoff_rule_matches(array $rule, array $runtime): array
    {
        $match = is_array($rule['match'] ?? null) ? $rule['match'] : [];
        $intentKeywords = $this->normalize_keyword_list($match['intent_keywords'] ?? [], 200, 120, false);
        $functionNames = $this->normalize_keyword_list($match['function_names'] ?? [], 200, 120, true);
        $payloadKeywords = $this->normalize_keyword_list($match['payload_keywords'] ?? [], 200, 120, false);
        $roles = $this->normalize_keyword_list($match['roles'] ?? [], 50, 40, true);
        $contextEquals = is_array($match['context_equals'] ?? null) ? $this->sanitize_context_array($match['context_equals']) : [];

        $functionName = sanitize_key((string) ($runtime['function_name'] ?? ''));
        $payloadJson = strtolower((string) ($runtime['payload_json'] ?? ''));
        $intent = strtolower(sanitize_text_field((string) ($runtime['intent'] ?? '')));
        $text = strtolower(sanitize_textarea_field((string) ($runtime['text'] ?? '')));
        $requestRoles = $this->normalize_keyword_list($runtime['roles'] ?? [], 50, 40, true);
        $context = is_array($runtime['context'] ?? null) ? $runtime['context'] : [];

        $details = [];

        if (count($functionNames) > 0) {
            if ($functionName === '' || !in_array($functionName, $functionNames, true)) {
                return ['matched' => false, 'details' => []];
            }
            $details['function_name'] = $functionName;
        }

        if (count($roles) > 0) {
            $matchedRoles = array_values(array_intersect($roles, $requestRoles));
            if (count($matchedRoles) === 0) {
                return ['matched' => false, 'details' => []];
            }
            $details['roles'] = $matchedRoles;
        }

        if (count($intentKeywords) > 0) {
            $matchedIntentKeyword = '';
            foreach ($intentKeywords as $keyword) {
                $needle = strtolower($keyword);
                if ($needle === '') {
                    continue;
                }
                if (($intent !== '' && str_contains($intent, $needle)) || ($text !== '' && str_contains($text, $needle))) {
                    $matchedIntentKeyword = $keyword;
                    break;
                }
            }
            if ($matchedIntentKeyword === '') {
                return ['matched' => false, 'details' => []];
            }
            $details['intent_keyword'] = $matchedIntentKeyword;
        }

        if (count($payloadKeywords) > 0) {
            $matchedPayloadKeyword = '';
            foreach ($payloadKeywords as $keyword) {
                $needle = strtolower($keyword);
                if ($needle === '') {
                    continue;
                }
                if ($payloadJson !== '' && str_contains($payloadJson, $needle)) {
                    $matchedPayloadKeyword = $keyword;
                    break;
                }
            }
            if ($matchedPayloadKeyword === '') {
                return ['matched' => false, 'details' => []];
            }
            $details['payload_keyword'] = $matchedPayloadKeyword;
        }

        if (count($contextEquals) > 0) {
            $matchedContext = [];
            foreach ($contextEquals as $ctxKey => $ctxValue) {
                if (!array_key_exists($ctxKey, $context)) {
                    return ['matched' => false, 'details' => []];
                }
                if (!$this->context_values_match($ctxValue, $context[$ctxKey])) {
                    return ['matched' => false, 'details' => []];
                }
                $matchedContext[$ctxKey] = $ctxValue;
            }
            if (count($matchedContext) > 0) {
                $details['context_equals'] = $matchedContext;
            }
        }

        return ['matched' => true, 'details' => $details];
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    private function context_values_match($expected, $actual): bool
    {
        if (is_array($expected) || is_array($actual)) {
            return wp_json_encode($expected) === wp_json_encode($actual);
        }
        if (is_bool($expected) || is_bool($actual)) {
            return (bool) $expected === (bool) $actual;
        }
        if ((is_int($expected) || is_float($expected)) && (is_int($actual) || is_float($actual))) {
            return (string) $expected === (string) $actual;
        }

        return sanitize_text_field((string) $expected) === sanitize_text_field((string) $actual);
    }

    /**
     * @param array<string, mixed> $value
     */
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
