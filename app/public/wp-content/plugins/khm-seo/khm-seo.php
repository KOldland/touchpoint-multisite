<?php
/**
 * Plugin Name: KHM SEO
 * Plugin URI: https://1927magazine.com/
 * Description: Complete SEO solution for content marketing and publishing platform. Includes meta optimization, schema markup, XML sitemaps, and content analysis.
 * Version: 1.0.0
 * Author: KHM Development Team
 * Author URI: https://1927magazine.com/
 * Text Domain: khm-seo
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * KHM SEO is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * KHM SEO is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * @package KHM_SEO
 * @version 1.0.0
 * @author  KHM Development Team
 * @license GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
if ( ! defined( 'KHM_SEO_VERSION' ) ) {
    define( 'KHM_SEO_VERSION', '1.0.0' );
}

if ( ! defined( 'KHM_SEO_PLUGIN_FILE' ) ) {
    define( 'KHM_SEO_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'KHM_SEO_PLUGIN_DIR' ) ) {
    define( 'KHM_SEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'KHM_SEO_PLUGIN_URL' ) ) {
    define( 'KHM_SEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'KHM_SEO_PLUGIN_BASENAME' ) ) {
    define( 'KHM_SEO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Check PHP version compatibility
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', 'khm_seo_php_version_notice' );
    return;
}

// Check WordPress version compatibility
global $wp_version;
if ( version_compare( $wp_version, '5.0', '<' ) ) {
    add_action( 'admin_notices', 'khm_seo_wp_version_notice' );
    return;
}

/**
 * Display admin notice for PHP version requirement.
 */
function khm_seo_php_version_notice() {
    echo '<div class="notice notice-error"><p>';
    printf( 
        /* translators: %s: Required PHP version */
        esc_html__( 'KHM SEO requires PHP version %s or higher. Please update your PHP version.', 'khm-seo' ), 
        '7.4' 
    );
    echo '</p></div>';
}

/**
 * Display admin notice for WordPress version requirement.
 */
function khm_seo_wp_version_notice() {
    echo '<div class="notice notice-error"><p>';
    printf( 
        /* translators: %s: Required WordPress version */
        esc_html__( 'KHM SEO requires WordPress version %s or higher. Please update your WordPress installation.', 'khm-seo' ), 
        '5.0' 
    );
    echo '</p></div>';
}

// Require the autoloader
require_once KHM_SEO_PLUGIN_DIR . 'src/Core/Autoloader.php';

// Initialize the plugin
if ( ! function_exists( 'khm_seo' ) ) {
    /**
     * Returns the main instance of KHM_SEO.
     *
     * @return KHM_SEO\Core\Plugin
     */
    function khm_seo() {
        return KHM_SEO\Core\Plugin::instance();
    }
}

// Register activation and deactivation hooks
register_activation_hook( __FILE__, array( 'KHM_SEO\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KHM_SEO\Core\Deactivator', 'deactivate' ) );

// Start the plugin
add_action( 'plugins_loaded', 'khm_seo' );