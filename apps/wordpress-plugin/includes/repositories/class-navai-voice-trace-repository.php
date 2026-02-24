<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Trace_Repository', false)) {
    return;
}

class Navai_Voice_Trace_Repository
{
    private function table(): string
    {
        return class_exists('Navai_Voice_DB', false)
            ? Navai_Voice_DB::table_trace_events()
            : 'wp_navai_trace_events';
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): int
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'insert') || !isset($wpdb->insert_id)) {
            return 0;
        }

        $ok = $wpdb->insert(
            $this->table(),
            [
                'session_id' => isset($row['session_id']) && is_numeric($row['session_id']) ? (int) $row['session_id'] : null,
                'trace_id' => sanitize_text_field((string) ($row['trace_id'] ?? '')),
                'span_id' => sanitize_text_field((string) ($row['span_id'] ?? '')),
                'event_type' => sanitize_key((string) ($row['event_type'] ?? 'event')),
                'severity' => sanitize_key((string) ($row['severity'] ?? 'info')),
                'event_json' => wp_json_encode($row['event'] ?? []),
                'duration_ms' => isset($row['duration_ms']) && is_numeric($row['duration_ms']) ? (int) $row['duration_ms'] : null,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        if (!$ok) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }
}

