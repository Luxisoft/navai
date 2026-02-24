<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Approval_Repository', false)) {
    return;
}

class Navai_Voice_Approval_Repository
{
    private function table(): string
    {
        return class_exists('Navai_Voice_DB', false)
            ? Navai_Voice_DB::table_approvals()
            : 'wp_navai_approvals';
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

        $ok = $wpdb->insert(
            $this->table(),
            [
                'status' => sanitize_key((string) ($data['status'] ?? 'pending')),
                'requested_by_user_id' => isset($data['requested_by_user_id']) && is_numeric($data['requested_by_user_id'])
                    ? (int) $data['requested_by_user_id']
                    : null,
                'session_id' => isset($data['session_id']) && is_numeric($data['session_id'])
                    ? (int) $data['session_id']
                    : null,
                'function_id' => isset($data['function_id']) && is_numeric($data['function_id'])
                    ? (int) $data['function_id']
                    : null,
                'function_key' => sanitize_text_field((string) ($data['function_key'] ?? '')),
                'function_source' => sanitize_text_field((string) ($data['function_source'] ?? '')),
                'payload_json' => wp_json_encode($data['payload'] ?? []),
                'reason' => sanitize_textarea_field((string) ($data['reason'] ?? '')),
                'approved_by_user_id' => null,
                'decision_notes' => '',
                'result_json' => null,
                'error_message' => null,
                'trace_id' => sanitize_text_field((string) ($data['trace_id'] ?? '')),
                'created_at' => current_time('mysql'),
                'resolved_at' => null,
            ],
            ['%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$ok) {
            return null;
        }

        return $this->get((int) $wpdb->insert_id);
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

        $status = isset($filters['status']) ? sanitize_key((string) $filters['status']) : '';
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $functionKey = isset($filters['function_key']) ? sanitize_text_field((string) $filters['function_key']) : '';
        if ($functionKey !== '') {
            $where[] = 'function_key = %s';
            $params[] = $functionKey;
        }

        $traceId = isset($filters['trace_id']) ? sanitize_text_field((string) $filters['trace_id']) : '';
        if ($traceId !== '') {
            $where[] = 'trace_id = %s';
            $params[] = $traceId;
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 50;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit;
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
    public function resolve(int $id, string $status, array $data = []): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'update')) {
            return null;
        }

        $update = [
            'status' => sanitize_key($status),
            'approved_by_user_id' => isset($data['approved_by_user_id']) && is_numeric($data['approved_by_user_id'])
                ? (int) $data['approved_by_user_id']
                : null,
            'decision_notes' => sanitize_textarea_field((string) ($data['decision_notes'] ?? '')),
            'result_json' => array_key_exists('result', $data) ? wp_json_encode($data['result']) : null,
            'error_message' => array_key_exists('error_message', $data)
                ? sanitize_textarea_field((string) ($data['error_message'] ?? ''))
                : null,
            'resolved_at' => current_time('mysql'),
        ];

        $ok = $wpdb->update(
            $this->table(),
            $update,
            ['id' => $id],
            ['%s', '%d', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($ok === false) {
            return null;
        }

        return $this->get($id);
    }
}

