<?php
/**
 * KH Events API AJAX Handlers
 *
 * Handles AJAX requests for API management in admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test API connection
 */
function kh_events_api_test_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    try {
        // Test basic API functionality
        $api_provider = kh_events_get_service('api_provider');
        $test_result = $api_provider->test_connection();

        if ($test_result) {
            wp_send_json_success(array(
                'message' => __('API connection test successful')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('API connection test failed')
            ));
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_api_test', 'kh_events_api_test_ajax');

/**
 * Test webhook delivery
 */
function kh_events_webhook_test_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    $webhook_id = isset($_POST['webhook_id']) ? intval($_POST['webhook_id']) : 0;

    if (!$webhook_id) {
        wp_send_json_error(array(
            'message' => __('Invalid webhook ID')
        ));
        return;
    }

    try {
        $webhook_manager = kh_events_get_service('webhook_manager');
        $result = $webhook_manager->test_webhook($webhook_id);

        if ($result['success']) {
            wp_send_json_success(array(
                'response_code' => $result['response_code'],
                'message' => __('Webhook test successful')
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error']
            ));
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_webhook_test', 'kh_events_webhook_test_ajax');

/**
 * Activate integration
 */
function kh_events_activate_integration_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    $integration_id = isset($_POST['integration_id']) ? sanitize_key($_POST['integration_id']) : '';

    if (!$integration_id) {
        wp_send_json_error(array(
            'message' => __('Invalid integration ID')
        ));
        return;
    }

    try {
        $integration_manager = kh_events_get_service('integration_manager');
        $result = $integration_manager->activate_integration($integration_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Integration activated successfully')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to activate integration')
            ));
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_activate_integration', 'kh_events_activate_integration_ajax');

/**
 * Deactivate integration
 */
function kh_events_deactivate_integration_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    $integration_id = isset($_POST['integration_id']) ? sanitize_key($_POST['integration_id']) : '';

    if (!$integration_id) {
        wp_send_json_error(array(
            'message' => __('Invalid integration ID')
        ));
        return;
    }

    try {
        $integration_manager = kh_events_get_service('integration_manager');
        $result = $integration_manager->deactivate_integration($integration_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Integration deactivated successfully')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to deactivate integration')
            ));
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_deactivate_integration', 'kh_events_deactivate_integration_ajax');

/**
 * Get API logs
 */
function kh_events_get_api_logs_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kh_events_api_logs';

        $logs = $wpdb->get_results(
            "SELECT timestamp, method, endpoint, ip_address, response_code
             FROM {$table_name}
             ORDER BY timestamp DESC
             LIMIT 100",
            ARRAY_A
        );

        if ($logs === false) {
            wp_send_json_error(array(
                'message' => __('Database query failed')
            ));
            return;
        }

        wp_send_json_success(array(
            'logs' => $logs
        ));
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_get_api_logs', 'kh_events_get_api_logs_ajax');

/**
 * Clear API logs
 */
function kh_events_clear_api_logs_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kh_events_api_logs';

        $result = $wpdb->query("DELETE FROM {$table_name}");

        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Failed to clear logs')
            ));
        } else {
            wp_send_json_success(array(
                'message' => sprintf(__('Cleared %d log entries'), $result)
            ));
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_clear_api_logs', 'kh_events_clear_api_logs_ajax');

/**
 * Get integration sync status
 */
function kh_events_get_integration_sync_status_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    $integration_id = isset($_POST['integration_id']) ? sanitize_key($_POST['integration_id']) : '';

    if (!$integration_id) {
        wp_send_json_error(array(
            'message' => __('Invalid integration ID')
        ));
        return;
    }

    try {
        $integration_manager = kh_events_get_service('integration_manager');
        $status = $integration_manager->get_sync_status($integration_id);

        wp_send_json_success(array(
            'status' => $status
        ));
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_get_integration_sync_status', 'kh_events_get_integration_sync_status_ajax');

/**
 * Sync integration data
 */
function kh_events_sync_integration_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    $integration_id = isset($_POST['integration_id']) ? sanitize_key($_POST['integration_id']) : '';

    if (!$integration_id) {
        wp_send_json_error(array(
            'message' => __('Invalid integration ID')
        ));
        return;
    }

    try {
        $integration_manager = kh_events_get_service('integration_manager');
        $result = $integration_manager->sync_integration($integration_id);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'data' => $result['data']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error']
            ));
        }
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_sync_integration', 'kh_events_sync_integration_ajax');

/**
 * Generate new API key
 */
function kh_events_generate_api_key_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    try {
        $new_key = wp_generate_password(32, false);

        // Update the setting
        $settings = get_option('kh_events_api_settings', array());
        $settings['api_key'] = $new_key;
        update_option('kh_events_api_settings', $settings);

        wp_send_json_success(array(
            'api_key' => $new_key,
            'message' => __('New API key generated successfully')
        ));
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_generate_api_key', 'kh_events_generate_api_key_ajax');

/**
 * Get API statistics
 */
function kh_events_get_api_stats_ajax() {
    check_ajax_referer('kh_events_api_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions'));
    }

    try {
        global $wpdb;

        $table_name = $wpdb->prefix . 'kh_events_api_logs';

        // Get stats for the last 30 days
        $stats = array(
            'total_requests' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'successful_requests' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE response_code BETWEEN 200 AND 299 AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'failed_requests' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE response_code >= 400 AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
            'top_endpoints' => $wpdb->get_results(
                "SELECT endpoint, COUNT(*) as count FROM {$table_name} WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY endpoint ORDER BY count DESC LIMIT 5",
                ARRAY_A
            ),
            'requests_by_day' => $wpdb->get_results(
                "SELECT DATE(timestamp) as date, COUNT(*) as count FROM {$table_name} WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(timestamp) ORDER BY date",
                ARRAY_A
            )
        );

        wp_send_json_success(array(
            'stats' => $stats
        ));
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}
add_action('wp_ajax_kh_events_get_api_stats', 'kh_events_get_api_stats_ajax');