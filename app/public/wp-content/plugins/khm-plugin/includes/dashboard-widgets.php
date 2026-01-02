<?php
/**
 * KHM Dashboard Widgets System
 * 
 * Modular widget system for the enhanced dashboard
 * Each widget is self-contained and provides specific functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class KHM_Dashboard_Widget {
    
    protected $id;
    protected $title;
    protected $description;
    protected $cache_key;
    protected $cache_duration = 300; // 5 minutes
    
    public function __construct($id, $title, $description = '') {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->cache_key = 'khm_widget_' . $id;
    }
    
    /**
     * Render the widget
     */
    public function render($args = array()) {
        $data = $this->get_cached_data();
        
        if ($data === false) {
            $data = $this->get_data($args);
            $this->cache_data($data);
        }
        
        $this->render_widget($data, $args);
    }
    
    /**
     * Get widget data - must be implemented by child classes
     */
    abstract protected function get_data($args = array());
    
    /**
     * Render widget HTML - must be implemented by child classes
     */
    abstract protected function render_widget($data, $args = array());
    
    /**
     * Get cached data
     */
    protected function get_cached_data() {
        return get_transient($this->cache_key);
    }
    
    /**
     * Cache data
     */
    protected function cache_data($data) {
        set_transient($this->cache_key, $data, $this->cache_duration);
    }
    
    /**
     * Clear widget cache
     */
    public function clear_cache() {
        delete_transient($this->cache_key);
    }
}

/**
 * Revenue Overview Widget
 */
class KHM_Revenue_Widget extends KHM_Dashboard_Widget {
    
    public function __construct() {
        parent::__construct('revenue_overview', 'Revenue Overview', 'Total revenue and trends');
    }
    
    protected function get_data($args = array()) {
        // Mock data - in production, query actual revenue data
        return array(
            'total_revenue' => 25420.50,
            'monthly_revenue' => 8450.25,
            'weekly_revenue' => 2180.75,
            'daily_revenue' => 312.50,
            'revenue_trend' => array(
                array('date' => '2024-11-01', 'amount' => 1250.00),
                array('date' => '2024-11-02', 'amount' => 1380.50),
                array('date' => '2024-11-03', 'amount' => 950.25),
                array('date' => '2024-11-04', 'amount' => 1680.75),
                array('date' => '2024-11-05', 'amount' => 1420.00)
            ),
            'top_revenue_sources' => array(
                array('source' => 'Premium Memberships', 'amount' => 12500.00, 'percentage' => 49.2),
                array('source' => 'Affiliate Commissions', 'amount' => 8750.25, 'percentage' => 34.4),
                array('source' => 'Course Sales', 'amount' => 4170.25, 'percentage' => 16.4)
            )
        );
    }
    
