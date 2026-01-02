<?php
/**
 * Phase 2.6 Analytics & Reporting Module - Main Integration
 * 
 * This file integrates all Phase 2.6 analytics components and provides
 * a unified interface for the comprehensive SEO analytics and reporting system.
 * 
 * Components Integrated:
 * - AnalyticsEngine: Core scoring and analysis
 * - PerformanceDashboard: Interactive dashboard interface  
 * - ScoringSystem: Advanced SEO scoring algorithms
 * - ReportingEngine: Automated report generation
 * - AnalyticsDatabase: Data persistence and retrieval
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
 * Phase 2.6 Analytics Module
 * Main integration class for analytics and reporting functionality
 */
class Analytics26Module {
    
    /**
     * @var AnalyticsEngine Analytics engine instance
     */
    private $analytics_engine;
    
    /**
     * @var PerformanceDashboard Dashboard instance
     */
    private $performance_dashboard;
    
    /**
     * @var ScoringSystem Scoring system instance
     */
    private $scoring_system;
    
    /**
     * @var ReportingEngine Reporting engine instance
     */
    private $reporting_engine;
    
    /**
     * @var AnalyticsDatabase Database handler instance
     */
    private $analytics_database;
    
    /**
     * @var string Module version
     */
    private $version = '2.6.0';
    
