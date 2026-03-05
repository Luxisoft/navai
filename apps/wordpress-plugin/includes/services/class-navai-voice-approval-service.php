<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Approval_Service', false)) {
    return;
}

class Navai_Voice_Approval_Service
{
    private Navai_Voice_Approval_Repository $repository;

    public function __construct(?Navai_Voice_Approval_Repository $repository = null)
    {
        $this->repository = $repository ?: new Navai_Voice_Approval_Repository();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_requests(array $filters = []): array
    {
        $rows = $this->repository->list($filters);
        return array_map([$this, 'normalize_row'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_request(int $id): ?array
    {
        $row = $this->repository->get($id);
        return is_array($row) ? $this->normalize_row($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function create_request(array $payload)
    {
        $normalized = [
            'status' => 'pending',
            'requested_by_user_id' => isset($payload['requested_by_user_id']) ? (int) $payload['requested_by_user_id'] : null,
            'session_id' => isset($payload['session_id']) ? (int) $payload['session_id'] : null,
            'function_id' => isset($payload['function_id']) ? (int) $payload['function_id'] : null,
            'function_key' => sanitize_text_field((string) ($payload['function_key'] ?? '')),
            'function_source' => sanitize_text_field((string) ($payload['function_source'] ?? '')),
            'payload' => isset($payload['payload']) ? $payload['payload'] : [],
            'reason' => sanitize_textarea_field((string) ($payload['reason'] ?? '')),
            'trace_id' => sanitize_text_field((string) ($payload['trace_id'] ?? '')),
        ];

        if ($normalized['function_key'] === '') {
            return new WP_Error('navai_invalid_approval_function', 'function_key is required.', ['status' => 400]);
        }

        $created = $this->repository->create($normalized);
        if (!is_array($created)) {
            return new WP_Error('navai_approval_create_failed', 'Failed to create approval request.', ['status' => 500]);
        }

        return $this->normalize_row($created);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function approve_request(int $id, array $payload = [])
    {
        $row = $this->repository->get($id);
        if (!is_array($row)) {
            return new WP_Error('navai_approval_not_found', 'Approval request not found.', ['status' => 404]);
        }

        $status = sanitize_key((string) ($row['status'] ?? ''));
        if ($status !== 'pending') {
            return new WP_Error('navai_approval_not_pending', 'Approval request is not pending.', ['status' => 409]);
        }

        $updated = $this->repository->resolve(
            $id,
            'approved',
            [
                'approved_by_user_id' => isset($payload['approved_by_user_id']) ? (int) $payload['approved_by_user_id'] : get_current_user_id(),
                'decision_notes' => (string) ($payload['decision_notes'] ?? ''),
                'result' => $payload['result'] ?? null,
                'error_message' => $payload['error_message'] ?? null,
            ]
        );

        if (!is_array($updated)) {
            return new WP_Error('navai_approval_approve_failed', 'Failed to approve request.', ['status' => 500]);
        }

        return $this->normalize_row($updated);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function reject_request(int $id, array $payload = [])
    {
        $row = $this->repository->get($id);
        if (!is_array($row)) {
            return new WP_Error('navai_approval_not_found', 'Approval request not found.', ['status' => 404]);
        }

        $status = sanitize_key((string) ($row['status'] ?? ''));
        if ($status !== 'pending') {
            return new WP_Error('navai_approval_not_pending', 'Approval request is not pending.', ['status' => 409]);
        }

        $updated = $this->repository->resolve(
            $id,
            'rejected',
            [
                'approved_by_user_id' => isset($payload['approved_by_user_id']) ? (int) $payload['approved_by_user_id'] : get_current_user_id(),
                'decision_notes' => (string) ($payload['decision_notes'] ?? ''),
                'result' => null,
                'error_message' => $payload['error_message'] ?? null,
            ]
        );

        if (!is_array($updated)) {
            return new WP_Error('navai_approval_reject_failed', 'Failed to reject request.', ['status' => 500]);
        }

        return $this->normalize_row($updated);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize_row(array $row): array
    {
        $payload = [];
        if (isset($row['payload_json']) && is_string($row['payload_json']) && trim($row['payload_json']) !== '') {
            $decodedPayload = json_decode((string) $row['payload_json'], true);
            if (is_array($decodedPayload)) {
                $payload = $decodedPayload;
            } else {
                $payload = ['raw' => (string) $row['payload_json']];
            }
        }

        $result = null;
        if (isset($row['result_json']) && is_string($row['result_json']) && trim($row['result_json']) !== '') {
            $decodedResult = json_decode((string) $row['result_json'], true);
            $result = $decodedResult !== null ? $decodedResult : (string) $row['result_json'];
        }

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'status' => sanitize_key((string) ($row['status'] ?? 'pending')),
            'requested_by_user_id' => isset($row['requested_by_user_id']) ? (int) $row['requested_by_user_id'] : 0,
            'session_id' => isset($row['session_id']) && is_numeric($row['session_id']) ? (int) $row['session_id'] : null,
            'function_id' => isset($row['function_id']) && is_numeric($row['function_id']) ? (int) $row['function_id'] : null,
            'function_key' => sanitize_text_field((string) ($row['function_key'] ?? '')),
            'function_source' => sanitize_text_field((string) ($row['function_source'] ?? '')),
            'payload' => $payload,
            'reason' => sanitize_textarea_field((string) ($row['reason'] ?? '')),
            'approved_by_user_id' => isset($row['approved_by_user_id']) && is_numeric($row['approved_by_user_id']) ? (int) $row['approved_by_user_id'] : null,
            'decision_notes' => sanitize_textarea_field((string) ($row['decision_notes'] ?? '')),
            'result' => $result,
            'error_message' => sanitize_textarea_field((string) ($row['error_message'] ?? '')),
            'trace_id' => sanitize_text_field((string) ($row['trace_id'] ?? '')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'resolved_at' => (string) ($row['resolved_at'] ?? ''),
        ];
    }
}

