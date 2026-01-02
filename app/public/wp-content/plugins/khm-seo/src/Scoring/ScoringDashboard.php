<?php

namespace KHM_SEO\Scoring;

use Exception;

/**
 * Scoring Dashboard
 * 
 * Comprehensive admin interface for SEO scoring, analytics, and performance
 * monitoring within the KHM SEO Phase 9 Module.
 * 
 * Features:
 * - Real-time scoring overview and analytics
 * - Performance trend visualization
 * - Competitive benchmarking interface
 * - Scoring configuration management
 * - Detailed metric breakdowns
 * - Recommendation tracking
 * - Historical scoring analysis
 * - Scoring report generation
 * 
 * @package KHM_SEO\Scoring
 * @since 1.0.0
 */
class ScoringDashboard {

    /**
     * @var ScoringEngine
     */
    private $scoring_engine;

    /**
     * Dashboard configuration
     */
    private $config = [
        'refresh_interval' => 300,
        'chart_data_points' => 30,
        'benchmark_update_frequency' => 'weekly',
        'report_retention_days' => 90
    ];

    /**
     * Scoring statistics cache
     */
    private $scoring_stats = [];

    /**
     * Performance trends data
     */
    private $trend_data = [];

    /**
     * Initialize Scoring Dashboard
     */
    public function __construct() {
        $this->scoring_engine = new ScoringEngine();
        
        \add_action('admin_menu', [$this, 'add_admin_menu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        \add_action('wp_ajax_scoring_dashboard_action', [$this, 'handle_ajax_actions']);
        
        $this->init_dashboard();
    }

    /**
     * Initialize dashboard
     */
    private function init_dashboard() {
        $this->load_scoring_statistics();
        $this->load_trend_data();
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        \add_submenu_page(
            'khm-seo-dashboard',
            'SEO Scoring',
            'Scoring',
            'manage_options',
            'khm-seo-scoring',
            [$this, 'render_main_dashboard']
        );

        \add_submenu_page(
            'khm-seo-scoring',
            'Scoring Analytics',
            'Analytics',
            'manage_options',
            'khm-seo-scoring-analytics',
            [$this, 'render_analytics_page']
        );

        \add_submenu_page(
            'khm-seo-scoring',
            'Benchmarks',
            'Benchmarks',
            'manage_options',
            'khm-seo-scoring-benchmarks',
            [$this, 'render_benchmarks_page']
        );

        \add_submenu_page(
            'khm-seo-scoring',
            'Scoring Configuration',
            'Configuration',
            'manage_options',
            'khm-seo-scoring-config',
            [$this, 'render_configuration_page']
        );
    }

    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        if (strpos($hook, 'khm-seo-scoring') === false) {
            return;
        }

        \wp_enqueue_script(
            'khm-scoring-dashboard',
            \plugins_url('assets/js/scoring-dashboard.js', dirname(__FILE__, 3)),
            ['jquery', 'chart-js'],
            '1.0.0',
            true
        );

        \wp_enqueue_style(
            'khm-scoring-dashboard',
            \plugins_url('assets/css/scoring-dashboard.css', dirname(__FILE__, 3)),
            [],
            '1.0.0'
        );

        // Enqueue Chart.js for data visualization
        \wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );

        \wp_localize_script('khm-scoring-dashboard', 'khmScoring', [
            'nonce' => \wp_create_nonce('khm_scoring_dashboard'),
            'ajaxurl' => \admin_url('admin-ajax.php'),
            'strings' => [
                'calculating' => \__('Calculating scores...', 'khm-seo'),
                'updating' => \__('Updating benchmarks...', 'khm-seo'),
                'generating' => \__('Generating report...', 'khm-seo'),
                'success' => \__('Operation completed successfully', 'khm-seo'),
                'error' => \__('Operation failed', 'khm-seo')
            ],
            'config' => $this->config
        ]);
    }

