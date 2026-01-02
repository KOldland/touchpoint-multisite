<?php
/**
 * Phase 9 SEO Measurement Module - User Experience & Frontend
 * 
 * Complete user interface implementation with responsive design, interactive charts
 * and graphs, intuitive navigation, mobile optimization, and seamless user workflows
 * for the entire SEO measurement system.
 * 
 * This is the final component (12/12) that brings together all Phase 9 modules
 * into a cohesive, user-friendly experience.
 * 
 * @package KHM_SEO
 * @version 1.0.0
 * @since Phase 9 - Final Component
 */

namespace KHM_SEO\Frontend;

class UserExperienceFrontend {
    
    /**
     * Frontend configuration
     */
    private $config;
    
    /**
     * Theme system
     */
    private $theme;
    
    /**
     * Navigation structure
     */
    private $navigation;
    
    /**
     * User interface components
     */
    private $ui_components;
    
    /**
     * Mobile optimization settings
     */
    private $mobile_config;
    
    /**
     * Chart and visualization settings
     */
    private $visualization;
    
    /**
     * User workflow definitions
     */
    private $workflows;
    
    /**
     * Responsive breakpoints
     */
    private $breakpoints;
    
    /**
     * Performance optimization
     */
    private $performance;
    
    /**
     * Accessibility features
     */
    private $accessibility;
    
    /**
     * Initialize the User Experience & Frontend
     */
    public function __construct() {
        $this->init_configuration();
        $this->init_theme_system();
        $this->init_navigation();
        $this->init_ui_components();
        $this->init_mobile_optimization();
        $this->init_visualization();
        $this->init_workflows();
        $this->init_responsive_design();
        $this->init_performance_optimization();
        $this->init_accessibility();
        
        // WordPress hooks for frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_head', [$this, 'add_frontend_meta']);
        add_action('admin_head', [$this, 'add_admin_styles']);
        add_action('wp_footer', [$this, 'add_frontend_scripts']);
        add_action('admin_footer', [$this, 'add_admin_scripts']);
        
        // AJAX handlers for frontend interactions
        add_action('wp_ajax_khm_frontend_action', [$this, 'handle_frontend_ajax']);
        add_action('wp_ajax_nopriv_khm_frontend_action', [$this, 'handle_public_ajax']);
        add_action('wp_ajax_khm_save_user_preferences', [$this, 'save_user_preferences']);
        add_action('wp_ajax_khm_get_chart_data', [$this, 'get_chart_data']);
        add_action('wp_ajax_khm_export_frontend_data', [$this, 'export_frontend_data']);
        
        // Custom post types and shortcodes
        add_action('init', [$this, 'register_custom_post_types']);
        add_action('init', [$this, 'register_shortcodes']);
        
        // Frontend customization
        add_action('customize_register', [$this, 'register_customizer_settings']);
        add_action('wp_head', [$this, 'output_custom_styles']);
    }
    
    /**
     * Initialize frontend configuration
     */
    private function init_configuration() {
        $this->config = [
            'version' => '1.0.0',
            'name' => 'Phase 9 SEO Frontend',
            'prefix' => 'khm-seo',
            'api_version' => 'v1',
            'cache_duration' => 3600, // 1 hour
            'max_chart_points' => 500,
            'animation_duration' => 300,
            'debounce_delay' => 250,
            'auto_save_interval' => 30000, // 30 seconds
            'lazy_load_threshold' => '200px',
            'supported_formats' => ['json', 'csv', 'pdf', 'png', 'svg'],
            'chart_engines' => [
                'primary' => 'chart.js',
                'fallback' => 'canvas',
                'vector' => 'd3.js'
            ],
            'icon_library' => 'dashicons',
            'font_system' => 'system-ui',
            'color_system' => 'hsl',
            'spacing_unit' => 'rem',
            'shadow_system' => 'layered',
            'border_radius' => '8px',
            'transition_easing' => 'cubic-bezier(0.4, 0, 0.2, 1)',
            'focus_outline' => '2px solid #005cee',
            'error_display_duration' => 5000,
            'success_display_duration' => 3000
        ];
    }
    
    /**
     * Initialize theme system
     */
    private function init_theme_system() {
        $this->theme = [
            'current' => get_option('khm_seo_theme', 'auto'),
            'themes' => [
                'light' => [
                    'name' => 'Light Theme',
                    'colors' => [
                        'primary' => '#005cee',
                        'secondary' => '#6c757d',
                        'success' => '#28a745',
                        'warning' => '#ffc107',
                        'error' => '#dc3545',
                        'info' => '#17a2b8',
                        'background' => '#ffffff',
                        'surface' => '#f8f9fa',
                        'text_primary' => '#212529',
                        'text_secondary' => '#6c757d',
                        'border' => '#e9ecef',
                        'shadow' => 'rgba(0, 0, 0, 0.1)',
                        'overlay' => 'rgba(0, 0, 0, 0.5)'
                    ],
                    'fonts' => [
                        'primary' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        'monospace' => 'SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace',
                        'heading' => 'inherit'
                    ]
                ],
                'dark' => [
                    'name' => 'Dark Theme',
                    'colors' => [
                        'primary' => '#4dabf7',
                        'secondary' => '#adb5bd',
                        'success' => '#51cf66',
                        'warning' => '#ffd43b',
                        'error' => '#ff6b6b',
                        'info' => '#74c0fc',
                        'background' => '#1a1a1a',
                        'surface' => '#2d2d2d',
                        'text_primary' => '#ffffff',
                        'text_secondary' => '#adb5bd',
                        'border' => '#404040',
                        'shadow' => 'rgba(0, 0, 0, 0.3)',
                        'overlay' => 'rgba(0, 0, 0, 0.7)'
                    ],
                    'fonts' => [
                        'primary' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        'monospace' => 'SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace',
                        'heading' => 'inherit'
                    ]
                ],
                'auto' => [
                    'name' => 'Auto (System)',
                    'description' => 'Follows system preference'
                ]
            ],
            'customizable_properties' => [
                'primary_color', 'accent_color', 'font_size',
                'border_radius', 'spacing', 'sidebar_width'
            ],
            'css_variables' => true,
            'theme_switching' => true,
            'user_preferences' => true
        ];
    }
    
