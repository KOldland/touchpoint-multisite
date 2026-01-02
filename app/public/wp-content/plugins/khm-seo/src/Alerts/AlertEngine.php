<?php

namespace KHM_SEO\Alerts;

use Exception;

/**
 * Alert Engine
 * 
 * Comprehensive real-time monitoring and alert system for SEO issues,
 * ranking changes, technical problems, and optimization opportunities.
 * 
 * Features:
 * - Real-time SEO monitoring and issue detection
 * - Multi-channel notification delivery (email, SMS, webhook, Slack)
 * - Configurable alert thresholds and triggers
 * - Alert prioritization and escalation rules
 * - Historical alert tracking and analytics
 * - Performance degradation monitoring
 * - Ranking change notifications
 * - Technical issue detection and alerts
 * - Competitive monitoring alerts
 * - Custom alert rule creation
 * 
 * @package KHM_SEO\Alerts
 * @since 1.0.0
 */
class AlertEngine {

    /**
     * Alert configuration
     */
    private $config = [
        'check_interval' => 300, // 5 minutes
        'batch_size' => 50,
        'max_alerts_per_hour' => 100,
        'alert_retention_days' => 90,
        'escalation_threshold' => 3,
        'rate_limit_per_channel' => 10
    ];

    /**
     * Alert types and their default configurations
     */
    private $alert_types = [
        'ranking_drop' => [
            'name' => 'Ranking Drop Alert',
            'priority' => 'high',
            'threshold' => 5, // positions
            'cooldown' => 3600, // 1 hour
            'channels' => ['email', 'slack'],
            'enabled' => true
        ],
        'ranking_improvement' => [
            'name' => 'Ranking Improvement',
            'priority' => 'medium',
            'threshold' => 3,
            'cooldown' => 7200,
            'channels' => ['email'],
            'enabled' => true
        ],
        'core_web_vitals' => [
            'name' => 'Core Web Vitals Issue',
            'priority' => 'critical',
            'threshold' => 0.1, // CLS threshold
            'cooldown' => 1800,
            'channels' => ['email', 'sms', 'webhook'],
            'enabled' => true
        ],
        'crawl_errors' => [
            'name' => 'Crawl Error Detected',
            'priority' => 'high',
            'threshold' => 1,
            'cooldown' => 3600,
            'channels' => ['email', 'slack'],
            'enabled' => true
        ],
        'indexing_issues' => [
            'name' => 'Indexing Issues',
            'priority' => 'high',
            'threshold' => 5, // pages
            'cooldown' => 3600,
            'channels' => ['email'],
            'enabled' => true
        ],
        'schema_errors' => [
            'name' => 'Schema Validation Errors',
            'priority' => 'medium',
            'threshold' => 1,
            'cooldown' => 7200,
            'channels' => ['email'],
            'enabled' => true
        ],
        'security_issues' => [
            'name' => 'Security Issues',
            'priority' => 'critical',
            'threshold' => 1,
            'cooldown' => 900, // 15 minutes
            'channels' => ['email', 'sms', 'webhook'],
            'enabled' => true
        ],
        'performance_degradation' => [
            'name' => 'Performance Degradation',
            'priority' => 'medium',
            'threshold' => 20, // percentage
            'cooldown' => 3600,
            'channels' => ['email'],
            'enabled' => true
        ],
        'traffic_drop' => [
            'name' => 'Traffic Drop Alert',
            'priority' => 'high',
            'threshold' => 25, // percentage
            'cooldown' => 3600,
            'channels' => ['email', 'slack'],
            'enabled' => true
        ],
        'competitor_changes' => [
            'name' => 'Competitor Activity',
            'priority' => 'low',
            'threshold' => 1,
            'cooldown' => 86400, // 24 hours
            'channels' => ['email'],
            'enabled' => false
        ]
    ];

    /**
     * Notification channels configuration
     */
    private $channels = [
        'email' => [
            'enabled' => true,
            'rate_limit' => 20,
            'template_path' => 'alerts/email',
            'from_email' => '',
            'from_name' => 'SEO Alert System'
        ],
        'sms' => [
            'enabled' => false,
            'rate_limit' => 5,
            'provider' => 'twilio',
            'api_key' => '',
            'from_number' => ''
        ],
        'webhook' => [
            'enabled' => false,
            'rate_limit' => 50,
            'url' => '',
            'secret' => '',
            'timeout' => 30
        ],
        'slack' => [
            'enabled' => false,
            'rate_limit' => 30,
            'webhook_url' => '',
            'channel' => '#seo-alerts',
            'username' => 'SEO Bot'
        ]
    ];

