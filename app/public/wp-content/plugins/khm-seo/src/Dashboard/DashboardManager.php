<?php
/**
 * Dashboard Manager - Advanced SEO Analytics Dashboard
 * 
 * Provides comprehensive SEO performance dashboard with visual analytics,
 * site-wide metrics, content recommendations, and actionable insights.
 * 
 * @package KHM_SEO\Dashboard
 * @since 2.1.0
 */

namespace KHM_SEO\Dashboard;

use KHM_SEO\Core\AnalysisEngine;
use KHM_SEO\Dashboard\Analytics\PerformanceTracker;
use KHM_SEO\Dashboard\Analytics\ContentAnalyzer;
use KHM_SEO\Dashboard\Analytics\TechnicalAnalyzer;
use KHM_SEO\Dashboard\Widgets\WidgetManager;

/**
 * Dashboard Manager Class
 */
class DashboardManager {
    /**
     * @var AnalysisEngine
     */
    private $analysis_engine;

    /**
     * @var PerformanceTracker
     */
    private $performance_tracker;

    /**
     * @var ContentAnalyzer
     */
    private $content_analyzer;

    /**
     * @var TechnicalAnalyzer
     */
    private $technical_analyzer;

    /**
     * @var WidgetManager
     */
    private $widget_manager;

    /**
     * @var array Dashboard configuration
     */
    private $config;

    /**
     * @var array Cached dashboard data
     */
    private $cache;

    /**
     * Constructor
     *
     * @param AnalysisEngine $analysis_engine Analysis engine instance
     */
    public function __construct(AnalysisEngine $analysis_engine) {
        $this->analysis_engine = $analysis_engine;
        $this->init_config();
        $this->init_components();
    }

    /**
     * Initialize dashboard configuration
     */
    private function init_config() {
        $this->config = [
            'dashboard_slug' => 'khm-seo-dashboard',
            'capability' => 'manage_options',
            'cache_duration' => 12 * HOUR_IN_SECONDS, // 12 hours
            'refresh_interval' => 5 * MINUTE_IN_SECONDS, // 5 minutes for real-time data
            'charts' => [
                'performance_chart' => [
                    'enabled' => true,
                    'timeframe' => 30, // days
                    'metrics' => ['score', 'issues', 'improvements']
                ],
                'content_distribution' => [
                    'enabled' => true,
                    'chart_type' => 'doughnut'
                ],
                'technical_health' => [
                    'enabled' => true,
                    'chart_type' => 'radar'
                ]
            ],
            'widgets' => [
                'overview_stats' => ['enabled' => true, 'priority' => 1],
                'recent_analysis' => ['enabled' => true, 'priority' => 2],
                'top_issues' => ['enabled' => true, 'priority' => 3],
                'content_opportunities' => ['enabled' => true, 'priority' => 4],
                'technical_insights' => ['enabled' => true, 'priority' => 5],
                'performance_trends' => ['enabled' => true, 'priority' => 6]
            ]
        ];
    }

    /**
     * Initialize dashboard components
     */
    private function init_components() {
        $this->performance_tracker = new PerformanceTracker();
        $this->content_analyzer = new ContentAnalyzer($this->analysis_engine);
        $this->technical_analyzer = new TechnicalAnalyzer();
        $this->widget_manager = new WidgetManager();
        
        $this->cache = [];
    }

