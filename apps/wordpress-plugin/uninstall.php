<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Uninstall needs explicit cleanup of plugin-owned custom tables and options.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
function navai_voice_uninstall_site_data(): void
{
    global $wpdb;

    if (!is_object($wpdb) || !isset($wpdb->prefix) || !method_exists($wpdb, 'query')) {
        return;
    }

    $tableNames = [
        $wpdb->prefix . 'navai_guardrails',
        $wpdb->prefix . 'navai_trace_events',
        $wpdb->prefix . 'navai_approvals',
        $wpdb->prefix . 'navai_sessions',
        $wpdb->prefix . 'navai_session_messages',
        $wpdb->prefix . 'navai_agents',
        $wpdb->prefix . 'navai_agent_handoffs',
        $wpdb->prefix . 'navai_mcp_servers',
        $wpdb->prefix . 'navai_mcp_tool_policies',
    ];

    foreach ($tableNames as $tableName) {
        $safeTableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        if (!is_string($safeTableName) || $safeTableName === '') {
            continue;
        }

        $wpdb->query('DROP TABLE IF EXISTS `' . $safeTableName . '`');
    }

    delete_option('navai_voice_settings');
    delete_option('navai_voice_db_version');
    delete_transient('navai_voice_bootstrap_error');
}

if (is_multisite() && function_exists('get_sites') && function_exists('switch_to_blog') && function_exists('restore_current_blog')) {
    $navai_voice_site_ids = get_sites(['fields' => 'ids']);
    if (is_array($navai_voice_site_ids)) {
        foreach ($navai_voice_site_ids as $navai_voice_site_id) {
            $navai_voice_site_id = (int) $navai_voice_site_id;
            if ($navai_voice_site_id <= 0) {
                continue;
            }

            switch_to_blog($navai_voice_site_id);
            navai_voice_uninstall_site_data();
            restore_current_blog();
        }
    }
} else {
    navai_voice_uninstall_site_data();
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
