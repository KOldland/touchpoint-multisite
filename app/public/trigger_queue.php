<?php
define('ABSPATH', __DIR__ . '/');
require_once 'wp-load.php';

// Allow execution from WP-CLI without additional checks.
$is_wp_cli = defined('WP_CLI') && WP_CLI;

if ( ! $is_wp_cli ) {
    // Web context: require logged-in admin with valid nonce.
    if ( ! function_exists('is_user_logged_in') || ! function_exists('current_user_can') ) {
        if ( function_exists('status_header') ) {
            status_header(403);
        }
        exit('Forbidden: WordPress authentication functions unavailable.');
    }

    if ( ! is_user_logged_in() || ! current_user_can('manage_options') ) {
        if ( function_exists('status_header') ) {
            status_header(403);
        }
        exit('Forbidden: insufficient permissions to trigger the queue.');
    }

    $nonce = '';
    if ( isset($_REQUEST['_wpnonce']) ) {
        if ( function_exists('wp_unslash') ) {
            $raw_nonce = wp_unslash($_REQUEST['_wpnonce']);
        } else {
            $raw_nonce = $_REQUEST['_wpnonce'];
        }
        if ( function_exists('sanitize_text_field') ) {
            $nonce = sanitize_text_field($raw_nonce);
        } else {
            $nonce = $raw_nonce;
        }
    }

    if ( empty($nonce) || ! function_exists('wp_verify_nonce') || ! wp_verify_nonce($nonce, 'kh_smma_trigger_queue') ) {
        if ( function_exists('status_header') ) {
            status_header(403);
        }
        exit('Forbidden: invalid or missing nonce.');
    }
}

echo 'Manually triggering kh_smma_process_queue...' . PHP_EOL;
do_action('kh_smma_process_queue');
echo 'Queue processing triggered.' . PHP_EOL;
