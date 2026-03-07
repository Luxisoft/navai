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

        $message = $this->enrich_usage_tracking_message(
            $message,
            is_array($resolved['session'] ?? null) ? $resolved['session'] : null
        );

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

            $item = $this->enrich_usage_tracking_message(
                $item,
                is_array($resolved['session'] ?? null) ? $resolved['session'] : null
            );

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
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function get_usage_statistics(array $filters = []): array
    {
        $normalizedFilters = $this->normalize_usage_statistics_filters($filters);
        $rows = $this->repository->list_event_messages_with_sessions([
            'date_from' => $normalizedFilters['date_from_datetime'],
            'date_to' => $normalizedFilters['date_to_datetime'],
            'order' => 'asc',
        ]);

        $summary = [
            'responses_count' => 0,
            'sessions_count' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'input_text_tokens' => 0,
            'output_text_tokens' => 0,
            'input_audio_tokens' => 0,
            'output_audio_tokens' => 0,
            'cached_input_tokens' => 0,
            'estimated_cost_usd' => 0.0,
        ];
        $daily = [];
        $models = [];
        $agents = [];
        $sessionIds = [];
        $availableModels = [];
        $availableAgents = [];
        $sessionHints = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sessionId = isset($row['session_id']) && is_numeric($row['session_id']) ? (int) $row['session_id'] : 0;
            $contentJson = [];
            if (isset($row['content_json']) && is_string($row['content_json']) && trim($row['content_json']) !== '') {
                $decoded = json_decode((string) $row['content_json'], true);
                if (is_array($decoded)) {
                    $contentJson = $decoded;
                }
            }

            $sessionContext = [];
            if (isset($row['context_json']) && is_string($row['context_json']) && trim($row['context_json']) !== '') {
                $decodedContext = json_decode((string) $row['context_json'], true);
                if (is_array($decodedContext)) {
                    $sessionContext = $decodedContext;
                }
            }

            $type = sanitize_text_field((string) ($contentJson['type'] ?? ''));
            if ($type === 'client_secret_issued' || $type === 'realtime_connected') {
                $hintModel = $this->normalize_usage_model((string) ($contentJson['model'] ?? ''));
                if ($hintModel !== '') {
                    $sessionHints[$sessionId]['model'] = $hintModel;
                }

                $hintAgentKey = sanitize_text_field((string) ($contentJson['agent_key'] ?? ($sessionContext['active_agent_key'] ?? '')));
                $hintAgentName = sanitize_text_field((string) ($contentJson['agent_name'] ?? ($sessionContext['active_agent_name'] ?? '')));
                if ($hintAgentKey !== '' || $hintAgentName !== '') {
                    $sessionHints[$sessionId]['agent_key'] = $hintAgentKey;
                    $sessionHints[$sessionId]['agent_name'] = $hintAgentName;
                }
                continue;
            }

            $usage = $this->normalize_usage_totals($this->extract_usage_payload_from_content($contentJson));
            if (!$this->has_usage_tokens($usage)) {
                continue;
            }

            $model = $this->normalize_usage_model((string) ($contentJson['model'] ?? ''));
            if ($model === '' && isset($sessionHints[$sessionId]['model']) && is_string($sessionHints[$sessionId]['model'])) {
                $model = $this->normalize_usage_model((string) $sessionHints[$sessionId]['model']);
            }
            if ($model === '') {
                $model = $this->normalize_usage_model((string) ($sessionContext['current_model'] ?? ''));
            }
            if ($model !== '') {
                $availableModels[$model] = $model;
            }

            $agentKey = sanitize_text_field((string) ($contentJson['agent_key'] ?? ''));
            if ($agentKey === '' && isset($sessionHints[$sessionId]['agent_key']) && is_string($sessionHints[$sessionId]['agent_key'])) {
                $agentKey = sanitize_text_field((string) $sessionHints[$sessionId]['agent_key']);
            }
            if ($agentKey === '') {
                $agentKey = sanitize_text_field((string) ($sessionContext['active_agent_key'] ?? ''));
            }

            $agentName = sanitize_text_field((string) ($contentJson['agent_name'] ?? ''));
            if ($agentName === '' && isset($sessionHints[$sessionId]['agent_name']) && is_string($sessionHints[$sessionId]['agent_name'])) {
                $agentName = sanitize_text_field((string) $sessionHints[$sessionId]['agent_name']);
            }
            if ($agentName === '') {
                $agentName = sanitize_text_field((string) ($sessionContext['active_agent_name'] ?? ''));
            }

            $agentFilterValue = $agentKey !== '' ? $agentKey : '__unassigned__';
            $availableAgents[$agentFilterValue] = [
                'value' => $agentFilterValue,
                'agent_key' => $agentKey,
                'agent_name' => $agentName,
                'label' => $agentName !== '' ? $agentName : $agentKey,
            ];

            if ($normalizedFilters['model'] !== '' && $model !== $normalizedFilters['model']) {
                continue;
            }
            if ($normalizedFilters['agent'] !== '' && $agentFilterValue !== $normalizedFilters['agent']) {
                continue;
            }

            $createdAt = (string) ($row['created_at'] ?? '');
            $dayKey = preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt) ? substr($createdAt, 0, 10) : $normalizedFilters['date_to'];
            $costUsd = $this->calculate_estimated_realtime_cost_usd($model, $usage);
            $sessionIds[$sessionId] = true;
            $countsAsResponse = $type === 'response.done';

            $this->accumulate_usage_totals($summary, $usage, $costUsd);
            if ($countsAsResponse) {
                $summary['responses_count'] += 1;
            }

            if (!isset($daily[$dayKey])) {
                $daily[$dayKey] = $this->create_usage_bucket([
                    'bucket' => $dayKey,
                ]);
            }
            $this->accumulate_usage_totals($daily[$dayKey], $usage, $costUsd);
            if ($countsAsResponse) {
                $daily[$dayKey]['responses_count'] += 1;
            }
            $daily[$dayKey]['session_ids'][$sessionId] = true;

            $modelKey = $model !== '' ? $model : 'unknown';
            if (!isset($models[$modelKey])) {
                $models[$modelKey] = $this->create_usage_bucket([
                    'bucket' => $modelKey,
                    'label' => $model !== '' ? $model : '',
                    'model' => $modelKey,
                ]);
            }
            $this->accumulate_usage_totals($models[$modelKey], $usage, $costUsd);
            if ($countsAsResponse) {
                $models[$modelKey]['responses_count'] += 1;
            }
            $models[$modelKey]['session_ids'][$sessionId] = true;

            if (!isset($agents[$agentFilterValue])) {
                $agents[$agentFilterValue] = $this->create_usage_bucket([
                    'bucket' => $agentFilterValue,
                    'label' => $agentName !== '' ? $agentName : $agentKey,
                    'agent_key' => $agentKey,
                    'agent_name' => $agentName,
                ]);
            }
            $this->accumulate_usage_totals($agents[$agentFilterValue], $usage, $costUsd);
            if ($countsAsResponse) {
                $agents[$agentFilterValue]['responses_count'] += 1;
            }
            $agents[$agentFilterValue]['session_ids'][$sessionId] = true;
        }

        $summary['sessions_count'] = count($sessionIds);
        $summary['estimated_cost_usd'] = round((float) $summary['estimated_cost_usd'], 6);

        $dailyItems = $this->finalize_usage_buckets($daily, 'bucket', true);
        $modelItems = $this->finalize_usage_buckets($models, 'model', false);
        $agentItems = $this->finalize_usage_buckets($agents, 'agent_key', false);

        ksort($availableModels);
        uasort(
            $availableAgents,
            static function (array $left, array $right): int {
                return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
            }
        );

        return [
            'filters' => [
                'date_from' => $normalizedFilters['date_from'],
                'date_to' => $normalizedFilters['date_to'],
                'model' => $normalizedFilters['model'],
                'agent' => $normalizedFilters['agent'],
                'available_models' => array_values(array_map(
                    static fn(string $modelId): array => ['value' => $modelId, 'label' => $modelId],
                    array_values($availableModels)
                )),
                'available_agents' => array_values($availableAgents),
            ],
            'summary' => $summary,
            'daily_items' => $dailyItems,
            'model_items' => $modelItems,
            'agent_items' => $agentItems,
            'pricing' => [
                'currency' => 'USD',
                'reference_date' => '2026-03-07',
                'estimated' => true,
            ],
        ];
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
     * @param array<string, mixed>|null $session
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function enrich_usage_tracking_message(array $message, ?array $session): array
    {
        $messageType = sanitize_key((string) ($message['message_type'] ?? 'event'));
        if ($messageType !== 'event') {
            return $message;
        }

        $contentJson = is_array($message['content_json'] ?? null) ? $message['content_json'] : [];
        $type = sanitize_text_field((string) ($contentJson['type'] ?? ''));
        $usagePayload = $this->extract_usage_payload_from_content($contentJson);
        if ($type !== 'response.done' && $type !== 'realtime_connected' && $type !== 'client_secret_issued' && count($usagePayload) === 0) {
            return $message;
        }

        $sessionContext = is_array($session['context'] ?? null) ? $session['context'] : [];

        if (!isset($contentJson['model']) || !is_string($contentJson['model']) || trim((string) $contentJson['model']) === '') {
            $contentJson['model'] = sanitize_text_field((string) ($sessionContext['current_model'] ?? ''));
        } else {
            $contentJson['model'] = sanitize_text_field((string) $contentJson['model']);
        }

        if (!isset($contentJson['agent_key']) || !is_string($contentJson['agent_key']) || trim((string) $contentJson['agent_key']) === '') {
            $contentJson['agent_key'] = sanitize_text_field((string) ($sessionContext['active_agent_key'] ?? ''));
        } else {
            $contentJson['agent_key'] = sanitize_text_field((string) $contentJson['agent_key']);
        }

        if (!isset($contentJson['agent_name']) || !is_string($contentJson['agent_name']) || trim((string) $contentJson['agent_name']) === '') {
            $contentJson['agent_name'] = sanitize_text_field((string) ($sessionContext['active_agent_name'] ?? ''));
        } else {
            $contentJson['agent_name'] = sanitize_text_field((string) $contentJson['agent_name']);
        }

        $message['content_json'] = $contentJson;
        return $message;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, string>
     */
    private function normalize_usage_statistics_filters(array $filters): array
    {
        $todayTs = function_exists('current_time') ? (int) current_time('timestamp') : time();
        $defaultTo = gmdate('Y-m-d', $todayTs + (int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS));
        $defaultFrom = gmdate('Y-m-d', strtotime($defaultTo . ' -29 days'));

        $dateFrom = $this->sanitize_usage_date((string) ($filters['date_from'] ?? ''), $defaultFrom);
        $dateTo = $this->sanitize_usage_date((string) ($filters['date_to'] ?? ''), $defaultTo);

        if (strtotime($dateFrom) > strtotime($dateTo)) {
            $swap = $dateFrom;
            $dateFrom = $dateTo;
            $dateTo = $swap;
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_from_datetime' => $dateFrom . ' 00:00:00',
            'date_to_datetime' => $dateTo . ' 23:59:59',
            'model' => $this->normalize_usage_model((string) ($filters['model'] ?? '')),
            'agent' => sanitize_text_field((string) ($filters['agent'] ?? '')),
        ];
    }

    private function sanitize_usage_date(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false) {
            return $value;
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $contentJson
     * @return array<string, mixed>
     */
    private function extract_usage_payload_from_content(array $contentJson): array
    {
        if (is_array($contentJson['usage'] ?? null)) {
            return $contentJson['usage'];
        }

        if (is_array($contentJson['response'] ?? null) && is_array($contentJson['response']['usage'] ?? null)) {
            return $contentJson['response']['usage'];
        }

        return [];
    }

    /**
     * @param array<string, int> $usage
     */
    private function has_usage_tokens(array $usage): bool
    {
        return
            (int) ($usage['total_tokens'] ?? 0) > 0
            || (int) ($usage['input_tokens'] ?? 0) > 0
            || (int) ($usage['output_tokens'] ?? 0) > 0
            || (int) ($usage['cached_input_tokens'] ?? 0) > 0;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, string> $keys
     */
    private function read_usage_int(array $source, array $keys): int
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_numeric($source[$key])) {
                return max(0, (int) $source[$key]);
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $usage
     * @return array<string, int>
     */
    private function normalize_usage_totals(array $usage): array
    {
        $inputTokens = $this->read_usage_int($usage, ['input_tokens']);
        $outputTokens = $this->read_usage_int($usage, ['output_tokens']);

        $inputDetails = is_array($usage['input_token_details'] ?? null)
            ? $usage['input_token_details']
            : (is_array($usage['input_tokens_details'] ?? null) ? $usage['input_tokens_details'] : []);
        $outputDetails = is_array($usage['output_token_details'] ?? null)
            ? $usage['output_token_details']
            : (is_array($usage['output_tokens_details'] ?? null) ? $usage['output_tokens_details'] : []);
        $cachedDetails = is_array($inputDetails['cached_tokens_details'] ?? null)
            ? $inputDetails['cached_tokens_details']
            : (is_array($usage['cached_tokens_details'] ?? null) ? $usage['cached_tokens_details'] : []);

        $inputAudioTokens = $this->read_usage_int($inputDetails, ['audio_tokens']);
        if ($inputAudioTokens <= 0) {
            $inputAudioTokens = $this->read_usage_int($usage, ['input_audio_tokens']);
        }

        $outputAudioTokens = $this->read_usage_int($outputDetails, ['audio_tokens']);
        if ($outputAudioTokens <= 0) {
            $outputAudioTokens = $this->read_usage_int($usage, ['output_audio_tokens']);
        }

        $inputTextTokens = $this->read_usage_int($inputDetails, ['text_tokens']);
        if ($inputTextTokens <= 0) {
            $inputTextTokens = $this->read_usage_int($usage, ['input_text_tokens']);
        }

        $outputTextTokens = $this->read_usage_int($outputDetails, ['text_tokens']);
        if ($outputTextTokens <= 0) {
            $outputTextTokens = $this->read_usage_int($usage, ['output_text_tokens']);
        }

        $cachedInputTokens = $this->read_usage_int($inputDetails, ['cached_tokens']);
        if ($cachedInputTokens <= 0) {
            $cachedInputTokens = $this->read_usage_int($usage, ['cached_input_tokens', 'cached_tokens']);
        }

        $cachedTextTokens = $this->read_usage_int($inputDetails, ['cached_text_tokens']);
        if ($cachedTextTokens <= 0) {
            $cachedTextTokens = $this->read_usage_int($cachedDetails, ['text_tokens']);
        }
        if ($cachedTextTokens <= 0) {
            $cachedTextTokens = $this->read_usage_int($usage, ['cached_text_tokens']);
        }

        $cachedAudioTokens = $this->read_usage_int($inputDetails, ['cached_audio_tokens']);
        if ($cachedAudioTokens <= 0) {
            $cachedAudioTokens = $this->read_usage_int($cachedDetails, ['audio_tokens']);
        }
        if ($cachedAudioTokens <= 0) {
            $cachedAudioTokens = $this->read_usage_int($usage, ['cached_audio_tokens']);
        }

        if ($inputTextTokens <= 0 && ($inputTokens > 0 || $inputAudioTokens > 0)) {
            $inputTextTokens = max(0, $inputTokens - $inputAudioTokens);
        }
        if ($outputTextTokens <= 0 && ($outputTokens > 0 || $outputAudioTokens > 0)) {
            $outputTextTokens = max(0, $outputTokens - $outputAudioTokens);
        }

        $inputTokens = max($inputTokens, $inputTextTokens + $inputAudioTokens);
        $outputTokens = max($outputTokens, $outputTextTokens + $outputAudioTokens);
        $totalTokens = max(
            $inputTokens + $outputTokens,
            $this->read_usage_int($usage, ['total_tokens'])
        );

        if ($cachedTextTokens === 0 && $cachedAudioTokens === 0 && $cachedInputTokens > 0) {
            if ($inputTextTokens > 0) {
                $cachedTextTokens = min($cachedInputTokens, $inputTextTokens);
            } elseif ($inputAudioTokens > 0) {
                $cachedAudioTokens = min($cachedInputTokens, $inputAudioTokens);
            }
        }

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'input_text_tokens' => $inputTextTokens,
            'output_text_tokens' => $outputTextTokens,
            'input_audio_tokens' => $inputAudioTokens,
            'output_audio_tokens' => $outputAudioTokens,
            'cached_input_tokens' => max($cachedInputTokens, $cachedTextTokens + $cachedAudioTokens),
            'cached_text_tokens' => $cachedTextTokens,
            'cached_audio_tokens' => $cachedAudioTokens,
        ];
    }

    private function normalize_usage_model(string $model): string
    {
        $model = sanitize_text_field(trim($model));
        return $model;
    }

    /**
     * @param array<string, int|float> $usage
     */
    private function calculate_estimated_realtime_cost_usd(string $model, array $usage): float
    {
        $family = $this->resolve_pricing_family($model);
        $rates = $this->get_realtime_pricing_rates()[$family] ?? null;
        if (!is_array($rates)) {
            return 0.0;
        }

        $inputTextTokens = max(0, (int) ($usage['input_text_tokens'] ?? 0));
        $inputAudioTokens = max(0, (int) ($usage['input_audio_tokens'] ?? 0));
        $outputTextTokens = max(0, (int) ($usage['output_text_tokens'] ?? 0));
        $outputAudioTokens = max(0, (int) ($usage['output_audio_tokens'] ?? 0));
        $cachedTextTokens = max(0, (int) ($usage['cached_text_tokens'] ?? 0));
        $cachedAudioTokens = max(0, (int) ($usage['cached_audio_tokens'] ?? 0));

        $billableInputText = max(0, $inputTextTokens - $cachedTextTokens);
        $billableInputAudio = max(0, $inputAudioTokens - $cachedAudioTokens);

        $cost = 0.0;
        $cost += ($billableInputText * (float) ($rates['text_input'] ?? 0.0)) / 1000000;
        $cost += ($cachedTextTokens * (float) ($rates['text_cached_input'] ?? 0.0)) / 1000000;
        $cost += ($billableInputAudio * (float) ($rates['audio_input'] ?? 0.0)) / 1000000;
        $cost += ($cachedAudioTokens * (float) ($rates['audio_cached_input'] ?? 0.0)) / 1000000;
        $cost += ($outputTextTokens * (float) ($rates['text_output'] ?? 0.0)) / 1000000;
        $cost += ($outputAudioTokens * (float) ($rates['audio_output'] ?? 0.0)) / 1000000;

        return round($cost, 6);
    }

    private function resolve_pricing_family(string $model): string
    {
        $normalized = strtolower(trim($model));
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, 'gpt-4o-mini-realtime-preview')) {
            return 'gpt-4o-mini-realtime-preview';
        }
        if (str_starts_with($normalized, 'gpt-4o-realtime-preview')) {
            return 'gpt-4o-realtime-preview';
        }
        if (str_starts_with($normalized, 'gpt-realtime-mini')) {
            return 'gpt-realtime-mini';
        }
        if (str_starts_with($normalized, 'gpt-realtime')) {
            return 'gpt-realtime';
        }

        return $normalized;
    }

    /**
     * @return array<string, array<string, float>>
     */
    private function get_realtime_pricing_rates(): array
    {
        $rates = [
            'gpt-realtime' => [
                'text_input' => 4.00,
                'text_cached_input' => 0.40,
                'text_output' => 16.00,
                'audio_input' => 32.00,
                'audio_cached_input' => 0.40,
                'audio_output' => 64.00,
            ],
            'gpt-realtime-mini' => [
                'text_input' => 0.60,
                'text_cached_input' => 0.30,
                'text_output' => 2.40,
                'audio_input' => 10.00,
                'audio_cached_input' => 0.30,
                'audio_output' => 20.00,
            ],
            'gpt-4o-mini-realtime-preview' => [
                'text_input' => 0.60,
                'text_cached_input' => 0.30,
                'text_output' => 2.40,
                'audio_input' => 10.00,
                'audio_cached_input' => 0.30,
                'audio_output' => 20.00,
            ],
            'gpt-4o-realtime-preview' => [
                'text_input' => 5.00,
                'text_cached_input' => 2.50,
                'text_output' => 20.00,
                'audio_input' => 40.00,
                'audio_cached_input' => 2.50,
                'audio_output' => 80.00,
            ],
        ];

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('navai_voice_statistics_realtime_rates', $rates);
            if (is_array($filtered)) {
                return $filtered;
            }
        }

        return $rates;
    }

    /**
     * @param array<string, mixed> $seed
     * @return array<string, mixed>
     */
    private function create_usage_bucket(array $seed = []): array
    {
        return array_merge(
            [
                'bucket' => '',
                'label' => '',
                'model' => '',
                'agent_key' => '',
                'agent_name' => '',
                'responses_count' => 0,
                'sessions_count' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total_tokens' => 0,
                'input_text_tokens' => 0,
                'output_text_tokens' => 0,
                'input_audio_tokens' => 0,
                'output_audio_tokens' => 0,
                'cached_input_tokens' => 0,
                'estimated_cost_usd' => 0.0,
                'session_ids' => [],
            ],
            $seed
        );
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, int> $usage
     */
    private function accumulate_usage_totals(array &$target, array $usage, float $costUsd): void
    {
        $target['input_tokens'] += (int) ($usage['input_tokens'] ?? 0);
        $target['output_tokens'] += (int) ($usage['output_tokens'] ?? 0);
        $target['total_tokens'] += (int) ($usage['total_tokens'] ?? 0);
        $target['input_text_tokens'] += (int) ($usage['input_text_tokens'] ?? 0);
        $target['output_text_tokens'] += (int) ($usage['output_text_tokens'] ?? 0);
        $target['input_audio_tokens'] += (int) ($usage['input_audio_tokens'] ?? 0);
        $target['output_audio_tokens'] += (int) ($usage['output_audio_tokens'] ?? 0);
        $target['cached_input_tokens'] += (int) ($usage['cached_input_tokens'] ?? 0);
        $target['estimated_cost_usd'] += $costUsd;
    }

    /**
     * @param array<string, array<string, mixed>> $buckets
     * @return array<int, array<string, mixed>>
     */
    private function finalize_usage_buckets(array $buckets, string $primaryKey, bool $sortAscending): array
    {
        $items = [];
        foreach ($buckets as $bucket) {
            if (!is_array($bucket)) {
                continue;
            }

            $bucket['sessions_count'] = is_array($bucket['session_ids'] ?? null) ? count($bucket['session_ids']) : 0;
            $bucket['estimated_cost_usd'] = round((float) ($bucket['estimated_cost_usd'] ?? 0.0), 6);
            unset($bucket['session_ids']);
            $items[] = $bucket;
        }

        usort(
            $items,
            static function (array $left, array $right) use ($primaryKey, $sortAscending): int {
                if ($sortAscending) {
                    return strcmp((string) ($left[$primaryKey] ?? ''), (string) ($right[$primaryKey] ?? ''));
                }

                $tokenCompare = ((int) ($right['total_tokens'] ?? 0)) <=> ((int) ($left['total_tokens'] ?? 0));
                if ($tokenCompare !== 0) {
                    return $tokenCompare;
                }

                return strcmp((string) ($left[$primaryKey] ?? ''), (string) ($right[$primaryKey] ?? ''));
            }
        );

        return $items;
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
