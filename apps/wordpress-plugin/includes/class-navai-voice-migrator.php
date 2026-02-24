<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('Navai_Voice_Migrator', false)) {
    return;
}

class Navai_Voice_Migrator
{
    public static function maybe_migrate(): void
    {
        if (!class_exists('Navai_Voice_DB', false) || !function_exists('get_option')) {
            return;
        }

        $targetVersion = defined('NAVAI_VOICE_DB_VERSION')
            ? (string) NAVAI_VOICE_DB_VERSION
            : '1';

        $installedVersion = (string) get_option(Navai_Voice_DB::OPTION_DB_VERSION, '0');

        if (
            $installedVersion === $targetVersion
            && self::guardrails_table_exists()
            && self::approvals_table_exists()
            && self::trace_events_table_exists()
            && self::sessions_table_exists()
            && self::session_messages_table_exists()
            && self::agents_table_exists()
            && self::agent_handoffs_table_exists()
            && self::mcp_servers_table_exists()
            && self::mcp_tool_policies_table_exists()
        ) {
            return;
        }

        try {
            self::migrate_to_v5();
            update_option(Navai_Voice_DB::OPTION_DB_VERSION, $targetVersion);
        } catch (Throwable $error) {
            if (function_exists('navai_voice_mark_bootstrap_error')) {
                navai_voice_mark_bootstrap_error(
                    'Error en migracion de base de datos: ' . $error->getMessage(),
                    true
                );
            }
        }
    }

    private static function guardrails_table_exists(): bool
    {
        return self::table_exists(Navai_Voice_DB::table_guardrails());
    }

    private static function trace_events_table_exists(): bool
    {
        return self::table_exists(Navai_Voice_DB::table_trace_events());
    }

    private static function approvals_table_exists(): bool
    {
        return self::table_exists(Navai_Voice_DB::table_approvals());
    }

    private static function sessions_table_exists(): bool
    {
        return self::table_exists(Navai_Voice_DB::table_sessions());
    }

    private static function session_messages_table_exists(): bool
    {
        return self::table_exists(Navai_Voice_DB::table_session_messages());
    }

    private static function agents_table_exists(): bool
    {
        return self::table_exists(Navai_Voice_DB::table_agents());
    }

    private static function agent_handoffs_table_exists(): bool
    {
        return self::table_exists(Navai_Voice_DB::table_agent_handoffs());
    }

    private static function mcp_servers_table_exists(): bool
    {
        return self::table_exists(Navai_Voice_DB::table_mcp_servers());
    }

    private static function mcp_tool_policies_table_exists(): bool
    {
        return self::table_exists(Navai_Voice_DB::table_mcp_tool_policies());
    }

    private static function table_exists(string $table): bool
    {
        global $wpdb;

        if (!is_object($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
            return false;
        }

        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return is_string($result) && $result === $table;
    }

    private static function migrate_to_v5(): void
    {
        global $wpdb;

        if (!is_object($wpdb)) {
            throw new RuntimeException('wpdb no disponible.');
        }

        $upgradeFile = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (!file_exists($upgradeFile)) {
            throw new RuntimeException('No se encontro wp-admin/includes/upgrade.php');
        }

        require_once $upgradeFile;

        $guardrailsTable = Navai_Voice_DB::table_guardrails();
        $approvalsTable = Navai_Voice_DB::table_approvals();
        $traceTable = Navai_Voice_DB::table_trace_events();
        $sessionsTable = Navai_Voice_DB::table_sessions();
        $sessionMessagesTable = Navai_Voice_DB::table_session_messages();
        $agentsTable = Navai_Voice_DB::table_agents();
        $agentHandoffsTable = Navai_Voice_DB::table_agent_handoffs();
        $mcpServersTable = Navai_Voice_DB::table_mcp_servers();
        $mcpToolPoliciesTable = Navai_Voice_DB::table_mcp_tool_policies();
        $charsetCollate = Navai_Voice_DB::get_charset_collate();

        $sqlGuardrails = "CREATE TABLE {$guardrailsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scope varchar(32) NOT NULL DEFAULT 'input',
            type varchar(32) NOT NULL DEFAULT 'keyword',
            name varchar(191) NOT NULL DEFAULT '',
            enabled tinyint(1) NOT NULL DEFAULT 1,
            role_scope varchar(191) NOT NULL DEFAULT '',
            plugin_scope varchar(191) NOT NULL DEFAULT '',
            pattern longtext NOT NULL,
            action varchar(32) NOT NULL DEFAULT 'block',
            priority int(11) NOT NULL DEFAULT 100,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY scope_enabled (scope, enabled),
            KEY priority (priority)
        ) {$charsetCollate};";

        $sqlTraceEvents = "CREATE TABLE {$traceTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned DEFAULT NULL,
            trace_id varchar(64) NOT NULL DEFAULT '',
            span_id varchar(64) NOT NULL DEFAULT '',
            event_type varchar(64) NOT NULL DEFAULT '',
            severity varchar(16) NOT NULL DEFAULT 'info',
            event_json longtext NOT NULL,
            duration_ms int(11) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY trace_id (trace_id)
        ) {$charsetCollate};";

