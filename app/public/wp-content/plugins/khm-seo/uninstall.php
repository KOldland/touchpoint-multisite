<?php
/**
 * Uninstall script for KHM SEO plugin
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
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
    'khm_seo_db_version'
);

foreach ( $options_to_delete as $option ) {
    delete_option( $option );
    
    // For multisite
    delete_site_option( $option );
}

// Delete database tables
global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}khm_seo_posts" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}khm_seo_terms" );

// Clear transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_khm_seo_%' 
     OR option_name LIKE '_transient_timeout_khm_seo_%'"
);

// Clear any cached data
wp_cache_flush();

// Remove scheduled events
wp_clear_scheduled_hook( 'khm_seo_generate_sitemap' );
wp_clear_scheduled_hook( 'khm_seo_cleanup_analysis' );