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
}

