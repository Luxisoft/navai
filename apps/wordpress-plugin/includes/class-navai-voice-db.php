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

    public static function table_approvals(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return 'wp_navai_approvals';
        }

        return (string) $wpdb->prefix . 'navai_approvals';
    }

    public static function table_sessions(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return 'wp_navai_sessions';
        }

        return (string) $wpdb->prefix . 'navai_sessions';
    }

    public static function table_session_messages(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return 'wp_navai_session_messages';
        }

        return (string) $wpdb->prefix . 'navai_session_messages';
    }

    public static function table_agents(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return 'wp_navai_agents';
        }

        return (string) $wpdb->prefix . 'navai_agents';
    }

    public static function table_agent_handoffs(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return 'wp_navai_agent_handoffs';
        }

        return (string) $wpdb->prefix . 'navai_agent_handoffs';
    }

    public static function table_mcp_servers(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return 'wp_navai_mcp_servers';
        }

        return (string) $wpdb->prefix . 'navai_mcp_servers';
    }

    public static function table_mcp_tool_policies(): string
    {
        global $wpdb;

        if (!is_object($wpdb) || !isset($wpdb->prefix)) {
            return 'wp_navai_mcp_tool_policies';
        }

        return (string) $wpdb->prefix . 'navai_mcp_tool_policies';
    }
}
