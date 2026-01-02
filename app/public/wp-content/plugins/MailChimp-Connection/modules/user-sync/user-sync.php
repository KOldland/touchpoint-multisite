<?php

defined('ABSPATH') or exit;

/**
 * TouchPoint MailChimp User Sync Module
 * 
 * Based on MC4WP User Sync functionality
 * Handles automatic synchronization of WordPress users to MailChimp
 */

// Use existing module class shipped in includes/modules
require_once dirname(__DIR__, 2) . '/includes/modules/class-user-sync.php';

// Initialize User Sync if enabled
$settings = TouchPoint_MailChimp_Settings::instance();

if ($settings->is_user_sync_enabled()) {
    // Class uses singleton pattern; get instance and ensure hooks are set.
    $user_sync = TouchPoint_MailChimp_User_Sync::instance();
    if (method_exists($user_sync, 'init')) {
        $user_sync->init();
    }
}
