<?php
/**
 * KHM Marketing Suite Helper Functions
 * 
 * Wrapper functions for service methods to maintain backward compatibility
 * while providing access to enhanced credit system functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced credit system wrapper functions
 */

if (!function_exists('khm_download_with_credits')) {
    function khm_download_with_credits(int $post_id, int $user_id): array {
        return \KHM\Services\PluginRegistry::call_service('download_with_credits', $post_id, $user_id);
    }
}

if (!function_exists('khm_generate_article_pdf')) {
    function khm_generate_article_pdf(int $post_id, int $user_id): array {
        return \KHM\Services\PluginRegistry::call_service('generate_article_pdf', $post_id, $user_id);
    }
}

if (!function_exists('khm_create_download_url')) {
    function khm_create_download_url(int $post_id, int $user_id, int $expires_hours = 2): string {
        return \KHM\Services\PluginRegistry::call_service('create_download_url', $post_id, $user_id, $expires_hours);
    }
}

if (!function_exists('khm_get_credit_history')) {
    function khm_get_credit_history(int $user_id, int $limit = 20): array {
        return \KHM\Services\PluginRegistry::call_service('get_credit_history', $user_id, $limit);
    }
}

if (!function_exists('khm_allocate_monthly_credits')) {
    function khm_allocate_monthly_credits(int $user_id): bool {
        return \KHM\Services\PluginRegistry::call_service('allocate_monthly_credits', $user_id);
    }
}

/**
 * Schedule monthly credit allocation cron job
 */
function khm_schedule_monthly_credit_reset() {
    if (!wp_next_scheduled('khm_monthly_credit_reset')) {
        wp_schedule_event(
            strtotime('first day of next month midnight'),
            'monthly',
            'khm_monthly_credit_reset'
        );
    }
}

/**
 * Handle monthly credit reset cron job
 */
function khm_handle_monthly_credit_reset() {
    if (class_exists('\KHM\Services\CreditService')) {
        $credit_service = new \KHM\Services\CreditService(
            new \KHM\Services\MembershipRepository(),
            new \KHM\Services\LevelRepository()
        );
        
        $processed = $credit_service->processMonthlyResets();
        error_log("KHM: Monthly credit reset processed for {$processed} users");
    }
}

// Hook the cron job
add_action('khm_monthly_credit_reset', 'khm_handle_monthly_credit_reset');

/**
 * Initialize credit system on plugin activation
 */
function khm_init_credit_system() {
    // Create database tables
    \KHM\Services\CreditService::createTables();
    
    // Schedule monthly resets
    khm_schedule_monthly_credit_reset();
    
    error_log('KHM: Credit system initialized successfully');
}

// Hook initialization
add_action('khm_plugin_activated', 'khm_init_credit_system');