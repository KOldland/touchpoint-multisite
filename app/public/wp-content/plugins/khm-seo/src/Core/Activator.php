<?php
/**
 * Plugin activation handler.
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

namespace KHM_SEO\Core;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin activator class.
 */
class Activator {

    /**
     * Activate the plugin.
     */
    public static function activate() {
        // Create database tables
        self::create_database_tables();
        
        // Set default options
        self::set_default_options();
        
        // Flush rewrite rules for sitemap
        flush_rewrite_rules();
        
        // Schedule events if needed
        self::schedule_events();
        
        // Set activation timestamp
        update_option( 'khm_seo_activated_time', time() );
        
        // Set plugin version
        update_option( 'khm_seo_version', KHM_SEO_VERSION );
    }

    /**
     * Create database tables.
     */
    private static function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create entity tables for GEO functionality
        self::create_entity_tables();

        // Posts meta table
        $posts_table = $wpdb->prefix . 'khm_seo_posts';
        $posts_sql = "CREATE TABLE $posts_table (
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

        // Terms meta table
        $terms_table = $wpdb->prefix . 'khm_seo_terms';
        $terms_sql = "CREATE TABLE $terms_table (
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
        dbDelta( $posts_sql );
        dbDelta( $terms_sql );
    }

    /**
     * Create entity tables for GEO functionality.
     */
    private static function create_entity_tables() {
        // Use the EntityTables class to create GEO tables
        require_once KHM_SEO_PLUGIN_DIR . 'src/GEO/Database/EntityTables.php';
        
        $entity_tables = new \KHM_SEO\GEO\Database\EntityTables();
        $entity_tables->install_tables();
    }

    /**
     * Set default options.
     */
    private static function set_default_options() {
        $default_options = array(
            'khm_seo_general' => array(
                'separator'               => '|',
                'home_title'             => '',
                'home_description'       => '',
                'site_name'              => get_bloginfo( 'name' ),
                'company_or_person'      => 'company',
                'company_name'           => get_bloginfo( 'name' ),
                'company_logo'           => '',
                'person_name'            => '',
                'social_profiles'        => array(),
            ),
            'khm_seo_titles' => array(
                'enable_title_rewrite'   => true,
                'post_title_format'      => '%title% %sep% %sitename%',
                'page_title_format'      => '%title% %sep% %sitename%',
                'category_title_format'  => '%term_title% %sep% %sitename%',
                'tag_title_format'       => '%term_title% %sep% %sitename%',
                'author_title_format'    => '%author% %sep% %sitename%',
                'date_title_format'      => '%date% %sep% %sitename%',
                'search_title_format'    => '%search_term% %sep% %sitename%',
                '404_title_format'       => '404 Not Found %sep% %sitename%',
            ),
            'khm_seo_meta' => array(
                'enable_meta_description' => true,
                'auto_generate_descriptions' => true,
                'description_length'      => 160,
                'enable_og_tags'         => true,
                'enable_twitter_cards'   => true,
                'twitter_site'           => '',
            ),
            'khm_seo_sitemap' => array(
                'enable_xml_sitemap'     => true,
                'include_posts'          => true,
                'include_pages'          => true,
                'include_categories'     => true,
                'include_tags'           => true,
                'include_authors'        => false,
                'include_images'         => true,
                'sitemap_posts_limit'    => 50000,
            ),
            'khm_seo_schema' => array(
                'enable_schema'          => true,
                'default_article_type'   => 'Article',
                'enable_breadcrumbs'     => true,
                'enable_organization'    => true,
                'enable_website'         => true,
            ),
            'khm_seo_tools' => array(
                'enable_robots_txt'      => false,
                'robots_txt_content'     => '',
                'google_verification'    => '',
                'bing_verification'      => '',
                'pinterest_verification' => '',
            ),
        );

        foreach ( $default_options as $option_name => $option_value ) {
            if ( ! get_option( $option_name ) ) {
                add_option( $option_name, $option_value );
            }
        }
    }

    /**
     * Schedule plugin events.
     */
    private static function schedule_events() {
        // Schedule sitemap generation
        if ( ! wp_next_scheduled( 'khm_seo_generate_sitemap' ) ) {
            wp_schedule_event( time(), 'daily', 'khm_seo_generate_sitemap' );
        }
        
        // Schedule SEO analysis cleanup
        if ( ! wp_next_scheduled( 'khm_seo_cleanup_analysis' ) ) {
            wp_schedule_event( time(), 'weekly', 'khm_seo_cleanup_analysis' );
        }
    }
}