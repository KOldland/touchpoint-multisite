<?php
/**
 * Database Manager for handling plugin data.
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

namespace KHM_SEO\Utils;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database manager class.
 */
class DatabaseManager {

    /**
     * Initialize the database manager.
     */
    public function __construct() {
        $this->check_database_version();
    }

    /**
     * Check if database needs updating.
     */
    private function check_database_version() {
        $current_version = get_option( 'khm_seo_db_version' );
        $required_version = '1.0.0';

        if ( version_compare( $current_version, $required_version, '<' ) ) {
            $this->update_database();
            update_option( 'khm_seo_db_version', $required_version );
        }
    }

    /**
     * Update database structure.
     */
    private function update_database() {
        $this->create_posts_table();
        $this->create_terms_table();
    }

    /**
     * Create posts meta table.
     */
    private function create_posts_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_seo_posts';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            title text DEFAULT NULL,
            description text DEFAULT NULL,
            keywords text DEFAULT NULL,
            og_title varchar(255) DEFAULT NULL,
            og_description text DEFAULT NULL,
            og_image varchar(500) DEFAULT NULL,
            twitter_title varchar(255) DEFAULT NULL,
            twitter_description text DEFAULT NULL,
            twitter_image varchar(500) DEFAULT NULL,
            robots varchar(100) DEFAULT NULL,
            canonical_url varchar(500) DEFAULT NULL,
            schema_type varchar(100) DEFAULT NULL,
            focus_keyword varchar(255) DEFAULT NULL,
            seo_score int(3) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY seo_score (seo_score),
            KEY focus_keyword (focus_keyword)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Create terms meta table.
     */
    private function create_terms_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_seo_terms';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            term_id bigint(20) unsigned NOT NULL,
            title text DEFAULT NULL,
            description text DEFAULT NULL,
            og_title varchar(255) DEFAULT NULL,
            og_description text DEFAULT NULL,
            og_image varchar(500) DEFAULT NULL,
            twitter_title varchar(255) DEFAULT NULL,
            twitter_description text DEFAULT NULL,
            twitter_image varchar(500) DEFAULT NULL,
            robots varchar(100) DEFAULT NULL,
            canonical_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY term_id (term_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get post SEO data.
     *
     * @param int $post_id Post ID.
     * @return object|null Post SEO data.
     */
    public function get_post_seo_data( $post_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_seo_posts';
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $post_id
        ) );
    }

    /**
     * Save post SEO data.
     *
     * @param int   $post_id Post ID.
     * @param array $data    SEO data.
     * @return bool Success.
     */
    public function save_post_seo_data( $post_id, $data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_seo_posts';
        $existing = $this->get_post_seo_data( $post_id );

        $defaults = array(
            'post_id' => $post_id,
            'title' => null,
            'description' => null,
            'keywords' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'twitter_title' => null,
            'twitter_description' => null,
            'twitter_image' => null,
            'robots' => null,
            'canonical_url' => null,
            'schema_type' => null,
            'focus_keyword' => null,
            'seo_score' => 0
        );

        $data = wp_parse_args( $data, $defaults );

        if ( $existing ) {
            $result = $wpdb->update(
                $table_name,
                $data,
                array( 'post_id' => $post_id ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ),
                array( '%d' )
            );
        } else {
            $result = $wpdb->insert(
                $table_name,
                $data,
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
            );
        }

        return false !== $result;
    }

    /**
     * Get term SEO data.
     *
     * @param int $term_id Term ID.
     * @return object|null Term SEO data.
     */
    public function get_term_seo_data( $term_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_seo_terms';
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE term_id = %d",
            $term_id
        ) );
    }

    /**
     * Save term SEO data.
     *
     * @param int   $term_id Term ID.
     * @param array $data    SEO data.
     * @return bool Success.
     */
    public function save_term_seo_data( $term_id, $data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_seo_terms';
        $existing = $this->get_term_seo_data( $term_id );

        $defaults = array(
            'term_id' => $term_id,
            'title' => null,
            'description' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'twitter_title' => null,
            'twitter_description' => null,
            'twitter_image' => null,
            'robots' => null,
            'canonical_url' => null
        );

        $data = wp_parse_args( $data, $defaults );

        if ( $existing ) {
            $result = $wpdb->update(
                $table_name,
                $data,
                array( 'term_id' => $term_id ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
        } else {
            $result = $wpdb->insert(
                $table_name,
                $data,
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
            );
        }

        return false !== $result;
    }

    /**
     * Delete post SEO data.
     *
     * @param int $post_id Post ID.
     * @return bool Success.
     */
    public function delete_post_seo_data( $post_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_seo_posts';
        
        $result = $wpdb->delete(
            $table_name,
            array( 'post_id' => $post_id ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Delete term SEO data.
     *
     * @param int $term_id Term ID.
     * @return bool Success.
     */
    public function delete_term_seo_data( $term_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_seo_terms';
        
        $result = $wpdb->delete(
            $table_name,
            array( 'term_id' => $term_id ),
            array( '%d' )
        );

        return false !== $result;
    }

    /**
     * Get SEO statistics.
     *
     * @return array Statistics.
     */
    public function get_seo_statistics() {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'khm_seo_posts';
        $terms_table = $wpdb->prefix . 'khm_seo_terms';

        $stats = array(
            'total_posts_optimized' => 0,
            'total_terms_optimized' => 0,
            'average_seo_score' => 0,
            'posts_missing_title' => 0,
            'posts_missing_description' => 0,
            'posts_missing_focus_keyword' => 0
        );

        // Total optimized posts
        $stats['total_posts_optimized'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $posts_table WHERE title IS NOT NULL OR description IS NOT NULL"
        );

        // Total optimized terms
        $stats['total_terms_optimized'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $terms_table WHERE title IS NOT NULL OR description IS NOT NULL"
        );

        // Average SEO score
        $stats['average_seo_score'] = $wpdb->get_var(
            "SELECT AVG(seo_score) FROM $posts_table WHERE seo_score > 0"
        );

        // Posts missing elements
        $stats['posts_missing_title'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p 
             LEFT JOIN $posts_table s ON p.ID = s.post_id 
             WHERE p.post_status = 'publish' AND p.post_type IN ('post', 'page') 
             AND (s.title IS NULL OR s.title = '')"
        );

        $stats['posts_missing_description'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p 
             LEFT JOIN $posts_table s ON p.ID = s.post_id 
             WHERE p.post_status = 'publish' AND p.post_type IN ('post', 'page') 
             AND (s.description IS NULL OR s.description = '')"
        );

        $stats['posts_missing_focus_keyword'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p 
             LEFT JOIN $posts_table s ON p.ID = s.post_id 
             WHERE p.post_status = 'publish' AND p.post_type IN ('post', 'page') 
             AND (s.focus_keyword IS NULL OR s.focus_keyword = '')"
        );

        return $stats;
    }
}