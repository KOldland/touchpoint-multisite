<?php
/**
 * Phase 9 SEO Measurement Module - Admin Dashboard Interface
 * 
 * Comprehensive admin interface providing unified control panel for all Phase 9 components.
 * Features data visualization, reporting tools, configuration management, user role controls,
 * and system monitoring dashboard.
 * 
 * @package KHM_SEO
 * @version 1.0.0
 * @since Phase 9
 */

namespace KHM_SEO\Dashboard;

class AdminDashboardInterface {
    
    /**
     * Dashboard configuration
     */
    private $config;
    
    /**
     * User roles and capabilities
     */
    private $user_roles;
    
    /**
     * Module integrations
     */
    private $modules;
    
    /**
     * Widget configurations
     */
    private $widgets;
    
    /**
     * Chart configurations
     */
    private $charts;
    
    /**
     * Real-time data connections
     */
    private $data_connections;
    
    /**
     * Security manager
     */
    private $security;
    
    /**
     * Initialize the Admin Dashboard Interface
     */
    public function __construct() {
        $this->init_configuration();
        $this->init_user_roles();
        $this->init_modules();
        $this->init_widgets();
        $this->init_charts();
        $this->init_data_connections();
        $this->init_security();
        
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        add_action('wp_ajax_khm_dashboard_action', [$this, 'handle_ajax_requests']);
        add_action('wp_ajax_khm_get_dashboard_data', [$this, 'get_dashboard_data']);
        add_action('wp_ajax_khm_update_widget_config', [$this, 'update_widget_config']);
        add_action('wp_ajax_khm_export_data', [$this, 'export_dashboard_data']);
        
        add_action('wp_dashboard_setup', [$this, 'add_wp_dashboard_widgets']);
    }
    
    /**
     * Initialize dashboard configuration
     */
    private function init_configuration() {
        $this->config = [
            'version' => '1.0.0',
            'auto_refresh_interval' => 30000, // 30 seconds
            'max_data_points' => 1000,
            'cache_duration' => 300, // 5 minutes
            'export_formats' => ['json', 'csv', 'pdf', 'excel'],
            'theme_options' => [
                'light' => 'Light Theme',
                'dark' => 'Dark Theme',
                'auto' => 'Auto (System)'
            ],
            'layout_options' => [
                'grid' => 'Grid Layout',
                'masonry' => 'Masonry Layout',
                'custom' => 'Custom Layout'
            ],
            'default_widgets' => [
                'overview_stats',
                'ranking_trends',
                'traffic_metrics',
                'technical_health',
                'alerts_summary',
                'recent_activity'
            ],
            'refresh_intervals' => [
                15000 => '15 seconds',
                30000 => '30 seconds',
                60000 => '1 minute',
                300000 => '5 minutes',
                900000 => '15 minutes'
            ]
        ];
    }
    
    /**
     * Initialize user roles and capabilities
     */
    private function init_user_roles() {
        $this->user_roles = [
            'seo_administrator' => [
                'label' => 'SEO Administrator',
                'capabilities' => [
                    'view_seo_dashboard',
                    'manage_seo_settings',
                    'view_all_reports',
                    'export_seo_data',
                    'manage_alerts',
                    'configure_integrations',
                    'view_system_logs',
                    'manage_user_roles'
                ]
            ],
            'seo_manager' => [
                'label' => 'SEO Manager',
                'capabilities' => [
                    'view_seo_dashboard',
                    'view_reports',
                    'export_reports',
                    'manage_keywords',
                    'view_alerts',
                    'configure_basic_settings'
                ]
            ],
            'seo_analyst' => [
                'label' => 'SEO Analyst',
                'capabilities' => [
                    'view_seo_dashboard',
                    'view_reports',
                    'analyze_data',
                    'view_alerts'
                ]
            ],
            'content_editor' => [
                'label' => 'Content Editor',
                'capabilities' => [
                    'view_content_optimization',
                    'view_keyword_suggestions',
                    'view_content_scores'
                ]
            ]
        ];
    }
    
    /**
     * Initialize module integrations
     */
    private function init_modules() {
        $this->modules = [
            'database' => [
                'class' => 'KHM\\SEO\\Database\\DatabaseLayer',
                'status' => 'active',
                'widgets' => ['database_health', 'data_statistics'],
                'endpoints' => ['get_db_stats', 'get_data_summary']
            ],
            'oauth' => [
                'class' => 'KHM\\SEO\\OAuth\\OAuthManager',
                'status' => 'active',
                'widgets' => ['account_status', 'api_connections'],
                'endpoints' => ['check_connections', 'refresh_tokens']
            ],
            'google_apis' => [
                'class' => 'KHM\\SEO\\APIs\\GoogleAPIsIntegration',
                'status' => 'active',
                'widgets' => ['api_status', 'quota_usage'],
                'endpoints' => ['get_api_status', 'get_quota_info']
            ],
            'crawler' => [
                'class' => 'KHM\\SEO\\Crawler\\WebCrawler',
                'status' => 'active',
                'widgets' => ['crawl_status', 'technical_issues'],
                'endpoints' => ['get_crawl_stats', 'get_issues']
            ],
            'analytics' => [
                'class' => 'KHM\\SEO\\Analytics\\AnalyticsEngine',
                'status' => 'active',
                'widgets' => ['traffic_overview', 'ranking_trends'],
                'endpoints' => ['get_traffic_data', 'get_ranking_data']
            ],
            'schema' => [
                'class' => 'KHM\\SEO\\Schema\\SchemaAnalyzer',
                'status' => 'active',
                'widgets' => ['schema_status', 'structured_data'],
                'endpoints' => ['get_schema_data', 'validate_markup']
            ],
            'keywords' => [
                'class' => 'KHM\\SEO\\Keywords\\KeywordResearch',
                'status' => 'active',
                'widgets' => ['keyword_overview', 'opportunities'],
                'endpoints' => ['get_keyword_data', 'get_opportunities']
            ],
            'content' => [
                'class' => 'KHM\\SEO\\Content\\ContentOptimizer',
                'status' => 'active',
                'widgets' => ['content_scores', 'optimization_tips'],
                'endpoints' => ['get_content_scores', 'get_suggestions']
            ],
            'scoring' => [
                'class' => 'KHM\\SEO\\Scoring\\ScoringEngine',
                'status' => 'active',
                'widgets' => ['seo_score', 'score_breakdown'],
                'endpoints' => ['get_overall_score', 'get_score_details']
            ],
            'alerts' => [
                'class' => 'KHM\\SEO\\Alerts\\AlertEngine',
                'status' => 'active',
                'widgets' => ['active_alerts', 'alert_history'],
                'endpoints' => ['get_alerts', 'get_alert_stats']
            ]
        ];
    }
    
