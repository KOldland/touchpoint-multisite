<?php
// Debug session startup issues
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

error_log('=== SESSION DEBUG START ===');
error_log('Session status: ' . session_status());
error_log('Session ID before: ' . session_id());

// Set session path before loading WordPress
$session_path = sys_get_temp_dir() . '/touchpoint_sessions';
error_log('Session path: ' . $session_path);
error_log('Path writable: ' . (is_writable($session_path) ? 'yes' : 'no'));

if (!session_id()) {
    error_log('Attempting to start session...');
    try {
        session_save_path($session_path);
        session_start();
        error_log('Session started successfully. ID: ' . session_id());
    } catch (Exception $e) {
        error_log('Session failed: ' . $e->getMessage());
    }
}

error_log('Session ID after: ' . session_id());
error_log('=== SESSION DEBUG END ===');

// Now load WordPress
require_once(__DIR__ . '/wp-load.php');
?>
