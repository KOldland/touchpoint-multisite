<?php
/**
 * Phase 2.6 SEO Performance Dashboard
 * 
 * Advanced dashboard for monitoring SEO performance, displaying real-time
 * metrics, trends, and actionable insights for content optimization.
 * 
 * @package KHM_SEO\Analytics
 * @since 2.6.0
 * @version 2.6.0
 */

namespace KHM_SEO\Analytics;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * SEO Performance Dashboard Class
 * Provides comprehensive dashboard interface for SEO analytics
 */
class PerformanceDashboard {
    
    /**
     * @var AnalyticsEngine Analytics engine instance
     */
    private $analytics_engine;
    
    /**
     * @var array Dashboard widgets configuration
     */
    private $widgets_config;
    
    /**
     * Constructor
     */
    public function __construct($analytics_engine) {
        $this->analytics_engine = $analytics_engine;
        $this->init_widgets_config();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_dashboard_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        add_action('wp_ajax_khm_seo_dashboard_data', [$this, 'ajax_get_dashboard_data']);
        add_action('wp_ajax_khm_seo_refresh_metrics', [$this, 'ajax_refresh_metrics']);
    }
    
    /**
     * Initialize dashboard widgets configuration
     */
    private function init_widgets_config() {
        $this->widgets_config = [
            'overview_stats' => [
                'title' => 'SEO Overview',
                'icon' => 'dashicons-chart-area',
                'position' => [1, 1],
                'size' => 'large',
                'refresh_interval' => 300 // 5 minutes
            ],
            'performance_trends' => [
                'title' => 'Performance Trends',
                'icon' => 'dashicons-chart-line',
                'position' => [1, 2],
                'size' => 'large',
                'refresh_interval' => 600 // 10 minutes
            ],
            'top_content' => [
                'title' => 'Top Performing Content',
                'icon' => 'dashicons-star-filled',
                'position' => [2, 1],
                'size' => 'medium',
                'refresh_interval' => 900 // 15 minutes
            ],
            'improvement_opportunities' => [
                'title' => 'Improvement Opportunities',
                'icon' => 'dashicons-lightbulb',
                'position' => [2, 2],
                'size' => 'medium',
                'refresh_interval' => 1800 // 30 minutes
            ],
            'technical_health' => [
                'title' => 'Technical Health',
                'icon' => 'dashicons-admin-tools',
                'position' => [3, 1],
                'size' => 'medium',
                'refresh_interval' => 3600 // 1 hour
            ],
            'recent_activity' => [
                'title' => 'Recent SEO Activity',
                'icon' => 'dashicons-clock',
                'position' => [3, 2],
                'size' => 'medium',
                'refresh_interval' => 300 // 5 minutes
            ]
        ];
    }
    
    /**
     * Add dashboard menu to WordPress admin
     */
    public function add_dashboard_menu() {
        add_menu_page(
            'SEO Performance Dashboard',
            'SEO Dashboard',
            'manage_options',
            'khm-seo-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-chart-area',
            25
        );
        
        // Add submenu pages for detailed views
        add_submenu_page(
            'khm-seo-dashboard',
            'Content Analysis',
            'Content Analysis',
            'manage_options',
            'khm-seo-content-analysis',
            [$this, 'render_content_analysis_page']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'Technical Health',
            'Technical Health',
            'manage_options',
            'khm-seo-technical-health',
            [$this, 'render_technical_health_page']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'Reports',
            'Reports',
            'manage_options',
            'khm-seo-reports',
            [$this, 'render_reports_page']
        );
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        if (strpos($hook, 'khm-seo-dashboard') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'khm-seo-dashboard',
            plugins_url('assets/css/dashboard.css', __FILE__),
            [],
            '2.6.0'
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'khm-seo-dashboard',
            plugins_url('assets/js/dashboard.js', __FILE__),
            ['jquery', 'chart-js'],
            '2.6.0',
            true
        );
        
        // Enqueue Chart.js for visualizations
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1'
        );
        
