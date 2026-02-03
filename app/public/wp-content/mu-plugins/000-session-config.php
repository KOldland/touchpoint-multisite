<?php
/**
 * Session Configuration - Must Load First
 * 
 * Configures PHP session save path for local development environment.
 * Named with 000- prefix to ensure it loads before other mu-plugins.
 */

// Set session save path before any plugins try to start sessions
if (!session_id()) {
    $session_path = sys_get_temp_dir() . '/touchpoint_sessions';
    
    // Ensure directory exists with proper permissions
    if (!is_dir($session_path)) {
        @mkdir($session_path, 0755, true);
    }
    
    // Only set session path if directory is writable
    if (is_writable($session_path)) {
        ini_set('session.save_path', $session_path);
        session_save_path($session_path);
    }
}
