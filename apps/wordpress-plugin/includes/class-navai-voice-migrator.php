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
            && self::trace_events_table_exists()
        ) {
            return;
        }

        try {
            self::migrate_to_v1();
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

    private static function table_exists(string $table): bool
    {
        global $wpdb;

        if (!is_object($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
            return false;
        }

        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return is_string($result) && $result === $table;
    }

    private static function migrate_to_v1(): void
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
        $traceTable = Navai_Voice_DB::table_trace_events();
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

        dbDelta($sqlGuardrails);
        dbDelta($sqlTraceEvents);
    }
}
