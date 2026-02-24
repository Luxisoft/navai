<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Session_Repository', false)) {
    return;
}

class Navai_Voice_Session_Repository
{
    private function sessions_table(): string
    {
        return class_exists('Navai_Voice_DB', false)
            ? Navai_Voice_DB::table_sessions()
            : 'wp_navai_sessions';
    }

    private function messages_table(): string
    {
        return class_exists('Navai_Voice_DB', false)
            ? Navai_Voice_DB::table_session_messages()
            : 'wp_navai_session_messages';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_session(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $sql = $wpdb->prepare(
            'SELECT s.*, (SELECT COUNT(1) FROM ' . $this->messages_table() . ' m WHERE m.session_id = s.id) AS message_count FROM '
            . $this->sessions_table() . ' s WHERE s.id = %d LIMIT 1',
            $id
        );
        $row = is_string($sql) ? $wpdb->get_row($sql, ARRAY_A) : null;

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_session_by_key(string $sessionKey): ?array
    {
        $sessionKey = sanitize_text_field($sessionKey);
        if ($sessionKey === '') {
            return null;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            return null;
        }

        $sql = $wpdb->prepare(
            'SELECT s.*, (SELECT COUNT(1) FROM ' . $this->messages_table() . ' m WHERE m.session_id = s.id) AS message_count FROM '
            . $this->sessions_table() . ' s WHERE s.session_key = %s LIMIT 1',
            $sessionKey
        );
        $row = is_string($sql) ? $wpdb->get_row($sql, ARRAY_A) : null;

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function create_session(array $data): ?array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'insert') || !isset($wpdb->insert_id)) {
            return null;
        }

        $now = current_time('mysql');
        $contextJson = wp_json_encode($data['context'] ?? []);
        if (!is_string($contextJson)) {
            $contextJson = '{}';
        }

        $summaryText = isset($data['summary_text']) ? sanitize_textarea_field((string) $data['summary_text']) : '';
        $status = sanitize_key((string) ($data['status'] ?? 'active'));
        if ($status === '') {
            $status = 'active';
        }

        $expiresAt = isset($data['expires_at']) && is_string($data['expires_at']) && trim($data['expires_at']) !== ''
            ? (string) $data['expires_at']
            : null;

        $ok = $wpdb->insert(
            $this->sessions_table(),
            [
                'session_key' => sanitize_text_field((string) ($data['session_key'] ?? '')),
                'wp_user_id' => isset($data['wp_user_id']) && is_numeric($data['wp_user_id']) ? (int) $data['wp_user_id'] : null,
                'visitor_key' => isset($data['visitor_key']) ? sanitize_text_field((string) $data['visitor_key']) : '',
                'context_json' => $contextJson,
                'summary_text' => $summaryText,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
                'expires_at' => $expiresAt,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$ok) {
            return null;
        }

        return $this->get_session((int) $wpdb->insert_id);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update_session(int $id, array $data): ?array
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

        if (array_key_exists('session_key', $data)) {
            $update['session_key'] = sanitize_text_field((string) $data['session_key']);
            $formats[] = '%s';
        }
        if (array_key_exists('wp_user_id', $data)) {
            $update['wp_user_id'] = isset($data['wp_user_id']) && is_numeric($data['wp_user_id'])
                ? (int) $data['wp_user_id']
                : null;
            $formats[] = '%d';
        }
        if (array_key_exists('visitor_key', $data)) {
            $update['visitor_key'] = sanitize_text_field((string) $data['visitor_key']);
            $formats[] = '%s';
        }
        if (array_key_exists('context', $data)) {
            $json = wp_json_encode($data['context']);
            $update['context_json'] = is_string($json) ? $json : '{}';
            $formats[] = '%s';
        }
        if (array_key_exists('summary_text', $data)) {
            $update['summary_text'] = sanitize_textarea_field((string) $data['summary_text']);
            $formats[] = '%s';
        }
        if (array_key_exists('status', $data)) {
            $status = sanitize_key((string) $data['status']);
            $update['status'] = $status !== '' ? $status : 'active';
            $formats[] = '%s';
        }
        if (array_key_exists('expires_at', $data)) {
            $expiresAt = $data['expires_at'];
            $update['expires_at'] = is_string($expiresAt) && trim($expiresAt) !== '' ? $expiresAt : null;
            $formats[] = '%s';
        }

        $update['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $ok = $wpdb->update($this->sessions_table(), $update, ['id' => $id], $formats, ['%d']);
        if ($ok === false) {
            return null;
        }

        return $this->get_session($id);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_sessions(array $filters = []): array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        $status = isset($filters['status']) ? sanitize_key((string) $filters['status']) : '';
        if ($status !== '') {
            $where[] = 's.status = %s';
            $params[] = $status;
        }

        if (array_key_exists('wp_user_id', $filters)) {
            $wpUserId = is_numeric($filters['wp_user_id']) ? (int) $filters['wp_user_id'] : 0;
            if ($wpUserId > 0) {
                $where[] = 's.wp_user_id = %d';
                $params[] = $wpUserId;
            }
        }

        $visitorKey = isset($filters['visitor_key']) ? sanitize_text_field((string) $filters['visitor_key']) : '';
        if ($visitorKey !== '') {
            $where[] = 's.visitor_key = %s';
            $params[] = $visitorKey;
        }

        $sessionKey = isset($filters['session_key']) ? sanitize_text_field((string) $filters['session_key']) : '';
        if ($sessionKey !== '') {
            $where[] = 's.session_key = %s';
            $params[] = $sessionKey;
        }

        $search = isset($filters['search']) ? sanitize_text_field((string) $filters['search']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(s.session_key LIKE %s OR s.visitor_key LIKE %s OR s.summary_text LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $updatedBefore = isset($filters['updated_before']) ? sanitize_text_field((string) $filters['updated_before']) : '';
        if ($updatedBefore !== '') {
            $where[] = 's.updated_at < %s';
            $params[] = $updatedBefore;
        }

        $order = strtolower(trim((string) ($filters['order'] ?? 'desc')));
        $direction = $order === 'asc' ? 'ASC' : 'DESC';

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 50;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $offset = isset($filters['offset']) && is_numeric($filters['offset']) ? (int) $filters['offset'] : 0;
        if ($offset < 0) {
            $offset = 0;
        }

        $sql = 'SELECT s.*, (SELECT COUNT(1) FROM ' . $this->messages_table() . ' m WHERE m.session_id = s.id) AS message_count'
            . ' FROM ' . $this->sessions_table() . ' s'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY s.updated_at ' . $direction . ', s.id ' . $direction
            . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

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

    public function count_messages(int $sessionId): int
    {
        if ($sessionId <= 0) {
            return 0;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_var')) {
            return 0;
        }

        $sql = $wpdb->prepare(
            'SELECT COUNT(1) FROM ' . $this->messages_table() . ' WHERE session_id = %d',
            $sessionId
        );
        $count = is_string($sql) ? $wpdb->get_var($sql) : 0;

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert_message(array $data): int
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'insert') || !isset($wpdb->insert_id)) {
            return 0;
        }

        $contentJson = array_key_exists('content_json', $data) ? wp_json_encode($data['content_json']) : null;
        $metaJson = array_key_exists('meta', $data) ? wp_json_encode($data['meta']) : null;

        $ok = $wpdb->insert(
            $this->messages_table(),
            [
                'session_id' => isset($data['session_id']) && is_numeric($data['session_id']) ? (int) $data['session_id'] : 0,
                'direction' => sanitize_key((string) ($data['direction'] ?? 'system')),
                'message_type' => sanitize_key((string) ($data['message_type'] ?? 'event')),
                'content_text' => array_key_exists('content_text', $data)
                    ? sanitize_textarea_field((string) ($data['content_text'] ?? ''))
                    : null,
                'content_json' => is_string($contentJson) ? $contentJson : null,
                'meta_json' => is_string($metaJson) ? $metaJson : null,
                'created_at' => isset($data['created_at']) && is_string($data['created_at']) && trim($data['created_at']) !== ''
                    ? (string) $data['created_at']
                    : current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$ok) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_messages(int $sessionId, array $filters = []): array
    {
        if ($sessionId <= 0) {
            return [];
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $where = ['session_id = %d'];
        $params = [$sessionId];

        $direction = isset($filters['direction']) ? sanitize_key((string) $filters['direction']) : '';
        if ($direction !== '') {
            $where[] = 'direction = %s';
            $params[] = $direction;
        }

        $messageType = isset($filters['message_type']) ? sanitize_key((string) $filters['message_type']) : '';
        if ($messageType !== '') {
            $where[] = 'message_type = %s';
            $params[] = $messageType;
        }

        $order = strtolower(trim((string) ($filters['order'] ?? 'asc')));
        $sortDir = $order === 'desc' ? 'DESC' : 'ASC';

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 200;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 2000) {
            $limit = 2000;
        }

        $sql = 'SELECT * FROM ' . $this->messages_table()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at ' . $sortDir . ', id ' . $sortDir
            . ' LIMIT ' . (int) $limit;

        $prepared = $wpdb->prepare($sql, ...$params);
        if (is_string($prepared)) {
            $sql = $prepared;
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    public function delete_messages(int $sessionId): int
    {
        if ($sessionId <= 0) {
            return 0;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'delete')) {
            return 0;
        }

        $deleted = $wpdb->delete($this->messages_table(), ['session_id' => $sessionId], ['%d']);
        if ($deleted === false) {
            return 0;
        }

        return (int) $deleted;
    }

    public function delete_messages_up_to(int $sessionId, int $maxMessageId): int
    {
        if ($sessionId <= 0 || $maxMessageId <= 0) {
            return 0;
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'query') || !method_exists($wpdb, 'prepare')) {
            return 0;
        }

        $sql = $wpdb->prepare(
            'DELETE FROM ' . $this->messages_table() . ' WHERE session_id = %d AND id <= %d',
            $sessionId,
            $maxMessageId
        );
        if (!is_string($sql)) {
            return 0;
        }

        $deleted = $wpdb->query($sql);
        if (!is_numeric($deleted)) {
            return 0;
        }

        return (int) $deleted;
    }

    /**
     * @param array<int, int> $sessionIds
     * @return array{sessions_deleted: int, messages_deleted: int}
     */
    public function delete_sessions_by_ids(array $sessionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $sessionIds), static fn(int $id): bool => $id > 0)));
        if (count($ids) === 0) {
            return ['sessions_deleted' => 0, 'messages_deleted' => 0];
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'query')) {
            return ['sessions_deleted' => 0, 'messages_deleted' => 0];
        }

        $idList = implode(',', array_map('intval', $ids));
        $messagesDeleted = $wpdb->query('DELETE FROM ' . $this->messages_table() . ' WHERE session_id IN (' . $idList . ')');
        $sessionsDeleted = $wpdb->query('DELETE FROM ' . $this->sessions_table() . ' WHERE id IN (' . $idList . ')');

        return [
            'sessions_deleted' => is_numeric($sessionsDeleted) ? (int) $sessionsDeleted : 0,
            'messages_deleted' => is_numeric($messagesDeleted) ? (int) $messagesDeleted : 0,
        ];
    }
}