    /**
     * Initialize widget configurations
     */
    private function init_widgets() {
        $this->widgets = [
            'overview_stats' => [
                'title' => 'SEO Overview',
                'type' => 'stats_grid',
                'size' => 'large',
                'refresh_interval' => 60000,
                'data_source' => 'multiple',
                'config' => [
                    'metrics' => ['overall_score', 'rankings', 'traffic', 'issues'],
                    'comparison_period' => '30d'
                ]
            ],
            'ranking_trends' => [
                'title' => 'Ranking Trends',
                'type' => 'line_chart',
                'size' => 'medium',
                'refresh_interval' => 300000,
                'data_source' => 'analytics',
                'config' => [
                    'timeframe' => '90d',
                    'keywords_limit' => 10,
                    'show_competitors' => true
                ]
            ],
            'traffic_metrics' => [
                'title' => 'Organic Traffic',
                'type' => 'area_chart',
                'size' => 'medium',
                'refresh_interval' => 300000,
                'data_source' => 'google_apis',
                'config' => [
                    'timeframe' => '30d',
                    'metrics' => ['sessions', 'users', 'pageviews'],
                    'segment' => 'organic'
                ]
            ],
            'technical_health' => [
                'title' => 'Technical Health',
                'type' => 'health_indicator',
                'size' => 'small',
                'refresh_interval' => 900000,
                'data_source' => 'crawler',
                'config' => [
                    'check_types' => ['core_web_vitals', 'mobile_friendly', 'ssl', 'crawl_errors'],
                    'threshold_warning' => 70,
                    'threshold_critical' => 50
                ]
            ],
            'alerts_summary' => [
                'title' => 'Active Alerts',
                'type' => 'alert_list',
                'size' => 'medium',
                'refresh_interval' => 15000,
                'data_source' => 'alerts',
                'config' => [
                    'max_items' => 10,
                    'priority_filter' => ['high', 'critical'],
                    'auto_dismiss' => false
                ]
            ],
            'recent_activity' => [
                'title' => 'Recent Activity',
                'type' => 'activity_feed',
                'size' => 'small',
                'refresh_interval' => 60000,
                'data_source' => 'multiple',
                'config' => [
                    'max_items' => 15,
                    'activity_types' => ['crawls', 'alerts', 'reports', 'optimizations'],
                    'show_timestamps' => true
                ]
            ],
            'keyword_overview' => [
                'title' => 'Keyword Performance',
                'type' => 'keyword_table',
                'size' => 'large',
                'refresh_interval' => 300000,
                'data_source' => 'keywords',
                'config' => [
                    'max_keywords' => 20,
                    'sort_by' => 'position_change',
                    'show_search_volume' => true
                ]
            ],
            'content_scores' => [
                'title' => 'Content Optimization',
                'type' => 'score_chart',
                'size' => 'medium',
                'refresh_interval' => 900000,
                'data_source' => 'content',
                'config' => [
                    'max_pages' => 15,
                    'score_threshold' => 70,
                    'show_recommendations' => true
                ]
            ],
            'schema_status' => [
                'title' => 'Schema Markup',
                'type' => 'schema_validator',
                'size' => 'small',
                'refresh_interval' => 1800000,
                'data_source' => 'schema',
                'config' => [
                    'validation_types' => ['errors', 'warnings', 'suggestions'],
                    'show_preview' => true
                ]
            ],
            'api_status' => [
                'title' => 'API Connections',
                'type' => 'connection_status',
                'size' => 'small',
                'refresh_interval' => 300000,
                'data_source' => 'oauth',
                'config' => [
                    'show_quota' => true,
                    'check_health' => true,
                    'auto_reconnect' => true
                ]
            ]
        ];
    }
    
    /**
     * Initialize chart configurations
     */
    private function init_charts() {
        $this->charts = [
            'library' => 'chartjs', // Chart.js as primary library
            'fallback' => 'canvas', // HTML5 Canvas fallback
            'themes' => [
                'light' => [
                    'background' => '#ffffff',
                    'grid_color' => '#e0e0e0',
                    'text_color' => '#333333',
                    'primary_color' => '#0073aa',
                    'success_color' => '#46b450',
                    'warning_color' => '#ffb900',
                    'error_color' => '#dc3232'
                ],
                'dark' => [
                    'background' => '#1e1e1e',
                    'grid_color' => '#444444',
                    'text_color' => '#ffffff',
                    'primary_color' => '#00a0d2',
                    'success_color' => '#5cb85c',
                    'warning_color' => '#f0ad4e',
                    'error_color' => '#d9534f'
                ]
            ],
            'responsive_breakpoints' => [
                'mobile' => 480,
                'tablet' => 768,
                'desktop' => 1024
            ],
            'animation' => [
                'duration' => 1000,
                'easing' => 'easeInOutQuart'
            ]
        ];
    }
    
    /**
     * Initialize data connections
     */
    private function init_data_connections() {
        global $wpdb;
        
        $this->data_connections = [
            'database' => $wpdb,
            'cache' => wp_using_ext_object_cache(),
            'websocket_enabled' => false, // Can be enabled for real-time updates
            'refresh_endpoints' => [
                'overview' => '/wp-admin/admin-ajax.php?action=khm_get_overview_data',
                'rankings' => '/wp-admin/admin-ajax.php?action=khm_get_ranking_data',
                'traffic' => '/wp-admin/admin-ajax.php?action=khm_get_traffic_data',
                'alerts' => '/wp-admin/admin-ajax.php?action=khm_get_alert_data',
                'health' => '/wp-admin/admin-ajax.php?action=khm_get_health_data'
            ]
        ];
    }
    