    /**
     * @var bool Initialization status
     */
    private $initialized = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Initialize all analytics components
     */
    private function init_components() {
        // Initialize database first
        $this->analytics_database = new AnalyticsDatabase();
        
        // Initialize scoring system
        $this->scoring_system = new ScoringSystem($this->analytics_database);
        
        // Initialize analytics engine
        $this->analytics_engine = new AnalyticsEngine($this->analytics_database);
        
        // Initialize reporting engine
        $this->reporting_engine = new ReportingEngine($this->analytics_engine, $this->scoring_system);
        
        // Initialize performance dashboard
        $this->performance_dashboard = new PerformanceDashboard($this->analytics_engine);
        
        $this->initialized = true;
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'register_analytics_features']);
        add_action('admin_init', [$this, 'setup_admin_features']);
        add_action('wp_loaded', [$this, 'schedule_analytics_tasks']);
        
        // Integration with other KHM SEO modules
        add_filter('khm_seo_modules', [$this, 'register_module']);
        add_filter('khm_seo_analytics_data', [$this, 'provide_analytics_data']);
        add_action('khm_seo_content_updated', [$this, 'analyze_updated_content']);
        
        // AJAX handlers
        add_action('wp_ajax_khm_seo_get_analytics_summary', [$this, 'ajax_get_analytics_summary']);
        add_action('wp_ajax_khm_seo_get_content_scores', [$this, 'ajax_get_content_scores']);
        add_action('wp_ajax_khm_seo_generate_report', [$this, 'ajax_generate_report']);
        
        // Cleanup hooks
        add_action('wp_ajax_khm_seo_cleanup_analytics', [$this, 'ajax_cleanup_analytics']);
    }
    
    /**
     * Register analytics features
     */
    public function register_analytics_features() {
        if (!$this->initialized) {
            return;
        }
        
        // Register analytics capabilities
        $this->register_capabilities();
        
        // Register custom post statuses for analytics
        $this->register_custom_statuses();
        
        // Register analytics meta boxes
        add_action('add_meta_boxes', [$this, 'add_analytics_meta_boxes']);
    }
    
    /**
     * Setup admin features
     */
    public function setup_admin_features() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add analytics settings
        add_action('admin_menu', [$this, 'add_analytics_admin_pages'], 20);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Add analytics columns to post lists
        add_filter('manage_posts_columns', [$this, 'add_seo_score_column']);
        add_filter('manage_pages_columns', [$this, 'add_seo_score_column']);
        add_action('manage_posts_custom_column', [$this, 'display_seo_score_column'], 10, 2);
        add_action('manage_pages_custom_column', [$this, 'display_seo_score_column'], 10, 2);
    }
    
    /**
     * Schedule analytics tasks
     */
    public function schedule_analytics_tasks() {
        // Schedule daily analytics analysis
        if (!wp_next_scheduled('khm_seo_daily_analytics_full')) {
            wp_schedule_event(time(), 'daily', 'khm_seo_daily_analytics_full');
        }
        
        // Schedule weekly data cleanup
        if (!wp_next_scheduled('khm_seo_weekly_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'khm_seo_weekly_cleanup');
        }
        
        add_action('khm_seo_daily_analytics_full', [$this, 'run_full_analytics_analysis']);
        add_action('khm_seo_weekly_cleanup', [$this, 'run_weekly_cleanup']);
    }
    
    /**
     * Register module with main KHM SEO system
     */
    public function register_module($modules) {
        $modules['analytics'] = [
            'name' => 'Analytics & Reporting',
            'version' => $this->version,
            'description' => 'Comprehensive SEO analytics, scoring, and reporting system',
            'status' => 'active',
            'features' => [
                'seo_scoring' => 'Advanced SEO scoring algorithm',
                'performance_dashboard' => 'Real-time analytics dashboard',
                'automated_reports' => 'Scheduled report generation',
                'competitive_analysis' => 'Competitive benchmarking',
                'historical_tracking' => 'Historical performance tracking'
            ],
            'dependencies' => ['phase-1', 'phase-2-1', 'phase-2-2', 'phase-2-3', 'phase-2-4', 'phase-2-5']
        ];
        
        return $modules;
    }
    
    /**
     * Provide analytics data to other modules
     */
    public function provide_analytics_data($data) {
        if (!$this->initialized) {
            return $data;
        }
        
        $data['analytics'] = [
            'overall_health' => $this->get_overall_seo_health(),
            'recent_scores' => $this->get_recent_content_scores(),
            'improvement_suggestions' => $this->get_top_improvement_suggestions(),
            'performance_trends' => $this->get_performance_trends()
        ];
        
        return $data;
    }
    
    /**
     * Analyze updated content
     */
    public function analyze_updated_content($post_id) {
        if (!$this->initialized || !$this->scoring_system) {
            return;
        }
        
        // Schedule analysis for updated content
        wp_schedule_single_event(time() + 30, 'khm_seo_analyze_content', [$post_id]);
        
        add_action('khm_seo_analyze_content', function($post_id) {
            $analysis_result = $this->scoring_system->analyze_content($post_id);
            
            if (!empty($analysis_result) && !isset($analysis_result['error'])) {
                // Store the analysis result
                $this->analytics_database->store_seo_score($post_id, $analysis_result);
                
                // Generate recommendations if score is low
                if ($analysis_result['overall_score'] < 70) {
                    $this->generate_improvement_recommendations($post_id, $analysis_result);
                }
            }
        });
    }
    
    /**
     * Add analytics admin pages
     */
    public function add_analytics_admin_pages() {
        // Analytics overview page
        add_submenu_page(
            'khm-seo-main',
            'SEO Analytics Overview',
            'Analytics Overview',
            'manage_options',
            'khm-seo-analytics',
            [$this, 'render_analytics_overview_page']
        );
        
        // Analytics settings page
        add_submenu_page(
            'khm-seo-main',
            'Analytics Settings',
            'Analytics Settings',
            'manage_options',
            'khm-seo-analytics-settings',
            [$this, 'render_analytics_settings_page']
        );
    }
    
    /**
     * Add analytics meta boxes
     */
    public function add_analytics_meta_boxes() {
        $post_types = ['post', 'page'];
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'khm-seo-analytics-score',
                'SEO Score & Analysis',
                [$this, 'render_seo_score_meta_box'],
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render SEO score meta box
     */
    public function render_seo_score_meta_box($post) {
        if (!$this->scoring_system) {
            echo '<p>Analytics system not available.</p>';
            return;
        }
        
        $analysis = $this->scoring_system->analyze_content($post);
        
        if (isset($analysis['error'])) {
            echo '<p>Unable to analyze content: ' . esc_html($analysis['error']) . '</p>';
            return;
        }
        
        ?>
        <div class="khm-seo-score-display">
            <div class="score-circle">
                <div class="score-number"><?php echo esc_html($analysis['overall_score']); ?></div>
                <div class="score-label">Overall Score</div>
                <div class="score-grade"><?php echo esc_html($analysis['grade']); ?></div>
            </div>
            
            <div class="score-breakdown">
                <?php foreach ($analysis['category_scores'] as $category => $score_info): ?>
                <div class="score-category">
                    <span class="category-name"><?php echo esc_html(ucwords(str_replace('_', ' ', $category))); ?></span>
                    <span class="category-score"><?php echo esc_html($score_info['score']); ?>%</span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($analysis['recommendations'])): ?>
            <div class="score-recommendations">
                <h4>Top Recommendations:</h4>
                <ul>
                    <?php foreach (array_slice($analysis['recommendations'], 0, 3) as $recommendation): ?>
                    <li><?php echo esc_html($recommendation['recommendation']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="score-actions">
                <button class="button button-secondary" onclick="khmSeoRefreshScore(<?php echo $post->ID; ?>)">
                    Refresh Score
                </button>
                <a href="<?php echo admin_url('admin.php?page=khm-seo-content-analysis&post_id=' . $post->ID); ?>" class="button button-primary">
                    Detailed Analysis
                </a>
            </div>
        </div>
        
        <style>
        .khm-seo-score-display { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto; }
        .score-circle { text-align: center; padding: 15px; background: #f9f9f9; border-radius: 8px; margin-bottom: 15px; }
        .score-number { font-size: 32px; font-weight: bold; color: #0073aa; }
        .score-label { font-size: 12px; color: #666; }
        .score-grade { font-size: 18px; font-weight: bold; color: #666; }
        .score-category { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee; }
        .category-name { font-size: 12px; }
        .category-score { font-weight: bold; }
        .score-recommendations ul { margin: 0; padding-left: 20px; }
        .score-recommendations li { font-size: 12px; margin: 5px 0; }
        .score-actions { margin-top: 15px; }
        .score-actions .button { margin-right: 5px; }
        </style>
        
        <script>
        function khmSeoRefreshScore(postId) {
            // AJAX call to refresh score
            jQuery.post(ajaxurl, {
                action: 'khm_seo_refresh_content_score',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('khm_seo_refresh_score'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to refresh score: ' + (response.data || 'Unknown error'));
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Add SEO score column to post lists
     */
    public function add_seo_score_column($columns) {
        $columns['khm_seo_score'] = 'SEO Score';
        return $columns;
    }
    
    /**
     * Display SEO score in post list column
     */
    public function display_seo_score_column($column, $post_id) {
        if ($column !== 'khm_seo_score' || !$this->analytics_database) {
            return;
        }
        
        $scores = $this->analytics_database->get_seo_scores($post_id, 1);
        
        if (!empty($scores)) {
            $latest_score = $scores[0];
            $score = $latest_score->overall_score;
            $grade = $latest_score->grade;
            
            $color = $score >= 80 ? '#00a32a' : ($score >= 60 ? '#dba617' : '#d63638');
            
            echo "<span style='color: {$color}; font-weight: bold;'>{$score}% ({$grade})</span>";
        } else {
            echo '<span style="color: #666;">Not analyzed</span>';
        }
    }
    
    /**
     * Render analytics overview page
     */
    public function render_analytics_overview_page() {
        ?>
        <div class="wrap">
            <h1>SEO Analytics Overview</h1>
            
            <div class="analytics-overview-grid">
                <div class="overview-card">
                    <h3>Overall Health</h3>
                    <div class="metric-large"><?php echo $this->get_overall_seo_health(); ?>%</div>
                    <p>Average SEO score across all content</p>
                </div>
                
                <div class="overview-card">
                    <h3>Content Analyzed</h3>
                    <div class="metric-large"><?php echo $this->get_analyzed_content_count(); ?></div>
                    <p>Total pieces of content with SEO analysis</p>
                </div>
                
                <div class="overview-card">
                    <h3>Recommendations</h3>
                    <div class="metric-large"><?php echo $this->get_pending_recommendations_count(); ?></div>
                    <p>Outstanding optimization opportunities</p>
                </div>
            </div>
            
            <div class="analytics-recent-activity">
                <h2>Recent Activity</h2>
                <?php $this->render_recent_analytics_activity(); ?>
            </div>
            
            <div class="analytics-quick-actions">
                <h2>Quick Actions</h2>
                <a href="<?php echo admin_url('admin.php?page=khm-seo-dashboard'); ?>" class="button button-primary button-large">
                    View Full Dashboard
                </a>
                <a href="<?php echo admin_url('admin.php?page=khm-seo-reports'); ?>" class="button button-secondary button-large">
                    Generate Report
                </a>
                <button class="button button-secondary button-large" onclick="khmSeoRunFullAnalysis()">
                    Analyze All Content
                </button>
            </div>
        </div>
        
        <style>
        .analytics-overview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .overview-card { background: white; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px; text-align: center; }
        .metric-large { font-size: 48px; font-weight: bold; color: #0073aa; margin: 10px 0; }
        .analytics-quick-actions { margin: 30px 0; }
        .analytics-quick-actions .button { margin-right: 10px; }
        </style>
        
        <script>
        function khmSeoRunFullAnalysis() {
            if (confirm('This will analyze all published content. This may take several minutes. Continue?')) {
                jQuery.post(ajaxurl, {
                    action: 'khm_seo_run_full_analysis',
                    nonce: '<?php echo wp_create_nonce('khm_seo_full_analysis'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Full analysis started. You will receive an email when complete.');
                    } else {
                        alert('Failed to start analysis: ' + (response.data || 'Unknown error'));
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    /**
     * Get overall SEO health score
     */
    private function get_overall_seo_health() {
        if (!$this->analytics_database) {
            return 0;
        }
        
        global $wpdb;
        $scores_table = $this->analytics_database->get_table_name('seo_scores');
        
        $avg_score = $wpdb->get_var("
            SELECT AVG(overall_score) 
            FROM {$scores_table} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return round($avg_score ?: 0);
    }
    
    /**
     * Get count of analyzed content
     */
    private function get_analyzed_content_count() {
        if (!$this->analytics_database) {
            return 0;
        }
        
        global $wpdb;
        $scores_table = $this->analytics_database->get_table_name('seo_scores');
        
        return $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$scores_table}");
    }
    
    /**
     * Get count of pending recommendations
     */
    private function get_pending_recommendations_count() {
        if (!$this->analytics_database) {
            return 0;
        }
        
        global $wpdb;
        $recommendations_table = $this->analytics_database->get_table_name('recommendations');
        
        return $wpdb->get_var("SELECT COUNT(*) FROM {$recommendations_table} WHERE status = 'pending'");
    }
    
    /**
     * Render recent analytics activity
     */
    private function render_recent_analytics_activity() {
        echo '<p>Recent analytics activity will be displayed here...</p>';
    }
    
    // AJAX handlers and additional helper methods would continue here...
    // For brevity, showing the core integration structure
    
    /**
     * Get component instance
     */
    public function get_component($component_name) {
        switch ($component_name) {
            case 'analytics_engine':
                return $this->analytics_engine;
            case 'scoring_system':
                return $this->scoring_system;
            case 'reporting_engine':
                return $this->reporting_engine;
            case 'performance_dashboard':
                return $this->performance_dashboard;
            case 'analytics_database':
                return $this->analytics_database;
            default:
                return null;
        }
    }
    
    /**
     * Check if module is properly initialized
     */
    public function is_initialized() {
        return $this->initialized && 
               $this->analytics_engine && 
               $this->scoring_system && 
               $this->reporting_engine && 
               $this->performance_dashboard && 
               $this->analytics_database;
    }
    
    // Placeholder methods for additional functionality
    private function register_capabilities() { /* Register user capabilities */ }
    private function register_custom_statuses() { /* Register custom post statuses */ }
    private function enqueue_admin_assets() { /* Enqueue CSS/JS for admin */ }
    private function generate_improvement_recommendations($post_id, $analysis) { /* Generate recommendations */ }
    private function get_recent_content_scores() { return []; }
    private function get_top_improvement_suggestions() { return []; }
    private function get_performance_trends() { return []; }
    private function run_full_analytics_analysis() { /* Run comprehensive analysis */ }
    private function run_weekly_cleanup() { /* Clean up old data */ }
    private function render_analytics_settings_page() { echo '<h1>Analytics Settings</h1><p>Settings interface coming soon...</p>'; }
    
    // AJAX method placeholders
    public function ajax_get_analytics_summary() { wp_send_json_success(['summary' => 'Analytics data']); }
    public function ajax_get_content_scores() { wp_send_json_success(['scores' => []]); }
    public function ajax_generate_report() { wp_send_json_success(['report' => 'Generated']); }
    public function ajax_cleanup_analytics() { wp_send_json_success(['cleaned' => true]); }
}