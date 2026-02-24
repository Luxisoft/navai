<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Trace_Service', false)) {
    return;
}

class Navai_Voice_Trace_Service
{
    private Navai_Voice_Trace_Repository $repository;

    public function __construct(?Navai_Voice_Trace_Repository $repository = null)
    {
        $this->repository = $repository ?: new Navai_Voice_Trace_Repository();
    }

    /**
     * @param array<string, mixed> $event
     */
    public function log_event(string $eventType, array $event = [], string $severity = 'info'): int
    {
        $traceId = '';
        if (isset($event['trace_id']) && is_string($event['trace_id'])) {
            $traceId = trim($event['trace_id']);
        }
        if ($traceId === '') {
            $traceId = wp_generate_uuid4();
        }

        $spanId = '';
        if (isset($event['span_id']) && is_string($event['span_id'])) {
            $spanId = trim($event['span_id']);
        }

        $payload = [
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'event_type' => $eventType,
            'severity' => $severity,
            'event' => $event,
            'duration_ms' => isset($event['duration_ms']) && is_numeric($event['duration_ms'])
                ? (int) $event['duration_ms']
                : null,
            'session_id' => isset($event['session_id']) && is_numeric($event['session_id'])
                ? (int) $event['session_id']
                : null,
        ];

        $insertId = $this->repository->insert($payload);

        do_action('navai_trace_event_logged', $eventType, $event, $insertId);

        return $insertId;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_events(array $filters = []): array
    {
        $rows = $this->repository->list_events($filters);
        return array_map([$this, 'normalize_event_row'], $rows);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_traces(array $filters = []): array
    {
        $rows = $this->list_events(array_merge($filters, ['only_with_trace' => true, 'limit' => $filters['limit'] ?? 300]));
        $grouped = [];

        foreach ($rows as $row) {
            $traceId = sanitize_text_field((string) ($row['trace_id'] ?? ''));
            if ($traceId === '') {
                continue;
            }

            if (!isset($grouped[$traceId])) {
                $grouped[$traceId] = [
                    'trace_id' => $traceId,
                    'event_count' => 0,
                    'last_event_type' => (string) ($row['event_type'] ?? ''),
                    'last_severity' => (string) ($row['severity'] ?? ''),
                    'first_created_at' => (string) ($row['created_at'] ?? ''),
                    'last_created_at' => (string) ($row['created_at'] ?? ''),
                    'function_name' => '',
                    'function_source' => '',
                    'approval_id' => null,
                ];
            }

            $grouped[$traceId]['event_count'] = (int) $grouped[$traceId]['event_count'] + 1;
            if ((string) ($row['created_at'] ?? '') < (string) $grouped[$traceId]['first_created_at']) {
                $grouped[$traceId]['first_created_at'] = (string) ($row['created_at'] ?? '');
            }
            if ((string) ($row['created_at'] ?? '') >= (string) $grouped[$traceId]['last_created_at']) {
                $grouped[$traceId]['last_created_at'] = (string) ($row['created_at'] ?? '');
                $grouped[$traceId]['last_event_type'] = (string) ($row['event_type'] ?? '');
                $grouped[$traceId]['last_severity'] = (string) ($row['severity'] ?? '');
            }

            $event = is_array($row['event'] ?? null) ? $row['event'] : [];
            if ((string) $grouped[$traceId]['function_name'] === '' && isset($event['function_name'])) {
                $grouped[$traceId]['function_name'] = sanitize_text_field((string) $event['function_name']);
            }
            if ((string) $grouped[$traceId]['function_source'] === '' && isset($event['function_source'])) {
                $grouped[$traceId]['function_source'] = sanitize_text_field((string) $event['function_source']);
            }
            if ($grouped[$traceId]['approval_id'] === null && isset($event['approval_id']) && is_numeric($event['approval_id'])) {
                $grouped[$traceId]['approval_id'] = (int) $event['approval_id'];
            }
        }

        $items = array_values($grouped);
        usort(
            $items,
            static fn(array $a, array $b): int => strcmp((string) ($b['last_created_at'] ?? ''), (string) ($a['last_created_at'] ?? ''))
        );

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_trace_timeline(string $traceId, int $limit = 300): array
    {
        return $this->list_events([
            'trace_id' => $traceId,
            'limit' => $limit,
            'order' => 'asc',
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize_event_row(array $row): array
    {
        $event = [];
        if (isset($row['event_json']) && is_string($row['event_json']) && trim($row['event_json']) !== '') {
            $decoded = json_decode((string) $row['event_json'], true);
            if (is_array($decoded)) {
                $event = $decoded;
            } else {
                $event = ['raw' => (string) $row['event_json']];
            }
        }

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'session_id' => isset($row['session_id']) && is_numeric($row['session_id']) ? (int) $row['session_id'] : null,
            'trace_id' => sanitize_text_field((string) ($row['trace_id'] ?? '')),
            'span_id' => sanitize_text_field((string) ($row['span_id'] ?? '')),
            'event_type' => sanitize_key((string) ($row['event_type'] ?? 'event')),
            'severity' => sanitize_key((string) ($row['severity'] ?? 'info')),
            'event' => $event,
            'duration_ms' => isset($row['duration_ms']) && is_numeric($row['duration_ms']) ? (int) $row['duration_ms'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
