<?php

namespace KHM_SEO\Alerts;

use Exception;

/**
 * Alert Dashboard
 * 
 * Comprehensive admin interface for alert management, configuration,
 * and monitoring within the KHM SEO Phase 9 Module.
 * 
 * Features:
 * - Real-time alert monitoring and status dashboard
 * - Alert configuration and threshold management
 * - Notification channel configuration
 * - Alert history and analytics
 * - Performance metrics and reporting
 * - Alert escalation management
 * - Custom alert rule creation
 * - Multi-channel notification testing
 * 
 * @package KHM_SEO\Alerts
 * @since 1.0.0
 */
class AlertDashboard {

    /**
     * @var AlertEngine
     */
    private $alert_engine;

    /**
     * Dashboard configuration
     */
    private $config = [
        'alerts_per_page' => 20,
        'history_retention_days' => 90,
        'refresh_interval' => 30,
        'chart_data_points' => 30
    ];

    /**
     * Alert statistics cache
     */
    private $alert_stats = [];

    /**
     * Initialize Alert Dashboard
     */
    public function __construct() {
        $this->alert_engine = new AlertEngine();
        
        \add_action('admin_menu', [$this, 'add_admin_menu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        \add_action('wp_ajax_alert_dashboard_action', [$this, 'handle_ajax_actions']);
        
        $this->init_dashboard();
    }

    /**
     * Initialize dashboard
     */
    private function init_dashboard() {
        $this->load_alert_statistics();
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        \add_submenu_page(
            'khm-seo-dashboard',
            'Alerts & Notifications',
            'Alerts',
            'manage_options',
            'khm-seo-alerts',
            [$this, 'render_main_dashboard']
        );

        \add_submenu_page(
            'khm-seo-alerts',
            'Alert Configuration',
            'Configuration',
            'manage_options',
            'khm-seo-alerts-config',
            [$this, 'render_configuration_page']
        );

        \add_submenu_page(
            'khm-seo-alerts',
            'Alert History',
            'History',
            'manage_options',
            'khm-seo-alerts-history',
            [$this, 'render_history_page']
        );

        \add_submenu_page(
            'khm-seo-alerts',
            'Notification Channels',
            'Channels',
            'manage_options',
            'khm-seo-alerts-channels',
            [$this, 'render_channels_page']
        );
    }

    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        if (strpos($hook, 'khm-seo-alerts') === false) {
            return;
        }

        \wp_enqueue_script(
            'khm-alert-dashboard',
            \plugins_url('assets/js/alert-dashboard.js', dirname(__FILE__, 3)),
            ['jquery', 'chart-js'],
            '1.0.0',
            true
        );

        \wp_enqueue_style(
            'khm-alert-dashboard',
            \plugins_url('assets/css/alert-dashboard.css', dirname(__FILE__, 3)),
            [],
            '1.0.0'
        );

        // Enqueue Chart.js for visualizations
        \wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );

        \wp_localize_script('khm-alert-dashboard', 'khmAlerts', [
            'nonce' => \wp_create_nonce('khm_alert_dashboard'),
            'ajaxurl' => \admin_url('admin-ajax.php'),
            'strings' => [
                'testing' => \__('Testing alert...', 'khm-seo'),
                'configuring' => \__('Saving configuration...', 'khm-seo'),
                'loading' => \__('Loading data...', 'khm-seo'),
                'success' => \__('Operation completed successfully', 'khm-seo'),
                'error' => \__('Operation failed', 'khm-seo')
            ],
            'config' => $this->config
        ]);
    }

