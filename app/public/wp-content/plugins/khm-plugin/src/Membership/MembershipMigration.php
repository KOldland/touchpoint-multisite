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
              status TEXT NOT NULL DEFAULT 'trial',
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

          self::ensure_promotion_attribution_indexes();

        $table_name = $wpdb->prefix . 'user_membership';
        $index_sql = "CREATE INDEX idx_user_membership_status ON $table_name (status);";
        $wpdb->query($index_sql);
        self::ensure_membership_columns();

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

        $table_name = $wpdb->prefix . 'khm_processed_webhook_events';
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
              KEY idx_khm_processed_events_status (status),
              KEY idx_khm_processed_events_type (event_type)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }

        // -- membership webhook audit log
        $table_name = $wpdb->prefix . 'khm_membership_webhook_audit';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            $sql = "CREATE TABLE $table_name (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              event_id VARCHAR(255) NOT NULL,
              event_type VARCHAR(128) NOT NULL,
              operation_key VARCHAR(255) NULL,
              object_id VARCHAR(255) NULL,
              user_id BIGINT UNSIGNED NULL,
              outcome VARCHAR(32) NOT NULL,
              message TEXT NULL,
              context LONGTEXT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_khm_webhook_audit_event (event_id),
              KEY idx_khm_webhook_audit_type (event_type),
              KEY idx_khm_webhook_audit_user (user_id),
              KEY idx_khm_webhook_audit_outcome (outcome),
              KEY idx_khm_webhook_audit_op (operation_key)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }

        // -- membership webhook operations (unique idempotency keys)
        $table_name = $wpdb->prefix . 'khm_membership_webhook_operations';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            $sql = "CREATE TABLE $table_name (
              operation_key VARCHAR(255) NOT NULL,
              event_id VARCHAR(255) NOT NULL,
              event_type VARCHAR(128) NOT NULL,
              object_id VARCHAR(255) NULL,
              user_id BIGINT UNSIGNED NULL,
              status VARCHAR(16) NOT NULL DEFAULT 'processing',
              attempts INT UNSIGNED NOT NULL DEFAULT 1,
              last_error TEXT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              completed_at DATETIME NULL,
              PRIMARY KEY (operation_key),
              KEY idx_khm_wh_op_status (status),
              KEY idx_khm_wh_op_event (event_id),
              KEY idx_khm_wh_op_user (user_id)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }

          // -- membership webhook dead letters
          $table_name = $wpdb->prefix . 'khm_webhook_dead_letter';
          if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            $sql = "CREATE TABLE $table_name (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              event_id VARCHAR(255) NOT NULL,
              event_type VARCHAR(128) NOT NULL,
              payload LONGTEXT NULL,
              payload_hash CHAR(64) NULL,
              reason VARCHAR(64) NOT NULL,
              error_message TEXT NULL,
              status VARCHAR(16) NOT NULL DEFAULT 'open',
              attempts INT UNSIGNED NOT NULL DEFAULT 1,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              resolved_at DATETIME NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uniq_khm_dead_event_reason (event_id, reason),
              KEY idx_khm_dead_status (status),
              KEY idx_khm_dead_type (event_type)
            ) $charset_collate;";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
          }

    }

    private static function ensure_membership_columns(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_membership';
        $columns = [
            'tier_slug' => "ALTER TABLE {$table_name} ADD COLUMN tier_slug VARCHAR(64) NULL",
            'stripe_price_id' => "ALTER TABLE {$table_name} ADD COLUMN stripe_price_id VARCHAR(255) NULL",
            'trial_end_date' => "ALTER TABLE {$table_name} ADD COLUMN trial_end_date DATETIME NULL",
            'current_period_end' => "ALTER TABLE {$table_name} ADD COLUMN current_period_end DATETIME NULL",
            'cancel_at_period_end' => "ALTER TABLE {$table_name} ADD COLUMN cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0",
            'last_payment_date' => "ALTER TABLE {$table_name} ADD COLUMN last_payment_date DATETIME NULL",
        ];

        foreach ( $columns as $column => $sql ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", $column ) );
            if ( ! $exists ) {
                $wpdb->query( $sql );
            }
        }
    }

      private static function ensure_promotion_attribution_indexes(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'promotion_attribution';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
          return;
        }

        self::maybe_create_index( $table_name, 'idx_promotion_schedule', 'schedule_id' );
        self::maybe_create_index( $table_name, 'idx_promotion_user', 'user_id' );
        self::maybe_create_index( $table_name, 'idx_promotion_sponsor', 'sponsor_id' );
        self::maybe_create_index( $table_name, 'idx_promotion_created', 'created_at' );
        self::maybe_create_index( $table_name, 'idx_promotion_conversion', 'conversion_type' );
        self::maybe_create_index( $table_name, 'idx_promotion_user_created', 'user_id, created_at' );
      }

      private static function maybe_create_index( string $table_name, string $index_name, string $columns ): void {
        global $wpdb;

        $existing = $wpdb->get_var(
          $wpdb->prepare(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
            $index_name
          )
        );

        if ( $existing ) {
          return;
        }

        $wpdb->query( "CREATE INDEX {$index_name} ON {$table_name} ({$columns})" );
      }
}
