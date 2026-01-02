<?php

namespace KHM_SEO\PageSpeed;

use KHM_SEO\PageSpeed\PSIManager;

/**
 * PageSpeed Insights Dashboard
 * 
 * Comprehensive dashboard for Core Web Vitals monitoring and 
 * performance analysis visualization
 * 
 * Features:
 * - Real-time performance monitoring
 * - Core Web Vitals tracking
 * - Performance trends visualization
 * - Optimization recommendations
 * - Bulk URL analysis
 * - Automated reporting
 * 
 * @package KHM_SEO\PageSpeed
 * @since 1.0.0
 */
class PSIDashboard {

    /**
     * PSI Manager instance
     */
    private $psi_manager;

    /**
     * Dashboard configuration
     */
    private $config = [
        'charts_enabled' => true,
        'auto_refresh' => 300, // 5 minutes
        'default_period' => 30, // days
        'max_urls_display' => 20
    ];

    /**
     * Initialize PSI Dashboard
     */
    public function __construct() {
        $this->psi_manager = new PSIManager();
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_psi_dashboard_data', [$this, 'ajax_dashboard_data']);
        add_action('wp_ajax_psi_url_analysis', [$this, 'ajax_url_analysis']);
        add_action('wp_ajax_psi_bulk_report', [$this, 'ajax_bulk_report']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-seo-dashboard',
            'PageSpeed Insights',
            'Core Web Vitals',
            'manage_options',
            'khm-seo-pagespeed',
            [$this, 'dashboard_page']
        );
    }

