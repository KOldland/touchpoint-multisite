<?php
/**
 * KHM Professional Affiliate Interface
 * 
 * Advanced affiliate account system that surpasses SliceWP's affiliate dashboard
 * Features multi-tab interface, advanced link generation, creative access, and comprehensive analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Professional_Affiliate_Interface {
    
    private $affiliate_service;
    private $creative_service;
    private $current_affiliate_id;
    
    public function __construct() {
        // Load required services
        require_once dirname(__FILE__) . '/../src/Services/AffiliateService.php';
        require_once dirname(__FILE__) . '/../src/Services/CreativeService.php';
        
        $this->affiliate_service = new KHM_AffiliateService();
        $this->creative_service = new KHM_CreativeService();
        
        // Initialize hooks
        add_action('init', array($this, 'init_affiliate_pages'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_ajax_khm_generate_affiliate_link', array($this, 'ajax_generate_affiliate_link'));
        add_action('wp_ajax_khm_get_affiliate_stats', array($this, 'ajax_get_affiliate_stats'));
        add_shortcode('khm_affiliate_dashboard', array($this, 'render_affiliate_dashboard'));
    }
    
    /**
     * Initialize affiliate pages
     */
    public function init_affiliate_pages() {
        // Create affiliate dashboard page if it doesn't exist
        $this->create_affiliate_dashboard_page();
        
        // Set current affiliate ID
        if (is_user_logged_in()) {
            $this->current_affiliate_id = get_current_user_id();
        }
    }
    
    /**
     * Create affiliate dashboard page
     */
    private function create_affiliate_dashboard_page() {
        $page_slug = 'affiliate-dashboard';
        
        // Check if page exists
        $existing_page = get_page_by_path($page_slug);
        
        if (!$existing_page) {
            $page_data = array(
                'post_title' => 'Affiliate Dashboard',
                'post_content' => '[khm_affiliate_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => $page_slug,
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            );
            
            wp_insert_post($page_data);
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if ($this->is_affiliate_dashboard_page()) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('khm-affiliate-js', plugin_dir_url(__FILE__) . '../assets/js/affiliate-interface.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('khm-affiliate-css', plugin_dir_url(__FILE__) . '../assets/css/affiliate-interface.css', array(), '1.0.0');
            
            // Localize script
            wp_localize_script('khm-affiliate-js', 'khmAffiliate', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('khm_affiliate_nonce'),
                'affiliateId' => $this->current_affiliate_id
            ));
        }
    }
    
    /**
     * Check if current page is affiliate dashboard
     */
    private function is_affiliate_dashboard_page() {
        return is_page('affiliate-dashboard') || 
               (isset($_GET['page']) && $_GET['page'] === 'affiliate-dashboard');
    }
    
    /**
     * Render affiliate dashboard shortcode
     */
    public function render_affiliate_dashboard($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }
        
        $affiliate_id = get_current_user_id();
        $affiliate_data = $this->get_affiliate_data($affiliate_id);
        
        if (!$affiliate_data['is_affiliate']) {
            return $this->render_not_affiliate();
        }
        
        ob_start();
        $this->render_dashboard_interface($affiliate_data);
        return ob_get_clean();
    }
    
    /**
     * Render main dashboard interface
     */
    private function render_dashboard_interface($affiliate_data) {
        echo '<div class="khm-affiliate-dashboard">';
        
        // Dashboard header
        $this->render_dashboard_header($affiliate_data);
        
        // Navigation tabs
        $this->render_navigation_tabs();
        
        // Tab content
        echo '<div class="khm-tab-content">';
        
        // Overview tab (default)
        echo '<div id="overview-tab" class="khm-tab-panel active">';
        $this->render_overview_tab($affiliate_data);
        echo '</div>';
        
        // Link Generator tab
        echo '<div id="links-tab" class="khm-tab-panel">';
        $this->render_link_generator_tab();
        echo '</div>';
        
        // Creatives tab
        echo '<div id="creatives-tab" class="khm-tab-panel">';
        $this->render_creatives_tab();
        echo '</div>';
        
        // Analytics tab
        echo '<div id="analytics-tab" class="khm-tab-panel">';
        $this->render_analytics_tab($affiliate_data);
        echo '</div>';
        
        // Earnings tab
        echo '<div id="earnings-tab" class="khm-tab-panel">';
        $this->render_earnings_tab($affiliate_data);
        echo '</div>';
        
        // Account tab
        echo '<div id="account-tab" class="khm-tab-panel">';
        $this->render_account_tab($affiliate_data);
        echo '</div>';
        
        echo '</div>'; // tab-content
        echo '</div>'; // khm-affiliate-dashboard
    }
    
    /**
     * Render dashboard header
     */
    private function render_dashboard_header($affiliate_data) {
        echo '<div class="khm-dashboard-header">';
        echo '<div class="khm-header-content">';
        echo '<h1>Affiliate Dashboard</h1>';
        echo '<div class="khm-affiliate-welcome">';
        echo '<span class="khm-welcome-text">Welcome back, ' . esc_html($affiliate_data['display_name']) . '</span>';
        echo '<span class="khm-affiliate-id">ID: ' . $affiliate_data['affiliate_id'] . '</span>';
        echo '</div>';
        echo '</div>';
        
        // Quick stats
        echo '<div class="khm-quick-stats">';
        echo '<div class="khm-stat-item">';
        echo '<div class="khm-stat-value">$' . number_format($affiliate_data['total_earnings'], 2) . '</div>';
        echo '<div class="khm-stat-label">Total Earnings</div>';
        echo '</div>';
        echo '<div class="khm-stat-item">';
        echo '<div class="khm-stat-value">' . number_format($affiliate_data['total_clicks']) . '</div>';
        echo '<div class="khm-stat-label">Total Clicks</div>';
        echo '</div>';
        echo '<div class="khm-stat-item">';
        echo '<div class="khm-stat-value">' . number_format($affiliate_data['total_conversions']) . '</div>';
        echo '<div class="khm-stat-label">Conversions</div>';
        echo '</div>';
        echo '<div class="khm-stat-item">';
        echo '<div class="khm-stat-value">' . $affiliate_data['conversion_rate'] . '%</div>';
        echo '<div class="khm-stat-label">Conversion Rate</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render navigation tabs
     */
    private function render_navigation_tabs() {
        echo '<div class="khm-navigation-tabs">';
        
        $tabs = array(
            'overview' => array('title' => 'Overview', 'icon' => '📊'),
            'links' => array('title' => 'Link Generator', 'icon' => '🔗'),
            'creatives' => array('title' => 'Marketing Materials', 'icon' => '🎨'),
            'analytics' => array('title' => 'Analytics', 'icon' => '📈'),
            'earnings' => array('title' => 'Earnings', 'icon' => '💰'),
            'account' => array('title' => 'Account', 'icon' => '⚙️')
        );
        
        foreach ($tabs as $tab_id => $tab) {
            $active_class = $tab_id === 'overview' ? ' active' : '';
            echo '<button class="khm-tab-button' . $active_class . '" data-tab="' . $tab_id . '-tab">';
            echo '<span class="khm-tab-icon">' . $tab['icon'] . '</span>';
            echo '<span class="khm-tab-title">' . $tab['title'] . '</span>';
            echo '</button>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render overview tab
     */
    private function render_overview_tab($affiliate_data) {
        echo '<div class="khm-overview-content">';
        
        // Performance cards
        echo '<div class="khm-performance-grid">';
        
        // This month earnings
        echo '<div class="khm-performance-card">';
        echo '<div class="khm-card-header">';
        echo '<h3>This Month</h3>';
        echo '<span class="khm-card-icon">📅</span>';
        echo '</div>';
        echo '<div class="khm-card-content">';
        echo '<div class="khm-card-value">$' . number_format($affiliate_data['monthly_earnings'], 2) . '</div>';
        echo '<div class="khm-card-change positive">+' . $affiliate_data['monthly_change'] . '% from last month</div>';
        echo '</div>';
        echo '</div>';
        
        // Recent activity
        echo '<div class="khm-performance-card">';
        echo '<div class="khm-card-header">';
        echo '<h3>Recent Activity</h3>';
        echo '<span class="khm-card-icon">🔄</span>';
        echo '</div>';
        echo '<div class="khm-card-content">';
        echo '<div class="khm-card-value">' . $affiliate_data['recent_clicks'] . '</div>';
        echo '<div class="khm-card-label">clicks this week</div>';
        echo '</div>';
        echo '</div>';
        
        // Top performing link
        echo '<div class="khm-performance-card">';
        echo '<div class="khm-card-header">';
        echo '<h3>Top Performer</h3>';
        echo '<span class="khm-card-icon">🏆</span>';
        echo '</div>';
        echo '<div class="khm-card-content">';
        echo '<div class="khm-card-value">' . $affiliate_data['top_link']['clicks'] . '</div>';
        echo '<div class="khm-card-label">clicks on ' . esc_html($affiliate_data['top_link']['name']) . '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // performance-grid
        
        // Recent activity feed
        echo '<div class="khm-activity-section">';
        echo '<h3>Recent Activity</h3>';
        echo '<div class="khm-activity-feed">';
        
        foreach ($affiliate_data['recent_activity'] as $activity) {
            echo '<div class="khm-activity-item">';
            echo '<div class="khm-activity-icon">' . $this->get_activity_icon($activity['type']) . '</div>';
            echo '<div class="khm-activity-content">';
            echo '<div class="khm-activity-text">' . esc_html($activity['description']) . '</div>';
            echo '<div class="khm-activity-time">' . $this->time_ago($activity['timestamp']) . '</div>';
            echo '</div>';
            if (isset($activity['amount'])) {
                echo '<div class="khm-activity-amount">+$' . number_format($activity['amount'], 2) . '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render link generator tab
     */
    private function render_link_generator_tab() {
        echo '<div class="khm-link-generator">';
        
        echo '<div class="khm-generator-header">';
        echo '<h3>Generate Affiliate Links</h3>';
        echo '<p>Create trackable affiliate links for any page or product.</p>';
        echo '</div>';
        
        // Quick links section
        echo '<div class="khm-quick-links">';
        echo '<h4>Quick Links</h4>';
        echo '<div class="khm-quick-link-grid">';
        
        $quick_links = array(
            array('title' => 'Homepage', 'url' => home_url(), 'icon' => '🏠'),
            array('title' => 'Membership Page', 'url' => home_url('/membership'), 'icon' => '👥'),
            array('title' => 'Products', 'url' => home_url('/products'), 'icon' => '🛍️'),
            array('title' => 'Blog', 'url' => home_url('/blog'), 'icon' => '📝')
        );
        
        foreach ($quick_links as $link) {
            echo '<div class="khm-quick-link-item" data-url="' . esc_url($link['url']) . '">';
            echo '<div class="khm-quick-link-icon">' . $link['icon'] . '</div>';
            echo '<div class="khm-quick-link-title">' . esc_html($link['title']) . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Custom link generator
        echo '<div class="khm-custom-generator">';
        echo '<h4>Custom Link Generator</h4>';
        echo '<form id="link-generator-form">';
        
        echo '<div class="khm-form-row">';
        echo '<div class="khm-form-group">';
        echo '<label for="target-url">Target URL</label>';
        echo '<input type="url" id="target-url" name="target_url" placeholder="https://example.com/page" required>';
        echo '</div>';
        echo '<div class="khm-form-group">';
        echo '<label for="link-campaign">Campaign Name (Optional)</label>';
        echo '<input type="text" id="link-campaign" name="campaign" placeholder="summer-promotion">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="khm-form-row">';
        echo '<div class="khm-form-group">';
        echo '<label for="link-medium">Medium (Optional)</label>';
        echo '<select id="link-medium" name="medium">';
        echo '<option value="">Select Medium</option>';
        echo '<option value="email">Email</option>';
        echo '<option value="social">Social Media</option>';
        echo '<option value="website">Website</option>';
        echo '<option value="blog">Blog Post</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="khm-form-group">';
        echo '<label for="link-source">Source (Optional)</label>';
        echo '<input type="text" id="link-source" name="source" placeholder="facebook, newsletter, etc.">';
        echo '</div>';
        echo '</div>';
        
        echo '<button type="submit" class="khm-generate-btn">Generate Affiliate Link</button>';
        echo '</form>';
        
        // Generated link display
        echo '<div id="generated-link-section" class="khm-generated-link" style="display: none;">';
        echo '<h4>Your Affiliate Link</h4>';
        echo '<div class="khm-link-display">';
        echo '<input type="text" id="generated-link" readonly>';
        echo '<button id="copy-link-btn" class="khm-copy-btn">Copy</button>';
        echo '</div>';
        echo '<div class="khm-link-actions">';
        echo '<button id="test-link-btn" class="khm-test-btn">Test Link</button>';
        echo '<button id="get-qr-code-btn" class="khm-qr-btn">Get QR Code</button>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Recent links
        echo '<div class="khm-recent-links">';
        echo '<h4>Recent Links</h4>';
        echo '<div id="recent-links-list">';
        echo '<p>Generate your first link to see it here.</p>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render creatives tab
     */
    private function render_creatives_tab() {
        echo '<div class="khm-creatives-tab">';
        
        echo '<div class="khm-creatives-header">';
        echo '<h3>Marketing Materials</h3>';
        echo '<p>Professional marketing materials ready for your campaigns.</p>';
        echo '</div>';
        
        // Creative categories
        echo '<div class="khm-creative-categories">';
        echo '<button class="khm-category-btn active" data-category="all">All Materials</button>';
        echo '<button class="khm-category-btn" data-category="banner">Banners</button>';
        echo '<button class="khm-category-btn" data-category="text">Text Ads</button>';
        echo '<button class="khm-category-btn" data-category="social">Social Media</button>';
        echo '<button class="khm-category-btn" data-category="video">Videos</button>';
        echo '</div>';
        
        // Creatives grid
        echo '<div id="creatives-grid" class="khm-creatives-grid">';
        $this->render_available_creatives();
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render available creatives
     */
    private function render_available_creatives() {
        $creatives = $this->creative_service->get_creatives(array('status' => 'active', 'limit' => 20));
        
        if (empty($creatives)) {
            echo '<div class="khm-no-creatives">';
            echo '<p>No marketing materials available yet.</p>';
            echo '</div>';
            return;
        }
        
        foreach ($creatives as $creative) {
            echo '<div class="khm-creative-item" data-type="' . esc_attr($creative->type) . '">';
            
            // Creative preview
            echo '<div class="khm-creative-preview">';
            if (!empty($creative->image_url)) {
                echo '<img src="' . esc_url($creative->image_url) . '" alt="' . esc_attr($creative->alt_text) . '">';
            } else {
                echo '<div class="khm-creative-placeholder">';
                echo '<span class="khm-creative-type">' . ucfirst($creative->type) . '</span>';
                echo '</div>';
            }
            echo '</div>';
            
            // Creative info
            echo '<div class="khm-creative-info">';
            echo '<h4>' . esc_html($creative->name) . '</h4>';
            echo '<p>' . esc_html($creative->description) . '</p>';
            
            if (!empty($creative->dimensions)) {
                echo '<div class="khm-creative-dimensions">' . esc_html($creative->dimensions) . '</div>';
            }
            
            echo '<div class="khm-creative-actions">';
            echo '<button class="khm-get-code-btn" data-creative-id="' . $creative->id . '">Get Code</button>';
            echo '<button class="khm-preview-btn" data-creative-id="' . $creative->id . '">Preview</button>';
            echo '</div>';
            
            echo '</div>';
            
            echo '</div>';
        }
    }
    
    /**
     * Render analytics tab
     */
    private function render_analytics_tab($affiliate_data) {
        echo '<div class="khm-analytics-tab">';
        
        // Analytics filters
        echo '<div class="khm-analytics-filters">';
        echo '<select id="analytics-period">';
        echo '<option value="7">Last 7 Days</option>';
        echo '<option value="30" selected>Last 30 Days</option>';
        echo '<option value="90">Last 90 Days</option>';
        echo '</select>';
        echo '<button id="export-analytics-btn" class="khm-export-btn">Export Data</button>';
        echo '</div>';
        
        // Charts section
        echo '<div class="khm-analytics-charts">';
        
        // Traffic chart
        echo '<div class="khm-chart-container">';
        echo '<h4>Traffic Overview</h4>';
        echo '<canvas id="traffic-chart" width="400" height="200"></canvas>';
        echo '</div>';
        
        // Conversions chart
        echo '<div class="khm-chart-container">';
        echo '<h4>Conversions</h4>';
        echo '<canvas id="conversions-chart" width="400" height="200"></canvas>';
        echo '</div>';
        
        echo '</div>';
        
        // Performance table
        echo '<div class="khm-performance-table">';
        echo '<h4>Link Performance</h4>';
        echo '<table class="khm-data-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Link</th>';
        echo '<th>Clicks</th>';
        echo '<th>Conversions</th>';
        echo '<th>CTR</th>';
        echo '<th>Earnings</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="performance-table-body">';
        $this->render_performance_table_data($affiliate_data);
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render performance table data
     */
    private function render_performance_table_data($affiliate_data) {
        if (!empty($affiliate_data['link_performance'])) {
            foreach ($affiliate_data['link_performance'] as $link) {
                echo '<tr>';
                echo '<td>' . esc_html($link['name']) . '</td>';
                echo '<td>' . number_format($link['clicks']) . '</td>';
                echo '<td>' . number_format($link['conversions']) . '</td>';
                echo '<td>' . $link['ctr'] . '%</td>';
                echo '<td>$' . number_format($link['earnings'], 2) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="5">No performance data available yet.</td></tr>';
        }
    }
    
    /**
     * Render earnings tab
     */
    private function render_earnings_tab($affiliate_data) {
        echo '<div class="khm-earnings-tab">';
        
        // Earnings summary
        echo '<div class="khm-earnings-summary">';
        echo '<div class="khm-earnings-card">';
        echo '<h3>Total Earnings</h3>';
        echo '<div class="khm-earnings-value">$' . number_format($affiliate_data['total_earnings'], 2) . '</div>';
        echo '</div>';
        echo '<div class="khm-earnings-card">';
        echo '<h3>Current Balance</h3>';
        echo '<div class="khm-earnings-value">$' . number_format($affiliate_data['current_balance'], 2) . '</div>';
        echo '</div>';
        echo '<div class="khm-earnings-card">';
        echo '<h3>Next Payout</h3>';
        echo '<div class="khm-earnings-value">' . $affiliate_data['next_payout_date'] . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Earnings history
        echo '<div class="khm-earnings-history">';
        echo '<h4>Earnings History</h4>';
        echo '<table class="khm-data-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Date</th>';
        echo '<th>Type</th>';
        echo '<th>Amount</th>';
        echo '<th>Status</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($affiliate_data['earnings_history'] as $earning) {
            echo '<tr>';
            echo '<td>' . date('M j, Y', strtotime($earning['date'])) . '</td>';
            echo '<td>' . esc_html($earning['type']) . '</td>';
            echo '<td>$' . number_format($earning['amount'], 2) . '</td>';
            echo '<td><span class="khm-status khm-status-' . $earning['status'] . '">' . ucfirst($earning['status']) . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render account tab
     */
    private function render_account_tab($affiliate_data) {
        echo '<div class="khm-account-tab">';
        
        // Account information
        echo '<div class="khm-account-info">';
        echo '<h4>Account Information</h4>';
        echo '<form id="account-form">';
        
        echo '<div class="khm-form-row">';
        echo '<div class="khm-form-group">';
        echo '<label for="affiliate-name">Display Name</label>';
        echo '<input type="text" id="affiliate-name" name="display_name" value="' . esc_attr($affiliate_data['display_name']) . '">';
        echo '</div>';
        echo '<div class="khm-form-group">';
        echo '<label for="affiliate-email">Email Address</label>';
        echo '<input type="email" id="affiliate-email" name="email" value="' . esc_attr($affiliate_data['email']) . '" readonly>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="khm-form-row">';
        echo '<div class="khm-form-group">';
        echo '<label for="affiliate-website">Website URL</label>';
        echo '<input type="url" id="affiliate-website" name="website" value="' . esc_attr($affiliate_data['website']) . '">';
        echo '</div>';
        echo '<div class="khm-form-group">';
        echo '<label for="affiliate-phone">Phone Number</label>';
        echo '<input type="tel" id="affiliate-phone" name="phone" value="' . esc_attr($affiliate_data['phone']) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<button type="submit" class="khm-save-btn">Save Changes</button>';
        echo '</form>';
        echo '</div>';
        
        // Payment information
        echo '<div class="khm-payment-info">';
        echo '<h4>Payment Information</h4>';
        echo '<form id="payment-form">';
        
        echo '<div class="khm-form-row">';
        echo '<div class="khm-form-group">';
        echo '<label for="payment-method">Payment Method</label>';
        echo '<select id="payment-method" name="payment_method">';
        echo '<option value="paypal"' . ($affiliate_data['payment_method'] === 'paypal' ? ' selected' : '') . '>PayPal</option>';
        echo '<option value="bank"' . ($affiliate_data['payment_method'] === 'bank' ? ' selected' : '') . '>Bank Transfer</option>';
        echo '<option value="check"' . ($affiliate_data['payment_method'] === 'check' ? ' selected' : '') . '>Check</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="khm-form-group">';
        echo '<label for="payment-email">PayPal Email / Account Info</label>';
        echo '<input type="text" id="payment-email" name="payment_email" value="' . esc_attr($affiliate_data['payment_email']) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<button type="submit" class="khm-save-btn">Update Payment Info</button>';
        echo '</form>';
        echo '</div>';
        
        // Affiliate tools
        echo '<div class="khm-affiliate-tools">';
        echo '<h4>Affiliate Tools</h4>';
        echo '<div class="khm-tools-grid">';
        
        echo '<div class="khm-tool-item">';
        echo '<h5>API Access</h5>';
        echo '<p>Generate API keys for advanced integrations.</p>';
        echo '<button class="khm-tool-btn">Generate API Key</button>';
        echo '</div>';
        
        echo '<div class="khm-tool-item">';
        echo '<h5>Referral Code</h5>';
        echo '<p>Your unique referral code: <code>' . $affiliate_data['referral_code'] . '</code></p>';
        echo '<button class="khm-tool-btn" onclick="navigator.clipboard.writeText(\'' . $affiliate_data['referral_code'] . '\')">Copy Code</button>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Get affiliate data
     */
    private function get_affiliate_data($affiliate_id) {
        // Mock data - in production, query actual affiliate data
        return array(
            'affiliate_id' => $affiliate_id,
            'is_affiliate' => true,
            'display_name' => get_user_meta($affiliate_id, 'display_name', true) ?: 'Affiliate User',
            'email' => get_user_meta($affiliate_id, 'user_email', true) ?: 'affiliate@example.com',
            'website' => get_user_meta($affiliate_id, 'website', true) ?: '',
            'phone' => get_user_meta($affiliate_id, 'phone', true) ?: '',
            'total_earnings' => 2450.75,
            'monthly_earnings' => 485.25,
            'monthly_change' => 15.3,
            'current_balance' => 450.75,
            'total_clicks' => 1247,
            'total_conversions' => 89,
            'conversion_rate' => 7.1,
            'recent_clicks' => 156,
            'next_payout_date' => 'Nov 15, 2024',
            'payment_method' => 'paypal',
            'payment_email' => 'payments@example.com',
            'referral_code' => strtoupper(substr(md5($affiliate_id), 0, 8)),
            'top_link' => array(
                'name' => 'Premium Membership',
                'clicks' => 340
            ),
            'recent_activity' => array(
                array(
                    'type' => 'conversion',
                    'description' => 'New conversion from premium membership link',
                    'timestamp' => time() - 1800,
                    'amount' => 25.00
                ),
                array(
                    'type' => 'click',
                    'description' => '15 new clicks on homepage link',
                    'timestamp' => time() - 3600
                ),
                array(
                    'type' => 'payout',
                    'description' => 'Monthly payout processed',
                    'timestamp' => time() - 86400,
                    'amount' => 450.75
                )
            ),
            'link_performance' => array(
                array('name' => 'Homepage', 'clicks' => 340, 'conversions' => 25, 'ctr' => 7.4, 'earnings' => 125.00),
                array('name' => 'Membership Page', 'clicks' => 280, 'conversions' => 18, 'ctr' => 6.4, 'earnings' => 90.00),
                array('name' => 'Products', 'clicks' => 195, 'conversions' => 12, 'ctr' => 6.2, 'earnings' => 60.00)
            ),
            'earnings_history' => array(
                array('date' => '2024-11-01', 'type' => 'Commission', 'amount' => 25.00, 'status' => 'paid'),
                array('date' => '2024-10-30', 'type' => 'Commission', 'amount' => 15.50, 'status' => 'paid'),
                array('date' => '2024-10-28', 'type' => 'Commission', 'amount' => 32.75, 'status' => 'paid'),
                array('date' => '2024-10-25', 'type' => 'Bonus', 'amount' => 50.00, 'status' => 'paid')
            )
        );
    }
    
    /**
     * Render login required message
     */
    private function render_login_required() {
        return '<div class="khm-login-required">
                    <h3>Login Required</h3>
                    <p>Please log in to access your affiliate dashboard.</p>
                    <a href="' . wp_login_url() . '" class="khm-login-btn">Login</a>
                </div>';
    }
    
    /**
     * Render not affiliate message
     */
    private function render_not_affiliate() {
        return '<div class="khm-not-affiliate">
                    <h3>Affiliate Registration Required</h3>
                    <p>You need to be registered as an affiliate to access this dashboard.</p>
                    <a href="/affiliate-registration" class="khm-register-btn">Register as Affiliate</a>
                </div>';
    }
    
    /**
     * Get activity icon
     */
    private function get_activity_icon($type) {
        $icons = array(
            'conversion' => '💰',
            'click' => '🖱️',
            'payout' => '💸',
            'signup' => '👤'
        );
        
        return $icons[$type] ?? '📊';
    }
    
    /**
     * Time ago helper
     */
    private function time_ago($timestamp) {
        $diff = time() - $timestamp;
        
        if ($diff < 60) return $diff . ' seconds ago';
        if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        
        return floor($diff / 86400) . ' days ago';
    }
    
    /**
     * AJAX: Generate affiliate link
     */
    public function ajax_generate_affiliate_link() {
        check_ajax_referer('khm_affiliate_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Authentication required'));
        }
        
        $affiliate_id = get_current_user_id();
        $target_url = esc_url_raw($_POST['target_url']);
        $campaign = sanitize_text_field($_POST['campaign'] ?? '');
        $medium = sanitize_text_field($_POST['medium'] ?? '');
        $source = sanitize_text_field($_POST['source'] ?? '');
        
        if (empty($target_url)) {
            wp_send_json_error(array('message' => 'Target URL is required'));
        }
        
        $context = array(
            'campaign' => $campaign,
            'medium' => $medium,
            'source' => $source
        );
        
        $affiliate_url = $this->affiliate_service->generate_affiliate_url($affiliate_id, $target_url, $context);
        
        if ($affiliate_url) {
            wp_send_json_success(array(
                'affiliate_url' => $affiliate_url,
                'message' => 'Affiliate link generated successfully'
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to generate affiliate link'));
        }
    }
    
    /**
     * AJAX: Get affiliate stats
     */
    public function ajax_get_affiliate_stats() {
        check_ajax_referer('khm_affiliate_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Authentication required'));
        }
        
        $affiliate_id = get_current_user_id();
        $period = sanitize_text_field($_POST['period'] ?? '30');
        
        // Get stats from affiliate service
        $stats = $this->get_affiliate_data($affiliate_id);
        
        wp_send_json_success($stats);
    }
}

// Initialize Professional Affiliate Interface
new KHM_Professional_Affiliate_Interface();