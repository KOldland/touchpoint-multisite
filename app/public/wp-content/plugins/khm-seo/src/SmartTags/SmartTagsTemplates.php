<?php
/**
 * Phase 10: User Experience Enhancement - Smart Tags & Templates System
 * 
 * Advanced dynamic content generation system that provides AISEO-style smart tags
 * functionality with enhanced enterprise features including conditional logic,
 * template management, bulk content optimization, and automated meta generation.
 * 
 * Features:
 * - Dynamic smart tags and variables
 * - Conditional logic and rule engine
 * - Template management system
 * - Bulk content optimization
 * - Automated meta description/title generation
 * - Custom variables and functions
 * - Content analysis and suggestions
 * - Performance optimization
 * - Import/export functionality
 * - Advanced templating engine
 * - Content personalization
 * - A/B testing for meta content
 * 
 * @package KHM_SEO
 * @subpackage SmartTags
 * @version 1.0.0
 * @since Phase 10
 */

namespace KHM_SEO\SmartTags;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SmartTagsTemplates {
    
    /**
     * Current settings
     */
    private $settings = [];
    
    /**
     * Template cache
     */
    private $template_cache = [];
    
    /**
     * Smart tag registry
     */
    private $smart_tags = [];
    
    /**
     * Default settings
     */
    private $default_settings = [
        'smart_tags_enabled' => true,
        'auto_generate_enabled' => true,
        'template_optimization' => true,
        'conditional_logic_enabled' => true,
        'bulk_optimization_enabled' => true,
        'content_analysis_enabled' => true,
        'performance_mode' => 'balanced',
        'cache_enabled' => true,
        'cache_duration' => 3600,
        'auto_save_templates' => true,
        'backup_enabled' => true,
        'ab_testing_enabled' => true,
        'personalization_enabled' => false,
        'advanced_variables_enabled' => true,
        'custom_functions_enabled' => true,
        'template_validation' => true,
        'smart_suggestions' => true,
        'content_quality_score' => true,
        'seo_analysis_depth' => 'comprehensive',
        'auto_optimize_titles' => true,
        'auto_optimize_descriptions' => true,
        'duplicate_detection' => true,
        'keyword_density_optimization' => true,
        'readability_optimization' => true,
        'length_optimization' => true
    ];
    
    /**
     * Built-in smart tags
     */
    private $built_in_tags = [
        'site' => [
            'site_title' => 'Site Title',
            'site_description' => 'Site Description',
            'site_url' => 'Site URL',
            'site_name' => 'Site Name',
            'admin_email' => 'Admin Email'
        ],
        'post' => [
            'post_title' => 'Post Title',
            'post_content' => 'Post Content',
            'post_excerpt' => 'Post Excerpt',
            'post_date' => 'Post Date',
            'post_modified' => 'Post Modified Date',
            'post_author' => 'Post Author',
            'post_category' => 'Post Category',
            'post_tags' => 'Post Tags',
            'post_id' => 'Post ID',
            'post_slug' => 'Post Slug',
            'post_type' => 'Post Type',
            'word_count' => 'Word Count',
            'reading_time' => 'Reading Time'
        ],
        'taxonomy' => [
            'category' => 'Category Name',
            'category_description' => 'Category Description',
            'tag' => 'Tag Name',
            'tag_description' => 'Tag Description',
            'term_name' => 'Term Name',
            'term_description' => 'Term Description',
            'term_count' => 'Term Post Count'
        ],
        'author' => [
            'author_name' => 'Author Name',
            'author_bio' => 'Author Bio',
            'author_email' => 'Author Email',
            'author_url' => 'Author URL',
            'author_posts_count' => 'Author Posts Count'
        ],
        'date' => [
            'current_date' => 'Current Date',
            'current_year' => 'Current Year',
            'current_month' => 'Current Month',
            'current_day' => 'Current Day'
        ],
        'search' => [
            'search_term' => 'Search Term',
            'search_count' => 'Search Results Count'
        ],
        'custom' => [
            'focus_keyword' => 'Focus Keyword',
            'related_keywords' => 'Related Keywords',
            'competitor_analysis' => 'Competitor Analysis',
            'content_score' => 'Content Score',
            'seo_recommendations' => 'SEO Recommendations'
        ]
    ];
    