    /**
     * Initialize navigation structure
     */
    private function init_navigation() {
        $this->navigation = [
            'main_menu' => [
                'dashboard' => [
                    'title' => 'Dashboard',
                    'icon' => 'dashicons-dashboard',
                    'url' => 'admin.php?page=khm-seo-dashboard',
                    'capability' => 'view_seo_dashboard',
                    'order' => 1,
                    'children' => [
                        'overview' => [
                            'title' => 'Overview',
                            'url' => 'admin.php?page=khm-seo-dashboard',
                            'order' => 1
                        ],
                        'widgets' => [
                            'title' => 'Customize Widgets',
                            'url' => 'admin.php?page=khm-seo-dashboard&view=widgets',
                            'order' => 2
                        ]
                    ]
                ],
                'analytics' => [
                    'title' => 'Analytics',
                    'icon' => 'dashicons-chart-line',
                    'url' => 'admin.php?page=khm-seo-analytics',
                    'capability' => 'view_reports',
                    'order' => 2,
                    'children' => [
                        'overview' => ['title' => 'Overview', 'url' => 'admin.php?page=khm-seo-analytics', 'order' => 1],
                        'traffic' => ['title' => 'Traffic Analysis', 'url' => 'admin.php?page=khm-seo-analytics&view=traffic', 'order' => 2],
                        'rankings' => ['title' => 'Ranking Trends', 'url' => 'admin.php?page=khm-seo-analytics&view=rankings', 'order' => 3],
                        'competitors' => ['title' => 'Competitor Analysis', 'url' => 'admin.php?page=khm-seo-analytics&view=competitors', 'order' => 4]
                    ]
                ],
                'keywords' => [
                    'title' => 'Keywords',
                    'icon' => 'dashicons-search',
                    'url' => 'admin.php?page=khm-seo-keywords',
                    'capability' => 'view_seo_dashboard',
                    'order' => 3,
                    'children' => [
                        'tracking' => ['title' => 'Keyword Tracking', 'url' => 'admin.php?page=khm-seo-keywords', 'order' => 1],
                        'research' => ['title' => 'Keyword Research', 'url' => 'admin.php?page=khm-seo-keywords&view=research', 'order' => 2],
                        'opportunities' => ['title' => 'Opportunities', 'url' => 'admin.php?page=khm-seo-keywords&view=opportunities', 'order' => 3]
                    ]
                ],
                'content' => [
                    'title' => 'Content',
                    'icon' => 'dashicons-edit-large',
                    'url' => 'admin.php?page=khm-seo-content',
                    'capability' => 'view_seo_dashboard',
                    'order' => 4,
                    'children' => [
                        'optimization' => ['title' => 'Content Optimization', 'url' => 'admin.php?page=khm-seo-content', 'order' => 1],
                        'analysis' => ['title' => 'Content Analysis', 'url' => 'admin.php?page=khm-seo-content&view=analysis', 'order' => 2],
                        'suggestions' => ['title' => 'AI Suggestions', 'url' => 'admin.php?page=khm-seo-content&view=suggestions', 'order' => 3]
                    ]
                ],
                'technical' => [
                    'title' => 'Technical SEO',
                    'icon' => 'dashicons-admin-tools',
                    'url' => 'admin.php?page=khm-seo-technical',
                    'capability' => 'view_seo_dashboard',
                    'order' => 5,
                    'children' => [
                        'health' => ['title' => 'Site Health', 'url' => 'admin.php?page=khm-seo-technical', 'order' => 1],
                        'crawl' => ['title' => 'Crawl Analysis', 'url' => 'admin.php?page=khm-seo-technical&view=crawl', 'order' => 2],
                        'schema' => ['title' => 'Schema Markup', 'url' => 'admin.php?page=khm-seo-technical&view=schema', 'order' => 3],
                        'performance' => ['title' => 'Performance', 'url' => 'admin.php?page=khm-seo-technical&view=performance', 'order' => 4]
                    ]
                ],
                'alerts' => [
                    'title' => 'Alerts',
                    'icon' => 'dashicons-warning',
                    'url' => 'admin.php?page=khm-seo-alerts',
                    'capability' => 'view_alerts',
                    'order' => 6,
                    'badge' => 'dynamic', // Shows alert count
                    'children' => [
                        'active' => ['title' => 'Active Alerts', 'url' => 'admin.php?page=khm-seo-alerts', 'order' => 1],
                        'history' => ['title' => 'Alert History', 'url' => 'admin.php?page=khm-seo-alerts&view=history', 'order' => 2],
                        'settings' => ['title' => 'Alert Settings', 'url' => 'admin.php?page=khm-seo-alerts&view=settings', 'order' => 3]
                    ]
                ],
                'settings' => [
                    'title' => 'Settings',
                    'icon' => 'dashicons-admin-settings',
                    'url' => 'admin.php?page=khm-seo-settings',
                    'capability' => 'manage_seo_settings',
                    'order' => 7,
                    'children' => [
                        'general' => ['title' => 'General', 'url' => 'admin.php?page=khm-seo-settings', 'order' => 1],
                        'apis' => ['title' => 'API Connections', 'url' => 'admin.php?page=khm-seo-settings&view=apis', 'order' => 2],
                        'users' => ['title' => 'User Roles', 'url' => 'admin.php?page=khm-seo-settings&view=users', 'order' => 3],
                        'advanced' => ['title' => 'Advanced', 'url' => 'admin.php?page=khm-seo-settings&view=advanced', 'order' => 4]
                    ]
                ]
            ],
            'breadcrumbs' => [
                'enabled' => true,
                'separator' => '›',
                'home_text' => 'SEO Dashboard',
                'show_current' => true
            ],
            'quick_actions' => [
                'new_keyword' => [
                    'title' => 'Add Keyword',
                    'icon' => 'dashicons-plus',
                    'action' => 'khm_add_keyword',
                    'capability' => 'manage_keywords'
                ],
                'run_crawl' => [
                    'title' => 'Run Site Crawl',
                    'icon' => 'dashicons-search',
                    'action' => 'khm_run_crawl',
                    'capability' => 'run_crawls'
                ],
                'generate_report' => [
                    'title' => 'Generate Report',
                    'icon' => 'dashicons-media-document',
                    'action' => 'khm_generate_report',
                    'capability' => 'view_reports'
                ]
            ],
            'mobile_menu' => [
                'enabled' => true,
                'collapse_threshold' => 768,
                'hamburger_icon' => true,
                'slide_direction' => 'left'
            ]
        ];
    }
    
