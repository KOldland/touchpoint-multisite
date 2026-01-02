<?php

namespace KHM_SEO\Analytics;

/**
 * Data Analysis Dashboard
 * 
 * Interactive dashboard for displaying advanced SEO analytics,
 * trends, correlations, and predictive insights
 * 
 * Features:
 * - Real-time trend visualization
 * - Interactive correlation matrices
 * - Anomaly detection alerts
 * - Predictive forecasting charts
 * - Executive summary reports
 * - Customizable analytics widgets
 * 
 * @package KHM_SEO\Analytics
 * @since 1.0.0
 */
class DataAnalysisDashboard {

    /**
     * @var DataAnalysisEngine
     */
    private $analysis_engine;

    /**
     * Dashboard configuration
     */
    private $dashboard_config = [
        'refresh_interval' => 300, // 5 minutes
        'chart_themes' => ['light', 'dark', 'analytics'],
        'default_period' => 30,
        'max_forecast_period' => 90,
        'chart_types' => ['line', 'area', 'bar', 'scatter', 'heatmap']
    ];

    /**
     * Widget configurations
     */
    private $widget_configs = [
        'executive_summary' => [
            'title' => 'Executive Summary',
            'size' => 'large',
            'refresh' => 'hourly'
        ],
        'trend_analysis' => [
            'title' => 'Trend Analysis',
            'size' => 'medium',
            'refresh' => 'real-time'
        ],
        'anomaly_detection' => [
            'title' => 'Anomaly Detection',
            'size' => 'medium', 
            'refresh' => 'hourly'
        ],
        'correlation_matrix' => [
            'title' => 'Metric Correlations',
            'size' => 'large',
            'refresh' => 'daily'
        ],
        'forecast_analysis' => [
            'title' => 'Predictive Forecasts',
            'size' => 'large',
            'refresh' => 'daily'
        ]
    ];

