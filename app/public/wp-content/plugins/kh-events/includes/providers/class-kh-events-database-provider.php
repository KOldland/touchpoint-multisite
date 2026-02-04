<?php
/**
 * Database Service Provider for KH Events
 *
 * Handles custom database tables and database operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Database_Provider extends KH_Events_Service_Provider {

    /**
     * Register the database services
     */
    public function register() {
        // Bind database services
        $this->bind('kh_events_db', 'KH_Events_Database', true);
        $this->bind('kh_events_table_manager', 'KH_Events_Table_Manager', true);
    }

    /**
     * Boot the database provider
     */
    public function boot() {
        // Create custom tables on plugin activation
        add_action('kh_events_activate', array($this, 'create_custom_tables'));

        // Handle database upgrades
        add_action('plugins_loaded', array($this, 'check_db_version'));
    }

    /**
     * Create custom database tables
     */
    public function create_custom_tables() {
        $table_manager = $this->get('kh_events_table_manager');
        $table_manager->create_tables();
    }

    /**
     * Check database version and upgrade if needed
     */
    public function check_db_version() {
        $current_version = get_option('kh_events_db_version', '1.0.0');
        if (version_compare($current_version, KH_EVENTS_VERSION, '<')) {
            $this->upgrade_database($current_version);
            update_option('kh_events_db_version', KH_EVENTS_VERSION);
        }
    }

    /**
     * Upgrade database schema
     */
    private function upgrade_database($current_version) {
        $table_manager = $this->get('kh_events_table_manager');
        $table_manager->upgrade_tables($current_version);
    }
}