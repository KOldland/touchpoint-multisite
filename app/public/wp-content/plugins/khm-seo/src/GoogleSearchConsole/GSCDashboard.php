<?php
/**
 * Google Search Console Dashboard Interface
 * 
 * This class provides a comprehensive dashboard for GSC data visualization
 * including real-time data display, property management, and analytics.
 * 
 * @package KHM_SEO
 * @subpackage GoogleSearchConsole
 * @since 9.0.0
 */

namespace KHM_SEO\GoogleSearchConsole;

use KHM_SEO\GoogleSearchConsole\GSCManager;

class GSCDashboard {
    
    private $gsc_manager;
    
    // Chart types available
    const CHART_TYPES = [
        'line' => 'Line Chart',
        'bar' => 'Bar Chart',
        'pie' => 'Pie Chart',
        'area' => 'Area Chart',
        'heatmap' => 'Heatmap',
        'table' => 'Data Table'
    ];
    
    // Time range options
    const TIME_RANGES = [
        '7d' => 'Last 7 Days',
        '14d' => 'Last 14 Days',
        '30d' => 'Last 30 Days',
        '90d' => 'Last 90 Days',
        '180d' => 'Last 6 Months',
        '365d' => 'Last 12 Months',
        'custom' => 'Custom Range'
    ];
    
    // Dashboard widgets
    const WIDGETS = [
        'overview' => 'Performance Overview',
        'top_queries' => 'Top Search Queries',
        'top_pages' => 'Top Landing Pages',
        'countries' => 'Performance by Country',
        'devices' => 'Device Performance',
        'search_appearance' => 'Search Appearance',
        'url_inspection' => 'URL Inspection Tool',
        'sitemaps' => 'Sitemap Status',
        'indexing_requests' => 'Indexing Requests'
    ];
    
    public function __construct() {
        $this->gsc_manager = new GSCManager();
        
        // WordPress hooks
        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_gsc_dashboard_data', [$this, 'ajax_dashboard_data']);
        add_action('wp_ajax_gsc_widget_data', [$this, 'ajax_widget_data']);
        add_action('wp_ajax_gsc_export_data', [$this, 'ajax_export_data']);
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_pages() {
        add_menu_page(
            'SEO Measurement',
            'SEO Measurement',
            'manage_options',
            'khm-seo-dashboard',
            [$this, 'render_main_dashboard'],
            'dashicons-chart-line',
            25
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'Search Console',
            'Search Console',
            'manage_options',
            'khm-seo-gsc',
            [$this, 'render_gsc_dashboard']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'GSC Properties',
            'Properties',
            'manage_options',
            'khm-seo-gsc-properties',
            [$this, 'render_properties_page']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'URL Inspector',
            'URL Inspector',
            'manage_options',
            'khm-seo-url-inspector',
            [$this, 'render_url_inspector']
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'khm-seo') === false) {
            return;
        }
        
        // Chart.js for visualizations
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1'
        );
        
        // DataTables for advanced table functionality
        wp_enqueue_script(
            'datatables-js',
            'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js',
            ['jquery'],
            '1.13.4'
        );
        
        wp_enqueue_style(
            'datatables-css',
            'https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css',
            [],
            '1.13.4'
        );
        
        // Custom dashboard scripts
        wp_enqueue_script(
            'khm-seo-gsc-dashboard',
            plugins_url('assets/js/gsc-dashboard.js', __FILE__),
            ['jquery', 'chart-js', 'datatables-js'],
            '1.0.0',
            true
        );
        
        wp_enqueue_style(
            'khm-seo-gsc-dashboard',
            plugins_url('assets/css/gsc-dashboard.css', __FILE__),
            [],
            '1.0.0'
        );
        