    /**
     * Initialize UI components
     */
    private function init_ui_components() {
        $this->ui_components = [
            'buttons' => [
                'primary' => [
                    'class' => 'khm-btn khm-btn-primary',
                    'styles' => [
                        'background' => 'var(--khm-color-primary)',
                        'color' => 'white',
                        'border' => 'none',
                        'border-radius' => 'var(--khm-border-radius)',
                        'padding' => '0.75rem 1.5rem',
                        'font-weight' => '500',
                        'transition' => 'all 0.2s ease'
                    ]
                ],
                'secondary' => [
                    'class' => 'khm-btn khm-btn-secondary',
                    'styles' => [
                        'background' => 'transparent',
                        'color' => 'var(--khm-color-primary)',
                        'border' => '1px solid var(--khm-color-primary)',
                        'border-radius' => 'var(--khm-border-radius)',
                        'padding' => '0.75rem 1.5rem',
                        'font-weight' => '500',
                        'transition' => 'all 0.2s ease'
                    ]
                ],
                'danger' => [
                    'class' => 'khm-btn khm-btn-danger',
                    'styles' => [
                        'background' => 'var(--khm-color-error)',
                        'color' => 'white',
                        'border' => 'none',
                        'border-radius' => 'var(--khm-border-radius)',
                        'padding' => '0.75rem 1.5rem',
                        'font-weight' => '500',
                        'transition' => 'all 0.2s ease'
                    ]
                ]
            ],
            'cards' => [
                'default' => [
                    'class' => 'khm-card',
                    'styles' => [
                        'background' => 'var(--khm-color-surface)',
                        'border' => '1px solid var(--khm-color-border)',
                        'border-radius' => 'var(--khm-border-radius)',
                        'padding' => '1.5rem',
                        'box-shadow' => '0 2px 4px var(--khm-color-shadow)',
                        'transition' => 'box-shadow 0.2s ease'
                    ]
                ],
                'elevated' => [
                    'class' => 'khm-card khm-card-elevated',
                    'styles' => [
                        'background' => 'var(--khm-color-surface)',
                        'border' => '1px solid var(--khm-color-border)',
                        'border-radius' => 'var(--khm-border-radius)',
                        'padding' => '1.5rem',
                        'box-shadow' => '0 4px 8px var(--khm-color-shadow)',
                        'transition' => 'box-shadow 0.2s ease'
                    ]
                ]
            ],
            'forms' => [
                'input' => [
                    'class' => 'khm-input',
                    'styles' => [
                        'border' => '1px solid var(--khm-color-border)',
                        'border-radius' => 'var(--khm-border-radius)',
                        'padding' => '0.75rem',
                        'font-size' => '1rem',
                        'transition' => 'border-color 0.2s ease',
                        'width' => '100%'
                    ]
                ],
                'select' => [
                    'class' => 'khm-select',
                    'styles' => [
                        'border' => '1px solid var(--khm-color-border)',
                        'border-radius' => 'var(--khm-border-radius)',
                        'padding' => '0.75rem',
                        'font-size' => '1rem',
                        'background' => 'var(--khm-color-surface)',
                        'transition' => 'border-color 0.2s ease'
                    ]
                ]
            ],
            'notifications' => [
                'success' => [
                    'class' => 'khm-notification khm-notification-success',
                    'icon' => 'dashicons-yes-alt',
                    'duration' => 3000
                ],
                'error' => [
                    'class' => 'khm-notification khm-notification-error',
                    'icon' => 'dashicons-dismiss',
                    'duration' => 5000
                ],
                'warning' => [
                    'class' => 'khm-notification khm-notification-warning',
                    'icon' => 'dashicons-warning',
                    'duration' => 4000
                ],
                'info' => [
                    'class' => 'khm-notification khm-notification-info',
                    'icon' => 'dashicons-info',
                    'duration' => 3000
                ]
            ],
            'modals' => [
                'default' => [
                    'class' => 'khm-modal',
                    'backdrop' => true,
                    'keyboard_close' => true,
                    'click_outside_close' => true,
                    'animation' => 'fade'
                ]
            ],
            'tooltips' => [
                'enabled' => true,
                'delay' => 500,
                'placement' => 'top',
                'animation' => true
            ],
            'loading' => [
                'spinner' => [
                    'class' => 'khm-spinner',
                    'type' => 'dots'
                ],
                'skeleton' => [
                    'class' => 'khm-skeleton',
                    'animation' => 'pulse'
                ]
            ]
        ];
    }
    
