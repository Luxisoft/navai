<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Privacy', false)) {
    return;
}

// Privacy export/erase callbacks need one-off queries against the plugin's custom tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
class Navai_Voice_Privacy
{
    public function register_privacy_policy_content(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = sprintf(
            '<p>%1$s</p><p>%2$s</p><ul><li>%3$s</li><li>%4$s</li><li>%5$s</li><li>%6$s</li></ul><p>%7$s</p>',
            esc_html__(
                'NAVAI Voice can process voice and text interactions, and can optionally store session history, traces, and approval records inside WordPress.',
                'navai-voice'
            ),
            esc_html__(
                'When a site visitor starts a voice or text interaction, this plugin may send audio, text prompts, model settings, and tool payloads to external AI services configured by the site owner.',
                'navai-voice'
            ),
            esc_html__(
                'OpenAI Realtime: used to create realtime voice sessions and process audio/text requests.',
                'navai-voice'
            ),
            esc_html__(
                'Optional MCP servers: used only if an administrator enables MCP integrations and configures one or more remote servers.',
                'navai-voice'
            ),
            esc_html__(
                'Session history and transcripts: stored only when session memory/history is enabled in the plugin settings.',
                'navai-voice'
            ),
            esc_html__(
                'Runtime traces and approvals: stored only when those features are enabled and used by the site owner.',
                'navai-voice'
            ),
            esc_html__(
                'The plugin registers personal data exporter and eraser handlers for logged-in WordPress users. Site owners should also disclose the privacy policies and terms of any external providers they configure.',
                'navai-voice'
            )
        );

        wp_add_privacy_policy_content(
            esc_html__('NAVAI Voice', 'navai-voice'),
            wp_kses_post($content)
        );
    }

    /**
     * @param array<string, mixed> $exporters
     * @return array<string, mixed>
     */
    public function register_exporters(array $exporters): array
    {
        $exporters['navai-voice'] = [
            'exporter_friendly_name' => __('NAVAI Voice data', 'navai-voice'),
            'callback' => [$this, 'export_personal_data'],
        ];

        return $exporters;
    }

