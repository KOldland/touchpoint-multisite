<?php
/**
 * Attribution System Admin Interface
 * 
 * Provides configuration and monitoring capabilities for the advanced attribution system
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Admin {
    
    private $attribution_manager;
    private $page_slug = 'khm-attribution';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_khm_test_attribution', array($this, 'handle_attribution_test'));
        add_action('wp_ajax_khm_clear_attribution_data', array($this, 'handle_clear_attribution_data'));
        
        // Load attribution manager (resides under src/)
        require_once dirname(__FILE__) . '/../src/Attribution/AttributionManager.php';
        $this->attribution_manager = new KHM_Advanced_Attribution_Manager();
    }
    
    /**
     * Add admin menu for attribution settings
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-main-menu', // Parent slug (assumes main plugin menu exists)
            'Attribution System',
            'Attribution',
            'manage_options',
            $this->page_slug,
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('khm_attribution_settings', 'khm_attribution_options');
        
        // Attribution Configuration Section
        add_settings_section(
            'khm_attribution_config',
            'Attribution Configuration',
            array($this, 'render_config_section'),
            'khm_attribution_settings'
        );
        
        // Attribution Window Setting
        add_settings_field(
            'attribution_window',
            'Attribution Window (Days)',
            array($this, 'render_attribution_window_field'),
            'khm_attribution_settings',
            'khm_attribution_config'
        );
        
        // Primary Attribution Method
        add_settings_field(
            'primary_attribution_method',
            'Primary Attribution Method',
            array($this, 'render_attribution_method_field'),
            'khm_attribution_settings',
            'khm_attribution_config'
        );
        
        // Fallback Methods
        add_settings_field(
            'fallback_methods',
            'Enabled Fallback Methods',
            array($this, 'render_fallback_methods_field'),
            'khm_attribution_settings',
            'khm_attribution_config'
        );
        
        // Performance Section
        add_settings_section(
            'khm_attribution_performance',
            'Performance Settings',
            array($this, 'render_performance_section'),
            'khm_attribution_settings'
        );
        
        // Async Tracking
        add_settings_field(
            'enable_async_tracking',
            'Enable Async Tracking',
            array($this, 'render_async_tracking_field'),
            'khm_attribution_settings',
            'khm_attribution_performance'
        );
        
        // Privacy Section
        add_settings_section(
            'khm_attribution_privacy',
            'Privacy & Compliance',
            array($this, 'render_privacy_section'),
            'khm_attribution_settings'
        );
        
        // Fingerprinting
        add_settings_field(
            'enable_fingerprinting',
            'Enable Device Fingerprinting',
            array($this, 'render_fingerprinting_field'),
            'khm_attribution_settings',
            'khm_attribution_privacy'
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        
        wp_enqueue_style('khm-attribution-admin', plugins_url('assets/css/attribution-admin.css', __FILE__), array(), '1.0.0');
        wp_enqueue_script('khm-attribution-admin', plugins_url('assets/js/attribution-admin.js', __FILE__), array('jquery', 'chart-js'), '1.0.0', true);
        
        // Localize script for AJAX
        wp_localize_script('khm-attribution-admin', 'khmAttribution', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_attribution_nonce')
        ));
    }
    
    /**
     * Render main admin page
     */
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
        ?>
        <div class="wrap">
            <h1>🎯 Advanced Attribution System</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=<?php echo $this->page_slug; ?>&tab=dashboard" 
                   class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    Dashboard
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=settings" 
                   class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    Settings
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=analytics" 
                   class="nav-tab <?php echo $active_tab == 'analytics' ? 'nav-tab-active' : ''; ?>">
                    Analytics
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=testing" 
                   class="nav-tab <?php echo $active_tab == 'testing' ? 'nav-tab-active' : ''; ?>">
                    Testing
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'analytics':
                        $this->render_analytics_tab();
                        break;
                    case 'testing':
                        $this->render_testing_tab();
                        break;
                    default:
                        $this->render_dashboard_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render dashboard tab
     */
    private function render_dashboard_tab() {
        global $wpdb;
        
        // Get attribution statistics
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        
        $total_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$table_events} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $total_conversions = $wpdb->get_var("SELECT COUNT(*) FROM {$table_conversions} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $attribution_rate = $total_clicks > 0 ? round(($total_conversions / $total_clicks) * 100, 2) : 0;
        
        $avg_confidence = $wpdb->get_var("SELECT AVG(attribution_confidence) FROM {$table_conversions} WHERE attribution_confidence IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $avg_confidence = $avg_confidence ? round($avg_confidence * 100, 1) : 0;
        
        ?>
        <div class="khm-dashboard">
            <div class="khm-stats-grid">
                <div class="khm-stat-card">
                    <h3>Attribution Performance (30 days)</h3>
                    <div class="khm-stat-row">
                        <div class="khm-stat-item">
                            <span class="khm-stat-number"><?php echo number_format($total_clicks); ?></span>
                            <span class="khm-stat-label">Tracked Clicks</span>
                        </div>
                        <div class="khm-stat-item">
                            <span class="khm-stat-number"><?php echo number_format($total_conversions); ?></span>
                            <span class="khm-stat-label">Attributed Conversions</span>
                        </div>
                        <div class="khm-stat-item">
                            <span class="khm-stat-number"><?php echo $attribution_rate; ?>%</span>
                            <span class="khm-stat-label">Attribution Rate</span>
                        </div>
                        <div class="khm-stat-item">
                            <span class="khm-stat-number"><?php echo $avg_confidence; ?>%</span>
                            <span class="khm-stat-label">Avg Confidence</span>
                        </div>
                    </div>
                </div>
                
                <div class="khm-stat-card">
                    <h3>System Health</h3>
                    <div class="khm-health-checks">
                        <?php $this->render_health_checks(); ?>
                    </div>
                </div>
            </div>
            
            <div class="khm-charts-grid">
                <div class="khm-chart-container">
                    <h3>Attribution Methods Distribution</h3>
                    <canvas id="attributionMethodsChart" width="400" height="200"></canvas>
                </div>
                
                <div class="khm-chart-container">
                    <h3>Daily Attribution Volume</h3>
                    <canvas id="dailyVolumeChart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <div class="khm-recent-activity">
                <h3>Recent Attribution Events</h3>
                <?php $this->render_recent_events(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize charts
            initializeAttributionCharts();
        });
        </script>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('khm_attribution_settings');
            do_settings_sections('khm_attribution_settings');
            submit_button('Save Attribution Settings');
            ?>
        </form>
        <?php
    }
    
    /**
     * Render analytics tab
     */
    private function render_analytics_tab() {
        global $wpdb;
        
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        
        // Get attribution method breakdown
        $attribution_methods = $wpdb->get_results("
            SELECT attribution_method, COUNT(*) as count, AVG(attribution_confidence) as avg_confidence
            FROM {$table_conversions} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY attribution_method
            ORDER BY count DESC
        ");
        
        // Get top affiliates by attribution
        $top_affiliates = $wpdb->get_results("
            SELECT affiliate_id, COUNT(*) as conversions, SUM(commission_amount) as total_commission
            FROM {$table_conversions}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY affiliate_id
            ORDER BY conversions DESC
            LIMIT 10
        ");
        
        ?>
        <div class="khm-analytics">
            <div class="khm-analytics-grid">
                <div class="khm-analytics-card">
                    <h3>Attribution Methods Performance</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Conversions</th>
                                <th>Avg Confidence</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_conversions = array_sum(array_column($attribution_methods, 'count'));
                            foreach ($attribution_methods as $method) {
                                $percentage = $total_conversions > 0 ? round(($method->count / $total_conversions) * 100, 1) : 0;
                                $confidence = round($method->avg_confidence * 100, 1);
                                ?>
                                <tr>
                                    <td><?php echo esc_html($method->attribution_method ?: 'Unknown'); ?></td>
                                    <td><?php echo number_format($method->count); ?></td>
                                    <td><?php echo $confidence; ?>%</td>
                                    <td><?php echo $percentage; ?>%</td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="khm-analytics-card">
                    <h3>Top Performing Affiliates</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Affiliate ID</th>
                                <th>Conversions</th>
                                <th>Total Commission</th>
                                <th>Avg Commission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_affiliates as $affiliate) {
                                $avg_commission = $affiliate->conversions > 0 ? $affiliate->total_commission / $affiliate->conversions : 0;
                                ?>
                                <tr>
                                    <td><?php echo esc_html($affiliate->affiliate_id); ?></td>
                                    <td><?php echo number_format($affiliate->conversions); ?></td>
                                    <td>$<?php echo number_format($affiliate->total_commission, 2); ?></td>
                                    <td>$<?php echo number_format($avg_commission, 2); ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render testing tab
     */
    private function render_testing_tab() {
        ?>
        <div class="khm-testing">
            <div class="khm-test-section">
                <h3>🧪 Attribution System Testing</h3>
                <p>Test various aspects of the attribution system to ensure everything is working correctly.</p>
                
                <div class="khm-test-actions">
                    <button class="button button-primary" onclick="runAttributionTest()">
                        Run Attribution Test Suite
                    </button>
                    <button class="button" onclick="testTrackingEndpoints()">
                        Test API Endpoints
                    </button>
                    <button class="button" onclick="simulateAttributionFlow()">
                        Simulate Attribution Flow
                    </button>
                </div>
                
                <div id="test-results" class="khm-test-results" style="display: none;">
                    <h4>Test Results</h4>
                    <div id="test-output"></div>
                </div>
            </div>
            
            <div class="khm-test-section">
                <h3>🔧 System Maintenance</h3>
                <p>Maintenance tools for the attribution system.</p>
                
                <div class="khm-maintenance-actions">
                    <button class="button" onclick="clearOldAttributionData()">
                        Clear Old Data (90+ days)
                    </button>
                    <button class="button" onclick="optimizeAttributionTables()">
                        Optimize Database Tables
                    </button>
                    <button class="button" onclick="exportAttributionData()">
                        Export Attribution Data
                    </button>
                </div>
            </div>
            
            <div class="khm-test-section">
                <h3>📊 Debug Information</h3>
                <div class="khm-debug-info">
                    <?php $this->render_debug_info(); ?>
                </div>
            </div>
        </div>
        
        <script>
        function runAttributionTest() {
            jQuery('#test-results').show();
            jQuery('#test-output').html('Running tests...');
            
            jQuery.post(ajaxurl, {
                action: 'khm_test_attribution',
                nonce: khmAttribution.nonce
            }, function(response) {
                jQuery('#test-output').html(response.data);
            });
        }
        
        function testTrackingEndpoints() {
            jQuery('#test-results').show();
            jQuery('#test-output').html('Testing API endpoints...');
            
            // Test click tracking endpoint
            jQuery.post('/wp-json/khm/v1/track/click', {
                affiliate_id: 'test_123',
                product_id: 'test_product',
                test_mode: true
            }, function(response) {
                jQuery('#test-output').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
            }).fail(function(xhr) {
                jQuery('#test-output').html('Endpoint test failed: ' + xhr.responseText);
            });
        }
        
        function simulateAttributionFlow() {
            jQuery('#test-results').show();
            jQuery('#test-output').html('Simulating attribution flow...');
            
            // Simulate complete attribution cycle
            var simulation = {
                click: { affiliate_id: 'sim_123', timestamp: new Date() },
                conversion: { order_id: 'sim_order_456', value: 100.00 }
            };
            
            jQuery('#test-output').html('<pre>Attribution Flow Simulation:\n' + JSON.stringify(simulation, null, 2) + '</pre>');
        }
        
        function clearOldAttributionData() {
            if (confirm('This will permanently delete attribution data older than 90 days. Continue?')) {
                jQuery.post(ajaxurl, {
                    action: 'khm_clear_attribution_data',
                    nonce: khmAttribution.nonce
                }, function(response) {
                    alert(response.data);
                });
            }
        }
        </script>
        <?php
    }
    
    /**
     * Render health checks
     */
    private function render_health_checks() {
        $checks = array(
            'Database Tables' => $this->check_database_tables(),
            'REST API Endpoints' => $this->check_rest_endpoints(),
            'JavaScript Loading' => $this->check_javascript_loading(),
            'Attribution Manager' => $this->check_attribution_manager()
        );
        
        foreach ($checks as $check_name => $status) {
            $icon = $status ? '✅' : '❌';
            $class = $status ? 'healthy' : 'unhealthy';
            echo "<div class='khm-health-check {$class}'>{$icon} {$check_name}</div>";
        }
    }
    
    /**
     * Render recent events
     */
    private function render_recent_events() {
        global $wpdb;
        
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $recent_events = $wpdb->get_results("
            SELECT click_id, affiliate_id, utm_source, utm_medium, attribution_method, created_at 
            FROM {$table_events} 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        
        if (empty($recent_events)) {
            echo "<p>No recent attribution events found.</p>";
            return;
        }
        
        echo "<table class='wp-list-table widefat fixed striped'>";
        echo "<thead><tr><th>Click ID</th><th>Affiliate</th><th>Source</th><th>Medium</th><th>Method</th><th>Time</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($recent_events as $event) {
            $time_ago = human_time_diff(strtotime($event->created_at), current_time('timestamp')) . ' ago';
            echo "<tr>";
            echo "<td>" . esc_html(substr($event->click_id, 0, 12)) . "...</td>";
            echo "<td>" . esc_html($event->affiliate_id) . "</td>";
            echo "<td>" . esc_html($event->utm_source ?: '-') . "</td>";
            echo "<td>" . esc_html($event->utm_medium ?: '-') . "</td>";
            echo "<td>" . esc_html($event->attribution_method ?: '-') . "</td>";
            echo "<td>" . esc_html($time_ago) . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
    }
    
    /**
     * Render debug information
     */
    private function render_debug_info() {
        $debug_info = array(
            'PHP Version' => PHP_VERSION,
            'WordPress Version' => get_bloginfo('version'),
            'Attribution Manager Class' => class_exists('KHM_Advanced_Attribution_Manager') ? 'Loaded' : 'Not Found',
            'Database Tables' => $this->check_database_tables() ? 'Created' : 'Missing',
            'REST API' => rest_url('khm/v1/'),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . 's'
        );
        
        echo "<table class='wp-list-table widefat fixed striped'>";
        foreach ($debug_info as $key => $value) {
            echo "<tr><td><strong>{$key}</strong></td><td>{$value}</td></tr>";
        }
        echo "</table>";
    }
    
    /**
     * Check if database tables exist
     */
    private function check_database_tables() {
        global $wpdb;
        
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        
        $events_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_events}'") === $table_events;
        $conversions_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_conversions}'") === $table_conversions;
        
        return $events_exists && $conversions_exists;
    }
    
    /**
     * Check REST endpoints
     */
    private function check_rest_endpoints() {
        return function_exists('rest_url') && rest_url('khm/v1/track/click');
    }
    
    /**
     * Check JavaScript loading
     */
    private function check_javascript_loading() {
        return file_exists(dirname(__FILE__) . '/../assets/js/attribution-tracker.js');
    }
    
    /**
     * Check attribution manager
     */
    private function check_attribution_manager() {
        return class_exists('KHM_Advanced_Attribution_Manager');
    }
    
    /**
     * Handle attribution test AJAX request
     */
    public function handle_attribution_test() {
        check_ajax_referer('khm_attribution_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Run test suite and capture output
        ob_start();
        
        if (file_exists(dirname(__FILE__) . '/../tests/test-attribution-system.php')) {
            include dirname(__FILE__) . '/../tests/test-attribution-system.php';
            $test_suite = new KHM_Attribution_Test_Suite();
            $test_suite->run_all_tests();
        } else {
            echo "Test suite file not found.";
        }
        
        $output = ob_get_clean();
        
        wp_send_json_success($output);
    }
    
    /**
     * Handle clear attribution data AJAX request
     */
    public function handle_clear_attribution_data() {
        check_ajax_referer('khm_attribution_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        
        $events_deleted = $wpdb->query("DELETE FROM {$table_events} WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $conversions_deleted = $wpdb->query("DELETE FROM {$table_conversions} WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        
        $message = "Cleaned up old attribution data. Deleted {$events_deleted} events and {$conversions_deleted} conversions.";
        
        wp_send_json_success($message);
    }
    
    // Settings field render methods
    public function render_config_section() {
        echo "<p>Configure how the attribution system tracks and attributes conversions.</p>";
    }
    
    public function render_attribution_window_field() {
        $options = get_option('khm_attribution_options', array());
        $value = isset($options['attribution_window']) ? $options['attribution_window'] : 30;
        echo "<input type='number' name='khm_attribution_options[attribution_window]' value='{$value}' min='1' max='365' />";
        echo "<p class='description'>Number of days to look back for attribution (1-365)</p>";
    }
    
    public function render_attribution_method_field() {
        $options = get_option('khm_attribution_options', array());
        $value = isset($options['primary_attribution_method']) ? $options['primary_attribution_method'] : 'last_touch';
        
        $methods = array(
            'first_touch' => 'First Touch',
            'last_touch' => 'Last Touch',
            'multi_touch' => 'Multi Touch'
        );
        
        echo "<select name='khm_attribution_options[primary_attribution_method]'>";
        foreach ($methods as $method_key => $method_name) {
            $selected = selected($value, $method_key, false);
            echo "<option value='{$method_key}' {$selected}>{$method_name}</option>";
        }
        echo "</select>";
    }
    
    public function render_fallback_methods_field() {
        $options = get_option('khm_attribution_options', array());
        $enabled_methods = isset($options['fallback_methods']) ? $options['fallback_methods'] : array();
        
        $methods = array(
            'server_side_event' => 'Server-side Events',
            'first_party_cookie' => 'First-party Cookies',
            'url_parameter' => 'URL Parameters',
            'session_storage' => 'Session Storage',
            'fingerprint_match' => 'Fingerprint Matching'
        );
        
        foreach ($methods as $method_key => $method_name) {
            $checked = in_array($method_key, $enabled_methods) ? 'checked' : '';
            echo "<label><input type='checkbox' name='khm_attribution_options[fallback_methods][]' value='{$method_key}' {$checked} /> {$method_name}</label><br>";
        }
    }
    
    public function render_performance_section() {
        echo "<p>Optimize attribution system performance for your traffic volume.</p>";
    }
    
    public function render_async_tracking_field() {
        $options = get_option('khm_attribution_options', array());
        $value = isset($options['enable_async_tracking']) ? $options['enable_async_tracking'] : '1';
        $checked = checked($value, '1', false);
        
        echo "<input type='checkbox' name='khm_attribution_options[enable_async_tracking]' value='1' {$checked} />";
        echo "<p class='description'>Enable non-blocking attribution tracking for better page performance</p>";
    }
    
    public function render_privacy_section() {
        echo "<p>Configure privacy and compliance settings for attribution tracking.</p>";
    }
    
    public function render_fingerprinting_field() {
        $options = get_option('khm_attribution_options', array());
        $value = isset($options['enable_fingerprinting']) ? $options['enable_fingerprinting'] : '0';
        $checked = checked($value, '1', false);
        
        echo "<input type='checkbox' name='khm_attribution_options[enable_fingerprinting]' value='1' {$checked} />";
        echo "<p class='description'>Enable device fingerprinting as a last-resort attribution method (consider privacy implications)</p>";
    }
}

// Initialize admin interface
if (is_admin()) {
    new KHM_Attribution_Admin();
}
?>