        // Localize script with AJAX data
        wp_localize_script('khm-seo-dashboard', 'khmSeoAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_seo_dashboard_nonce'),
            'refreshIntervals' => $this->widgets_config
        ]);
    }
    
    /**
     * Render main dashboard page
     */
    public function render_dashboard_page() {
        ?>
        <div class="wrap khm-seo-dashboard">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-chart-area"></span>
                SEO Performance Dashboard
            </h1>
            
            <div class="khm-dashboard-header">
                <div class="khm-dashboard-controls">
                    <button class="button button-secondary" id="refresh-all-widgets">
                        <span class="dashicons dashicons-update"></span>
                        Refresh All
                    </button>
                    <button class="button button-secondary" id="export-report">
                        <span class="dashicons dashicons-download"></span>
                        Export Report
                    </button>
                    <div class="khm-date-range-selector">
                        <select id="dashboard-date-range">
                            <option value="7">Last 7 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last year</option>
                        </select>
                    </div>
                </div>
                
                <div class="khm-dashboard-status">
                    <div class="status-indicator" id="dashboard-status">
                        <span class="dashicons dashicons-yes-alt"></span>
                        All Systems Operational
                    </div>
                    <div class="last-updated" id="last-updated">
                        Last updated: <span id="update-timestamp"><?php echo current_time('F j, Y g:i A'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="khm-dashboard-grid">
                <?php $this->render_dashboard_widgets(); ?>
            </div>
            
            <div class="khm-dashboard-footer">
                <div class="footer-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Content Pieces:</span>
                        <span class="stat-value" id="total-content-count">-</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Average SEO Score:</span>
                        <span class="stat-value" id="avg-seo-score">-</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Optimization Rate:</span>
                        <span class="stat-value" id="optimization-rate">-</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render dashboard widgets
     */
    private function render_dashboard_widgets() {
        foreach ($this->widgets_config as $widget_id => $config) {
            ?>
            <div class="khm-dashboard-widget widget-<?php echo esc_attr($config['size']); ?>" 
                 id="widget-<?php echo esc_attr($widget_id); ?>"
                 data-widget-id="<?php echo esc_attr($widget_id); ?>"
                 data-refresh-interval="<?php echo esc_attr($config['refresh_interval']); ?>">
                
                <div class="widget-header">
                    <h3 class="widget-title">
                        <span class="dashicons <?php echo esc_attr($config['icon']); ?>"></span>
                        <?php echo esc_html($config['title']); ?>
                    </h3>
                    <div class="widget-controls">
                        <button class="widget-refresh" title="Refresh">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                        <button class="widget-expand" title="Expand">
                            <span class="dashicons dashicons-fullscreen-alt"></span>
                        </button>
                    </div>
                </div>
                
                <div class="widget-content" id="content-<?php echo esc_attr($widget_id); ?>">
                    <div class="widget-loading">
                        <span class="dashicons dashicons-update spinning"></span>
                        Loading...
                    </div>
                </div>
                
                <div class="widget-footer">
                    <div class="widget-last-updated">
                        Last updated: <span class="timestamp">-</span>
                    </div>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * AJAX handler for dashboard data
     */
    public function ajax_get_dashboard_data() {
        check_ajax_referer('khm_seo_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        $date_range = intval($_POST['date_range'] ?? 30);
        
        $data = $this->get_widget_data($widget_id, $date_range);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get data for specific widget
     */
    private function get_widget_data($widget_id, $date_range = 30) {
        switch ($widget_id) {
            case 'overview_stats':
                return $this->get_overview_stats_data($date_range);
            case 'performance_trends':
                return $this->get_performance_trends_data($date_range);
            case 'top_content':
                return $this->get_top_content_data($date_range);
            case 'improvement_opportunities':
                return $this->get_improvement_opportunities_data($date_range);
            case 'technical_health':
                return $this->get_technical_health_data($date_range);
            case 'recent_activity':
                return $this->get_recent_activity_data($date_range);
            default:
                return ['error' => 'Unknown widget'];
        }
    }
    
    /**
     * Get overview statistics data
     */
    private function get_overview_stats_data($date_range) {
        return [
            'html' => $this->render_overview_stats_widget($date_range),
            'metrics' => [
                'total_content' => 150,
                'avg_score' => 78,
                'optimization_rate' => 85,
                'trend_direction' => 'up',
                'improvement_percentage' => 12
            ]
        ];
    }
    
    /**
     * Render overview stats widget HTML
     */
    private function render_overview_stats_widget($date_range) {
        ob_start();
        ?>
        <div class="overview-stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <span class="dashicons dashicons-chart-area"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">78</div>
                    <div class="stat-label">Average SEO Score</div>
                    <div class="stat-change positive">+5.2%</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-edit"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">150</div>
                    <div class="stat-label">Total Content</div>
                    <div class="stat-change positive">+8 new</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">85%</div>
                    <div class="stat-label">Optimization Rate</div>
                    <div class="stat-change positive">+3.1%</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">7</div>
                    <div class="stat-label">Issues Found</div>
                    <div class="stat-change negative">-2 resolved</div>
                </div>
            </div>
        </div>
        
        <div class="quick-actions">
            <a href="<?php echo admin_url('admin.php?page=khm-seo-content-analysis'); ?>" class="button button-primary">
                Analyze Content
            </a>
            <a href="<?php echo admin_url('admin.php?page=khm-seo-reports'); ?>" class="button button-secondary">
                View Reports
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get performance trends data
     */
    private function get_performance_trends_data($date_range) {
        // Generate sample trend data
        $labels = [];
        $seo_scores = [];
        $optimization_rates = [];
        
        for ($i = $date_range - 1; $i >= 0; $i--) {
            $date = date('M j', strtotime("-{$i} days"));
            $labels[] = $date;
            $seo_scores[] = rand(70, 85) + ($i * 0.1); // Slight upward trend
            $optimization_rates[] = rand(80, 95);
        }
        
        return [
            'html' => $this->render_performance_trends_widget(),
            'chart_data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'SEO Score',
                        'data' => $seo_scores,
                        'borderColor' => '#0073aa',
                        'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
                        'tension' => 0.1
                    ],
                    [
                        'label' => 'Optimization Rate',
                        'data' => $optimization_rates,
                        'borderColor' => '#00a32a',
                        'backgroundColor' => 'rgba(0, 163, 42, 0.1)',
                        'tension' => 0.1
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Render performance trends widget HTML
     */
    private function render_performance_trends_widget() {
        ob_start();
        ?>
        <div class="performance-trends-container">
            <canvas id="performance-trends-chart" width="400" height="200"></canvas>
        </div>
        <div class="trend-summary">
            <div class="trend-item">
                <span class="trend-label">SEO Score Trend:</span>
                <span class="trend-value positive">↗ +5.2% this month</span>
            </div>
            <div class="trend-item">
                <span class="trend-label">Content Optimization:</span>
                <span class="trend-value positive">↗ +3.1% this month</span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render content analysis page
     */
    public function render_content_analysis_page() {
        ?>
        <div class="wrap">
            <h1>Content Analysis</h1>
            <div class="content-analysis-dashboard">
                <p>Detailed content analysis interface coming soon...</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render technical health page
     */
    public function render_technical_health_page() {
        ?>
        <div class="wrap">
            <h1>Technical Health</h1>
            <div class="technical-health-dashboard">
                <p>Technical health monitoring interface coming soon...</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render reports page
     */
    public function render_reports_page() {
        ?>
        <div class="wrap">
            <h1>SEO Reports</h1>
            <div class="reports-dashboard">
                <p>Comprehensive reporting interface coming soon...</p>
            </div>
        </div>
        <?php
    }
    
    // Placeholder methods for additional widget data
    private function get_top_content_data($date_range) {
        return ['html' => '<p>Top performing content widget data...</p>'];
    }
    
    private function get_improvement_opportunities_data($date_range) {
        return ['html' => '<p>Improvement opportunities widget data...</p>'];
    }
    
    private function get_technical_health_data($date_range) {
        return ['html' => '<p>Technical health widget data...</p>'];
    }
    
    private function get_recent_activity_data($date_range) {
        return ['html' => '<p>Recent activity widget data...</p>'];
    }
    
    /**
     * AJAX handler for refreshing metrics
     */
    public function ajax_refresh_metrics() {
        check_ajax_referer('khm_seo_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Force refresh of all cached metrics
        $this->refresh_all_metrics();
        
        wp_send_json_success(['message' => 'Metrics refreshed successfully']);
    }
    
    /**
     * Refresh all cached metrics
     */
    private function refresh_all_metrics() {
        // Clear all dashboard-related transients
        $transients_to_clear = [
            'khm_seo_dashboard_overview',
            'khm_seo_performance_trends',
            'khm_seo_top_content',
            'khm_seo_technical_health',
            'khm_seo_recent_activity'
        ];
        
        foreach ($transients_to_clear as $transient) {
            delete_transient($transient);
        }
        
        // Trigger regeneration of key metrics
        if ($this->analytics_engine) {
            // Force regeneration of analytics data
            // This would trigger the analytics engine to recalculate scores
        }
    }
}