    /**
     * @param array<string, mixed> $erasers
     * @return array<string, mixed>
     */
    public function register_erasers(array $erasers): array
    {
        $erasers['navai-voice'] = [
            'eraser_friendly_name' => __('NAVAI Voice data', 'navai-voice'),
            'callback' => [$this, 'erase_personal_data'],
        ];

        return $erasers;
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, done: bool}
     */
    public function export_personal_data(string $emailAddress, int $page = 1): array
    {
        $userId = $this->resolve_user_id($emailAddress);
        if ($userId <= 0) {
            return [
                'data' => [],
                'done' => true,
            ];
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_results') || !method_exists($wpdb, 'get_var')) {
            return [
                'data' => [],
                'done' => true,
            ];
        }

        $page = max(1, $page);
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $sessionsTable = Navai_Voice_DB::table_sessions();
        $messagesTable = Navai_Voice_DB::table_session_messages();
        $approvalsTable = Navai_Voice_DB::table_approvals();

        $totalSql = $wpdb->prepare(
            'SELECT COUNT(1) FROM ' . $sessionsTable . ' WHERE wp_user_id = %d',
            $userId
        );
        $totalSessions = is_string($totalSql) ? (int) $wpdb->get_var($totalSql) : 0;

        $sessionsSql = $wpdb->prepare(
            'SELECT * FROM ' . $sessionsTable . ' WHERE wp_user_id = %d ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d',
            $userId,
            $limit,
            $offset
        );
        $sessionRows = is_string($sessionsSql) ? $wpdb->get_results($sessionsSql, ARRAY_A) : [];
        if (!is_array($sessionRows)) {
            $sessionRows = [];
        }

        $exportItems = [];
        foreach ($sessionRows as $sessionRow) {
            if (!is_array($sessionRow)) {
                continue;
            }

            $sessionId = isset($sessionRow['id']) && is_numeric($sessionRow['id']) ? (int) $sessionRow['id'] : 0;
            if ($sessionId <= 0) {
                continue;
            }

            $exportItems[] = [
                'group_id' => 'navai-voice-sessions',
                'group_label' => __('NAVAI Voice sessions', 'navai-voice'),
                'item_id' => 'navai-session-' . $sessionId,
                'data' => [
                    [
                        'name' => __('Session ID', 'navai-voice'),
                        'value' => (string) $sessionId,
                    ],
                    [
                        'name' => __('Session key', 'navai-voice'),
                        'value' => sanitize_text_field((string) ($sessionRow['session_key'] ?? '')),
                    ],
                    [
                        'name' => __('Visitor key', 'navai-voice'),
                        'value' => sanitize_text_field((string) ($sessionRow['visitor_key'] ?? '')),
                    ],
                    [
                        'name' => __('Status', 'navai-voice'),
                        'value' => sanitize_key((string) ($sessionRow['status'] ?? '')),
                    ],
                    [
                        'name' => __('Summary', 'navai-voice'),
                        'value' => sanitize_textarea_field((string) ($sessionRow['summary_text'] ?? '')),
                    ],
                    [
                        'name' => __('Context JSON', 'navai-voice'),
                        'value' => $this->stringify_json($sessionRow['context_json'] ?? ''),
                    ],
                    [
                        'name' => __('Created at', 'navai-voice'),
                        'value' => sanitize_text_field((string) ($sessionRow['created_at'] ?? '')),
                    ],
                    [
                        'name' => __('Updated at', 'navai-voice'),
                        'value' => sanitize_text_field((string) ($sessionRow['updated_at'] ?? '')),
                    ],
                    [
                        'name' => __('Expires at', 'navai-voice'),
                        'value' => sanitize_text_field((string) ($sessionRow['expires_at'] ?? '')),
                    ],
                ],
            ];

            $messagesSql = $wpdb->prepare(
                'SELECT * FROM ' . $messagesTable . ' WHERE session_id = %d ORDER BY created_at ASC, id ASC',
                $sessionId
            );
            $messageRows = is_string($messagesSql) ? $wpdb->get_results($messagesSql, ARRAY_A) : [];
            if (!is_array($messageRows)) {
                $messageRows = [];
            }

            foreach ($messageRows as $messageRow) {
                if (!is_array($messageRow)) {
                    continue;
                }

                $messageId = isset($messageRow['id']) && is_numeric($messageRow['id']) ? (int) $messageRow['id'] : 0;
                if ($messageId <= 0) {
                    continue;
                }

                $exportItems[] = [
                    'group_id' => 'navai-voice-session-messages',
                    'group_label' => __('NAVAI Voice session messages', 'navai-voice'),
                    'item_id' => 'navai-session-message-' . $messageId,
                    'data' => [
                        [
                            'name' => __('Session ID', 'navai-voice'),
                            'value' => (string) $sessionId,
                        ],
                        [
                            'name' => __('Direction', 'navai-voice'),
                            'value' => sanitize_key((string) ($messageRow['direction'] ?? '')),
                        ],
                        [
                            'name' => __('Message type', 'navai-voice'),
                            'value' => sanitize_key((string) ($messageRow['message_type'] ?? '')),
                        ],
                        [
                            'name' => __('Content text', 'navai-voice'),
                            'value' => sanitize_textarea_field((string) ($messageRow['content_text'] ?? '')),
                        ],
                        [
                            'name' => __('Content JSON', 'navai-voice'),
                            'value' => $this->stringify_json($messageRow['content_json'] ?? ''),
                        ],
                        [
                            'name' => __('Meta JSON', 'navai-voice'),
                            'value' => $this->stringify_json($messageRow['meta_json'] ?? ''),
                        ],
                        [
                            'name' => __('Created at', 'navai-voice'),
                            'value' => sanitize_text_field((string) ($messageRow['created_at'] ?? '')),
                        ],
                    ],
                ];
            }
        }

        if ($page === 1) {
            $approvalsSql = $wpdb->prepare(
                'SELECT * FROM ' . $approvalsTable . ' WHERE requested_by_user_id = %d OR approved_by_user_id = %d ORDER BY created_at DESC, id DESC LIMIT 200',
                $userId,
                $userId
            );
            $approvalRows = is_string($approvalsSql) ? $wpdb->get_results($approvalsSql, ARRAY_A) : [];
            if (!is_array($approvalRows)) {
                $approvalRows = [];
            }

            foreach ($approvalRows as $approvalRow) {
                if (!is_array($approvalRow)) {
                    continue;
                }

                $approvalId = isset($approvalRow['id']) && is_numeric($approvalRow['id']) ? (int) $approvalRow['id'] : 0;
                if ($approvalId <= 0) {
                    continue;
                }

                $exportItems[] = [
                    'group_id' => 'navai-voice-approvals',
                    'group_label' => __('NAVAI Voice approvals', 'navai-voice'),
                    'item_id' => 'navai-approval-' . $approvalId,
                    'data' => [
                        [
                            'name' => __('Status', 'navai-voice'),
                            'value' => sanitize_key((string) ($approvalRow['status'] ?? '')),
                        ],
                        [
                            'name' => __('Function key', 'navai-voice'),
                            'value' => sanitize_text_field((string) ($approvalRow['function_key'] ?? '')),
                        ],
                        [
                            'name' => __('Function source', 'navai-voice'),
                            'value' => sanitize_text_field((string) ($approvalRow['function_source'] ?? '')),
                        ],
                        [
                            'name' => __('Payload JSON', 'navai-voice'),
                            'value' => $this->stringify_json($approvalRow['payload_json'] ?? ''),
                        ],
                        [
                            'name' => __('Reason', 'navai-voice'),
                            'value' => sanitize_textarea_field((string) ($approvalRow['reason'] ?? '')),
                        ],
                        [
                            'name' => __('Decision notes', 'navai-voice'),
                            'value' => sanitize_textarea_field((string) ($approvalRow['decision_notes'] ?? '')),
                        ],
                        [
                            'name' => __('Result JSON', 'navai-voice'),
                            'value' => $this->stringify_json($approvalRow['result_json'] ?? ''),
                        ],
                        [
                            'name' => __('Error message', 'navai-voice'),
                            'value' => sanitize_textarea_field((string) ($approvalRow['error_message'] ?? '')),
                        ],
                        [
                            'name' => __('Trace ID', 'navai-voice'),
                            'value' => sanitize_text_field((string) ($approvalRow['trace_id'] ?? '')),
                        ],
                        [
                            'name' => __('Created at', 'navai-voice'),
                            'value' => sanitize_text_field((string) ($approvalRow['created_at'] ?? '')),
                        ],
                        [
                            'name' => __('Resolved at', 'navai-voice'),
                            'value' => sanitize_text_field((string) ($approvalRow['resolved_at'] ?? '')),
                        ],
                    ],
                ];
            }
        }

        return [
            'data' => $exportItems,
            'done' => ($offset + count($sessionRows)) >= $totalSessions,
        ];
    }

