<?php
/**
 * GEO Answer Cards Database Migration
 *
 * Creates the wp_geo_answer_cards table for storing answer card data
 * in a queryable format for reporting and the Tracker.
 *
 * @package KHM\Migrations
 */

namespace KHM\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * GEO Answer Card Migration Class
 */
class GeoAnswerCardMigration {

    /**
     * Table name without prefix
     *
     * @var string
     */
    private static $table_name = 'geo_answer_cards';

    /**
     * Get the full table name with WordPress prefix.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }

    /**
     * Check if the table exists.
     *
     * @return bool
     */
    public static function table_exists() {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    /**
     * Create the answer cards table.
     *
     * @return bool True on success, false on failure.
     */
    public static function create_table() {
        global $wpdb;

        $table           = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            question varchar(1024) DEFAULT NULL,
            concise_answer longtext,
            key_points longtext,
            citations longtext,
            entities longtext,
            expose_in_schema tinyint(1) DEFAULT 1,
            position int(10) unsigned DEFAULT 0,
            word_count int(10) unsigned DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY position (position),
            KEY expose_in_schema (expose_in_schema),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Verify table was created
        return self::table_exists();
    }

    /**
     * Create the page settings table for storing page-level GEO configuration.
     *
     * @return bool True on success, false on failure.
     */
    public static function create_page_settings_table() {
        global $wpdb;

        $table           = $wpdb->prefix . 'geo_page_settings';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            enable_jsonld tinyint(1) DEFAULT 1,
            enable_faqpage tinyint(1) DEFAULT 1,
            primary_entity varchar(512) DEFAULT NULL,
            target_queries longtext,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    /**
     * Create all GEO-related tables.
     *
     * @return array Results of table creation.
     */
    public static function create_tables() {
        $results = array();

        $results['geo_answer_cards']  = self::create_table();
        $results['geo_page_settings'] = self::create_page_settings_table();

        return $results;
    }

    /**
     * Drop the answer cards table.
     *
     * @return bool
     */
    public static function drop_table() {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->query( "DROP TABLE IF EXISTS {$table}" ) !== false;
    }

    /**
     * Drop all GEO-related tables.
     *
     * @return bool
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            self::get_table_name(),
            $wpdb->prefix . 'geo_page_settings',
        );

        $success = true;
        foreach ( $tables as $table ) {
            if ( $wpdb->query( "DROP TABLE IF EXISTS {$table}" ) === false ) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Migrate existing postmeta data to the table.
     *
     * This is useful when the table is created after posts already have
     * answer cards stored in postmeta.
     *
     * @param int $batch_size Number of posts to process per batch.
     * @return array Migration statistics.
     */
    public static function migrate_from_postmeta( $batch_size = 100 ) {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return array(
                'success'  => false,
                'error'    => 'Table does not exist',
                'migrated' => 0,
            );
        }

        $table = self::get_table_name();

        // Get all posts with answer cards in postmeta
        $post_ids = $wpdb->get_col(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_geo_answercards' LIMIT {$batch_size}"
        );

        $migrated = 0;
        $errors   = array();

        foreach ( $post_ids as $post_id ) {
            $cards = get_post_meta( $post_id, '_geo_answercards', true );

            if ( empty( $cards ) || ! is_array( $cards ) ) {
                continue;
            }

            // Delete existing cards for this post in the table
            $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );

            foreach ( $cards as $card ) {
                $answer     = $card['concise_answer'] ?? '';
                $word_count = str_word_count( strip_tags( $answer ) );

                $result = $wpdb->insert(
                    $table,
                    array(
                        'post_id'          => $post_id,
                        'question'         => $card['question'] ?? '',
                        'concise_answer'   => $answer,
                        'key_points'       => wp_json_encode( $card['key_points'] ?? array() ),
                        'citations'        => wp_json_encode( $card['citations'] ?? array() ),
                        'entities'         => wp_json_encode( $card['entities'] ?? array() ),
                        'expose_in_schema' => ! empty( $card['expose_in_schema'] ) ? 1 : 0,
                        'position'         => $card['position'] ?? 0,
                        'word_count'       => $word_count,
                    ),
                    array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
                );

                if ( false === $result ) {
                    $errors[] = array(
                        'post_id' => $post_id,
                        'error'   => $wpdb->last_error,
                    );
                } else {
                    $migrated++;
                }
            }
        }

        return array(
            'success'       => empty( $errors ),
            'migrated'      => $migrated,
            'posts_checked' => count( $post_ids ),
            'errors'        => $errors,
            'has_more'      => count( $post_ids ) >= $batch_size,
        );
    }

    /**
     * Get answer cards count statistics.
     *
     * @return array Statistics array.
     */
    public static function get_statistics() {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return array(
                'table_exists' => false,
            );
        }

        $table = self::get_table_name();

        $total_cards = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $total_posts = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$table}" );
        $exposed     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE expose_in_schema = 1" );
        $avg_words   = (float) $wpdb->get_var( "SELECT AVG(word_count) FROM {$table}" );

        return array(
            'table_exists' => true,
            'total_cards'  => $total_cards,
            'total_posts'  => $total_posts,
            'exposed'      => $exposed,
            'hidden'       => $total_cards - $exposed,
            'avg_words'    => round( $avg_words, 1 ),
        );
    }
}

/**
 * Hook to run migration on plugin activation.
 *
 * @return void
 */
function khm_geo_maybe_create_tables() {
    // Only run if tables don't exist
    if ( ! GeoAnswerCardMigration::table_exists() ) {
        GeoAnswerCardMigration::create_tables();
    }
}

// Register activation hook (should be called from main plugin file)
// register_activation_hook( __FILE__, __NAMESPACE__ . '\\khm_geo_maybe_create_tables' );

/**
 * Admin action to manually run migration.
 *
 * @return void
 */
function khm_geo_admin_migrate_action() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    check_admin_referer( 'khm_geo_migrate_tables' );

    $results = GeoAnswerCardMigration::create_tables();

    if ( $results['geo_answer_cards'] && $results['geo_page_settings'] ) {
        // Also migrate existing data
        $migrate_result = GeoAnswerCardMigration::migrate_from_postmeta();

        add_settings_error(
            'khm_geo_migration',
            'migration_success',
            sprintf(
                /* translators: %d: number of migrated cards */
                __( 'GEO tables created successfully. Migrated %d answer cards from existing posts.', 'khm-membership' ),
                $migrate_result['migrated']
            ),
            'success'
        );
    } else {
        add_settings_error(
            'khm_geo_migration',
            'migration_failed',
            __( 'Failed to create GEO tables. Check error logs.', 'khm-membership' ),
            'error'
        );
    }

    // Redirect back
    wp_safe_redirect( wp_get_referer() ?: admin_url( 'tools.php' ) );
    exit;
}
add_action( 'admin_post_khm_geo_migrate_tables', __NAMESPACE__ . '\\khm_geo_admin_migrate_action' );