    /**
     * Initialize security manager
     */
    private function init_security() {
        $this->security = [
            'nonce_action' => 'khm_dashboard_nonce',
            'capability_required' => 'view_seo_dashboard',
            'rate_limits' => [
                'ajax_requests' => 100, // per minute
                'data_exports' => 5, // per hour
                'config_changes' => 20 // per hour
            ],
            'allowed_actions' => [
                'get_dashboard_data',
                'update_widget_config',
                'export_data',
                'refresh_module',
                'toggle_widget',
                'save_layout',
                'reset_dashboard'
            ],
            'data_sanitization' => true,
            'xss_protection' => true,
            'csrf_protection' => true
        ];
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menus() {
        // Main dashboard page
        add_menu_page(
            'SEO Dashboard',
            'SEO Dashboard',
            'view_seo_dashboard',
            'khm-seo-dashboard',
            [$this, 'render_main_dashboard'],
            'dashicons-chart-line',
            3
        );
        
        // Sub-menu pages
        add_submenu_page(
            'khm-seo-dashboard',
            'Dashboard Overview',
            'Overview',
            'view_seo_dashboard',
            'khm-seo-dashboard',
            [$this, 'render_main_dashboard']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'Analytics & Reports',
            'Analytics',
            'view_reports',
            'khm-seo-analytics',
            [$this, 'render_analytics_page']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'Keyword Tracking',
            'Keywords',
            'view_seo_dashboard',
            'khm-seo-keywords',
            [$this, 'render_keywords_page']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'Content Optimization',
            'Content',
            'view_seo_dashboard',
            'khm-seo-content',
            [$this, 'render_content_page']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'Technical SEO',
            'Technical',
            'view_seo_dashboard',
            'khm-seo-technical',
            [$this, 'render_technical_page']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'Alerts & Monitoring',
            'Alerts',
            'view_alerts',
            'khm-seo-alerts',
            [$this, 'render_alerts_page']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'Settings & Configuration',
            'Settings',
            'manage_seo_settings',
            'khm-seo-settings',
            [$this, 'render_settings_page']
        );
        
        add_submenu_page(
            'khm-seo-dashboard',
            'System Status',
            'System',
            'view_system_logs',
            'khm-seo-system',
            [$this, 'render_system_page']
        );
    }
    
    /**
     * Add WordPress dashboard widgets
     */
    public function add_wp_dashboard_widgets() {
        // Note: wp_add_dashboard_widget is only available on dashboard page
        // This would need to be called on 'wp_dashboard_setup' hook
        if (current_user_can('view_seo_dashboard')) {
            // Widget will be added via JavaScript or alternative method
            // since wp_add_dashboard_widget may not be available in this context
        }
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        // Only load on our dashboard pages
        if (strpos($hook, 'khm-seo') === false) {
            return;
        }
        
        // Chart.js for data visualization
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.0.1/dist/chart.min.js',
            [],
            '4.0.1',
            true
        );
        
        // Dashboard JavaScript
        wp_enqueue_script(
            'khm-dashboard-js',
            plugins_url('assets/js/dashboard.js', __FILE__),
            ['jquery', 'chartjs'],
            $this->config['version'],
            true
        );
        
        // Dashboard CSS
        wp_enqueue_style(
            'khm-dashboard-css',
            plugins_url('assets/css/dashboard.css', __FILE__),
            [],
            $this->config['version']
        );
        
        // Grid layout library
        wp_enqueue_script(
            'gridstack',
            'https://cdn.jsdelivr.net/npm/gridstack@8.4.0/dist/gridstack-all.js',
            [],
            '8.4.0',
            true
        );
        
        // Grid CSS
        wp_enqueue_style(
            'gridstack-css',
            'https://cdn.jsdelivr.net/npm/gridstack@8.4.0/dist/gridstack.min.css',
            [],
            '8.4.0'
        );
        
        // Localize script data
        wp_localize_script('khm-dashboard-js', 'khmDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($this->security['nonce_action']),
            'config' => $this->config,
            'widgets' => $this->widgets,
            'refreshEndpoints' => $this->data_connections['refresh_endpoints'],
            'userCapabilities' => $this->get_user_capabilities(),
            'chartThemes' => $this->charts['themes'],
            'strings' => [
                'loading' => __('Loading...', 'khm-seo'),
                'error' => __('Error loading data', 'khm-seo'),
                'noData' => __('No data available', 'khm-seo'),
                'refreshing' => __('Refreshing data...', 'khm-seo'),
                'exportSuccess' => __('Data exported successfully', 'khm-seo'),
                'configSaved' => __('Configuration saved', 'khm-seo'),
                'confirmReset' => __('Are you sure you want to reset the dashboard?', 'khm-seo')
            ]
        ]);
    }
    
    /**
     * Render main dashboard page
     */
    public function render_main_dashboard() {
        if (!current_user_can('view_seo_dashboard')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $user_layout = get_user_meta(get_current_user_id(), 'khm_dashboard_layout', true);
        $default_layout = $this->get_default_layout();
        $layout = !empty($user_layout) ? $user_layout : $default_layout;
        
        ?>
        <div class="wrap khm-dashboard-wrap">
            <h1 class="wp-heading-inline">
                <?php echo esc_html__('SEO Dashboard', 'khm-seo'); ?>
                <span class="khm-version">v<?php echo esc_html($this->config['version']); ?></span>
            </h1>
            
            <div class="khm-dashboard-controls">
                <div class="khm-controls-left">
                    <button type="button" class="button button-secondary" id="khm-refresh-all">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh All', 'khm-seo'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary" id="khm-add-widget">
                        <span class="dashicons dashicons-plus"></span>
                        <?php esc_html_e('Add Widget', 'khm-seo'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary" id="khm-customize-layout">
                        <span class="dashicons dashicons-admin-customizer"></span>
                        <?php esc_html_e('Customize', 'khm-seo'); ?>
                    </button>
                </div>
                
                <div class="khm-controls-right">
                    <select id="khm-auto-refresh">
                        <?php foreach ($this->config['refresh_intervals'] as $interval => $label): ?>
                            <option value="<?php echo esc_attr($interval); ?>" 
                                    <?php selected($interval, $this->config['auto_refresh_interval']); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="button" class="button button-primary" id="khm-export-data">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export', 'khm-seo'); ?>
                    </button>
                </div>
            </div>
            
            <div id="khm-dashboard-notifications" class="khm-notifications"></div>
            
            <div class="khm-dashboard-main">
                <div class="grid-stack" id="khm-dashboard-grid">
                    <?php $this->render_dashboard_widgets($layout); ?>
                </div>
            </div>
            
            <!-- Widget configuration modal -->
            <div id="khm-widget-modal" class="khm-modal" style="display: none;">
                <div class="khm-modal-content">
                    <div class="khm-modal-header">
                        <h2><?php esc_html_e('Widget Configuration', 'khm-seo'); ?></h2>
                        <button type="button" class="khm-modal-close">&times;</button>
                    </div>
                    <div class="khm-modal-body">
                        <!-- Widget configuration form will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Add widget modal -->
            <div id="khm-add-widget-modal" class="khm-modal" style="display: none;">
                <div class="khm-modal-content">
                    <div class="khm-modal-header">
                        <h2><?php esc_html_e('Add Widget', 'khm-seo'); ?></h2>
                        <button type="button" class="khm-modal-close">&times;</button>
                    </div>
                    <div class="khm-modal-body">
                        <div class="khm-widget-gallery">
                            <?php $this->render_widget_gallery(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Initialize dashboard
                window.khmDashboard = new KHMDashboard({
                    container: '#khm-dashboard-grid',
                    layout: <?php echo json_encode($layout); ?>,
                    autoRefresh: <?php echo json_encode($this->config['auto_refresh_interval']); ?>
                });
            });
        </script>
        <?php
    }
    
    /**
     * Render dashboard widgets based on layout
     */
    private function render_dashboard_widgets($layout) {
        foreach ($layout as $widget_id => $position) {
            if (!isset($this->widgets[$widget_id])) {
                continue;
            }
            
            $widget_config = $this->widgets[$widget_id];
            $widget_data = $this->get_widget_data($widget_id);
            
            ?>
            <div class="grid-stack-item" 
                 data-gs-id="<?php echo esc_attr($widget_id); ?>"
                 data-gs-x="<?php echo esc_attr($position['x']); ?>"
                 data-gs-y="<?php echo esc_attr($position['y']); ?>"
                 data-gs-w="<?php echo esc_attr($position['w']); ?>"
                 data-gs-h="<?php echo esc_attr($position['h']); ?>">
                <div class="grid-stack-item-content khm-widget" 
                     data-widget-id="<?php echo esc_attr($widget_id); ?>"
                     data-widget-type="<?php echo esc_attr($widget_config['type']); ?>">
                    
                    <div class="khm-widget-header">
                        <h3 class="khm-widget-title"><?php echo esc_html($widget_config['title']); ?></h3>
                        <div class="khm-widget-controls">
                            <button type="button" class="khm-widget-refresh" title="<?php esc_attr_e('Refresh', 'khm-seo'); ?>">
                                <span class="dashicons dashicons-update"></span>
                            </button>
                            <button type="button" class="khm-widget-configure" title="<?php esc_attr_e('Configure', 'khm-seo'); ?>">
                                <span class="dashicons dashicons-admin-generic"></span>
                            </button>
                            <button type="button" class="khm-widget-remove" title="<?php esc_attr_e('Remove', 'khm-seo'); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="khm-widget-content">
                        <?php $this->render_widget_content($widget_id, $widget_config, $widget_data); ?>
                    </div>
                    
                    <div class="khm-widget-loading" style="display: none;">
                        <span class="spinner is-active"></span>
                        <?php esc_html_e('Loading...', 'khm-seo'); ?>
                    </div>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render widget content based on type
     */
    private function render_widget_content($widget_id, $config, $data) {
        switch ($config['type']) {
            case 'stats_grid':
                $this->render_stats_grid_widget($widget_id, $config, $data);
                break;
                
            case 'line_chart':
                $this->render_line_chart_widget($widget_id, $config, $data);
                break;
                
            case 'area_chart':
                $this->render_area_chart_widget($widget_id, $config, $data);
                break;
                
            case 'health_indicator':
                $this->render_health_indicator_widget($widget_id, $config, $data);
                break;
                
            case 'alert_list':
                $this->render_alert_list_widget($widget_id, $config, $data);
                break;
                
            case 'activity_feed':
                $this->render_activity_feed_widget($widget_id, $config, $data);
                break;
                
            case 'keyword_table':
                $this->render_keyword_table_widget($widget_id, $config, $data);
                break;
                
            case 'score_chart':
                $this->render_score_chart_widget($widget_id, $config, $data);
                break;
                
            case 'schema_validator':
                $this->render_schema_validator_widget($widget_id, $config, $data);
                break;
                
            case 'connection_status':
                $this->render_connection_status_widget($widget_id, $config, $data);
                break;
                
            default:
                echo '<p>' . esc_html__('Widget type not supported.', 'khm-seo') . '</p>';
        }
    }
    
    /**
     * Render stats grid widget
     */
    private function render_stats_grid_widget($widget_id, $config, $data) {
        ?>
        <div class="khm-stats-grid">
            <?php if (!empty($data['metrics'])): ?>
                <?php foreach ($data['metrics'] as $metric_key => $metric_data): ?>
                    <div class="khm-stat-item" data-metric="<?php echo esc_attr($metric_key); ?>">
                        <div class="khm-stat-value">
                            <?php echo esc_html($metric_data['value']); ?>
                        </div>
                        <div class="khm-stat-label">
                            <?php echo esc_html($metric_data['label']); ?>
                        </div>
                        <?php if (isset($metric_data['change'])): ?>
                            <div class="khm-stat-change <?php echo esc_attr($metric_data['change']['direction']); ?>">
                                <span class="dashicons <?php echo esc_attr($metric_data['change']['icon']); ?>"></span>
                                <?php echo esc_html($metric_data['change']['percentage']); ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="khm-no-data"><?php esc_html_e('No data available', 'khm-seo'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render line chart widget
     */
    private function render_line_chart_widget($widget_id, $config, $data) {
        ?>
        <div class="khm-chart-container">
            <canvas id="chart-<?php echo esc_attr($widget_id); ?>" 
                    data-chart-type="line"
                    data-chart-data="<?php echo esc_attr(json_encode($data)); ?>"
                    data-chart-config="<?php echo esc_attr(json_encode($config)); ?>">
            </canvas>
        </div>
        <?php
    }
    
    /**
     * Render area chart widget
     */
    private function render_area_chart_widget($widget_id, $config, $data) {
        ?>
        <div class="khm-chart-container">
            <canvas id="chart-<?php echo esc_attr($widget_id); ?>" 
                    data-chart-type="area"
                    data-chart-data="<?php echo esc_attr(json_encode($data)); ?>"
                    data-chart-config="<?php echo esc_attr(json_encode($config)); ?>">
            </canvas>
        </div>
        <?php
    }
    
    /**
     * Render health indicator widget
     */
    private function render_health_indicator_widget($widget_id, $config, $data) {
        $overall_score = $data['overall_score'] ?? 0;
        $health_status = $this->get_health_status($overall_score);
        
        ?>
        <div class="khm-health-indicator">
            <div class="khm-health-score <?php echo esc_attr($health_status['class']); ?>">
                <div class="khm-score-circle">
                    <div class="khm-score-value"><?php echo esc_html($overall_score); ?></div>
                    <div class="khm-score-label"><?php esc_html_e('Health Score', 'khm-seo'); ?></div>
                </div>
            </div>
            
            <?php if (!empty($data['checks'])): ?>
                <div class="khm-health-checks">
                    <?php foreach ($data['checks'] as $check): ?>
                        <div class="khm-health-check <?php echo esc_attr($check['status']); ?>">
                            <span class="khm-check-icon <?php echo esc_attr($check['icon']); ?>"></span>
                            <span class="khm-check-label"><?php echo esc_html($check['label']); ?></span>
                            <span class="khm-check-value"><?php echo esc_html($check['value']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get user capabilities for dashboard
     */
    private function get_user_capabilities() {
        $user = wp_get_current_user();
        $capabilities = [];
        
        foreach ($this->user_roles as $role_key => $role_data) {
            if (in_array($role_key, $user->roles)) {
                $capabilities = array_merge($capabilities, $role_data['capabilities']);
            }
        }
        
        return array_unique($capabilities);
    }
    
    /**
     * Get default dashboard layout
     */
    private function get_default_layout() {
        return [
            'overview_stats' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 3],
            'ranking_trends' => ['x' => 0, 'y' => 3, 'w' => 8, 'h' => 5],
            'technical_health' => ['x' => 8, 'y' => 3, 'w' => 4, 'h' => 5],
            'alerts_summary' => ['x' => 0, 'y' => 8, 'w' => 6, 'h' => 4],
            'recent_activity' => ['x' => 6, 'y' => 8, 'w' => 6, 'h' => 4],
            'traffic_metrics' => ['x' => 0, 'y' => 12, 'w' => 12, 'h' => 4]
        ];
    }
    
    /**
     * Get widget data from appropriate source
     */
    private function get_widget_data($widget_id) {
        $widget_config = $this->widgets[$widget_id];
        $data_source = $widget_config['data_source'];
        
        // Check cache first
        $cache_key = "khm_widget_data_{$widget_id}";
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Generate sample data for demonstration
        $sample_data = $this->generate_sample_data($widget_id, $widget_config);
        
        // Cache the data
        set_transient($cache_key, $sample_data, $this->config['cache_duration']);
        
        return $sample_data;
    }
    
    /**
     * Generate sample data for widgets (placeholder implementation)
     */
    private function generate_sample_data($widget_id, $config) {
        switch ($widget_id) {
            case 'overview_stats':
                return [
                    'metrics' => [
                        'overall_score' => [
                            'value' => '87',
                            'label' => 'SEO Score',
                            'change' => ['direction' => 'up', 'percentage' => '5.2', 'icon' => 'dashicons-arrow-up-alt']
                        ],
                        'rankings' => [
                            'value' => '142',
                            'label' => 'Top 10 Keywords',
                            'change' => ['direction' => 'up', 'percentage' => '8.1', 'icon' => 'dashicons-arrow-up-alt']
                        ],
                        'traffic' => [
                            'value' => '24,891',
                            'label' => 'Organic Sessions',
                            'change' => ['direction' => 'up', 'percentage' => '12.7', 'icon' => 'dashicons-arrow-up-alt']
                        ],
                        'issues' => [
                            'value' => '3',
                            'label' => 'Critical Issues',
                            'change' => ['direction' => 'down', 'percentage' => '50.0', 'icon' => 'dashicons-arrow-down-alt']
                        ]
                    ]
                ];
                
            case 'technical_health':
                return [
                    'overall_score' => 87,
                    'checks' => [
                        [
                            'status' => 'good',
                            'icon' => 'dashicons-yes',
                            'label' => 'Core Web Vitals',
                            'value' => 'Good'
                        ],
                        [
                            'status' => 'good',
                            'icon' => 'dashicons-yes',
                            'label' => 'Mobile Friendly',
                            'value' => 'Yes'
                        ],
                        [
                            'status' => 'warning',
                            'icon' => 'dashicons-warning',
                            'label' => 'SSL Certificate',
                            'value' => 'Expires Soon'
                        ],
                        [
                            'status' => 'good',
                            'icon' => 'dashicons-yes',
                            'label' => 'Crawl Errors',
                            'value' => '0'
                        ]
                    ]
                ];
                
            case 'alerts_summary':
                return [
                    'alerts' => [
                        [
                            'id' => 1,
                            'type' => 'ranking_drop',
                            'title' => 'Keyword ranking dropped',
                            'message' => '"Best SEO Tools" dropped from position 3 to 8',
                            'priority' => 'high',
                            'timestamp' => time() - 1800
                        ],
                        [
                            'id' => 2,
                            'type' => 'core_web_vitals',
                            'title' => 'Core Web Vitals issue',
                            'message' => 'LCP increased on mobile devices',
                            'priority' => 'medium',
                            'timestamp' => time() - 3600
                        ]
                    ]
                ];
                
            default:
                return ['status' => 'no_data', 'message' => 'Sample data not available'];
        }
    }
    
    /**
     * Get health status based on score
     */
    private function get_health_status($score) {
        if ($score >= 80) {
            return ['class' => 'excellent', 'label' => 'Excellent'];
        } elseif ($score >= 70) {
            return ['class' => 'good', 'label' => 'Good'];
        } elseif ($score >= 50) {
            return ['class' => 'fair', 'label' => 'Fair'];
        } else {
            return ['class' => 'poor', 'label' => 'Poor'];
        }
    }
    
    /**
     * Render widget gallery for adding widgets
     */
    private function render_widget_gallery() {
        ?>
        <div class="khm-widget-gallery-grid">
            <?php foreach ($this->widgets as $widget_id => $widget_config): ?>
                <div class="khm-widget-preview" data-widget-id="<?php echo esc_attr($widget_id); ?>">
                    <div class="khm-widget-preview-header">
                        <h4><?php echo esc_html($widget_config['title']); ?></h4>
                        <span class="khm-widget-type"><?php echo esc_html($widget_config['type']); ?></span>
                    </div>
                    <div class="khm-widget-preview-content">
                        <!-- Mini preview would go here -->
                        <div class="khm-widget-placeholder">
                            <span class="dashicons dashicons-chart-<?php echo esc_attr($this->get_widget_icon($widget_config['type'])); ?>"></span>
                        </div>
                    </div>
                    <div class="khm-widget-preview-footer">
                        <button type="button" class="button button-primary khm-add-widget-btn">
                            <?php esc_html_e('Add Widget', 'khm-seo'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Get widget icon based on type
     */
    private function get_widget_icon($type) {
        $icons = [
            'stats_grid' => 'bar',
            'line_chart' => 'line-graph',
            'area_chart' => 'area-graph',
            'health_indicator' => 'pie',
            'alert_list' => 'warning',
            'activity_feed' => 'list-view',
            'keyword_table' => 'grid-view',
            'score_chart' => 'chart-pie',
            'schema_validator' => 'code-standards',
            'connection_status' => 'networking'
        ];
        
        return $icons[$type] ?? 'chart-bar';
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax_requests() {
        // Security checks
        if (!check_ajax_referer($this->security['nonce_action'], 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('view_seo_dashboard')) {
            wp_die('Insufficient permissions');
        }
        
        $action = sanitize_text_field($_POST['dashboard_action'] ?? '');
        
        if (!in_array($action, $this->security['allowed_actions'])) {
            wp_die('Invalid action');
        }
        
        switch ($action) {
            case 'get_dashboard_data':
                $this->ajax_get_dashboard_data();
                break;
                
            case 'update_widget_config':
                $this->ajax_update_widget_config();
                break;
                
            case 'export_data':
                $this->ajax_export_data();
                break;
                
            case 'refresh_module':
                $this->ajax_refresh_module();
                break;
                
            case 'toggle_widget':
                $this->ajax_toggle_widget();
                break;
                
            case 'save_layout':
                $this->ajax_save_layout();
                break;
                
            case 'reset_dashboard':
                $this->ajax_reset_dashboard();
                break;
                
            default:
                wp_send_json_error('Unknown action');
        }
    }
    
    /**
     * AJAX: Get dashboard data
     */
    private function ajax_get_dashboard_data() {
        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        
        if (empty($widget_id) || !isset($this->widgets[$widget_id])) {
            wp_send_json_error('Invalid widget ID');
        }
        
        $data = $this->get_widget_data($widget_id);
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Update widget configuration
     */
    private function ajax_update_widget_config() {
        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        $config = json_decode(stripslashes($_POST['config'] ?? '{}'), true);
        
        if (empty($widget_id) || !isset($this->widgets[$widget_id])) {
            wp_send_json_error('Invalid widget ID');
        }
        
        // Validate and sanitize configuration
        $sanitized_config = $this->sanitize_widget_config($config);
        
        // Save configuration
        $user_configs = get_user_meta(get_current_user_id(), 'khm_widget_configs', true) ?: [];
        $user_configs[$widget_id] = $sanitized_config;
        update_user_meta(get_current_user_id(), 'khm_widget_configs', $user_configs);
        
        wp_send_json_success('Configuration updated');
    }
    
    /**
     * AJAX: Save dashboard layout
     */
    private function ajax_save_layout() {
        $layout = json_decode(stripslashes($_POST['layout'] ?? '{}'), true);
        
        // Validate layout structure
        foreach ($layout as $widget_id => $position) {
            if (!isset($this->widgets[$widget_id])) {
                unset($layout[$widget_id]);
                continue;
            }
            
            // Sanitize position values
            $layout[$widget_id] = [
                'x' => max(0, intval($position['x'] ?? 0)),
                'y' => max(0, intval($position['y'] ?? 0)),
                'w' => max(1, min(12, intval($position['w'] ?? 1))),
                'h' => max(1, intval($position['h'] ?? 1))
            ];
        }
        
        update_user_meta(get_current_user_id(), 'khm_dashboard_layout', $layout);
        wp_send_json_success('Layout saved');
    }
    
    /**
     * Sanitize widget configuration
     */
    private function sanitize_widget_config($config) {
        $sanitized = [];
        
        foreach ($config as $key => $value) {
            switch ($key) {
                case 'timeframe':
                    $allowed_timeframes = ['7d', '30d', '90d', '365d'];
                    $sanitized[$key] = in_array($value, $allowed_timeframes) ? $value : '30d';
                    break;
                    
                case 'max_items':
                    $sanitized[$key] = max(1, min(100, intval($value)));
                    break;
                    
                case 'show_competitors':
                case 'auto_dismiss':
                case 'show_timestamps':
                    $sanitized[$key] = (bool) $value;
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        // Implementation for analytics page
        echo '<div class="wrap"><h1>Analytics & Reports</h1></div>';
    }
    
    /**
     * Render keywords page
     */
    public function render_keywords_page() {
        // Implementation for keywords page
        echo '<div class="wrap"><h1>Keyword Tracking</h1></div>';
    }
    
    /**
     * Render content page
     */
    public function render_content_page() {
        // Implementation for content page
        echo '<div class="wrap"><h1>Content Optimization</h1></div>';
    }
    
    /**
     * Render technical page
     */
    public function render_technical_page() {
        // Implementation for technical page
        echo '<div class="wrap"><h1>Technical SEO</h1></div>';
    }
    
    /**
     * Render alerts page
     */
    public function render_alerts_page() {
        // Implementation for alerts page
        echo '<div class="wrap"><h1>Alerts & Monitoring</h1></div>';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Implementation for settings page
        echo '<div class="wrap"><h1>Settings & Configuration</h1></div>';
    }
    
    /**
     * Render system page
     */
    public function render_system_page() {
        // Implementation for system page
        echo '<div class="wrap"><h1>System Status</h1></div>';
    }
    
    /**
     * Render alert list widget
     */
    private function render_alert_list_widget($widget_id, $config, $data) {
        ?>
        <div class="khm-alert-list">
            <?php if (!empty($data['alerts'])): ?>
                <ul class="khm-alerts">
                    <?php foreach ($data['alerts'] as $alert): ?>
                        <li class="khm-alert-item priority-<?php echo esc_attr($alert['priority']); ?>" 
                            data-alert-id="<?php echo esc_attr($alert['id']); ?>">
                            <div class="khm-alert-icon">
                                <span class="dashicons <?php echo esc_attr($this->get_alert_icon($alert['type'])); ?>"></span>
                            </div>
                            <div class="khm-alert-content">
                                <h4 class="khm-alert-title"><?php echo esc_html($alert['title']); ?></h4>
                                <p class="khm-alert-message"><?php echo esc_html($alert['message']); ?></p>
                                <small class="khm-alert-timestamp">
                                    <?php echo esc_html(human_time_diff($alert['timestamp'])); ?> ago
                                </small>
                            </div>
                            <div class="khm-alert-actions">
                                <button type="button" class="button-link khm-alert-dismiss" 
                                        title="<?php esc_attr_e('Dismiss', 'khm-seo'); ?>">
                                    <span class="dashicons dashicons-dismiss"></span>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="khm-no-data"><?php esc_html_e('No active alerts', 'khm-seo'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render activity feed widget
     */
    private function render_activity_feed_widget($widget_id, $config, $data) {
        ?>
        <div class="khm-activity-feed">
            <?php if (!empty($data['activities'])): ?>
                <ul class="khm-activities">
                    <?php foreach ($data['activities'] as $activity): ?>
                        <li class="khm-activity-item type-<?php echo esc_attr($activity['type']); ?>">
                            <div class="khm-activity-icon">
                                <span class="dashicons <?php echo esc_attr($this->get_activity_icon($activity['type'])); ?>"></span>
                            </div>
                            <div class="khm-activity-content">
                                <span class="khm-activity-message"><?php echo esc_html($activity['message']); ?></span>
                                <?php if ($config['config']['show_timestamps']): ?>
                                    <small class="khm-activity-timestamp">
                                        <?php echo esc_html(human_time_diff($activity['timestamp'])); ?> ago
                                    </small>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="khm-no-data"><?php esc_html_e('No recent activity', 'khm-seo'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render keyword table widget
     */
    private function render_keyword_table_widget($widget_id, $config, $data) {
        ?>
        <div class="khm-keyword-table">
            <?php if (!empty($data['keywords'])): ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Keyword', 'khm-seo'); ?></th>
                            <th><?php esc_html_e('Position', 'khm-seo'); ?></th>
                            <th><?php esc_html_e('Change', 'khm-seo'); ?></th>
                            <?php if ($config['config']['show_search_volume']): ?>
                                <th><?php esc_html_e('Volume', 'khm-seo'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['keywords'] as $keyword): ?>
                            <tr>
                                <td class="khm-keyword-term">
                                    <strong><?php echo esc_html($keyword['term']); ?></strong>
                                    <?php if (!empty($keyword['url'])): ?>
                                        <br><small><a href="<?php echo esc_url($keyword['url']); ?>" target="_blank">
                                            <?php echo esc_html(parse_url($keyword['url'], PHP_URL_PATH)); ?>
                                        </a></small>
                                    <?php endif; ?>
                                </td>
                                <td class="khm-keyword-position">
                                    <?php echo esc_html($keyword['position']); ?>
                                </td>
                                <td class="khm-keyword-change <?php echo esc_attr($keyword['change']['direction']); ?>">
                                    <span class="dashicons <?php echo esc_attr($keyword['change']['icon']); ?>"></span>
                                    <?php echo esc_html($keyword['change']['value']); ?>
                                </td>
                                <?php if ($config['config']['show_search_volume']): ?>
                                    <td class="khm-keyword-volume">
                                        <?php echo esc_html(number_format($keyword['search_volume'])); ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="khm-no-data"><?php esc_html_e('No keyword data available', 'khm-seo'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render score chart widget
     */
    private function render_score_chart_widget($widget_id, $config, $data) {
        ?>
        <div class="khm-score-chart">
            <?php if (!empty($data['pages'])): ?>
                <div class="khm-score-list">
                    <?php foreach ($data['pages'] as $page): ?>
                        <div class="khm-score-item">
                            <div class="khm-score-page-info">
                                <h4 class="khm-score-page-title">
                                    <a href="<?php echo esc_url($page['url']); ?>" target="_blank">
                                        <?php echo esc_html($page['title']); ?>
                                    </a>
                                </h4>
                                <small class="khm-score-page-url"><?php echo esc_html($page['url']); ?></small>
                            </div>
                            <div class="khm-score-value">
                                <div class="khm-score-circle <?php echo esc_attr($this->get_score_class($page['score'])); ?>">
                                    <span class="khm-score-number"><?php echo esc_html($page['score']); ?></span>
                                </div>
                            </div>
                            <?php if ($config['config']['show_recommendations'] && !empty($page['recommendations'])): ?>
                                <div class="khm-score-recommendations">
                                    <ul>
                                        <?php foreach ($page['recommendations'] as $recommendation): ?>
                                            <li><?php echo esc_html($recommendation); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="khm-no-data"><?php esc_html_e('No content scores available', 'khm-seo'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render schema validator widget
     */
    private function render_schema_validator_widget($widget_id, $config, $data) {
        ?>
        <div class="khm-schema-validator">
            <?php if (!empty($data['validation_results'])): ?>
                <div class="khm-schema-summary">
                    <div class="khm-schema-stat errors">
                        <span class="khm-stat-value"><?php echo esc_html($data['summary']['errors']); ?></span>
                        <span class="khm-stat-label"><?php esc_html_e('Errors', 'khm-seo'); ?></span>
                    </div>
                    <div class="khm-schema-stat warnings">
                        <span class="khm-stat-value"><?php echo esc_html($data['summary']['warnings']); ?></span>
                        <span class="khm-stat-label"><?php esc_html_e('Warnings', 'khm-seo'); ?></span>
                    </div>
                    <div class="khm-schema-stat suggestions">
                        <span class="khm-stat-value"><?php echo esc_html($data['summary']['suggestions']); ?></span>
                        <span class="khm-stat-label"><?php esc_html_e('Suggestions', 'khm-seo'); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($data['recent_issues'])): ?>
                    <div class="khm-schema-issues">
                        <h4><?php esc_html_e('Recent Issues', 'khm-seo'); ?></h4>
                        <ul>
                            <?php foreach ($data['recent_issues'] as $issue): ?>
                                <li class="khm-schema-issue <?php echo esc_attr($issue['type']); ?>">
                                    <span class="khm-issue-type"><?php echo esc_html($issue['type']); ?>:</span>
                                    <span class="khm-issue-message"><?php echo esc_html($issue['message']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="khm-no-data"><?php esc_html_e('No schema validation data', 'khm-seo'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render connection status widget
     */
    private function render_connection_status_widget($widget_id, $config, $data) {
        ?>
        <div class="khm-connection-status">
            <?php if (!empty($data['connections'])): ?>
                <div class="khm-connections-list">
                    <?php foreach ($data['connections'] as $connection): ?>
                        <div class="khm-connection-item status-<?php echo esc_attr($connection['status']); ?>">
                            <div class="khm-connection-info">
                                <h4 class="khm-connection-name"><?php echo esc_html($connection['name']); ?></h4>
                                <span class="khm-connection-status-text">
                                    <?php echo esc_html($connection['status_text']); ?>
                                </span>
                            </div>
                            <div class="khm-connection-status-icon">
                                <span class="dashicons <?php echo esc_attr($this->get_connection_icon($connection['status'])); ?>"></span>
                            </div>
                            <?php if ($config['config']['show_quota'] && isset($connection['quota'])): ?>
                                <div class="khm-connection-quota">
                                    <div class="khm-quota-bar">
                                        <div class="khm-quota-used" 
                                             style="width: <?php echo esc_attr($connection['quota']['percentage']); ?>%"></div>
                                    </div>
                                    <small><?php echo esc_html($connection['quota']['used'] . '/' . $connection['quota']['limit']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="khm-no-data"><?php esc_html_e('No connection data available', 'khm-seo'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX: Export dashboard data
     */
    private function ajax_export_data() {
        $format = sanitize_text_field($_POST['format'] ?? 'json');
        $data_type = sanitize_text_field($_POST['data_type'] ?? 'dashboard');
        
        if (!in_array($format, $this->config['export_formats'])) {
            wp_send_json_error('Invalid export format');
        }
        
        // Generate export data
        $export_data = $this->generate_export_data($data_type);
        
        // Process export based on format
        switch ($format) {
            case 'json':
                $output = json_encode($export_data, JSON_PRETTY_PRINT);
                $content_type = 'application/json';
                $filename = "seo-dashboard-{$data_type}-" . date('Y-m-d') . ".json";
                break;
                
            case 'csv':
                $output = $this->convert_to_csv($export_data);
                $content_type = 'text/csv';
                $filename = "seo-dashboard-{$data_type}-" . date('Y-m-d') . ".csv";
                break;
                
            default:
                wp_send_json_error('Export format not implemented');
        }
        
        wp_send_json_success([
            'filename' => $filename,
            'content_type' => $content_type,
            'data' => base64_encode($output)
        ]);
    }
    
    /**
     * AJAX: Refresh module data
     */
    private function ajax_refresh_module() {
        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        
        if (!isset($this->modules[$module_id])) {
            wp_send_json_error('Invalid module ID');
        }
        
        // Clear cache for this module
        $cache_pattern = "khm_{$module_id}_*";
        $this->clear_cache_pattern($cache_pattern);
        
        wp_send_json_success('Module cache cleared and refreshed');
    }
    
    /**
     * AJAX: Toggle widget visibility
     */
    private function ajax_toggle_widget() {
        $widget_id = sanitize_text_field($_POST['widget_id'] ?? '');
        $visible = (bool) $_POST['visible'];
        
        if (!isset($this->widgets[$widget_id])) {
            wp_send_json_error('Invalid widget ID');
        }
        
        $user_widgets = get_user_meta(get_current_user_id(), 'khm_dashboard_widgets', true) ?: [];
        $user_widgets[$widget_id] = ['visible' => $visible];
        update_user_meta(get_current_user_id(), 'khm_dashboard_widgets', $user_widgets);
        
        wp_send_json_success('Widget visibility updated');
    }
    
    /**
     * AJAX: Reset dashboard to defaults
     */
    private function ajax_reset_dashboard() {
        $user_id = get_current_user_id();
        
        // Remove user customizations
        delete_user_meta($user_id, 'khm_dashboard_layout');
        delete_user_meta($user_id, 'khm_dashboard_widgets');
        delete_user_meta($user_id, 'khm_widget_configs');
        
        wp_send_json_success('Dashboard reset to defaults');
    }
    
    /**
     * Get alert icon based on type
     */
    private function get_alert_icon($type) {
        $icons = [
            'ranking_drop' => 'dashicons-arrow-down-alt',
            'core_web_vitals' => 'dashicons-performance',
            'crawl_errors' => 'dashicons-warning',
            'indexing_issues' => 'dashicons-search',
            'security_issues' => 'dashicons-shield-alt',
            'performance_degradation' => 'dashicons-clock',
            'traffic_drop' => 'dashicons-chart-line'
        ];
        
        return $icons[$type] ?? 'dashicons-info';
    }
    
    /**
     * Get activity icon based on type
     */
    private function get_activity_icon($type) {
        $icons = [
            'crawls' => 'dashicons-search',
            'alerts' => 'dashicons-warning',
            'reports' => 'dashicons-chart-bar',
            'optimizations' => 'dashicons-admin-tools'
        ];
        
        return $icons[$type] ?? 'dashicons-admin-generic';
    }
    
    /**
     * Get score class based on value
     */
    private function get_score_class($score) {
        if ($score >= 80) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'fair';
        return 'poor';
    }
    
    /**
     * Get connection status icon
     */
    private function get_connection_icon($status) {
        $icons = [
            'connected' => 'dashicons-yes',
            'disconnected' => 'dashicons-no',
            'warning' => 'dashicons-warning',
            'error' => 'dashicons-dismiss'
        ];
        
        return $icons[$status] ?? 'dashicons-minus';
    }
    
    /**
     * Generate export data
     */
    private function generate_export_data($data_type) {
        switch ($data_type) {
            case 'dashboard':
                return [
                    'export_date' => date('Y-m-d H:i:s'),
                    'overview' => $this->get_widget_data('overview_stats'),
                    'rankings' => $this->get_widget_data('ranking_trends'),
                    'traffic' => $this->get_widget_data('traffic_metrics'),
                    'health' => $this->get_widget_data('technical_health'),
                    'alerts' => $this->get_widget_data('alerts_summary')
                ];
                
            default:
                return ['error' => 'Data type not supported'];
        }
    }
    
    /**
     * Convert data to CSV format
     */
    private function convert_to_csv($data) {
        $output = '';
        
        if (isset($data['overview']['metrics'])) {
            $output .= "SEO Overview\n";
            $output .= "Metric,Value,Change\n";
            
            foreach ($data['overview']['metrics'] as $key => $metric) {
                $change = isset($metric['change']) ? $metric['change']['percentage'] . '%' : 'N/A';
                $output .= sprintf('"%s","%s","%s"' . "\n", 
                    $metric['label'], 
                    $metric['value'], 
                    $change
                );
            }
            $output .= "\n";
        }
        
        return $output;
    }
    
    /**
     * Clear cache by pattern
     */
    private function clear_cache_pattern($pattern) {
        // Simple implementation - in production, use more sophisticated cache clearing
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            "_transient_" . str_replace('*', '%', $pattern)
        ));
    }
    
    /**
     * Render WordPress dashboard widget
     */
    public function render_wp_dashboard_widget() {
        
        if (!empty($overview_data['metrics'])) {
            echo '<div class="khm-wp-dashboard-widget">';
            foreach (array_slice($overview_data['metrics'], 0, 4) as $metric) {
                echo '<div class="khm-wp-stat">';
                echo '<strong>' . esc_html($metric['value']) . '</strong> ';
                echo '<span>' . esc_html($metric['label']) . '</span>';
                echo '</div>';
            }
            echo '<p><a href="' . admin_url('admin.php?page=khm-seo-dashboard') . '">';
            echo esc_html__('View Full Dashboard', 'khm-seo') . '</a></p>';
            echo '</div>';
        }
    }
}

// Initialize the dashboard
new AdminDashboardInterface();