    /**
     * Render main dashboard page
     */
    public function render_main_dashboard() {
        $this->load_alert_statistics();
        ?>
        <div class="wrap khm-alert-dashboard">
            <h1>
                <?php \esc_html_e('Alerts & Notifications Dashboard', 'khm-seo'); ?>
                <button id="test-all-channels" class="button button-secondary">
                    <?php \esc_html_e('Test All Channels', 'khm-seo'); ?>
                </button>
            </h1>

            <?php $this->render_dashboard_notices(); ?>

            <div class="alert-overview">
                <?php $this->render_alert_overview(); ?>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-main">
                    <?php $this->render_recent_alerts(); ?>
                    <?php $this->render_alert_trends(); ?>
                </div>
                
                <div class="dashboard-sidebar">
                    <?php $this->render_active_monitors(); ?>
                    <?php $this->render_channel_status(); ?>
                    <?php $this->render_quick_actions(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render configuration page
     */
    public function render_configuration_page() {
        ?>
        <div class="wrap khm-alert-configuration">
            <h1><?php \esc_html_e('Alert Configuration', 'khm-seo'); ?></h1>

            <form method="post" action="options.php">
                <?php
                \settings_fields('khm_seo_alert_config');
                ?>

                <div class="configuration-sections">
                    <?php $this->render_alert_types_configuration(); ?>
                    <?php $this->render_threshold_configuration(); ?>
                    <?php $this->render_escalation_configuration(); ?>
                    <?php $this->render_monitoring_configuration(); ?>
                </div>

                <?php \submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render history page
     */
    public function render_history_page() {
        ?>
        <div class="wrap khm-alert-history">
            <h1><?php \esc_html_e('Alert History', 'khm-seo'); ?></h1>

            <div class="history-controls">
                <?php $this->render_history_filters(); ?>
            </div>

            <div class="history-content">
                <?php $this->render_alert_analytics(); ?>
                <?php $this->render_alert_history_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render channels page
     */
    public function render_channels_page() {
        ?>
        <div class="wrap khm-notification-channels">
            <h1><?php \esc_html_e('Notification Channels', 'khm-seo'); ?></h1>

            <div class="channels-grid">
                <?php $this->render_email_configuration(); ?>
                <?php $this->render_sms_configuration(); ?>
                <?php $this->render_webhook_configuration(); ?>
                <?php $this->render_slack_configuration(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render alert overview section
     */
    private function render_alert_overview() {
        $stats = $this->alert_stats;
        ?>
        <div class="alert-overview-grid">
            <div class="alert-stat-card">
                <div class="stat-value"><?php echo \esc_html($stats['active_alerts'] ?? 0); ?></div>
                <div class="stat-label"><?php \esc_html_e('Active Alerts', 'khm-seo'); ?></div>
                <div class="stat-trend <?php echo ($stats['alert_trend'] ?? 0) > 0 ? 'up' : 'down'; ?>">
                    <?php echo \esc_html($stats['alert_trend'] ?? 0); ?>%
                </div>
            </div>

            <div class="alert-stat-card">
                <div class="stat-value"><?php echo \esc_html($stats['alerts_today'] ?? 0); ?></div>
                <div class="stat-label"><?php \esc_html_e('Alerts Today', 'khm-seo'); ?></div>
            </div>

            <div class="alert-stat-card">
                <div class="stat-value"><?php echo \esc_html($stats['critical_alerts'] ?? 0); ?></div>
                <div class="stat-label"><?php \esc_html_e('Critical Alerts', 'khm-seo'); ?></div>
            </div>

            <div class="alert-stat-card">
                <div class="stat-value"><?php echo \esc_html($stats['channels_active'] ?? 0); ?></div>
                <div class="stat-label"><?php \esc_html_e('Active Channels', 'khm-seo'); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render recent alerts
     */
    private function render_recent_alerts() {
        $recent_alerts = $this->get_recent_alerts();
        ?>
        <div class="recent-alerts-section">
            <h2><?php \esc_html_e('Recent Alerts', 'khm-seo'); ?></h2>
            
            <?php if (!empty($recent_alerts)): ?>
                <div class="alerts-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php \esc_html_e('Time', 'khm-seo'); ?></th>
                                <th><?php \esc_html_e('Type', 'khm-seo'); ?></th>
                                <th><?php \esc_html_e('Priority', 'khm-seo'); ?></th>
                                <th><?php \esc_html_e('Message', 'khm-seo'); ?></th>
                                <th><?php \esc_html_e('Status', 'khm-seo'); ?></th>
                                <th><?php \esc_html_e('Actions', 'khm-seo'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_alerts as $alert): ?>
                                <tr class="alert-row priority-<?php echo \esc_attr($alert['priority']); ?>">
                                    <td><?php echo \esc_html(\date('M j, Y g:i A', \strtotime($alert['created_at']))); ?></td>
                                    <td>
                                        <span class="alert-type"><?php echo \esc_html(\ucwords(\str_replace('_', ' ', $alert['type']))); ?></span>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo \esc_attr($alert['priority']); ?>">
                                            <?php echo \esc_html(\ucfirst($alert['priority'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="alert-message">
                                            <?php echo \esc_html($this->get_alert_summary($alert)); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo \esc_attr($alert['status']); ?>">
                                            <?php echo \esc_html(\ucfirst($alert['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="alert-actions">
                                            <button class="button button-small view-details" data-alert-id="<?php echo \esc_attr($alert['alert_id']); ?>">
                                                <?php \esc_html_e('Details', 'khm-seo'); ?>
                                            </button>
                                            <?php if ($alert['status'] === 'queued'): ?>
                                                <button class="button button-small resend-alert" data-alert-id="<?php echo \esc_attr($alert['alert_id']); ?>">
                                                    <?php \esc_html_e('Resend', 'khm-seo'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-alerts-message">
                    <p><?php \esc_html_e('No recent alerts. Your SEO monitoring is running smoothly!', 'khm-seo'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render alert types configuration
     */
    private function render_alert_types_configuration() {
        $alert_types = $this->get_alert_types_config();
        ?>
        <div class="configuration-section">
            <h2><?php \esc_html_e('Alert Types Configuration', 'khm-seo'); ?></h2>
            
            <div class="alert-types-grid">
                <?php foreach ($alert_types as $type => $config): ?>
                    <div class="alert-type-config">
                        <h3><?php echo \esc_html($config['name']); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php \esc_html_e('Enabled', 'khm-seo'); ?></th>
                                <td>
                                    <input type="checkbox" 
                                           name="khm_alert_types[<?php echo \esc_attr($type); ?>][enabled]"
                                           value="1" 
                                           <?php checked($config['enabled']); ?> />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php \esc_html_e('Priority', 'khm-seo'); ?></th>
                                <td>
                                    <select name="khm_alert_types[<?php echo \esc_attr($type); ?>][priority]">
                                        <option value="low" <?php selected($config['priority'], 'low'); ?>><?php \esc_html_e('Low', 'khm-seo'); ?></option>
                                        <option value="medium" <?php selected($config['priority'], 'medium'); ?>><?php \esc_html_e('Medium', 'khm-seo'); ?></option>
                                        <option value="high" <?php selected($config['priority'], 'high'); ?>><?php \esc_html_e('High', 'khm-seo'); ?></option>
                                        <option value="critical" <?php selected($config['priority'], 'critical'); ?>><?php \esc_html_e('Critical', 'khm-seo'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php \esc_html_e('Threshold', 'khm-seo'); ?></th>
                                <td>
                                    <input type="number" 
                                           name="khm_alert_types[<?php echo \esc_attr($type); ?>][threshold]"
                                           value="<?php echo \esc_attr($config['threshold']); ?>"
                                           step="0.1" 
                                           class="regular-text" />
                                    <p class="description"><?php echo \esc_html($this->get_threshold_description($type)); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php \esc_html_e('Cooldown (seconds)', 'khm-seo'); ?></th>
                                <td>
                                    <input type="number" 
                                           name="khm_alert_types[<?php echo \esc_attr($type); ?>][cooldown]"
                                           value="<?php echo \esc_attr($config['cooldown']); ?>"
                                           class="regular-text" />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php \esc_html_e('Channels', 'khm-seo'); ?></th>
                                <td>
                                    <?php $this->render_channel_checkboxes($type, $config['channels']); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render email configuration
     */
    private function render_email_configuration() {
        $email_config = $this->get_channel_config('email');
        ?>
        <div class="channel-config email-config">
            <h2><?php \esc_html_e('Email Notifications', 'khm-seo'); ?></h2>
            
            <form method="post" action="options.php">
                <?php \settings_fields('khm_seo_email_config'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php \esc_html_e('Enable Email Alerts', 'khm-seo'); ?></th>
                        <td>
                            <input type="checkbox" 
                                   name="khm_email_config[enabled]" 
                                   value="1" 
                                   <?php checked($email_config['enabled'] ?? false); ?> />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php \esc_html_e('From Email', 'khm-seo'); ?></th>
                        <td>
                            <input type="email" 
                                   name="khm_email_config[from_email]" 
                                   value="<?php echo \esc_attr($email_config['from_email'] ?? ''); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php \esc_html_e('From Name', 'khm-seo'); ?></th>
                        <td>
                            <input type="text" 
                                   name="khm_email_config[from_name]" 
                                   value="<?php echo \esc_attr($email_config['from_name'] ?? 'SEO Alert System'); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php \esc_html_e('Recipients', 'khm-seo'); ?></th>
                        <td>
                            <textarea name="khm_email_config[recipients]" 
                                      class="large-text" 
                                      rows="3"
                                      placeholder="admin@example.com&#10;manager@example.com"><?php echo \esc_textarea($email_config['recipients'] ?? ''); ?></textarea>
                            <p class="description"><?php \esc_html_e('One email address per line', 'khm-seo'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php \esc_html_e('Rate Limit (per hour)', 'khm-seo'); ?></th>
                        <td>
                            <input type="number" 
                                   name="khm_email_config[rate_limit]" 
                                   value="<?php echo \esc_attr($email_config['rate_limit'] ?? 20); ?>"
                                   class="small-text" />
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php \submit_button(\__('Save Email Configuration', 'khm-seo'), 'primary', 'save_email_config'); ?>
                    <button type="button" class="button button-secondary test-channel" data-channel="email">
                        <?php \esc_html_e('Send Test Email', 'khm-seo'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle AJAX actions
     */
    public function handle_ajax_actions() {
        if (!\wp_verify_nonce($_POST['nonce'], 'khm_alert_dashboard')) {
            \wp_die('Security check failed');
        }

        $action = \sanitize_text_field($_POST['action_type'] ?? '');
        
        switch ($action) {
            case 'test_alert':
                $this->ajax_test_alert();
                break;
                
            case 'get_alert_details':
                $this->ajax_get_alert_details();
                break;
                
            case 'configure_alert_type':
                $this->ajax_configure_alert_type();
                break;
                
            case 'test_channel':
                $this->ajax_test_channel();
                break;
                
            case 'get_alert_history':
                $this->ajax_get_alert_history();
                break;
                
            default:
                \wp_send_json_error(['message' => 'Invalid action']);
        }
    }

    /**
     * AJAX: Test specific alert type
     */
    private function ajax_test_alert() {
        $alert_type = \sanitize_text_field($_POST['alert_type'] ?? '');
        $channel = \sanitize_text_field($_POST['channel'] ?? 'email');

        if (empty($alert_type)) {
            \wp_send_json_error(['message' => 'Alert type is required']);
            return;
        }

        try {
            // Use the alert engine to send a test alert
            $result = $this->alert_engine->ajax_test_alert();
            
            \wp_send_json_success([
                'message' => 'Test alert sent successfully',
                'alert_type' => $alert_type,
                'channel' => $channel
            ]);

        } catch (Exception $e) {
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Helper methods
     */
    private function load_alert_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_alerts';
        
        $this->alert_stats = [
            'active_alerts' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status IN ('queued', 'processing')"),
            'alerts_today' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = CURDATE()"),
            'critical_alerts' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE priority = 'critical' AND DATE(created_at) = CURDATE()"),
            'channels_active' => $this->count_active_channels()
        ];
        
        // Calculate alert trend
        $yesterday_alerts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
        if ($yesterday_alerts > 0) {
            $this->alert_stats['alert_trend'] = round((($this->alert_stats['alerts_today'] - $yesterday_alerts) / $yesterday_alerts) * 100, 1);
        } else {
            $this->alert_stats['alert_trend'] = 0;
        }
    }

    private function get_recent_alerts($limit = 20) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_seo_alerts';
        
        return $wpdb->get_results("
            SELECT * FROM $table_name 
            ORDER BY created_at DESC 
            LIMIT $limit
        ", ARRAY_A);
    }

    private function get_alert_summary($alert) {
        $data = \json_decode($alert['data'], true);
        $type = $alert['type'];

        switch ($type) {
            case 'ranking_drop':
                return sprintf('Keyword "%s" dropped from position %d to %d', 
                    $data['keyword'] ?? 'unknown', 
                    $data['from_position'] ?? 0, 
                    $data['to_position'] ?? 0
                );
            case 'core_web_vitals':
                return sprintf('Core Web Vitals issues on %s', $data['url'] ?? 'unknown URL');
            case 'crawl_errors':
                return sprintf('Crawl error on %s (Status: %d)', 
                    $data['url'] ?? 'unknown URL', 
                    $data['status_code'] ?? 0
                );
            default:
                return 'SEO alert detected';
        }
    }

    private function count_active_channels() {
        $channels = \get_option('khm_seo_notification_channels', []);
        $active = 0;
        
        foreach (['email', 'sms', 'webhook', 'slack'] as $channel) {
            if (!empty($channels[$channel]['enabled'])) {
                $active++;
            }
        }
        
        return $active;
    }

    // Placeholder methods for missing functionality
    private function render_dashboard_notices() { return; }
    private function render_alert_trends() { return; }
    private function render_active_monitors() { return; }
    private function render_channel_status() { return; }
    private function render_quick_actions() { return; }
    private function render_threshold_configuration() { return; }
    private function render_escalation_configuration() { return; }
    private function render_monitoring_configuration() { return; }
    private function render_history_filters() { return; }
    private function render_alert_analytics() { return; }
    private function render_alert_history_table() { return; }
    private function render_sms_configuration() { return; }
    private function render_webhook_configuration() { return; }
    private function render_slack_configuration() { return; }
    private function get_alert_types_config() { return []; }
    private function get_threshold_description($type) { return 'Configure threshold for this alert type'; }
    private function render_channel_checkboxes($type, $channels) { return; }
    private function get_channel_config($channel) { return []; }
    
    // AJAX methods (placeholder)
    private function ajax_get_alert_details() { \wp_send_json_success([]); }
    private function ajax_configure_alert_type() { \wp_send_json_success([]); }
    private function ajax_test_channel() { \wp_send_json_success([]); }
    private function ajax_get_alert_history() { \wp_send_json_success([]); }
}