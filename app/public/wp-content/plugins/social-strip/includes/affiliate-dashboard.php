<?php
/**
 * Affiliate Dashboard
 * 
 * Provides member interface for viewing affiliate performance and managing codes
 */

if (!defined('ABSPATH')) exit;

/**
 * Add affiliate dashboard to member account
 */
function kss_add_affiliate_dashboard() {
    // Only show to logged-in users who are members
    if (!is_user_logged_in() || !khm_is_marketing_suite_ready()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $user = get_user_by('ID', $user_id);
    
    // Check if user has affiliate permissions (implement your own logic here)
    if (!kss_user_can_be_affiliate($user_id)) {
        return;
    }
    
    // Get user's affiliate code
    $affiliate_code = kss_get_user_affiliate_code($user_id);
    if (!$affiliate_code) {
        $affiliate_code = kss_generate_user_affiliate_code($user_id);
    }
    
    // Get performance data
    $performance = kss_get_affiliate_performance($affiliate_code);
    
    // Render dashboard
    kss_render_affiliate_dashboard($affiliate_code, $performance);
}

/**
 * Check if user can be an affiliate
 */
function kss_user_can_be_affiliate($user_id) {
    // For now, allow all logged-in users
    // You could check membership levels, roles, etc.
    return true;
}

/**
 * Get or generate affiliate code for user
 */
function kss_get_user_affiliate_code($user_id) {
    $affiliate_code = get_user_meta($user_id, 'kss_affiliate_code', true);
    
    if (empty($affiliate_code)) {
        $affiliate_code = kss_generate_user_affiliate_code($user_id);
    }
    
    return $affiliate_code;
}

/**
 * Generate unique affiliate code for user
 */
function kss_generate_user_affiliate_code($user_id) {
    $user = get_user_by('ID', $user_id);
    
    // Create code based on username + random
    $base_code = substr(strtoupper($user->user_login), 0, 4);
    $random = strtoupper(wp_generate_password(4, false));
    $affiliate_code = $base_code . $random;
    
    // Ensure uniqueness
    $attempt = 0;
    while (kss_affiliate_code_exists($affiliate_code) && $attempt < 10) {
        $random = strtoupper(wp_generate_password(4, false));
        $affiliate_code = $base_code . $random;
        $attempt++;
    }
    
    // Store the code
    update_user_meta($user_id, 'kss_affiliate_code', $affiliate_code);
    
    // Also register in KHM if available
    if (function_exists('khm_register_affiliate_code')) {
        khm_register_affiliate_code($affiliate_code, $user_id);
    }
    
    return $affiliate_code;
}

/**
 * Check if affiliate code already exists
 */
function kss_affiliate_code_exists($code) {
    if (!khm_is_marketing_suite_ready()) {
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'kh_affiliate_codes';
    
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE code = %s",
        $code
    ));
    
    return $exists > 0;
}

/**
 * Get affiliate performance data
 */
function kss_get_affiliate_performance($affiliate_code, $days = 30) {
    if (!khm_is_marketing_suite_ready()) {
        return [
            'clicks' => 0,
            'conversions' => 0,
            'earnings' => 0,
            'conversion_rate' => 0,
            'recent_activity' => []
        ];
    }
    
    global $wpdb;
    
    $date_from = date('Y-m-d', strtotime("-{$days} days"));
    
    // Get clicks
    $clicks_table = $wpdb->prefix . 'kh_affiliate_clicks';
    $total_clicks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$clicks_table} 
         WHERE affiliate_code = %s AND DATE(click_time) >= %s",
        $affiliate_code, $date_from
    ));
    
    // Get conversions and earnings
    $conversions_table = $wpdb->prefix . 'kh_affiliate_conversions';
    $conversions_data = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) as total_conversions, SUM(commission_amount) as total_earnings
         FROM {$conversions_table} 
         WHERE affiliate_code = %s AND DATE(conversion_time) >= %s",
        $affiliate_code, $date_from
    ));
    
    $total_conversions = $conversions_data->total_conversions ?? 0;
    $total_earnings = $conversions_data->total_earnings ?? 0;
    
    // Calculate conversion rate
    $conversion_rate = $total_clicks > 0 ? ($total_conversions / $total_clicks) * 100 : 0;
    
    // Get recent activity
    $recent_activity = $wpdb->get_results($wpdb->prepare(
        "SELECT 'conversion' as type, conversion_time as activity_time, commission_amount as amount, conversion_type
         FROM {$conversions_table} 
         WHERE affiliate_code = %s AND DATE(conversion_time) >= %s
         UNION ALL
         SELECT 'click' as type, click_time as activity_time, 0 as amount, 'click' as conversion_type
         FROM {$clicks_table} 
         WHERE affiliate_code = %s AND DATE(click_time) >= %s
         ORDER BY activity_time DESC
         LIMIT 20",
        $affiliate_code, $date_from, $affiliate_code, $date_from
    ));
    
    return [
        'clicks' => $total_clicks,
        'conversions' => $total_conversions,
        'earnings' => $total_earnings,
        'conversion_rate' => round($conversion_rate, 2),
        'recent_activity' => $recent_activity
    ];
}

/**
 * Render affiliate dashboard
 */
