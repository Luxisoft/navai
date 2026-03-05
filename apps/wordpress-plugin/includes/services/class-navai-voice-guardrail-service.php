<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Guardrail_Service', false)) {
    return;
}

class Navai_Voice_Guardrail_Service
{
    private Navai_Voice_Guardrail_Repository $repository;

    public function __construct(?Navai_Voice_Guardrail_Repository $repository = null)
    {
        $this->repository = $repository ?: new Navai_Voice_Guardrail_Repository();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_rules(array $filters = []): array
    {
        $rows = $this->repository->list($filters);
        return array_map([$this, 'normalize_rule_row'], $rows);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function create_rule(array $payload)
    {
        $normalized = $this->validate_rule_payload($payload);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $created = $this->repository->create($normalized);
        if (!is_array($created)) {
            return new WP_Error('navai_guardrail_create_failed', 'Failed to create guardrail rule.', ['status' => 500]);
        }

        return $this->normalize_rule_row($created);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function update_rule(int $id, array $payload)
    {
        if ($id <= 0) {
            return new WP_Error('navai_invalid_guardrail_id', 'Invalid guardrail id.', ['status' => 400]);
        }

        $normalized = $this->validate_rule_payload($payload);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $updated = $this->repository->update($id, $normalized);
        if (!is_array($updated)) {
            return new WP_Error('navai_guardrail_update_failed', 'Failed to update guardrail rule.', ['status' => 500]);
        }

        return $this->normalize_rule_row($updated);
    }

    public function delete_rule(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * @param array<string, mixed> $subject
     * @return array<string, mixed>
     */
    public function evaluate(string $scope, array $subject): array
    {
        $scope = sanitize_key($scope);
        if (!in_array($scope, ['input', 'tool', 'output'], true)) {
            $scope = 'input';
        }

        $rules = $this->list_rules([
            'scope' => $scope,
            'enabled' => true,
        ]);

        $roles = $this->normalize_roles($subject['roles'] ?? null);
        $functionName = strtolower(trim((string) ($subject['function_name'] ?? '')));
        $functionSource = strtolower(trim((string) ($subject['function_source'] ?? '')));
        $haystack = $this->build_haystack($scope, $subject);

        $matches = [];
        $blocked = false;
        $warnings = [];

        foreach ($rules as $rule) {
            if (!$this->rule_matches_roles($rule, $roles)) {
                continue;
            }
            if (!$this->rule_matches_plugin_scope($rule, $functionName, $functionSource)) {
                continue;
            }
            if (!$this->rule_matches_text($rule, $haystack)) {
                continue;
            }

            $match = [
                'id' => (int) ($rule['id'] ?? 0),
                'name' => (string) ($rule['name'] ?? ''),
                'scope' => (string) ($rule['scope'] ?? $scope),
                'type' => (string) ($rule['type'] ?? 'keyword'),
                'action' => (string) ($rule['action'] ?? 'block'),
                'priority' => (int) ($rule['priority'] ?? 100),
            ];
            $matches[] = $match;

            $action = strtolower(trim((string) ($rule['action'] ?? 'block')));
            if ($action === 'block') {
                $blocked = true;
            } elseif ($action === 'warn') {
                $warnings[] = $match;
            }
        }

        return [
            'scope' => $scope,
            'blocked' => $blocked,
            'matched_count' => count($matches),
            'matches' => $matches,
            'warnings' => $warnings,
            'roles' => $roles,
            'function_name' => $functionName,
            'function_source' => $functionSource,
            'haystack_excerpt' => substr($haystack, 0, 500),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    private function validate_rule_payload(array $payload)
    {
        $scope = sanitize_key((string) ($payload['scope'] ?? 'input'));
        if (!in_array($scope, ['input', 'tool', 'output'], true)) {
            return new WP_Error('navai_invalid_guardrail_scope', 'Invalid scope.', ['status' => 400]);
        }

        $type = sanitize_key((string) ($payload['type'] ?? 'keyword'));
        if (!in_array($type, ['keyword', 'regex'], true)) {
            return new WP_Error('navai_invalid_guardrail_type', 'Invalid type.', ['status' => 400]);
        }

        $action = sanitize_key((string) ($payload['action'] ?? 'block'));
        if (!in_array($action, ['block', 'warn', 'allow'], true)) {
            return new WP_Error('navai_invalid_guardrail_action', 'Invalid action.', ['status' => 400]);
        }

        $pattern = trim((string) ($payload['pattern'] ?? ''));
        if ($pattern === '') {
            return new WP_Error('navai_invalid_guardrail_pattern', 'Pattern is required.', ['status' => 400]);
        }

        if ($type === 'regex' && !$this->is_valid_regex($pattern)) {
            return new WP_Error('navai_invalid_guardrail_regex', 'Invalid regex pattern.', ['status' => 400]);
        }

        $name = sanitize_text_field((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $name = 'Rule';
        }

        $priority = isset($payload['priority']) ? (int) $payload['priority'] : 100;
        if ($priority < 0) {
            $priority = 0;
        }
        if ($priority > 999999) {
            $priority = 999999;
        }

        return [
            'scope' => $scope,
            'type' => $type,
            'name' => $name,
            'enabled' => !empty($payload['enabled']),
            'role_scope' => $this->normalize_scope_tokens((string) ($payload['role_scope'] ?? '')),
            'plugin_scope' => $this->normalize_scope_tokens((string) ($payload['plugin_scope'] ?? '')),
            'pattern' => wp_unslash($pattern),
            'action' => $action,
            'priority' => $priority,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize_rule_row(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'scope' => sanitize_key((string) ($row['scope'] ?? 'input')),
            'type' => sanitize_key((string) ($row['type'] ?? 'keyword')),
            'name' => sanitize_text_field((string) ($row['name'] ?? '')),
            'enabled' => !empty($row['enabled']),
            'role_scope' => (string) ($row['role_scope'] ?? ''),
            'plugin_scope' => (string) ($row['plugin_scope'] ?? ''),
            'pattern' => (string) ($row['pattern'] ?? ''),
            'action' => sanitize_key((string) ($row['action'] ?? 'block')),
            'priority' => isset($row['priority']) ? (int) $row['priority'] : 100,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param mixed $rolesInput
     * @return array<int, string>
     */
    private function normalize_roles($rolesInput): array
    {
        $roles = [];
        if (is_array($rolesInput)) {
            foreach ($rolesInput as $role) {
                $clean = sanitize_key((string) $role);
                if ($clean !== '') {
                    $roles[] = $clean;
                }
            }
        }
        if (count($roles) === 0) {
            $roles[] = is_user_logged_in() ? 'authenticated' : 'guest';
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param array<string, mixed> $subject
     */
    private function build_haystack(string $scope, array $subject): string
    {
        $pieces = [];

        $functionName = trim((string) ($subject['function_name'] ?? ''));
        if ($functionName !== '') {
            $pieces[] = 'function_name:' . $functionName;
        }

        if ($scope === 'input') {
            foreach (['text', 'input_text', 'message', 'prompt', 'query'] as $key) {
                if (isset($subject[$key]) && is_string($subject[$key]) && trim((string) $subject[$key]) !== '') {
                    $pieces[] = (string) $subject[$key];
                }
            }

            if (isset($subject['payload'])) {
                $pieces[] = $this->safe_json($subject['payload']);
            }
        } elseif ($scope === 'tool') {
            $source = trim((string) ($subject['function_source'] ?? ''));
            if ($source !== '') {
                $pieces[] = 'function_source:' . $source;
            }
            if (isset($subject['payload'])) {
                $pieces[] = $this->safe_json($subject['payload']);
            }
        } elseif ($scope === 'output') {
            if (isset($subject['result'])) {
                $pieces[] = $this->safe_json($subject['result']);
            }
            foreach (['text', 'output_text'] as $key) {
                if (isset($subject[$key]) && is_string($subject[$key]) && trim((string) $subject[$key]) !== '') {
                    $pieces[] = (string) $subject[$key];
                }
            }
        }

        return strtolower(implode("\n", array_filter($pieces, static fn($v): bool => is_string($v) && trim($v) !== '')));
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<int, string> $roles
     */
    private function rule_matches_roles(array $rule, array $roles): bool
    {
        $scope = trim((string) ($rule['role_scope'] ?? ''));
        if ($scope === '') {
            return true;
        }

        $allowed = $this->parse_scope_tokens($scope);
        if (count($allowed) === 0) {
            return true;
        }

        return count(array_intersect($allowed, $roles)) > 0;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function rule_matches_plugin_scope(array $rule, string $functionName, string $functionSource): bool
    {
        $scope = trim((string) ($rule['plugin_scope'] ?? ''));
        if ($scope === '') {
            return true;
        }

        $tokens = $this->parse_scope_tokens($scope);
        if (count($tokens) === 0) {
            return true;
        }

        $hay = strtolower($functionName . ' ' . $functionSource);
        foreach ($tokens as $token) {
            if ($token !== '' && str_contains($hay, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function rule_matches_text(array $rule, string $haystack): bool
    {
        if ($haystack === '') {
            return false;
        }

        $type = strtolower(trim((string) ($rule['type'] ?? 'keyword')));
        $pattern = (string) ($rule['pattern'] ?? '');
        if ($pattern === '') {
            return false;
        }

        if ($type === 'regex') {
            return $this->match_regex($pattern, $haystack);
        }

        return str_contains($haystack, strtolower($pattern));
    }

    private function match_regex(string $pattern, string $haystack): bool
    {
        $candidate = $pattern;
        $result = @preg_match($candidate, $haystack);
        if ($result === false) {
            $candidate = '/' . str_replace('/', '\/', $pattern) . '/i';
            $result = @preg_match($candidate, $haystack);
        }

        return $result === 1;
    }

    private function is_valid_regex(string $pattern): bool
    {
        $result = @preg_match($pattern, '');
        if ($result === false) {
            $result = @preg_match('/' . str_replace('/', '\/', $pattern) . '/i', '');
        }

        return $result !== false;
    }

    private function safe_json($value): string
    {
        $encoded = wp_json_encode($value);
        return is_string($encoded) ? $encoded : '';
    }

    private function normalize_scope_tokens(string $value): string
    {
        return implode(',', $this->parse_scope_tokens($value));
    }

    /**
     * @return array<int, string>
     */
    private function parse_scope_tokens(string $value): array
    {
        $parts = preg_split('/[\s,|]+/', strtolower($value)) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $clean = sanitize_key((string) $part);
            if ($clean !== '') {
                $tokens[] = $clean;
            }
        }

        return array_values(array_unique($tokens));
    }
}