    /**
     * Initialize the dashboard
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_dashboard_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        add_action('wp_ajax_khm_seo_dashboard_data', [$this, 'handle_dashboard_data_request']);
        add_action('wp_ajax_khm_seo_refresh_widget', [$this, 'handle_widget_refresh']);
        add_action('wp_ajax_khm_seo_export_report', [$this, 'handle_export_report']);
        
        // Schedule background analysis for dashboard data
        add_action('khm_seo_dashboard_background_analysis', [$this, 'perform_background_analysis']);
        
        // Admin notices for dashboard insights
        add_action('admin_notices', [$this, 'display_dashboard_notices']);
    }

    /**
     * Add dashboard menu to WordPress admin
     */
    public function add_dashboard_menu() {
        $hook = add_menu_page(
            __('SEO Dashboard', 'khm-seo'),
            __('SEO Dashboard', 'khm-seo'),
            $this->config['capability'],
            $this->config['dashboard_slug'],
            [$this, 'render_dashboard_page'],
            'data:image/svg+xml;base64,' . base64_encode('<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>'),
            25
        );

        // Add dashboard-specific help tab
        add_action("load-$hook", [$this, 'add_dashboard_help_tab']);
        
        // Add submenus for different dashboard sections
        add_submenu_page(
            $this->config['dashboard_slug'],
            __('Performance Analytics', 'khm-seo'),
            __('Performance', 'khm-seo'),
            $this->config['capability'],
            'khm-seo-performance',
            [$this, 'render_performance_page']
        );

        add_submenu_page(
            $this->config['dashboard_slug'],
            __('Content Analysis', 'khm-seo'),
            __('Content', 'khm-seo'),
            $this->config['capability'],
            'khm-seo-content',
            [$this, 'render_content_page']
        );

        add_submenu_page(
            $this->config['dashboard_slug'],
            __('Technical SEO', 'khm-seo'),
            __('Technical', 'khm-seo'),
            $this->config['capability'],
            'khm-seo-technical',
            [$this, 'render_technical_page']
        );
    }

    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        if (strpos($hook, 'khm-seo') === false) {
            return;
        }

