<?php
namespace KH\Attribution\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AttributionAdmin
 * 
 * Handles the admin interface for the attribution system.
 */
class AttributionAdmin {
    private $manager;
    private $page_slug = 'khm-attribution';

    public function __construct($manager) {
        $this->manager = $manager;
    }

    /**
     * Register hooks
     */
    public function register() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_khm_test_attribution', array($this, 'handle_attribution_test'));
        add_action('wp_ajax_khm_clear_attribution_data', array($this, 'handle_clear_attribution_data'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        global $admin_page_hooks;
        $parent_slug = isset($admin_page_hooks['khm-main-menu']) ? 'khm-main-menu' : null;

        if ($parent_slug) {
            add_submenu_page(
                $parent_slug,
                'Attribution System',
                'Attribution',
                'manage_options',
                $this->page_slug,
                array($this, 'render_admin_page')
            );
        } else {
            add_menu_page(
                'Attribution System',
                'Attribution',
                'manage_options',
                $this->page_slug,
                array($this, 'render_admin_page'),
                'dashicons-chart-line',
                30
            );
        }
    }

    /**
     * Register settings
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

        add_settings_field(
            'attribution_window',
            'Attribution Window (Days)',
            array($this, 'render_attribution_window_field'),
            'khm_attribution_settings',
            'khm_attribution_config'
        );
        
        add_settings_field(
            'primary_attribution_method',
            'Primary Attribution Method',
            array($this, 'render_attribution_method_field'),
            'khm_attribution_settings',
            'khm_attribution_config'
        );
        
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
        
        add_settings_field(
            'enable_fingerprinting',
            'Enable Device Fingerprinting',
            array($this, 'render_fingerprinting_field'),
            'khm_attribution_settings',
            'khm_attribution_privacy'
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        
        $plugin_url = plugin_dir_url(dirname(__FILE__, 2));
        wp_enqueue_style('khm-attribution-admin', $plugin_url . 'assets/css/attribution-admin.css', array(), '1.0.0');
        wp_enqueue_script('khm-attribution-admin', $plugin_url . 'assets/js/attribution-admin.js', array('jquery', 'chart-js'), '1.0.0', true);
        
        wp_localize_script('khm-attribution-admin', 'khmAttribution', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_attribution_nonce')
        ));
    }

    public function render_attribution_window_field() {
        $options = get_option('khm_attribution_options', array());
        $value = isset($options['attribution_window']) ? $options['attribution_window'] : 30;
        echo "<input type='number' name='khm_attribution_options[attribution_window]' value='" . esc_attr($value) . "' min='1' max='365' />";
        echo "<p class='description'>Number of days to look back for attribution (1-365)</p>";
    }

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
        
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        
        $total_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$table_events} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $total_conversions = $wpdb->get_var("SELECT COUNT(*) FROM {$table_conversions} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $attribution_rate = $total_clicks > 0 ? round(($total_conversions / $total_clicks) * 100, 2) : 0;
        
        $avg_confidence = $wpdb->get_var("SELECT AVG(confidence_score) FROM {$table_conversions} WHERE confidence_score IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
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
            if (typeof initializeAttributionCharts === 'function') {
                initializeAttributionCharts();
            }
        });
        </script>
        <?php
    }

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

    private function render_analytics_tab() {
        global $wpdb;
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        
        $attribution_methods = $wpdb->get_results("
            SELECT attribution_method, COUNT(*) as count, AVG(confidence_score) as avg_confidence
            FROM {$table_conversions} 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY attribution_method
            ORDER BY count DESC
        ");
        
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_affiliates as $affiliate) { ?>
                                <tr>
                                    <td><?php echo esc_html($affiliate->affiliate_id); ?></td>
                                    <td><?php echo number_format($affiliate->conversions); ?></td>
                                    <td>$<?php echo number_format($affiliate->total_commission, 2); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_testing_tab() {
        ?>
        <div class="khm-testing">
            <div class="khm-test-section">
                <h3>🧪 Attribution System Testing</h3>
                <div class="khm-test-actions">
                    <button class="button button-primary" onclick="runAttributionTest()">Run Test Suite</button>
                </div>
                <div id="test-results" class="khm-test-results" style="display: none; margin-top: 15px; padding: 10px; background: #f0f0f0;">
                    <h4>Test Results</h4>
                    <div id="test-output"></div>
                </div>
            </div>
            <div class="khm-test-section" style="margin-top: 20px;">
                <h3>🔧 System Maintenance</h3>
                <button class="button" onclick="clearOldAttributionData()">Clear Old Data (90+ days)</button>
            </div>
        </div>
        <?php
    }

    private function render_health_checks() {
        $checks = array(
            'Database Tables' => $this->check_database_tables(),
            'REST API' => (function_exists('rest_url')),
            'Attribution Manager' => ($this->manager !== null)
        );
        
        foreach ($checks as $name => $status) {
            echo "<div>" . ($status ? '✅' : '❌') . " {$name}</div>";
        }
    }

    private function render_recent_events() {
        global $wpdb;
        $table_events = $wpdb->prefix . 'khm_attribution_events';
        $recent = $wpdb->get_results("SELECT click_id, affiliate_id, utm_source, created_at FROM {$table_events} ORDER BY created_at DESC LIMIT 5");
        
        if ($recent) {
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>ID</th><th>Affiliate</th><th>Source</th><th>Time</th></tr></thead><tbody>';
            foreach ($recent as $r) {
                echo "<tr><td>".esc_html(substr($r->click_id,0,8))."...</td><td>".esc_html($r->affiliate_id)."</td><td>".esc_html($r->utm_source)."</td><td>".esc_html($r->created_at)."</td></tr>";
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No recent events.</p>';
        }
    }

    private function check_database_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_attribution_events';
        return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    }

    // Settings Field Renderers
    public function render_config_section() { echo "<p>Core tracking settings.</p>"; }
    public function render_performance_section() { echo "<p>Speed and batching settings.</p>"; }
    public function render_privacy_section() { echo "<p>Compliance settings.</p>"; }

    public function render_attribution_method_field() {
        $options = get_option('khm_attribution_options', array());
        $value = $options['primary_attribution_method'] ?? 'last_touch';
        $methods = array('first_touch' => 'First Touch', 'last_touch' => 'Last Touch');
        echo "<select name='khm_attribution_options[primary_attribution_method]'>";
        foreach ($methods as $k => $v) {
            echo "<option value='{$k}' ".selected($value, $k, false).">{$v}</option>";
        }
        echo "</select>";
    }

    public function render_fallback_methods_field() {
        $options = get_option('khm_attribution_options', array());
        $enabled = $options['fallback_methods'] ?? array();
        $methods = array('server_side_event' => 'Server-side', 'first_party_cookie' => 'Cookie', 'fingerprint_match' => 'Fingerprint');
        foreach ($methods as $k => $v) {
            $checked = in_array($k, $enabled) ? 'checked' : '';
            echo "<label><input type='checkbox' name='khm_attribution_options[fallback_methods][]' value='{$k}' {$checked} /> {$v}</label><br>";
        }
    }

    public function render_async_tracking_field() {
        $options = get_option('khm_attribution_options', array());
        $value = $options['enable_async_tracking'] ?? '1';
        echo "<input type='checkbox' name='khm_attribution_options[enable_async_tracking]' value='1' ".checked($value, '1', false)." />";
    }

    public function render_fingerprinting_field() {
        $options = get_option('khm_attribution_options', array());
        $value = $options['enable_fingerprinting'] ?? '0';
        echo "<input type='checkbox' name='khm_attribution_options[enable_fingerprinting]' value='1' ".checked($value, '1', false)." />";
    }