    protected function render_widget($data, $args = array()) {
        echo '<div class="khm-revenue-widget">';
        echo '<div class="khm-revenue-summary">';
        
        // Revenue cards
        $periods = array(
            array('label' => 'Total Revenue', 'value' => $data['total_revenue'], 'class' => 'total'),
            array('label' => 'This Month', 'value' => $data['monthly_revenue'], 'class' => 'monthly'),
            array('label' => 'This Week', 'value' => $data['weekly_revenue'], 'class' => 'weekly'),
            array('label' => 'Today', 'value' => $data['daily_revenue'], 'class' => 'daily')
        );
        
        foreach ($periods as $period) {
            echo '<div class="khm-revenue-card khm-revenue-' . $period['class'] . '">';
            echo '<div class="khm-revenue-label">' . esc_html($period['label']) . '</div>';
            echo '<div class="khm-revenue-value">$' . number_format($period['value'], 2) . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Revenue sources
        echo '<div class="khm-revenue-sources">';
        echo '<h4>Revenue Sources</h4>';
        foreach ($data['top_revenue_sources'] as $source) {
            echo '<div class="khm-revenue-source">';
            echo '<div class="khm-source-info">';
            echo '<span class="khm-source-name">' . esc_html($source['source']) . '</span>';
            echo '<span class="khm-source-amount">$' . number_format($source['amount'], 2) . '</span>';
            echo '</div>';
            echo '<div class="khm-source-bar">';
            echo '<div class="khm-source-fill" style="width: ' . $source['percentage'] . '%"></div>';
            echo '</div>';
            echo '<div class="khm-source-percentage">' . $source['percentage'] . '%</div>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
}

/**
 * Affiliate Performance Widget
 */
class KHM_Affiliate_Performance_Widget extends KHM_Dashboard_Widget {
    
    public function __construct() {
        parent::__construct('affiliate_performance', 'Affiliate Performance', 'Top performing affiliates');
    }
    
    protected function get_data($args = array()) {
        // Mock data - in production, query actual affiliate data
        return array(
            'total_affiliates' => 147,
            'active_affiliates' => 89,
            'new_affiliates' => 8,
            'top_performers' => array(
                array('id' => 1, 'name' => 'John Smith', 'revenue' => 1250.00, 'conversions' => 25, 'clicks' => 850),
                array('id' => 2, 'name' => 'Sarah Johnson', 'revenue' => 980.50, 'conversions' => 18, 'clicks' => 720),
                array('id' => 3, 'name' => 'Mike Davis', 'revenue' => 845.25, 'conversions' => 15, 'clicks' => 650),
                array('id' => 4, 'name' => 'Lisa Wilson', 'revenue' => 720.00, 'conversions' => 12, 'clicks' => 480),
                array('id' => 5, 'name' => 'David Brown', 'revenue' => 650.75, 'conversions' => 10, 'clicks' => 420)
            ),
            'performance_metrics' => array(
                'avg_conversion_rate' => 3.2,
                'avg_commission' => 48.75,
                'total_clicks' => 15673,
                'total_conversions' => 502
            )
        );
    }
    
    protected function render_widget($data, $args = array()) {
        echo '<div class="khm-affiliate-widget">';
        
        // Summary stats
        echo '<div class="khm-affiliate-summary">';
        echo '<div class="khm-affiliate-stat">';
        echo '<div class="khm-stat-value">' . number_format($data['total_affiliates']) . '</div>';
        echo '<div class="khm-stat-label">Total Affiliates</div>';
        echo '</div>';
        echo '<div class="khm-affiliate-stat">';
        echo '<div class="khm-stat-value">' . number_format($data['active_affiliates']) . '</div>';
        echo '<div class="khm-stat-label">Active This Month</div>';
        echo '</div>';
        echo '<div class="khm-affiliate-stat">';
        echo '<div class="khm-stat-value">' . $data['performance_metrics']['avg_conversion_rate'] . '%</div>';
        echo '<div class="khm-stat-label">Avg Conversion Rate</div>';
        echo '</div>';
        echo '</div>';
        
        // Top performers
        echo '<div class="khm-top-affiliates">';
        echo '<h4>Top Performers This Month</h4>';
        
        foreach ($data['top_performers'] as $index => $performer) {
            $rank = $index + 1;
            $medal = $rank <= 3 ? ['🥇', '🥈', '🥉'][$rank - 1] : $rank;
            
            echo '<div class="khm-affiliate-performer">';
            echo '<div class="khm-performer-rank">' . $medal . '</div>';
            echo '<div class="khm-performer-details">';
            echo '<div class="khm-performer-name">' . esc_html($performer['name']) . '</div>';
            echo '<div class="khm-performer-metrics">';
            echo '<span class="revenue">$' . number_format($performer['revenue'], 2) . '</span>';
            echo '<span class="conversions">' . $performer['conversions'] . ' conversions</span>';
            echo '<span class="clicks">' . number_format($performer['clicks']) . ' clicks</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="khm-performer-ctr">';
            $ctr = $performer['clicks'] > 0 ? ($performer['conversions'] / $performer['clicks']) * 100 : 0;
            echo number_format($ctr, 1) . '% CTR';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
}

/**
 * System Health Widget
 */
class KHM_System_Health_Widget extends KHM_Dashboard_Widget {
    
    public function __construct() {
        parent::__construct('system_health', 'System Health', 'Monitor system performance and status');
    }
    
    protected function get_data($args = array()) {
        global $wpdb;
        
        // Perform actual system checks
        $health_checks = array();
        
        // Database health
        $db_status = $wpdb->get_var("SELECT 1");
        $health_checks['database'] = array(
            'status' => $db_status ? 'healthy' : 'error',
            'message' => $db_status ? 'Database connection active' : 'Database connection failed',
            'last_check' => time()
        );
        
        // Table existence
        $required_tables = array(
            $wpdb->prefix . 'khm_affiliate_codes',
            $wpdb->prefix . 'khm_affiliate_clicks',
            $wpdb->prefix . 'khm_creatives',
            $wpdb->prefix . 'khm_user_credits'
        );
        
        $missing_tables = array();
        foreach ($required_tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        $health_checks['tables'] = array(
            'status' => empty($missing_tables) ? 'healthy' : 'error',
            'message' => empty($missing_tables) ? 'All required tables exist' : 'Missing tables: ' . implode(', ', $missing_tables),
            'last_check' => time()
        );
        
        // Performance check
        $start_time = microtime(true);
        $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
        $query_time = microtime(true) - $start_time;
        
        $health_checks['performance'] = array(
            'status' => $query_time < 1.0 ? 'healthy' : ($query_time < 3.0 ? 'warning' : 'error'),
            'message' => 'Average query time: ' . round($query_time * 1000, 2) . 'ms',
            'last_check' => time()
        );
        
        // WordPress version check
        $wp_version = get_bloginfo('version');
        $health_checks['wordpress'] = array(
            'status' => version_compare($wp_version, '5.0', '>=') ? 'healthy' : 'warning',
            'message' => 'WordPress version: ' . $wp_version,
            'last_check' => time()
        );
        
        return array(
            'overall_status' => $this->calculate_overall_health($health_checks),
            'checks' => $health_checks,
            'last_updated' => time()
        );
    }
    
    protected function render_widget($data, $args = array()) {
        echo '<div class="khm-health-widget">';
        
        // Overall status
        $status_class = 'khm-health-' . $data['overall_status'];
        $status_icon = $data['overall_status'] === 'healthy' ? '✅' : ($data['overall_status'] === 'warning' ? '⚠️' : '❌');
        
        echo '<div class="khm-health-overall ' . $status_class . '">';
        echo '<div class="khm-health-icon">' . $status_icon . '</div>';
        echo '<div class="khm-health-status">';
        echo '<div class="khm-health-label">System Status</div>';
        echo '<div class="khm-health-value">' . ucfirst($data['overall_status']) . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Individual checks
        echo '<div class="khm-health-checks">';
        foreach ($data['checks'] as $check_name => $check) {
            $check_class = 'khm-check-' . $check['status'];
            $check_icon = $check['status'] === 'healthy' ? '✅' : ($check['status'] === 'warning' ? '⚠️' : '❌');
            
            echo '<div class="khm-health-check ' . $check_class . '">';
            echo '<div class="khm-check-icon">' . $check_icon . '</div>';
            echo '<div class="khm-check-info">';
            echo '<div class="khm-check-name">' . ucwords(str_replace('_', ' ', $check_name)) . '</div>';
            echo '<div class="khm-check-message">' . esc_html($check['message']) . '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '<div class="khm-health-updated">';
        echo 'Last updated: ' . human_time_diff($data['last_updated']) . ' ago';
        echo '</div>';
        
        echo '</div>';
    }
    
    private function calculate_overall_health($checks) {
        $statuses = array_column($checks, 'status');
        
        if (in_array('error', $statuses)) {
            return 'error';
        } elseif (in_array('warning', $statuses)) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }
}

/**
 * Activity Feed Widget
 */
class KHM_Activity_Feed_Widget extends KHM_Dashboard_Widget {
    
    public function __construct() {
        parent::__construct('activity_feed', 'Recent Activity', 'Latest system activity and events');
    }
    
    protected function get_data($args = array()) {
        // Mock data - in production, query actual activity logs
        return array(
            'activities' => array(
                array(
                    'type' => 'conversion',
                    'title' => 'New Conversion',
                    'description' => 'John Smith generated $125.00 commission',
                    'timestamp' => time() - 300,
                    'meta' => array('affiliate' => 'John Smith', 'amount' => 125.00)
                ),
                array(
                    'type' => 'signup',
                    'title' => 'New Affiliate',
                    'description' => 'Sarah Johnson joined as an affiliate',
                    'timestamp' => time() - 1800,
                    'meta' => array('affiliate' => 'Sarah Johnson')
                ),
                array(
                    'type' => 'creative',
                    'title' => 'Creative Performance',
                    'description' => 'Premium Banner reached 1000 views',
                    'timestamp' => time() - 3600,
                    'meta' => array('creative' => 'Premium Banner', 'views' => 1000)
                ),
                array(
                    'type' => 'payout',
                    'title' => 'Payout Processed',
                    'description' => 'Mike Davis received $500.00 payout',
                    'timestamp' => time() - 7200,
                    'meta' => array('affiliate' => 'Mike Davis', 'amount' => 500.00)
                ),
                array(
                    'type' => 'system',
                    'title' => 'System Update',
                    'description' => 'Affiliate tracking system updated',
                    'timestamp' => time() - 14400,
                    'meta' => array()
                )
            ),
            'total_activities' => 127,
            'activities_today' => 12
        );
    }
    
    protected function render_widget($data, $args = array()) {
        echo '<div class="khm-activity-widget">';
        
        // Activity summary
        echo '<div class="khm-activity-summary">';
        echo '<div class="khm-activity-stat">';
        echo '<span class="khm-activity-count">' . $data['activities_today'] . '</span>';
        echo '<span class="khm-activity-label">activities today</span>';
        echo '</div>';
        echo '<a href="#" class="khm-activity-view-all">View All Activity</a>';
        echo '</div>';
        
        // Recent activities
        echo '<div class="khm-activity-list">';
        foreach ($data['activities'] as $activity) {
            $icon = $this->get_activity_icon($activity['type']);
            $time_ago = human_time_diff($activity['timestamp']) . ' ago';
            
            echo '<div class="khm-activity-item">';
            echo '<div class="khm-activity-icon">' . $icon . '</div>';
            echo '<div class="khm-activity-content">';
            echo '<div class="khm-activity-title">' . esc_html($activity['title']) . '</div>';
            echo '<div class="khm-activity-description">' . esc_html($activity['description']) . '</div>';
            echo '<div class="khm-activity-time">' . $time_ago . '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    private function get_activity_icon($type) {
        $icons = array(
            'conversion' => '💰',
            'signup' => '👤',
            'creative' => '🎨',
            'payout' => '💸',
            'system' => '⚙️',
            'click' => '🖱️'
        );
        
        return $icons[$type] ?? '📊';
    }
}

/**
 * Widget Manager
 */
class KHM_Dashboard_Widget_Manager {
    
    private $widgets = array();
    
    public function __construct() {
        $this->register_default_widgets();
    }
    
    /**
     * Register a widget
     */
    public function register_widget($widget) {
        if ($widget instanceof KHM_Dashboard_Widget) {
            $this->widgets[$widget->id] = $widget;
        }
    }
    
    /**
     * Get a widget by ID
     */
    public function get_widget($id) {
        return isset($this->widgets[$id]) ? $this->widgets[$id] : null;
    }
    
    /**
     * Get all widgets
     */
    public function get_widgets() {
        return $this->widgets;
    }
    
    /**
     * Render a widget
     */
    public function render_widget($id, $args = array()) {
        $widget = $this->get_widget($id);
        if ($widget) {
            $widget->render($args);
        }
    }
    
    /**
     * Clear all widget caches
     */
    public function clear_all_caches() {
        foreach ($this->widgets as $widget) {
            $widget->clear_cache();
        }
    }
    
    /**
     * Register default widgets
     */
    private function register_default_widgets() {
        $this->register_widget(new KHM_Revenue_Widget());
        $this->register_widget(new KHM_Affiliate_Performance_Widget());
        $this->register_widget(new KHM_System_Health_Widget());
        $this->register_widget(new KHM_Activity_Feed_Widget());
    }
}

// Initialize widget manager
global $khm_widget_manager;
$khm_widget_manager = new KHM_Dashboard_Widget_Manager();