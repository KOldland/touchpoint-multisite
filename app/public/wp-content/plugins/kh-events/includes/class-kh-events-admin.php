<?php
/**
 * KH Events Admin
 *
 * Main admin class for the service provider pattern
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_Admin {

    /**
     * Initialize admin functionality
     */
    public function init() {
        // This class serves as a bridge between the service provider and existing admin functionality
        // The actual admin logic is handled by the provider
    }

    /**
     * Get admin URL for a specific page
     */
    public function get_admin_url($page = 'kh-events') {
        return admin_url('admin.php?page=' . $page);
    }

    /**
     * Check if current page is KH Events admin page
     */
    public function is_kh_events_page() {
        global $pagenow;

        if ($pagenow !== 'admin.php') {
            return false;
        }

        $page = isset($_GET['page']) ? $_GET['page'] : '';

        return strpos($page, 'kh-events') === 0;
    }

    /**
     * Add admin notice
     */
    public function add_notice($message, $type = 'success', $dismissible = true) {
        $class = 'notice notice-' . $type;
        if ($dismissible) {
            $class .= ' is-dismissible';
        }

        add_action('admin_notices', function() use ($message, $class) {
            echo '<div class="' . esc_attr($class) . '">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        });
    }
}