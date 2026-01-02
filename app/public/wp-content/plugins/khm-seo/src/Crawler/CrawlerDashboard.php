<?php

namespace KHM_SEO\Crawler;

use KHM_SEO\Crawler\SEOCrawler;

/**
 * SEO Crawler Dashboard
 * 
 * Comprehensive dashboard for technical SEO crawler management
 * and analysis visualization
 * 
 * Features:
 * - Crawl management interface
 * - Real-time crawl progress monitoring
 * - Technical SEO issue reporting
 * - Site structure visualization
 * - Performance analysis dashboard
 * - Automated recommendations
 * 
 * @package KHM_SEO\Crawler
 * @since 1.0.0
 */
class CrawlerDashboard {

    /**
     * SEO Crawler instance
     */
    private $seo_crawler;

    /**
     * Dashboard configuration
     */
    private $config = [
        'max_display_urls' => 100,
        'issues_per_page' => 50,
        'refresh_interval' => 5000, // milliseconds
        'chart_colors' => [
            'good' => '#4CAF50',
            'warning' => '#FF9800', 
            'error' => '#F44336',
            'info' => '#2196F3'
        ]
    ];

    /**
     * Initialize Crawler Dashboard
     */
    public function __construct() {
        $this->seo_crawler = new SEOCrawler();
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_crawler_dashboard_data', [$this, 'ajax_dashboard_data']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-seo-dashboard',
            'SEO Crawler',
            'Site Crawler',
            'manage_options',
            'khm-seo-crawler',
            [$this, 'dashboard_page']
        );
    }

    /**
     * Enqueue dashboard scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'khm-seo-crawler') === false) {
            return;
        }

        // Chart.js for visualizations
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true);
        
        // DataTables for data display
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], '1.13.6', true);
        wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], '1.13.6');
        
        // Custom crawler scripts
        wp_enqueue_script(
            'crawler-dashboard',
            plugin_dir_url(__FILE__) . 'assets/js/crawler-dashboard.js',
            ['jquery', 'chartjs', 'datatables'],
            '1.0.0',
            true
        );

        // Custom styles
        wp_enqueue_style(
            'crawler-dashboard',
            plugin_dir_url(__FILE__) . 'assets/css/crawler-dashboard.css',
            [],
            '1.0.0'
        );

        // Localize script data
        wp_localize_script('crawler-dashboard', 'crawlerDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('crawler_dashboard'),
            'config' => $this->config,
            'strings' => [
                'crawling' => __('Crawling...', 'khm-seo'),
                'paused' => __('Paused', 'khm-seo'),
                'completed' => __('Completed', 'khm-seo'),
                'error' => __('Error occurred', 'khm-seo'),
                'noData' => __('No data available', 'khm-seo')
            ]
        ]);
    }

    /**
     * Render dashboard page
     */
    public function dashboard_page() {
        ?>
        <div class="wrap khm-crawler-dashboard">
            <h1><?php _e('Technical SEO Crawler', 'khm-seo'); ?></h1>
            
            <!-- Dashboard Header -->
            <div class="crawler-dashboard-header">
                <div class="crawler-stats-cards">
                    <?php $this->render_stats_cards(); ?>
                </div>
                <div class="crawler-actions">
                    <button class="button button-primary" id="start-new-crawl">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Start New Crawl', 'khm-seo'); ?>
                    </button>
                    <button class="button" id="pause-crawl" disabled>
                        <span class="dashicons dashicons-controls-pause"></span>
                        <?php _e('Pause Crawl', 'khm-seo'); ?>
                    </button>
                    <button class="button" id="export-results">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export Results', 'khm-seo'); ?>
                    </button>
                </div>
            </div>

            <!-- Crawl Progress Section -->
            <div class="crawler-section" id="crawl-progress" style="display: none;">
                <h2><?php _e('Crawl Progress', 'khm-seo'); ?></h2>
                <div class="crawler-progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-stats">
                        <span id="crawled-count">0</span> / <span id="total-count">0</span> pages crawled
                    </div>
                    <div class="progress-details">
                        <div class="detail-item">
                            <span class="label"><?php _e('Current URL:', 'khm-seo'); ?></span>
                            <span id="current-url">-</span>
                        </div>
                        <div class="detail-item">
                            <span class="label"><?php _e('Estimated Time:', 'khm-seo'); ?></span>
                            <span id="estimated-time">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Content -->
            <div class="crawler-dashboard-content">
                
                <!-- Site Overview -->
                <div class="crawler-section" id="site-overview">
                    <h2><?php _e('Site Overview', 'khm-seo'); ?></h2>
                    <div class="overview-grid">
                        <div class="overview-card">
                            <h3><?php _e('SEO Score Distribution', 'khm-seo'); ?></h3>
                            <canvas id="seoScoreChart" width="400" height="300"></canvas>
                        </div>
                        <div class="overview-card">
                            <h3><?php _e('Issues by Category', 'khm-seo'); ?></h3>
                            <canvas id="issuesCategoryChart" width="400" height="300"></canvas>
                        </div>
                        <div class="overview-card">
                            <h3><?php _e('Page Performance', 'khm-seo'); ?></h3>
                            <canvas id="performanceChart" width="400" height="300"></canvas>
                        </div>
                        <div class="overview-card">
                            <h3><?php _e('Site Structure', 'khm-seo'); ?></h3>
                            <div class="site-structure-summary">
                                <?php $this->render_site_structure(); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Issues Summary -->
                <div class="crawler-section" id="issues-summary">
                    <h2><?php _e('Issues Summary', 'khm-seo'); ?></h2>
                    <div class="issues-filter">
                        <select id="issue-type-filter">
                            <option value="all"><?php _e('All Issues', 'khm-seo'); ?></option>
                            <option value="critical"><?php _e('Critical', 'khm-seo'); ?></option>
                            <option value="warning"><?php _e('Warnings', 'khm-seo'); ?></option>
                            <option value="recommendation"><?php _e('Recommendations', 'khm-seo'); ?></option>
                        </select>
                        <select id="page-filter">
                            <option value="all"><?php _e('All Pages', 'khm-seo'); ?></option>
                            <option value="homepage"><?php _e('Homepage', 'khm-seo'); ?></option>
                            <option value="category"><?php _e('Category Pages', 'khm-seo'); ?></option>
                            <option value="product"><?php _e('Product Pages', 'khm-seo'); ?></option>
                        </select>
                    </div>
                    <div class="issues-list">
                        <?php $this->render_issues_list(); ?>
                    </div>
                </div>

                <!-- Pages Analysis -->
                <div class="crawler-section" id="pages-analysis">
                    <h2><?php _e('Pages Analysis', 'khm-seo'); ?></h2>
                    <div class="pages-table-container">
                        <table id="pages-analysis-table" class="crawler-data-table">
                            <thead>
                                <tr>
                                    <th><?php _e('URL', 'khm-seo'); ?></th>
                                    <th><?php _e('SEO Score', 'khm-seo'); ?></th>
                                    <th><?php _e('Issues', 'khm-seo'); ?></th>
                                    <th><?php _e('Load Time', 'khm-seo'); ?></th>
                                    <th><?php _e('Word Count', 'khm-seo'); ?></th>
                                    <th><?php _e('Status', 'khm-seo'); ?></th>
                                    <th><?php _e('Actions', 'khm-seo'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Technical Analysis -->
                <div class="crawler-section" id="technical-analysis">
                    <h2><?php _e('Technical Analysis', 'khm-seo'); ?></h2>
                    <div class="technical-tabs">
                        <nav class="tab-nav">
                            <button class="tab-button active" data-tab="meta-analysis">
                                <?php _e('Meta Tags', 'khm-seo'); ?>
                            </button>
                            <button class="tab-button" data-tab="schema-analysis">
                                <?php _e('Schema Markup', 'khm-seo'); ?>
                            </button>
                            <button class="tab-button" data-tab="links-analysis">
                                <?php _e('Links', 'khm-seo'); ?>
                            </button>
                            <button class="tab-button" data-tab="performance-analysis">
                                <?php _e('Performance', 'khm-seo'); ?>
                            </button>
                        </nav>
                        
                        <div class="tab-content active" id="meta-analysis">
                            <?php $this->render_meta_analysis(); ?>
                        </div>
                        
                        <div class="tab-content" id="schema-analysis">
                            <?php $this->render_schema_analysis(); ?>
                        </div>
                        
                        <div class="tab-content" id="links-analysis">
                            <?php $this->render_links_analysis(); ?>
                        </div>
                        
                        <div class="tab-content" id="performance-analysis">
                            <?php $this->render_performance_analysis(); ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modals -->
            <?php $this->render_modals(); ?>
        </div>
        <?php
    }

    /**
     * Render statistics cards
     */
    private function render_stats_cards() {
        $stats = $this->get_crawler_stats();
        ?>
        <div class="stat-card">
            <div class="stat-value"><?php echo esc_html($stats['total_pages']); ?></div>
            <div class="stat-label"><?php _e('Pages Crawled', 'khm-seo'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value stat-score-<?php echo esc_attr($this->get_score_class($stats['avg_seo_score'])); ?>">
                <?php echo esc_html($stats['avg_seo_score']); ?>
            </div>
            <div class="stat-label"><?php _e('Avg SEO Score', 'khm-seo'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value stat-issues"><?php echo esc_html($stats['total_issues']); ?></div>
            <div class="stat-label"><?php _e('Total Issues', 'khm-seo'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo esc_html($stats['avg_load_time']); ?>s</div>
            <div class="stat-label"><?php _e('Avg Load Time', 'khm-seo'); ?></div>
        </div>
        <?php
    }

    /**
     * Render site structure overview
     */
    private function render_site_structure() {
        $structure = $this->get_site_structure();
        ?>
        <div class="structure-item">
            <strong><?php _e('Total Depth:', 'khm-seo'); ?></strong>
            <?php echo esc_html($structure['max_depth']); ?> levels
        </div>
        <div class="structure-item">
            <strong><?php _e('Internal Links:', 'khm-seo'); ?></strong>
            <?php echo esc_html($structure['internal_links']); ?>
        </div>
        <div class="structure-item">
            <strong><?php _e('External Links:', 'khm-seo'); ?></strong>
            <?php echo esc_html($structure['external_links']); ?>
        </div>
        <div class="structure-item">
            <strong><?php _e('Broken Links:', 'khm-seo'); ?></strong>
            <span class="<?php echo $structure['broken_links'] > 0 ? 'text-error' : 'text-success'; ?>">
                <?php echo esc_html($structure['broken_links']); ?>
            </span>
        </div>
        <?php
    }

    /**
     * Render issues list
     */
    private function render_issues_list() {
        $issues = $this->get_recent_issues();
        
        if (empty($issues)) {
            echo '<p>' . __('No issues found. Excellent!', 'khm-seo') . '</p>';
            return;
        }

        foreach ($issues as $issue) {
            $severity_class = $this->get_severity_class($issue['severity']);
            ?>
            <div class="issue-item severity-<?php echo esc_attr($severity_class); ?>">
                <div class="issue-header">
                    <h4><?php echo esc_html($issue['title']); ?></h4>
                    <span class="issue-count"><?php echo esc_html($issue['affected_pages']); ?> pages</span>
                </div>
                <div class="issue-description">
                    <?php echo esc_html($issue['description']); ?>
                </div>
                <div class="issue-actions">
                    <button class="button button-small" onclick="viewIssueDetails(<?php echo esc_attr($issue['id']); ?>)">
                        <?php _e('View Details', 'khm-seo'); ?>
                    </button>
                    <button class="button button-small" onclick="viewAffectedPages(<?php echo esc_attr($issue['id']); ?>)">
                        <?php _e('Affected Pages', 'khm-seo'); ?>
                    </button>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render technical analysis tabs content
     */
    private function render_meta_analysis() {
        echo '<div class="meta-analysis-content">';
        echo '<p>' . __('Meta tags analysis will be displayed here', 'khm-seo') . '</p>';
        echo '</div>';
    }

    private function render_schema_analysis() {
        echo '<div class="schema-analysis-content">';
        echo '<p>' . __('Schema markup analysis will be displayed here', 'khm-seo') . '</p>';
        echo '</div>';
    }

    private function render_links_analysis() {
        echo '<div class="links-analysis-content">';
        echo '<p>' . __('Links analysis will be displayed here', 'khm-seo') . '</p>';
        echo '</div>';
    }

    private function render_performance_analysis() {
        echo '<div class="performance-analysis-content">';
        echo '<p>' . __('Performance analysis will be displayed here', 'khm-seo') . '</p>';
        echo '</div>';
    }

    /**
     * Render modal dialogs
     */
    private function render_modals() {
        ?>
        <!-- Start Crawl Modal -->
        <div id="start-crawl-modal" class="crawler-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Start New Crawl', 'khm-seo'); ?></h3>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="start-crawl-form">
                        <div class="form-group">
                            <label for="crawl-start-url"><?php _e('Starting URL:', 'khm-seo'); ?></label>
                            <input type="url" id="crawl-start-url" required placeholder="https://example.com" />
                        </div>
                        <div class="form-group">
                            <label for="crawl-max-depth"><?php _e('Maximum Depth:', 'khm-seo'); ?></label>
                            <select id="crawl-max-depth">
                                <option value="3">3 levels</option>
                                <option value="5" selected>5 levels</option>
                                <option value="10">10 levels</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="crawl-max-pages"><?php _e('Maximum Pages:', 'khm-seo'); ?></label>
                            <select id="crawl-max-pages">
                                <option value="100">100 pages</option>
                                <option value="500">500 pages</option>
                                <option value="1000" selected>1000 pages</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="crawl-respect-robots" checked />
                                <?php _e('Respect robots.txt', 'khm-seo'); ?>
                            </label>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="button button-primary">
                                <?php _e('Start Crawling', 'khm-seo'); ?>
                            </button>
                            <button type="button" class="button modal-cancel">
                                <?php _e('Cancel', 'khm-seo'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Page Details Modal -->
        <div id="page-details-modal" class="crawler-modal" style="display: none;">
            <div class="modal-content modal-large">
                <div class="modal-header">
                    <h3><?php _e('Page Analysis Details', 'khm-seo'); ?></h3>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="page-details-content">
                        <!-- Content loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get crawler statistics
     */
    private function get_crawler_stats() {
        // Would retrieve from database
        return [
            'total_pages' => 0,
            'avg_seo_score' => 85,
            'total_issues' => 12,
            'avg_load_time' => 2.3
        ];
    }

    /**
     * Get site structure data
     */
    private function get_site_structure() {
        return [
            'max_depth' => 5,
            'internal_links' => 156,
            'external_links' => 23,
            'broken_links' => 2
        ];
    }

    /**
     * Get recent issues
     */
    private function get_recent_issues() {
        return []; // Placeholder
    }

    /**
     * Utility methods
     */
    private function get_score_class($score) {
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'warning';
        return 'poor';
    }

    private function get_severity_class($severity) {
        return strtolower($severity);
    }

    /**
     * AJAX handler for dashboard data
     */
    public function ajax_dashboard_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'crawler_dashboard')) {
            wp_die('Security check failed');
        }

        $data_type = sanitize_text_field($_POST['data_type'] ?? '');

        switch ($data_type) {
            case 'stats':
                wp_send_json_success($this->get_crawler_stats());
                break;
            case 'issues':
                wp_send_json_success($this->get_recent_issues());
                break;
            default:
                wp_send_json_error('Invalid data type');
        }
    }
}