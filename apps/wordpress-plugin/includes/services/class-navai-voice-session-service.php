<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Session_Service', false)) {
    return;
}

class Navai_Voice_Session_Service
{
    private Navai_Voice_Session_Repository $repository;

    public function __construct(?Navai_Voice_Session_Repository $repository = null)
    {
        $this->repository = $repository ?: new Navai_Voice_Session_Repository();
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{
     *   enabled: bool,
     *   session_ttl_minutes: int,
     *   session_retention_days: int,
     *   session_compaction_threshold: int,
     *   session_compaction_keep_recent: int
     * }
     */
    public function get_runtime_config(array $settings = []): array
    {
        $enabled = !array_key_exists('enable_session_memory', $settings) || !empty($settings['enable_session_memory']);

        $ttlMinutes = isset($settings['session_ttl_minutes']) && is_numeric($settings['session_ttl_minutes'])
            ? (int) $settings['session_ttl_minutes']
            : 1440;
        if ($ttlMinutes < 5) {
            $ttlMinutes = 5;
        }
        if ($ttlMinutes > 43200) {
            $ttlMinutes = 43200;
        }

        $retentionDays = isset($settings['session_retention_days']) && is_numeric($settings['session_retention_days'])
            ? (int) $settings['session_retention_days']
            : 30;
        if ($retentionDays < 1) {
            $retentionDays = 1;
        }
        if ($retentionDays > 3650) {
            $retentionDays = 3650;
        }

        $compactThreshold = isset($settings['session_compaction_threshold']) && is_numeric($settings['session_compaction_threshold'])
            ? (int) $settings['session_compaction_threshold']
            : 120;
        if ($compactThreshold < 20) {
            $compactThreshold = 20;
        }
        if ($compactThreshold > 2000) {
            $compactThreshold = 2000;
        }

        $compactKeepRecent = isset($settings['session_compaction_keep_recent']) && is_numeric($settings['session_compaction_keep_recent'])
            ? (int) $settings['session_compaction_keep_recent']
            : 80;
        if ($compactKeepRecent < 10) {
            $compactKeepRecent = 10;
        }
        if ($compactKeepRecent >= $compactThreshold) {
            $compactKeepRecent = max(10, $compactThreshold - 10);
        }

        return [
            'enabled' => $enabled,
            'session_ttl_minutes' => $ttlMinutes,
            'session_retention_days' => $retentionDays,
            'session_compaction_threshold' => $compactThreshold,
            'session_compaction_keep_recent' => $compactKeepRecent,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function resolve_session(array $input = [], array $settings = []): array
    {
        $config = $this->get_runtime_config($settings);
        $requestedKey = isset($input['session_key']) ? $this->sanitize_session_key((string) $input['session_key']) : '';

        if ($requestedKey === '') {
            $requestedKey = $this->generate_session_key();
        }

        if (!$config['enabled']) {
            return [
                'enabled' => false,
                'persisted' => false,
                'session_key' => $requestedKey,
                'session_id' => null,
                'session' => null,
                'created' => false,
            ];
        }

        $userId = is_user_logged_in() ? (int) get_current_user_id() : null;
        $visitorKey = $userId ? '' : $requestedKey;

        $context = is_array($input['context'] ?? null) ? $input['context'] : [];
        if ($userId) {
            $context['wp_user_id'] = $userId;
        } elseif ($visitorKey !== '') {
            $context['visitor_key'] = $visitorKey;
        }

        $nowTs = function_exists('current_time') ? (int) current_time('timestamp') : time();
        $expiresAt = date('Y-m-d H:i:s', $nowTs + ((int) $config['session_ttl_minutes'] * 60));
        $row = $this->repository->get_session_by_key($requestedKey);
        $created = false;

        if (!is_array($row)) {
            $row = $this->repository->create_session([
                'session_key' => $requestedKey,
                'wp_user_id' => $userId,
                'visitor_key' => $visitorKey,
                'context' => $context,
                'summary_text' => '',
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);
            $created = true;
        } else {
            $mergedContext = $this->merge_context_from_row($row, $context);
            $updatePayload = [
                'context' => $mergedContext,
                'status' => 'active',
                'expires_at' => $expiresAt,
            ];
            if ($userId && (int) ($row['wp_user_id'] ?? 0) <= 0) {
                $updatePayload['wp_user_id'] = $userId;
            }
            if (!$userId && $visitorKey !== '' && trim((string) ($row['visitor_key'] ?? '')) === '') {
                $updatePayload['visitor_key'] = $visitorKey;
            }

            $updated = $this->repository->update_session((int) $row['id'], $updatePayload);
            if (is_array($updated)) {
                $row = $updated;
            }
        }

        if (!is_array($row)) {
            return [
                'enabled' => true,
                'persisted' => false,
                'session_key' => $requestedKey,
                'session_id' => null,
                'session' => null,
                'created' => false,
            ];
        }

        $this->maybe_cleanup($config);

        return [
            'enabled' => true,
            'persisted' => true,
            'session_key' => $requestedKey,
            'session_id' => isset($row['id']) ? (int) $row['id'] : null,
            'session' => $this->normalize_session_row($row),
            'created' => $created,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    public function record_message_by_session_key(string $sessionKey, array $message, array $settings = []): array
    {
        $resolved = $this->resolve_session([
            'session_key' => $sessionKey,
            'context' => is_array($message['session_context'] ?? null) ? $message['session_context'] : [],
        ], $settings);

        if (empty($resolved['persisted']) || empty($resolved['session_id'])) {
            return [
                'ok' => false,
                'persisted' => false,
                'session' => $resolved['session'] ?? null,
                'session_key' => $resolved['session_key'] ?? $sessionKey,
                'message_id' => 0,
            ];
        }

        $sessionId = (int) $resolved['session_id'];
        $direction = sanitize_key((string) ($message['direction'] ?? 'system'));
        if (!in_array($direction, ['user', 'assistant', 'system', 'tool'], true)) {
            $direction = 'system';
        }

        $messageType = sanitize_key((string) ($message['message_type'] ?? 'event'));
        if (!in_array($messageType, ['text', 'audio', 'event', 'tool_call', 'tool_result'], true)) {
            $messageType = 'event';
        }

        $insertId = $this->repository->insert_message([
            'session_id' => $sessionId,
            'direction' => $direction,
            'message_type' => $messageType,
            'content_text' => array_key_exists('content_text', $message) ? (string) ($message['content_text'] ?? '') : null,
            'content_json' => $message['content_json'] ?? null,
            'meta' => $message['meta'] ?? null,
        ]);

        if ($insertId > 0) {
            $this->repository->update_session($sessionId, ['status' => 'active']);
            $this->maybe_compact_session($sessionId, $settings);
        }

        return [
            'ok' => $insertId > 0,
            'persisted' => $insertId > 0,
            'session' => $resolved['session'] ?? null,
            'session_key' => (string) ($resolved['session_key'] ?? $sessionKey),
            'session_id' => $sessionId,
            'message_id' => $insertId,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function record_messages_batch_by_session_key(string $sessionKey, array $items, array $settings = []): array
    {
        $resolved = $this->resolve_session(['session_key' => $sessionKey], $settings);
        if (empty($resolved['persisted']) || empty($resolved['session_id'])) {
            return [
                'ok' => false,
                'persisted' => false,
                'saved' => 0,
                'failed' => count($items),
                'session' => $resolved['session'] ?? null,
                'session_key' => $resolved['session_key'] ?? $sessionKey,
            ];
        }

        $saved = 0;
        $failed = 0;
        $sessionId = (int) $resolved['session_id'];
        foreach ($items as $item) {
            if (!is_array($item)) {
                $failed += 1;
                continue;
            }

            $direction = sanitize_key((string) ($item['direction'] ?? 'system'));
            if (!in_array($direction, ['user', 'assistant', 'system', 'tool'], true)) {
                $direction = 'system';
            }
            $messageType = sanitize_key((string) ($item['message_type'] ?? 'event'));
            if (!in_array($messageType, ['text', 'audio', 'event', 'tool_call', 'tool_result'], true)) {
                $messageType = 'event';
            }

            $insertId = $this->repository->insert_message([
                'session_id' => $sessionId,
                'direction' => $direction,
                'message_type' => $messageType,
                'content_text' => array_key_exists('content_text', $item) ? (string) ($item['content_text'] ?? '') : null,
                'content_json' => $item['content_json'] ?? null,
                'meta' => $item['meta'] ?? null,
            ]);
            if ($insertId > 0) {
                $saved += 1;
            } else {
                $failed += 1;
            }
        }

        if ($saved > 0) {
            $this->repository->update_session($sessionId, ['status' => 'active']);
            $this->maybe_compact_session($sessionId, $settings);
        }

        return [
            'ok' => $saved > 0 && $failed === 0,
            'persisted' => $saved > 0,
            'saved' => $saved,
            'failed' => $failed,
            'session_key' => (string) ($resolved['session_key'] ?? $sessionKey),
            'session' => $resolved['session'] ?? null,
            'session_id' => $sessionId,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_sessions(array $filters = []): array
    {
        $rows = $this->repository->list_sessions($filters);
        return array_map([$this, 'normalize_session_row'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_session(int $id): ?array
    {
        $row = $this->repository->get_session($id);
        return is_array($row) ? $this->normalize_session_row($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_session_messages(int $sessionId, int $limit = 500): array
    {
        $rows = $this->repository->list_messages($sessionId, [
            'limit' => $limit,
            'order' => 'asc',
        ]);

        return array_map([$this, 'normalize_message_row'], $rows);
    }

    /**
     * @param array<string, mixed> $contextDelta
     * @return array<string, mixed>|null
     */
    public function update_session_context(int $sessionId, array $contextDelta): ?array
    {
        if ($sessionId <= 0 || count($contextDelta) === 0) {
            return null;
        }

        $row = $this->repository->get_session($sessionId);
        if (!is_array($row)) {
            return null;
        }

        $updated = $this->repository->update_session(
            $sessionId,
            [
                'context' => $this->merge_context_from_row($row, $contextDelta),
                'status' => (string) ($row['status'] ?? 'active'),
            ]
        );

        return is_array($updated) ? $this->normalize_session_row($updated) : null;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function clear_session(int $sessionId)
    {
        if ($sessionId <= 0) {
            return new WP_Error('navai_invalid_session', 'Invalid session id.', ['status' => 400]);
        }

        $row = $this->repository->get_session($sessionId);
        if (!is_array($row)) {
            return new WP_Error('navai_session_not_found', 'Session not found.', ['status' => 404]);
        }

        $deletedMessages = $this->repository->delete_messages($sessionId);
        $updated = $this->repository->update_session($sessionId, [
            'summary_text' => '',
            'status' => 'cleared',
            'context' => $this->merge_context_from_row($row, [
                'cleared_at' => current_time('mysql'),
            ]),
        ]);

        return [
            'session' => is_array($updated) ? $this->normalize_session_row($updated) : $this->normalize_session_row($row),
            'deleted_messages' => $deletedMessages,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, int>
     */
    public function cleanup_retention(array $settings = [], int $limit = 200): array
    {
        $config = $this->get_runtime_config($settings);
        $nowTs = function_exists('current_time') ? (int) current_time('timestamp') : time();
        $cutoff = date('Y-m-d H:i:s', $nowTs - ((int) $config['session_retention_days'] * DAY_IN_SECONDS));
        $limit = max(1, min(1000, $limit));

        $rows = $this->repository->list_sessions([
            'updated_before' => $cutoff,
            'limit' => $limit,
            'order' => 'asc',
        ]);

        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id']) || !is_numeric($row['id'])) {
                continue;
            }
            $ids[] = (int) $row['id'];
        }

        $result = $this->repository->delete_sessions_by_ids($ids);
        return [
            'sessions_deleted' => (int) ($result['sessions_deleted'] ?? 0),
            'messages_deleted' => (int) ($result['messages_deleted'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize_session_row(array $row): array
    {
        $context = [];
        if (isset($row['context_json']) && is_string($row['context_json']) && trim($row['context_json']) !== '') {
            $decoded = json_decode((string) $row['context_json'], true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        $status = sanitize_key((string) ($row['status'] ?? 'active'));
        if ($status === '') {
            $status = 'active';
        }

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'session_key' => sanitize_text_field((string) ($row['session_key'] ?? '')),
            'wp_user_id' => isset($row['wp_user_id']) && is_numeric($row['wp_user_id']) ? (int) $row['wp_user_id'] : null,
            'visitor_key' => sanitize_text_field((string) ($row['visitor_key'] ?? '')),
            'context' => $context,
            'summary_text' => sanitize_textarea_field((string) ($row['summary_text'] ?? '')),
            'status' => $status,
            'message_count' => isset($row['message_count']) && is_numeric($row['message_count']) ? (int) $row['message_count'] : 0,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
            'is_expired' => $this->is_expired_datetime((string) ($row['expires_at'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize_message_row(array $row): array
    {
        $contentJson = null;
        if (isset($row['content_json']) && is_string($row['content_json']) && trim($row['content_json']) !== '') {
            $decoded = json_decode((string) $row['content_json'], true);
            $contentJson = $decoded !== null ? $decoded : ['raw' => (string) $row['content_json']];
        }

        $meta = [];
        if (isset($row['meta_json']) && is_string($row['meta_json']) && trim($row['meta_json']) !== '') {
            $decoded = json_decode((string) $row['meta_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            } else {
                $meta = ['raw' => (string) $row['meta_json']];
            }
        }

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'session_id' => isset($row['session_id']) && is_numeric($row['session_id']) ? (int) $row['session_id'] : 0,
            'direction' => sanitize_key((string) ($row['direction'] ?? 'system')),
            'message_type' => sanitize_key((string) ($row['message_type'] ?? 'event')),
            'content_text' => sanitize_textarea_field((string) ($row['content_text'] ?? '')),
            'content_json' => $contentJson,
            'meta' => $meta,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function sanitize_session_key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9:_-]/', '', $value);
        if (!is_string($value)) {
            return '';
        }
        if (strlen($value) > 191) {
            $value = substr($value, 0, 191);
        }

        return $value;
    }

    private function generate_session_key(): string
    {
        $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('navai_', true);
        return $this->sanitize_session_key((string) $uuid);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function merge_context_from_row(array $row, array $extra): array
    {
        $context = [];
        if (isset($row['context_json']) && is_string($row['context_json']) && trim($row['context_json']) !== '') {
            $decoded = json_decode((string) $row['context_json'], true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        foreach ($extra as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }
            $context[$key] = $value;
        }

        return $context;
    }

    private function is_expired_datetime(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $ts = strtotime($value);
        $nowTs = function_exists('current_time') ? (int) current_time('timestamp') : time();

        return is_int($ts) && $ts > 0 && $ts < $nowTs;
    }

    private function maybe_cleanup(array $config): void
    {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return;
        }

        $lockKey = 'navai_session_cleanup_lock';
        if (get_transient($lockKey)) {
            return;
        }

        if (wp_rand(1, 100) > 5) {
            return;
        }

        set_transient($lockKey, 1, 120);
        try {
            $this->cleanup_retention($config, 50);
        } catch (Throwable $error) {
            unset($error);
        }
    }

    private function maybe_compact_session(int $sessionId, array $settings = []): void
    {
        $config = $this->get_runtime_config($settings);
        $threshold = (int) $config['session_compaction_threshold'];
        $keepRecent = (int) $config['session_compaction_keep_recent'];

        if ($sessionId <= 0 || $threshold <= 0 || $keepRecent <= 0) {
            return;
        }

        $total = $this->repository->count_messages($sessionId);
        if ($total <= $threshold) {
            return;
        }

        $toCompact = $total - $keepRecent;
        if ($toCompact < 1) {
            return;
        }
        if ($toCompact > 500) {
            $toCompact = 500;
        }

        $messages = $this->repository->list_messages($sessionId, [
            'limit' => $toCompact,
            'order' => 'asc',
        ]);
        if (count($messages) === 0) {
            return;
        }

        $summaryLines = [];
        $lastId = 0;
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $lastId = isset($message['id']) && is_numeric($message['id']) ? (int) $message['id'] : $lastId;
            $dir = sanitize_key((string) ($message['direction'] ?? 'system'));
            $type = sanitize_key((string) ($message['message_type'] ?? 'event'));
            $text = sanitize_textarea_field((string) ($message['content_text'] ?? ''));
            if ($text === '' && isset($message['content_json']) && is_string($message['content_json'])) {
                $text = sanitize_textarea_field((string) $message['content_json']);
            }
            if ($text === '') {
                $text = '[' . $type . ']';
            }
            if (strlen($text) > 180) {
                $text = substr($text, 0, 180) . '...';
            }
            $summaryLines[] = sprintf(
                '[%s] %s/%s: %s',
                (string) ($message['created_at'] ?? ''),
                $dir !== '' ? $dir : 'system',
                $type !== '' ? $type : 'event',
                $text
            );
        }

        if ($lastId <= 0 || count($summaryLines) === 0) {
            return;
        }

        $sessionRow = $this->repository->get_session($sessionId);
        if (!is_array($sessionRow)) {
            return;
        }

        $existingSummary = sanitize_textarea_field((string) ($sessionRow['summary_text'] ?? ''));
        $summaryChunk = implode("\n", $summaryLines);
        $combinedSummary = trim($existingSummary . "\n" . $summaryChunk);
        if (strlen($combinedSummary) > 8000) {
            $combinedSummary = substr($combinedSummary, -8000);
        }

        $deleted = $this->repository->delete_messages_up_to($sessionId, $lastId);
        if ($deleted > 0) {
            $existingContext = $this->merge_context_from_row($sessionRow, []);
            $previousCompacted = isset($existingContext['compacted_messages']) && is_numeric($existingContext['compacted_messages'])
                ? (int) $existingContext['compacted_messages']
                : 0;
            $this->repository->update_session($sessionId, [
                'summary_text' => $combinedSummary,
                'status' => 'active',
                'context' => $this->merge_context_from_row($sessionRow, [
                    'last_compacted_at' => current_time('mysql'),
                    'compacted_messages' => $previousCompacted + $deleted,
                ]),
            ]);
        }
    }
}