    /**
     * Initialize dashboard
     */
    public function __construct() {
        $this->analysis_engine = new DataAnalysisEngine();
        add_action('admin_menu', [$this, 'add_dashboard_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        add_action('wp_ajax_dashboard_get_widget_data', [$this, 'ajax_get_widget_data']);
        add_action('wp_ajax_dashboard_update_settings', [$this, 'ajax_update_settings']);
    }

    /**
     * Add dashboard menu item
     */
    public function add_dashboard_menu() {
        add_submenu_page(
            'seo-intelligence',
            'Analytics Dashboard',
            'Analytics Dashboard',
            'manage_options',
            'seo-analytics-dashboard',
            [$this, 'render_dashboard']
        );
    }

    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        if (strpos($hook, 'seo-analytics-dashboard') === false) {
            return;
        }

        // Chart.js for data visualization
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1'
        );

        // Dashboard JavaScript
        wp_enqueue_script(
            'analytics-dashboard',
            plugins_url('assets/js/analytics-dashboard.js', __FILE__),
            ['jquery', 'chartjs'],
            '1.0.0',
            true
        );

        // Dashboard CSS
        wp_enqueue_style(
            'analytics-dashboard',
            plugins_url('assets/css/analytics-dashboard.css', __FILE__),
            [],
            '1.0.0'
        );

        // Localize script with AJAX URL and nonces
        wp_localize_script('analytics-dashboard', 'analyticsAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('analytics_dashboard'),
            'refresh_interval' => $this->dashboard_config['refresh_interval'],
            'chart_config' => $this->get_chart_configurations()
        ]);
    }

    /**
     * Render main dashboard
     */
    public function render_dashboard() {
        ?>
        <div class="wrap analytics-dashboard">
            <h1>SEO Analytics Dashboard</h1>
            
            <!-- Dashboard Controls -->
            <div class="dashboard-controls">
                <div class="control-group">
                    <label for="dashboard-period">Analysis Period:</label>
                    <select id="dashboard-period" class="period-selector">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="180">Last 6 months</option>
                        <option value="365">Last year</option>
                    </select>
                </div>
                
                <div class="control-group">
                    <label for="dashboard-theme">Theme:</label>
                    <select id="dashboard-theme" class="theme-selector">
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                        <option value="analytics" selected>Analytics</option>
                    </select>
                </div>
                
                <div class="control-group">
                    <button id="refresh-dashboard" class="button button-primary">
                        <span class="dashicons dashicons-update"></span> Refresh
                    </button>
                </div>
                
                <div class="control-group">
                    <button id="export-report" class="button button-secondary">
                        <span class="dashicons dashicons-download"></span> Export Report
                    </button>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Executive Summary Widget -->
                <div class="dashboard-widget widget-executive-summary" data-widget="executive_summary">
                    <div class="widget-header">
                        <h3>Executive Summary</h3>
                        <div class="widget-controls">
                            <span class="widget-status loading" title="Loading..."></span>
                        </div>
                    </div>
                    <div class="widget-content">
                        <div class="summary-metrics">
                            <div class="metric-card">
                                <h4>Overall Performance</h4>
                                <div class="metric-value" id="overall-score">-</div>
                                <div class="metric-change" id="overall-change">-</div>
                            </div>
                            <div class="metric-card">
                                <h4>Organic Traffic</h4>
                                <div class="metric-value" id="traffic-value">-</div>
                                <div class="metric-change" id="traffic-change">-</div>
                            </div>
                            <div class="metric-card">
                                <h4>Search Rankings</h4>
                                <div class="metric-value" id="rankings-value">-</div>
                                <div class="metric-change" id="rankings-change">-</div>
                            </div>
                            <div class="metric-card">
                                <h4>Technical Health</h4>
                                <div class="metric-value" id="technical-value">-</div>
                                <div class="metric-change" id="technical-change">-</div>
                            </div>
                        </div>
                        <div class="summary-insights" id="executive-insights"></div>
                    </div>
                </div>

                <!-- Trend Analysis Widget -->
                <div class="dashboard-widget widget-trends" data-widget="trend_analysis">
                    <div class="widget-header">
                        <h3>Trend Analysis</h3>
                        <div class="widget-controls">
                            <select class="metric-selector" id="trend-metric">
                                <option value="gsc_clicks">Organic Clicks</option>
                                <option value="gsc_impressions">Search Impressions</option>
                                <option value="ga4_sessions">GA4 Sessions</option>
                                <option value="cwv_performance">Core Web Vitals</option>
                            </select>
                        </div>
                    </div>
                    <div class="widget-content">
                        <canvas id="trends-chart"></canvas>
                        <div class="trend-insights" id="trend-insights"></div>
                    </div>
                </div>

                <!-- Anomaly Detection Widget -->
                <div class="dashboard-widget widget-anomalies" data-widget="anomaly_detection">
                    <div class="widget-header">
                        <h3>Anomaly Detection</h3>
                        <div class="widget-controls">
                            <button class="refresh-widget" data-widget="anomaly_detection">
                                <span class="dashicons dashicons-update"></span>
                            </button>
                        </div>
                    </div>
                    <div class="widget-content">
                        <div class="anomaly-summary" id="anomaly-summary"></div>
                        <div class="anomaly-list" id="anomaly-list"></div>
                    </div>
                </div>

                <!-- Correlation Matrix Widget -->
                <div class="dashboard-widget widget-correlations" data-widget="correlation_matrix">
                    <div class="widget-header">
                        <h3>Metric Correlations</h3>
                        <div class="widget-controls">
                            <select class="correlation-type" id="correlation-type">
                                <option value="pearson">Pearson</option>
                                <option value="spearman">Spearman</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                    </div>
                    <div class="widget-content">
                        <canvas id="correlation-chart"></canvas>
                        <div class="correlation-insights" id="correlation-insights"></div>
                    </div>
                </div>

                <!-- Forecast Analysis Widget -->
                <div class="dashboard-widget widget-forecast" data-widget="forecast_analysis">
                    <div class="widget-header">
                        <h3>Predictive Forecasts</h3>
                        <div class="widget-controls">
                            <select class="forecast-metric" id="forecast-metric">
                                <option value="gsc_clicks">Organic Clicks</option>
                                <option value="ga4_sessions">Sessions</option>
                                <option value="cwv_performance">Performance Score</option>
                            </select>
                            <select class="forecast-period" id="forecast-period">
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                    </div>
                    <div class="widget-content">
                        <canvas id="forecast-chart"></canvas>
                        <div class="forecast-insights" id="forecast-insights"></div>
                    </div>
                </div>

                <!-- Recommendations Widget -->
                <div class="dashboard-widget widget-recommendations" data-widget="recommendations">
                    <div class="widget-header">
                        <h3>Actionable Recommendations</h3>
                        <div class="widget-controls">
                            <button class="refresh-widget" data-widget="recommendations">
                                <span class="dashicons dashicons-update"></span>
                            </button>
                        </div>
                    </div>
                    <div class="widget-content">
                        <div class="recommendations-list" id="recommendations-list"></div>
                    </div>
                </div>
            </div>

            <!-- Alert Modal -->
            <div id="alert-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Analytics Alert</h3>
                        <span class="close-modal">&times;</span>
                    </div>
                    <div class="modal-body" id="alert-content"></div>
                    <div class="modal-footer">
                        <button class="button button-primary close-modal">OK</button>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize Analytics Dashboard
            window.AnalyticsDashboard = new SEOAnalyticsDashboard();
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for widget data
     */
    public function ajax_get_widget_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'analytics_dashboard')) {
            wp_die('Security check failed');
        }

        $widget_type = sanitize_text_field($_POST['widget'] ?? '');
        $period = intval($_POST['period'] ?? 30);
        $metric = sanitize_text_field($_POST['metric'] ?? '');

        try {
            switch ($widget_type) {
                case 'executive_summary':
                    $data = $this->get_executive_summary_data($period);
                    break;
                    
                case 'trend_analysis':
                    $data = $this->get_trend_analysis_data($metric, $period);
                    break;
                    
                case 'anomaly_detection':
                    $data = $this->get_anomaly_detection_data($period);
                    break;
                    
                case 'correlation_matrix':
                    $data = $this->get_correlation_matrix_data($period);
                    break;
                    
                case 'forecast_analysis':
                    $forecast_period = intval($_POST['forecast_period'] ?? 30);
                    $data = $this->get_forecast_analysis_data($metric, $forecast_period);
                    break;
                    
                case 'recommendations':
                    $data = $this->get_recommendations_data();
                    break;
                    
                default:
                    throw new \Exception('Invalid widget type');
            }

            wp_send_json_success($data);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Get executive summary data
     */
    private function get_executive_summary_data($period) {
        $insights = $this->analysis_engine->generate_insights();
        $performance = $insights['performance_score'] ?? [];
        $summary = $insights['executive_summary'] ?? [];

        return [
            'overall_score' => $performance['overall_score'] ?? 0,
            'overall_grade' => $performance['grade'] ?? 'N/A',
            'key_metrics' => $summary['key_metrics'] ?? [],
            'status' => $summary['status'] ?? 'unknown',
            'priority_areas' => $summary['priority_areas'] ?? [],
            'achievements' => $summary['achievements'] ?? [],
            'insights' => $insights['insights'] ?? [],
            'last_updated' => current_time('mysql')
        ];
    }

    /**
     * Get trend analysis data for charts
     */
    private function get_trend_analysis_data($metric, $period) {
        $trends = $this->analysis_engine->analyze_trends($period);
        
        // Extract specific metric data
        $metric_data = $this->extract_metric_from_trends($trends, $metric);
        
        return [
            'chart_data' => $this->format_chart_data($metric_data),
            'trend_summary' => $this->get_trend_summary($metric_data),
            'insights' => $this->get_trend_insights($metric_data),
            'period' => $period,
            'metric' => $metric
        ];
    }

    /**
     * Get anomaly detection data
     */
    private function get_anomaly_detection_data($period) {
        $anomalies = $this->analysis_engine->detect_anomalies($period);
        
        return [
            'total_anomalies' => $this->count_total_anomalies($anomalies),
            'severity_breakdown' => $this->get_severity_breakdown($anomalies),
            'recent_anomalies' => $this->get_recent_anomalies($anomalies, 5),
            'anomaly_trends' => $this->get_anomaly_trends($anomalies),
            'recommendations' => $anomalies['recommendations'] ?? []
        ];
    }

    /**
     * Get correlation matrix data
     */
    private function get_correlation_matrix_data($period) {
        $correlations = $this->analysis_engine->analyze_correlations($period);
        
        return [
            'matrix' => $correlations['correlation_matrix'] ?? [],
            'significant_correlations' => $correlations['significant_correlations'] ?? [],
            'network_analysis' => $correlations['network_analysis'] ?? [],
            'insights' => $correlations['insights'] ?? [],
            'chart_data' => $this->format_correlation_chart_data($correlations)
        ];
    }

    /**
     * Get forecast analysis data
     */
    private function get_forecast_analysis_data($metric, $forecast_period) {
        $forecasts = $this->analysis_engine->generate_forecasts($metric, $forecast_period);
        
        return [
            'forecast_data' => $forecasts['forecasts'][$metric] ?? [],
            'confidence_intervals' => $forecasts['confidence_intervals'][$metric] ?? [],
            'scenarios' => $forecasts['scenarios'][$metric] ?? [],
            'chart_data' => $this->format_forecast_chart_data($forecasts, $metric),
            'accuracy' => $forecasts['validation']['validation_score'] ?? 0
        ];
    }

    /**
     * Get recommendations data
     */
    private function get_recommendations_data() {
        $insights = $this->analysis_engine->generate_insights();
        $recommendations = $insights['recommendations'] ?? [];
        
        return [
            'recommendations' => $recommendations,
            'priority_recommendations' => $this->get_priority_recommendations($recommendations),
            'quick_wins' => $this->get_quick_wins($recommendations),
            'long_term_strategies' => $this->get_long_term_strategies($recommendations)
        ];
    }

    /**
     * Chart configuration helpers
     */
    private function get_chart_configurations() {
        return [
            'trends' => [
                'type' => 'line',
                'responsive' => true,
                'plugins' => [
                    'title' => ['display' => true],
                    'legend' => ['display' => true]
                ],
                'scales' => [
                    'x' => ['type' => 'time'],
                    'y' => ['beginAtZero' => false]
                ]
            ],
            'correlation' => [
                'type' => 'scatter',
                'responsive' => true,
                'plugins' => [
                    'title' => ['display' => true],
                    'legend' => ['display' => false]
                ]
            ],
            'forecast' => [
                'type' => 'line',
                'responsive' => true,
                'plugins' => [
                    'title' => ['display' => true],
                    'legend' => ['display' => true]
                ]
            ]
        ];
    }

    /**
     * Data formatting helpers
     */
    private function extract_metric_from_trends($trends, $metric) {
        // Extract specific metric data from trends analysis
        $parts = explode('_', $metric);
        $source = $parts[0] . '_' . ($parts[1] ?? 'stats');
        $metric_name = $parts[1] ?? $parts[0];
        
        return $trends[$source][$metric_name] ?? [];
    }

    private function format_chart_data($metric_data) {
        // Format data for Chart.js
        return [
            'labels' => [], // Date labels
            'datasets' => [
                [
                    'label' => 'Actual',
                    'data' => [],
                    'borderColor' => 'rgb(54, 162, 235)',
                    'tension' => 0.1
                ],
                [
                    'label' => '7-day MA',
                    'data' => [],
                    'borderColor' => 'rgb(255, 99, 132)',
                    'tension' => 0.1
                ]
            ]
        ];
    }

    private function format_correlation_chart_data($correlations) {
        return [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Correlation Strength',
                    'data' => [],
                    'backgroundColor' => 'rgba(54, 162, 235, 0.5)'
                ]
            ]
        ];
    }

    private function format_forecast_chart_data($forecasts, $metric) {
        return [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Historical',
                    'data' => [],
                    'borderColor' => 'rgb(54, 162, 235)'
                ],
                [
                    'label' => 'Forecast',
                    'data' => [],
                    'borderColor' => 'rgb(255, 99, 132)',
                    'borderDash' => [5, 5]
                ]
            ]
        ];
    }

    /**
     * Analysis helpers
     */
    private function get_trend_summary($metric_data) {
        return [
            'direction' => $metric_data['trend_direction'] ?? 'unknown',
            'strength' => $metric_data['trend_strength'] ?? 0,
            'change_percent' => $metric_data['period_change_percent'] ?? 0
        ];
    }

    private function get_trend_insights($metric_data) {
        return [
            'key_observations' => [],
            'recommendations' => []
        ];
    }

    private function count_total_anomalies($anomalies) {
        $count = 0;
        foreach ($anomalies['anomalies'] ?? [] as $metric_anomalies) {
            $count += count($metric_anomalies);
        }
        return $count;
    }

    private function get_severity_breakdown($anomalies) {
        $breakdown = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($anomalies['anomalies'] ?? [] as $metric_anomalies) {
            foreach ($metric_anomalies as $anomaly) {
                $severity = $anomaly['severity'] ?? 'low';
                $breakdown[$severity]++;
            }
        }
        return $breakdown;
    }

    private function get_recent_anomalies($anomalies, $limit) {
        $recent = [];
        foreach ($anomalies['anomalies'] ?? [] as $metric => $metric_anomalies) {
            foreach ($metric_anomalies as $anomaly) {
                $anomaly['metric'] = $metric;
                $recent[] = $anomaly;
            }
        }
        
        // Sort by date and limit
        usort($recent, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return array_slice($recent, 0, $limit);
    }

    private function get_anomaly_trends($anomalies) {
        return ['trend' => 'stable', 'frequency' => 'normal'];
    }

    private function get_priority_recommendations($recommendations) {
        $priority = array_filter($recommendations, function($rec) {
            return ($rec['priority'] ?? '') === 'high';
        });
        return array_slice($priority, 0, 3);
    }

    private function get_quick_wins($recommendations) {
        $quick_wins = array_filter($recommendations, function($rec) {
            return ($rec['effort'] ?? '') === 'low' && ($rec['impact'] ?? '') === 'high';
        });
        return array_slice($quick_wins, 0, 3);
    }

    private function get_long_term_strategies($recommendations) {
        $long_term = array_filter($recommendations, function($rec) {
            return ($rec['effort'] ?? '') === 'high' && ($rec['impact'] ?? '') === 'high';
        });
        return array_slice($long_term, 0, 3);
    }

    /**
     * Settings update handler
     */
    public function ajax_update_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'analytics_dashboard')) {
            wp_die('Security check failed');
        }

        $settings = [
            'refresh_interval' => intval($_POST['refresh_interval'] ?? 300),
            'default_theme' => sanitize_text_field($_POST['theme'] ?? 'analytics'),
            'default_period' => intval($_POST['period'] ?? 30)
        ];

        update_option('analytics_dashboard_settings', $settings);
        wp_send_json_success(['message' => 'Settings updated successfully']);
    }
}