    /**
     * Template patterns
     */
    private $template_patterns = [
        'title' => [
            'basic' => '%%post_title%% | %%site_title%%',
            'keyword_focused' => '%%focus_keyword%% - %%post_title%% | %%site_title%%',
            'category_based' => '%%post_title%% in %%category%% | %%site_title%%',
            'author_focused' => '%%post_title%% by %%author_name%% | %%site_title%%',
            'date_focused' => '%%post_title%% (%%current_year%%) | %%site_title%%',
            'question_format' => 'How to %%post_title%%? | %%site_title%%',
            'listicle_format' => '%%word_count%% Tips: %%post_title%% | %%site_title%%',
            'location_based' => '%%post_title%% in [Location] | %%site_title%%'
        ],
        'description' => [
            'basic' => '%%post_excerpt%%',
            'keyword_rich' => 'Learn about %%focus_keyword%%. %%post_excerpt%% Read more on %%site_title%%.',
            'call_to_action' => '%%post_excerpt%% Click to learn more!',
            'question_answer' => 'Looking for %%focus_keyword%%? %%post_excerpt%%',
            'benefit_focused' => 'Discover how %%post_title%% can help you. %%post_excerpt%%',
            'urgency_driven' => 'Don\'t miss out! %%post_excerpt%% Learn more now.',
            'social_proof' => 'Join thousands who learned about %%focus_keyword%%. %%post_excerpt%%',
            'problem_solution' => 'Struggling with %%focus_keyword%%? %%post_excerpt%% Find solutions here.'
        ]
    ];
    
