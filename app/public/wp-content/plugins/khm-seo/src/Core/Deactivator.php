<?php
/**
 * Plugin deactivation handler.
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
 * Plugin deactivator class.
 */
class Deactivator {

    /**
     * Deactivate the plugin.
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear cache if any
        self::clear_cache();
        
        // Set deactivation timestamp
        update_option( 'khm_seo_deactivated_time', time() );
    }

    /**
     * Clear scheduled events.
     */
    private static function clear_scheduled_events() {
        // Clear sitemap generation
        wp_clear_scheduled_hook( 'khm_seo_generate_sitemap' );
        
        // Clear SEO analysis cleanup
        wp_clear_scheduled_hook( 'khm_seo_cleanup_analysis' );
    }

    /**
     * Clear any cached data.
     */
    private static function clear_cache() {
        // Clear sitemap cache
        delete_transient( 'khm_seo_sitemap_cache' );
        
        // Clear schema cache
        delete_transient( 'khm_seo_schema_cache' );
        
        // Clear any other plugin caches
        wp_cache_delete( 'khm_seo_titles', 'khm_seo' );
        wp_cache_delete( 'khm_seo_descriptions', 'khm_seo' );
    }

    /**
     * Clean up database on uninstall (optional).
     * This method is here for reference but won't be called automatically.
     * Use uninstall.php for actual uninstall cleanup.
     */
    public static function uninstall() {
        global $wpdb;

        // Remove database tables
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}khm_seo_posts" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}khm_seo_terms" );

        // Remove all plugin options
        $options_to_delete = array(
            'khm_seo_general',
            'khm_seo_titles',
            'khm_seo_meta',
            'khm_seo_sitemap',
            'khm_seo_schema',
            'khm_seo_tools',
            'khm_seo_activated_time',
            'khm_seo_deactivated_time',
            'khm_seo_version',
        );

        foreach ( $options_to_delete as $option ) {
            delete_option( $option );
        }

        // Clear all transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_khm_seo_%' 
             OR option_name LIKE '_transient_timeout_khm_seo_%'"
        );
    }
}