        // Chart.js for data visualization
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
            [],
            '4.4.0',
            true
        );

        // Dashboard JavaScript
        wp_enqueue_script(
            'khm-seo-dashboard',
            plugin_dir_url(__FILE__) . '../../assets/js/dashboard/dashboard.js',
            ['jquery', 'chartjs'],
            '2.1.0',
            true
        );

        // Dashboard CSS
        wp_enqueue_style(
            'khm-seo-dashboard',
            plugin_dir_url(__FILE__) . '../../assets/css/dashboard.css',
            [],
            '2.1.0'
        );

        // Localize script with dashboard data and configuration
        wp_localize_script('khm-seo-dashboard', 'khmSeoDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_seo_dashboard_nonce'),
            'config' => $this->config,
            'strings' => [
                'loading' => __('Loading...', 'khm-seo'),
                'error' => __('Error loading data', 'khm-seo'),
                'refresh' => __('Refresh', 'khm-seo'),
                'export' => __('Export Report', 'khm-seo'),
                'noData' => __('No data available', 'khm-seo'),
                'lastUpdated' => __('Last updated', 'khm-seo'),
                'viewDetails' => __('View Details', 'khm-seo'),
                'fixIssue' => __('Fix Issue', 'khm-seo')
            ],
            'initialData' => $this->get_initial_dashboard_data()
        ]);
    }

    /**
     * Get initial dashboard data for page load
     */
    private function get_initial_dashboard_data() {
        $cache_key = 'khm_seo_dashboard_initial_data';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }

        $data = [
            'overview' => $this->get_overview_stats(),
            'performance' => $this->get_performance_summary(),
            'content' => $this->get_content_summary(),
            'technical' => $this->get_technical_summary(),
            'timestamp' => time()
        ];

        set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);
        return $data;
    }

    /**
     * Render main dashboard page
     */
    public function render_dashboard_page() {
        if (!current_user_can($this->config['capability'])) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $overview_stats = $this->get_overview_stats();
        $recent_analyses = $this->get_recent_analyses(5);
        $top_issues = $this->get_top_issues(10);
        
        ?>
        <div class="wrap khm-seo-dashboard">
            <div class="khm-seo-dashboard-header">
                <div class="dashboard-title-section">
                    <h1 class="dashboard-title">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php _e('SEO Dashboard', 'khm-seo'); ?>
                    </h1>
                    <p class="dashboard-subtitle">
                        <?php _e('Comprehensive SEO performance analytics and insights for your website', 'khm-seo'); ?>
                    </p>
                </div>
                
                <div class="dashboard-actions">
                    <button class="button button-secondary" id="refresh-dashboard">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh Data', 'khm-seo'); ?>
                    </button>
                    <button class="button button-primary" id="export-report">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export Report', 'khm-seo'); ?>
                    </button>
                </div>
            </div>

            <!-- Dashboard Navigation -->
            <nav class="dashboard-nav">
                <ul class="dashboard-tabs">
                    <li><a href="#overview" class="tab-link active" data-tab="overview">
                        <span class="tab-icon">📊</span>
                        <?php _e('Overview', 'khm-seo'); ?>
                    </a></li>
                    <li><a href="#performance" class="tab-link" data-tab="performance">
                        <span class="tab-icon">📈</span>
                        <?php _e('Performance', 'khm-seo'); ?>
                    </a></li>
                    <li><a href="#content" class="tab-link" data-tab="content">
                        <span class="tab-icon">📝</span>
                        <?php _e('Content', 'khm-seo'); ?>
                    </a></li>
                    <li><a href="#technical" class="tab-link" data-tab="technical">
                        <span class="tab-icon">⚙️</span>
                        <?php _e('Technical', 'khm-seo'); ?>
                    </a></li>
                    <li><a href="#insights" class="tab-link" data-tab="insights">
                        <span class="tab-icon">💡</span>
                        <?php _e('Insights', 'khm-seo'); ?>
                    </a></li>
                </ul>
            </nav>

            <!-- Overview Tab Content -->
            <div id="overview-tab" class="dashboard-tab-content active">
                <!-- Overview Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card overall-score">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-content">
                            <div class="stat-value" data-value="<?php echo esc_attr($overview_stats['overall_score']); ?>">
                                <?php echo esc_html($overview_stats['overall_score']); ?>
                            </div>
                            <div class="stat-label"><?php _e('Overall SEO Score', 'khm-seo'); ?></div>
                            <div class="stat-change <?php echo $overview_stats['score_trend'] >= 0 ? 'positive' : 'negative'; ?>">
                                <span class="trend-icon"><?php echo $overview_stats['score_trend'] >= 0 ? '▲' : '▼'; ?></span>
                                <?php echo esc_html(abs($overview_stats['score_trend'])); ?> <?php _e('points', 'khm-seo'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card total-pages">
                        <div class="stat-icon">📄</div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo esc_html($overview_stats['total_pages']); ?></div>
                            <div class="stat-label"><?php _e('Pages Analyzed', 'khm-seo'); ?></div>
                            <div class="stat-meta"><?php echo esc_html($overview_stats['optimized_pages']); ?> <?php _e('optimized', 'khm-seo'); ?></div>
                        </div>
                    </div>

                    <div class="stat-card critical-issues">
                        <div class="stat-icon">🚨</div>
                        <div class="stat-content">
                            <div class="stat-value critical"><?php echo esc_html($overview_stats['critical_issues']); ?></div>
                            <div class="stat-label"><?php _e('Critical Issues', 'khm-seo'); ?></div>
                            <div class="stat-meta"><?php echo esc_html($overview_stats['total_issues']); ?> <?php _e('total issues', 'khm-seo'); ?></div>
                        </div>
                    </div>

                    <div class="stat-card quick-wins">
                        <div class="stat-icon">⚡</div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo esc_html($overview_stats['quick_wins']); ?></div>
                            <div class="stat-label"><?php _e('Quick Wins', 'khm-seo'); ?></div>
                            <div class="stat-meta"><?php echo esc_html($overview_stats['avg_improvement']); ?> <?php _e('avg improvement', 'khm-seo'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Main Dashboard Content Grid -->
                <div class="dashboard-content-grid">
                    <!-- Performance Chart -->
                    <div class="dashboard-widget chart-widget">
                        <div class="widget-header">
                            <h3><?php _e('SEO Performance Trend', 'khm-seo'); ?></h3>
                            <div class="widget-controls">
                                <select id="performance-timeframe">
                                    <option value="7"><?php _e('Last 7 days', 'khm-seo'); ?></option>
                                    <option value="30" selected><?php _e('Last 30 days', 'khm-seo'); ?></option>
                                    <option value="90"><?php _e('Last 90 days', 'khm-seo'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="widget-content">
                            <canvas id="performance-chart" data-chart="performance"></canvas>
                        </div>
                    </div>

                    <!-- Content Distribution -->
                    <div class="dashboard-widget chart-widget">
                        <div class="widget-header">
                            <h3><?php _e('Content Quality Distribution', 'khm-seo'); ?></h3>
                        </div>
                        <div class="widget-content">
                            <canvas id="content-distribution-chart" data-chart="content-distribution"></canvas>
                        </div>
                    </div>

                    <!-- Recent Analyses -->
                    <div class="dashboard-widget list-widget">
                        <div class="widget-header">
                            <h3><?php _e('Recent Analyses', 'khm-seo'); ?></h3>
                            <a href="<?php echo admin_url('admin.php?page=khm-seo-content'); ?>" class="view-all-link">
                                <?php _e('View All', 'khm-seo'); ?>
                            </a>
                        </div>
                        <div class="widget-content">
                            <div class="recent-analyses-list">
                                <?php foreach ($recent_analyses as $analysis): ?>
                                    <div class="analysis-item">
                                        <div class="analysis-info">
                                            <div class="analysis-title">
                                                <a href="<?php echo get_edit_post_link($analysis['post_id']); ?>">
                                                    <?php echo esc_html($analysis['title']); ?>
                                                </a>
                                            </div>
                                            <div class="analysis-meta">
                                                <?php echo esc_html($analysis['post_type']); ?> • 
                                                <?php echo esc_html(human_time_diff($analysis['analyzed_at'])); ?> <?php _e('ago', 'khm-seo'); ?>
                                            </div>
                                        </div>
                                        <div class="analysis-score">
                                            <div class="score-badge score-<?php echo esc_attr($this->get_score_class($analysis['score'])); ?>">
                                                <?php echo esc_html($analysis['score']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Top Issues -->
                    <div class="dashboard-widget list-widget">
                        <div class="widget-header">
                            <h3><?php _e('Top Issues to Fix', 'khm-seo'); ?></h3>
                            <a href="<?php echo admin_url('admin.php?page=khm-seo-technical'); ?>" class="view-all-link">
                                <?php _e('View All', 'khm-seo'); ?>
                            </a>
                        </div>
                        <div class="widget-content">
                            <div class="issues-list">
                                <?php foreach ($top_issues as $issue): ?>
                                    <div class="issue-item priority-<?php echo esc_attr($issue['severity']); ?>">
                                        <div class="issue-info">
                                            <div class="issue-title">
                                                <?php echo esc_html($issue['title']); ?>
                                            </div>
                                            <div class="issue-description">
                                                <?php echo esc_html($issue['description']); ?>
                                            </div>
                                        </div>
                                        <div class="issue-actions">
                                            <span class="affected-pages">
                                                <?php echo esc_html($issue['affected_pages']); ?> <?php _e('pages', 'khm-seo'); ?>
                                            </span>
                                            <button class="button button-small fix-issue-btn" data-issue="<?php echo esc_attr($issue['id']); ?>">
                                                <?php _e('Fix', 'khm-seo'); ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Other tab contents will be loaded dynamically -->
            <div id="performance-tab" class="dashboard-tab-content">
                <div class="tab-loading">
                    <div class="loading-spinner"></div>
                    <p><?php _e('Loading performance data...', 'khm-seo'); ?></p>
                </div>
            </div>

            <div id="content-tab" class="dashboard-tab-content">
                <div class="tab-loading">
                    <div class="loading-spinner"></div>
                    <p><?php _e('Loading content analysis...', 'khm-seo'); ?></p>
                </div>
            </div>

            <div id="technical-tab" class="dashboard-tab-content">
                <div class="tab-loading">
                    <div class="loading-spinner"></div>
                    <p><?php _e('Loading technical analysis...', 'khm-seo'); ?></p>
                </div>
            </div>

            <div id="insights-tab" class="dashboard-tab-content">
                <div class="tab-loading">
                    <div class="loading-spinner"></div>
                    <p><?php _e('Loading insights...', 'khm-seo'); ?></p>
                </div>
            </div>
        </div>

        <!-- Dashboard Modals -->
        <div id="export-modal" class="khm-seo-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Export SEO Report', 'khm-seo'); ?></h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="export-form">
                        <div class="form-group">
                            <label for="export-format"><?php _e('Export Format', 'khm-seo'); ?></label>
                            <select id="export-format" name="format">
                                <option value="pdf"><?php _e('PDF Report', 'khm-seo'); ?></option>
                                <option value="csv"><?php _e('CSV Data', 'khm-seo'); ?></option>
                                <option value="json"><?php _e('JSON Data', 'khm-seo'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="export-timeframe"><?php _e('Time Period', 'khm-seo'); ?></label>
                            <select id="export-timeframe" name="timeframe">
                                <option value="7"><?php _e('Last 7 days', 'khm-seo'); ?></option>
                                <option value="30" selected><?php _e('Last 30 days', 'khm-seo'); ?></option>
                                <option value="90"><?php _e('Last 90 days', 'khm-seo'); ?></option>
                                <option value="all"><?php _e('All time', 'khm-seo'); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="include_recommendations" checked>
                                <?php _e('Include optimization recommendations', 'khm-seo'); ?>
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="include_charts" checked>
                                <?php _e('Include performance charts', 'khm-seo'); ?>
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="button button-secondary modal-cancel"><?php _e('Cancel', 'khm-seo'); ?></button>
                    <button class="button button-primary" id="start-export"><?php _e('Export Report', 'khm-seo'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle dashboard data AJAX requests
     */
    public function handle_dashboard_data_request() {
        check_ajax_referer('khm_seo_dashboard_nonce', 'nonce');

        if (!current_user_can($this->config['capability'])) {
            wp_die(__('Insufficient permissions'));
        }

        $data_type = sanitize_text_field($_POST['data_type'] ?? '');
        $timeframe = intval($_POST['timeframe'] ?? 30);

        try {
            switch ($data_type) {
                case 'performance':
                    $data = $this->get_performance_data($timeframe);
                    break;
                case 'content':
                    $data = $this->get_content_data();
                    break;
                case 'technical':
                    $data = $this->get_technical_data();
                    break;
                case 'insights':
                    $data = $this->get_insights_data();
                    break;
                default:
                    throw new \Exception('Invalid data type');
            }

            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Get overview statistics
     */
    private function get_overview_stats() {
        $cache_key = 'khm_seo_overview_stats';
        $stats = get_transient($cache_key);

        if ($stats === false) {
            $stats = [
                'overall_score' => $this->calculate_overall_site_score(),
                'score_trend' => $this->calculate_score_trend(),
                'total_pages' => $this->get_total_analyzed_pages(),
                'optimized_pages' => $this->get_optimized_pages_count(),
                'critical_issues' => $this->get_critical_issues_count(),
                'total_issues' => $this->get_total_issues_count(),
                'quick_wins' => $this->get_quick_wins_count(),
                'avg_improvement' => $this->calculate_average_improvement_potential()
            ];

            set_transient($cache_key, $stats, $this->config['cache_duration']);
        }

        return $stats;
    }

    /**
     * Get recent analyses
     */
    private function get_recent_analyses($limit = 5) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ar.*, p.post_title as title, p.post_type 
             FROM {$table_name} ar 
             JOIN {$wpdb->posts} p ON ar.post_id = p.ID 
             WHERE p.post_status = 'publish' 
             ORDER BY ar.analyzed_at DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return array_map(function($result) {
            $analysis_data = json_decode($result['analysis_data'], true);
            return [
                'post_id' => $result['post_id'],
                'title' => $result['title'],
                'post_type' => ucfirst($result['post_type']),
                'score' => $analysis_data['overall_score'] ?? 0,
                'analyzed_at' => strtotime($result['analyzed_at'])
            ];
        }, $results);
    }

    /**
     * Get top issues across the site
     */
    private function get_top_issues($limit = 10) {
        // This would aggregate issues across all analyzed content
        // For now, returning sample data structure
        return [
            [
                'id' => 'missing_meta_descriptions',
                'title' => 'Missing Meta Descriptions',
                'description' => 'Pages without meta descriptions for search results',
                'severity' => 'high',
                'affected_pages' => 15
            ],
            [
                'id' => 'large_images',
                'title' => 'Large Unoptimized Images',
                'description' => 'Images that slow down page load times',
                'severity' => 'medium',
                'affected_pages' => 8
            ],
            [
                'id' => 'missing_alt_tags',
                'title' => 'Missing Image Alt Text',
                'description' => 'Images without descriptive alt attributes',
                'severity' => 'medium',
                'affected_pages' => 12
            ]
        ];
    }

    /**
     * Calculate overall site score
     */
    private function calculate_overall_site_score() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';
        
        $avg_score = $wpdb->get_var(
            "SELECT AVG(JSON_EXTRACT(analysis_data, '$.overall_score')) 
             FROM {$table_name} 
             WHERE analyzed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        return round($avg_score ?: 0);
    }

    /**
     * Calculate score trend
     */
    private function calculate_score_trend() {
        // Compare last 7 days vs previous 7 days
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';
        
        $recent_avg = $wpdb->get_var(
            "SELECT AVG(JSON_EXTRACT(analysis_data, '$.overall_score')) 
             FROM {$table_name} 
             WHERE analyzed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ) ?: 0;
        
        $previous_avg = $wpdb->get_var(
            "SELECT AVG(JSON_EXTRACT(analysis_data, '$.overall_score')) 
             FROM {$table_name} 
             WHERE analyzed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ) ?: 0;
        
        return round($recent_avg - $previous_avg, 1);
    }

    /**
     * Get helper methods for statistics
     */
    private function get_total_analyzed_pages() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';
        return (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$table_name}");
    }

    private function get_optimized_pages_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_seo_analysis_results';
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) 
             FROM {$table_name} 
             WHERE JSON_EXTRACT(analysis_data, '$.overall_score') >= 80"
        );
    }

    private function get_critical_issues_count() {
        // This would count issues marked as critical across all content
        return 3; // Placeholder
    }

    private function get_total_issues_count() {
        return 25; // Placeholder
    }

    private function get_quick_wins_count() {
        return 8; // Placeholder
    }

    private function calculate_average_improvement_potential() {
        return '+15%'; // Placeholder
    }

    /**
     * Get score class for styling
     */
    private function get_score_class($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'fair';
        return 'poor';
    }

    /**
     * Placeholder methods for other data
     */
    private function get_performance_summary() { return []; }
    private function get_content_summary() { return []; }
    private function get_technical_summary() { return []; }
    private function get_performance_data($timeframe) { return []; }
    private function get_content_data() { return []; }
    private function get_technical_data() { return []; }
    private function get_insights_data() { return []; }

    // Additional methods for performance page, content page, technical page, etc.
    public function render_performance_page() { /* Implementation */ }
    public function render_content_page() { /* Implementation */ }
    public function render_technical_page() { /* Implementation */ }
    public function handle_widget_refresh() { /* Implementation */ }
    public function handle_export_report() { /* Implementation */ }
    public function perform_background_analysis() { /* Implementation */ }
    public function display_dashboard_notices() { /* Implementation */ }
    public function add_dashboard_help_tab() { /* Implementation */ }
}