function kss_render_affiliate_dashboard($affiliate_code, $performance) {
    $current_url = home_url($_SERVER['REQUEST_URI']);
    $affiliate_url = add_query_arg(['ref' => $affiliate_code], $current_url);
    
    ?>
    <div class="kss-affiliate-dashboard">
        <h3>Your Affiliate Dashboard</h3>
        
        <div class="affiliate-stats">
            <div class="stat-box">
                <h4>Total Clicks</h4>
                <span class="stat-number"><?php echo esc_html($performance['clicks']); ?></span>
            </div>
            
            <div class="stat-box">
                <h4>Conversions</h4>
                <span class="stat-number"><?php echo esc_html($performance['conversions']); ?></span>
            </div>
            
            <div class="stat-box">
                <h4>E-Store Credits Earned</h4>
                <span class="stat-number"><?php echo esc_html(floor($performance['earnings'])); ?> credits</span>
                <small style="display: block; color: #666; font-size: 12px;">≈ £<?php echo esc_html(number_format($performance['earnings'], 2)); ?></small>
            </div>
            
            <div class="stat-box">
                <h4>Current Credit Balance</h4>
                <?php $current_credits = khm_is_marketing_suite_ready() ? khm_get_user_credits(get_current_user_id()) : 0; ?>
                <span class="stat-number"><?php echo esc_html($current_credits); ?> credits</span>
                <small style="display: block; color: #666; font-size: 12px;">Available to spend</small>
            </div>
            
            <div class="stat-box">
                <h4>Conversion Rate</h4>
                <span class="stat-number"><?php echo esc_html($performance['conversion_rate']); ?>%</span>
            </div>
        </div>
        
        <div class="affiliate-tools">
            <h4>Your Affiliate Code: <code><?php echo esc_html($affiliate_code); ?></code></h4>
            
            <div class="url-generator">
                <h5>Generate Affiliate Link:</h5>
                <input type="url" id="base-url" placeholder="Enter article URL to share" value="<?php echo esc_url($current_url); ?>">
                <button onclick="generateAffiliateLink('<?php echo esc_js($affiliate_code); ?>')">Generate Link</button>
                <div id="generated-link" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <?php if (!empty($performance['recent_activity'])): ?>
        <div class="recent-activity">
            <h4>Recent Activity</h4>
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Details</th>
                        <th>Credits Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($performance['recent_activity'] as $activity): ?>
                    <tr>
                        <td><?php echo esc_html(date('M j, Y H:i', strtotime($activity->activity_time))); ?></td>
                        <td><?php echo esc_html(ucfirst($activity->type)); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $activity->conversion_type))); ?></td>
                        <td>
                            <?php if ($activity->amount > 0): ?>
                                <?php echo esc_html(floor($activity->amount)); ?> credits
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <style>
        .kss-affiliate-dashboard {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .affiliate-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-box h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .affiliate-tools {
            background: white;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        
        .url-generator input {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .url-generator button {
            background: #0073aa;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .activity-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .activity-table th,
        .activity-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .activity-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        #generated-link {
            background: #e8f5e8;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #4CAF50;
            display: none;
        }
        
        #generated-link input {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        </style>
        
        <script>
        function generateAffiliateLink(affiliateCode) {
            const baseUrl = document.getElementById('base-url').value;
            if (!baseUrl) {
                alert('Please enter a URL to share');
                return;
            }
            
            const url = new URL(baseUrl);
            url.searchParams.set('ref', affiliateCode);
            
            const generatedDiv = document.getElementById('generated-link');
            generatedDiv.innerHTML = `
                <strong>Your Affiliate Link:</strong><br>
                <input type="text" value="${url.toString()}" readonly onclick="this.select()">
                <button onclick="copyToClipboard('${url.toString()}')">Copy</button>
            `;
            generatedDiv.style.display = 'block';
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Link copied to clipboard!');
            });
        }
        </script>
    </div>
    <?php
}

/**
 * Add shortcode for affiliate dashboard
 */
function kss_affiliate_dashboard_shortcode($atts) {
    ob_start();
    kss_add_affiliate_dashboard();
    return ob_get_clean();
}
add_shortcode('affiliate_dashboard', 'kss_affiliate_dashboard_shortcode');

/**
 * Add affiliate dashboard to user account pages
 */
function kss_add_dashboard_to_account() {
    // Hook into your account page system
    // This will depend on how your membership system works
    
    // For now, just add it to any page with the shortcode or template
}
add_action('wp', 'kss_add_dashboard_to_account');

/**
 * AJAX handler for generating affiliate links
 */
function kss_ajax_generate_affiliate_link() {
    check_ajax_referer('kss_affiliate_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_die('Not authorized');
    }
    
    $user_id = get_current_user_id();
    $base_url = sanitize_url($_POST['base_url']);
    
    if (empty($base_url)) {
        wp_send_json_error(['message' => 'Base URL is required']);
    }
    
    $affiliate_code = kss_get_user_affiliate_code($user_id);
    $affiliate_url = add_query_arg(['ref' => $affiliate_code], $base_url);
    
    wp_send_json_success(['affiliate_url' => $affiliate_url]);
}
add_action('wp_ajax_kss_generate_affiliate_link', 'kss_ajax_generate_affiliate_link');

/**
 * Add affiliate dashboard menu item to user account
 */
function kss_add_affiliate_menu_item($menu_items) {
    if (is_user_logged_in() && kss_user_can_be_affiliate(get_current_user_id())) {
        $menu_items['affiliate-dashboard'] = 'Affiliate Program';
    }
    return $menu_items;
}
// Hook this into your account menu system
// add_filter('khm_account_menu_items', 'kss_add_affiliate_menu_item');
?>