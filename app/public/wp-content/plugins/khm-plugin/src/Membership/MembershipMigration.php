<?php

namespace KHM\Membership;

class MembershipMigration {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // -- membership tiers
        $table_name = $wpdb->prefix . 'membership_tier';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            $sql = "CREATE TABLE $table_name (
              id SERIAL PRIMARY KEY,
              slug TEXT UNIQUE NOT NULL,
              name TEXT NOT NULL,
              price_cents INT NOT NULL,
              currency CHAR(3) NOT NULL DEFAULT 'GBP',
              billing_interval TEXT NOT NULL DEFAULT 'month',
              trial_days INT DEFAULT 0,
              benefits JSONB DEFAULT '{}'::jsonb,
              is_active BOOLEAN DEFAULT TRUE,
              created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
              updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }

        // -- user membership record
        $table_name = $wpdb->prefix . 'user_membership';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            $sql = "CREATE TABLE $table_name (
              user_id BIGINT PRIMARY KEY,
              tier_id INT,
              stripe_customer_id TEXT,
              stripe_subscription_id TEXT,
              status TEXT NOT NULL DEFAULT 'trialing',
              trial_ends_at TIMESTAMP WITH TIME ZONE,
              started_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
              cancelled_at TIMESTAMP WITH TIME ZONE,
              metadata JSONB DEFAULT '{}'::jsonb,
              created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
              updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
        
        // -- promotion attribution
        $table_name = $wpdb->prefix . 'promotion_attribution';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            $sql = "CREATE TABLE $table_name (
              id BIGSERIAL PRIMARY KEY,
              schedule_id BIGINT NULL,
              sponsor_id BIGINT NULL,
              user_id BIGINT NULL,
              user_email TEXT NULL,
              utm_source TEXT NULL,
              utm_medium TEXT NULL,
              utm_campaign TEXT NULL,
              utm_term TEXT NULL,
              utm_content TEXT NULL,
              phase_at_click TEXT NULL,
              conversion_type TEXT NOT NULL,
              plan_id INT NULL,
              reference_metadata JSONB DEFAULT '{}'::jsonb,
              created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );

            $index_sql = "CREATE INDEX idx_promotion_schedule ON $table_name (schedule_id);";
            $wpdb->query($index_sql);
            $index_sql = "CREATE INDEX idx_promotion_user ON $table_name (user_id);";
            $wpdb->query($index_sql);
        }

        $table_name = $wpdb->prefix . 'user_membership';
        $index_sql = "CREATE INDEX idx_user_membership_status ON $table_name (status);";
        $wpdb->query($index_sql);

        // -- processed webhook events (idempotency + ops)
        $table_name = $wpdb->prefix . 'khm_processed_webhooks';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            $sql = "CREATE TABLE $table_name (
              event_id VARCHAR(255) NOT NULL,
              event_type VARCHAR(128) NOT NULL,
              status VARCHAR(16) NOT NULL DEFAULT 'processing',
              payload LONGTEXT NULL,
              payload_hash CHAR(64) NULL,
              attempts INT UNSIGNED NOT NULL DEFAULT 1,
              notes TEXT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              processed_at DATETIME NULL,
              PRIMARY KEY  (event_id),
              KEY idx_khm_processed_status (status),
              KEY idx_khm_processed_type (event_type)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }

    }
}
