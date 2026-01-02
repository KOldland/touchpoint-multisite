<?php
/**
 * Affiliate Conversion Tracking
 * 
 * Hooks into purchase events to track affiliate conversions and calculate commissions
 */

if (!defined('ABSPATH')) exit;

/**
 * Initialize affiliate conversion tracking
 */
function kss_init_affiliate_conversion_tracking() {
    // Hook into article purchases
    add_action('kss_article_purchased', 'kss_track_article_purchase_conversion', 10, 4);
    
    // Hook into membership signups if KHM provides hooks
    add_action('khm_membership_activated', 'kss_track_membership_conversion', 10, 3);
    add_action('khm_order_completed', 'kss_track_order_conversion', 10, 2);
    
    // Hook into gift purchases when they're implemented
    add_action('kss_gift_purchased', 'kss_track_gift_conversion', 10, 4);
}
add_action('init', 'kss_init_affiliate_conversion_tracking');

/**
 * Get user ID from affiliate code
 */
function kss_get_affiliate_user_id($affiliate_code) {
    if (!khm_is_marketing_suite_ready()) {
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'kh_affiliate_codes';
    
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$table_name} WHERE code = %s",
        $affiliate_code
    ));
    
    return $user_id ? intval($user_id) : false;
}

/**
 * Track conversion when article is purchased
 *
 * @param int $user_id
 * @param int $post_id
 * @param object $order
 * @param float $final_price
 */
function kss_track_article_purchase_conversion($user_id, $post_id, $order, $final_price) {
    // Check if there's an affiliate code in the session or referrer
    $affiliate_code = kss_get_affiliate_code_from_session();
    
    if (!$affiliate_code) {
        return; // No affiliate referral
    }
    
    // Calculate commission (configurable percentage)
    $commission_rate = get_option('kss_affiliate_commission_rate', 10); // Default 10%
    $commission_amount = ($final_price * $commission_rate) / 100;
    
    // Track the conversion
    if (khm_is_marketing_suite_ready()) {
        $tracked = khm_track_affiliate_conversion(
            $affiliate_code,
            $post_id,
            $commission_amount,
            'article_purchase'
        );
        
        if ($tracked) {
            // Award e-store credits to the affiliate member
            $affiliate_user_id = kss_get_affiliate_user_id($affiliate_code);
            if ($affiliate_user_id) {
                // Convert commission amount to credits (£1 = 1 credit)
                $credits_to_award = floor($commission_amount);
                
                if ($credits_to_award > 0) {
                    khm_add_credits($affiliate_user_id, $credits_to_award, "affiliate_article_{$post_id}");
                    error_log("Affiliate credits awarded: {$affiliate_code} (user {$affiliate_user_id}) earned {$credits_to_award} credits from article {$post_id}");
                }
            }
            
            // Log successful conversion tracking
            error_log("Affiliate conversion tracked: {$affiliate_code} earned £{$commission_amount} from article {$post_id}");
            
            // Clear the affiliate code from session
            kss_clear_affiliate_session();
            
            // Fire hook for additional processing
            do_action('kss_affiliate_conversion_tracked', $affiliate_code, $post_id, $commission_amount, 'article_purchase');
        }
    }
}

/**
 * Track conversion when membership is activated
 *
 * @param int $user_id
 * @param int $level_id
 * @param object $membership
 */
function kss_track_membership_conversion($user_id, $level_id, $membership) {
    $affiliate_code = kss_get_affiliate_code_from_session();
    
    if (!$affiliate_code) {
        return;
    }
    
    // Get membership level details for commission calculation
    if (!khm_is_marketing_suite_ready()) {
        return;
    }
    
    // Get level price for commission calculation
    $level_price = kss_get_membership_level_price($level_id);
    $commission_rate = get_option('kss_affiliate_membership_commission_rate', 25); // Default 25% for memberships
    $commission_amount = ($level_price * $commission_rate) / 100;
    
    $tracked = khm_track_affiliate_conversion(
        $affiliate_code,
        0, // No specific post for membership
        $commission_amount,
        'membership_signup'
    );
    
    if ($tracked) {
        // Award e-store credits to the affiliate member
        $affiliate_user_id = kss_get_affiliate_user_id($affiliate_code);
        if ($affiliate_user_id) {
            // Convert commission amount to credits (£1 = 1 credit)
            $credits_to_award = floor($commission_amount);
            
            if ($credits_to_award > 0) {
                khm_add_credits($affiliate_user_id, $credits_to_award, "affiliate_membership_{$level_id}");
                error_log("Affiliate credits awarded: {$affiliate_code} (user {$affiliate_user_id}) earned {$credits_to_award} credits from membership signup");
            }
        }
        
        error_log("Membership conversion tracked: {$affiliate_code} earned £{$commission_amount} from membership signup");
        kss_clear_affiliate_session();
        do_action('kss_affiliate_conversion_tracked', $affiliate_code, 0, $commission_amount, 'membership_signup');
    }
}