    /**
     * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
     */
    public function erase_personal_data(string $emailAddress, int $page = 1): array
    {
        $userId = $this->resolve_user_id($emailAddress);
        if ($userId <= 0) {
            return [
                'items_removed' => false,
                'items_retained' => false,
                'messages' => [],
                'done' => true,
            ];
        }

        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_col') || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'query')) {
            return [
                'items_removed' => false,
                'items_retained' => false,
                'messages' => [],
                'done' => true,
            ];
        }

        $page = max(1, $page);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $sessionsTable = Navai_Voice_DB::table_sessions();
        $messagesTable = Navai_Voice_DB::table_session_messages();
        $tracesTable = Navai_Voice_DB::table_trace_events();
        $approvalsTable = Navai_Voice_DB::table_approvals();

        $sessionIdsSql = $wpdb->prepare(
            'SELECT id FROM ' . $sessionsTable . ' WHERE wp_user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
            $userId,
            $limit,
            $offset
        );
        $sessionIds = is_string($sessionIdsSql) ? $wpdb->get_col($sessionIdsSql) : [];
        if (!is_array($sessionIds)) {
            $sessionIds = [];
        }
        $sessionIds = array_values(array_filter(array_map('intval', $sessionIds), static fn(int $id): bool => $id > 0));