        // Localize script with AJAX URL and nonces
        wp_localize_script('khm-seo-gsc-dashboard', 'khmSeoGsc', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonces' => [
                'dashboard' => wp_create_nonce('khm_seo_gsc_dashboard'),
                'widget' => wp_create_nonce('khm_seo_gsc_widget'),
                'export' => wp_create_nonce('khm_seo_gsc_export'),
                'sync' => wp_create_nonce('khm_seo_gsc_sync'),
                'properties' => wp_create_nonce('khm_seo_gsc_properties'),
                'inspect' => wp_create_nonce('khm_seo_gsc_inspect'),
                'index' => wp_create_nonce('khm_seo_gsc_index')
            ],
            'chartColors' => [
                'primary' => '#1e73be',
                'secondary' => '#dd3333',
                'success' => '#81c784',
                'warning' => '#ffb74d',
                'info' => '#64b5f6'
            ]
        ]);
    }
    
    /**
     * Render main SEO dashboard
     */
    public function render_main_dashboard() {
        $current_user = wp_get_current_user();
        ?>
        <div class="wrap khm-seo-dashboard">
            <h1 class="wp-heading-inline">SEO Measurement Dashboard</h1>
            <hr class="wp-header-end">
            
            <div class="dashboard-welcome">
                <h2>Welcome back, <?php echo esc_html($current_user->display_name); ?>!</h2>
                <p>Monitor your website's search performance with comprehensive SEO analytics.</p>
            </div>
            
            <!-- Quick Stats Row -->
            <div class="dashboard-stats">
                <div class="stat-box">
                    <div class="stat-icon"><span class="dashicons dashicons-search"></span></div>
                    <div class="stat-content">
                        <h3 id="total-queries">Loading...</h3>
                        <p>Total Search Queries</p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon"><span class="dashicons dashicons-visibility"></span></div>
                    <div class="stat-content">
                        <h3 id="total-impressions">Loading...</h3>
                        <p>Total Impressions</p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon"><span class="dashicons dashicons-external"></span></div>
                    <div class="stat-content">
                        <h3 id="total-clicks">Loading...</h3>
                        <p>Total Clicks</p>
                    </div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon"><span class="dashicons dashicons-arrow-up-alt"></span></div>
                    <div class="stat-content">
                        <h3 id="avg-position">Loading...</h3>
                        <p>Average Position</p>
                    </div>
                </div>
            </div>
            
            <!-- Main Dashboard Grid -->
            <div class="dashboard-grid">
                <div class="dashboard-card full-width">
                    <div class="card-header">
                        <h3>Performance Overview</h3>
                        <div class="card-actions">
                            <select id="overview-timerange">
                                <?php foreach (self::TIME_RANGES as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>"<?php selected($value, '30d'); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button button-secondary" id="refresh-overview">Refresh</button>
                        </div>
                    </div>
                    <div class="card-content">
                        <canvas id="performance-chart" height="100"></canvas>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Top Search Queries</h3>
                        <a href="<?php echo admin_url('admin.php?page=khm-seo-gsc'); ?>" class="button button-small">View All</a>
                    </div>
                    <div class="card-content">
                        <div id="top-queries-table">Loading...</div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Top Landing Pages</h3>
                        <a href="<?php echo admin_url('admin.php?page=khm-seo-gsc'); ?>" class="button button-small">View All</a>
                    </div>
                    <div class="card-content">
                        <div id="top-pages-table">Loading...</div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Device Performance</h3>
                    </div>
                    <div class="card-content">
                        <canvas id="device-chart"></canvas>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Connected Properties</h3>
                        <a href="<?php echo admin_url('admin.php?page=khm-seo-gsc-properties'); ?>" class="button button-small">Manage</a>
                    </div>
                    <div class="card-content">
                        <div id="connected-properties">Loading...</div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="card-content">
                        <div id="recent-activity">
                            <ul class="activity-list">
                                <li><span class="activity-icon success"></span> Daily data sync completed</li>
                                <li><span class="activity-icon info"></span> New property connected</li>
                                <li><span class="activity-icon warning"></span> Rate limit approaching</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize dashboard
            KHMSeoGscDashboard.init();
        });
        </script>
        <?php
    }
    
    /**
     * Render GSC detailed dashboard
     */
    public function render_gsc_dashboard() {
        ?>
        <div class="wrap khm-seo-gsc-dashboard">
            <h1 class="wp-heading-inline">Google Search Console Analytics</h1>
            
            <!-- Filters and Controls -->
            <div class="dashboard-filters">
                <div class="filter-group">
                    <label for="property-selector">Property:</label>
                    <select id="property-selector">
                        <option value="">Loading properties...</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date-range">Date Range:</label>
                    <select id="date-range">
                        <?php foreach (self::TIME_RANGES as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="dimension-selector">Group By:</label>
                    <select id="dimension-selector">
                        <option value="query">Search Query</option>
                        <option value="page">Landing Page</option>
                        <option value="country">Country</option>
                        <option value="device">Device</option>
                        <option value="searchAppearance">Search Appearance</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button class="button button-primary" id="apply-filters">Apply Filters</button>
                    <button class="button button-secondary" id="export-data">Export Data</button>
                    <button class="button button-secondary" id="sync-data">Sync Latest</button>
                </div>
            </div>
            
            <!-- Data Visualization Tabs -->
            <div class="nav-tab-wrapper">
                <a href="#tab-overview" class="nav-tab nav-tab-active">Overview</a>
                <a href="#tab-queries" class="nav-tab">Queries</a>
                <a href="#tab-pages" class="nav-tab">Pages</a>
                <a href="#tab-countries" class="nav-tab">Countries</a>
                <a href="#tab-devices" class="nav-tab">Devices</a>
                <a href="#tab-comparison" class="nav-tab">Comparison</a>
            </div>
            
            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Overview Tab -->
                <div id="tab-overview" class="tab-panel active">
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <h3 id="metric-impressions">0</h3>
                            <p>Total Impressions</p>
                            <span class="metric-change" id="impressions-change">+0%</span>
                        </div>
                        
                        <div class="metric-card">
                            <h3 id="metric-clicks">0</h3>
                            <p>Total Clicks</p>
                            <span class="metric-change" id="clicks-change">+0%</span>
                        </div>
                        
                        <div class="metric-card">
                            <h3 id="metric-ctr">0%</h3>
                            <p>Click-through Rate</p>
                            <span class="metric-change" id="ctr-change">+0%</span>
                        </div>
                        
                        <div class="metric-card">
                            <h3 id="metric-position">0</h3>
                            <p>Average Position</p>
                            <span class="metric-change" id="position-change">+0</span>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="overview-chart" height="150"></canvas>
                    </div>
                </div>
                
                <!-- Queries Tab -->
                <div id="tab-queries" class="tab-panel">
                    <div class="table-controls">
                        <input type="search" id="query-search" placeholder="Search queries...">
                        <select id="query-sort">
                            <option value="impressions">Sort by Impressions</option>
                            <option value="clicks">Sort by Clicks</option>
                            <option value="ctr">Sort by CTR</option>
                            <option value="position">Sort by Position</option>
                        </select>
                    </div>
                    
                    <table id="queries-table" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Search Query</th>
                                <th>Impressions</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                                <th>Avg. Position</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                
                <!-- Pages Tab -->
                <div id="tab-pages" class="tab-panel">
                    <div class="table-controls">
                        <input type="search" id="page-search" placeholder="Search pages...">
                        <select id="page-filter">
                            <option value="">All Pages</option>
                            <option value="blog">Blog Posts</option>
                            <option value="product">Products</option>
                            <option value="category">Categories</option>
                        </select>
                    </div>
                    
                    <table id="pages-table" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>Landing Page</th>
                                <th>Impressions</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                                <th>Avg. Position</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                
                <!-- Countries Tab -->
                <div id="tab-countries" class="tab-panel">
                    <div class="countries-view">
                        <div class="countries-chart">
                            <canvas id="countries-chart"></canvas>
                        </div>
                        <div class="countries-table">
                            <table id="countries-data" class="display compact">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>Impressions</th>
                                        <th>Clicks</th>
                                        <th>CTR</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Devices Tab -->
                <div id="tab-devices" class="tab-panel">
                    <div class="devices-comparison">
                        <canvas id="devices-comparison-chart" height="200"></canvas>
                    </div>
                    
                    <div class="device-breakdown">
                        <div class="device-card">
                            <h4>Desktop</h4>
                            <div class="device-metrics">
                                <span class="metric">Impressions: <strong id="desktop-impressions">0</strong></span>
                                <span class="metric">Clicks: <strong id="desktop-clicks">0</strong></span>
                                <span class="metric">CTR: <strong id="desktop-ctr">0%</strong></span>
                                <span class="metric">Avg. Position: <strong id="desktop-position">0</strong></span>
                            </div>
                        </div>
                        
                        <div class="device-card">
                            <h4>Mobile</h4>
                            <div class="device-metrics">
                                <span class="metric">Impressions: <strong id="mobile-impressions">0</strong></span>
                                <span class="metric">Clicks: <strong id="mobile-clicks">0</strong></span>
                                <span class="metric">CTR: <strong id="mobile-ctr">0%</strong></span>
                                <span class="metric">Avg. Position: <strong id="mobile-position">0</strong></span>
                            </div>
                        </div>
                        
                        <div class="device-card">
                            <h4>Tablet</h4>
                            <div class="device-metrics">
                                <span class="metric">Impressions: <strong id="tablet-impressions">0</strong></span>
                                <span class="metric">Clicks: <strong id="tablet-clicks">0</strong></span>
                                <span class="metric">CTR: <strong id="tablet-ctr">0%</strong></span>
                                <span class="metric">Avg. Position: <strong id="tablet-position">0</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Comparison Tab -->
                <div id="tab-comparison" class="tab-panel">
                    <div class="comparison-controls">
                        <div class="comparison-dates">
                            <label>Compare:</label>
                            <select id="comparison-period">
                                <option value="previous">Previous Period</option>
                                <option value="last-year">Same Period Last Year</option>
                                <option value="custom">Custom Period</option>
                            </select>
                        </div>
                        
                        <button class="button button-primary" id="run-comparison">Run Comparison</button>
                    </div>
                    
                    <div class="comparison-results">
                        <canvas id="comparison-chart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize GSC dashboard
            KHMSeoGscAnalytics.init();
        });
        </script>
        <?php
    }
    
    /**
     * Render properties management page
     */
    public function render_properties_page() {
        ?>
        <div class="wrap khm-seo-properties">
            <h1 class="wp-heading-inline">GSC Properties Management</h1>
            <a href="#" class="page-title-action" id="refresh-properties">Refresh Properties</a>
            <hr class="wp-header-end">
            
            <div class="properties-overview">
                <div class="properties-stats">
                    <div class="stat-item">
                        <span class="count" id="total-properties">0</span>
                        <span class="label">Total Properties</span>
                    </div>
                    <div class="stat-item">
                        <span class="count" id="verified-properties">0</span>
                        <span class="label">Verified</span>
                    </div>
                    <div class="stat-item">
                        <span class="count" id="domain-properties">0</span>
                        <span class="label">Domain Properties</span>
                    </div>
                </div>
            </div>
            
            <div class="properties-list">
                <table id="properties-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Property URL</th>
                            <th>Type</th>
                            <th>Permission Level</th>
                            <th>Status</th>
                            <th>Last Sync</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="properties-tbody">
                        <tr>
                            <td colspan="6" class="loading">Loading properties...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            KHMSeoPropertiesManager.init();
        });
        </script>
        <?php
    }
    
    /**
     * Render URL Inspector tool
     */
    public function render_url_inspector() {
        ?>
        <div class="wrap khm-seo-url-inspector">
            <h1>URL Inspector</h1>
            
            <div class="inspector-form">
                <div class="form-row">
                    <label for="inspect-url">URL to Inspect:</label>
                    <input type="url" id="inspect-url" placeholder="https://example.com/page" class="large-text">
                </div>
                
                <div class="form-row">
                    <label for="property-select">Property:</label>
                    <select id="property-select">
                        <option value="">Select a property...</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button class="button button-primary" id="inspect-url-btn">Inspect URL</button>
                    <button class="button button-secondary" id="request-indexing-btn" disabled>Request Indexing</button>
                </div>
            </div>
            
            <div class="inspection-results" id="inspection-results" style="display:none;">
                <h2>Inspection Results</h2>
                
                <div class="result-cards">
                    <div class="result-card">
                        <h3>Index Status</h3>
                        <div class="status-indicator" id="index-status">
                            <span class="status-icon"></span>
                            <span class="status-text">Unknown</span>
                        </div>
                        <p id="index-details"></p>
                    </div>
                    
                    <div class="result-card">
                        <h3>Mobile Usability</h3>
                        <div class="status-indicator" id="mobile-status">
                            <span class="status-icon"></span>
                            <span class="status-text">Unknown</span>
                        </div>
                        <div id="mobile-issues"></div>
                    </div>
                    
                    <div class="result-card">
                        <h3>Rich Results</h3>
                        <div class="status-indicator" id="rich-results-status">
                            <span class="status-icon"></span>
                            <span class="status-text">Unknown</span>
                        </div>
                        <div id="rich-results-items"></div>
                    </div>
                </div>
                
                <div class="technical-details">
                    <h3>Technical Details</h3>
                    <table class="technical-table">
                        <tr>
                            <th>Google Canonical:</th>
                            <td id="google-canonical">-</td>
                        </tr>
                        <tr>
                            <th>User Canonical:</th>
                            <td id="user-canonical">-</td>
                        </tr>
                        <tr>
                            <th>Last Crawl Time:</th>
                            <td id="last-crawl">-</td>
                        </tr>
                        <tr>
                            <th>Coverage State:</th>
                            <td id="coverage-state">-</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            KHMSeoUrlInspector.init();
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for dashboard data
     */
    public function ajax_dashboard_data() {
        try {
            if (!current_user_can('manage_options')) {
                throw new \Exception('Insufficient permissions');
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_gsc_dashboard')) {
                throw new \Exception('Security check failed');
            }
            
            $timerange = sanitize_text_field($_POST['timerange'] ?? '30d');
            $properties = $this->gsc_manager->get_properties();
            
            // Get aggregated data for all properties
            $dashboard_data = $this->get_dashboard_summary($properties, $timerange);
            
            wp_send_json_success($dashboard_data);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get dashboard summary data
     */
    private function get_dashboard_summary($properties, $timerange) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_gsc_stats';
        
        // Calculate date range
        $days = intval(str_replace('d', '', $timerange));
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        $end_date = date('Y-m-d');
        
        // Get aggregated metrics
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                AVG(ctr) as avg_ctr,
                AVG(average_position) as avg_position,
                COUNT(DISTINCT query_text) as unique_queries
            FROM {$table_name} 
            WHERE DATE(date_recorded) BETWEEN %s AND %s
        ", $start_date, $end_date));
        
        // Get top queries
        $top_queries = $wpdb->get_results($wpdb->prepare("
            SELECT 
                query_text,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                AVG(ctr) as ctr,
                AVG(average_position) as position
            FROM {$table_name} 
            WHERE DATE(date_recorded) BETWEEN %s AND %s
            AND query_text IS NOT NULL
            GROUP BY query_text
            ORDER BY impressions DESC
            LIMIT 10
        ", $start_date, $end_date));
        
        // Get top pages
        $top_pages = $wpdb->get_results($wpdb->prepare("
            SELECT 
                page_url,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                AVG(ctr) as ctr,
                AVG(average_position) as position
            FROM {$table_name} 
            WHERE DATE(date_recorded) BETWEEN %s AND %s
            AND page_url IS NOT NULL
            GROUP BY page_url
            ORDER BY impressions DESC
            LIMIT 10
        ", $start_date, $end_date));
        
        // Get device breakdown
        $device_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                device_type,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                AVG(ctr) as ctr
            FROM {$table_name} 
            WHERE DATE(date_recorded) BETWEEN %s AND %s
            AND device_type IS NOT NULL
            GROUP BY device_type
        ", $start_date, $end_date));
        
        return [
            'overview' => [
                'total_impressions' => intval($metrics->total_impressions ?? 0),
                'total_clicks' => intval($metrics->total_clicks ?? 0),
                'avg_ctr' => round(floatval($metrics->avg_ctr ?? 0) * 100, 2),
                'avg_position' => round(floatval($metrics->avg_position ?? 0), 1),
                'unique_queries' => intval($metrics->unique_queries ?? 0)
            ],
            'top_queries' => $top_queries,
            'top_pages' => $top_pages,
            'device_data' => $device_data,
            'properties' => $properties,
            'date_range' => [
                'start' => $start_date,
                'end' => $end_date,
                'days' => $days
            ]
        ];
    }
}