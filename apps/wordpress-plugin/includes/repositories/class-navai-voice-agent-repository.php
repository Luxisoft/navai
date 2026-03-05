<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Agent_Repository', false)) {
    return;
}

class Navai_Voice_Agent_Repository
{
    private function agents_table(): string
    {
        return class_exists('Navai_Voice_DB', false)
            ? Navai_Voice_DB::table_agents()
            : 'wp_navai_agents';
    }

    private function handoffs_table(): string
    {
        return class_exists('Navai_Voice_DB', false)
            ? Navai_Voice_DB::table_agent_handoffs()
            : 'wp_navai_agent_handoffs';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_agent(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $sql = $wpdb->prepare('SELECT * FROM ' . $this->agents_table() . ' WHERE id = %d LIMIT 1', $id);
        $row = is_string($sql) ? $wpdb->get_row($sql, ARRAY_A) : null;

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_agent_by_key(string $agentKey): ?array
    {
        $agentKey = sanitize_text_field($agentKey);
        if ($agentKey === '') {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $sql = $wpdb->prepare('SELECT * FROM ' . $this->agents_table() . ' WHERE agent_key = %s LIMIT 1', $agentKey);
        $row = is_string($sql) ? $wpdb->get_row($sql, ARRAY_A) : null;

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_agents(array $filters = []): array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (array_key_exists('enabled', $filters)) {
            $where[] = 'enabled = %d';
            $params[] = !empty($filters['enabled']) ? 1 : 0;
        }

        $search = isset($filters['search']) ? sanitize_text_field((string) $filters['search']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(agent_key LIKE %s OR name LIKE %s OR description LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 200;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        $sql = 'SELECT * FROM ' . $this->agents_table()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY is_default DESC, priority ASC, name ASC, id ASC'
            . ' LIMIT ' . (int) $limit;

        if (count($params) > 0) {
            $prepared = $wpdb->prepare($sql, ...$params);
            if (is_string($prepared)) {
                $sql = $prepared;
            }
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function create_agent(array $data): ?array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'insert') || !isset($wpdb->insert_id)) {
            return null;
        }

        $now = current_time('mysql');
        $ok = $wpdb->insert(
            $this->agents_table(),
            [
                'agent_key' => sanitize_text_field((string) ($data['agent_key'] ?? '')),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'description' => sanitize_textarea_field((string) ($data['description'] ?? '')),
                'instructions_text' => sanitize_textarea_field((string) ($data['instructions_text'] ?? '')),
                'enabled' => !empty($data['enabled']) ? 1 : 0,
                'is_default' => !empty($data['is_default']) ? 1 : 0,
                'allowed_tools_json' => wp_json_encode($data['allowed_tools'] ?? []),
                'allowed_routes_json' => wp_json_encode($data['allowed_routes'] ?? []),
                'context_json' => wp_json_encode($data['context'] ?? []),
                'priority' => isset($data['priority']) && is_numeric($data['priority']) ? (int) $data['priority'] : 100,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if (!$ok) {
            return null;
        }

        return $this->get_agent((int) $wpdb->insert_id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update_agent(int $id, array $data): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'update')) {
            return null;
        }

        $update = [];
        $formats = [];

        if (array_key_exists('agent_key', $data)) {
            $update['agent_key'] = sanitize_text_field((string) $data['agent_key']);
            $formats[] = '%s';
        }
        if (array_key_exists('name', $data)) {
            $update['name'] = sanitize_text_field((string) $data['name']);
            $formats[] = '%s';
        }
        if (array_key_exists('description', $data)) {
            $update['description'] = sanitize_textarea_field((string) $data['description']);
            $formats[] = '%s';
        }
        if (array_key_exists('instructions_text', $data)) {
            $update['instructions_text'] = sanitize_textarea_field((string) $data['instructions_text']);
            $formats[] = '%s';
        }
        if (array_key_exists('enabled', $data)) {
            $update['enabled'] = !empty($data['enabled']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (array_key_exists('is_default', $data)) {
            $update['is_default'] = !empty($data['is_default']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (array_key_exists('allowed_tools', $data)) {
            $update['allowed_tools_json'] = wp_json_encode($data['allowed_tools'] ?? []);
            $formats[] = '%s';
        }
        if (array_key_exists('allowed_routes', $data)) {
            $update['allowed_routes_json'] = wp_json_encode($data['allowed_routes'] ?? []);
            $formats[] = '%s';
        }
        if (array_key_exists('context', $data)) {
            $update['context_json'] = wp_json_encode($data['context'] ?? []);
            $formats[] = '%s';
        }
        if (array_key_exists('priority', $data)) {
            $update['priority'] = isset($data['priority']) && is_numeric($data['priority']) ? (int) $data['priority'] : 100;
            $formats[] = '%d';
        }

        if (count($update) === 0) {
            return $this->get_agent($id);
        }

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $ok = $wpdb->update($this->agents_table(), $update, ['id' => $id], $formats, ['%d']);
        if ($ok === false) {
            return null;
        }

        return $this->get_agent($id);
    }

    public function clear_default_flag_except(int $agentId): void
    {
        if ($agentId <= 0) {
            return;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'query') || !method_exists($wpdb, 'prepare')) {
            return;
        }

        $sql = $wpdb->prepare(
            'UPDATE ' . $this->agents_table() . ' SET is_default = 0 WHERE id <> %d AND is_default <> 0',
            $agentId
        );
        if (is_string($sql)) {
            $wpdb->query($sql);
        }
    }

    public function delete_agent(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'delete') || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'query')) {
            return false;
        }

        $sql = $wpdb->prepare(
            'DELETE FROM ' . $this->handoffs_table() . ' WHERE source_agent_id = %d OR target_agent_id = %d',
            $id,
            $id
        );
        if (is_string($sql)) {
            $wpdb->query($sql);
        }

        $deleted = $wpdb->delete($this->agents_table(), ['id' => $id], ['%d']);
        return $deleted !== false && (int) $deleted > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_handoff(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $sql = $wpdb->prepare('SELECT * FROM ' . $this->handoffs_table() . ' WHERE id = %d LIMIT 1', $id);
        $row = is_string($sql) ? $wpdb->get_row($sql, ARRAY_A) : null;

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_handoffs(array $filters = []): array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (array_key_exists('enabled', $filters)) {
            $where[] = 'enabled = %d';
            $params[] = !empty($filters['enabled']) ? 1 : 0;
        }

        if (array_key_exists('source_agent_id', $filters) && is_numeric($filters['source_agent_id'])) {
            $sourceAgentId = (int) $filters['source_agent_id'];
            if ($sourceAgentId > 0) {
                $where[] = 'source_agent_id = %d';
                $params[] = $sourceAgentId;
            } elseif ($sourceAgentId === 0) {
                $where[] = 'source_agent_id IS NULL';
            }
        }

        if (array_key_exists('target_agent_id', $filters) && is_numeric($filters['target_agent_id'])) {
            $targetAgentId = (int) $filters['target_agent_id'];
            if ($targetAgentId > 0) {
                $where[] = 'target_agent_id = %d';
                $params[] = $targetAgentId;
            }
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 500;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 2000) {
            $limit = 2000;
        }

        $sql = 'SELECT * FROM ' . $this->handoffs_table()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY priority ASC, id ASC'
            . ' LIMIT ' . (int) $limit;

        if (count($params) > 0) {
            $prepared = $wpdb->prepare($sql, ...$params);
            if (is_string($prepared)) {
                $sql = $prepared;
            }
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function create_handoff(array $data): ?array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'insert') || !isset($wpdb->insert_id)) {
            return null;
        }

        $now = current_time('mysql');
        $ok = $wpdb->insert(
            $this->handoffs_table(),
            [
                'source_agent_id' => isset($data['source_agent_id']) && is_numeric($data['source_agent_id']) && (int) $data['source_agent_id'] > 0
                    ? (int) $data['source_agent_id']
                    : null,
                'target_agent_id' => isset($data['target_agent_id']) && is_numeric($data['target_agent_id']) ? (int) $data['target_agent_id'] : 0,
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'enabled' => !empty($data['enabled']) ? 1 : 0,
                'priority' => isset($data['priority']) && is_numeric($data['priority']) ? (int) $data['priority'] : 100,
                'match_json' => wp_json_encode($data['match'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s']
        );

        if (!$ok) {
            return null;
        }

        return $this->get_handoff((int) $wpdb->insert_id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update_handoff(int $id, array $data): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'update')) {
            return null;
        }

        $update = [];
        $formats = [];

        if (array_key_exists('source_agent_id', $data)) {
            $update['source_agent_id'] = isset($data['source_agent_id']) && is_numeric($data['source_agent_id']) && (int) $data['source_agent_id'] > 0
                ? (int) $data['source_agent_id']
                : null;
            $formats[] = '%d';
        }
        if (array_key_exists('target_agent_id', $data)) {
            $update['target_agent_id'] = isset($data['target_agent_id']) && is_numeric($data['target_agent_id'])
                ? (int) $data['target_agent_id']
                : 0;
            $formats[] = '%d';
        }
        if (array_key_exists('name', $data)) {
            $update['name'] = sanitize_text_field((string) $data['name']);
            $formats[] = '%s';
        }
        if (array_key_exists('enabled', $data)) {
            $update['enabled'] = !empty($data['enabled']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (array_key_exists('priority', $data)) {
            $update['priority'] = isset($data['priority']) && is_numeric($data['priority']) ? (int) $data['priority'] : 100;
            $formats[] = '%d';
        }
        if (array_key_exists('match', $data)) {
            $update['match_json'] = wp_json_encode($data['match'] ?? []);
            $formats[] = '%s';
        }

        if (count($update) === 0) {
            return $this->get_handoff($id);
        }

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $ok = $wpdb->update($this->handoffs_table(), $update, ['id' => $id], $formats, ['%d']);
        if ($ok === false) {
            return null;
        }

        return $this->get_handoff($id);
    }

    public function delete_handoff(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'delete')) {
            return false;
        }

        $deleted = $wpdb->delete($this->handoffs_table(), ['id' => $id], ['%d']);
        return $deleted !== false && (int) $deleted > 0;
    }
}
