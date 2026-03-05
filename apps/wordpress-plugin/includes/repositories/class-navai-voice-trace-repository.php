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

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_events(array $filters = []): array
    {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        $traceId = isset($filters['trace_id']) ? sanitize_text_field((string) $filters['trace_id']) : '';
        if ($traceId !== '') {
            $where[] = 'trace_id = %s';
            $params[] = $traceId;
        }

        $eventType = isset($filters['event_type']) ? sanitize_key((string) $filters['event_type']) : '';
        if ($eventType !== '') {
            $where[] = 'event_type = %s';
            $params[] = $eventType;
        }

        $severity = isset($filters['severity']) ? sanitize_key((string) $filters['severity']) : '';
        if ($severity !== '') {
            $where[] = 'severity = %s';
            $params[] = $severity;
        }

        if (!empty($filters['only_with_trace'])) {
            $where[] = "trace_id <> ''";
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 200;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        $order = strtolower(trim((string) ($filters['order'] ?? 'desc')));
        $direction = $order === 'asc' ? 'ASC' : 'DESC';

        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at ' . $direction . ', id ' . $direction . ' LIMIT ' . (int) $limit;
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
}