/**
 * Track conversion for general orders
 *
 * @param int $order_id
 * @param object $order
 */
function kss_track_order_conversion($order_id, $order) {
    $affiliate_code = kss_get_affiliate_code_from_session();
    
    if (!$affiliate_code || !isset($order->total)) {
        return;
    }
    
    // Calculate commission based on order type
    $commission_rate = get_option('kss_affiliate_general_commission_rate', 15); // Default 15%
    $commission_amount = ($order->total * $commission_rate) / 100;
    
    if (khm_is_marketing_suite_ready()) {
        $tracked = khm_track_affiliate_conversion(
            $affiliate_code,
            0,
            $commission_amount,
            'order_purchase'
        );
        
        if ($tracked) {
            // Award e-store credits to the affiliate member
            $affiliate_user_id = kss_get_affiliate_user_id($affiliate_code);
            if ($affiliate_user_id) {
                // Convert commission amount to credits (£1 = 1 credit)
                $credits_to_award = floor($commission_amount);
                
                if ($credits_to_award > 0) {
                    khm_add_credits($affiliate_user_id, $credits_to_award, "affiliate_order_{$order_id}");
                    error_log("Affiliate credits awarded: {$affiliate_code} (user {$affiliate_user_id}) earned {$credits_to_award} credits from order {$order_id}");
                }
            }
            
            error_log("Order conversion tracked: {$affiliate_code} earned £{$commission_amount} from order {$order_id}");
            kss_clear_affiliate_session();
            do_action('kss_affiliate_conversion_tracked', $affiliate_code, 0, $commission_amount, 'order_purchase');
        }
    }
}

/**
 * Track conversion when gift is purchased
 *
 * @param int $user_id
 * @param int $post_id
 * @param array $gift_data
 * @param float $price
 */
function kss_track_gift_conversion($user_id, $post_id, $gift_data, $price) {
    $affiliate_code = kss_get_affiliate_code_from_session();
    
    if (!$affiliate_code) {
        return;
    }
    
    $commission_rate = get_option('kss_affiliate_gift_commission_rate', 15); // Default 15% for gifts
    $commission_amount = ($price * $commission_rate) / 100;
    
    if (khm_is_marketing_suite_ready()) {
        $tracked = khm_track_affiliate_conversion(
            $affiliate_code,
            $post_id,
            $commission_amount,
            'gift_purchase'
        );
        
        if ($tracked) {
            // Award e-store credits to the affiliate member
            $affiliate_user_id = kss_get_affiliate_user_id($affiliate_code);
            if ($affiliate_user_id) {
                // Convert commission amount to credits (£1 = 1 credit)
                $credits_to_award = floor($commission_amount);
                
                if ($credits_to_award > 0) {
                    khm_add_credits($affiliate_user_id, $credits_to_award, "affiliate_gift_{$post_id}");
                    error_log("Affiliate credits awarded: {$affiliate_code} (user {$affiliate_user_id}) earned {$credits_to_award} credits from gift {$post_id}");
                }
            }
            
            error_log("Gift conversion tracked: {$affiliate_code} earned £{$commission_amount} from gift {$post_id}");
            kss_clear_affiliate_session();
            do_action('kss_affiliate_conversion_tracked', $affiliate_code, $post_id, $commission_amount, 'gift_purchase');
        }
    }
}

/**
 * Get affiliate code from session/cookies
 */