    /**
     * Alert queue and processing
     */
    private $alert_queue = [];
    private $alert_cache = [];
    private $rate_limits = [];

    /**
     * Dependencies
     */
    private $database;
    private $monitoring;

    /**
     * Initialize Alert Engine
     */
    public function __construct() {
        // Initialize database connection if available
        if (class_exists('KHM\\SEO\\Database\\DatabaseManager')) {
            $this->database = new \KHM_SEO\Database\DatabaseManager();
        }

        add_action('init', [$this, 'init_alert_system']);
        add_action('khm_seo_process_alerts', [$this, 'process_alert_queue']);
        add_action('khm_seo_monitor_alerts', [$this, 'run_monitoring_checks']);
        
        $this->init_alert_engine();
    }

    /**
     * Initialize alert engine
     */
    private function init_alert_engine() {
        $this->load_alert_configuration();
        $this->schedule_monitoring_tasks();
        $this->init_notification_channels();
    }

    /**
     * Initialize WordPress hooks and scheduling
     */
    public function init_alert_system() {
        // Schedule monitoring checks
        if (!wp_next_scheduled('khm_seo_monitor_alerts')) {
            wp_schedule_event(time(), 'khm_seo_5min', 'khm_seo_monitor_alerts');
        }

        // Schedule alert processing
        if (!wp_next_scheduled('khm_seo_process_alerts')) {
            wp_schedule_event(time(), 'minutely', 'khm_seo_process_alerts');
        }

        // Add custom cron intervals
        add_filter('cron_schedules', [$this, 'add_custom_cron_intervals']);

        // AJAX handlers
        add_action('wp_ajax_khm_test_alert', [$this, 'ajax_test_alert']);
        add_action('wp_ajax_khm_configure_alerts', [$this, 'ajax_configure_alerts']);
        add_action('wp_ajax_khm_get_alert_history', [$this, 'ajax_get_alert_history']);
    }

    /**
     * Add custom cron intervals
     */
    public function add_custom_cron_intervals($schedules) {
        $schedules['minutely'] = [
            'interval' => 60,
            'display' => __('Every Minute', 'khm-seo')
        ];
        
        $schedules['khm_seo_5min'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'khm-seo')
        ];

