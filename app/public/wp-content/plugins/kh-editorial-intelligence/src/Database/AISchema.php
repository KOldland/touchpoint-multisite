<?php

namespace KH\Editorial\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISchema
 * 
 * Manages the centralized database tables for AI jobs, budgets, and audit logs.
 */
class AISchema {

    /**
     * Create tables for the AI infrastructure.
     */
    public static function up() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. AI Jobs (The universal queue)
        $table_jobs = $wpdb->prefix . 'ai_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
            id varchar(36) NOT NULL,
            session_id varchar(36) DEFAULT NULL,
            status varchar(32) NOT NULL DEFAULT 'queued',
            model varchar(64) DEFAULT NULL,
            prompt longtext DEFAULT NULL,
            response longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            idempotency_key varchar(64) DEFAULT NULL,
            created_at datetime NOT NULL,
            finished_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY status (status),
            KEY idempotency_key (idempotency_key)
        ) $charset_collate;";

        // 2. AI Budgets (Token management)
        $table_budgets = $wpdb->prefix . 'ai_budgets';
        $sql_budgets = "CREATE TABLE $table_budgets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scope varchar(32) NOT NULL DEFAULT 'user',
            scope_id varchar(64) NOT NULL,
            period varchar(32) NOT NULL DEFAULT 'monthly',
            token_limit bigint(20) unsigned NOT NULL DEFAULT 0,
            token_used bigint(20) unsigned NOT NULL DEFAULT 0,
            reset_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY scope_id_period (scope, scope_id, period)
        ) $charset_collate;";

        // 3. AI Audit (Usage logging)
        $table_audit = $wpdb->prefix . 'ai_audit';
        $sql_audit = "CREATE TABLE $table_audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id varchar(36) DEFAULT NULL,
            event_type varchar(64) NOT NULL,
            payload_json longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY job_id (job_id),
            KEY event_type (event_type)
        ) $charset_collate;";

        dbDelta( $sql_jobs );
        dbDelta( $sql_budgets );
        dbDelta( $sql_audit );

        update_option( 'kh_editorial_db_version', '1.0.0' );
    }

    /**
     * Drop tables (Rollback).
     */
    public static function down() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ai_jobs" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ai_budgets" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ai_audit" );
        delete_option( 'kh_editorial_db_version' );
    }
}