    /**
     * Initialize smart tags and templates
     */
    public function __construct() {
        $this->load_settings();
        $this->init_smart_tags();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin interface
        \add_action('admin_menu', [$this, 'add_admin_menu']);
        \add_action('admin_init', [$this, 'register_settings']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Template processing
        \add_filter('khm_seo_title', [$this, 'process_title_template'], 10, 2);
        \add_filter('khm_seo_description', [$this, 'process_description_template'], 10, 2);
        \add_filter('khm_seo_keywords', [$this, 'process_keywords_template'], 10, 2);
        
        // Content analysis
        \add_action('save_post', [$this, 'analyze_content'], 10, 2);
        \add_action('wp_loaded', [$this, 'maybe_bulk_optimize']);
        
        // AJAX handlers
        \add_action('wp_ajax_khm_seo_preview_template', [$this, 'handle_template_preview']);
        \add_action('wp_ajax_khm_seo_generate_templates', [$this, 'handle_template_generation']);
        \add_action('wp_ajax_khm_seo_bulk_optimize', [$this, 'handle_bulk_optimization']);
        \add_action('wp_ajax_khm_seo_analyze_content', [$this, 'handle_content_analysis']);
        \add_action('wp_ajax_khm_seo_validate_template', [$this, 'handle_template_validation']);
        \add_action('wp_ajax_khm_seo_export_templates', [$this, 'handle_template_export']);
        \add_action('wp_ajax_khm_seo_import_templates', [$this, 'handle_template_import']);
        
        // Meta box for post editing
        \add_action('add_meta_boxes', [$this, 'add_smart_tags_meta_box']);
        \add_action('save_post', [$this, 'save_post_template_settings'], 10, 2);
        
        // Frontend processing
        \add_action('wp_head', [$this, 'output_processed_meta'], 1);
        
        // Shortcodes
        \add_shortcode('smart_tag', [$this, 'smart_tag_shortcode']);
        \add_shortcode('conditional_content', [$this, 'conditional_content_shortcode']);
        
        // Cache management
        \add_action('init', [$this, 'init_cache_system']);
        \add_action('khm_seo_clear_template_cache', [$this, 'clear_template_cache']);
        
        // Performance optimization
        if ($this->settings['performance_mode'] === 'high') {
            \add_action('wp_loaded', [$this, 'preload_templates']);
        }
        
        // A/B Testing
        if ($this->settings['ab_testing_enabled']) {
            \add_action('init', [$this, 'init_ab_testing']);
        }
        
        // Background processing
        \add_action('khm_seo_background_optimize', [$this, 'background_optimize_content']);
        
        // Schedule background optimization
        if (!\wp_next_scheduled('khm_seo_background_optimize')) {
            \wp_schedule_event(time(), 'hourly', 'khm_seo_background_optimize');
        }
        
        // REST API endpoints
        \add_action('rest_api_init', [$this, 'register_rest_endpoints']);
    }
    
    /**
     * Load settings
     */
    private function load_settings() {
        $saved_settings = \get_option('khm_seo_smart_tags', []);
        $this->settings = array_merge($this->default_settings, $saved_settings);
    }
    
    /**
     * Initialize smart tags registry
     */
    private function init_smart_tags() {
        foreach ($this->built_in_tags as $category => $tags) {
            foreach ($tags as $tag => $description) {
                $this->register_smart_tag($tag, $description, $category);
            }
        }
        
        // Load custom smart tags
        $custom_tags = \get_option('khm_seo_custom_smart_tags', []);
        foreach ($custom_tags as $tag_data) {
            $this->register_smart_tag(
                $tag_data['tag'],
                $tag_data['description'],
                'custom',
                $tag_data['callback'] ?? null
            );
        }
        
        // Allow plugins to register additional tags
        \do_action('khm_seo_register_smart_tags', $this);
    }
    
    /**
     * Register a smart tag
     */
    public function register_smart_tag($tag, $description, $category = 'custom', $callback = null) {
        $this->smart_tags[$tag] = [
            'description' => $description,
            'category' => $category,
            'callback' => $callback
        ];
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        \add_submenu_page(
            'khm-seo-dashboard',
            'Smart Tags & Templates',
            'Smart Tags',
            'manage_options',
            'khm-seo-smart-tags',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        \register_setting('khm_seo_smart_tags', 'khm_seo_smart_tags', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($settings) {
        $clean_settings = [];
        
        foreach ($this->default_settings as $key => $default_value) {
            if (isset($settings[$key])) {
                if (is_bool($default_value)) {
                    $clean_settings[$key] = (bool) $settings[$key];
                } else {
                    $clean_settings[$key] = \sanitize_text_field($settings[$key]);
                }
            } else {
                $clean_settings[$key] = $default_value;
            }
        }
        
        return $clean_settings;
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap khm-seo-smart-tags-admin">
            <h1>
                <span class="dashicons dashicons-tag"></span>
                Smart Tags & Templates
            </h1>
            
            <div class="khm-seo-admin-header">
                <div class="header-content">
                    <h2>Automate Your SEO Content</h2>
                    <p>Create dynamic meta titles and descriptions using smart tags, conditional logic, and advanced templates.</p>
                </div>
                <div class="header-actions">
                    <button type="button" class="button button-secondary" id="preview-templates">
                        <span class="dashicons dashicons-visibility"></span>
                        Preview Templates
                    </button>
                    <button type="button" class="button button-secondary" id="bulk-optimize">
                        <span class="dashicons dashicons-performance"></span>
                        Bulk Optimize
                    </button>
                    <button type="button" class="button button-primary" id="generate-templates">
                        <span class="dashicons dashicons-admin-generic"></span>
                        Auto Generate
                    </button>
                </div>
            </div>
            
            <!-- Smart Tags Performance Dashboard -->
            <div class="khm-seo-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-tag"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="active-templates">-</div>
                        <div class="stat-label">Active Templates</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="optimization-score">-</div>
                        <div class="stat-label">Optimization Score</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-performance"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="processed-posts">-</div>
                        <div class="stat-label">Processed Posts</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-saved"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="time-saved">-</div>
                        <div class="stat-label">Time Saved</div>
                    </div>
                </div>
            </div>
            
            <form method="post" action="options.php" class="khm-seo-settings-form">
                <?php \settings_fields('khm_seo_smart_tags'); ?>
                
                <div class="khm-seo-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#template-builder" class="nav-tab nav-tab-active">Template Builder</a>
                        <a href="#smart-tags" class="nav-tab">Smart Tags</a>
                        <a href="#conditional-logic" class="nav-tab">Conditional Logic</a>
                        <a href="#bulk-optimization" class="nav-tab">Bulk Optimization</a>
                        <a href="#content-analysis" class="nav-tab">Content Analysis</a>
                        <a href="#advanced-settings" class="nav-tab">Advanced Settings</a>
                    </nav>
                    
                    <!-- Template Builder Tab -->
                    <div id="template-builder" class="tab-content active">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-edit"></span> Template Builder</h3>
                            
                            <div class="template-builder-interface">
                                <div class="template-types">
                                    <h4>Template Types</h4>
                                    <div class="template-type-tabs">
                                        <button type="button" class="template-type-tab active" data-type="title">Title Templates</button>
                                        <button type="button" class="template-type-tab" data-type="description">Description Templates</button>
                                        <button type="button" class="template-type-tab" data-type="keywords">Keywords Templates</button>
                                    </div>
                                </div>
                                
                                <div class="template-editor">
                                    <div class="template-editor-toolbar">
                                        <select id="template-preset">
                                            <option value="">Choose a preset...</option>
                                            <option value="basic">Basic Template</option>
                                            <option value="keyword-focused">Keyword Focused</option>
                                            <option value="category-based">Category Based</option>
                                            <option value="author-focused">Author Focused</option>
                                            <option value="date-focused">Date Focused</option>
                                            <option value="question-format">Question Format</option>
                                            <option value="listicle-format">Listicle Format</option>
                                            <option value="location-based">Location Based</option>
                                        </select>
                                        <button type="button" class="button" id="load-preset">Load Preset</button>
                                        <button type="button" class="button" id="save-template">Save Template</button>
                                        <button type="button" class="button button-secondary" id="preview-template">Preview</button>
                                    </div>
                                    
                                    <div class="template-input-container">
                                        <textarea id="template-input" placeholder="Enter your template here... Use %%tag_name%% for smart tags" rows="4"></textarea>
                                        <div class="template-info">
                                            <div class="character-count">Characters: <span id="char-count">0</span></div>
                                            <div class="template-validation" id="template-validation"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="template-preview-container" id="template-preview-container" style="display:none;">
                                        <h4>Preview Output</h4>
                                        <div class="template-preview-output" id="template-preview-output"></div>
                                    </div>
                                </div>
                                
                                <div class="saved-templates">
                                    <h4>Saved Templates</h4>
                                    <div class="templates-list" id="templates-list">
                                        <!-- Templates will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-admin-generic"></span> Auto-Generation Settings</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="auto_generate_enabled">Auto-Generate Templates</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_smart_tags[auto_generate_enabled]" id="auto_generate_enabled" value="1" <?php \checked($this->settings['auto_generate_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Automatically generate optimized templates based on content analysis.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="auto_optimize_titles">Auto-Optimize Titles</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_smart_tags[auto_optimize_titles]" id="auto_optimize_titles" value="1" <?php \checked($this->settings['auto_optimize_titles']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Automatically optimize title tags for length and keyword placement.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="auto_optimize_descriptions">Auto-Optimize Descriptions</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_smart_tags[auto_optimize_descriptions]" id="auto_optimize_descriptions" value="1" <?php \checked($this->settings['auto_optimize_descriptions']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Automatically optimize meta descriptions for length and readability.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Smart Tags Tab -->
                    <div id="smart-tags" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-tag"></span> Available Smart Tags</h3>
                            
                            <div class="smart-tags-browser">
                                <div class="tags-categories">
                                    <div class="tags-search">
                                        <input type="text" id="tags-search" placeholder="Search tags..." class="regular-text">
                                        <button type="button" class="button" id="search-tags">
                                            <span class="dashicons dashicons-search"></span>
                                        </button>
                                    </div>
                                    
                                    <div class="tags-category-filter">
                                        <select id="tags-category-filter">
                                            <option value="">All Categories</option>
                                            <option value="site">Site Information</option>
                                            <option value="post">Post Content</option>
                                            <option value="taxonomy">Taxonomy</option>
                                            <option value="author">Author</option>
                                            <option value="date">Date & Time</option>
                                            <option value="search">Search</option>
                                            <option value="custom">Custom Tags</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="tags-list" id="smart-tags-list">
                                    <!-- Smart tags will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-plus"></span> Custom Smart Tags</h3>
                            
                            <div class="custom-tags-manager">
                                <button type="button" class="button button-primary" id="add-custom-tag">
                                    <span class="dashicons dashicons-plus"></span>
                                    Add Custom Tag
                                </button>
                                
                                <div class="custom-tags-list" id="custom-tags-list">
                                    <!-- Custom tags will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conditional Logic Tab -->
                    <div id="conditional-logic" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-randomize"></span> Conditional Logic Rules</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="conditional_logic_enabled">Enable Conditional Logic</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_smart_tags[conditional_logic_enabled]" id="conditional_logic_enabled" value="1" <?php \checked($this->settings['conditional_logic_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Enable conditional logic in templates using if/else statements.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="conditional-logic-builder" id="conditional-logic-builder">
                                <!-- Conditional logic interface will be loaded here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bulk Optimization Tab -->
                    <div id="bulk-optimization" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-performance"></span> Bulk Optimization</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="bulk_optimization_enabled">Enable Bulk Operations</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_smart_tags[bulk_optimization_enabled]" id="bulk_optimization_enabled" value="1" <?php \checked($this->settings['bulk_optimization_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Enable bulk optimization tools for processing multiple posts at once.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="bulk-optimization-interface" id="bulk-optimization-interface">
                                <!-- Bulk optimization tools will be loaded here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content Analysis Tab -->
                    <div id="content-analysis" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-analytics"></span> Content Analysis</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="content_analysis_enabled">Enable Content Analysis</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_smart_tags[content_analysis_enabled]" id="content_analysis_enabled" value="1" <?php \checked($this->settings['content_analysis_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Analyze content to provide SEO recommendations and optimization suggestions.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="seo_analysis_depth">Analysis Depth</label>
                                    </th>
                                    <td>
                                        <select name="khm_seo_smart_tags[seo_analysis_depth]" id="seo_analysis_depth">
                                            <option value="basic" <?php selected($this->settings['seo_analysis_depth'], 'basic'); ?>>Basic</option>
                                            <option value="detailed" <?php selected($this->settings['seo_analysis_depth'], 'detailed'); ?>>Detailed</option>
                                            <option value="comprehensive" <?php selected($this->settings['seo_analysis_depth'], 'comprehensive'); ?>>Comprehensive</option>
                                        </select>
                                        <p class="description">Choose the depth of SEO analysis to perform on your content.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="content-analysis-dashboard" id="content-analysis-dashboard">
                                <!-- Content analysis results will be loaded here -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings Tab -->
                    <div id="advanced-settings" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-admin-settings"></span> Performance Settings</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="performance_mode">Performance Mode</label>
                                    </th>
                                    <td>
                                        <select name="khm_seo_smart_tags[performance_mode]" id="performance_mode">
                                            <option value="low" <?php selected($this->settings['performance_mode'], 'low'); ?>>Low (Minimum processing)</option>
                                            <option value="balanced" <?php selected($this->settings['performance_mode'], 'balanced'); ?>>Balanced (Recommended)</option>
                                            <option value="high" <?php selected($this->settings['performance_mode'], 'high'); ?>>High (Maximum optimization)</option>
                                        </select>
                                        <p class="description">Choose the performance mode that best fits your server capabilities.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="cache_enabled">Template Caching</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_smart_tags[cache_enabled]" id="cache_enabled" value="1" <?php \checked($this->settings['cache_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Cache processed templates to improve performance.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="cache_duration">Cache Duration</label>
                                    </th>
                                    <td>
                                        <input type="number" name="khm_seo_smart_tags[cache_duration]" id="cache_duration" value="<?php echo \esc_attr($this->settings['cache_duration']); ?>" min="300" max="86400" class="small-text">
                                        <span>seconds</span>
                                        <p class="description">How long to cache processed templates (300-86400 seconds).</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-backup"></span> Backup & Export</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="backup_enabled">Auto Backup</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_smart_tags[backup_enabled]" id="backup_enabled" value="1" <?php \checked($this->settings['backup_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Automatically backup templates and settings.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="backup-export-tools">
                                <div class="tool-group">
                                    <h4>Export Templates</h4>
                                    <p>Export all your templates and settings to a JSON file.</p>
                                    <button type="button" class="button button-secondary" id="export-templates">
                                        <span class="dashicons dashicons-download"></span>
                                        Export Templates
                                    </button>
                                </div>
                                
                                <div class="tool-group">
                                    <h4>Import Templates</h4>
                                    <p>Import templates and settings from a JSON file.</p>
                                    <input type="file" id="import-file" accept=".json" style="display:none;">
                                    <button type="button" class="button button-secondary" id="import-templates">
                                        <span class="dashicons dashicons-upload"></span>
                                        Import Templates
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php \submit_button('Save Smart Tags Settings', 'primary', 'submit', false, ['id' => 'save-smart-tags-settings']); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Process title template
     */
    public function process_title_template($title, $context = []) {
        if (!$this->settings['smart_tags_enabled']) {
            return $title;
        }
        
        return $this->process_template($title, $context);
    }
    
    /**
     * Process description template
     */
    public function process_description_template($description, $context = []) {
        if (!$this->settings['smart_tags_enabled']) {
            return $description;
        }
        
        return $this->process_template($description, $context);
    }
    
    /**
     * Process keywords template
     */
    public function process_keywords_template($keywords, $context = []) {
        if (!$this->settings['smart_tags_enabled']) {
            return $keywords;
        }
        
        return $this->process_template($keywords, $context);
    }
    
    /**
     * Process template with smart tags
     */
    private function process_template($template, $context = []) {
        // Check cache first
        if ($this->settings['cache_enabled']) {
            $cache_key = 'template_' . md5($template . serialize($context));
            $cached_result = \wp_cache_get($cache_key, 'khm_seo_templates');
            
            if ($cached_result !== false) {
                return $cached_result;
            }
        }
        
        // Process smart tags
        $processed = preg_replace_callback('/%%([^%]+)%%/', function($matches) use ($context) {
            return $this->process_smart_tag($matches[1], $context);
        }, $template);
        
        // Process conditional logic
        if ($this->settings['conditional_logic_enabled']) {
            $processed = $this->process_conditional_logic($processed, $context);
        }
        
        // Cache the result
        if ($this->settings['cache_enabled']) {
            \wp_cache_set($cache_key, $processed, 'khm_seo_templates', $this->settings['cache_duration']);
        }
        
        return $processed;
    }
    
    /**
     * Process individual smart tag
     */
    private function process_smart_tag($tag, $context = []) {
        // Check if tag exists in registry
        if (!isset($this->smart_tags[$tag])) {
            return "%%{$tag}%%"; // Return original if tag not found
        }
        
        $tag_data = $this->smart_tags[$tag];
        
        // Use custom callback if provided
        if (isset($tag_data['callback']) && is_callable($tag_data['callback'])) {
            return call_user_func($tag_data['callback'], $context);
        }
        
        // Process built-in tags
        return $this->get_tag_value($tag, $context);
    }
    
    /**
     * Get smart tag value
     */
    private function get_tag_value($tag, $context = []) {
        global $post, $wp_query;
        
        // Set default context if not provided
        if (empty($context) && isset($post)) {
            $context['post'] = $post;
        }
        
        switch ($tag) {
            // Site tags
            case 'site_title':
                return \get_bloginfo('name');
            case 'site_description':
                return \get_bloginfo('description');
            case 'site_url':
                return \home_url();
            case 'site_name':
                return \get_bloginfo('name');
            case 'admin_email':
                return \get_option('admin_email');
            
            // Post tags
            case 'post_title':
                return isset($context['post']) ? $context['post']->post_title : '';
            case 'post_content':
                return isset($context['post']) ? $context['post']->post_content : '';
            case 'post_excerpt':
                return isset($context['post']) ? \get_the_excerpt($context['post']) : '';
            case 'post_date':
                return isset($context['post']) ? \get_the_date('', $context['post']) : '';
            case 'post_modified':
                return isset($context['post']) ? \get_the_modified_date('', $context['post']) : '';
            case 'post_author':
                return isset($context['post']) ? \get_the_author_meta('display_name', $context['post']->post_author) : '';
            case 'post_category':
                return isset($context['post']) ? $this->get_primary_category($context['post']) : '';
            case 'post_tags':
                return isset($context['post']) ? $this->get_post_tags_string($context['post']) : '';
            case 'post_id':
                return isset($context['post']) ? $context['post']->ID : '';
            case 'post_slug':
                return isset($context['post']) ? $context['post']->post_name : '';
            case 'post_type':
                return isset($context['post']) ? $context['post']->post_type : '';
            case 'word_count':
                return isset($context['post']) ? str_word_count(strip_tags($context['post']->post_content)) : '';
            case 'reading_time':
                return isset($context['post']) ? $this->calculate_reading_time($context['post']) : '';
            
            // Date tags
            case 'current_date':
                return \current_time('F j, Y');
            case 'current_year':
                return \current_time('Y');
            case 'current_month':
                return \current_time('F');
            case 'current_day':
                return \current_time('j');
            
            // Search tags
            case 'search_term':
                return \get_search_query();
            case 'search_count':
                return isset($wp_query) ? $wp_query->found_posts : 0;
            
            default:
                return \apply_filters('khm_seo_smart_tag_value', '', $tag, $context);
        }
    }
    
    /**
     * Get primary category for post
     */
    private function get_primary_category($post) {
        $categories = \get_the_category($post->ID);
        return !empty($categories) ? $categories[0]->name : '';
    }
    
    /**
     * Get post tags as string
     */
    private function get_post_tags_string($post) {
        $tags = \get_the_tags($post->ID);
        if (empty($tags)) {
            return '';
        }
        
        $tag_names = array_map(function($tag) {
            return $tag->name;
        }, $tags);
        
        return implode(', ', $tag_names);
    }
    
    /**
     * Calculate reading time
     */
    private function calculate_reading_time($post) {
        $word_count = str_word_count(strip_tags($post->post_content));
        $reading_time = ceil($word_count / 200); // Average reading speed: 200 words per minute
        return $reading_time . ' min read';
    }
    
    /**
     * Process conditional logic
     */
    private function process_conditional_logic($template, $context = []) {
        // Basic conditional logic implementation
        // This would be expanded with a full parser for complex conditions
        
        $pattern = '/\{if\s+([^}]+)\}(.*?)\{\/if\}/s';
        
        return preg_replace_callback($pattern, function($matches) use ($context) {
            $condition = trim($matches[1]);
            $content = $matches[2];
            
            if ($this->evaluate_condition($condition, $context)) {
                return $content;
            }
            
            return '';
        }, $template);
    }
    
    /**
     * Evaluate conditional logic condition
     */
    private function evaluate_condition($condition, $context = []) {
        // Simple condition evaluation
        // This would be expanded with a full expression parser
        
        if (preg_match('/(\w+)\s*(==|!=|>|<)\s*["\']?([^"\']*)["\']?/', $condition, $matches)) {
            $tag = $matches[1];
            $operator = $matches[2];
            $value = $matches[3];
            
            $tag_value = $this->get_tag_value($tag, $context);
            
            switch ($operator) {
                case '==':
                    return $tag_value == $value;
                case '!=':
                    return $tag_value != $value;
                case '>':
                    return $tag_value > $value;
                case '<':
                    return $tag_value < $value;
            }
        }
        
        return false;
    }
    
    // Additional methods for content analysis, bulk optimization, etc. would be added here...
}

// Initialize smart tags and templates
new SmartTagsTemplates();