<?php
/**
 * Widget Manager - Dashboard widget management and rendering
 * 
 * Manages dashboard widgets, handles widget configuration,
 * and provides widget rendering functionality for the SEO dashboard.
 * 
 * @package KHM_SEO\Dashboard\Widgets
 * @since 2.1.0
 */

namespace KHM_SEO\Dashboard\Widgets;

/**
 * Widget Manager Class
 */
class WidgetManager {
    /**
     * @var array Registered widgets
     */
    private $widgets;

    /**
     * @var array Widget configuration
     */
    private $widget_config;

    /**
     * @var array User widget preferences
     */
    private $user_preferences;

    /**
     * Constructor
     */
    public function __construct() {
        $this->widgets = [];
        $this->init_widget_config();
        $this->load_user_preferences();
        $this->register_default_widgets();
    }

    /**
     * Initialize widget configuration
     */
    private function init_widget_config() {
        $this->widget_config = [
            'default_widgets' => [
                'overview_stats' => [
                    'title' => 'Overview Statistics',
                    'description' => 'Key SEO metrics and performance indicators',
                    'category' => 'overview',
                    'size' => 'large',
                    'refresh_interval' => 300, // 5 minutes
                    'cacheable' => true
                ],
                'recent_analysis' => [
                    'title' => 'Recent Analyses',
                    'description' => 'Latest content analysis results',
                    'category' => 'content',
                    'size' => 'medium',
                    'refresh_interval' => 60, // 1 minute
                    'cacheable' => true
                ],
                'performance_chart' => [
                    'title' => 'Performance Trends',
                    'description' => 'SEO performance over time',
                    'category' => 'analytics',
                    'size' => 'large',
                    'refresh_interval' => 600, // 10 minutes
                    'cacheable' => true
                ],
                'top_issues' => [
                    'title' => 'Critical Issues',
                    'description' => 'Issues requiring immediate attention',
                    'category' => 'technical',
                    'size' => 'medium',
                    'refresh_interval' => 300,
                    'cacheable' => true
                ],
                'content_opportunities' => [
                    'title' => 'Content Opportunities',
                    'description' => 'Content optimization suggestions',
                    'category' => 'content',
                    'size' => 'medium',
                    'refresh_interval' => 600,
                    'cacheable' => true
                ],
                'technical_health' => [
                    'title' => 'Technical Health',
                    'description' => 'Site technical SEO status',
                    'category' => 'technical',
                    'size' => 'medium',
                    'refresh_interval' => 1800, // 30 minutes
                    'cacheable' => true
                ]
            ],
            'widget_sizes' => [
                'small' => ['width' => '25%', 'min_width' => '280px'],
                'medium' => ['width' => '50%', 'min_width' => '400px'],
                'large' => ['width' => '100%', 'min_width' => '600px'],
                'extra_large' => ['width' => '100%', 'min_width' => '800px']
            ],
            'categories' => [
                'overview' => 'Overview',
                'analytics' => 'Analytics',
                'content' => 'Content',
                'technical' => 'Technical',
                'performance' => 'Performance'
            ]
        ];
    }

    /**
     * Load user widget preferences
     */
    private function load_user_preferences() {
        $user_id = get_current_user_id();
        $preferences = get_user_meta($user_id, 'khm_seo_dashboard_preferences', true);
        
        $this->user_preferences = wp_parse_args($preferences, [
            'widget_order' => [],
            'hidden_widgets' => [],
            'widget_settings' => [],
            'layout' => 'default'
        ]);
    }

    /**
     * Register default dashboard widgets
     */
    private function register_default_widgets() {
        foreach ($this->widget_config['default_widgets'] as $widget_id => $config) {
            $this->register_widget($widget_id, $config);
        }
    }

