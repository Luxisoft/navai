<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_MCP_Repository', false)) {
    return;
}

class Navai_Voice_MCP_Repository
{
    private function servers_table(): string
    {
        return class_exists('Navai_Voice_DB', false)
            ? Navai_Voice_DB::table_mcp_servers()
            : 'wp_navai_mcp_servers';
    }

    private function policies_table(): string
    {
        return class_exists('Navai_Voice_DB', false)
            ? Navai_Voice_DB::table_mcp_tool_policies()
            : 'wp_navai_mcp_tool_policies';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_server(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $sql = $wpdb->prepare('SELECT * FROM ' . $this->servers_table() . ' WHERE id = %d LIMIT 1', $id);
        $row = is_string($sql) ? $wpdb->get_row($sql, ARRAY_A) : null;

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_server_by_key(string $serverKey): ?array
    {
        $serverKey = sanitize_text_field($serverKey);
        if ($serverKey === '') {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $sql = $wpdb->prepare('SELECT * FROM ' . $this->servers_table() . ' WHERE server_key = %s LIMIT 1', $serverKey);
        $row = is_string($sql) ? $wpdb->get_row($sql, ARRAY_A) : null;

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_servers(array $filters = []): array
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
            $where[] = '(server_key LIKE %s OR name LIKE %s OR base_url LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 200;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 2000) {
            $limit = 2000;
        }

        $sql = 'SELECT * FROM ' . $this->servers_table()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY enabled DESC, name ASC, id ASC'
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
    public function create_server(array $data): ?array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'insert') || !isset($wpdb->insert_id)) {
            return null;
        }

        $now = current_time('mysql');
        $ok = $wpdb->insert(
            $this->servers_table(),
            [
                'server_key' => sanitize_text_field((string) ($data['server_key'] ?? '')),
                'name' => sanitize_text_field((string) ($data['name'] ?? '')),
                'base_url' => esc_url_raw((string) ($data['base_url'] ?? '')),
                'transport' => sanitize_key((string) ($data['transport'] ?? 'http_jsonrpc')),
                'enabled' => !empty($data['enabled']) ? 1 : 0,
                'auth_type' => sanitize_key((string) ($data['auth_type'] ?? 'none')),
                'auth_header_name' => sanitize_text_field((string) ($data['auth_header_name'] ?? '')),
                'auth_value' => (string) ($data['auth_value'] ?? ''),
                'extra_headers_json' => wp_json_encode($data['extra_headers'] ?? []),
                'timeout_connect_seconds' => isset($data['timeout_connect_seconds']) && is_numeric($data['timeout_connect_seconds'])
                    ? (int) $data['timeout_connect_seconds']
                    : 10,
                'timeout_read_seconds' => isset($data['timeout_read_seconds']) && is_numeric($data['timeout_read_seconds'])
                    ? (int) $data['timeout_read_seconds']
                    : 20,
                'verify_ssl' => !empty($data['verify_ssl']) ? 1 : 0,
                'tools_json' => wp_json_encode($data['tools'] ?? []),
                'tools_hash' => sanitize_text_field((string) ($data['tools_hash'] ?? '')),
                'tool_count' => isset($data['tool_count']) && is_numeric($data['tool_count']) ? (int) $data['tool_count'] : 0,
                'last_health_status' => sanitize_key((string) ($data['last_health_status'] ?? 'unknown')),
                'last_health_message' => sanitize_textarea_field((string) ($data['last_health_message'] ?? '')),
                'last_http_status' => isset($data['last_http_status']) && is_numeric($data['last_http_status']) ? (int) $data['last_http_status'] : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s']
        );

        if (!$ok) {
            return null;
        }

        return $this->get_server((int) $wpdb->insert_id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update_server(int $id, array $data): ?array
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

        if (array_key_exists('server_key', $data)) {
            $update['server_key'] = sanitize_text_field((string) $data['server_key']);
            $formats[] = '%s';
        }
        if (array_key_exists('name', $data)) {
            $update['name'] = sanitize_text_field((string) $data['name']);
            $formats[] = '%s';
        }
        if (array_key_exists('base_url', $data)) {
            $update['base_url'] = esc_url_raw((string) $data['base_url']);
            $formats[] = '%s';
        }
        if (array_key_exists('transport', $data)) {
            $update['transport'] = sanitize_key((string) $data['transport']);
            $formats[] = '%s';
        }
        if (array_key_exists('enabled', $data)) {
            $update['enabled'] = !empty($data['enabled']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (array_key_exists('auth_type', $data)) {
            $update['auth_type'] = sanitize_key((string) $data['auth_type']);
            $formats[] = '%s';
        }
        if (array_key_exists('auth_header_name', $data)) {
            $update['auth_header_name'] = sanitize_text_field((string) $data['auth_header_name']);
            $formats[] = '%s';
        }
        if (array_key_exists('auth_value', $data)) {
            $update['auth_value'] = (string) $data['auth_value'];
            $formats[] = '%s';
        }
        if (array_key_exists('extra_headers', $data)) {
            $update['extra_headers_json'] = wp_json_encode($data['extra_headers'] ?? []);
            $formats[] = '%s';
        }
        if (array_key_exists('timeout_connect_seconds', $data)) {
            $update['timeout_connect_seconds'] = isset($data['timeout_connect_seconds']) && is_numeric($data['timeout_connect_seconds'])
                ? (int) $data['timeout_connect_seconds']
                : 10;
            $formats[] = '%d';
        }
        if (array_key_exists('timeout_read_seconds', $data)) {
            $update['timeout_read_seconds'] = isset($data['timeout_read_seconds']) && is_numeric($data['timeout_read_seconds'])
                ? (int) $data['timeout_read_seconds']
                : 20;
            $formats[] = '%d';
        }
        if (array_key_exists('verify_ssl', $data)) {
            $update['verify_ssl'] = !empty($data['verify_ssl']) ? 1 : 0;
            $formats[] = '%d';
        }
        if (array_key_exists('tools', $data)) {
            $update['tools_json'] = wp_json_encode($data['tools'] ?? []);
            $formats[] = '%s';
        }
        if (array_key_exists('tools_hash', $data)) {
            $update['tools_hash'] = sanitize_text_field((string) $data['tools_hash']);
            $formats[] = '%s';
        }
        if (array_key_exists('tool_count', $data)) {
            $update['tool_count'] = isset($data['tool_count']) && is_numeric($data['tool_count']) ? (int) $data['tool_count'] : 0;
            $formats[] = '%d';
        }
        if (array_key_exists('last_health_status', $data)) {
            $update['last_health_status'] = sanitize_key((string) $data['last_health_status']);
            $formats[] = '%s';
        }
        if (array_key_exists('last_health_message', $data)) {
            $update['last_health_message'] = sanitize_textarea_field((string) $data['last_health_message']);
            $formats[] = '%s';
        }
        if (array_key_exists('last_http_status', $data)) {
            $update['last_http_status'] = isset($data['last_http_status']) && is_numeric($data['last_http_status']) ? (int) $data['last_http_status'] : 0;
            $formats[] = '%d';
        }
        if (array_key_exists('last_health_checked_at', $data)) {
            $value = isset($data['last_health_checked_at']) ? (string) $data['last_health_checked_at'] : '';
            $update['last_health_checked_at'] = $value !== '' ? $value : null;
            $formats[] = '%s';
        }
        if (array_key_exists('last_tools_sync_at', $data)) {
            $value = isset($data['last_tools_sync_at']) ? (string) $data['last_tools_sync_at'] : '';
            $update['last_tools_sync_at'] = $value !== '' ? $value : null;
            $formats[] = '%s';
        }

        if (count($update) === 0) {
            return $this->get_server($id);
        }

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $ok = $wpdb->update($this->servers_table(), $update, ['id' => $id], $formats, ['%d']);
        if ($ok === false) {
            return null;
        }

        return $this->get_server($id);
    }

    public function delete_server(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'delete')) {
            return false;
        }

        $wpdb->delete($this->policies_table(), ['server_id' => $id], ['%d']);
        $deleted = $wpdb->delete($this->servers_table(), ['id' => $id], ['%d']);

        return $deleted !== false && (int) $deleted > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_policy(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $sql = $wpdb->prepare('SELECT * FROM ' . $this->policies_table() . ' WHERE id = %d LIMIT 1', $id);
        $row = is_string($sql) ? $wpdb->get_row($sql, ARRAY_A) : null;

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_policies(array $filters = []): array
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

        if (array_key_exists('server_id', $filters) && is_numeric($filters['server_id'])) {
            $where[] = 'server_id = %d';
            $params[] = (int) $filters['server_id'];
        }

        if (array_key_exists('mode', $filters)) {
            $mode = sanitize_key((string) $filters['mode']);
            if ($mode !== '') {
                $where[] = 'mode = %s';
                $params[] = $mode;
            }
        }

        $search = isset($filters['search']) ? sanitize_text_field((string) $filters['search']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(tool_name LIKE %s OR notes LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 1000;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        $sql = 'SELECT * FROM ' . $this->policies_table()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY enabled DESC, priority ASC, id ASC'
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
    public function create_policy(array $data): ?array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'insert') || !isset($wpdb->insert_id)) {
            return null;
        }

        $now = current_time('mysql');
        $ok = $wpdb->insert(
            $this->policies_table(),
            [
                'server_id' => isset($data['server_id']) && is_numeric($data['server_id']) ? (int) $data['server_id'] : 0,
                'tool_name' => sanitize_text_field((string) ($data['tool_name'] ?? '*')),
                'mode' => sanitize_key((string) ($data['mode'] ?? 'allow')),
                'enabled' => !empty($data['enabled']) ? 1 : 0,
                'priority' => isset($data['priority']) && is_numeric($data['priority']) ? (int) $data['priority'] : 100,
                'roles_json' => wp_json_encode($data['roles'] ?? []),
                'agent_keys_json' => wp_json_encode($data['agent_keys'] ?? []),
                'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$ok) {
            return null;
        }

        return $this->get_policy((int) $wpdb->insert_id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update_policy(int $id, array $data): ?array
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

        if (array_key_exists('server_id', $data)) {
            $update['server_id'] = isset($data['server_id']) && is_numeric($data['server_id']) ? (int) $data['server_id'] : 0;
            $formats[] = '%d';
        }
        if (array_key_exists('tool_name', $data)) {
            $update['tool_name'] = sanitize_text_field((string) $data['tool_name']);
            $formats[] = '%s';
        }
        if (array_key_exists('mode', $data)) {
            $update['mode'] = sanitize_key((string) $data['mode']);
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
        if (array_key_exists('roles', $data)) {
            $update['roles_json'] = wp_json_encode($data['roles'] ?? []);
            $formats[] = '%s';
        }
        if (array_key_exists('agent_keys', $data)) {
            $update['agent_keys_json'] = wp_json_encode($data['agent_keys'] ?? []);
            $formats[] = '%s';
        }
        if (array_key_exists('notes', $data)) {
            $update['notes'] = sanitize_textarea_field((string) $data['notes']);
            $formats[] = '%s';
        }

        if (count($update) === 0) {
            return $this->get_policy($id);
        }

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $ok = $wpdb->update($this->policies_table(), $update, ['id' => $id], $formats, ['%d']);
        if ($ok === false) {
            return null;
        }

        return $this->get_policy($id);
    }

    public function delete_policy(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'delete')) {
            return false;
        }

        $deleted = $wpdb->delete($this->policies_table(), ['id' => $id], ['%d']);
        return $deleted !== false && (int) $deleted > 0;
    }
}
