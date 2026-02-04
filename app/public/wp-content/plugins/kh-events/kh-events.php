<?php
/**
 * Plugin Name: KH-Events
 * Description: Comprehensive event management plugin for 1927MSuite, integrating event creation, bookings, views, and more with suite-wide compatibility.
 * Version: 1.0.0
 * Author: 1927MSuite
 * Text Domain: kh-events
 * Requires at least: 5.6
 * Tested up to: 6.0
 * Requires PHP: 7.1
 * License: GPLv2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('KH_EVENTS_VERSION', '1.0.0');
define('KH_EVENTS_DIR', plugin_dir_path(__FILE__));
define('KH_EVENTS_PATH', KH_EVENTS_DIR); // Backwards compatibility for older includes.
define('KH_EVENTS_URL', plugin_dir_url(__FILE__));
define('KH_EVENTS_BASENAME', plugin_basename(__FILE__));

/**
 * Detect Elementor/builder preview to avoid loading frontend assets inside the editor.
 *
 * @return bool
 */
function kh_events_is_builder_preview() {
    if ( defined( 'ELEMENTOR_EDITOR' ) && ELEMENTOR_EDITOR ) {
        return true;
    }
    if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return true;
    }
    if ( class_exists( '\Elementor\Plugin' ) ) {
        $plugin = \Elementor\Plugin::$instance;
        if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
            return true;
        }
    }
    return false;
}

// Load composer dependencies if available
if (file_exists(KH_EVENTS_DIR . 'vendor/autoload.php')) {
    require_once KH_EVENTS_DIR . 'vendor/autoload.php';
}

// Safely include core files; bail gracefully if anything is missing to avoid fatals
$kh_events_required_files = array(
    'includes/interface-kh-events-service-provider.php',
    'includes/class-kh-events-service-provider.php',
    'includes/class-kh-events-container.php',
    'includes/kh-events-service-providers.php',
    'includes/class-kh-events.php',
    'includes/class-kh-event-integrations.php',
    'includes/class-kh-event-analytics.php',
    'includes/class-kh-events-email-marketing.php',
    'includes/class-kh-events-enhanced-api.php',
    'includes/class-kh-social-media-integration.php',
    'includes/class-kh-payment-gateways.php',
    'includes/class-kh-event-bookings.php',
    'includes/class-kh-events-views.php',
    'includes/class-kh-events-webhook-manager.php',
    'includes/class-kh-events-integration-manager.php',
    'includes/class-kh-events-analytics.php',
    'includes/class-kh-events-admin-settings.php',
    'includes/class-kh-events-woocommerce-bridge.php',
    'includes/class-kh-events-api-controller.php',
    'includes/class-kh-events-api-auth.php',
    'includes/class-kh-events-feed-generator.php',
    'includes/integrations/class-kh-hubspot-integration.php',
    // Service providers
    'includes/providers/class-kh-events-database-provider.php',
    'includes/providers/class-kh-events-admin-provider.php',
    'includes/providers/class-kh-events-api-provider.php',
    'includes/class-kh-events-table-manager.php',
    'includes/class-kh-events-database.php',
    'includes/class-kh-events-admin.php',
    'includes/class-kh-events-admin-menus.php',
);

foreach ($kh_events_required_files as $relative_path) {
    $full_path = KH_EVENTS_DIR . $relative_path;
    if (!file_exists($full_path)) {
        error_log('KH Events missing required file: ' . $full_path);
        add_action('admin_notices', function() use ($full_path) {
            echo '<div class="notice notice-error"><p>KH Events plugin is missing a required file: <code>' . esc_html($full_path) . '</code>. Please reinstall or restore the plugin.</p></div>';
        });
        return;
    }
    require_once $full_path;
}

// Activation hook
register_activation_hook(__FILE__, array('KH_Events', 'activate'));

// Deactivation hook
register_deactivation_hook(__FILE__, array('KH_Events', 'deactivate'));

// Initialize the plugin with service providers
add_action('plugins_loaded', function() {
    // Register service providers
    kh_events_register_provider('KH_Events_Database_Provider');
    kh_events_register_provider('KH_Events_Admin_Provider');
    kh_events_register_provider('KH_Events_API_Provider');

    // Boot the container and all service providers
    $container = KH_Events_Container::instance();
    $container->boot();

    // Initialize the main plugin class
    KH_Events::instance();
});

// Elementor widget registration
add_action('elementor/widgets/register', function($widgets_manager) {
    if (! class_exists('\Elementor\Widget_Base')) {
        return;
    }

    $widgets = array(
        __DIR__ . '/includes/elementor/EventsWidget.php'      => '\KH_Events_Views_Widget',
        __DIR__ . '/includes/elementor/BookingFormWidget.php' => '\KH_Event_BookingForm_Widget',
    );

    foreach ($widgets as $file => $class) {
        if (file_exists($file)) {
            require_once $file;
        }
        if (class_exists($class)) {
            $widgets_manager->register(new $class());
        }
    }
});
