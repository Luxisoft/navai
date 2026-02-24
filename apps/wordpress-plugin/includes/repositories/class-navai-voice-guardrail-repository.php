<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Guardrail_Repository', false)) {
    return;
}

class Navai_Voice_Guardrail_Repository
{
    private function table(): string
    {
        return class_exists('Navai_Voice_DB', false)
            ? Navai_Voice_DB::table_guardrails()
            : 'wp_navai_guardrails';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        $scope = isset($filters['scope']) ? sanitize_key((string) $filters['scope']) : '';
        if ($scope !== '') {
            $where[] = 'scope = %s';
            $params[] = $scope;
        }

        if (array_key_exists('enabled', $filters)) {
            $enabled = !empty($filters['enabled']) ? 1 : 0;
            $where[] = 'enabled = %d';
            $params[] = $enabled;
        }

        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY priority ASC, id DESC';
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
     * @return array<string, mixed>|null
     */
    public function get(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $sql = $wpdb->prepare('SELECT * FROM ' . $this->table() . ' WHERE id = %d LIMIT 1', $id);
        $row = is_string($sql) ? $wpdb->get_row($sql, ARRAY_A) : null;
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function create(array $data): ?array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'insert') || !isset($wpdb->insert_id)) {
            return null;
        }

        $now = current_time('mysql');
        $inserted = $wpdb->insert(
            $this->table(),
            [
                'scope' => (string) ($data['scope'] ?? 'input'),
                'type' => (string) ($data['type'] ?? 'keyword'),
                'name' => (string) ($data['name'] ?? ''),
                'enabled' => !empty($data['enabled']) ? 1 : 0,
                'role_scope' => (string) ($data['role_scope'] ?? ''),
                'plugin_scope' => (string) ($data['plugin_scope'] ?? ''),
                'pattern' => (string) ($data['pattern'] ?? ''),
                'action' => (string) ($data['action'] ?? 'block'),
                'priority' => (int) ($data['priority'] ?? 100),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        if (!$inserted) {
            return null;
        }

        return $this->get((int) $wpdb->insert_id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'update')) {
            return null;
        }

        $updated = $wpdb->update(
            $this->table(),
            [
                'scope' => (string) ($data['scope'] ?? 'input'),
                'type' => (string) ($data['type'] ?? 'keyword'),
                'name' => (string) ($data['name'] ?? ''),
                'enabled' => !empty($data['enabled']) ? 1 : 0,
                'role_scope' => (string) ($data['role_scope'] ?? ''),
                'plugin_scope' => (string) ($data['plugin_scope'] ?? ''),
                'pattern' => (string) ($data['pattern'] ?? ''),
                'action' => (string) ($data['action'] ?? 'block'),
                'priority' => (int) ($data['priority'] ?? 100),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return null;
        }

        return $this->get($id);
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'delete')) {
            return false;
        }

        $deleted = $wpdb->delete($this->table(), ['id' => $id], ['%d']);
        return (int) $deleted > 0;
    }
}