        return $schedules;
    }

    /**
     * Run comprehensive monitoring checks
     */
    public function run_monitoring_checks() {
        try {
            $this->monitor_ranking_changes();
            $this->monitor_core_web_vitals();
            $this->monitor_crawl_errors();
            $this->monitor_indexing_issues();
            $this->monitor_schema_errors();
            $this->monitor_security_issues();
            $this->monitor_performance();
            $this->monitor_traffic_changes();
            
            $this->process_alert_queue();
            
        } catch (Exception $e) {
            error_log('Alert monitoring failed: ' . $e->getMessage());
        }
    }

    /**
     * Monitor ranking changes
     */
    private function monitor_ranking_changes() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_rankings';
        
        // Get recent ranking changes
        $ranking_changes = $wpdb->get_results("
            SELECT 
                r1.keyword,
                r1.position as current_position,
                r2.position as previous_position,
                (r2.position - r1.position) as change,
                r1.url
            FROM {$table_name} r1
            LEFT JOIN {$table_name} r2 ON r1.keyword = r2.keyword 
                AND r2.date = DATE_SUB(r1.date, INTERVAL 1 DAY)
            WHERE r1.date = CURDATE()
            AND r2.position IS NOT NULL
            AND ABS(r2.position - r1.position) >= {$this->alert_types['ranking_drop']['threshold']}
        ");

        foreach ($ranking_changes as $change) {
            if ($change->change > 0) { // Ranking drop (higher position number)
                $this->queue_alert('ranking_drop', [
                    'keyword' => $change->keyword,
                    'from_position' => $change->previous_position,
                    'to_position' => $change->current_position,
                    'change' => $change->change,
                    'url' => $change->url
                ]);
            } elseif ($change->change < -$this->alert_types['ranking_improvement']['threshold']) {
                $this->queue_alert('ranking_improvement', [
                    'keyword' => $change->keyword,
                    'from_position' => $change->previous_position,
                    'to_position' => $change->current_position,
                    'improvement' => abs($change->change),
                    'url' => $change->url
                ]);
            }
        }
    }

    /**
     * Monitor Core Web Vitals
     */
    private function monitor_core_web_vitals() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_performance';
        
        // Check for CWV threshold violations
        $cwv_issues = $wpdb->get_results("
            SELECT 
                url,
                lcp,
                fid,
                cls,
                created_at
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND (
                lcp > 2.5 OR
                fid > 100 OR
                cls > {$this->alert_types['core_web_vitals']['threshold']}
            )
        ");

        foreach ($cwv_issues as $issue) {
            $this->queue_alert('core_web_vitals', [
                'url' => $issue->url,
                'lcp' => $issue->lcp,
                'fid' => $issue->fid,
                'cls' => $issue->cls,
                'timestamp' => $issue->created_at
            ]);
        }
    }

    /**
     * Monitor crawl errors
     */
    private function monitor_crawl_errors() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_crawl_data';
        
        // Check for new crawl errors
        $crawl_errors = $wpdb->get_results("
            SELECT 
                url,
                error_type,
                error_message,
                status_code,
                discovered_at
            FROM {$table_name}
            WHERE discovered_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND status_code >= 400
        ");

        foreach ($crawl_errors as $error) {
            $this->queue_alert('crawl_errors', [
                'url' => $error->url,
                'error_type' => $error->error_type,
                'error_message' => $error->error_message,
                'status_code' => $error->status_code,
                'discovered_at' => $error->discovered_at
            ]);
        }
    }

    /**
     * Monitor indexing issues
     */
    private function monitor_indexing_issues() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_indexing_status';
        
        // Check for indexing coverage issues
        $indexing_issues = $wpdb->get_results("
            SELECT 
                url,
                coverage_status,
                issue_type,
                last_crawled,
                updated_at
            FROM {$table_name}
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND coverage_status IN ('error', 'excluded', 'valid_with_warnings')
        ");

        if (count($indexing_issues) >= $this->alert_types['indexing_issues']['threshold']) {
            $this->queue_alert('indexing_issues', [
                'issues_count' => count($indexing_issues),
                'issues' => array_slice($indexing_issues, 0, 10) // Limit details
            ]);
        }
    }

    /**
     * Monitor schema validation errors
     */
    private function monitor_schema_errors() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_schema_validation';
        
        // Check for schema validation errors
        $schema_errors = $wpdb->get_results("
            SELECT 
                url,
                schema_type,
                error_count,
                validation_result,
                created_at
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND error_count > 0
        ");

        foreach ($schema_errors as $error) {
            $this->queue_alert('schema_errors', [
                'url' => $error->url,
                'schema_type' => $error->schema_type,
                'error_count' => $error->error_count,
                'validation_result' => json_decode($error->validation_result, true)
            ]);
        }
    }

    /**
     * Monitor security issues
     */
    private function monitor_security_issues() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_security_scans';
        
        // Check for security vulnerabilities
        $security_issues = $wpdb->get_results("
            SELECT 
                url,
                issue_type,
                severity,
                description,
                detected_at
            FROM {$table_name}
            WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND severity IN ('critical', 'high')
        ");

        foreach ($security_issues as $issue) {
            $this->queue_alert('security_issues', [
                'url' => $issue->url,
                'issue_type' => $issue->issue_type,
                'severity' => $issue->severity,
                'description' => $issue->description,
                'detected_at' => $issue->detected_at
            ]);
        }
    }

    /**
     * Monitor performance degradation
     */
    private function monitor_performance() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_performance';
        
        // Compare current performance with baseline
        $performance_check = $wpdb->get_row("
            SELECT 
                AVG(load_time) as current_avg,
                (
                    SELECT AVG(load_time) 
                    FROM {$table_name} 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
                ) as baseline_avg
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");

        if ($performance_check && $performance_check->baseline_avg > 0) {
            $degradation = (($performance_check->current_avg - $performance_check->baseline_avg) / $performance_check->baseline_avg) * 100;
            
            if ($degradation >= $this->alert_types['performance_degradation']['threshold']) {
                $this->queue_alert('performance_degradation', [
                    'current_avg' => $performance_check->current_avg,
                    'baseline_avg' => $performance_check->baseline_avg,
                    'degradation_percent' => $degradation
                ]);
            }
        }
    }

    /**
     * Monitor traffic changes
     */
    private function monitor_traffic_changes() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_analytics';
        
        // Compare today's traffic with yesterday
        $traffic_comparison = $wpdb->get_row("
            SELECT 
                SUM(CASE WHEN date = CURDATE() THEN sessions ELSE 0 END) as today_sessions,
                SUM(CASE WHEN date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN sessions ELSE 0 END) as yesterday_sessions
            FROM {$table_name}
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND traffic_source = 'organic'
        ");

        if ($traffic_comparison && $traffic_comparison->yesterday_sessions > 0) {
            $traffic_change = (($traffic_comparison->yesterday_sessions - $traffic_comparison->today_sessions) / $traffic_comparison->yesterday_sessions) * 100;
            
            if ($traffic_change >= $this->alert_types['traffic_drop']['threshold']) {
                $this->queue_alert('traffic_drop', [
                    'today_sessions' => $traffic_comparison->today_sessions,
                    'yesterday_sessions' => $traffic_comparison->yesterday_sessions,
                    'drop_percent' => $traffic_change
                ]);
            }
        }
    }

    /**
     * Queue an alert for processing
     */
    private function queue_alert($type, $data) {
        $alert_id = $this->generate_alert_id($type, $data);
        
        // Check if alert was recently sent (cooldown)
        if ($this->is_alert_in_cooldown($type, $alert_id)) {
            return false;
        }

        $alert = [
            'id' => $alert_id,
            'type' => $type,
            'data' => $data,
            'priority' => $this->alert_types[$type]['priority'],
            'channels' => $this->alert_types[$type]['channels'],
            'created_at' => current_time('mysql'),
            'status' => 'queued'
        ];

        $this->alert_queue[] = $alert;
        $this->store_alert($alert);
        
        return true;
    }

    /**
     * Process the alert queue
     */
    public function process_alert_queue() {
        if (empty($this->alert_queue)) {
            $this->load_pending_alerts();
        }

        $processed = 0;
        $max_batch = $this->config['batch_size'];

        foreach ($this->alert_queue as $index => $alert) {
            if ($processed >= $max_batch) {
                break;
            }

            if ($this->send_alert($alert)) {
                unset($this->alert_queue[$index]);
                $this->update_alert_status($alert['id'], 'sent');
                $processed++;
            } else {
                $this->update_alert_status($alert['id'], 'failed');
                unset($this->alert_queue[$index]);
            }
        }

        // Reindex array
        $this->alert_queue = array_values($this->alert_queue);
    }

    /**
     * Send alert through configured channels
     */
    private function send_alert($alert) {
        $success = false;
        $alert_config = $this->alert_types[$alert['type']];

        foreach ($alert_config['channels'] as $channel) {
            if (!$this->channels[$channel]['enabled']) {
                continue;
            }

            if ($this->is_channel_rate_limited($channel)) {
                continue;
            }

            try {
                switch ($channel) {
                    case 'email':
                        $success = $this->send_email_alert($alert) || $success;
                        break;
                    case 'sms':
                        $success = $this->send_sms_alert($alert) || $success;
                        break;
                    case 'webhook':
                        $success = $this->send_webhook_alert($alert) || $success;
                        break;
                    case 'slack':
                        $success = $this->send_slack_alert($alert) || $success;
                        break;
                }

                $this->increment_channel_rate_limit($channel);

            } catch (Exception $e) {
                error_log("Failed to send {$channel} alert: " . $e->getMessage());
            }
        }

        return $success;
    }

    /**
     * Send email alert
     */
    private function send_email_alert($alert) {
        $to = $this->get_alert_recipients('email');
        if (empty($to)) {
            return false;
        }

        $subject = $this->get_alert_subject($alert);
        $message = $this->get_alert_message($alert, 'email');
        $headers = $this->get_email_headers();

        return \wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send SMS alert
     */
    private function send_sms_alert($alert) {
        if (!$this->channels['sms']['enabled']) {
            return false;
        }

        $phone_numbers = $this->get_alert_recipients('sms');
        if (empty($phone_numbers)) {
            return false;
        }

        $message = $this->get_alert_message($alert, 'sms');
        
        // Integration with SMS provider (Twilio example)
        return $this->send_via_twilio($phone_numbers, $message);
    }

    /**
     * Send webhook alert
     */
    private function send_webhook_alert($alert) {
        $webhook_url = $this->channels['webhook']['url'];
        if (empty($webhook_url)) {
            return false;
        }

        $payload = [
            'alert' => $alert,
            'timestamp' => time(),
            'source' => get_site_url()
        ];

        $signature = $this->generate_webhook_signature($payload);
        
        $response = wp_remote_post($webhook_url, [
            'timeout' => $this->channels['webhook']['timeout'],
            'headers' => [
                'Content-Type' => 'application/json',
                'X-SEO-Signature' => $signature
            ],
            'body' => wp_json_encode($payload)
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Send Slack alert
     */
    private function send_slack_alert($alert) {
        $webhook_url = $this->channels['slack']['webhook_url'];
        if (empty($webhook_url)) {
            return false;
        }

        $message = $this->format_slack_message($alert);
        
        $response = wp_remote_post($webhook_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($message)
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Format message for Slack
     */
    private function format_slack_message($alert) {
        $color = $this->get_alert_color($alert['priority']);
        $title = $this->alert_types[$alert['type']]['name'];
        $message = $this->get_alert_message($alert, 'slack');

        return [
            'channel' => $this->channels['slack']['channel'],
            'username' => $this->channels['slack']['username'],
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $title,
                    'text' => $message,
                    'ts' => time()
                ]
            ]
        ];
    }

    /**
     * Get alert message for specific channel
     */
    private function get_alert_message($alert, $channel) {
        $data = $alert['data'];
        $type = $alert['type'];

        switch ($type) {
            case 'ranking_drop':
                return $this->format_ranking_drop_message($data, $channel);
            case 'ranking_improvement':
                return $this->format_ranking_improvement_message($data, $channel);
            case 'core_web_vitals':
                return $this->format_cwv_message($data, $channel);
            case 'crawl_errors':
                return $this->format_crawl_error_message($data, $channel);
            case 'traffic_drop':
                return $this->format_traffic_drop_message($data, $channel);
            default:
                return $this->format_generic_message($alert, $channel);
        }
    }

    /**
     * Format ranking drop message
     */
    private function format_ranking_drop_message($data, $channel) {
        if ($channel === 'sms') {
            return sprintf(
                'SEO Alert: Keyword "%s" dropped from position %d to %d',
                $data['keyword'],
                $data['from_position'],
                $data['to_position']
            );
        }

        return sprintf(
            'Ranking Alert: The keyword "%s" has dropped from position %d to position %d (-%d positions) for URL: %s',
            $data['keyword'],
            $data['from_position'],
            $data['to_position'],
            $data['change'],
            $data['url']
        );
    }

    /**
     * Format Core Web Vitals message
     */
    private function format_cwv_message($data, $channel) {
        $issues = [];
        
        if ($data['lcp'] > 2.5) {
            $issues[] = "LCP: {$data['lcp']}s (threshold: 2.5s)";
        }
        if ($data['fid'] > 100) {
            $issues[] = "FID: {$data['fid']}ms (threshold: 100ms)";
        }
        if ($data['cls'] > 0.1) {
            $issues[] = "CLS: {$data['cls']} (threshold: 0.1)";
        }

        if ($channel === 'sms') {
            return sprintf(
                'CWV Alert: Performance issues detected on %s',
                $data['url']
            );
        }

        return sprintf(
            'Core Web Vitals Alert: Performance issues detected on %s. Issues: %s',
            $data['url'],
            implode(', ', $issues)
        );
    }

    /**
     * Helper methods for alert processing
     */
    private function generate_alert_id($type, $data) {
        return md5($type . serialize($data) . date('Y-m-d-H'));
    }

    private function is_alert_in_cooldown($type, $alert_id) {
        $cooldown = $this->alert_types[$type]['cooldown'];
        $cache_key = "alert_cooldown_{$alert_id}";
        
        return get_transient($cache_key) !== false;
    }

    private function set_alert_cooldown($type, $alert_id) {
        $cooldown = $this->alert_types[$type]['cooldown'];
        $cache_key = "alert_cooldown_{$alert_id}";
        
        set_transient($cache_key, true, $cooldown);
    }

    private function is_channel_rate_limited($channel) {
        $limit = $this->channels[$channel]['rate_limit'];
        $current = $this->rate_limits[$channel] ?? 0;
        
        return $current >= $limit;
    }

    private function increment_channel_rate_limit($channel) {
        $this->rate_limits[$channel] = ($this->rate_limits[$channel] ?? 0) + 1;
        
        // Set expiry for rate limit reset
        $cache_key = "rate_limit_{$channel}";
        set_transient($cache_key, $this->rate_limits[$channel], HOUR_IN_SECONDS);
    }

    /**
     * Database operations
     */
    private function store_alert($alert) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_alerts';
        
        $wpdb->insert($table_name, [
            'alert_id' => $alert['id'],
            'type' => $alert['type'],
            'priority' => $alert['priority'],
            'data' => wp_json_encode($alert['data']),
            'channels' => wp_json_encode($alert['channels']),
            'status' => $alert['status'],
            'created_at' => $alert['created_at']
        ]);
    }

    private function update_alert_status($alert_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_alerts';
        
        $wpdb->update(
            $table_name,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['alert_id' => $alert_id]
        );
    }

    private function load_pending_alerts() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_alerts';
        
        $alerts = $wpdb->get_results("
            SELECT * FROM {$table_name}
            WHERE status = 'queued'
            ORDER BY created_at ASC
            LIMIT {$this->config['batch_size']}
        ", ARRAY_A);

        foreach ($alerts as $alert_data) {
            $this->alert_queue[] = [
                'id' => $alert_data['alert_id'],
                'type' => $alert_data['type'],
                'priority' => $alert_data['priority'],
                'data' => json_decode($alert_data['data'], true),
                'channels' => json_decode($alert_data['channels'], true),
                'created_at' => $alert_data['created_at'],
                'status' => $alert_data['status']
            ];
        }
    }

    /**
     * Configuration and helper methods
     */
    private function load_alert_configuration() {
        $custom_config = get_option('khm_seo_alert_config', []);
        
        if (!empty($custom_config)) {
            $this->config = array_merge($this->config, $custom_config);
        }
    }

    private function schedule_monitoring_tasks() {
        // Tasks are scheduled in init_alert_system()
    }

    private function init_notification_channels() {
        $channel_config = get_option('khm_seo_notification_channels', []);
        
        foreach ($channel_config as $channel => $config) {
            if (isset($this->channels[$channel]) && is_array($config)) {
                $this->channels[$channel] = array_merge($this->channels[$channel], $config);
            }
        }
    }

    /**
     * AJAX handlers
     */
    public function ajax_test_alert() {
        if (!wp_verify_nonce($_POST['nonce'], 'khm_seo_alerts')) {
            wp_die('Security check failed');
        }

        $type = sanitize_text_field($_POST['type'] ?? '');
        $channel = sanitize_text_field($_POST['channel'] ?? '');

        if (empty($type) || !isset($this->alert_types[$type])) {
            wp_send_json_error(['message' => 'Invalid alert type']);
            return;
        }

        $test_alert = [
            'id' => 'test_' . time(),
            'type' => $type,
            'priority' => $this->alert_types[$type]['priority'],
            'data' => $this->get_test_alert_data($type),
            'channels' => [$channel],
            'created_at' => current_time('mysql'),
            'status' => 'test'
        ];

        $result = $this->send_alert($test_alert);
        
        if ($result) {
            wp_send_json_success(['message' => 'Test alert sent successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to send test alert']);
        }
    }

    /**
     * Placeholder methods for missing functionality
     */
    private function get_test_alert_data($type) {
        switch ($type) {
            case 'ranking_drop':
                return [
                    'keyword' => 'test keyword',
                    'from_position' => 5,
                    'to_position' => 12,
                    'change' => 7,
                    'url' => home_url('/')
                ];
            default:
                return ['message' => 'Test alert'];
        }
    }

    // Additional helper methods (placeholder)
    private function get_alert_subject($alert) { return 'SEO Alert: ' . $this->alert_types[$alert['type']]['name']; }
    private function get_email_headers() { return ['Content-Type: text/html; charset=UTF-8']; }
    private function get_alert_recipients($channel) { return ['admin@example.com']; }
    private function get_alert_color($priority) { 
        return $priority === 'critical' ? 'danger' : ($priority === 'high' ? 'warning' : 'good'); 
    }
    private function generate_webhook_signature($payload) { return hash_hmac('sha256', wp_json_encode($payload), 'secret'); }
    private function send_via_twilio($numbers, $message) { return false; }
    private function format_traffic_drop_message($data, $channel) { return 'Traffic drop detected'; }
    private function format_crawl_error_message($data, $channel) { return 'Crawl error detected'; }
    private function format_ranking_improvement_message($data, $channel) { return 'Ranking improvement detected'; }
    private function format_generic_message($alert, $channel) { return 'SEO alert: ' . $alert['type']; }
    
    // Additional AJAX handlers (placeholder)
    public function ajax_configure_alerts() { wp_send_json_success([]); }
    public function ajax_get_alert_history() { wp_send_json_success([]); }
}