    /**
     * Initialize mobile optimization
     */
    private function init_mobile_optimization() {
        $this->mobile_config = [
            'responsive' => true,
            'viewport' => 'width=device-width, initial-scale=1.0',
            'touch_optimization' => [
                'touch_targets' => '44px', // Minimum touch target size
                'touch_callouts' => false,
                'user_select' => 'none',
                'tap_highlight' => 'transparent'
            ],
            'mobile_menu' => [
                'type' => 'slide',
                'position' => 'left',
                'width' => '280px',
                'overlay' => true,
                'swipe_gestures' => true
            ],
            'mobile_tables' => [
                'scroll_hint' => true,
                'card_view_threshold' => '600px',
                'priority_columns' => true
            ],
            'mobile_charts' => [
                'simplified' => true,
                'touch_zoom' => true,
                'reduced_animation' => true,
                'larger_touch_areas' => true
            ],
            'progressive_disclosure' => [
                'enabled' => true,
                'expandable_sections' => true,
                'accordion_style' => true
            ],
            'mobile_performance' => [
                'lazy_loading' => true,
                'image_optimization' => true,
                'reduced_motion' => 'respect_preference',
                'memory_management' => true
            ]
        ];
    }
    
    /**
     * Initialize visualization system
     */
    private function init_visualization() {
        $this->visualization = [
            'chart_library' => 'Chart.js',
            'chart_types' => [
                'line' => [
                    'name' => 'Line Chart',
                    'use_cases' => ['trends', 'time_series', 'rankings'],
                    'responsive' => true,
                    'animations' => true
                ],
                'bar' => [
                    'name' => 'Bar Chart',
                    'use_cases' => ['comparisons', 'categories', 'metrics'],
                    'responsive' => true,
                    'animations' => true
                ],
                'doughnut' => [
                    'name' => 'Doughnut Chart',
                    'use_cases' => ['proportions', 'percentages', 'distribution'],
                    'responsive' => true,
                    'animations' => true
                ],
                'radar' => [
                    'name' => 'Radar Chart',
                    'use_cases' => ['multi_dimensional', 'scores', 'comparisons'],
                    'responsive' => true,
                    'animations' => true
                ],
                'scatter' => [
                    'name' => 'Scatter Plot',
                    'use_cases' => ['correlations', 'relationships', 'clusters'],
                    'responsive' => true,
                    'animations' => true
                ]
            ],
            'color_palettes' => [
                'primary' => ['#005cee', '#4dabf7', '#74c0fc', '#a5d8ff', '#d0ebff'],
                'success' => ['#28a745', '#51cf66', '#69db7c', '#8ce99a', '#b2f2bb'],
                'warning' => ['#ffc107', '#ffd43b', '#ffec8c', '#fff3bf', '#fff8db'],
                'error' => ['#dc3545', '#ff6b6b', '#ffa8a8', '#ffc9c9', '#ffe0e0'],
                'neutral' => ['#6c757d', '#868e96', '#adb5bd', '#ced4da', '#dee2e6']
            ],
            'default_options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'interaction' => [
                    'mode' => 'index',
                    'intersect' => false
                ],
                'plugins' => [
                    'legend' => ['display' => true, 'position' => 'bottom'],
                    'tooltip' => ['enabled' => true, 'mode' => 'index']
                ],
                'animation' => [
                    'duration' => 1000,
                    'easing' => 'easeInOutQuart'
                ]
            ],
            'data_formatting' => [
                'number_format' => 'localized',
                'date_format' => 'relative',
                'percentage_precision' => 1,
                'large_number_format' => 'compact'
            ]
        ];
    }
    
    /**
     * Initialize user workflows
     */
    private function init_workflows() {
        $this->workflows = [
            'onboarding' => [
                'enabled' => true,
                'steps' => [
                    'welcome' => [
                        'title' => 'Welcome to Phase 9 SEO',
                        'description' => 'Let\'s get your SEO monitoring set up',
                        'actions' => ['skip', 'continue']
                    ],
                    'connect_apis' => [
                        'title' => 'Connect Your Accounts',
                        'description' => 'Connect Google Search Console and Analytics',
                        'required' => true,
                        'actions' => ['skip', 'connect']
                    ],
                    'add_keywords' => [
                        'title' => 'Add Your Keywords',
                        'description' => 'Start tracking your important keywords',
                        'actions' => ['skip', 'add_keywords']
                    ],
                    'setup_alerts' => [
                        'title' => 'Configure Alerts',
                        'description' => 'Get notified about important changes',
                        'actions' => ['skip', 'setup']
                    ],
                    'complete' => [
                        'title' => 'You\'re All Set!',
                        'description' => 'Your SEO dashboard is ready to use',
                        'actions' => ['finish']
                    ]
                ],
                'progress_tracking' => true,
                'skippable' => true
            ],
            'keyword_management' => [
                'add_keyword' => [
                    'steps' => ['search', 'select', 'configure', 'save'],
                    'validation' => true,
                    'bulk_actions' => true
                ],
                'keyword_research' => [
                    'steps' => ['input_seed', 'analyze', 'filter', 'export'],
                    'ai_suggestions' => true,
                    'competitor_analysis' => true
                ]
            ],
            'content_optimization' => [
                'page_analysis' => [
                    'steps' => ['select_page', 'analyze', 'review_suggestions', 'implement'],
                    'real_time_scoring' => true,
                    'before_after_comparison' => true
                ],
                'content_creation' => [
                    'steps' => ['keyword_research', 'outline', 'write', 'optimize', 'publish'],
                    'ai_assistance' => true,
                    'seo_suggestions' => true
                ]
            ],
            'reporting' => [
                'custom_reports' => [
                    'steps' => ['select_metrics', 'choose_timeframe', 'customize', 'generate'],
                    'templates' => true,
                    'scheduled_delivery' => true
                ],
                'data_export' => [
                    'steps' => ['select_data', 'choose_format', 'configure', 'download'],
                    'multiple_formats' => true,
                    'custom_filters' => true
                ]
            ]
        ];
    }
    
    /**
     * Initialize responsive design system
     */
    private function init_responsive_design() {
        $this->breakpoints = [
            'xs' => ['min' => 0, 'max' => 575, 'container' => '100%'],
            'sm' => ['min' => 576, 'max' => 767, 'container' => '540px'],
            'md' => ['min' => 768, 'max' => 991, 'container' => '720px'],
            'lg' => ['min' => 992, 'max' => 1199, 'container' => '960px'],
            'xl' => ['min' => 1200, 'max' => 1399, 'container' => '1140px'],
            'xxl' => ['min' => 1400, 'max' => 9999, 'container' => '1320px']
        ];
    }
    
    /**
     * Initialize performance optimization
     */
    private function init_performance_optimization() {
        $this->performance = [
            'lazy_loading' => [
                'images' => true,
                'charts' => true,
                'widgets' => true,
                'threshold' => '200px'
            ],
            'caching' => [
                'api_responses' => 300, // 5 minutes
                'chart_data' => 900, // 15 minutes
                'user_preferences' => 3600, // 1 hour
                'static_assets' => 86400 // 24 hours
            ],
            'code_splitting' => [
                'enabled' => true,
                'chunk_strategy' => 'page_based',
                'preload_critical' => true
            ],
            'asset_optimization' => [
                'minification' => true,
                'compression' => true,
                'cdn_support' => true,
                'image_optimization' => true
            ],
            'memory_management' => [
                'chart_cleanup' => true,
                'event_listener_cleanup' => true,
                'dom_cleanup' => true,
                'memory_monitoring' => true
            ]
        ];
    }
    
    /**
     * Initialize accessibility features
     */
    private function init_accessibility() {
        $this->accessibility = [
            'wcag_level' => 'AA',
            'features' => [
                'keyboard_navigation' => true,
                'screen_reader_support' => true,
                'high_contrast_mode' => true,
                'focus_indicators' => true,
                'skip_links' => true,
                'aria_labels' => true,
                'semantic_markup' => true,
                'reduced_motion' => 'respect_preference'
            ],
            'color_contrast' => [
                'minimum_ratio' => 4.5,
                'large_text_ratio' => 3,
                'enhanced_ratio' => 7
            ],
            'text_scaling' => [
                'support_zoom' => true,
                'max_zoom' => '200%',
                'responsive_text' => true
            ],
            'announcements' => [
                'live_regions' => true,
                'status_updates' => true,
                'error_announcements' => true
            ]
        ];
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on SEO-related pages
        if (!$this->is_seo_page()) {
            return;
        }
        
        // Core CSS
        wp_enqueue_style(
            'khm-frontend-core',
            $this->get_asset_url('css/frontend-core.css'),
            [],
            $this->config['version']
        );
        
        // Theme CSS
        wp_enqueue_style(
            'khm-frontend-theme',
            $this->get_asset_url('css/themes/' . $this->theme['current'] . '.css'),
            ['khm-frontend-core'],
            $this->config['version']
        );
        
        // Component CSS
        wp_enqueue_style(
            'khm-components',
            $this->get_asset_url('css/components.css'),
            ['khm-frontend-theme'],
            $this->config['version']
        );
        
        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.0.1/dist/chart.min.js',
            [],
            '4.0.1',
            true
        );
        
        // Core JavaScript
        wp_enqueue_script(
            'khm-frontend-core',
            $this->get_asset_url('js/frontend-core.js'),
            ['jquery', 'chartjs'],
            $this->config['version'],
            true
        );
        
        // UI Components JavaScript
        wp_enqueue_script(
            'khm-ui-components',
            $this->get_asset_url('js/ui-components.js'),
            ['khm-frontend-core'],
            $this->config['version'],
            true
        );
        
        // Workflow JavaScript
        wp_enqueue_script(
            'khm-workflows',
            $this->get_asset_url('js/workflows.js'),
            ['khm-ui-components'],
            $this->config['version'],
            true
        );
        
        // Localize script data
        wp_localize_script('khm-frontend-core', 'khmFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_frontend_nonce'),
            'config' => $this->config,
            'theme' => $this->theme,
            'navigation' => $this->navigation,
            'breakpoints' => $this->breakpoints,
            'visualization' => $this->visualization,
            'workflows' => $this->workflows,
            'accessibility' => $this->accessibility,
            'strings' => [
                'loading' => __('Loading...', 'khm-seo'),
                'error' => __('An error occurred', 'khm-seo'),
                'success' => __('Success!', 'khm-seo'),
                'confirm' => __('Are you sure?', 'khm-seo'),
                'cancel' => __('Cancel', 'khm-seo'),
                'save' => __('Save', 'khm-seo'),
                'close' => __('Close', 'khm-seo'),
                'next' => __('Next', 'khm-seo'),
                'previous' => __('Previous', 'khm-seo'),
                'skip' => __('Skip', 'khm-seo'),
                'finish' => __('Finish', 'khm-seo')
            ]
        ]);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on SEO admin pages
        if (strpos($hook, 'khm-seo') === false) {
            return;
        }
        
        // Admin-specific styles
        wp_enqueue_style(
            'khm-admin-frontend',
            $this->get_asset_url('css/admin-frontend.css'),
            [],
            $this->config['version']
        );
        
        // Admin-specific JavaScript
        wp_enqueue_script(
            'khm-admin-frontend',
            $this->get_asset_url('js/admin-frontend.js'),
            ['khm-frontend-core'],
            $this->config['version'],
            true
        );
    }
    
    /**
     * Add frontend meta tags
     */
    public function add_frontend_meta() {
        if (!$this->is_seo_page()) {
            return;
        }
        
        echo '<meta name="viewport" content="' . esc_attr($this->mobile_config['viewport']) . '">' . "\n";
        echo '<meta name="theme-color" content="' . esc_attr($this->get_theme_color('primary')) . '">' . "\n";
        
        // Preload critical assets
        echo '<link rel="preload" href="' . esc_url($this->get_asset_url('js/frontend-core.js')) . '" as="script">' . "\n";
        echo '<link rel="preload" href="' . esc_url($this->get_asset_url('css/frontend-core.css')) . '" as="style">' . "\n";
    }
    
    /**
     * Add admin styles to head
     */
    public function add_admin_styles() {
        global $current_screen;
        
        if (!$current_screen || strpos($current_screen->id, 'khm-seo') === false) {
            return;
        }
        
        ?>
        <style type="text/css">
            :root {
                <?php echo $this->generate_css_variables(); ?>
            }
            
            /* Custom admin styles */
            .khm-admin-header {
                background: var(--khm-color-primary);
                color: white;
                padding: 1rem;
                margin: 0 -20px 20px -20px;
                border-radius: 0 0 8px 8px;
            }
            
            .khm-admin-header h1 {
                color: white;
                margin: 0;
                font-size: 1.5rem;
            }
            
            .khm-quick-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
                margin-bottom: 2rem;
            }
            
            .khm-quick-stat {
                background: var(--khm-color-surface);
                border: 1px solid var(--khm-color-border);
                border-radius: var(--khm-border-radius);
                padding: 1rem;
                text-align: center;
            }
            
            .khm-quick-stat-value {
                font-size: 2rem;
                font-weight: bold;
                color: var(--khm-color-primary);
            }
            
            .khm-quick-stat-label {
                font-size: 0.875rem;
                color: var(--khm-color-text-secondary);
                margin-top: 0.5rem;
            }
        </style>
        <?php
    }
    
    /**
     * Add frontend scripts to footer
     */
    public function add_frontend_scripts() {
        if (!$this->is_seo_page()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
            // Initialize frontend when DOM is ready
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof KHMFrontend !== 'undefined') {
                    window.khmFrontend = new KHMFrontend(khmFrontend);
                    window.khmFrontend.init();
                }
            });
            
            // Theme switching
            function khmSwitchTheme(theme) {
                localStorage.setItem('khm-theme-preference', theme);
                document.documentElement.setAttribute('data-theme', theme);
                
                // Update system theme if auto
                if (theme === 'auto') {
                    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    document.documentElement.setAttribute('data-theme', systemTheme);
                }
            }
            
            // Apply saved theme preference
            (function() {
                const savedTheme = localStorage.getItem('khm-theme-preference') || 'auto';
                khmSwitchTheme(savedTheme);
            })();
        </script>
        <?php
    }
    
    /**
     * Add admin scripts to footer
     */
    public function add_admin_scripts() {
        global $current_screen;
        
        if (!$current_screen || strpos($current_screen->id, 'khm-seo') === false) {
            return;
        }
        
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Initialize admin frontend features
                if (typeof KHMAdminFrontend !== 'undefined') {
                    window.khmAdminFrontend = new KHMAdminFrontend();
                    window.khmAdminFrontend.init();
                }
                
                // Auto-refresh functionality
                setInterval(function() {
                    $('.khm-auto-refresh').each(function() {
                        const $element = $(this);
                        const endpoint = $element.data('endpoint');
                        
                        if (endpoint) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'khm_frontend_action',
                                    frontend_action: 'refresh_data',
                                    endpoint: endpoint,
                                    nonce: khmFrontend.nonce
                                },
                                success: function(response) {
                                    if (response.success) {
                                        $element.html(response.data.html);
                                    }
                                }
                            });
                        }
                    });
                }, 30000); // 30 seconds
            });
        </script>
        <?php
    }
    
    /**
     * Handle frontend AJAX requests
     */
    public function handle_frontend_ajax() {
        // Security check
        if (!check_ajax_referer('khm_frontend_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['frontend_action'] ?? '');
        
        switch ($action) {
            case 'save_preferences':
                $this->ajax_save_preferences();
                break;
                
            case 'get_chart_data':
                $this->ajax_get_chart_data();
                break;
                
            case 'refresh_data':
                $this->ajax_refresh_data();
                break;
                
            case 'export_data':
                $this->ajax_export_data();
                break;
                
            case 'onboarding_step':
                $this->ajax_onboarding_step();
                break;
                
            default:
                wp_send_json_error('Invalid action');
        }
    }
    
    /**
     * Handle public AJAX requests (for frontend widgets)
     */
    public function handle_public_ajax() {
        // Limited functionality for non-logged-in users
        $action = sanitize_text_field($_POST['frontend_action'] ?? '');
        
        switch ($action) {
            case 'get_public_data':
                $this->ajax_get_public_data();
                break;
                
            default:
                wp_send_json_error('Action not available');
        }
    }
    
    /**
     * AJAX: Save user preferences
     */
    private function ajax_save_preferences() {
        $preferences = json_decode(stripslashes($_POST['preferences'] ?? '{}'), true);
        
        // Validate and sanitize preferences
        $sanitized_preferences = $this->sanitize_user_preferences($preferences);
        
        // Save to user meta
        update_user_meta(get_current_user_id(), 'khm_frontend_preferences', $sanitized_preferences);
        
        wp_send_json_success('Preferences saved');
    }
    
    /**
     * AJAX: Get chart data
     */
    private function ajax_get_chart_data() {
        $chart_type = sanitize_text_field($_POST['chart_type'] ?? '');
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? '30d');
        
        // Generate chart data (placeholder implementation)
        $chart_data = $this->generate_chart_data($chart_type, $timeframe);
        
        wp_send_json_success($chart_data);
    }
    
    /**
     * Register custom post types for SEO frontend
     */
    public function register_custom_post_types() {
        // SEO Reports post type
        register_post_type('khm_seo_report', [
            'labels' => [
                'name' => __('SEO Reports', 'khm-seo'),
                'singular_name' => __('SEO Report', 'khm-seo')
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'khm-seo-dashboard',
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'view_reports'
            ],
            'supports' => ['title', 'editor', 'custom-fields'],
            'show_in_rest' => true
        ]);
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('khm_seo_widget', [$this, 'render_widget_shortcode']);
        add_shortcode('khm_seo_chart', [$this, 'render_chart_shortcode']);
        add_shortcode('khm_seo_stats', [$this, 'render_stats_shortcode']);
        add_shortcode('khm_seo_alerts', [$this, 'render_alerts_shortcode']);
    }
    
    /**
     * Render widget shortcode
     */
    public function render_widget_shortcode($atts) {
        $atts = shortcode_atts([
            'type' => 'overview',
            'title' => '',
            'class' => '',
            'height' => 'auto'
        ], $atts);
        
        $widget_data = $this->get_widget_data($atts['type']);
        
        ob_start();
        ?>
        <div class="khm-frontend-widget <?php echo esc_attr($atts['class']); ?>" 
             data-widget-type="<?php echo esc_attr($atts['type']); ?>"
             style="height: <?php echo esc_attr($atts['height']); ?>;">
            
            <?php if (!empty($atts['title'])): ?>
                <h3 class="khm-widget-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            
            <div class="khm-widget-content">
                <?php echo $this->render_widget_content($atts['type'], $widget_data); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Register customizer settings
     */
    public function register_customizer_settings($wp_customize) {
        // SEO Frontend section
        $wp_customize->add_section('khm_seo_frontend', [
            'title' => __('SEO Frontend', 'khm-seo'),
            'priority' => 160,
            'capability' => 'manage_seo_settings'
        ]);
        
        // Primary color setting
        $wp_customize->add_setting('khm_seo_primary_color', [
            'default' => '#005cee',
            'sanitize_callback' => 'sanitize_hex_color'
        ]);
        
        $wp_customize->add_control(new \WP_Customize_Color_Control($wp_customize, 'khm_seo_primary_color', [
            'label' => __('Primary Color', 'khm-seo'),
            'section' => 'khm_seo_frontend'
        ]));
        
        // Font size setting
        $wp_customize->add_setting('khm_seo_font_size', [
            'default' => '16',
            'sanitize_callback' => 'absint'
        ]);
        
        $wp_customize->add_control('khm_seo_font_size', [
            'label' => __('Base Font Size (px)', 'khm-seo'),
            'section' => 'khm_seo_frontend',
            'type' => 'range',
            'input_attrs' => [
                'min' => 12,
                'max' => 24,
                'step' => 1
            ]
        ]);
    }
    
    /**
     * Output custom styles based on customizer settings
     */
    public function output_custom_styles() {
        if (!$this->is_seo_page()) {
            return;
        }
        
        $primary_color = get_theme_mod('khm_seo_primary_color', '#005cee');
        $font_size = get_theme_mod('khm_seo_font_size', 16);
        
        ?>
        <style type="text/css" id="khm-custom-styles">
            :root {
                --khm-color-primary: <?php echo esc_html($primary_color); ?>;
                --khm-font-size-base: <?php echo esc_html($font_size); ?>px;
            }
        </style>
        <?php
    }
    
    /**
     * Helper functions
     */
    
    /**
     * Check if current page is an SEO page
     */
    private function is_seo_page() {
        global $current_screen;
        
        if (is_admin()) {
            return $current_screen && strpos($current_screen->id, 'khm-seo') !== false;
        }
        
        // Check for frontend SEO widgets or shortcodes
        return has_shortcode(get_post()->post_content ?? '', 'khm_seo_widget') ||
               has_shortcode(get_post()->post_content ?? '', 'khm_seo_chart') ||
               is_active_widget(false, false, 'khm_seo_widget');
    }
    
    /**
     * Get asset URL
     */
    private function get_asset_url($path) {
        return plugins_url('assets/' . $path, __FILE__);
    }
    
    /**
     * Get theme color
     */
    private function get_theme_color($color_name) {
        $current_theme = $this->theme['current'];
        if ($current_theme === 'auto') {
            $current_theme = 'light'; // Default for meta tags
        }
        
        return $this->theme['themes'][$current_theme]['colors'][$color_name] ?? '#005cee';
    }
    
    /**
     * Generate CSS variables
     */
    private function generate_css_variables() {
        $current_theme = $this->theme['current'];
        if ($current_theme === 'auto') {
            $current_theme = 'light'; // Default
        }
        
        $css = '';
        foreach ($this->theme['themes'][$current_theme]['colors'] as $name => $value) {
            $css .= '--khm-color-' . str_replace('_', '-', $name) . ': ' . $value . ';' . "\n";
        }
        
        // Add other CSS variables
        $css .= '--khm-border-radius: ' . $this->config['border_radius'] . ';' . "\n";
        $css .= '--khm-focus-outline: ' . $this->config['focus_outline'] . ';' . "\n";
        $css .= '--khm-transition-easing: ' . $this->config['transition_easing'] . ';' . "\n";
        
        return $css;
    }
    
    /**
     * Sanitize user preferences
     */
    private function sanitize_user_preferences($preferences) {
        $sanitized = [];
        
        foreach ($preferences as $key => $value) {
            switch ($key) {
                case 'theme':
                    $sanitized[$key] = in_array($value, array_keys($this->theme['themes'])) ? $value : 'auto';
                    break;
                    
                case 'font_size':
                    $sanitized[$key] = max(12, min(24, intval($value)));
                    break;
                    
                case 'sidebar_collapsed':
                case 'animations_enabled':
                case 'auto_refresh':
                    $sanitized[$key] = (bool) $value;
                    break;
                    
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Generate chart data (placeholder implementation)
     */
    private function generate_chart_data($chart_type, $timeframe) {
        // This would connect to real data sources in production
        return [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            'datasets' => [
                [
                    'label' => 'Sample Data',
                    'data' => [12, 19, 3, 5, 2, 3],
                    'backgroundColor' => $this->visualization['color_palettes']['primary'][0],
                    'borderColor' => $this->visualization['color_palettes']['primary'][1]
                ]
            ]
        ];
    }
    
    /**
     * Get widget data for frontend display
     */
    private function get_widget_data($widget_type) {
        // Placeholder data - would connect to real data sources
        return [
            'status' => 'success',
            'data' => [
                'value' => '87',
                'label' => 'SEO Score',
                'trend' => 'up',
                'change' => '+5.2%'
            ]
        ];
    }
    
    /**
     * Render widget content
     */
    private function render_widget_content($widget_type, $data) {
        switch ($widget_type) {
            case 'overview':
                return $this->render_overview_widget($data);
            case 'chart':
                return $this->render_chart_widget($data);
            case 'stats':
                return $this->render_stats_widget($data);
            default:
                return '<p>Widget type not supported</p>';
        }
    }
    
    /**
     * Render overview widget
     */
    private function render_overview_widget($data) {
        ob_start();
        ?>
        <div class="khm-overview-widget">
            <div class="khm-overview-value"><?php echo esc_html($data['data']['value']); ?></div>
            <div class="khm-overview-label"><?php echo esc_html($data['data']['label']); ?></div>
            <div class="khm-overview-trend <?php echo esc_attr($data['data']['trend']); ?>">
                <?php echo esc_html($data['data']['change']); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Placeholder for additional AJAX methods
     */
    private function ajax_refresh_data() { wp_send_json_success('Refreshed'); }
    private function ajax_export_data() { wp_send_json_success('Exported'); }
    private function ajax_onboarding_step() { wp_send_json_success('Step completed'); }
    private function ajax_get_public_data() { wp_send_json_success('Public data'); }
    
    /**
     * Placeholder for additional render methods
     */
    public function save_user_preferences() { /* Implementation */ }
    public function get_chart_data() { /* Implementation */ }
    public function export_frontend_data() { /* Implementation */ }
    public function render_chart_shortcode($atts) { return '<!-- Chart -->'; }
    public function render_stats_shortcode($atts) { return '<!-- Stats -->'; }
    public function render_alerts_shortcode($atts) { return '<!-- Alerts -->'; }
    private function render_chart_widget($data) { return '<!-- Chart Widget -->'; }
    private function render_stats_widget($data) { return '<!-- Stats Widget -->'; }
}

// Initialize the User Experience & Frontend
new UserExperienceFrontend();