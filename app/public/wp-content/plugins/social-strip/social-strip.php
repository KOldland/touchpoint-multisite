<?php
/*
* Plugin Name: Social Strip
* Description: Adds a floating or static social share strip as an Elementor widget with unified sharing and gifting functionality.
* Version: 1.2
* Author: Kirsty Hennah
*/

if (!defined('ABSPATH')) exit;

// Initialize the plugin
function kss_init_plugin() {
    // Load KHM integration if available
    $khm_integration = null;
    if (file_exists(__DIR__ . '/includes/khm-integration.php')) {
        require_once __DIR__ . '/includes/khm-integration.php';
        $khm_integration = new KSS_KHM_Integration();
    }
    
    // Load affiliate conversion tracking
    if (file_exists(__DIR__ . '/includes/affiliate-conversion-tracking.php')) {
        require_once __DIR__ . '/includes/affiliate-conversion-tracking.php';
    }
    
    // Load affiliate dashboard
    if (file_exists(__DIR__ . '/includes/affiliate-dashboard.php')) {
        require_once __DIR__ . '/includes/affiliate-dashboard.php';
    }
    
    // Load affiliate test (admin only)
    if (is_admin() && file_exists(__DIR__ . '/includes/affiliate-test.php')) {
        require_once __DIR__ . '/includes/affiliate-test.php';
    }
    
    // Load AJAX handlers
    if (file_exists(__DIR__ . '/includes/class-ajax-handlers.php')) {
        require_once __DIR__ . '/includes/class-ajax-handlers.php';
        new KSS_Ajax_Handlers($khm_integration);
    }
    
    // Load social sharing modal
    if (file_exists(__DIR__ . '/includes/social-sharing-modal.php')) {
        require_once __DIR__ . '/includes/social-sharing-modal.php';
        // Initialize unified modal
        kss_add_unified_modal_to_footer();
    }
}
add_action('init', 'kss_init_plugin');

// Register the Elementor widget
function kss_register_social_strip_widget($widgets_manager) {
    require_once(__DIR__ . '/widgets/class-social-strip-widget.php');
    $widgets_manager->register(new \KSS_Social_Strip_Widget());

    $affiliate_widget = __DIR__ . '/widgets/class-affiliate-dashboard-widget.php';
    if (file_exists($affiliate_widget)) {
        require_once $affiliate_widget;
        if (class_exists('\KSS_Affiliate_Dashboard_Widget')) {
            $widgets_manager->register(new \KSS_Affiliate_Dashboard_Widget());
        }
    }
}
add_action('elementor/widgets/widgets_registered', 'kss_register_social_strip_widget');

// Enqueue plugin assets
function kss_enqueue_social_strip_assets() {
    wp_enqueue_style(
        'kss-social-strip',
        plugin_dir_url(__FILE__) . 'assets/css/social-strip.css',
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'assets/css/social-strip.css')
    );
    
    // Enqueue unified modal styles
    wp_enqueue_style(
        'kss-social-sharing-modal',
        plugin_dir_url(__FILE__) . 'assets/css/social-sharing-modal.css',
        array('kss-social-strip'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/css/social-sharing-modal.css')
    );

    wp_enqueue_script(
        'kss-social-strip',
        plugin_dir_url(__FILE__) . 'assets/js/social-strip.js',
        array('jquery'), // Only if needed
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/social-strip.js'),
        true
    );
    
    // Enqueue unified modal scripts
    wp_enqueue_script(
        'kss-social-sharing-modal',
        plugin_dir_url(__FILE__) . 'assets/js/social-sharing-modal.js',
        array('jquery', 'kss-social-strip'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/social-sharing-modal.js'),
        true
    );
    
    // Localize script for AJAX
    wp_localize_script('kss-social-sharing-modal', 'kssKhm', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kss_modal_nonce'),
        'currentUserId' => get_current_user_id(),
        'isLoggedIn' => is_user_logged_in(),
        'siteUrl' => home_url(),
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ));
}
add_action('wp_enqueue_scripts', 'kss_enqueue_social_strip_assets');