        $itemsRemoved = false;
        $itemsRetained = false;
        $messages = [];

        if (count($sessionIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($sessionIds), '%d'));
            $traceSql = $wpdb->prepare(
                'DELETE FROM ' . $tracesTable . ' WHERE session_id IN (' . $placeholders . ')',
                ...$sessionIds
            );
            if (is_string($traceSql)) {
                $wpdb->query($traceSql);
            }

            $approvalSql = $wpdb->prepare(
                'DELETE FROM ' . $approvalsTable . ' WHERE session_id IN (' . $placeholders . ')',
                ...$sessionIds
            );
            if (is_string($approvalSql)) {
                $wpdb->query($approvalSql);
            }

            $messagesSql = $wpdb->prepare(
                'DELETE FROM ' . $messagesTable . ' WHERE session_id IN (' . $placeholders . ')',
                ...$sessionIds
            );
            if (is_string($messagesSql)) {
                $wpdb->query($messagesSql);
            }

            $sessionsSql = $wpdb->prepare(
                'DELETE FROM ' . $sessionsTable . ' WHERE id IN (' . $placeholders . ')',
                ...$sessionIds
            );
            if (is_string($sessionsSql)) {
                $wpdb->query($sessionsSql);
            }

            $itemsRemoved = true;
            $messages[] = __('Deleted NAVAI Voice sessions, messages, traces, and related approval requests for this user.', 'navai-voice');
        }

        if ($page === 1) {
            $deleteLooseApprovalsSql = $wpdb->prepare(
                'DELETE FROM ' . $approvalsTable . ' WHERE requested_by_user_id = %d AND (session_id IS NULL OR session_id = 0)',
                $userId
            );
            if (is_string($deleteLooseApprovalsSql)) {
                $deletedApprovals = $wpdb->query($deleteLooseApprovalsSql);
                if (is_numeric($deletedApprovals) && (int) $deletedApprovals > 0) {
                    $itemsRemoved = true;
                }
            }

            $anonymizeApprovalsSql = $wpdb->prepare(
                'UPDATE ' . $approvalsTable . ' SET approved_by_user_id = NULL WHERE approved_by_user_id = %d',
                $userId
            );
            if (is_string($anonymizeApprovalsSql)) {
                $updatedApprovals = $wpdb->query($anonymizeApprovalsSql);
                if (is_numeric($updatedApprovals) && (int) $updatedApprovals > 0) {
                    $itemsRemoved = true;
                    $messages[] = __('Anonymized NAVAI Voice approval decisions made by this user.', 'navai-voice');
                }
            }
        }

        $remainingSql = $wpdb->prepare(
            'SELECT COUNT(1) FROM ' . $sessionsTable . ' WHERE wp_user_id = %d',
            $userId
        );
        $remaining = is_string($remainingSql) ? (int) $wpdb->get_var($remainingSql) : 0;
        if (!$itemsRemoved && $remaining > 0) {
            $itemsRetained = true;
        }

        return [
            'items_removed' => $itemsRemoved,
            'items_retained' => $itemsRetained,
            'messages' => $messages,
            'done' => $remaining === 0,
        ];
    }

    private function resolve_user_id(string $emailAddress): int
    {
        $emailAddress = sanitize_email($emailAddress);
        if ($emailAddress === '' || !function_exists('get_user_by')) {
            return 0;
        }

        $user = get_user_by('email', $emailAddress);
        if (!$user || !isset($user->ID)) {
            return 0;
        }

        return (int) $user->ID;
    }

    /**
     * @param mixed $value
     */
    private function stringify_json($value): string
    {
        if (is_array($value) || is_object($value)) {
            $encoded = wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : '';
        }

        if (!is_string($value)) {
            return '';
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $encoded = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : $value;
        }

        return sanitize_textarea_field($value);
    }
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