        $sqlApprovals = "CREATE TABLE {$approvalsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            status varchar(20) NOT NULL DEFAULT 'pending',
            requested_by_user_id bigint(20) unsigned DEFAULT NULL,
            session_id bigint(20) unsigned DEFAULT NULL,
            function_id bigint(20) unsigned DEFAULT NULL,
            function_key varchar(191) NOT NULL DEFAULT '',
            function_source varchar(191) NOT NULL DEFAULT '',
            payload_json longtext NOT NULL,
            reason text NOT NULL,
            approved_by_user_id bigint(20) unsigned DEFAULT NULL,
            decision_notes text NOT NULL,
            result_json longtext NULL,
            error_message text NULL,
            trace_id varchar(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY function_key (function_key),
            KEY trace_id (trace_id),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        $sqlSessions = "CREATE TABLE {$sessionsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_key varchar(191) NOT NULL DEFAULT '',
            wp_user_id bigint(20) unsigned DEFAULT NULL,
            visitor_key varchar(191) DEFAULT NULL,
            context_json longtext NOT NULL,
            summary_text longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_key (session_key),
            KEY wp_user_id (wp_user_id),
            KEY visitor_key (visitor_key(64)),
            KEY status (status),
            KEY updated_at (updated_at),
            KEY expires_at (expires_at)
        ) {$charsetCollate};";

        $sqlSessionMessages = "CREATE TABLE {$sessionMessagesTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            direction varchar(20) NOT NULL DEFAULT 'system',
            message_type varchar(32) NOT NULL DEFAULT 'event',
            content_text longtext NULL,
            content_json longtext NULL,
            meta_json longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY session_created (session_id, created_at),
            KEY message_type (message_type),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        $sqlAgents = "CREATE TABLE {$agentsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            agent_key varchar(64) NOT NULL DEFAULT '',
            name varchar(191) NOT NULL DEFAULT '',
            description text NOT NULL,
            instructions_text longtext NOT NULL,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            allowed_tools_json longtext NOT NULL,
            allowed_routes_json longtext NOT NULL,
            context_json longtext NOT NULL,
            priority int(11) NOT NULL DEFAULT 100,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY agent_key (agent_key),
            KEY enabled (enabled),
            KEY is_default (is_default),
            KEY priority (priority)
        ) {$charsetCollate};";

        $sqlAgentHandoffs = "CREATE TABLE {$agentHandoffsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_agent_id bigint(20) unsigned DEFAULT NULL,
            target_agent_id bigint(20) unsigned NOT NULL,
            name varchar(191) NOT NULL DEFAULT '',
            enabled tinyint(1) NOT NULL DEFAULT 1,
            priority int(11) NOT NULL DEFAULT 100,
            match_json longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY source_agent_id (source_agent_id),
            KEY target_agent_id (target_agent_id),
            KEY enabled_priority (enabled, priority)
        ) {$charsetCollate};";

        $sqlMcpServers = "CREATE TABLE {$mcpServersTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            server_key varchar(64) NOT NULL DEFAULT '',
            name varchar(191) NOT NULL DEFAULT '',
            base_url text NOT NULL,
            transport varchar(32) NOT NULL DEFAULT 'http_jsonrpc',
            enabled tinyint(1) NOT NULL DEFAULT 1,
            auth_type varchar(32) NOT NULL DEFAULT 'none',
            auth_header_name varchar(191) NOT NULL DEFAULT '',
            auth_value longtext NOT NULL,
            extra_headers_json longtext NOT NULL,
            timeout_connect_seconds int(11) NOT NULL DEFAULT 10,
            timeout_read_seconds int(11) NOT NULL DEFAULT 20,
            verify_ssl tinyint(1) NOT NULL DEFAULT 1,
            tools_json longtext NOT NULL,
            tools_hash varchar(64) NOT NULL DEFAULT '',
            tool_count int(11) NOT NULL DEFAULT 0,
            last_health_status varchar(16) NOT NULL DEFAULT 'unknown',
            last_health_message text NOT NULL,
            last_http_status int(11) NOT NULL DEFAULT 0,
            last_health_checked_at datetime DEFAULT NULL,
            last_tools_sync_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY server_key (server_key),
            KEY enabled (enabled),
            KEY last_health_status (last_health_status),
            KEY updated_at (updated_at)
        ) {$charsetCollate};";

        $sqlMcpToolPolicies = "CREATE TABLE {$mcpToolPoliciesTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            server_id bigint(20) unsigned NOT NULL DEFAULT 0,
            tool_name varchar(191) NOT NULL DEFAULT '*',
            mode varchar(16) NOT NULL DEFAULT 'allow',
            enabled tinyint(1) NOT NULL DEFAULT 1,
            priority int(11) NOT NULL DEFAULT 100,
            roles_json longtext NOT NULL,
            agent_keys_json longtext NOT NULL,
            notes text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY server_id (server_id),
            KEY tool_name (tool_name),
            KEY mode (mode),
            KEY enabled_priority (enabled, priority)
        ) {$charsetCollate};";

        dbDelta($sqlGuardrails);
        dbDelta($sqlTraceEvents);
        dbDelta($sqlApprovals);
        dbDelta($sqlSessions);
        dbDelta($sqlSessionMessages);
        dbDelta($sqlAgents);
        dbDelta($sqlAgentHandoffs);
        dbDelta($sqlMcpServers);
        dbDelta($sqlMcpToolPolicies);
    }
}