    /**
     * Register a new widget
     *
     * @param string $widget_id Unique widget identifier
     * @param array $config Widget configuration
     * @return bool Success status
     */
    public function register_widget($widget_id, $config) {
        if (isset($this->widgets[$widget_id])) {
            return false; // Widget already exists
        }

        $default_config = [
            'title' => 'Widget Title',
            'description' => 'Widget description',
            'category' => 'overview',
            'size' => 'medium',
            'refresh_interval' => 300,
            'cacheable' => true,
            'callback' => null,
            'data_source' => null,
            'permissions' => 'manage_options',
            'dependencies' => []
        ];

        $this->widgets[$widget_id] = array_merge($default_config, $config);
        $this->widgets[$widget_id]['id'] = $widget_id;

        return true;
    }

    /**
     * Unregister a widget
     *
     * @param string $widget_id Widget identifier
     * @return bool Success status
     */
    public function unregister_widget($widget_id) {
        if (isset($this->widgets[$widget_id])) {
            unset($this->widgets[$widget_id]);
            return true;
        }
        return false;
    }

    /**
     * Get widget configuration
     *
     * @param string $widget_id Widget identifier
     * @return array|null Widget configuration
     */
    public function get_widget_config($widget_id) {
        return $this->widgets[$widget_id] ?? null;
    }

    /**
     * Get all registered widgets
     *
     * @param string $category Optional category filter
     * @return array Registered widgets
     */
    public function get_widgets($category = null) {
        if ($category === null) {
            return $this->widgets;
        }

        return array_filter($this->widgets, function($widget) use ($category) {
            return $widget['category'] === $category;
        });
    }

    /**
     * Render widget HTML
     *
     * @param string $widget_id Widget identifier
     * @param array $args Additional arguments
     * @return string Widget HTML
     */
    public function render_widget($widget_id, $args = []) {
        $widget = $this->get_widget_config($widget_id);
        
        if (!$widget) {
            return $this->render_error_widget('Widget not found: ' . $widget_id);
        }

        // Check permissions
        if (!current_user_can($widget['permissions'])) {
            return $this->render_error_widget('Insufficient permissions');
        }

        // Check if widget is hidden by user
        if (in_array($widget_id, $this->user_preferences['hidden_widgets'])) {
            return '';
        }

        // Get widget data
        $widget_data = $this->get_widget_data($widget_id, $args);
        
        if (is_wp_error($widget_data)) {
            return $this->render_error_widget($widget_data->get_error_message());
        }

        // Render widget container
        $html = $this->start_widget_container($widget, $widget_data);
        $html .= $this->render_widget_content($widget, $widget_data, $args);
        $html .= $this->end_widget_container();

        return $html;
    }

    /**
     * Get widget data
     *
     * @param string $widget_id Widget identifier
     * @param array $args Additional arguments
     * @return mixed Widget data
     */
    private function get_widget_data($widget_id, $args = []) {
        $widget = $this->widgets[$widget_id];
        
        // Check cache first if widget is cacheable
        if ($widget['cacheable']) {
            $cache_key = "khm_seo_widget_{$widget_id}_" . md5(serialize($args));
            $cached_data = get_transient($cache_key);
            
            if ($cached_data !== false) {
                return $cached_data;
            }
        }

        // Get data from callback or data source
        $data = null;
        
        if (is_callable($widget['callback'])) {
            $data = call_user_func($widget['callback'], $args);
        } elseif (!empty($widget['data_source'])) {
            $data = $this->get_data_from_source($widget['data_source'], $args);
        } else {
            $data = $this->get_default_widget_data($widget_id, $args);
        }

        // Cache the data if widget is cacheable
        if ($widget['cacheable'] && !is_wp_error($data)) {
            $cache_key = "khm_seo_widget_{$widget_id}_" . md5(serialize($args));
            set_transient($cache_key, $data, $widget['refresh_interval']);
        }

        return $data;
    }

    /**
     * Get default widget data based on widget type
     *
     * @param string $widget_id Widget identifier
     * @param array $args Arguments
     * @return mixed Widget data
     */
    private function get_default_widget_data($widget_id, $args = []) {
        switch ($widget_id) {
            case 'overview_stats':
                return $this->get_overview_stats_data();
            
            case 'recent_analysis':
                return $this->get_recent_analysis_data($args);
            
            case 'performance_chart':
                return $this->get_performance_chart_data($args);
            
            case 'top_issues':
                return $this->get_top_issues_data($args);
            
            case 'content_opportunities':
                return $this->get_content_opportunities_data($args);
            
            case 'technical_health':
                return $this->get_technical_health_data();
            
            default:
                return new \WP_Error('invalid_widget', 'Invalid widget type');
        }
    }