function kss_get_affiliate_code_from_session() {
    // Check session first
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['kss_affiliate_code'])) {
        return $_SESSION['kss_affiliate_code'];
    }
    
    // Check cookies as fallback
    if (isset($_COOKIE['kss_affiliate_ref'])) {
        return sanitize_text_field($_COOKIE['kss_affiliate_ref']);
    }
    
    // Check URL parameters (for immediate conversions)
    if (isset($_GET['ref'])) {
        return sanitize_text_field($_GET['ref']);
    }
    
    return null;
}

/**
 * Set affiliate code in session when someone clicks an affiliate link
 */
function kss_set_affiliate_session($affiliate_code) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['kss_affiliate_code'] = $affiliate_code;
    
    // Also set a cookie for longer persistence (30 days)
    setcookie('kss_affiliate_ref', $affiliate_code, time() + (30 * 24 * 60 * 60), '/');
}

/**
 * Clear affiliate code from session after conversion
 */
function kss_clear_affiliate_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    unset($_SESSION['kss_affiliate_code']);
    setcookie('kss_affiliate_ref', '', time() - 3600, '/'); // Expire cookie
}

/**
 * Get membership level price for commission calculation
 */
function kss_get_membership_level_price($level_id) {
    // This would need to be implemented based on how membership levels store pricing
    // For now, return a default value
    $default_prices = [
        1 => 29.99, // Basic membership
        2 => 49.99, // Premium membership
        3 => 99.99  // Pro membership
    ];
    
    return $default_prices[$level_id] ?? 39.99;
}

/**
 * Handle affiliate link clicks and set session
 */
function kss_handle_affiliate_link_click() {
    if (isset($_GET['ref']) && !empty($_GET['ref'])) {
        $affiliate_code = sanitize_text_field($_GET['ref']);
        kss_set_affiliate_session($affiliate_code);
        
        // Track the click as well
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (khm_is_marketing_suite_ready()) {
            khm_track_affiliate_click($affiliate_code, $post_id, $visitor_ip, $user_agent);
        }
    }
}
add_action('init', 'kss_handle_affiliate_link_click');

/**
 * Admin settings for affiliate commission rates
 */
function kss_add_affiliate_commission_settings() {
    // Only run in admin
    if (!is_admin()) {
        return;
    }
    
    add_settings_section(
        'kss_affiliate_settings',
        'Affiliate Commission Settings',
        'kss_affiliate_settings_description',
        'general'
    );
    
    // Article commission rate
    add_settings_field(
        'kss_affiliate_commission_rate',
        'Article Commission Rate (%)',
        'kss_commission_rate_field',
        'general',
        'kss_affiliate_settings',
        ['field' => 'kss_affiliate_commission_rate', 'default' => 10]
    );
    
    // Membership commission rate
    add_settings_field(
        'kss_affiliate_membership_commission_rate',
        'Membership Commission Rate (%)',
        'kss_commission_rate_field',
        'general',
        'kss_affiliate_settings',
        ['field' => 'kss_affiliate_membership_commission_rate', 'default' => 25]
    );
    
    // Gift commission rate
    add_settings_field(
        'kss_affiliate_gift_commission_rate',
        'Gift Commission Rate (%)',
        'kss_commission_rate_field',
        'general',
        'kss_affiliate_settings',
        ['field' => 'kss_affiliate_gift_commission_rate', 'default' => 15]
    );
    
    // General commission rate
    add_settings_field(
        'kss_affiliate_general_commission_rate',
        'General Order Commission Rate (%)',
        'kss_commission_rate_field',
        'general',
        'kss_affiliate_settings',
        ['field' => 'kss_affiliate_general_commission_rate', 'default' => 15]
    );
    
    // Register settings
    register_setting('general', 'kss_affiliate_commission_rate');
    register_setting('general', 'kss_affiliate_membership_commission_rate');
    register_setting('general', 'kss_affiliate_gift_commission_rate');
    register_setting('general', 'kss_affiliate_general_commission_rate');
}
add_action('admin_init', 'kss_add_affiliate_commission_settings');

function kss_affiliate_settings_description() {
    echo '<p>Configure commission rates for different types of affiliate conversions.</p>';
}

function kss_commission_rate_field($args) {
    $field = $args['field'];
    $default = $args['default'];
    $value = get_option($field, $default);
    
    echo '<input type="number" name="' . esc_attr($field) . '" value="' . esc_attr($value) . '" min="0" max="100" step="0.1" />';
    echo '<p class="description">Commission percentage for this type of conversion.</p>';
}
?>