    /**
     * Enqueue dashboard scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'khm-seo-pagespeed') === false) {
            return;
        }

        // Chart.js for visualizations
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
        
        // DataTables for reports
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], '1.13.6', true);
        wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], '1.13.6');
        
        // Custom dashboard scripts
        wp_enqueue_script(
            'psi-dashboard',
            plugin_dir_url(__FILE__) . 'assets/js/psi-dashboard.js',
            ['jquery', 'chartjs', 'datatables'],
            '1.0.0',
            true
        );

        // Custom styles
        wp_enqueue_style(
            'psi-dashboard',
            plugin_dir_url(__FILE__) . 'assets/css/psi-dashboard.css',
            [],
            '1.0.0'
        );

        // Localize script data
        wp_localize_script('psi-dashboard', 'psiDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('psi_dashboard'),
            'config' => $this->config,
            'strings' => [
                'analyzing' => __('Analyzing...', 'khm-seo'),
                'error' => __('Error occurred', 'khm-seo'),
                'noData' => __('No data available', 'khm-seo'),
                'loading' => __('Loading...', 'khm-seo')
            ]
        ]);
    }

    /**
     * Render dashboard page
     */
    public function dashboard_page() {
        ?>
        <div class="wrap khm-psi-dashboard">
            <h1><?php _e('Core Web Vitals Dashboard', 'khm-seo'); ?></h1>
            
            <!-- Dashboard Header -->
            <div class="psi-dashboard-header">
                <div class="psi-quick-stats">
                    <?php $this->render_quick_stats(); ?>
                </div>
                <div class="psi-actions">
                    <button class="button button-primary" id="analyze-current-page">
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Analyze Current Page', 'khm-seo'); ?>
                    </button>
                    <button class="button" id="bulk-analysis">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('Bulk Analysis', 'khm-seo'); ?>
                    </button>
                    <button class="button" id="export-report">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export Report', 'khm-seo'); ?>
                    </button>
                </div>
            </div>

            <!-- Main Dashboard Content -->
            <div class="psi-dashboard-content">
                
                <!-- Core Web Vitals Overview -->
                <div class="psi-section" id="cwv-overview">
                    <h2><?php _e('Core Web Vitals Overview', 'khm-seo'); ?></h2>
                    <div class="psi-cwv-cards">
                        <?php $this->render_cwv_cards(); ?>
                    </div>
                </div>

                <!-- Performance Trends Chart -->
                <div class="psi-section" id="performance-trends">
                    <h2><?php _e('Performance Trends', 'khm-seo'); ?></h2>
                    <div class="psi-chart-container">
                        <canvas id="trendsChart" width="800" height="400"></canvas>
                    </div>
                    <div class="psi-chart-controls">
                        <select id="trend-period">
                            <option value="7"><?php _e('Last 7 days', 'khm-seo'); ?></option>
                            <option value="30" selected><?php _e('Last 30 days', 'khm-seo'); ?></option>
                            <option value="90"><?php _e('Last 90 days', 'khm-seo'); ?></option>
                        </select>
                        <select id="trend-metric">
                            <option value="performance"><?php _e('Performance Score', 'khm-seo'); ?></option>
                            <option value="lcp"><?php _e('Largest Contentful Paint', 'khm-seo'); ?></option>
                            <option value="fid"><?php _e('First Input Delay', 'khm-seo'); ?></option>
                            <option value="cls"><?php _e('Cumulative Layout Shift', 'khm-seo'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- URL Analysis Section -->
                <div class="psi-section" id="url-analysis">
                    <h2><?php _e('URL Analysis', 'khm-seo'); ?></h2>
                    <div class="psi-url-input">
                        <input type="url" id="analysis-url" placeholder="<?php _e('Enter URL to analyze...', 'khm-seo'); ?>" />
                        <select id="analysis-strategy">
                            <option value="mobile"><?php _e('Mobile', 'khm-seo'); ?></option>
                            <option value="desktop"><?php _e('Desktop', 'khm-seo'); ?></option>
                        </select>
                        <button class="button button-primary" id="analyze-url">
                            <?php _e('Analyze', 'khm-seo'); ?>
                        </button>
                    </div>
                    <div id="analysis-results" class="psi-analysis-results">
                        <!-- Results populated via AJAX -->
                    </div>
                </div>

                <!-- Top Pages Performance -->
                <div class="psi-section" id="top-pages">
                    <h2><?php _e('Top Pages Performance', 'khm-seo'); ?></h2>
                    <div class="psi-table-container">
                        <table id="pages-performance-table" class="psi-data-table">
                            <thead>
                                <tr>
                                    <th><?php _e('URL', 'khm-seo'); ?></th>
                                    <th><?php _e('Performance', 'khm-seo'); ?></th>
                                    <th><?php _e('LCP', 'khm-seo'); ?></th>
                                    <th><?php _e('FID', 'khm-seo'); ?></th>
                                    <th><?php _e('CLS', 'khm-seo'); ?></th>
                                    <th><?php _e('Last Check', 'khm-seo'); ?></th>
                                    <th><?php _e('Actions', 'khm-seo'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Optimization Opportunities -->
                <div class="psi-section" id="opportunities">
                    <h2><?php _e('Top Optimization Opportunities', 'khm-seo'); ?></h2>
                    <div class="psi-opportunities-list">
                        <?php $this->render_opportunities(); ?>
                    </div>
                </div>

            </div>

            <!-- Modals -->
            <?php $this->render_modals(); ?>
        </div>
        <?php
    }

    /**
     * Render quick statistics cards
     */
    private function render_quick_stats() {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="psi-stat-card">
            <div class="psi-stat-value"><?php echo esc_html($stats['total_pages']); ?></div>
            <div class="psi-stat-label"><?php _e('Pages Monitored', 'khm-seo'); ?></div>
        </div>
        <div class="psi-stat-card">
            <div class="psi-stat-value psi-stat-score-<?php echo esc_attr($stats['avg_performance_class']); ?>">
                <?php echo esc_html($stats['avg_performance']); ?>
            </div>
            <div class="psi-stat-label"><?php _e('Avg Performance', 'khm-seo'); ?></div>
        </div>
        <div class="psi-stat-card">
            <div class="psi-stat-value psi-stat-<?php echo esc_attr($stats['cwv_status_class']); ?>">
                <?php echo esc_html($stats['cwv_passing']); ?>%
            </div>
            <div class="psi-stat-label"><?php _e('CWV Passing', 'khm-seo'); ?></div>
        </div>
        <div class="psi-stat-card">
            <div class="psi-stat-value"><?php echo esc_html($stats['issues_count']); ?></div>
            <div class="psi-stat-label"><?php _e('Issues Found', 'khm-seo'); ?></div>
        </div>
        <?php
    }

    /**
     * Render Core Web Vitals cards
     */
    private function render_cwv_cards() {
        $cwv_data = $this->get_cwv_overview();
        
        foreach ($cwv_data as $metric => $data) {
            $class = $this->get_metric_class($data['rating']);
            ?>
            <div class="psi-cwv-card psi-cwv-<?php echo esc_attr($class); ?>">
                <div class="psi-cwv-header">
                    <h3><?php echo esc_html($data['name']); ?></h3>
                    <span class="psi-cwv-rating"><?php echo esc_html(ucfirst($data['rating'])); ?></span>
                </div>
                <div class="psi-cwv-value">
                    <?php echo esc_html($data['value']); ?>
                    <span class="psi-cwv-unit"><?php echo esc_html($data['unit']); ?></span>
                </div>
                <div class="psi-cwv-trend">
                    <span class="psi-trend-<?php echo esc_attr($data['trend']); ?>">
                        <?php echo $data['trend'] === 'up' ? '↗' : ($data['trend'] === 'down' ? '↘' : '→'); ?>
                        <?php echo esc_html($data['change']); ?>
                    </span>
                </div>
                <div class="psi-cwv-description">
                    <?php echo esc_html($data['description']); ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render optimization opportunities
     */
    private function render_opportunities() {
        $opportunities = $this->get_top_opportunities();
        
        if (empty($opportunities)) {
            echo '<p>' . __('No optimization opportunities found. Great job!', 'khm-seo') . '</p>';
            return;
        }

        foreach ($opportunities as $opportunity) {
            $impact_class = $this->get_impact_class($opportunity['impact']);
            ?>
            <div class="psi-opportunity-card psi-impact-<?php echo esc_attr($impact_class); ?>">
                <div class="psi-opportunity-header">
                    <h4><?php echo esc_html($opportunity['title']); ?></h4>
                    <span class="psi-opportunity-savings">
                        <?php printf(__('Save %s ms', 'khm-seo'), number_format($opportunity['savings_ms'])); ?>
                    </span>
                </div>
                <div class="psi-opportunity-description">
                    <?php echo esc_html($opportunity['description']); ?>
                </div>
                <div class="psi-opportunity-affected">
                    <?php printf(__('Affects %d pages', 'khm-seo'), $opportunity['affected_pages']); ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render modal dialogs
     */
    private function render_modals() {
        ?>
        <!-- Bulk Analysis Modal -->
        <div id="bulk-analysis-modal" class="psi-modal" style="display: none;">
            <div class="psi-modal-content">
                <div class="psi-modal-header">
                    <h3><?php _e('Bulk URL Analysis', 'khm-seo'); ?></h3>
                    <span class="psi-modal-close">&times;</span>
                </div>
                <div class="psi-modal-body">
                    <p><?php _e('Enter URLs to analyze (one per line):', 'khm-seo'); ?></p>
                    <textarea id="bulk-urls" rows="10" placeholder="<?php _e("https://example.com/\nhttps://example.com/page1/\nhttps://example.com/page2/", 'khm-seo'); ?>"></textarea>
                    <div class="psi-modal-actions">
                        <button class="button button-primary" id="start-bulk-analysis">
                            <?php _e('Start Analysis', 'khm-seo'); ?>
                        </button>
                        <button class="button" id="cancel-bulk-analysis">
                            <?php _e('Cancel', 'khm-seo'); ?>
                        </button>
                    </div>
                    <div id="bulk-progress" style="display: none;">
                        <div class="psi-progress-bar">
                            <div class="psi-progress-fill"></div>
                        </div>
                        <div class="psi-progress-text"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- URL Details Modal -->
        <div id="url-details-modal" class="psi-modal" style="display: none;">
            <div class="psi-modal-content psi-modal-large">
                <div class="psi-modal-header">
                    <h3><?php _e('Detailed Analysis', 'khm-seo'); ?></h3>
                    <span class="psi-modal-close">&times;</span>
                </div>
                <div class="psi-modal-body">
                    <div id="url-details-content">
                        <!-- Content loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gsc_cwv_metrics';

        // Get total monitored pages
        $total_pages = $wpdb->get_var("
            SELECT COUNT(DISTINCT url) 
            FROM {$table_name} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        // Get average performance score
        $avg_performance = $wpdb->get_var("
            SELECT AVG(performance_score) 
            FROM {$table_name} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        // Get CWV passing percentage
        $cwv_data = $wpdb->get_results("
            SELECT url, 
                   AVG(CASE WHEN lcp <= 2500 THEN 1 ELSE 0 END) as lcp_good,
                   AVG(CASE WHEN fid <= 100 THEN 1 ELSE 0 END) as fid_good,
                   AVG(CASE WHEN cls <= 0.1 THEN 1 ELSE 0 END) as cls_good
            FROM {$table_name} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY url
        ");

        $cwv_passing = 0;
        if (!empty($cwv_data)) {
            $passing_count = 0;
            foreach ($cwv_data as $row) {
                if ($row->lcp_good && $row->fid_good && $row->cls_good) {
                    $passing_count++;
                }
            }
            $cwv_passing = count($cwv_data) > 0 ? ($passing_count / count($cwv_data)) * 100 : 0;
        }

        return [
            'total_pages' => $total_pages ?: 0,
            'avg_performance' => round($avg_performance * 100),
            'avg_performance_class' => $this->get_score_class($avg_performance),
            'cwv_passing' => round($cwv_passing),
            'cwv_status_class' => $cwv_passing >= 75 ? 'good' : ($cwv_passing >= 50 ? 'warning' : 'poor'),
            'issues_count' => $this->get_issues_count()
        ];
    }

    /**
     * Get Core Web Vitals overview
     */
    private function get_cwv_overview() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gsc_cwv_metrics';

        $metrics = $wpdb->get_row("
            SELECT 
                AVG(lcp) as avg_lcp,
                AVG(fid) as avg_fid,
                AVG(cls) as avg_cls,
                AVG(fcp) as avg_fcp
            FROM {$table_name} 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");

        return [
            'lcp' => [
                'name' => 'Largest Contentful Paint',
                'value' => $metrics ? round($metrics->avg_lcp / 1000, 2) : 0,
                'unit' => 's',
                'rating' => $this->classify_lcp($metrics->avg_lcp ?? 0),
                'trend' => 'stable', // Would be calculated from historical data
                'change' => '0%',
                'description' => 'Time to render the largest content element'
            ],
            'fid' => [
                'name' => 'First Input Delay',
                'value' => $metrics ? round($metrics->avg_fid) : 0,
                'unit' => 'ms',
                'rating' => $this->classify_fid($metrics->avg_fid ?? 0),
                'trend' => 'stable',
                'change' => '0%',
                'description' => 'Time from user interaction to browser response'
            ],
            'cls' => [
                'name' => 'Cumulative Layout Shift',
                'value' => $metrics ? round($metrics->avg_cls, 3) : 0,
                'unit' => '',
                'rating' => $this->classify_cls($metrics->avg_cls ?? 0),
                'trend' => 'stable',
                'change' => '0%',
                'description' => 'Visual stability during page load'
            ]
        ];
    }

    /**
     * AJAX: Get dashboard data
     */
    public function ajax_dashboard_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'psi_dashboard')) {
            wp_die('Security check failed');
        }

        $data_type = sanitize_text_field($_POST['data_type'] ?? '');
        $period = intval($_POST['period'] ?? 30);

        switch ($data_type) {
            case 'trends':
                wp_send_json_success($this->get_trends_data($period));
                break;
            case 'pages':
                wp_send_json_success($this->get_pages_data());
                break;
            default:
                wp_send_json_error('Invalid data type');
        }
    }

    /**
     * Utility methods for data classification
     */
    private function classify_lcp($lcp) {
        if ($lcp <= 2500) return 'good';
        if ($lcp <= 4000) return 'needs-improvement';
        return 'poor';
    }

    private function classify_fid($fid) {
        if ($fid <= 100) return 'good';
        if ($fid <= 300) return 'needs-improvement';
        return 'poor';
    }

    private function classify_cls($cls) {
        if ($cls <= 0.1) return 'good';
        if ($cls <= 0.25) return 'needs-improvement';
        return 'poor';
    }

    private function get_score_class($score) {
        if ($score >= 0.9) return 'good';
        if ($score >= 0.5) return 'warning';
        return 'poor';
    }

    private function get_metric_class($rating) {
        return $rating === 'good' ? 'good' : ($rating === 'needs-improvement' ? 'warning' : 'poor');
    }

    private function get_impact_class($impact) {
        return $impact; // high, medium, low
    }

    private function get_issues_count() {
        // Would calculate from recent analysis data
        return 5; // Placeholder
    }

    private function get_top_opportunities() {
        // Would get from recent analysis data
        return []; // Placeholder
    }

    private function get_trends_data($period) {
        // Would return chart data for trends
        return []; // Placeholder
    }

    private function get_pages_data() {
        // Would return table data for pages
        return []; // Placeholder
    }
}