    /**
     * Start widget container HTML
     *
     * @param array $widget Widget configuration
     * @param mixed $widget_data Widget data
     * @return string HTML
     */
    private function start_widget_container($widget, $widget_data) {
        $size_class = 'widget-size-' . $widget['size'];
        $category_class = 'widget-category-' . $widget['category'];
        $widget_class = 'dashboard-widget widget-' . $widget['id'];
        
        $last_updated = '';
        if (is_array($widget_data) && isset($widget_data['last_updated'])) {
            $last_updated = sprintf(
                'data-last-updated="%s"',
                esc_attr($widget_data['last_updated'])
            );
        }

        return sprintf(
            '<div class="%s" id="widget-%s" data-widget-id="%s" %s>',
            esc_attr(implode(' ', [$widget_class, $size_class, $category_class])),
            esc_attr($widget['id']),
            esc_attr($widget['id']),
            $last_updated
        );
    }

    /**
     * End widget container HTML
     *
     * @return string HTML
     */
    private function end_widget_container() {
        return '</div>';
    }

    /**
     * Render widget content
     *
     * @param array $widget Widget configuration
     * @param mixed $widget_data Widget data
     * @param array $args Additional arguments
     * @return string Widget content HTML
     */
    private function render_widget_content($widget, $widget_data, $args) {
        ob_start();
        ?>
        <div class="widget-header">
            <div class="widget-title-section">
                <h3 class="widget-title"><?php echo esc_html($widget['title']); ?></h3>
                <?php if (!empty($widget['description'])): ?>
                    <p class="widget-description"><?php echo esc_html($widget['description']); ?></p>
                <?php endif; ?>
            </div>
            <div class="widget-controls">
                <?php if ($widget['cacheable']): ?>
                    <button class="widget-refresh" data-widget-id="<?php echo esc_attr($widget['id']); ?>" title="Refresh">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                <?php endif; ?>
                <button class="widget-settings" data-widget-id="<?php echo esc_attr($widget['id']); ?>" title="Settings">
                    <span class="dashicons dashicons-admin-generic"></span>
                </button>
                <button class="widget-hide" data-widget-id="<?php echo esc_attr($widget['id']); ?>" title="Hide">
                    <span class="dashicons dashicons-hidden"></span>
                </button>
            </div>
        </div>

        <div class="widget-content">
            <?php echo $this->render_widget_type_content($widget, $widget_data, $args); ?>
        </div>

        <?php if (is_array($widget_data) && isset($widget_data['last_updated'])): ?>
        <div class="widget-footer">
            <span class="last-updated">
                Last updated: <?php echo esc_html(human_time_diff($widget_data['last_updated'])); ?> ago
            </span>
        </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render specific widget type content
     *
     * @param array $widget Widget configuration
     * @param mixed $widget_data Widget data
     * @param array $args Arguments
     * @return string Widget content HTML
     */
    private function render_widget_type_content($widget, $widget_data, $args) {
        if (is_wp_error($widget_data)) {
            return $this->render_error_message($widget_data->get_error_message());
        }

        switch ($widget['id']) {
            case 'overview_stats':
                return $this->render_overview_stats_widget($widget_data);
            
            case 'recent_analysis':
                return $this->render_recent_analysis_widget($widget_data);
            
            case 'performance_chart':
                return $this->render_performance_chart_widget($widget_data);
            
            case 'top_issues':
                return $this->render_top_issues_widget($widget_data);
            
            case 'content_opportunities':
                return $this->render_content_opportunities_widget($widget_data);
            
            case 'technical_health':
                return $this->render_technical_health_widget($widget_data);
            
            default:
                return $this->render_generic_widget($widget_data);
        }
    }

    /**
     * Render overview stats widget
     *
     * @param array $data Widget data
     * @return string HTML
     */
    private function render_overview_stats_widget($data) {
        ob_start();
        ?>
        <div class="overview-stats-grid">
            <?php foreach ($data['stats'] as $stat): ?>
            <div class="stat-item">
                <div class="stat-icon"><?php echo esc_html($stat['icon']); ?></div>
                <div class="stat-value"><?php echo esc_html($stat['value']); ?></div>
                <div class="stat-label"><?php echo esc_html($stat['label']); ?></div>
                <?php if (isset($stat['trend'])): ?>
                <div class="stat-trend trend-<?php echo esc_attr($stat['trend']['direction']); ?>">
                    <?php echo esc_html($stat['trend']['text']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render recent analysis widget
     *
     * @param array $data Widget data
     * @return string HTML
     */
    private function render_recent_analysis_widget($data) {
        ob_start();
        ?>
        <div class="recent-analysis-list">
            <?php if (!empty($data['analyses'])): ?>
                <?php foreach ($data['analyses'] as $analysis): ?>
                <div class="analysis-item">
                    <div class="analysis-info">
                        <h4 class="analysis-title">
                            <a href="<?php echo esc_url($analysis['edit_link']); ?>">
                                <?php echo esc_html($analysis['title']); ?>
                            </a>
                        </h4>
                        <p class="analysis-meta">
                            <?php echo esc_html($analysis['post_type']); ?> • 
                            <?php echo esc_html(human_time_diff($analysis['analyzed_at'])); ?> ago
                        </p>
                    </div>
                    <div class="analysis-score">
                        <span class="score-badge score-<?php echo esc_attr($this->get_score_class($analysis['score'])); ?>">
                            <?php echo esc_html($analysis['score']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>No recent analyses available</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render error widget
     *
     * @param string $message Error message
     * @return string HTML
     */
    private function render_error_widget($message) {
        return sprintf(
            '<div class="dashboard-widget widget-error">
                <div class="widget-content">
                    <div class="error-message">
                        <span class="dashicons dashicons-warning"></span>
                        <p>%s</p>
                    </div>
                </div>
            </div>',
            esc_html($message)
        );
    }

    /**
     * Save user preferences
     *
     * @param array $preferences User preferences
     * @return bool Success status
     */
    public function save_user_preferences($preferences) {
        $user_id = get_current_user_id();
        $this->user_preferences = array_merge($this->user_preferences, $preferences);
        
        return update_user_meta($user_id, 'khm_seo_dashboard_preferences', $this->user_preferences);
    }

    /**
     * Clear widget cache
     *
     * @param string $widget_id Optional specific widget
     */
    public function clear_widget_cache($widget_id = null) {
        global $wpdb;
        
        if ($widget_id) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                $wpdb->esc_like("_transient_khm_seo_widget_{$widget_id}_") . '%'
            ));
        } else {
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_khm_seo_widget_%'"
            );
        }
    }

    // Placeholder data methods
    private function get_overview_stats_data() { return ['stats' => []]; }
    private function get_recent_analysis_data($args) { return ['analyses' => []]; }
    private function get_performance_chart_data($args) { return ['chart_data' => []]; }
    private function get_top_issues_data($args) { return ['issues' => []]; }
    private function get_content_opportunities_data($args) { return ['opportunities' => []]; }
    private function get_technical_health_data() { return ['health_score' => 75]; }
    private function render_performance_chart_widget($data) { return '<canvas id="performance-chart"></canvas>'; }
    private function render_top_issues_widget($data) { return '<div class="issues-list">No critical issues</div>'; }
    private function render_content_opportunities_widget($data) { return '<div class="opportunities-list">No opportunities</div>'; }
    private function render_technical_health_widget($data) { return '<div class="health-score">Health: 75%</div>'; }
    private function render_generic_widget($data) { return '<div class="generic-content">' . esc_html(print_r($data, true)) . '</div>'; }
    private function render_error_message($message) { return '<div class="error">' . esc_html($message) . '</div>'; }
    private function get_data_from_source($source, $args) { return []; }
    private function get_score_class($score) { return $score >= 80 ? 'good' : ($score >= 60 ? 'fair' : 'poor'); }
}