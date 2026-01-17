<?php
/**
 * GEO Answer Cards Database Migration
 *
 * Creates the wp_geo_answer_cards table for storing answer card data
 * in a queryable format for reporting and the Tracker.
 *
 * To run the migration after updating:
 * 1. Access WP CLI or admin interface
 * 2. Run: GeoAnswerCardMigration::create_tables()
 * 3. Run: GeoAnswerCardMigration::migrate_from_postmeta() to migrate existing data
 *
 * The table now includes evidence_json and preferred_summary columns for the
 * evidence-based GEO framework.
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
     * Redirects table name without prefix
     *
     * @var string
     */
    private static $redirects_table = 'geo_redirects';

    /**
     * Redirect clicks table name without prefix
     *
     * @var string
     */
    private static $redirect_clicks_table = 'geo_redirect_clicks';

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
     * Get the redirects table name with prefix.
     *
     * @return string
     */
    public static function get_redirects_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$redirects_table;
    }

    /**
     * Get the redirect clicks table name with prefix.
     *
     * @return string
     */
    public static function get_redirect_clicks_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$redirect_clicks_table;
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
            answer_card_id varchar(128) DEFAULT NULL,
            question varchar(1024) DEFAULT NULL,
            concise_answer longtext,
            key_points longtext,
            citations longtext,
            entities longtext,
            evidence_json longtext,
            preferred_summary text,
            topic_discussed_at longtext,
            expose_in_schema tinyint(1) DEFAULT 1,
            requires_review tinyint(1) DEFAULT 0,
            position int(10) unsigned DEFAULT 0,
            word_count int(10) unsigned DEFAULT 0,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY answer_card_id (answer_card_id),
            KEY post_id (post_id),
            KEY position (position),
            KEY expose_in_schema (expose_in_schema),
            KEY requires_review (requires_review),
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
     * Create the redirects table for storing tracked citation links.
     *
     * This table stores redirect records that map short codes to external URLs,
     * allowing us to track click-through on citations without modifying the
     * publisher's canonical URL.
     *
     * @return bool True on success, false on failure.
     */
    public static function create_redirects_table() {
        global $wpdb;

        $table           = self::get_redirects_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(64) NOT NULL,
            target_url text NOT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            answer_card_id varchar(128) DEFAULT NULL,
            citation_index int(11) DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY post_id (post_id),
            KEY answer_card_id (answer_card_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    /**
     * Create the redirect clicks table for tracking click-through analytics.
     *
     * @return bool True on success, false on failure.
     */
    public static function create_redirect_clicks_table() {
        global $wpdb;

        $table           = self::get_redirect_clicks_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            redirect_id bigint(20) unsigned NOT NULL,
            code varchar(64) NOT NULL,
            user_agent text,
            referer text,
            ip_hash varchar(64) DEFAULT NULL,
            clicked_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY redirect_id (redirect_id),
            KEY code (code),
            KEY clicked_at (clicked_at)
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

        $results['geo_answer_cards']    = self::create_table();
        $results['geo_page_settings']   = self::create_page_settings_table();
        $results['geo_redirects']       = self::create_redirects_table();
        $results['geo_redirect_clicks'] = self::create_redirect_clicks_table();

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
            self::get_redirects_table_name(),
            self::get_redirect_clicks_table_name(),
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

                // Generate answer_card_id if not present
                $answer_card_id = $card['answer_card_id'] ?? self::generate_answer_card_id( $post_id );

                // Check confidence for requires_review flag
                $evidence   = $card['evidence'] ?? array();
                $confidence = isset( $evidence['confidence'] ) ? floatval( $evidence['confidence'] ) : 0.0;
                $requires_review = $confidence < 0.6 ? 1 : 0;

                $result = $wpdb->insert(
                    $table,
                    array(
                        'post_id'            => $post_id,
                        'answer_card_id'     => $answer_card_id,
                        'question'           => $card['question'] ?? '',
                        'concise_answer'     => $answer,
                        'key_points'         => wp_json_encode( $card['key_points'] ?? array() ),
                        'citations'          => wp_json_encode( $card['citations'] ?? array() ),
                        'entities'           => wp_json_encode( $card['entities'] ?? array() ),
                        'evidence_json'      => wp_json_encode( $card['evidence'] ?? array() ),
                        'preferred_summary'  => $card['preferred_summary'] ?? '',
                        'topic_discussed_at' => wp_json_encode( $card['topic_discussed_at'] ?? array() ),
                        'expose_in_schema'   => ! empty( $card['expose_in_schema'] ) && ! $requires_review ? 1 : 0,
                        'requires_review'    => $requires_review,
                        'position'           => $card['position'] ?? 0,
                        'word_count'         => $word_count,
                    ),
                    array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
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

    /**
     * Create a redirect record for tracking citation clicks.
     *
     * @param string   $target_url     The URL to redirect to (publisher canonical URL).
     * @param int|null $post_id        Optional post ID associated with this redirect.
     * @param string|null $answer_card_id Optional answer card ID.
     * @param int|null $citation_index Optional citation index within the card.
     * @param int|null $created_by     Optional user ID who created the redirect.
     * @return string|false The full tracked URL on success, false on failure.
     */
    public static function create_redirect_record( $target_url, $post_id = null, $answer_card_id = null, $citation_index = null, $created_by = null ) {
        global $wpdb;

        // Validate target URL
        $target_url = esc_url_raw( $target_url );
        if ( empty( $target_url ) || ! filter_var( $target_url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        // Only allow https URLs for security
        $scheme = wp_parse_url( $target_url, PHP_URL_SCHEME );
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            return false;
        }

        $table = self::get_redirects_table_name();

        // Check if redirect already exists for this target
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT code FROM {$table} WHERE target_url = %s AND answer_card_id = %s AND citation_index = %d LIMIT 1",
            $target_url,
            $answer_card_id ?? '',
            $citation_index ?? 0
        ) );

        if ( $existing ) {
            return home_url( '/r/' . $existing );
        }

        // Generate unique short code (8 hex chars = 4 bytes)
        $code = bin2hex( random_bytes( 4 ) );

        // Ensure unique code
        $max_attempts = 10;
        $attempts     = 0;
        while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) ) ) {
            $code = bin2hex( random_bytes( 4 ) );
            $attempts++;
            if ( $attempts >= $max_attempts ) {
                return false; // Unlikely but prevent infinite loop
            }
        }

        $result = $wpdb->insert(
            $table,
            array(
                'code'            => $code,
                'target_url'      => $target_url,
                'post_id'         => $post_id,
                'answer_card_id'  => $answer_card_id,
                'citation_index'  => $citation_index,
                'created_by'      => $created_by ?? get_current_user_id(),
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%d', '%d', '%s' )
        );

        if ( false === $result ) {
            return false;
        }

        return home_url( '/r/' . $code );
    }

    /**
     * Lookup a redirect by code.
     *
     * @param string $code The redirect code.
     * @return object|null The redirect record or null if not found.
     */
    public static function get_redirect_by_code( $code ) {
        global $wpdb;

        $table = self::get_redirects_table_name();
        $code  = sanitize_text_field( $code );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE code = %s",
            $code
        ) );
    }

    /**
     * Log a redirect click.
     *
     * @param int    $redirect_id The redirect record ID.
     * @param string $code        The redirect code.
     * @return bool True on success.
     */
    public static function log_redirect_click( $redirect_id, $code ) {
        global $wpdb;

        $table = self::get_redirect_clicks_table_name();

        // Hash the IP for privacy (we don't store raw IPs)
        $ip_hash = null;
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip_hash = hash( 'sha256', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) . wp_salt() );
        }

        $result = $wpdb->insert(
            $table,
            array(
                'redirect_id' => $redirect_id,
                'code'        => $code,
                'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
                'referer'     => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
                'ip_hash'     => $ip_hash,
                'clicked_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        return false !== $result;
    }

    /**
     * Generate a stable answer_card_id for an answer card.
     *
     * Format: AC-<post_id>-<8 hex chars>
     *
     * @param int $post_id The post ID.
     * @return string The generated answer card ID.
     */
    public static function generate_answer_card_id( $post_id ) {
        return sprintf( 'AC-%d-%s', absint( $post_id ), bin2hex( random_bytes( 4 ) ) );
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
