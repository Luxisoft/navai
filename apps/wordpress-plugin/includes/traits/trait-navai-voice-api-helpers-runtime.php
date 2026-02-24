<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Navai_Voice_API_Helpers_Runtime_Trait
{
    private function build_session_instructions(
        string $baseInstructions,
        string $language,
        string $voiceAccent,
        string $voiceTone
    ): string {
        $lines = [trim($baseInstructions) !== '' ? trim($baseInstructions) : 'You are a helpful assistant.'];

        $language = trim($language);
        if ($language !== '') {
            $lines[] = sprintf('Always reply in %s.', $language);
        }

        $voiceAccent = trim($voiceAccent);
        if ($voiceAccent !== '') {
            $lines[] = sprintf('Use a %s accent while speaking.', $voiceAccent);
        }

        $voiceTone = trim($voiceTone);
        if ($voiceTone !== '') {
            $lines[] = sprintf('Use a %s tone while speaking.', $voiceTone);
        }

        return implode("\n", $lines);
    }

    private function check_rate_limit(): bool
    {
        $ip = $this->get_client_ip();
        $key = 'navai_voice_rl_' . md5($ip);
        $bucket = get_transient($key);
        $now = time();

        if (!is_array($bucket) || !isset($bucket['count'], $bucket['started_at'])) {
            $bucket = [
                'count' => 0,
                'started_at' => $now,
            ];
        }

        $windowSeconds = 60;
        $maxRequestsPerWindow = 30;
        $elapsed = $now - (int) $bucket['started_at'];
        if ($elapsed >= $windowSeconds) {
            $bucket = [
                'count' => 0,
                'started_at' => $now,
            ];
        }

        if ((int) $bucket['count'] >= $maxRequestsPerWindow) {
            return false;
        }

        $bucket['count'] = (int) $bucket['count'] + 1;
        set_transient($key, $bucket, $windowSeconds);

        return true;
    }

    private function get_client_ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_CLIENT_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        foreach ($candidates as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $parts = explode(',', $value);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return 'unknown';
    }
}

