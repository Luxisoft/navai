<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_DB', false)) {
    return;
}

class Navai_Voice_DB
{
    public const OPTION_DB_VERSION = 'navai_voice_db_version';

    public static function get_charset_collate(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !method_exists($wpdb, 'get_charset_collate')) {
            return '';
        }

        return (string) $wpdb->get_charset_collate();
    }

    public static function table_guardrails(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return 'wp_navai_guardrails';
        }

        return (string) $wpdb->prefix . 'navai_guardrails';
    }

    public static function table_trace_events(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return 'wp_navai_trace_events';
        }

        return (string) $wpdb->prefix . 'navai_trace_events';
    }
}
