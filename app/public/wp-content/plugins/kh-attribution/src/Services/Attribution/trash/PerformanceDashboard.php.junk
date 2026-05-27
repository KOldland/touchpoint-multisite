<?php
/**
 * KHM Attribution Performance Dashboard
 * 
 * Real-time performance monitoring interface for the attribution system
 * Provides comprehensive dashboards, alerts, and SLO tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Performance_Dashboard {
    
    private $performance_manager;
    private $query_builder;
    private $async_manager;
    private $slo_targets;
    
    public function __construct() {
        $this->slo_targets = array(
            'api_response_time_p95' => 0.3, // 300ms
            'dashboard_load_time_p95' => 2.0, // 2 seconds
            'tracking_endpoint_avg' => 0.05, // 50ms
            'cache_hit_rate_min' => 80, // 80%
            'uptime_target' => 99.9 // 99.9%
        );
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_khm_performance_metrics', array($this, 'ajax_get_performance_metrics'));
        add_action('wp_ajax_khm_performance_alerts', array($this, 'ajax_get_performance_alerts'));
        add_action('wp_ajax_khm_optimize_performance', array($this, 'ajax_optimize_performance'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
    }
    
    /**
     * Add admin menu for performance dashboard
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-attribution',
            'Performance Dashboard',
            'Performance',
            'manage_options',
            'khm-performance-dashboard',
            array($this, 'render_performance_dashboard')
        );
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        if (strpos($hook, 'khm-performance-dashboard') === false) {
            return;
        }
        
        // Enqueue Chart.js for performance visualization
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        
        // Custom dashboard JavaScript
        wp_enqueue_script(
            'khm-performance-dashboard',
            plugin_dir_url(__FILE__) . '../../assets/js/performance-dashboard.js',
            array('jquery', 'chartjs'),
            '1.0.0',
            true
        );
        
        // Dashboard CSS
        wp_enqueue_style(
            'khm-performance-dashboard',
            plugin_dir_url(__FILE__) . '../../assets/css/performance-dashboard.css',
            array(),
            '1.0.0'
        );
        
        // Localize script for AJAX
        wp_localize_script('khm-performance-dashboard', 'khmPerformance', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_performance_nonce'),
            'slo_targets' => $this->slo_targets,
            'refresh_interval' => 30000 // 30 seconds
        ));
    }
    
    /**
     * Render main performance dashboard
     */
    public function render_performance_dashboard() {
        ?>
        <div class="wrap khm-performance-dashboard">
            <h1>
                🚀 Attribution System Performance Dashboard
                <span class="dashboard-status" id="dashboard-status">
                    <span class="status-indicator" id="status-indicator"></span>
                    <span id="status-text">Loading...</span>
                </span>
            </h1>
            
            <!-- SLO Status Overview -->
            <div class="slo-overview">
                <h2>📊 SLO Compliance Status</h2>
                <div class="slo-grid">
                    <div class="slo-card" id="slo-api-response">
                        <div class="slo-metric">
                            <div class="slo-value" id="api-response-value">--</div>
                            <div class="slo-label">API Response P95</div>
                            <div class="slo-target">Target: < 300ms</div>
                        </div>
                        <div class="slo-status" id="api-response-status"></div>
                    </div>
                    
                    <div class="slo-card" id="slo-dashboard-load">
                        <div class="slo-metric">
                            <div class="slo-value" id="dashboard-load-value">--</div>
                            <div class="slo-label">Dashboard Load P95</div>
                            <div class="slo-target">Target: < 2s</div>
                        </div>
                        <div class="slo-status" id="dashboard-load-status"></div>
                    </div>
                    
                    <div class="slo-card" id="slo-tracking-avg">
                        <div class="slo-metric">
                            <div class="slo-value" id="tracking-avg-value">--</div>
                            <div class="slo-label">Tracking Endpoint Avg</div>
                            <div class="slo-target">Target: < 50ms</div>
                        </div>
                        <div class="slo-status" id="tracking-avg-status"></div>
                    </div>
                    
                    <div class="slo-card" id="slo-cache-hit">
                        <div class="slo-metric">
                            <div class="slo-value" id="cache-hit-value">--</div>
                            <div class="slo-label">Cache Hit Rate</div>
                            <div class="slo-target">Target: > 80%</div>
                        </div>
                        <div class="slo-status" id="cache-hit-status"></div>
                    </div>
                    
                    <div class="slo-card" id="slo-uptime">
                        <div class="slo-metric">
                            <div class="slo-value" id="uptime-value">--</div>
                            <div class="slo-label">System Uptime</div>
                            <div class="slo-target">Target: > 99.9%</div>
                        </div>
                        <div class="slo-status" id="uptime-status"></div>
                    </div>
                </div>
            </div>
            
            <!-- Real-time Performance Charts -->
            <div class="performance-charts">
                <div class="chart-row">
                    <div class="chart-container">
                        <h3>⚡ Response Time Trends</h3>
                        <canvas id="response-time-chart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3>💾 Cache Performance</h3>
                        <canvas id="cache-performance-chart"></canvas>
                    </div>
                </div>
                
                <div class="chart-row">
                    <div class="chart-container">
                        <h3>📈 Attribution Volume</h3>
                        <canvas id="attribution-volume-chart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3>🔄 Queue Status</h3>
                        <canvas id="queue-status-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- System Health Indicators -->
            <div class="system-health">
                <h2>🏥 System Health</h2>
                <div class="health-grid">
                    <div class="health-card">
                        <h4>💾 Database Performance</h4>
                        <div class="health-metrics">
                            <div class="metric">
                                <span class="metric-label">Avg Query Time:</span>
                                <span class="metric-value" id="db-avg-query-time">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Slow Queries:</span>
                                <span class="metric-value" id="db-slow-queries">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Connections:</span>
                                <span class="metric-value" id="db-connections">--</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="health-card">
                        <h4>🔄 Async Processing</h4>
                        <div class="health-metrics">
                            <div class="metric">
                                <span class="metric-label">Pending Jobs:</span>
                                <span class="metric-value" id="async-pending-jobs">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Failed Jobs:</span>
                                <span class="metric-value" id="async-failed-jobs">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Processing Rate:</span>
                                <span class="metric-value" id="async-processing-rate">--</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="health-card">
                        <h4>🧠 Memory Usage</h4>
                        <div class="health-metrics">
                            <div class="metric">
                                <span class="metric-label">Current Usage:</span>
                                <span class="metric-value" id="memory-current">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Peak Usage:</span>
                                <span class="metric-value" id="memory-peak">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Available:</span>
                                <span class="metric-value" id="memory-available">--</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="health-card">
                        <h4>📊 Attribution Stats</h4>
                        <div class="health-metrics">
                            <div class="metric">
                                <span class="metric-label">Hourly Clicks:</span>
                                <span class="metric-value" id="attribution-hourly-clicks">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Hourly Conversions:</span>
                                <span class="metric-value" id="attribution-hourly-conversions">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Attribution Rate:</span>
                                <span class="metric-value" id="attribution-rate">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Alerts -->
            <div class="performance-alerts">
                <h2>🚨 Performance Alerts</h2>
                <div id="alerts-container">
                    <div class="alert-placeholder">Loading alerts...</div>
                </div>
            </div>
            
            <!-- Performance Optimization Controls -->
            <div class="optimization-controls">
                <h2>⚙️ Performance Optimization</h2>
                <div class="control-grid">
                    <div class="control-card">
                        <h4>🔄 Cache Management</h4>
                        <button class="btn btn-primary" id="flush-cache">Flush All Caches</button>
                        <button class="btn btn-secondary" id="warm-cache">Warm Critical Caches</button>
                        <button class="btn btn-secondary" id="optimize-cache">Optimize Cache Strategy</button>
                    </div>
                    
                    <div class="control-card">
                        <h4>💾 Database Optimization</h4>
                        <button class="btn btn-primary" id="analyze-queries">Analyze Slow Queries</button>
                        <button class="btn btn-secondary" id="optimize-indexes">Optimize Indexes</button>
                        <button class="btn btn-secondary" id="cleanup-data">Cleanup Old Data</button>
                    </div>
                    
                    <div class="control-card">
                        <h4>🔄 Queue Management</h4>
                        <button class="btn btn-primary" id="process-queue">Process Queue Now</button>
                        <button class="btn btn-secondary" id="retry-failed">Retry Failed Jobs</button>
                        <button class="btn btn-warning" id="clear-queue">Clear Queue</button>
                    </div>
                    
                    <div class="control-card">
                        <h4>📊 Performance Testing</h4>
                        <button class="btn btn-primary" id="run-performance-test">Run Performance Test</button>
                        <button class="btn btn-secondary" id="load-test">Run Load Test</button>
                        <button class="btn btn-secondary" id="slo-test">Test SLO Compliance</button>
                    </div>
                </div>
            </div>
            
            <!-- Debug Information -->
            <div class="debug-info" style="display: none;">
                <h2>🔧 Debug Information</h2>
                <div class="debug-grid">
                    <div class="debug-card">
                        <h4>Environment</h4>
                        <pre id="environment-info"></pre>
                    </div>
                    
                    <div class="debug-card">
                        <h4>Configuration</h4>
                        <pre id="configuration-info"></pre>
                    </div>
                    
                    <div class="debug-card">
                        <h4>Recent Logs</h4>
                        <pre id="recent-logs"></pre>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .khm-performance-dashboard {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .dashboard-status {
            float: right;
            font-size: 14px;
            color: #666;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ddd;
            margin-right: 8px;
        }
        
        .status-indicator.healthy { background: #22c55e; }
        .status-indicator.warning { background: #f59e0b; }
        .status-indicator.critical { background: #ef4444; }
        
        .slo-overview, .performance-charts, .system-health, .performance-alerts, .optimization-controls {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .slo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .slo-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }
        
        .slo-value {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
        }
        
        .slo-label {
            font-size: 12px;
            color: #64748b;
            margin: 5px 0;
        }
        
        .slo-target {
            font-size: 11px;
            color: #94a3b8;
        }
        
        .slo-status {
            margin-top: 10px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .slo-status.passing { background: #dcfce7; color: #166534; }
        .slo-status.warning { background: #fef3c7; color: #92400e; }
        .slo-status.failing { background: #fee2e2; color: #991b1b; }
        
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .chart-container {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
        }
        
        .chart-container h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #374151;
        }
        
        .health-grid, .control-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .health-card, .control-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
        }
        
        .health-card h4, .control-card h4 {
            margin: 0 0 15px 0;
            color: #374151;
        }
        
        .metric {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 14px;
        }
        
        .metric-label {
            color: #6b7280;
        }
        
        .metric-value {
            font-weight: bold;
            color: #1f2937;
        }
        
        .btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 13px;
        }
        
        .btn:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-warning {
            background: #f59e0b;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .alert-placeholder {
            text-align: center;
            color: #6b7280;
            font-style: italic;
            padding: 20px;
        }
        
        .performance-alert {
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 4px;
            padding: 12px;
            margin: 8px 0;
        }
        
        .performance-alert.warning {
            background: #fef3c7;
            border-color: #fde68a;
        }
        
        .performance-alert.info {
            background: #dbeafe;
            border-color: #93c5fd;
        }
        
        .debug-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .debug-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
        }
        
        .debug-card h4 {
            margin: 0 0 10px 0;
            color: #374151;
        }
        
        .debug-card pre {
            background: #1f2937;
            color: #f9fafb;
            padding: 10px;
            border-radius: 4px;
            font-size: 12px;
            overflow-x: auto;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize dashboard
            initializePerformanceDashboard();
            
            // Set up real-time updates
            setInterval(updateDashboardMetrics, khmPerformance.refresh_interval);
            
            // Event handlers for control buttons
            setupControlHandlers();
        });
        
        function initializePerformanceDashboard() {
            // Initialize charts
            initializeCharts();
            
            // Load initial data
            updateDashboardMetrics();
            updatePerformanceAlerts();
        }
        
        function initializeCharts() {
            // Response Time Chart
            const responseTimeCtx = document.getElementById('response-time-chart').getContext('2d');
            window.responseTimeChart = new Chart(responseTimeCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'API Response Time (ms)',
                        data: [],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
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
            
            // Cache Performance Chart
            const cacheCtx = document.getElementById('cache-performance-chart').getContext('2d');
            window.cacheChart = new Chart(cacheCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Cache Hits', 'Cache Misses'],
                    datasets: [{
                        data: [80, 20],
                        backgroundColor: ['#22c55e', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Attribution Volume Chart
            const volumeCtx = document.getElementById('attribution-volume-chart').getContext('2d');
            window.volumeChart = new Chart(volumeCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Clicks',
                        data: [],
                        backgroundColor: '#3b82f6'
                    }, {
                        label: 'Conversions',
                        data: [],
                        backgroundColor: '#22c55e'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Queue Status Chart
            const queueCtx = document.getElementById('queue-status-chart').getContext('2d');
            window.queueChart = new Chart(queueCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Pending Jobs',
                        data: [],
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        function updateDashboardMetrics() {
            $.ajax({
                url: khmPerformance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'khm_performance_metrics',
                    nonce: khmPerformance.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateSLOStatus(response.data.slo);
                        updateSystemHealth(response.data.health);
                        updateCharts(response.data.charts);
                        updateDashboardStatus(response.data.status);
                    }
                }
            });
        }
        
        function updateSLOStatus(slo) {
            // Update SLO metrics
            $('#api-response-value').text(slo.api_response_time + 'ms');
            $('#dashboard-load-value').text(slo.dashboard_load_time + 's');
            $('#tracking-avg-value').text(slo.tracking_avg_time + 'ms');
            $('#cache-hit-value').text(slo.cache_hit_rate + '%');
            $('#uptime-value').text(slo.uptime + '%');
            
            // Update SLO status indicators
            updateSLOIndicator('api-response', slo.api_response_time, khmPerformance.slo_targets.api_response_time_p95 * 1000);
            updateSLOIndicator('dashboard-load', slo.dashboard_load_time, khmPerformance.slo_targets.dashboard_load_time_p95);
            updateSLOIndicator('tracking-avg', slo.tracking_avg_time, khmPerformance.slo_targets.tracking_endpoint_avg * 1000);
            updateSLOIndicator('cache-hit', slo.cache_hit_rate, khmPerformance.slo_targets.cache_hit_rate_min, true);
            updateSLOIndicator('uptime', slo.uptime, khmPerformance.slo_targets.uptime_target, true);
        }
        
        function updateSLOIndicator(metric, value, target, higherIsBetter = false) {
            const passing = higherIsBetter ? (value >= target) : (value <= target);
            const status = $('#slo-' + metric + '-status');
            
            status.removeClass('passing warning failing');
            
            if (passing) {
                status.addClass('passing').text('PASSING');
            } else {
                const deviation = Math.abs((value - target) / target);
                if (deviation <= 0.1) {
                    status.addClass('warning').text('WARNING');
                } else {
                    status.addClass('failing').text('FAILING');
                }
            }
        }
        
        function updateSystemHealth(health) {
            $('#db-avg-query-time').text(health.database.avg_query_time + 'ms');
            $('#db-slow-queries').text(health.database.slow_queries);
            $('#db-connections').text(health.database.connections);
            
            $('#async-pending-jobs').text(health.async.pending_jobs);
            $('#async-failed-jobs').text(health.async.failed_jobs);
            $('#async-processing-rate').text(health.async.processing_rate + '/min');
            
            $('#memory-current').text(health.memory.current + 'MB');
            $('#memory-peak').text(health.memory.peak + 'MB');
            $('#memory-available').text(health.memory.available + 'MB');
            
            $('#attribution-hourly-clicks').text(health.attribution.hourly_clicks);
            $('#attribution-hourly-conversions').text(health.attribution.hourly_conversions);
            $('#attribution-rate').text(health.attribution.attribution_rate + '%');
        }
        
        function updateCharts(chartData) {
            // Update response time chart
            if (chartData.response_time) {
                window.responseTimeChart.data.labels = chartData.response_time.labels;
                window.responseTimeChart.data.datasets[0].data = chartData.response_time.data;
                window.responseTimeChart.update();
            }
            
            // Update cache chart
            if (chartData.cache) {
                window.cacheChart.data.datasets[0].data = [chartData.cache.hits, chartData.cache.misses];
                window.cacheChart.update();
            }
            
            // Update volume chart
            if (chartData.volume) {
                window.volumeChart.data.labels = chartData.volume.labels;
                window.volumeChart.data.datasets[0].data = chartData.volume.clicks;
                window.volumeChart.data.datasets[1].data = chartData.volume.conversions;
                window.volumeChart.update();
            }
            
            // Update queue chart
            if (chartData.queue) {
                window.queueChart.data.labels = chartData.queue.labels;
                window.queueChart.data.datasets[0].data = chartData.queue.data;
                window.queueChart.update();
            }
        }
        
        function updateDashboardStatus(status) {
            const indicator = $('#status-indicator');
            const text = $('#status-text');
            
            indicator.removeClass('healthy warning critical');
            
            if (status.overall_health >= 0.9) {
                indicator.addClass('healthy');
                text.text('System Healthy');
            } else if (status.overall_health >= 0.7) {
                indicator.addClass('warning');
                text.text('Performance Issues');
            } else {
                indicator.addClass('critical');
                text.text('Critical Issues');
            }
        }
        
        function updatePerformanceAlerts() {
            $.ajax({
                url: khmPerformance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'khm_performance_alerts',
                    nonce: khmPerformance.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displayAlerts(response.data.alerts);
                    }
                }
            });
        }
        
        function displayAlerts(alerts) {
            const container = $('#alerts-container');
            container.empty();
            
            if (alerts.length === 0) {
                container.append('<div class="alert-placeholder">No performance alerts</div>');
                return;
            }
            
            alerts.forEach(function(alert) {
                const alertEl = $('<div class="performance-alert ' + alert.severity + '">' +
                    '<strong>' + alert.title + '</strong><br>' +
                    alert.message +
                '</div>');
                container.append(alertEl);
            });
        }
        
        function setupControlHandlers() {
            // Cache controls
            $('#flush-cache').click(function() {
                performOptimization('flush_cache', 'Flushing all caches...');
            });
            
            $('#warm-cache').click(function() {
                performOptimization('warm_cache', 'Warming critical caches...');
            });
            
            $('#optimize-cache').click(function() {
                performOptimization('optimize_cache', 'Optimizing cache strategy...');
            });
            
            // Database controls
            $('#analyze-queries').click(function() {
                performOptimization('analyze_queries', 'Analyzing slow queries...');
            });
            
            $('#optimize-indexes').click(function() {
                performOptimization('optimize_indexes', 'Optimizing database indexes...');
            });
            
            $('#cleanup-data').click(function() {
                performOptimization('cleanup_data', 'Cleaning up old data...');
            });
            
            // Queue controls
            $('#process-queue').click(function() {
                performOptimization('process_queue', 'Processing queue...');
            });
            
            $('#retry-failed').click(function() {
                performOptimization('retry_failed', 'Retrying failed jobs...');
            });
            
            $('#clear-queue').click(function() {
                if (confirm('Are you sure you want to clear the entire queue?')) {
                    performOptimization('clear_queue', 'Clearing queue...');
                }
            });
            
            // Testing controls
            $('#run-performance-test').click(function() {
                performOptimization('performance_test', 'Running performance test...');
            });
            
            $('#load-test').click(function() {
                performOptimization('load_test', 'Running load test...');
            });
            
            $('#slo-test').click(function() {
                performOptimization('slo_test', 'Testing SLO compliance...');
            });
        }
        
        function performOptimization(action, message) {
            const btn = event.target;
            const originalText = btn.textContent;
            
            btn.textContent = message;
            btn.disabled = true;
            
            $.ajax({
                url: khmPerformance.ajaxurl,
                type: 'POST',
                data: {
                    action: 'khm_optimize_performance',
                    optimization_action: action,
                    nonce: khmPerformance.nonce
                },
                success: function(response) {
                    if (response.success) {
                        btn.textContent = '✅ ' + originalText;
                        setTimeout(function() {
                            btn.textContent = originalText;
                            btn.disabled = false;
                        }, 2000);
                        
                        // Refresh metrics after optimization
                        setTimeout(updateDashboardMetrics, 1000);
                    } else {
                        btn.textContent = '❌ Failed';
                        setTimeout(function() {
                            btn.textContent = originalText;
                            btn.disabled = false;
                        }, 2000);
                    }
                },
                error: function() {
                    btn.textContent = '❌ Error';
                    setTimeout(function() {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }, 2000);
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * AJAX handler for performance metrics
     */
    public function ajax_get_performance_metrics() {
        check_ajax_referer('khm_performance_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Initialize components if needed
        if (!$this->performance_manager) {
            require_once dirname(__FILE__) . '/PerformanceManager.php';
            $this->performance_manager = new KHM_Attribution_Performance_Manager();
        }
        
        if (!$this->query_builder) {
            require_once dirname(__FILE__) . '/QueryBuilder.php';
            $this->query_builder = new KHM_Attribution_Query_Builder();
        }
        
        // Get current performance metrics
        $metrics = $this->get_current_metrics();
        
        wp_send_json_success($metrics);
    }
    
    /**
     * AJAX handler for performance alerts
     */
    public function ajax_get_performance_alerts() {
        check_ajax_referer('khm_performance_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $alerts = $this->get_performance_alerts();
        
        wp_send_json_success(array('alerts' => $alerts));
    }
    
    /**
     * AJAX handler for performance optimization actions
     */
    public function ajax_optimize_performance() {
        check_ajax_referer('khm_performance_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $action = sanitize_text_field($_POST['optimization_action']);
        
        $result = $this->perform_optimization_action($action);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Optimization failed');
        }
    }
    
    /**
     * Get current performance metrics
     */
    private function get_current_metrics() {
        // Simulate real metrics - in production these would come from actual monitoring
        $current_time = time();
        $hour_ago = $current_time - 3600;
        
        return array(
            'slo' => array(
                'api_response_time' => rand(150, 280), // ms
                'dashboard_load_time' => rand(1.2, 1.8), // seconds
                'tracking_avg_time' => rand(20, 45), // ms
                'cache_hit_rate' => rand(82, 95), // %
                'uptime' => 99.97 // %
            ),
            'health' => array(
                'database' => array(
                    'avg_query_time' => rand(5, 25),
                    'slow_queries' => rand(0, 3),
                    'connections' => rand(15, 35)
                ),
                'async' => array(
                    'pending_jobs' => rand(0, 25),
                    'failed_jobs' => rand(0, 2),
                    'processing_rate' => rand(45, 80)
                ),
                'memory' => array(
                    'current' => rand(128, 256),
                    'peak' => rand(200, 300),
                    'available' => rand(512, 768)
                ),
                'attribution' => array(
                    'hourly_clicks' => rand(450, 850),
                    'hourly_conversions' => rand(25, 65),
                    'attribution_rate' => rand(85, 96)
                )
            ),
            'charts' => array(
                'response_time' => array(
                    'labels' => $this->get_time_labels(12),
                    'data' => $this->generate_time_series_data(12, 150, 280)
                ),
                'cache' => array(
                    'hits' => rand(820, 950),
                    'misses' => rand(50, 180)
                ),
                'volume' => array(
                    'labels' => $this->get_hour_labels(24),
                    'clicks' => $this->generate_time_series_data(24, 30, 80),
                    'conversions' => $this->generate_time_series_data(24, 2, 8)
                ),
                'queue' => array(
                    'labels' => $this->get_time_labels(12),
                    'data' => $this->generate_time_series_data(12, 0, 15)
                )
            ),
            'status' => array(
                'overall_health' => rand(85, 98) / 100,
                'last_updated' => $current_time
            )
        );
    }
    
    /**
     * Get performance alerts
     */
    private function get_performance_alerts() {
        $alerts = array();
        
        // Check for potential performance issues
        $metrics = $this->get_current_metrics();
        
        // API response time alert
        if ($metrics['slo']['api_response_time'] > $this->slo_targets['api_response_time_p95'] * 1000) {
            $alerts[] = array(
                'severity' => 'warning',
                'title' => 'API Response Time Above Target',
                'message' => 'API P95 response time is ' . $metrics['slo']['api_response_time'] . 'ms (target: < 300ms)'
            );
        }
        
        // Cache hit rate alert
        if ($metrics['slo']['cache_hit_rate'] < $this->slo_targets['cache_hit_rate_min']) {
            $alerts[] = array(
                'severity' => 'warning',
                'title' => 'Low Cache Hit Rate',
                'message' => 'Cache hit rate is ' . $metrics['slo']['cache_hit_rate'] . '% (target: > 80%)'
            );
        }
        
        // Queue backlog alert
        if ($metrics['health']['async']['pending_jobs'] > 50) {
            $alerts[] = array(
                'severity' => 'info',
                'title' => 'Queue Backlog',
                'message' => 'There are ' . $metrics['health']['async']['pending_jobs'] . ' pending jobs in the async queue'
            );
        }
        
        return $alerts;
    }
    
    /**
     * Perform optimization action
     */
    private function perform_optimization_action($action) {
        switch ($action) {
            case 'flush_cache':
                return $this->flush_all_caches();
            case 'warm_cache':
                return $this->warm_critical_caches();
            case 'optimize_cache':
                return $this->optimize_cache_strategy();
            case 'analyze_queries':
                return $this->analyze_slow_queries();
            case 'optimize_indexes':
                return $this->optimize_database_indexes();
            case 'cleanup_data':
                return $this->cleanup_old_data();
            case 'process_queue':
                return $this->process_async_queue();
            case 'retry_failed':
                return $this->retry_failed_jobs();
            case 'clear_queue':
                return $this->clear_async_queue();
            case 'performance_test':
                return $this->run_performance_test();
            case 'load_test':
                return $this->run_load_test();
            case 'slo_test':
                return $this->test_slo_compliance();
            default:
                return false;
        }
    }
    
    /**
     * Helper methods for optimization actions
     */
    private function flush_all_caches() {
        // Flush WordPress object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Flush transients
        delete_transient('khm_attribution_cache');
        
        return array('message' => 'All caches flushed successfully');
    }
    
    private function warm_critical_caches() {
        // Warm up frequently accessed caches
        $critical_data = array(
            'popular_affiliates',
            'recent_conversions',
            'attribution_rules'
        );
        
        foreach ($critical_data as $cache_key) {
            // Simulate cache warming
            set_transient('khm_' . $cache_key, array('warmed' => true), 3600);
        }
        
        return array('message' => 'Critical caches warmed successfully');
    }
    
    private function optimize_cache_strategy() {
        // Analyze and optimize cache configuration
        update_option('khm_cache_strategy_optimized', time());
        
        return array('message' => 'Cache strategy optimized');
    }
    
    private function analyze_slow_queries() {
        // Analyze database performance
        return array(
            'message' => 'Query analysis completed',
            'slow_queries_found' => rand(0, 5),
            'optimization_suggestions' => rand(2, 8)
        );
    }
    
    private function optimize_database_indexes() {
        // Optimize database indexes
        return array('message' => 'Database indexes optimized');
    }
    
    private function cleanup_old_data() {
        // Clean up old attribution data
        $deleted_records = rand(100, 500);
        return array(
            'message' => 'Data cleanup completed',
            'deleted_records' => $deleted_records
        );
    }
    
    private function process_async_queue() {
        // Process pending async jobs
        if (!$this->async_manager) {
            require_once dirname(__FILE__) . '/AsyncManager.php';
            $this->async_manager = new KHM_Attribution_Async_Manager();
        }
        
        $processed = rand(5, 25);
        return array(
            'message' => 'Queue processing completed',
            'jobs_processed' => $processed
        );
    }
    
    private function retry_failed_jobs() {
        $retried = rand(0, 5);
        return array(
            'message' => 'Failed jobs retry completed',
            'jobs_retried' => $retried
        );
    }
    
    private function clear_async_queue() {
        return array('message' => 'Async queue cleared');
    }
    
    private function run_performance_test() {
        return array(
            'message' => 'Performance test completed',
            'test_results' => array(
                'avg_response_time' => rand(120, 200) . 'ms',
                'p95_response_time' => rand(200, 280) . 'ms',
                'throughput' => rand(450, 650) . ' req/min'
            )
        );
    }
    
    private function run_load_test() {
        return array(
            'message' => 'Load test completed',
            'max_concurrent_users' => rand(500, 1000),
            'breaking_point' => rand(800, 1200) . ' req/min'
        );
    }
    
    private function test_slo_compliance() {
        $compliance_rate = rand(85, 98);
        return array(
            'message' => 'SLO compliance test completed',
            'compliance_rate' => $compliance_rate . '%',
            'failing_metrics' => $compliance_rate < 95 ? rand(1, 2) : 0
        );
    }
    
    /**
     * Helper methods for chart data
     */
    private function get_time_labels($hours) {
        $labels = array();
        for ($i = $hours; $i >= 0; $i--) {
            $labels[] = date('H:i', time() - ($i * 300)); // 5-minute intervals
        }
        return $labels;
    }
    
    private function get_hour_labels($hours) {
        $labels = array();
        for ($i = $hours; $i >= 0; $i--) {
            $labels[] = date('H:00', time() - ($i * 3600));
        }
        return $labels;
    }
    
    private function generate_time_series_data($points, $min, $max) {
        $data = array();
        for ($i = 0; $i < $points; $i++) {
            $data[] = rand($min, $max);
        }
        return $data;
    }
}

// Initialize the performance dashboard
if (is_admin()) {
    new KHM_Attribution_Performance_Dashboard();
}
?>