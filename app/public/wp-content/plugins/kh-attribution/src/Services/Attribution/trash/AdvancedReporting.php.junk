<?php
/**
 * KHM Attribution Advanced Reporting
 * 
 * Comprehensive reporting system with customizable reports, exports, and visualizations
 * using Phase 2 OOP architectural patterns.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Advanced_Reporting {
    
    private $performance_manager;
    private $database_manager;
    private $query_builder;
    private $business_intelligence;
    private $reporting_config = array();
    private $report_templates = array();
    private $export_formats = array();
    
    /**
     * Constructor - Initialize advanced reporting components
     */
    public function __construct() {
        $this->init_reporting_components();
        $this->setup_reporting_config();
        $this->load_report_templates();
        $this->register_reporting_hooks();
    }
    
    /**
     * Initialize reporting components
     */
    private function init_reporting_components() {
        // Load core components
        if (file_exists(dirname(__FILE__) . '/PerformanceManager.php')) {
            require_once dirname(__FILE__) . '/PerformanceManager.php';
            $this->performance_manager = new KHM_Attribution_Performance_Manager();
        }
        
        if (file_exists(dirname(__FILE__) . '/DatabaseManager.php')) {
            require_once dirname(__FILE__) . '/DatabaseManager.php';
            $this->database_manager = new KHM_Attribution_Database_Manager();
        }
        
        if (file_exists(dirname(__FILE__) . '/QueryBuilder.php')) {
            require_once dirname(__FILE__) . '/QueryBuilder.php';
            $this->query_builder = new KHM_Attribution_Query_Builder();
        }
        
        if (file_exists(dirname(__FILE__) . '/BusinessIntelligenceEngine.php')) {
            require_once dirname(__FILE__) . '/BusinessIntelligenceEngine.php';
            $this->business_intelligence = new KHM_Attribution_Business_Intelligence_Engine();
        }
    }
    
    /**
     * Setup reporting configuration
     */
    private function setup_reporting_config() {
        $this->reporting_config = array(
            'max_report_size' => 10000, // Max rows
            'cache_duration' => 1800, // 30 minutes
            'enable_scheduled_reports' => true,
            'default_timezone' => 'UTC',
            'report_retention_days' => 90,
            'export_formats' => array('csv', 'xlsx', 'pdf', 'json'),
            'visualization_types' => array('line', 'bar', 'pie', 'heatmap', 'funnel')
        );
        
        // Allow configuration overrides
        $this->reporting_config = apply_filters('khm_reporting_config', $this->reporting_config);
    }
    
    /**
     * Load report templates
     */
    private function load_report_templates() {
        $this->report_templates = array(
            'attribution_overview' => array(
                'name' => 'Attribution Overview',
                'description' => 'Comprehensive attribution performance overview',
                'sections' => array('summary', 'channels', 'trends', 'insights'),
                'default_period' => '30_days'
            ),
            'channel_performance' => array(
                'name' => 'Channel Performance Report',
                'description' => 'Detailed performance analysis by marketing channel',
                'sections' => array('channel_metrics', 'roi_analysis', 'attribution_share'),
                'default_period' => '30_days'
            ),
            'customer_journey' => array(
                'name' => 'Customer Journey Analysis',
                'description' => 'Customer touchpoint and journey insights',
                'sections' => array('journey_metrics', 'touchpoint_analysis', 'conversion_paths'),
                'default_period' => '90_days'
            ),
            'roi_optimization' => array(
                'name' => 'ROI Optimization Report',
                'description' => 'Budget allocation and ROI optimization recommendations',
                'sections' => array('roi_metrics', 'budget_allocation', 'optimization_opportunities'),
                'default_period' => '30_days'
            ),
            'executive_summary' => array(
                'name' => 'Executive Summary',
                'description' => 'High-level KPI summary for executive stakeholders',
                'sections' => array('kpi_summary', 'trends', 'recommendations'),
                'default_period' => '30_days'
            )
        );
    }
    
    /**
     * Register reporting hooks
     */
    private function register_reporting_hooks() {
        add_action('khm_generate_scheduled_reports', array($this, 'generate_scheduled_reports'));
        add_action('admin_menu', array($this, 'add_reporting_menu'));
        
        // AJAX hooks
        add_action('wp_ajax_khm_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_khm_export_report', array($this, 'ajax_export_report'));
        add_action('wp_ajax_khm_schedule_report', array($this, 'ajax_schedule_report'));
        
        // Export hooks
        add_action('wp_ajax_khm_download_report', array($this, 'handle_report_download'));
    }
    
    /**
     * Generate comprehensive report
     */
    public function generate_report($template_key, $options = array()) {
        if (!isset($this->report_templates[$template_key])) {
            return false;
        }
        
        $template = $this->report_templates[$template_key];
        
        // Set default options
        $default_options = array(
            'date_range' => $this->get_default_date_range($template['default_period']),
            'filters' => array(),
            'format' => 'html',
            'include_visualizations' => true,
            'include_raw_data' => false
        );
        
        $options = array_merge($default_options, $options);
        
        // Generate report sections
        $report_data = array(
            'template' => $template_key,
            'title' => $template['name'],
            'description' => $template['description'],
            'generated_at' => current_time('mysql'),
            'date_range' => $options['date_range'],
            'sections' => array()
        );
        
        foreach ($template['sections'] as $section_key) {
            $section_data = $this->generate_report_section($section_key, $options);
            $report_data['sections'][$section_key] = $section_data;
        }
        
        // Add metadata
        $report_data['metadata'] = $this->generate_report_metadata($report_data, $options);
        
        // Store report for caching
        $this->store_report($report_data);
        
        return $report_data;
    }
    
    /**
     * Generate report section
     */
    private function generate_report_section($section_key, $options) {
        switch ($section_key) {
            case 'summary':
                return $this->generate_summary_section($options);
                
            case 'channels':
                return $this->generate_channels_section($options);
                
            case 'trends':
                return $this->generate_trends_section($options);
                
            case 'insights':
                return $this->generate_insights_section($options);
                
            case 'channel_metrics':
                return $this->generate_channel_metrics_section($options);
                
            case 'roi_analysis':
                return $this->generate_roi_analysis_section($options);
                
            case 'attribution_share':
                return $this->generate_attribution_share_section($options);
                
            case 'journey_metrics':
                return $this->generate_journey_metrics_section($options);
                
            case 'touchpoint_analysis':
                return $this->generate_touchpoint_analysis_section($options);
                
            case 'conversion_paths':
                return $this->generate_conversion_paths_section($options);
                
            case 'roi_metrics':
                return $this->generate_roi_metrics_section($options);
                
            case 'budget_allocation':
                return $this->generate_budget_allocation_section($options);
                
            case 'optimization_opportunities':
                return $this->generate_optimization_opportunities_section($options);
                
            case 'kpi_summary':
                return $this->generate_kpi_summary_section($options);
                
            case 'recommendations':
                return $this->generate_recommendations_section($options);
                
            default:
                return array('error' => 'Unknown section: ' . $section_key);
        }
    }
    
    /**
     * Generate summary section
     */
    private function generate_summary_section($options) {
        $date_range = $options['date_range'];
        
        // Get key metrics
        $metrics = array(
            'total_revenue' => $this->calculate_total_revenue($date_range),
            'total_conversions' => $this->calculate_total_conversions($date_range),
            'conversion_rate' => $this->calculate_conversion_rate($date_range),
            'average_order_value' => $this->calculate_average_order_value($date_range),
            'attribution_accuracy' => $this->calculate_attribution_accuracy($date_range)
        );
        
        // Calculate period-over-period changes
        $previous_period = $this->get_previous_period($date_range);
        $previous_metrics = array(
            'total_revenue' => $this->calculate_total_revenue($previous_period),
            'total_conversions' => $this->calculate_total_conversions($previous_period),
            'conversion_rate' => $this->calculate_conversion_rate($previous_period),
            'average_order_value' => $this->calculate_average_order_value($previous_period),
            'attribution_accuracy' => $this->calculate_attribution_accuracy($previous_period)
        );
        
        // Calculate changes
        $changes = array();
        foreach ($metrics as $key => $value) {
            $previous_value = $previous_metrics[$key] ?? 0;
            $change = $previous_value > 0 ? (($value - $previous_value) / $previous_value) * 100 : 0;
            $changes[$key] = $change;
        }
        
        return array(
            'title' => 'Performance Summary',
            'metrics' => $metrics,
            'changes' => $changes,
            'formatted_metrics' => $this->format_summary_metrics($metrics),
            'visualization' => $this->generate_summary_visualization($metrics, $changes)
        );
    }
    
    /**
     * Generate channels section
     */
    private function generate_channels_section($options) {
        $date_range = $options['date_range'];
        
        // Get channel performance data
        $channel_data = $this->get_channel_performance_data($date_range);
        
        // Calculate channel metrics
        $channel_metrics = array();
        foreach ($channel_data as $channel) {
            $channel_metrics[] = array(
                'channel' => $channel['utm_medium'],
                'revenue' => $channel['revenue'],
                'conversions' => $channel['conversions'],
                'clicks' => $channel['clicks'],
                'conversion_rate' => $channel['clicks'] > 0 ? ($channel['conversions'] / $channel['clicks']) * 100 : 0,
                'revenue_per_click' => $channel['clicks'] > 0 ? $channel['revenue'] / $channel['clicks'] : 0,
                'attribution_share' => 0 // Will be calculated below
            );
        }
        
        // Calculate attribution share
        $total_revenue = array_sum(array_column($channel_metrics, 'revenue'));
        foreach ($channel_metrics as &$metric) {
            $metric['attribution_share'] = $total_revenue > 0 ? ($metric['revenue'] / $total_revenue) * 100 : 0;
        }
        
        return array(
            'title' => 'Channel Performance',
            'channel_metrics' => $channel_metrics,
            'top_channels' => $this->get_top_channels($channel_metrics, 5),
            'visualization' => $this->generate_channel_visualization($channel_metrics)
        );
    }
    
    /**
     * Generate trends section
     */
    private function generate_trends_section($options) {
        $date_range = $options['date_range'];
        
        // Get daily trends
        $daily_trends = $this->get_daily_trends($date_range);
        
        // Analyze trends
        $trend_analysis = $this->analyze_trend_patterns($daily_trends);
        
        return array(
            'title' => 'Performance Trends',
            'daily_trends' => $daily_trends,
            'trend_analysis' => $trend_analysis,
            'visualization' => $this->generate_trends_visualization($daily_trends)
        );
    }
    
    /**
     * Generate insights section
     */
    private function generate_insights_section($options) {
        if (!isset($this->business_intelligence)) {
            return array('title' => 'Insights', 'insights' => array(), 'error' => 'Business Intelligence Engine not available');
        }
        
        $insights = $this->business_intelligence->generate_business_insights($options['date_range']);
        
        return array(
            'title' => 'Business Insights',
            'automated_insights' => $insights['automated_insights'] ?? array(),
            'recommendations' => $insights['recommendations'] ?? array(),
            'alerts' => $insights['alerts'] ?? array()
        );
    }
    
    /**
     * Export report in specified format
     */
    public function export_report($report_data, $format = 'csv') {
        if (!in_array($format, $this->reporting_config['export_formats'])) {
            return false;
        }
        
        switch ($format) {
            case 'csv':
                return $this->export_to_csv($report_data);
                
            case 'xlsx':
                return $this->export_to_xlsx($report_data);
                
            case 'pdf':
                return $this->export_to_pdf($report_data);
                
            case 'json':
                return $this->export_to_json($report_data);
                
            default:
                return false;
        }
    }
    
    /**
     * Export to CSV
     */
    private function export_to_csv($report_data) {
        $filename = 'attribution_report_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        $file = fopen($filepath, 'w');
        
        // Write header
        fputcsv($file, array('Report: ' . $report_data['title']));
        fputcsv($file, array('Generated: ' . $report_data['generated_at']));
        fputcsv($file, array('Date Range: ' . $report_data['date_range']['start'] . ' to ' . $report_data['date_range']['end']));
        fputcsv($file, array(''));
        
        // Export each section
        foreach ($report_data['sections'] as $section_key => $section_data) {
            fputcsv($file, array(strtoupper(str_replace('_', ' ', $section_key))));
            
            // Export section data based on structure
            if (isset($section_data['metrics'])) {
                foreach ($section_data['metrics'] as $metric_key => $metric_value) {
                    fputcsv($file, array($metric_key, $metric_value));
                }
            }
            
            if (isset($section_data['channel_metrics'])) {
                // Write channel headers
                fputcsv($file, array('Channel', 'Revenue', 'Conversions', 'Clicks', 'Conversion Rate', 'Revenue per Click', 'Attribution Share'));
                
                foreach ($section_data['channel_metrics'] as $channel) {
                    fputcsv($file, array(
                        $channel['channel'],
                        $channel['revenue'],
                        $channel['conversions'],
                        $channel['clicks'],
                        number_format($channel['conversion_rate'], 2) . '%',
                        '$' . number_format($channel['revenue_per_click'], 2),
                        number_format($channel['attribution_share'], 2) . '%'
                    ));
                }
            }
            
            fputcsv($file, array(''));
        }
        
        fclose($file);
        
        return array(
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => wp_upload_dir()['url'] . '/' . $filename
        );
    }
    
    /**
     * Export to JSON
     */
    private function export_to_json($report_data) {
        $filename = 'attribution_report_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        $json_data = json_encode($report_data, JSON_PRETTY_PRINT);
        file_put_contents($filepath, $json_data);
        
        return array(
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => wp_upload_dir()['url'] . '/' . $filename
        );
    }
    
    /**
     * Schedule report generation
     */
    public function schedule_report($template_key, $schedule_options) {
        $schedule_data = array(
            'template' => $template_key,
            'frequency' => $schedule_options['frequency'] ?? 'weekly',
            'recipients' => $schedule_options['recipients'] ?? array(),
            'format' => $schedule_options['format'] ?? 'pdf',
            'options' => $schedule_options['report_options'] ?? array(),
            'next_run' => $this->calculate_next_run($schedule_options['frequency']),
            'created_at' => current_time('mysql'),
            'status' => 'active'
        );
        
        // Store scheduled report
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_scheduled_reports';
        $this->maybe_create_scheduled_reports_table();
        
        $wpdb->insert($table_name, array(
            'template_key' => $template_key,
            'schedule_data' => json_encode($schedule_data),
            'frequency' => $schedule_data['frequency'],
            'next_run' => $schedule_data['next_run'],
            'status' => $schedule_data['status'],
            'created_at' => $schedule_data['created_at']
        ));
        
        return $wpdb->insert_id;
    }
    
    /**
     * Generate scheduled reports
     */
    public function generate_scheduled_reports() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_scheduled_reports';
        
        // Get reports due for generation
        $due_reports = $wpdb->get_results(
            "SELECT * FROM {$table_name} 
             WHERE status = 'active' 
             AND next_run <= NOW()",
            ARRAY_A
        );
        
        foreach ($due_reports as $scheduled_report) {
            $schedule_data = json_decode($scheduled_report['schedule_data'], true);
            
            // Generate report
            $report = $this->generate_report($schedule_data['template'], $schedule_data['options']);
            
            if ($report) {
                // Export report
                $export_result = $this->export_report($report, $schedule_data['format']);
                
                if ($export_result['success']) {
                    // Send to recipients
                    $this->send_scheduled_report($export_result, $schedule_data['recipients'], $report);
                }
            }
            
            // Update next run time
            $next_run = $this->calculate_next_run($schedule_data['frequency']);
            $wpdb->update(
                $table_name,
                array('next_run' => $next_run),
                array('id' => $scheduled_report['id'])
            );
        }
    }
    
    /**
     * Calculate next run time
     */
    private function calculate_next_run($frequency) {
        switch ($frequency) {
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('+1 day'));
            case 'weekly':
                return date('Y-m-d H:i:s', strtotime('+1 week'));
            case 'monthly':
                return date('Y-m-d H:i:s', strtotime('+1 month'));
            case 'quarterly':
                return date('Y-m-d H:i:s', strtotime('+3 months'));
            default:
                return date('Y-m-d H:i:s', strtotime('+1 week'));
        }
    }
    
    /**
     * Send scheduled report
     */
    private function send_scheduled_report($export_result, $recipients, $report_data) {
        $subject = 'Scheduled Attribution Report: ' . $report_data['title'];
        
        $message = "Your scheduled attribution report is ready.\n\n";
        $message .= "Report: " . $report_data['title'] . "\n";
        $message .= "Generated: " . $report_data['generated_at'] . "\n";
        $message .= "Date Range: " . $report_data['date_range']['start'] . " to " . $report_data['date_range']['end'] . "\n\n";
        $message .= "Download: " . $export_result['url'] . "\n";
        
        foreach ($recipients as $email) {
            wp_mail($email, $subject, $message);
        }
    }
    
    /**
     * Utility methods for calculations
     */
    private function calculate_total_revenue($date_range) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT SUM(value) FROM {$table_name} 
                WHERE created_at BETWEEN %s AND %s 
                AND status = 'attributed'";
        
        return floatval($wpdb->get_var($wpdb->prepare($sql, $date_range['start'], $date_range['end'])));
    }
    
    private function calculate_total_conversions($date_range) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT COUNT(*) FROM {$table_name} 
                WHERE created_at BETWEEN %s AND %s 
                AND status = 'attributed'";
        
        return intval($wpdb->get_var($wpdb->prepare($sql, $date_range['start'], $date_range['end'])));
    }
    
    private function calculate_conversion_rate($date_range) {
        $conversions = $this->calculate_total_conversions($date_range);
        
        global $wpdb;
        $events_table = $wpdb->prefix . 'khm_attribution_events';
        
        $sql = "SELECT COUNT(*) FROM {$events_table} 
                WHERE created_at BETWEEN %s AND %s";
        
        $clicks = intval($wpdb->get_var($wpdb->prepare($sql, $date_range['start'], $date_range['end'])));
        
        return $clicks > 0 ? ($conversions / $clicks) * 100 : 0;
    }
    
    private function calculate_average_order_value($date_range) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_conversion_tracking';
        
        $sql = "SELECT AVG(value) FROM {$table_name} 
                WHERE created_at BETWEEN %s AND %s 
                AND status = 'attributed'";
        
        return floatval($wpdb->get_var($wpdb->prepare($sql, $date_range['start'], $date_range['end'])));
    }
    
    private function calculate_attribution_accuracy($date_range) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_conversion_tracking';
        
        $attributed_sql = "SELECT COUNT(*) FROM {$table_name} 
                          WHERE created_at BETWEEN %s AND %s 
                          AND status = 'attributed' 
                          AND attribution_method IS NOT NULL";
        
        $total_sql = "SELECT COUNT(*) FROM {$table_name} 
                     WHERE created_at BETWEEN %s AND %s";
        
        $attributed = intval($wpdb->get_var($wpdb->prepare($attributed_sql, $date_range['start'], $date_range['end'])));
        $total = intval($wpdb->get_var($wpdb->prepare($total_sql, $date_range['start'], $date_range['end'])));
        
        return $total > 0 ? ($attributed / $total) * 100 : 0;
    }
    
    /**
     * Get default date range
     */
    private function get_default_date_range($period) {
        switch ($period) {
            case '7_days':
                return array(
                    'start' => date('Y-m-d', strtotime('-7 days')),
                    'end' => date('Y-m-d')
                );
            case '30_days':
                return array(
                    'start' => date('Y-m-d', strtotime('-30 days')),
                    'end' => date('Y-m-d')
                );
            case '90_days':
                return array(
                    'start' => date('Y-m-d', strtotime('-90 days')),
                    'end' => date('Y-m-d')
                );
            default:
                return array(
                    'start' => date('Y-m-d', strtotime('-30 days')),
                    'end' => date('Y-m-d')
                );
        }
    }
    
    /**
     * Get previous period for comparison
     */
    private function get_previous_period($date_range) {
        $start_date = strtotime($date_range['start']);
        $end_date = strtotime($date_range['end']);
        $period_length = $end_date - $start_date;
        
        return array(
            'start' => date('Y-m-d', $start_date - $period_length - 86400),
            'end' => date('Y-m-d', $start_date - 86400)
        );
    }
    
    /**
     * Format summary metrics
     */
    private function format_summary_metrics($metrics) {
        return array(
            'total_revenue' => '$' . number_format($metrics['total_revenue'], 2),
            'total_conversions' => number_format($metrics['total_conversions']),
            'conversion_rate' => number_format($metrics['conversion_rate'], 2) . '%',
            'average_order_value' => '$' . number_format($metrics['average_order_value'], 2),
            'attribution_accuracy' => number_format($metrics['attribution_accuracy'], 2) . '%'
        );
    }
    
    /**
     * Get channel performance data
     */
    private function get_channel_performance_data($date_range) {
        global $wpdb;
        
        $analytics_table = $wpdb->prefix . 'khm_attribution_analytics';
        
        $sql = "SELECT 
                    utm_medium,
                    SUM(commission_total) as revenue,
                    SUM(conversions) as conversions,
                    SUM(clicks) as clicks
                FROM {$analytics_table} 
                WHERE date BETWEEN %s AND %s 
                GROUP BY utm_medium
                ORDER BY revenue DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $date_range['start'], $date_range['end']), ARRAY_A);
    }
    
    /**
     * Get top channels
     */
    private function get_top_channels($channel_metrics, $limit = 5) {
        // Sort by revenue and take top N
        usort($channel_metrics, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        
        return array_slice($channel_metrics, 0, $limit);
    }
    
    /**
     * Store report
     */
    private function store_report($report_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_generated_reports';
        $this->maybe_create_reports_table();
        
        $wpdb->insert($table_name, array(
            'template_key' => $report_data['template'],
            'report_data' => json_encode($report_data),
            'date_range_start' => $report_data['date_range']['start'],
            'date_range_end' => $report_data['date_range']['end'],
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Database table creation methods
     */
    private function maybe_create_reports_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_generated_reports';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            template_key VARCHAR(100) NOT NULL,
            report_data LONGTEXT,
            date_range_start DATE,
            date_range_end DATE,
            created_at DATETIME NOT NULL,
            
            INDEX idx_template_date (template_key, date_range_start),
            INDEX idx_created_timeline (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function maybe_create_scheduled_reports_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_scheduled_reports';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            template_key VARCHAR(100) NOT NULL,
            schedule_data TEXT,
            frequency VARCHAR(20) NOT NULL,
            next_run DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME NOT NULL,
            
            INDEX idx_next_run (next_run, status),
            INDEX idx_template_frequency (template_key, frequency)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_generate_report() {
        check_ajax_referer('khm_reporting_nonce', 'nonce');
        
        $template_key = sanitize_text_field($_POST['template'] ?? '');
        $date_range = array(
            'start' => sanitize_text_field($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'))),
            'end' => sanitize_text_field($_POST['end_date'] ?? date('Y-m-d'))
        );
        
        $options = array(
            'date_range' => $date_range,
            'format' => sanitize_text_field($_POST['format'] ?? 'html')
        );
        
        $report = $this->generate_report($template_key, $options);
        
        if ($report) {
            wp_send_json_success($report);
        } else {
            wp_send_json_error('Failed to generate report');
        }
    }
    
    public function ajax_export_report() {
        check_ajax_referer('khm_reporting_nonce', 'nonce');
        
        $report_data = json_decode(stripslashes($_POST['report_data'] ?? ''), true);
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        $export_result = $this->export_report($report_data, $format);
        
        if ($export_result) {
            wp_send_json_success($export_result);
        } else {
            wp_send_json_error('Failed to export report');
        }
    }
    
    public function ajax_schedule_report() {
        check_ajax_referer('khm_reporting_nonce', 'nonce');
        
        $template_key = sanitize_text_field($_POST['template'] ?? '');
        $schedule_options = array(
            'frequency' => sanitize_text_field($_POST['frequency'] ?? 'weekly'),
            'recipients' => array_map('sanitize_email', $_POST['recipients'] ?? array()),
            'format' => sanitize_text_field($_POST['format'] ?? 'pdf'),
            'report_options' => $_POST['report_options'] ?? array()
        );
        
        $schedule_id = $this->schedule_report($template_key, $schedule_options);
        
        if ($schedule_id) {
            wp_send_json_success(array('schedule_id' => $schedule_id));
        } else {
            wp_send_json_error('Failed to schedule report');
        }
    }
    
    /**
     * Add reporting menu
     */
    public function add_reporting_menu() {
        add_submenu_page(
            'khm-attribution',
            'Reports',
            'Reports',
            'manage_options',
            'khm-attribution-reports',
            array($this, 'render_reports_page')
        );
    }
    
    /**
     * Render reports page
     */
    public function render_reports_page() {
        echo '<div class="wrap">';
        echo '<h1>Attribution Reports</h1>';
        
        // Report templates
        echo '<div class="khm-report-templates">';
        echo '<h2>Available Report Templates</h2>';
        
        foreach ($this->report_templates as $key => $template) {
            echo '<div class="report-template">';
            echo '<h3>' . esc_html($template['name']) . '</h3>';
            echo '<p>' . esc_html($template['description']) . '</p>';
            echo '<button class="button generate-report" data-template="' . esc_attr($key) . '">Generate Report</button>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Handle report download
     */
    public function handle_report_download() {
        $filename = sanitize_file_name($_GET['file'] ?? '');
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        if (file_exists($filepath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            wp_die('File not found');
        }
    }
    
    /**
     * Placeholder methods for visualizations (would be implemented with charting library)
     */
    private function generate_summary_visualization($metrics, $changes) {
        return array('type' => 'summary_cards', 'data' => $metrics, 'changes' => $changes);
    }
    
    private function generate_channel_visualization($channel_metrics) {
        return array('type' => 'bar_chart', 'data' => $channel_metrics);
    }
    
    private function generate_trends_visualization($daily_trends) {
        return array('type' => 'line_chart', 'data' => $daily_trends);
    }
    
    // Additional placeholder methods for remaining report sections
    private function generate_channel_metrics_section($options) { return array('title' => 'Channel Metrics', 'data' => array()); }
    private function generate_roi_analysis_section($options) { return array('title' => 'ROI Analysis', 'data' => array()); }
    private function generate_attribution_share_section($options) { return array('title' => 'Attribution Share', 'data' => array()); }
    private function generate_journey_metrics_section($options) { return array('title' => 'Journey Metrics', 'data' => array()); }
    private function generate_touchpoint_analysis_section($options) { return array('title' => 'Touchpoint Analysis', 'data' => array()); }
    private function generate_conversion_paths_section($options) { return array('title' => 'Conversion Paths', 'data' => array()); }
    private function generate_roi_metrics_section($options) { return array('title' => 'ROI Metrics', 'data' => array()); }
    private function generate_budget_allocation_section($options) { return array('title' => 'Budget Allocation', 'data' => array()); }
    private function generate_optimization_opportunities_section($options) { return array('title' => 'Optimization Opportunities', 'data' => array()); }
    private function generate_kpi_summary_section($options) { return array('title' => 'KPI Summary', 'data' => array()); }
    private function generate_recommendations_section($options) { return array('title' => 'Recommendations', 'data' => array()); }
    private function get_daily_trends($date_range) { return array(); }
    private function analyze_trend_patterns($daily_trends) { return array(); }
    private function export_to_xlsx($report_data) { return array('success' => false, 'error' => 'XLSX export not implemented'); }
    private function export_to_pdf($report_data) { return array('success' => false, 'error' => 'PDF export not implemented'); }
}
?>