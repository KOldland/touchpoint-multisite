<?php
/**
 * KHM Global Helper Functions
 * 
 * Provides convenient functions for theme developers and plugin integrations.
 */

if (!function_exists('khm_has_access')) {
    /**
     * Check if a user has access to a resource
     *
     * @param int $user_id User ID (0 for current user)
     * @param int|string|null $resource Post ID or custom resource identifier
     * @param array $options Additional options (required_levels, etc.)
     * @return bool
     */
    function khm_has_access(int $user_id = 0, $resource = null, array $options = []): bool
    {
        static $access_control = null;
        
        if ($access_control === null) {
            if (!class_exists('KHM\\Services\\AccessControlService')) {
                return false;
            }
            
            $membership_repo = new \KHM\Services\MembershipRepository();
            $level_repo      = new \KHM\Services\LevelRepository();
            $access_control  = new \KHM\Services\AccessControlService($membership_repo, $level_repo);
        }
        
        return $access_control->has_access($user_id, $resource, $options);
    }
}

if (!function_exists('khm_get_level_repository')) {
    /**
     * Retrieve shared LevelRepository instance.
     *
     * @return \KHM\Services\LevelRepository|null
     */
    function khm_get_level_repository(): ?\KHM\Services\LevelRepository
    {
        static $repo = null;

        if (!class_exists('KHM\\Services\\LevelRepository')) {
            return null;
        }

        if ($repo === null) {
            $repo = new \KHM\Services\LevelRepository();
        }

        return $repo;
    }
}

if (!function_exists('khm_get_user_memberships')) {
    /**
     * Get all active memberships for a user
     *
     * @param int $user_id User ID (0 for current user)
     * @return array Array of membership objects
     */
    function khm_get_user_memberships(int $user_id = 0): array
    {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return [];
        }
        
        if (!class_exists('KHM\\Services\\MembershipRepository')) {
            return [];
        }
        
        $repo = new \KHM\Services\MembershipRepository();
        return $repo->findActive($user_id);
    }
}

if (!function_exists('khm_user_has_membership')) {
    /**
     * Check if user has a specific membership level
     *
     * @param int $level_id Membership level ID
     * @param int $user_id User ID (0 for current user)
     * @return bool
     */
    function khm_user_has_membership(int $level_id, int $user_id = 0): bool
    {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        if (!class_exists('KHM\\Services\\MembershipRepository')) {
            return false;
        }
        
        $repo = new \KHM\Services\MembershipRepository();
        return $repo->hasAccess($user_id, $level_id);
    }
}

if (!function_exists('khm_protect_post')) {
    /**
     * Protect a post by requiring specific membership levels
     *
     * @param int $post_id Post ID
     * @param array $level_ids Array of membership level IDs
     * @return bool Success
     */
    function khm_protect_post(int $post_id, array $level_ids): bool
    {
        if (!class_exists('KHM\\Services\\AccessControlService')) {
            return false;
        }
        
        $membership_repo = new \KHM\Services\MembershipRepository();
        $access_control = new \KHM\Services\AccessControlService($membership_repo);
        
        return $access_control->protect_post($post_id, $level_ids);
    }
}

if (!function_exists('khm_get_checkout_url')) {
    /**
     * Get checkout URL for a membership level
     *
     * @param int $level_id Membership level ID
     * @param array $args Additional query args
     * @return string
     */
    function khm_get_checkout_url(int $level_id, array $args = []): string
    {
        $args['level_id'] = $level_id;
        
        // Try to find page with [khm_checkout] shortcode
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            's' => '[khm_checkout',
            'posts_per_page' => 1,
        ]);
        
        if (!empty($pages)) {
            $url = get_permalink($pages[0]->ID);
        } else {
            $url = home_url('/checkout/');
        }
        
        return add_query_arg($args, $url);
    }
}

if (!function_exists('khm_get_account_url')) {
    /**
     * Get account page URL
     *
     * @param string $section Account section (overview, memberships, orders, profile)
     * @return string
     */
    function khm_get_account_url(string $section = 'overview'): string
    {
        // Try to find page with [khm_account] shortcode
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            's' => '[khm_account',
            'posts_per_page' => 1,
        ]);
        
        if (!empty($pages)) {
            $url = get_permalink($pages[0]->ID);
        } else {
            $url = home_url('/account/');
        }
        
        if ($section !== 'overview') {
            $url = add_query_arg('section', $section, $url);
        }
        
        return $url;
    }
}

if (!function_exists('khm_get_membership_level')) {
    /**
     * Get membership level details
     *
     * @param int $level_id Level ID
     * @return object|null
     */
    function khm_get_membership_level(int $level_id)
    {
        $repo = khm_get_level_repository();

        return $repo ? $repo->get($level_id, true) : null;
    }
}

if (!function_exists('khm_format_price')) {
    /**
     * Format price with currency symbol
     *
     * @param float $amount
     * @param string $currency Currency code (default: USD)
     * @return string
     */
    function khm_format_price(float $amount, string $currency = 'USD'): string
    {
        $symbol = '$'; // Default to USD
        
        // Add more currency symbols as needed
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'CA$',
            'AUD' => 'A$',
        ];
        
        if (isset($symbols[$currency])) {
            $symbol = $symbols[$currency];
        }
        
        return $symbol . number_format($amount, 2);
    }
}
