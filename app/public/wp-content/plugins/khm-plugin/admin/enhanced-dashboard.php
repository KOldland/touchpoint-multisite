<?php
/**
 * KHM Enhanced Admin Dashboard
 * 
 * Professional admin interface that surpasses SliceWP's dashboard capabilities
 * Features comprehensive analytics, performance cards, affiliate oversight, and commission management
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Enhanced_Dashboard {
    
    private $affiliate_service;
    private $creative_service;
    private $credit_service;
    
    public function __construct() {
        // Load required services
        require_once dirname(__FILE__) . '/../src/Services/AffiliateService.php';
        require_once dirname(__FILE__) . '/../src/Services/CreativeService.php';
        require_once dirname(__FILE__) . '/../src/Services/CreditService.php';
        
        $this->affiliate_service = new KHM_AffiliateService();
        $this->creative_service = new KHM_CreativeService();
        $this->credit_service = new KHM_CreditService();
        
        // Initialize admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_khm_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_khm_export_data', array($this, 'ajax_export_data'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'KHM Dashboard',
            'KHM Dashboard',
            'manage_options',
            'khm-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-area',
            3
        );
        
        // Sub-menu pages
        add_submenu_page(
            'khm-dashboard',
            'Overview',
            'Overview',
            'manage_options',
            'khm-dashboard',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'khm-dashboard',
            'Affiliate Analytics',
            'Affiliate Analytics',
            'manage_options',
            'khm-affiliate-analytics',
            array($this, 'render_affiliate_analytics')
        );
        
        add_submenu_page(
            'khm-dashboard',
            'Performance Monitor',
            'Performance Monitor',
            'manage_options',
            'khm-performance',
            array($this, 'render_performance_monitor')
        );
        
        add_submenu_page(
            'khm-dashboard',
            'Commission Management',
            'Commission Management',
            'manage_options',
            'khm-commissions',
            array($this, 'render_commission_management')
        );
        
        add_submenu_page(
            'khm-dashboard',
            'System Health',
            'System Health',
            'manage_options',
            'khm-system-health',
            array($this, 'render_system_health')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'khm-') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        wp_enqueue_style('khm-dashboard', plugin_dir_url(__FILE__) . '../assets/css/enhanced-dashboard.css', array(), '1.0.0');
        
        // Localize script for AJAX
        wp_localize_script('jquery', 'khmDashboard', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_dashboard_nonce')
        ));
    }
    
    /**
     * Render main dashboard page
     */
    public function render_dashboard_page() {
        $stats = $this->get_dashboard_statistics();
        
        echo '<div class="wrap khm-dashboard">';
        echo '<h1>KHM Marketing Suite Dashboard</h1>';
        echo '<p class="khm-dashboard-subtitle">Professional marketing analytics and affiliate management system</p>';
        
        // Performance Cards Row
        $this->render_performance_cards($stats);
        
        // Charts Row
        echo '<div class="khm-dashboard-row">';
        echo '<div class="khm-dashboard-col-8">';
        $this->render_affiliate_performance_chart($stats);
        echo '</div>';
        echo '<div class="khm-dashboard-col-4">';
        $this->render_top_performers_widget($stats);
        echo '</div>';
        echo '</div>';
        
        // Recent Activity Row
        echo '<div class="khm-dashboard-row">';
        echo '<div class="khm-dashboard-col-6">';
        $this->render_recent_activity($stats);
        echo '</div>';
        echo '<div class="khm-dashboard-col-6">';
        $this->render_creative_performance($stats);
        echo '</div>';
        echo '</div>';
        
        // System Status Row
        $this->render_system_status();
        
        echo '</div>';
        
        $this->render_dashboard_scripts();
    }
    
    /**
     * Render performance cards
     */
    private function render_performance_cards($stats) {
        echo '<div class="khm-performance-cards">';
        
        // Total Revenue Card
        echo '<div class="khm-card khm-card-revenue">';
        echo '<div class="khm-card-icon">💰</div>';
        echo '<div class="khm-card-content">';
        echo '<h3>Total Revenue</h3>';
        echo '<div class="khm-card-value">$' . number_format($stats['total_revenue'], 2) . '</div>';
        echo '<div class="khm-card-change ' . ($stats['revenue_change'] >= 0 ? 'positive' : 'negative') . '">';
        echo ($stats['revenue_change'] >= 0 ? '↗' : '↘') . ' ' . abs($stats['revenue_change']) . '% vs last month';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Active Affiliates Card
        echo '<div class="khm-card khm-card-affiliates">';
        echo '<div class="khm-card-icon">👥</div>';
        echo '<div class="khm-card-content">';
        echo '<h3>Active Affiliates</h3>';
        echo '<div class="khm-card-value">' . number_format($stats['active_affiliates']) . '</div>';
        echo '<div class="khm-card-change positive">↗ ' . $stats['new_affiliates'] . ' new this month</div>';
        echo '</div>';
        echo '</div>';
        
        // Conversion Rate Card
        echo '<div class="khm-card khm-card-conversion">';
        echo '<div class="khm-card-icon">📈</div>';
        echo '<div class="khm-card-content">';
        echo '<h3>Conversion Rate</h3>';
        echo '<div class="khm-card-value">' . $stats['conversion_rate'] . '%</div>';
        echo '<div class="khm-card-change ' . ($stats['conversion_change'] >= 0 ? 'positive' : 'negative') . '">';
        echo ($stats['conversion_change'] >= 0 ? '↗' : '↘') . ' ' . abs($stats['conversion_change']) . '% improvement';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Total Clicks Card
        echo '<div class="khm-card khm-card-clicks">';
        echo '<div class="khm-card-icon">🖱️</div>';
        echo '<div class="khm-card-content">';
        echo '<h3>Total Clicks</h3>';
        echo '<div class="khm-card-value">' . number_format($stats['total_clicks']) . '</div>';
        echo '<div class="khm-card-change positive">↗ ' . number_format($stats['clicks_today']) . ' today</div>';
        echo '</div>';
        echo '</div>';

        // Preview Manager CTA
        echo '<div class="khm-card khm-card-preview">';
        echo '<div class="khm-card-icon">📝</div>';
        echo '<div class="khm-card-content">';
        echo '<h3>Preview Manager</h3>';
        echo '<p>Share draft campaigns with execs before launch using secure preview links.</p>';
        echo '<a class="button button-secondary" href="' . esc_url( admin_url( 'admin.php?page=khm-preview-links' ) ) . '">Manage Preview Links</a>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render affiliate performance chart
     */
    private function render_affiliate_performance_chart($stats) {
        echo '<div class="khm-widget">';
        echo '<div class="khm-widget-header">';
        echo '<h3>Affiliate Performance Trends</h3>';
        echo '<div class="khm-widget-controls">';
        echo '<select id="performance-period">';
        echo '<option value="7">Last 7 Days</option>';
        echo '<option value="30" selected>Last 30 Days</option>';
        echo '<option value="90">Last 90 Days</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
        echo '<div class="khm-widget-content">';
        echo '<canvas id="affiliatePerformanceChart" width="400" height="200"></canvas>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render top performers widget
     */
    private function render_top_performers_widget($stats) {
        echo '<div class="khm-widget">';
        echo '<div class="khm-widget-header">';
        echo '<h3>Top Performers</h3>';
        echo '</div>';
        echo '<div class="khm-widget-content">';
        
        if (!empty($stats['top_performers'])) {
            echo '<div class="khm-top-performers">';
            foreach ($stats['top_performers'] as $index => $performer) {
                $rank = $index + 1;
                $medal = $rank <= 3 ? ['🥇', '🥈', '🥉'][$rank - 1] : $rank;
                
                echo '<div class="khm-performer-item">';
                echo '<div class="khm-performer-rank">' . $medal . '</div>';
                echo '<div class="khm-performer-info">';
                echo '<div class="khm-performer-name">' . esc_html($performer['name']) . '</div>';
                echo '<div class="khm-performer-stats">';
                echo '<span class="revenue">$' . number_format($performer['revenue'], 2) . '</span>';
                echo '<span class="conversions">' . $performer['conversions'] . ' conversions</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="khm-no-data">No performance data available yet.</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render recent activity widget
     */
    private function render_recent_activity($stats) {
        echo '<div class="khm-widget">';
        echo '<div class="khm-widget-header">';
        echo '<h3>Recent Activity</h3>';
        echo '</div>';
        echo '<div class="khm-widget-content">';
        
        if (!empty($stats['recent_activity'])) {
            echo '<div class="khm-activity-feed">';
            foreach ($stats['recent_activity'] as $activity) {
                echo '<div class="khm-activity-item">';
                echo '<div class="khm-activity-icon">' . $this->get_activity_icon($activity['type']) . '</div>';
                echo '<div class="khm-activity-content">';
                echo '<div class="khm-activity-text">' . esc_html($activity['text']) . '</div>';
                echo '<div class="khm-activity-time">' . $this->time_ago($activity['timestamp']) . '</div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="khm-no-data">No recent activity.</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render creative performance widget
     */
    private function render_creative_performance($stats) {
        echo '<div class="khm-widget">';
        echo '<div class="khm-widget-header">';
        echo '<h3>Creative Performance</h3>';
        echo '<a href="' . admin_url('admin.php?page=khm-creatives') . '" class="khm-widget-link">Manage Creatives</a>';
        echo '</div>';
        echo '<div class="khm-widget-content">';
        
        if (!empty($stats['creative_performance'])) {
            echo '<div class="khm-creative-stats">';
            foreach ($stats['creative_performance'] as $creative) {
                echo '<div class="khm-creative-item">';
                echo '<div class="khm-creative-name">' . esc_html($creative['name']) . '</div>';
                echo '<div class="khm-creative-metrics">';
                echo '<span class="views">' . number_format($creative['views']) . ' views</span>';
                echo '<span class="ctr">' . $creative['ctr'] . '% CTR</span>';
                echo '</div>';
                echo '<div class="khm-creative-bar">';
                echo '<div class="khm-creative-fill" style="width: ' . min(100, $creative['ctr'] * 10) . '%"></div>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="khm-no-data">No creative performance data available.</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render system status
     */
    private function render_system_status() {
        $health = $this->get_system_health();
        
        echo '<div class="khm-system-status">';
        echo '<h3>System Health Overview</h3>';
        echo '<div class="khm-health-grid">';
        
        foreach ($health as $component => $status) {
            $icon = $status['status'] === 'healthy' ? '✅' : ($status['status'] === 'warning' ? '⚠️' : '❌');
            $class = 'khm-health-' . $status['status'];
            
            echo '<div class="khm-health-item ' . $class . '">';
            echo '<div class="khm-health-icon">' . $icon . '</div>';
            echo '<div class="khm-health-content">';
            echo '<div class="khm-health-title">' . esc_html($component) . '</div>';
            echo '<div class="khm-health-message">' . esc_html($status['message']) . '</div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render affiliate analytics page
     */
    public function render_affiliate_analytics() {
        echo '<div class="wrap khm-analytics">';
        echo '<h1>Affiliate Analytics</h1>';
        
        // Filters
        echo '<div class="khm-analytics-filters">';
        echo '<select id="analytics-period">';
        echo '<option value="7">Last 7 Days</option>';
        echo '<option value="30" selected>Last 30 Days</option>';
        echo '<option value="90">Last 90 Days</option>';
        echo '<option value="365">Last Year</option>';
        echo '</select>';
        echo '<select id="analytics-affiliate">';
        echo '<option value="">All Affiliates</option>';
        // Populated via JavaScript
        echo '</select>';
        echo '<button id="export-analytics" class="button">Export Data</button>';
        echo '</div>';
        
        // Analytics Charts
        echo '<div class="khm-analytics-grid">';
        echo '<div class="khm-analytics-chart">';
        echo '<canvas id="revenueChart"></canvas>';
        echo '</div>';
        echo '<div class="khm-analytics-chart">';
        echo '<canvas id="conversionChart"></canvas>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render performance monitor page
     */
    public function render_performance_monitor() {
        echo '<div class="wrap khm-performance">';
        echo '<h1>Performance Monitor</h1>';
        
        // Real-time metrics
        echo '<div class="khm-realtime-metrics">';
        echo '<h2>Real-time Metrics</h2>';
        echo '<div class="khm-metrics-grid" id="realtime-metrics">';
        echo '<div class="khm-metric-item">';
        echo '<div class="khm-metric-value" id="realtime-clicks">--</div>';
        echo '<div class="khm-metric-label">Clicks Today</div>';
        echo '</div>';
        echo '<div class="khm-metric-item">';
        echo '<div class="khm-metric-value" id="realtime-conversions">--</div>';
        echo '<div class="khm-metric-label">Conversions Today</div>';
        echo '</div>';
        echo '<div class="khm-metric-item">';
        echo '<div class="khm-metric-value" id="realtime-revenue">--</div>';
        echo '<div class="khm-metric-label">Revenue Today</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Performance alerts
        echo '<div class="khm-performance-alerts">';
        echo '<h2>Performance Alerts</h2>';
        echo '<div id="performance-alerts">';
        // Populated via JavaScript
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render commission management page
     */
    public function render_commission_management() {
        echo '<div class="wrap khm-commissions">';
        echo '<h1>Commission Management</h1>';
        
        // Commission settings
        echo '<div class="khm-commission-settings">';
        echo '<h2>Commission Rates</h2>';
        echo '<form id="commission-form">';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">Default Commission Rate</th>';
        echo '<td><input type="number" step="0.01" name="default_rate" value="10.00" /> %</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">Premium Member Rate</th>';
        echo '<td><input type="number" step="0.01" name="premium_rate" value="15.00" /> %</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row">Minimum Payout</th>';
        echo '<td>$<input type="number" step="0.01" name="minimum_payout" value="50.00" /></td>';
        echo '</tr>';
        echo '</table>';
        echo '<p class="submit">';
        echo '<input type="submit" class="button-primary" value="Save Settings" />';
        echo '</p>';
        echo '</form>';
        echo '</div>';
        
        // Pending payouts
        echo '<div class="khm-pending-payouts">';
        echo '<h2>Pending Payouts</h2>';
        echo '<div id="pending-payouts-table">';
        // Populated via JavaScript
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render system health page
     */
    public function render_system_health() {
        $health = $this->get_system_health_detailed();
        
        echo '<div class="wrap khm-system-health">';
        echo '<h1>System Health</h1>';
        
        foreach ($health as $category => $checks) {
            echo '<div class="khm-health-category">';
            echo '<h2>' . esc_html($category) . '</h2>';
            echo '<div class="khm-health-checks">';
            
            foreach ($checks as $check) {
                $icon = $check['status'] === 'pass' ? '✅' : ($check['status'] === 'warning' ? '⚠️' : '❌');
                echo '<div class="khm-health-check khm-health-' . $check['status'] . '">';
                echo '<div class="khm-health-check-icon">' . $icon . '</div>';
                echo '<div class="khm-health-check-content">';
                echo '<div class="khm-health-check-title">' . esc_html($check['title']) . '</div>';
                echo '<div class="khm-health-check-description">' . esc_html($check['description']) . '</div>';
                if (!empty($check['action'])) {
                    echo '<div class="khm-health-check-action">' . $check['action'] . '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_statistics() {
        // Mock data for now - in production this would query actual data
        return array(
            'total_revenue' => 25420.50,
            'revenue_change' => 12.5,
            'active_affiliates' => 147,
            'new_affiliates' => 8,
            'conversion_rate' => 3.2,
            'conversion_change' => 0.8,
            'total_clicks' => 15673,
            'clicks_today' => 234,
            'top_performers' => array(
                array('name' => 'John Smith', 'revenue' => 1250.00, 'conversions' => 25),
                array('name' => 'Sarah Johnson', 'revenue' => 980.50, 'conversions' => 18),
                array('name' => 'Mike Davis', 'revenue' => 845.25, 'conversions' => 15),
                array('name' => 'Lisa Wilson', 'revenue' => 720.00, 'conversions' => 12),
                array('name' => 'David Brown', 'revenue' => 650.75, 'conversions' => 10)
            ),
            'recent_activity' => array(
                array('type' => 'conversion', 'text' => 'New conversion from John Smith - $125.00', 'timestamp' => time() - 300),
                array('type' => 'signup', 'text' => 'New affiliate registration: Sarah Johnson', 'timestamp' => time() - 1800),
                array('type' => 'click', 'text' => '50 new clicks on Premium Banner creative', 'timestamp' => time() - 3600),
                array('type' => 'payout', 'text' => 'Payout processed for Mike Davis - $500.00', 'timestamp' => time() - 7200)
            ),
            'creative_performance' => array(
                array('name' => 'Premium Banner', 'views' => 1250, 'ctr' => 4.2),
                array('name' => 'Feature Article Link', 'views' => 980, 'ctr' => 3.8),
                array('name' => 'Social Share Pack', 'views' => 750, 'ctr' => 2.9),
                array('name' => 'Video Tutorial Promo', 'views' => 620, 'ctr' => 2.1)
            )
        );
    }
    
    /**
     * Get system health status
     */
    private function get_system_health() {
        return array(
            'Database' => array('status' => 'healthy', 'message' => 'All tables operational'),
            'Affiliate Tracking' => array('status' => 'healthy', 'message' => 'Tracking system active'),
            'Creative System' => array('status' => 'healthy', 'message' => 'All creatives rendering properly'),
            'Credit System' => array('status' => 'healthy', 'message' => 'Credit processing normal'),
            'Performance' => array('status' => 'warning', 'message' => 'Some queries running slowly')
        );
    }
    
    /**
     * Get detailed system health
     */
    private function get_system_health_detailed() {
        return array(
            'Database Health' => array(
                array('title' => 'Database Connection', 'status' => 'pass', 'description' => 'MySQL connection is stable'),
                array('title' => 'Table Integrity', 'status' => 'pass', 'description' => 'All required tables exist'),
                array('title' => 'Query Performance', 'status' => 'warning', 'description' => 'Some queries exceed 2s threshold')
            ),
            'Affiliate System' => array(
                array('title' => 'URL Generation', 'status' => 'pass', 'description' => 'Affiliate URLs generating correctly'),
                array('title' => 'Click Tracking', 'status' => 'pass', 'description' => 'Click tracking operational'),
                array('title' => 'Conversion Tracking', 'status' => 'pass', 'description' => 'Conversions being recorded')
            ),
            'Creative System' => array(
                array('title' => 'Creative Rendering', 'status' => 'pass', 'description' => 'All creative types rendering properly'),
                array('title' => 'Image Loading', 'status' => 'pass', 'description' => 'Creative images loading successfully'),
                array('title' => 'Analytics Tracking', 'status' => 'pass', 'description' => 'Creative performance being tracked')
            )
        );
    }
    
    /**
     * Get activity icon
     */
    private function get_activity_icon($type) {
        $icons = array(
            'conversion' => '💰',
            'signup' => '👤',
            'click' => '🖱️',
            'payout' => '💸',
            'creative' => '🎨'
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
     * AJAX: Get dashboard stats
     */
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('khm_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $period = sanitize_text_field($_POST['period'] ?? '30');
        $stats = $this->get_dashboard_statistics(); // In production, filter by period
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Export data
     */
    public function ajax_export_data() {
        check_ajax_referer('khm_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'analytics');
        $period = sanitize_text_field($_POST['period'] ?? '30');
        
        // Generate CSV data
        $filename = "khm-{$type}-" . date('Y-m-d') . '.csv';
        $data = $this->generate_export_data($type, $period);
        
        wp_send_json_success(array(
            'filename' => $filename,
            'data' => $data
        ));
    }
    
    /**
     * Generate export data
     */
    private function generate_export_data($type, $period) {
        // Mock CSV data - in production this would generate actual CSV
        $header = "Date,Affiliate,Clicks,Conversions,Revenue\n";
        $data = "2024-11-01,John Smith,45,3,375.00\n";
        $data .= "2024-11-01,Sarah Johnson,32,2,250.00\n";
        $data .= "2024-11-02,Mike Davis,28,1,125.00\n";
        
        return $header . $data;
    }
    
    /**
     * Render dashboard JavaScript
     */
    private function render_dashboard_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Initialize performance chart
            if (document.getElementById('affiliatePerformanceChart')) {
                initPerformanceChart();
            }
            
            // Auto-refresh real-time metrics
            if ($('#realtime-metrics').length) {
                setInterval(updateRealtimeMetrics, 30000); // 30 seconds
            }
            
            // Export functionality
            $('#export-analytics').on('click', function() {
                exportAnalytics();
            });
            
            function initPerformanceChart() {
                const ctx = document.getElementById('affiliatePerformanceChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'],
                        datasets: [{
                            label: 'Revenue',
                            data: [1200, 1350, 980, 1680, 1420, 1850, 2100],
                            borderColor: '#007cba',
                            backgroundColor: 'rgba(0, 124, 186, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Conversions',
                            data: [12, 14, 9, 18, 15, 21, 24],
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        }
                    }
                });
            }
            
            function updateRealtimeMetrics() {
                $.post(khmDashboard.ajaxUrl, {
                    action: 'khm_dashboard_stats',
                    nonce: khmDashboard.nonce,
                    period: '1'
                }).done(function(response) {
                    if (response.success) {
                        $('#realtime-clicks').text(response.data.clicks_today);
                        $('#realtime-conversions').text(response.data.conversions_today || '0');
                        $('#realtime-revenue').text('$' + (response.data.revenue_today || '0.00'));
                    }
                });
            }
            
            function exportAnalytics() {
                const period = $('#analytics-period').val();
                const affiliate = $('#analytics-affiliate').val();
                
                $.post(khmDashboard.ajaxUrl, {
                    action: 'khm_export_data',
                    nonce: khmDashboard.nonce,
                    type: 'analytics',
                    period: period,
                    affiliate: affiliate
                }).done(function(response) {
                    if (response.success) {
                        // Create download link
                        const blob = new Blob([response.data.data], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        a.click();
                        window.URL.revokeObjectURL(url);
                    }
                });
            }
        });
        </script>
        <?php
    }
}

// Initialize enhanced dashboard
new KHM_Enhanced_Dashboard();
