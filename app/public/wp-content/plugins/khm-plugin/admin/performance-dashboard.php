<?php
/**
 * KHM Attribution Performance Dashboard
 * 
 * Real-time performance monitoring and optimization metrics
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Performance_Dashboard {
    
    private $performance_manager;
    private $query_builder;
    
    public function __construct() {
        // Load performance components
        require_once dirname(__FILE__) . '/PerformanceManager.php';
        require_once dirname(__FILE__) . '/QueryBuilder.php';
        
        $this->performance_manager = new KHM_Attribution_Performance_Manager();
        $this->query_builder = new KHM_Attribution_Query_Builder();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_performance_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_khm_get_performance_metrics', array($this, 'get_performance_metrics_ajax'));
        add_action('wp_ajax_khm_optimize_performance', array($this, 'optimize_performance_ajax'));
    }
    
    /**
     * Add performance monitoring menu
     */
    public function add_performance_menu() {
        add_submenu_page(
            'khm-attribution',
            'Performance Monitor',
            'Performance',
            'manage_options',
            'khm-performance',
            array($this, 'render_performance_dashboard')
        );
    }
    
    /**
     * Render performance dashboard
     */
    public function render_performance_dashboard() {
        ?>
        <div class="wrap">
            <h1>🚀 Attribution System Performance Monitor</h1>
            
            <!-- Performance Overview Cards -->
            <div class="khm-performance-overview">
                <div class="khm-perf-card" id="api-performance">
                    <h3>API Performance</h3>
                    <div class="khm-metric">
                        <span class="khm-metric-value" id="avg-response-time">--</span>
                        <span class="khm-metric-label">Avg Response Time</span>
                    </div>
                    <div class="khm-metric">
                        <span class="khm-metric-value" id="p95-response-time">--</span>
                        <span class="khm-metric-label">P95 Response Time</span>
                    </div>
                    <div class="khm-slo-status" id="slo-status">
                        <span class="slo-label">SLO Status:</span>
                        <span class="slo-value">Checking...</span>
                    </div>
                </div>
                
                <div class="khm-perf-card" id="cache-performance">
                    <h3>Cache Performance</h3>
                    <div class="khm-metric">
                        <span class="khm-metric-value" id="cache-hit-rate">--</span>
                        <span class="khm-metric-label">Hit Rate</span>
                    </div>
                    <div class="khm-metric">
                        <span class="khm-metric-value" id="cache-method">--</span>
                        <span class="khm-metric-label">Cache Method</span>
                    </div>
                </div>
                
                <div class="khm-perf-card" id="query-performance">
                    <h3>Database Performance</h3>
                    <div class="khm-metric">
                        <span class="khm-metric-value" id="avg-query-time">--</span>
                        <span class="khm-metric-label">Avg Query Time</span>
                    </div>
                    <div class="khm-metric">
                        <span class="khm-metric-value" id="total-queries">--</span>
                        <span class="khm-metric-label">Total Queries</span>
                    </div>
                </div>
                
                <div class="khm-perf-card" id="throughput-performance">
                    <h3>Throughput</h3>
                    <div class="khm-metric">
                        <span class="khm-metric-value" id="events-per-minute">--</span>
                        <span class="khm-metric-label">Events/Minute</span>
                    </div>
                    <div class="khm-metric">
                        <span class="khm-metric-value" id="conversion-rate">--</span>
                        <span class="khm-metric-label">Attribution Rate</span>
                    </div>
                </div>
            </div>
            
            <!-- Performance Charts -->
            <div class="khm-performance-charts">
                <div class="khm-chart-container">
                    <h3>Response Time Trends</h3>
                    <canvas id="responseTimeChart" width="800" height="300"></canvas>
                </div>
                
                <div class="khm-chart-container">
                    <h3>Attribution Volume</h3>
                    <canvas id="volumeChart" width="800" height="300"></canvas>
                </div>
            </div>
            
            <!-- Performance Controls -->
            <div class="khm-performance-controls">
                <div class="khm-control-section">
                    <h3>Performance Optimization</h3>
                    <div class="khm-control-buttons">
                        <button class="button button-primary" onclick="optimizeCache()">
                            Optimize Cache
                        </button>
                        <button class="button" onclick="optimizeDatabase()">
                            Optimize Database
                        </button>
                        <button class="button" onclick="clearOldData()">
                            Clear Old Data
                        </button>
                        <button class="button" onclick="regenerateIndexes()">
                            Rebuild Indexes
                        </button>
                    </div>
                </div>
                
                <div class="khm-control-section">
                    <h3>Cache Management</h3>
                    <div class="khm-cache-controls">
                        <button class="button" onclick="warmCache()">
                            Warm Cache
                        </button>
                        <button class="button" onclick="clearCache()">
                            Clear Cache
                        </button>
                        <button class="button" onclick="testCachePerformance()">
                            Test Cache Performance
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Metrics -->
            <div class="khm-detailed-metrics">
                <div class="khm-metrics-section">
                    <h3>API Endpoint Performance</h3>
                    <table class="wp-list-table widefat fixed striped" id="endpoint-performance-table">
                        <thead>
                            <tr>
                                <th>Endpoint</th>
                                <th>Avg Response Time</th>
                                <th>P95 Response Time</th>
                                <th>Total Requests</th>
                                <th>SLO Violations</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="endpoint-performance-body">
                            <tr><td colspan="6">Loading performance data...</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="khm-metrics-section">
                    <h3>System Health Checks</h3>
                    <div id="health-checks" class="khm-health-grid">
                        <!-- Health checks will be populated via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .khm-performance-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .khm-perf-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .khm-perf-card h3 {
            margin: 0 0 15px 0;
            color: #0073aa;
            font-size: 16px;
        }
        
        .khm-metric {
            margin-bottom: 10px;
        }
        
        .khm-metric-value {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .khm-metric-label {
            display: block;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .khm-slo-status {
            margin-top: 15px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .slo-status-good {
            background: #d4edda;
            color: #155724;
        }
        
        .slo-status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .slo-status-critical {
            background: #f8d7da;
            color: #721c24;
        }
        
        .khm-performance-charts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }
        
        .khm-chart-container {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .khm-chart-container h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .khm-performance-controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }
        
        .khm-control-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .khm-control-section h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .khm-control-buttons,
        .khm-cache-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .khm-detailed-metrics {
            margin-top: 30px;
        }
        
        .khm-metrics-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .khm-metrics-section h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .khm-health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .health-check-item {
            padding: 12px;
            border-radius: 4px;
            text-align: center;
        }
        
        .health-check-good {
            background: #d4edda;
            color: #155724;
        }
        
        .health-check-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .health-check-critical {
            background: #f8d7da;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .khm-performance-overview,
            .khm-performance-charts,
            .khm-performance-controls {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <script>
        let performanceCharts = {};
        
        jQuery(document).ready(function($) {
            // Initialize performance monitoring
            initializePerformanceDashboard();
            
            // Refresh data every 30 seconds
            setInterval(refreshPerformanceData, 30000);
        });
        
        function initializePerformanceDashboard() {
            // Load initial performance data
            refreshPerformanceData();
            
            // Initialize charts
            initializePerformanceCharts();
        }
        
        function refreshPerformanceData() {
            jQuery.post(ajaxurl, {
                action: 'khm_get_performance_metrics',
                nonce: '<?php echo wp_create_nonce('khm_performance_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    updatePerformanceMetrics(response.data);
                }
            });
        }
        
        function updatePerformanceMetrics(data) {
            // Update overview cards
            jQuery('#avg-response-time').text(formatTime(data.api_performance.avg_time));
            jQuery('#p95-response-time').text(formatTime(data.api_performance.p95_time));
            jQuery('#cache-hit-rate').text(formatPercentage(data.cache_performance.hit_rate));
            jQuery('#cache-method').text(data.cache_performance.cache_method);
            jQuery('#avg-query-time').text(formatTime(data.query_performance.avg_time));
            jQuery('#total-queries').text(data.query_performance.total_queries);
            
            // Update SLO status
            updateSLOStatus(data.api_performance.p95_time);
            
            // Update endpoint performance table
            updateEndpointTable(data.api_performance);
            
            // Update health checks
            updateHealthChecks(data.health_checks || {});
        }
        
        function updateSLOStatus(p95Time) {
            const sloElement = jQuery('#slo-status');
            const sloValue = sloElement.find('.slo-value');
            
            // Remove existing classes
            sloElement.removeClass('slo-status-good slo-status-warning slo-status-critical');
            
            if (p95Time < 0.2) { // 200ms SLO
                sloElement.addClass('slo-status-good');
                sloValue.text('✅ Meeting SLO');
            } else if (p95Time < 0.3) {
                sloElement.addClass('slo-status-warning');
                sloValue.text('⚠️ Near SLO Limit');
            } else {
                sloElement.addClass('slo-status-critical');
                sloValue.text('❌ SLO Violation');
            }
        }
        
        function formatTime(seconds) {
            if (seconds < 0.001) return '< 1ms';
            if (seconds < 1) return Math.round(seconds * 1000) + 'ms';
            return seconds.toFixed(2) + 's';
        }
        
        function formatPercentage(value) {
            return Math.round(value) + '%';
        }
        
        function initializePerformanceCharts() {
            // Initialize response time chart
            const responseTimeCtx = document.getElementById('responseTimeChart');
            if (responseTimeCtx) {
                performanceCharts.responseTime = new Chart(responseTimeCtx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Average Response Time',
                            data: [],
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'P95 Response Time',
                            data: [],
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Response Time (ms)'
                                }
                            }
                        }
                    }
                });
            }
            
            // Initialize volume chart
            const volumeCtx = document.getElementById('volumeChart');
            if (volumeCtx) {
                performanceCharts.volume = new Chart(volumeCtx, {
                    type: 'bar',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Attribution Events',
                            data: [],
                            backgroundColor: '#28a745'
                        }, {
                            label: 'Conversions',
                            data: [],
                            backgroundColor: '#0073aa'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }
        
        // Performance optimization functions
        function optimizeCache() {
            showOptimizationMessage('Optimizing cache...', 'info');
            
            jQuery.post(ajaxurl, {
                action: 'khm_optimize_performance',
                optimization_type: 'cache',
                nonce: '<?php echo wp_create_nonce('khm_performance_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    showOptimizationMessage('Cache optimization completed successfully!', 'success');
                } else {
                    showOptimizationMessage('Cache optimization failed: ' + response.data, 'error');
                }
            });
        }
        
        function optimizeDatabase() {
            showOptimizationMessage('Optimizing database...', 'info');
            
            jQuery.post(ajaxurl, {
                action: 'khm_optimize_performance',
                optimization_type: 'database',
                nonce: '<?php echo wp_create_nonce('khm_performance_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    showOptimizationMessage('Database optimization completed!', 'success');
                } else {
                    showOptimizationMessage('Database optimization failed: ' + response.data, 'error');
                }
            });
        }
        
        function showOptimizationMessage(message, type) {
            const alertClass = type === 'success' ? 'notice-success' : 
                              type === 'error' ? 'notice-error' : 'notice-info';
            
            const notice = jQuery(`<div class="notice ${alertClass} is-dismissible"><p>${message}</p></div>`);
            jQuery('.wrap h1').after(notice);
            
            setTimeout(() => {
                notice.fadeOut(500, function() {
                    jQuery(this).remove();
                });
            }, 5000);
        }
        </script>
        <?php
    }
    
    /**
     * AJAX handler for performance metrics
     */
    public function get_performance_metrics_ajax() {
        check_ajax_referer('khm_performance_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $metrics = $this->performance_manager->get_performance_metrics();
        
        // Add current system metrics
        $metrics['system'] = array(
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'load_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        );
        
        // Add health checks
        $metrics['health_checks'] = $this->run_health_checks();
        
        wp_send_json_success($metrics);
    }
    
    /**
     * AJAX handler for performance optimization
     */
    public function optimize_performance_ajax() {
        check_ajax_referer('khm_performance_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $optimization_type = $_POST['optimization_type'] ?? '';
        
        switch ($optimization_type) {
            case 'cache':
                $result = $this->optimize_cache();
                break;
            case 'database':
                $result = $this->optimize_database();
                break;
            case 'cleanup':
                $result = $this->cleanup_old_data();
                break;
            default:
                wp_send_json_error('Invalid optimization type');
                return;
        }
        
        if ($result) {
            wp_send_json_success('Optimization completed successfully');
        } else {
            wp_send_json_error('Optimization failed');
        }
    }
    
    /**
     * Run system health checks
     */
    private function run_health_checks() {
        $checks = array();
        
        // Database connectivity
        global $wpdb;
        $checks['database'] = array(
            'status' => $wpdb->check_connection() ? 'good' : 'critical',
            'message' => $wpdb->check_connection() ? 'Database connected' : 'Database connection failed'
        );
        
        // Cache availability
        $cache_available = function_exists('wp_cache_get') || class_exists('Redis') || class_exists('Memcached');
        $checks['cache'] = array(
            'status' => $cache_available ? 'good' : 'warning',
            'message' => $cache_available ? 'Cache system available' : 'No external cache available'
        );
        
        // Memory usage
        $memory_usage = memory_get_usage(true) / 1024 / 1024; // MB
        $memory_limit = ini_get('memory_limit');
        $memory_limit_mb = intval($memory_limit);
        
        $memory_percentage = ($memory_usage / $memory_limit_mb) * 100;
        
        $checks['memory'] = array(
            'status' => $memory_percentage < 80 ? 'good' : ($memory_percentage < 90 ? 'warning' : 'critical'),
            'message' => sprintf('Memory usage: %.1fMB / %s (%.1f%%)', $memory_usage, $memory_limit, $memory_percentage)
        );
        
        // Attribution tables
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_events}'") === $table_events;
        
        $checks['tables'] = array(
            'status' => $table_exists ? 'good' : 'critical',
            'message' => $table_exists ? 'Attribution tables exist' : 'Attribution tables missing'
        );
        
        return $checks;
    }
    
    /**
     * Optimize cache performance
     */
    private function optimize_cache() {
        // Clear expired cache entries
        wp_cache_flush();
        
        // Warm critical cache entries
        $this->warm_critical_cache();
        
        return true;
    }
    
    /**
     * Optimize database performance
     */
    private function optimize_database() {
        global $wpdb;
        
        // Optimize attribution tables
        $tables = array(
            $wpdb->prefix . 'khm_attribution_events',
            $wpdb->prefix . 'khm_conversion_tracking'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }
        
        // Update table statistics
        foreach ($tables as $table) {
            $wpdb->query("ANALYZE TABLE {$table}");
        }
        
        return true;
    }
    
    /**
     * Clean up old data
     */
    private function cleanup_old_data() {
        return $this->performance_manager->cleanup_old_data();
    }
    
    /**
     * Warm critical cache entries
     */
    private function warm_critical_cache() {
        // Warm frequently accessed attribution data
        $common_queries = array(
            'recent_events' => "SELECT * FROM {$this->wpdb->prefix}khm_attribution_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 100",
            'top_affiliates' => "SELECT affiliate_id, COUNT(*) as count FROM {$this->wpdb->prefix}khm_attribution_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY affiliate_id ORDER BY count DESC LIMIT 20"
        );
        
        foreach ($common_queries as $key => $sql) {
            $this->performance_manager->execute_cached_query($sql, "warm_cache_{$key}", 3600);
        }
    }
}

// Initialize performance dashboard
if (is_admin()) {
    new KHM_Attribution_Performance_Dashboard();
}
?>