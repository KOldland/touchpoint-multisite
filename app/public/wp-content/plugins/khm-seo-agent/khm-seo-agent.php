<?php
/**
 * Plugin Name: KHM SEO Agent
 * Plugin URI: https://1927magazine.com/
 * Description: Auditable, human-gated SEO Agent powered by Dual-GPT and KHM SEO.
 * Version: 0.1.0
 * Author: KHM Development Team
 * Author URI: https://1927magazine.com/
 * Text Domain: khm-seo-agent
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'KHM_SEO_AGENT_VERSION' ) ) {
    define( 'KHM_SEO_AGENT_VERSION', '0.1.0' );
}

if ( ! defined( 'KHM_SEO_AGENT_PLUGIN_FILE' ) ) {
    define( 'KHM_SEO_AGENT_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'KHM_SEO_AGENT_PLUGIN_DIR' ) ) {
    define( 'KHM_SEO_AGENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'KHM_SEO_AGENT_PLUGIN_URL' ) ) {
    define( 'KHM_SEO_AGENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once KHM_SEO_AGENT_PLUGIN_DIR . 'src/Core/Autoloader.php';

if ( ! function_exists( 'khm_seo_agent' ) ) {
    function khm_seo_agent() {
        return KHM_SEO_AGENT\Core\Plugin::instance();
    }
}

add_action( 'plugins_loaded', 'khm_seo_agent' );
