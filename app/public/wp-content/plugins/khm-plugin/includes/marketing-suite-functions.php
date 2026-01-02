<?php
/**
 * Marketing Suite Integration Functions
 *
 * Global functions that other plugins can use to interact with KHM
 */

if (!function_exists('khm_register_plugin')) {
    /**
     * Register a marketing suite plugin with KHM
     *
     * @param string $plugin_slug
     * @param array $config
     * @return bool
     */
    function khm_register_plugin(string $plugin_slug, array $config): bool {
        if (!class_exists('KHM\\Services\\PluginRegistry')) {
            return false;
        }
        
        return KHM\Services\PluginRegistry::register_plugin($plugin_slug, $config);
    }
}

if (!function_exists('khm_call_service')) {
    /**
     * Call a KHM service from external plugins
     *
     * @param string $service_name
     * @param mixed ...$args
     * @return mixed
     */
    function khm_call_service(string $service_name, ...$args) {
        if (!class_exists('KHM\\Services\\PluginRegistry')) {
            throw new Exception('KHM Plugin Registry not available');
        }
        
        return KHM\Services\PluginRegistry::call_service($service_name, ...$args);
    }
}

if (!function_exists('khm_is_marketing_suite_ready')) {
    /**
     * Check if KHM marketing suite is ready for integration
     *
     * @return bool
     */
    function khm_is_marketing_suite_ready(): bool {
        return class_exists('KHM\\Services\\PluginRegistry') && 
               class_exists('KHM\\Services\\MarketingSuiteServices');
    }
}

// Convenience functions for common operations

if (!function_exists('khm_get_user_membership')) {
    /**
     * Get user's active membership
     *
     * @param int $user_id
     * @return object|null
     */
    function khm_get_user_membership(int $user_id): ?object {
        try {
            return khm_call_service('get_user_membership', $user_id);
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('khm_check_user_access')) {
    /**
     * Check if user has access to specific content/feature
     *
     * @param int $user_id
     * @param string $access_type
     * @param array $params
     * @return bool
     */
    function khm_check_user_access(int $user_id, string $access_type, array $params = []): bool {
        try {
            return khm_call_service('check_user_access', $user_id, $access_type, $params);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('khm_get_member_discount')) {
    /**
     * Get member discount for a price
     *
     * @param int $user_id
     * @param float $original_price
     * @param string $item_type
     * @return array
     */
    function khm_get_member_discount(int $user_id, float $original_price, string $item_type = 'general'): array {
        try {
            return khm_call_service('get_member_discount', $user_id, $original_price, $item_type);
        } catch (Exception $e) {
            return [
                'discounted_price' => $original_price,
                'discount_percent' => 0,
                'discount_amount' => 0
            ];
        }
    }
}

if (!function_exists('khm_get_user_credits')) {
    /**
     * Get user's credit balance
     *
     * @param int $user_id
     * @return int
     */
    function khm_get_user_credits(int $user_id): int {
        try {
            return khm_call_service('get_user_credits', $user_id);
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('khm_use_credit')) {
    /**
     * Use a credit for a user
     *
     * @param int $user_id
     * @param string $reason
     * @return bool
     */
    function khm_use_credit(int $user_id, string $reason = 'download'): bool {
        try {
            return khm_call_service('use_credit', $user_id, $reason);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('khm_create_external_order')) {
    /**
     * Create an order from external plugins
     *
     * @param array $order_data
     * @return object|false
     */
    function khm_create_external_order(array $order_data) {
        try {
            return khm_call_service('create_order', $order_data);
        } catch (Exception $e) {
            return false;
        }
    }
}

// PDF & Download Helper Functions

if (!function_exists('khm_generate_article_pdf')) {
    /**
     * Generate PDF for an article
     *
     * @param int $post_id
     * @param int $user_id
     * @return array
     */
    function khm_generate_article_pdf(int $post_id, int $user_id): array {
        try {
            return khm_call_service('generate_article_pdf', $post_id, $user_id);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to generate PDF: ' . $e->getMessage()
            ];
        }
    }
}

if (!function_exists('khm_create_download_url')) {
    /**
     * Create secure download URL for an article
     *
     * @param int $post_id
     * @param int $user_id
     * @param int $expires_hours
     * @return string
     */
    function khm_create_download_url(int $post_id, int $user_id, int $expires_hours = 2): string {
        try {
            return khm_call_service('create_download_url', $post_id, $user_id, $expires_hours);
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('khm_download_with_credits')) {
    /**
     * Download article using credits
     *
     * @param int $post_id
     * @param int $user_id
     * @return array
     */
    function khm_download_with_credits(int $post_id, int $user_id): array {
        try {
            return khm_call_service('download_with_credits', $post_id, $user_id);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to process download: ' . $e->getMessage(),
                'credits_remaining' => 0
            ];
        }
    }
}