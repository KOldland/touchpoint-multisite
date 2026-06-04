<?php
namespace KH\Attribution\Database;

/**
 * AttributionSchema
 * 
 * Ensures the necessary database tables for the attribution system are created.
 */
class AttributionSchema {
    public static function ensure_tables_exist() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. Attribution events table
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $sql_events = "CREATE TABLE $table_events (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            click_id varchar(100) NOT NULL,
            affiliate_id bigint(20) NOT NULL,
            session_id varchar(100) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            utm_source varchar(100) DEFAULT NULL,
            utm_medium varchar(100) DEFAULT NULL,
            utm_campaign varchar(200) DEFAULT NULL,
            utm_content varchar(200) DEFAULT NULL,
            utm_term varchar(200) DEFAULT NULL,
            target_url text NOT NULL,
            referrer_url text,
            landing_page text,
            ip_address varchar(45),
            user_agent text,
            screen_resolution varchar(20) DEFAULT NULL,
            browser_language varchar(10) DEFAULT NULL,
            timezone varchar(50) DEFAULT NULL,
            fingerprint_hash varchar(64) DEFAULT NULL,
            client_data text,
            utm_params text,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            attribution_method varchar(50) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY click_id (click_id),
            KEY idx_session_user_lookup (session_id, user_id, created_at),
            KEY idx_affiliate_performance (affiliate_id, created_at),
            KEY idx_utm_analysis (utm_source, utm_medium, created_at),
            KEY idx_expiration_cleanup (expires_at)
        ) $charset_collate;";
        
        // 2. Conversion attribution table
        $table_conv_attr = $wpdb->prefix . 'khm_conversion_attribution';
        $sql_conv_attr = "CREATE TABLE $table_conv_attr (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            conversion_id varchar(100) NOT NULL,
            click_id varchar(100),
            affiliate_id bigint(20) NOT NULL,
            attribution_method varchar(50) NOT NULL,
            attribution_confidence decimal(5,4) NOT NULL DEFAULT 1.0000,
            conversion_value decimal(10,2) NOT NULL,
            commission_amount decimal(10,2),
            currency varchar(3) DEFAULT 'USD',
            attribution_data text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY conversion_id (conversion_id),
            KEY click_id (click_id),
            KEY affiliate_id (affiliate_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 3. Conversion tracking table
        $table_conv_track = $wpdb->prefix . 'khm_conversion_tracking';
        $sql_conv_track = "CREATE TABLE $table_conv_track (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id varchar(100) NOT NULL,
            click_id varchar(100),
            affiliate_id bigint(20),
            order_value decimal(10,2) NOT NULL DEFAULT 0.00,
            commission_amount decimal(10,2) DEFAULT NULL,
            commission_rate decimal(5,4) DEFAULT NULL,
            attribution_method varchar(50) DEFAULT NULL,
            attribution_confidence decimal(5,4) DEFAULT NULL,
            attribution_explanation text,
            multi_touch_data longtext,
            currency varchar(3) DEFAULT 'USD',
            processed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY idx_attribution_lookup (click_id, status),
            KEY idx_affiliate_commissions (affiliate_id, created_at, status),
            KEY idx_order_tracking (order_id, processed_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        \dbDelta($sql_events);
        \dbDelta($sql_conv_attr);
        \dbDelta($sql_conv_track);
    }
}