    /**
     * Render main scoring dashboard
     */
    public function render_main_dashboard() {
        $this->load_scoring_statistics();
        ?>
        <div class="wrap khm-scoring-dashboard">
            <h1>
                <?php \esc_html_e('SEO Scoring Dashboard', 'khm-seo'); ?>
                <button id="calculate-scores" class="button button-primary">
                    <?php \esc_html_e('Calculate Scores', 'khm-seo'); ?>
                </button>
            </h1>

            <?php $this->render_dashboard_notices(); ?>

            <div class="scoring-overview">
                <?php $this->render_score_overview(); ?>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-main">
                    <?php $this->render_score_breakdown(); ?>
                    <?php $this->render_performance_trends(); ?>
                </div>
                
                <div class="dashboard-sidebar">
                    <?php $this->render_recommendations_widget(); ?>
                    <?php $this->render_benchmarks_widget(); ?>
                    <?php $this->render_quick_stats(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap khm-scoring-analytics">
            <h1><?php \esc_html_e('Scoring Analytics', 'khm-seo'); ?></h1>

            <div class="analytics-dashboard">
                <?php $this->render_performance_charts(); ?>
                <?php $this->render_category_analysis(); ?>
                <?php $this->render_trend_analysis(); ?>
                <?php $this->render_competitive_analysis(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render benchmarks page
     */
    public function render_benchmarks_page() {
        ?>
        <div class="wrap khm-scoring-benchmarks">
            <h1><?php \esc_html_e('Industry Benchmarks', 'khm-seo'); ?></h1>

            <div class="benchmarks-content">
                <?php $this->render_benchmark_comparison(); ?>
                <?php $this->render_industry_standards(); ?>
                <?php $this->render_benchmark_configuration(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render configuration page
     */
    public function render_configuration_page() {
        ?>
        <div class="wrap khm-scoring-configuration">
            <h1><?php \esc_html_e('Scoring Configuration', 'khm-seo'); ?></h1>

            <form method="post" action="options.php">
                <?php
                \settings_fields('khm_seo_scoring_config');
                // Settings sections will be rendered in configuration methods
                ?>

                <div class="configuration-sections">
                    <?php $this->render_weights_configuration(); ?>
                    <?php $this->render_thresholds_configuration(); ?>
                    <?php $this->render_calculation_settings(); ?>
                </div>

                <?php \submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render score overview section
     */
    private function render_score_overview() {
        $latest_score = $this->get_latest_score();
        ?>
        <div class="score-overview-grid">
            <div class="overall-score-card">
                <div class="score-circle">
                    <div class="score-value" data-score="<?php echo \esc_attr($latest_score['overall_score'] ?? 0); ?>">
                        <?php echo \esc_html($latest_score['overall_score'] ?? 0); ?>
                    </div>
                    <div class="score-label"><?php \esc_html_e('Overall Score', 'khm-seo'); ?></div>
                </div>
                <div class="score-trend">
                    <?php $this->render_score_trend_indicator($latest_score); ?>
                </div>
            </div>

            <div class="category-scores">
                <?php if (!empty($latest_score['category_scores'])): ?>
                    <?php foreach ($latest_score['category_scores'] as $category => $score): ?>
                        <div class="category-score-item">
                            <div class="category-name"><?php echo \esc_html(\ucwords(\str_replace('_', ' ', $category))); ?></div>
                            <div class="category-score" data-score="<?php echo \esc_attr($score); ?>">
                                <?php echo \esc_html($score); ?>
                            </div>
                            <div class="category-bar">
                                <div class="category-fill" style="width: <?php echo \esc_attr($score); ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><?php \esc_html_e('No scoring data available. Click "Calculate Scores" to generate initial scores.', 'khm-seo'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render score breakdown
     */
    private function render_score_breakdown() {
        $latest_score = $this->get_latest_score();
        ?>
        <div class="score-breakdown-section">
            <h2><?php \esc_html_e('Score Breakdown', 'khm-seo'); ?></h2>
            
            <?php if (!empty($latest_score['metrics_data'])): ?>
                <div class="breakdown-tabs">
                    <nav class="tab-nav">
                        <button class="tab-button active" data-tab="technical">
                            <?php \esc_html_e('Technical SEO', 'khm-seo'); ?>
                        </button>
                        <button class="tab-button" data-tab="content">
                            <?php \esc_html_e('Content Quality', 'khm-seo'); ?>
                        </button>
                        <button class="tab-button" data-tab="authority">
                            <?php \esc_html_e('Authority & Trust', 'khm-seo'); ?>
                        </button>
                        <button class="tab-button" data-tab="user_experience">
                            <?php \esc_html_e('User Experience', 'khm-seo'); ?>
                        </button>
                        <button class="tab-button" data-tab="visibility">
                            <?php \esc_html_e('Search Visibility', 'khm-seo'); ?>
                        </button>
                    </nav>

                    <?php foreach ($latest_score['metrics_data'] as $category => $metrics): ?>
                        <div class="tab-content <?php echo $category === 'technical' ? 'active' : ''; ?>" id="tab-<?php echo \esc_attr($category); ?>">
                            <?php $this->render_category_metrics($category, $metrics); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php \esc_html_e('No detailed metrics available. Calculate scores to see breakdown.', 'khm-seo'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render category metrics
     */
    private function render_category_metrics($category, $metrics) {
        ?>
        <div class="category-metrics">
            <div class="metrics-grid">
                <?php foreach ($metrics as $metric => $data): ?>
                    <div class="metric-card">
                        <div class="metric-header">
                            <h4><?php echo \esc_html(\ucwords(\str_replace('_', ' ', $metric))); ?></h4>
                            <div class="metric-score <?php echo $this->get_score_class($data['score'] ?? 0); ?>">
                                <?php echo \esc_html($data['score'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="metric-details">
                            <?php $this->render_metric_details($metric, $data); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render metric details
     */
    private function render_metric_details($metric, $data) {
        unset($data['score']); // Remove score from details
        
        if (empty($data)) {
            echo '<p>' . \esc_html__('No additional details available.', 'khm-seo') . '</p>';
            return;
        }

        echo '<ul class="metric-details-list">';
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? \__('Yes', 'khm-seo') : \__('No', 'khm-seo');
            } elseif (is_numeric($value)) {
                $value = \number_format($value, 2);
            } elseif (is_array($value)) {
                $value = \implode(', ', $value);
            }
            
            echo '<li>';
            echo '<strong>' . \esc_html(\ucwords(\str_replace('_', ' ', $key))) . ':</strong> ';
            echo \esc_html($value);
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Render recommendations widget
     */
    private function render_recommendations_widget() {
        $latest_score = $this->get_latest_score();
        $recommendations = $latest_score['recommendations'] ?? [];
        ?>
        <div class="recommendations-widget">
            <h3><?php \esc_html_e('Recommendations', 'khm-seo'); ?></h3>
            
            <?php if (!empty($recommendations)): ?>
                <div class="recommendations-list">
                    <?php foreach ($recommendations as $category => $category_recommendations): ?>
                        <?php if (!empty($category_recommendations)): ?>
                            <div class="recommendation-category">
                                <h4><?php echo \esc_html(\ucwords(\str_replace('_', ' ', $category))); ?></h4>
                                <?php foreach ($category_recommendations as $recommendation): ?>
                                    <div class="recommendation-item priority-<?php echo \esc_attr($recommendation['priority'] ?? 'medium'); ?>">
                                        <div class="recommendation-message">
                                            <?php echo \esc_html($recommendation['message'] ?? ''); ?>
                                        </div>
                                        <?php if (!empty($recommendation['actions'])): ?>
                                            <ul class="recommendation-actions">
                                                <?php foreach ($recommendation['actions'] as $action): ?>
                                                    <li><?php echo \esc_html($action); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php \esc_html_e('No recommendations available. Calculate scores to get recommendations.', 'khm-seo'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle AJAX actions
     */
    public function handle_ajax_actions() {
        if (!\wp_verify_nonce($_POST['nonce'], 'khm_scoring_dashboard')) {
            \wp_die('Security check failed');
        }

        $action = \sanitize_text_field($_POST['action_type'] ?? '');
        
        switch ($action) {
            case 'calculate_scores':
                $this->ajax_calculate_scores();
                break;
                
            case 'update_benchmarks':
                $this->ajax_update_benchmarks();
                break;
                
            case 'export_report':
                $this->ajax_export_report();
                break;
                
            case 'refresh_data':
                $this->ajax_refresh_data();
                break;
                
            default:
                \wp_send_json_error(['message' => 'Invalid action']);
        }
    }

    /**
     * AJAX: Calculate comprehensive scores
     */
    private function ajax_calculate_scores() {
        try {
            $url = \filter_var($_POST['url'] ?? \home_url('/'), FILTER_SANITIZE_URL);
            $result = $this->scoring_engine->calculate_comprehensive_score($url);
            
            if ($result) {
                \wp_send_json_success([
                    'message' => 'Scores calculated successfully',
                    'data' => $result
                ]);
            } else {
                \wp_send_json_error(['message' => 'Failed to calculate scores']);
            }

        } catch (Exception $e) {
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Helper methods
     */
    private function load_scoring_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_scoring';
        
        $this->scoring_stats = [
            'total_calculations' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'average_score' => $wpdb->get_var("SELECT AVG(overall_score) FROM $table_name"),
            'latest_calculation' => $wpdb->get_var("SELECT MAX(calculated_at) FROM $table_name")
        ];
    }

    private function load_trend_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_scoring';
        
        $this->trend_data = $wpdb->get_results("
            SELECT 
                DATE(calculated_at) as date,
                AVG(overall_score) as score
            FROM $table_name 
            WHERE calculated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(calculated_at)
            ORDER BY date ASC
        ", ARRAY_A);
    }

    private function get_latest_score() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_scoring';
        
        $latest = $wpdb->get_row("
            SELECT * FROM $table_name 
            ORDER BY calculated_at DESC 
            LIMIT 1
        ", ARRAY_A);

        if ($latest) {
            $latest['category_scores'] = \json_decode($latest['category_scores'], true);
            $latest['metrics_data'] = \json_decode($latest['metrics_data'], true);
            $latest['recommendations'] = \json_decode($latest['recommendations'], true);
            $latest['benchmark_comparison'] = \json_decode($latest['benchmark_comparison'], true);
        }

        return $latest ?: [];
    }

    private function get_score_class($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        if ($score >= 40) return 'poor';
        return 'critical';
    }

    // Placeholder methods for missing functionality
    private function render_dashboard_notices() { return; }
    private function render_performance_trends() { return; }
    private function render_benchmarks_widget() { return; }
    private function render_quick_stats() { return; }
    private function render_performance_charts() { return; }
    private function render_category_analysis() { return; }
    private function render_trend_analysis() { return; }
    private function render_competitive_analysis() { return; }
    private function render_benchmark_comparison() { return; }
    private function render_industry_standards() { return; }
    private function render_benchmark_configuration() { return; }
    private function render_weights_configuration() { return; }
    private function render_thresholds_configuration() { return; }
    private function render_calculation_settings() { return; }
    private function render_score_trend_indicator($score_data) { return; }
    
    // AJAX methods (placeholder)
    private function ajax_update_benchmarks() { \wp_send_json_success([]); }
    private function ajax_export_report() { \wp_send_json_success([]); }
    private function ajax_refresh_data() { \wp_send_json_success([]); }
}