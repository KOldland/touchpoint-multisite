<?php
/**
 * Phase 10: User Experience Enhancement - Breadcrumbs System
 * 
 * Comprehensive breadcrumb navigation system that provides AISEO-style
 * breadcrumb functionality with enhanced enterprise features including
 * schema markup, customizable templates, and SEO optimization.
 * 
 * Features:
 * - Automatic breadcrumb generation for all content types
 * - JSON-LD schema markup for enhanced SEO
 * - Customizable breadcrumb templates and styling
 * - WordPress multisite and multilingual support
 * - Custom post type and taxonomy support
 * - Performance optimized with caching
 * - Advanced filtering and customization hooks
 * - Accessibility compliance (ARIA labels)
 * - Mobile-responsive design
 * - Analytics tracking integration
 * 
 * @package KHM_SEO
 * @subpackage Breadcrumbs
 * @version 1.0.0
 * @since Phase 10
 */

namespace KHM_SEO\Breadcrumbs;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BreadcrumbSystem {
    
    /**
     * Breadcrumb items cache
     */
    private $breadcrumb_items = [];
    
    /**
     * Current breadcrumb settings
     */
    private $settings = [];
    
    /**
     * Default breadcrumb settings
     */
    private $default_settings = [
        'enabled' => true,
        'show_home' => true,
        'home_text' => 'Home',
        'separator' => '&raquo;',
        'show_current' => true,
        'link_current' => false,
        'show_prefix' => true,
        'prefix_blog' => 'Blog',
        'prefix_search' => 'Search Results for',
        'prefix_404' => '404 Not Found',
        'prefix_archive' => 'Archive',
        'prefix_author' => 'Author',
        'prefix_category' => 'Category',
        'prefix_tag' => 'Tag',
        'prefix_date' => 'Archive',
        'max_length' => 50,
        'enable_schema' => true,
        'enable_cache' => true,
        'cache_duration' => 3600,
        'display_location' => 'auto',
        'custom_css_class' => '',
        'enable_analytics' => true,
        'show_post_type' => true,
        'show_taxonomy' => true,
        'hierarchical_categories' => true,
        'remove_stopwords' => false
    ];
    
    /**
     * Initialize breadcrumb system
     */
    public function __construct() {
        $this->load_settings();
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
        
        // Frontend display
        \add_action('wp', [$this, 'maybe_display_breadcrumbs']);
        \add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Theme integration
        \add_action('after_setup_theme', [$this, 'add_theme_support']);
        
        // AJAX handlers
        \add_action('wp_ajax_khm_seo_breadcrumb_preview', [$this, 'handle_breadcrumb_preview']);
        \add_action('wp_ajax_khm_seo_breadcrumb_settings', [$this, 'handle_settings_save']);
        
        // Shortcode
        \add_shortcode('khm_breadcrumbs', [$this, 'breadcrumb_shortcode']);
        
        // Widget
        \add_action('widgets_init', [$this, 'register_breadcrumb_widget']);
        
        // REST API
        \add_action('rest_api_init', [$this, 'register_rest_endpoints']);
        
        // Cache management
        \add_action('post_updated', [$this, 'clear_breadcrumb_cache']);
        \add_action('deleted_post', [$this, 'clear_breadcrumb_cache']);
        \add_action('wp_update_nav_menu', [$this, 'clear_breadcrumb_cache']);
        
        // Filter hooks for customization
        \add_filter('khm_seo_breadcrumb_items', [$this, 'apply_custom_filters'], 10, 2);
        \add_filter('khm_seo_breadcrumb_schema', [$this, 'apply_schema_filters'], 10, 2);
    }
    
    /**
     * Load breadcrumb settings
     */
    private function load_settings() {
        $saved_settings = \get_option('khm_seo_breadcrumbs', []);
        $this->settings = array_merge($this->default_settings, $saved_settings);
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        \add_submenu_page(
            'khm-seo-dashboard',
            'Breadcrumbs Settings',
            'Breadcrumbs',
            'manage_options',
            'khm-seo-breadcrumbs',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        \register_setting('khm_seo_breadcrumbs', 'khm_seo_breadcrumbs', [
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
                switch (gettype($default_value)) {
                    case 'boolean':
                        $clean_settings[$key] = (bool) $settings[$key];
                        break;
                    case 'integer':
                        $clean_settings[$key] = (int) $settings[$key];
                        break;
                    default:
                        $clean_settings[$key] = \sanitize_text_field($settings[$key]);
                        break;
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
        <div class="wrap khm-seo-breadcrumbs-admin">
            <h1>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
                Breadcrumb Navigation Settings
            </h1>
            
            <div class="khm-seo-admin-header">
                <div class="header-content">
                    <h2>Configure Your Site Navigation</h2>
                    <p>Set up breadcrumb navigation to improve user experience and SEO. Breadcrumbs help visitors understand their location on your site and provide search engines with additional context.</p>
                </div>
                <div class="header-actions">
                    <button type="button" class="button button-secondary" id="preview-breadcrumbs">
                        <span class="dashicons dashicons-visibility"></span>
                        Preview
                    </button>
                    <button type="button" class="button button-secondary" id="reset-settings">
                        <span class="dashicons dashicons-backup"></span>
                        Reset to Defaults
                    </button>
                </div>
            </div>
            
            <form method="post" action="options.php" class="khm-seo-settings-form">
                <?php \settings_fields('khm_seo_breadcrumbs'); ?>
                
                <div class="khm-seo-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#general" class="nav-tab nav-tab-active">General Settings</a>
                        <a href="#display" class="nav-tab">Display Options</a>
                        <a href="#schema" class="nav-tab">Schema & SEO</a>
                        <a href="#advanced" class="nav-tab">Advanced</a>
                        <a href="#templates" class="nav-tab">Templates</a>
                    </nav>
                    
                    <!-- General Settings Tab -->
                    <div id="general" class="tab-content active">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-admin-settings"></span> Basic Configuration</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="enabled">Enable Breadcrumbs</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_breadcrumbs[enabled]" id="enabled" value="1" <?php \checked($this->settings['enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Enable breadcrumb navigation throughout your site.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="display_location">Display Location</label>
                                    </th>
                                    <td>
                                        <select name="khm_seo_breadcrumbs[display_location]" id="display_location" class="regular-text">
                                            <option value="auto" <?php \selected($this->settings['display_location'], 'auto'); ?>>Automatic (Before Content)</option>
                                            <option value="manual" <?php \selected($this->settings['display_location'], 'manual'); ?>>Manual (Shortcode/Function Only)</option>
                                            <option value="header" <?php \selected($this->settings['display_location'], 'header'); ?>>In Header</option>
                                            <option value="after_title" <?php \selected($this->settings['display_location'], 'after_title'); ?>>After Page Title</option>
                                        </select>
                                        <p class="description">Choose where breadcrumbs should appear on your site.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="home_text">Home Text</label>
                                    </th>
                                    <td>
                                        <input type="text" name="khm_seo_breadcrumbs[home_text]" id="home_text" value="<?php echo \esc_attr($this->settings['home_text']); ?>" class="regular-text">
                                        <p class="description">Text to display for the home page link.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="separator">Separator</label>
                                    </th>
                                    <td>
                                        <div class="separator-options">
                                            <?php
                                            $separators = [
                                                '&raquo;' => '»',
                                                '&rsaquo;' => '›',
                                                '/' => '/',
                                                '|' => '|',
                                                '&gt;' => '>',
                                                '&rarr;' => '→',
                                                '&bull;' => '•',
                                                'custom' => 'Custom'
                                            ];
                                            
                                            foreach ($separators as $value => $display) {
                                                $checked = $this->settings['separator'] === $value ? 'checked' : '';
                                                echo "<label class='separator-option'>";
                                                echo "<input type='radio' name='khm_seo_breadcrumbs[separator]' value='{$value}' {$checked}>";
                                                echo "<span class='separator-display'>{$display}</span>";
                                                echo "</label>";
                                            }
                                            ?>
                                        </div>
                                        <input type="text" name="khm_seo_breadcrumbs[custom_separator]" id="custom_separator" value="<?php echo \esc_attr($this->settings['custom_separator'] ?? ''); ?>" class="regular-text custom-separator-input" placeholder="Enter custom separator" style="display: none;">
                                        <p class="description">Choose the separator to display between breadcrumb items.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-visibility"></span> Visibility Options</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Show Options</th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="khm_seo_breadcrumbs[show_home]" value="1" <?php \checked($this->settings['show_home']); ?>>
                                                Show home page link
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="khm_seo_breadcrumbs[show_current]" value="1" <?php \checked($this->settings['show_current']); ?>>
                                                Show current page/post
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="khm_seo_breadcrumbs[link_current]" value="1" <?php \checked($this->settings['link_current']); ?>>
                                                Link current page/post
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="khm_seo_breadcrumbs[show_post_type]" value="1" <?php \checked($this->settings['show_post_type']); ?>>
                                                Show post type archives
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="khm_seo_breadcrumbs[show_taxonomy]" value="1" <?php \checked($this->settings['show_taxonomy']); ?>>
                                                Show taxonomy terms
                                            </label><br>
                                            
                                            <label>
                                                <input type="checkbox" name="khm_seo_breadcrumbs[hierarchical_categories]" value="1" <?php \checked($this->settings['hierarchical_categories']); ?>>
                                                Show full category hierarchy
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Display Options Tab -->
                    <div id="display" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-admin-appearance"></span> Styling & Appearance</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="custom_css_class">Custom CSS Class</label>
                                    </th>
                                    <td>
                                        <input type="text" name="khm_seo_breadcrumbs[custom_css_class]" id="custom_css_class" value="<?php echo \esc_attr($this->settings['custom_css_class']); ?>" class="regular-text">
                                        <p class="description">Add custom CSS classes to the breadcrumb container.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="max_length">Text Length Limit</label>
                                    </th>
                                    <td>
                                        <input type="number" name="khm_seo_breadcrumbs[max_length]" id="max_length" value="<?php echo \esc_attr($this->settings['max_length']); ?>" min="10" max="200" class="small-text">
                                        <span class="description">characters maximum for breadcrumb item text (0 = no limit)</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-editor-spellcheck"></span> Text Prefixes</h3>
                            <p class="section-description">Customize the prefixes shown for different page types.</p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="prefix_blog">Blog Prefix</label>
                                    </th>
                                    <td>
                                        <input type="text" name="khm_seo_breadcrumbs[prefix_blog]" id="prefix_blog" value="<?php echo \esc_attr($this->settings['prefix_blog']); ?>" class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="prefix_search">Search Prefix</label>
                                    </th>
                                    <td>
                                        <input type="text" name="khm_seo_breadcrumbs[prefix_search]" id="prefix_search" value="<?php echo \esc_attr($this->settings['prefix_search']); ?>" class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="prefix_404">404 Prefix</label>
                                    </th>
                                    <td>
                                        <input type="text" name="khm_seo_breadcrumbs[prefix_404]" id="prefix_404" value="<?php echo \esc_attr($this->settings['prefix_404']); ?>" class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="prefix_author">Author Prefix</label>
                                    </th>
                                    <td>
                                        <input type="text" name="khm_seo_breadcrumbs[prefix_author]" id="prefix_author" value="<?php echo \esc_attr($this->settings['prefix_author']); ?>" class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="prefix_archive">Archive Prefix</label>
                                    </th>
                                    <td>
                                        <input type="text" name="khm_seo_breadcrumbs[prefix_archive]" id="prefix_archive" value="<?php echo \esc_attr($this->settings['prefix_archive']); ?>" class="regular-text">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Schema & SEO Tab -->
                    <div id="schema" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-search"></span> SEO & Schema Markup</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="enable_schema">JSON-LD Schema</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_breadcrumbs[enable_schema]" id="enable_schema" value="1" <?php \checked($this->settings['enable_schema']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Add JSON-LD structured data for breadcrumbs to improve search engine understanding.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="enable_analytics">Analytics Tracking</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_breadcrumbs[enable_analytics]" id="enable_analytics" value="1" <?php \checked($this->settings['enable_analytics']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Track breadcrumb clicks in Google Analytics and other tracking platforms.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="remove_stopwords">Remove Stop Words</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_breadcrumbs[remove_stopwords]" id="remove_stopwords" value="1" <?php \checked($this->settings['remove_stopwords']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Remove common words (a, the, and, etc.) from breadcrumb text for cleaner URLs.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-performance"></span> Performance</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="enable_cache">Enable Caching</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_breadcrumbs[enable_cache]" id="enable_cache" value="1" <?php \checked($this->settings['enable_cache']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Cache breadcrumb data to improve performance on high-traffic sites.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="cache_duration">Cache Duration</label>
                                    </th>
                                    <td>
                                        <select name="khm_seo_breadcrumbs[cache_duration]" id="cache_duration" class="regular-text">
                                            <option value="900" <?php \selected($this->settings['cache_duration'], 900); ?>>15 minutes</option>
                                            <option value="1800" <?php \selected($this->settings['cache_duration'], 1800); ?>>30 minutes</option>
                                            <option value="3600" <?php \selected($this->settings['cache_duration'], 3600); ?>>1 hour</option>
                                            <option value="7200" <?php \selected($this->settings['cache_duration'], 7200); ?>>2 hours</option>
                                            <option value="21600" <?php \selected($this->settings['cache_duration'], 21600); ?>>6 hours</option>
                                            <option value="86400" <?php \selected($this->settings['cache_duration'], 86400); ?>>24 hours</option>
                                        </select>
                                        <p class="description">How long to cache breadcrumb data.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Advanced Tab -->
                    <div id="advanced" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-admin-tools"></span> Advanced Configuration</h3>
                            
                            <div class="code-example">
                                <h4>Shortcode Usage</h4>
                                <p>Display breadcrumbs anywhere using the shortcode:</p>
                                <code>[khm_breadcrumbs]</code>
                                
                                <h4>PHP Function</h4>
                                <p>Display breadcrumbs in your theme files:</p>
                                <code>&lt;?php if (function_exists('khm_seo_breadcrumbs')) khm_seo_breadcrumbs(); ?&gt;</code>
                                
                                <h4>Custom Hooks</h4>
                                <p>Available filter hooks for developers:</p>
                                <ul>
                                    <li><code>khm_seo_breadcrumb_items</code> - Modify breadcrumb items</li>
                                    <li><code>khm_seo_breadcrumb_schema</code> - Customize schema markup</li>
                                    <li><code>khm_seo_breadcrumb_html</code> - Modify output HTML</li>
                                    <li><code>khm_seo_breadcrumb_separator</code> - Custom separator</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Templates Tab -->
                    <div id="templates" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-editor-code"></span> Custom Templates</h3>
                            
                            <div class="template-editor">
                                <p>Customize the HTML output for different breadcrumb elements:</p>
                                
                                <h4>Container Template</h4>
                                <textarea name="khm_seo_breadcrumbs[template_container]" class="large-text code-editor" rows="5" placeholder="<nav class='breadcrumbs'>{breadcrumbs}</nav>"><?php echo \esc_textarea($this->settings['template_container'] ?? ''); ?></textarea>
                                
                                <h4>Item Template</h4>
                                <textarea name="khm_seo_breadcrumbs[template_item]" class="large-text code-editor" rows="3" placeholder="<span class='breadcrumb-item'><a href='{url}'>{title}</a></span>"><?php echo \esc_textarea($this->settings['template_item'] ?? ''); ?></textarea>
                                
                                <h4>Current Item Template</h4>
                                <textarea name="khm_seo_breadcrumbs[template_current]" class="large-text code-editor" rows="3" placeholder="<span class='breadcrumb-item current'>{title}</span>"><?php echo \esc_textarea($this->settings['template_current'] ?? ''); ?></textarea>
                                
                                <h4>Separator Template</h4>
                                <textarea name="khm_seo_breadcrumbs[template_separator]" class="large-text code-editor" rows="2" placeholder="<span class='breadcrumb-separator'>{separator}</span>"><?php echo \esc_textarea($this->settings['template_separator'] ?? ''); ?></textarea>
                                
                                <p class="description">Available variables: {url}, {title}, {separator}, {breadcrumbs}, {position}</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php \submit_button('Save Settings', 'primary', 'submit', false, ['id' => 'save-breadcrumb-settings']); ?>
            </form>
            
            <!-- Live Preview Panel -->
            <div class="khm-seo-preview-panel" id="breadcrumb-preview-panel" style="display: none;">
                <div class="preview-header">
                    <h3>Breadcrumb Preview</h3>
                    <button type="button" class="close-preview">&times;</button>
                </div>
                <div class="preview-content">
                    <div class="preview-example" id="breadcrumb-preview-content">
                        <!-- Preview content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'seo_page_khm-seo-breadcrumbs') {
            return;
        }
        
        \wp_enqueue_style(
            'khm-seo-breadcrumbs-admin',
            \plugins_url('assets/css/breadcrumbs-admin.css', KHM_SEO_PLUGIN_FILE),
            [],
            KHM_SEO_VERSION
        );
        
        \wp_enqueue_script(
            'khm-seo-breadcrumbs-admin',
            \plugins_url('assets/js/breadcrumbs-admin.js', KHM_SEO_PLUGIN_FILE),
            ['jquery', 'wp-util'],
            KHM_SEO_VERSION,
            true
        );
        
        \wp_localize_script('khm-seo-breadcrumbs-admin', 'khmSeoBreadcrumbs', [
            'ajax_url' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('khm_seo_breadcrumbs_nonce'),
            'settings' => $this->settings
        ]);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!$this->settings['enabled']) {
            return;
        }
        
        \wp_enqueue_style(
            'khm-seo-breadcrumbs',
            \plugins_url('assets/css/breadcrumbs.css', KHM_SEO_PLUGIN_FILE),
            [],
            KHM_SEO_VERSION
        );
        
        if ($this->settings['enable_analytics']) {
            \wp_enqueue_script(
                'khm-seo-breadcrumbs',
                \plugins_url('assets/js/breadcrumbs.js', KHM_SEO_PLUGIN_FILE),
                ['jquery'],
                KHM_SEO_VERSION,
                true
            );
            
            \wp_localize_script('khm-seo-breadcrumbs', 'khmSeoBreadcrumbsConfig', [
                'track_clicks' => $this->settings['enable_analytics'],
                'ga_category' => 'Breadcrumbs',
                'ga_action' => 'click'
            ]);
        }
    }
    
    /**
     * Maybe display breadcrumbs automatically
     */
    public function maybe_display_breadcrumbs() {
        if (!$this->settings['enabled'] || $this->settings['display_location'] !== 'auto') {
            return;
        }
        
        if (\is_front_page() || \is_home()) {
            return; // Don't show on homepage
        }
        
        \add_filter('the_content', [$this, 'prepend_breadcrumbs'], 5);
    }
    
    /**
     * Prepend breadcrumbs to content
     */
    public function prepend_breadcrumbs($content) {
        if (\is_main_query() && \in_the_loop()) {
            $breadcrumbs = $this->generate_breadcrumbs();
            return $breadcrumbs . $content;
        }
        
        return $content;
    }
    
    /**
     * Generate breadcrumbs HTML
     */
    public function generate_breadcrumbs($args = []) {
        $settings = array_merge($this->settings, $args);
        
        // Check cache first
        if ($settings['enable_cache']) {
            $cache_key = $this->get_cache_key();
            $cached_breadcrumbs = \get_transient($cache_key);
            
            if ($cached_breadcrumbs !== false) {
                return $cached_breadcrumbs;
            }
        }
        
        // Generate breadcrumb items
        $items = $this->get_breadcrumb_items();
        
        if (empty($items)) {
            return '';
        }
        
        // Build HTML output
        $html = $this->build_breadcrumb_html($items, $settings);
        
        // Add schema markup
        if ($settings['enable_schema']) {
            $html .= $this->generate_schema_markup($items);
        }
        
        // Cache the result
        if ($settings['enable_cache']) {
            \set_transient($cache_key, $html, $settings['cache_duration']);
        }
        
        return $html;
    }
    
    /**
     * Get breadcrumb items for current page
     */
    private function get_breadcrumb_items() {
        $items = [];
        
        // Add home link
        if ($this->settings['show_home']) {
            $items[] = [
                'title' => $this->settings['home_text'],
                'url' => \home_url('/'),
                'position' => 1
            ];
        }
        
        // Determine current page type and build breadcrumbs accordingly
        if (\is_single() || \is_page()) {
            $items = array_merge($items, $this->get_post_breadcrumbs());
        } elseif (\is_category()) {
            $items = array_merge($items, $this->get_category_breadcrumbs());
        } elseif (\is_tag()) {
            $items = array_merge($items, $this->get_tag_breadcrumbs());
        } elseif (\is_author()) {
            $items = array_merge($items, $this->get_author_breadcrumbs());
        } elseif (\is_date()) {
            $items = array_merge($items, $this->get_date_breadcrumbs());
        } elseif (\is_search()) {
            $items = array_merge($items, $this->get_search_breadcrumbs());
        } elseif (\is_404()) {
            $items = array_merge($items, $this->get_404_breadcrumbs());
        } elseif (\is_post_type_archive()) {
            $items = array_merge($items, $this->get_post_type_archive_breadcrumbs());
        } elseif (\is_tax()) {
            $items = array_merge($items, $this->get_taxonomy_breadcrumbs());
        }
        
        // Apply filters
        $items = \apply_filters('khm_seo_breadcrumb_items', $items, $this);
        
        // Add positions
        foreach ($items as $index => &$item) {
            if (!isset($item['position'])) {
                $item['position'] = $index + 1;
            }
        }
        
        return $items;
    }
    
    /**
     * Get breadcrumbs for single post/page
     */
    private function get_post_breadcrumbs() {
        global $post;
        $items = [];
        
        if (!$post) {
            return $items;
        }
        
        // Add post type archive if not 'post'
        if ($post->post_type !== 'post' && $this->settings['show_post_type']) {
            $post_type_obj = \get_post_type_object($post->post_type);
            if ($post_type_obj && $post_type_obj->has_archive) {
                $items[] = [
                    'title' => $post_type_obj->labels->name,
                    'url' => \get_post_type_archive_link($post->post_type),
                    'type' => 'post_type_archive'
                ];
            }
        }
        
        // Add categories/taxonomies
        if ($this->settings['show_taxonomy']) {
            $taxonomy_items = $this->get_post_taxonomy_breadcrumbs($post);
            $items = array_merge($items, $taxonomy_items);
        }
        
        // Add parent pages for hierarchical post types
        if (\is_page() && $post->post_parent) {
            $parent_items = $this->get_parent_pages($post->post_parent);
            $items = array_merge($items, $parent_items);
        }
        
        // Add current post/page
        if ($this->settings['show_current']) {
            $items[] = [
                'title' => \get_the_title($post),
                'url' => $this->settings['link_current'] ? \get_permalink($post) : '',
                'type' => 'current',
                'current' => true
            ];
        }
        
        return $items;
    }
    
    /**
     * Get taxonomy breadcrumbs for a post
     */
    private function get_post_taxonomy_breadcrumbs($post) {
        $items = [];
        
        // Get primary category for posts
        if ($post->post_type === 'post') {
            $categories = \get_the_category($post->ID);
            if (!empty($categories)) {
                $primary_category = $categories[0];
                
                // Add category hierarchy if enabled
                if ($this->settings['hierarchical_categories']) {
                    $category_items = $this->get_category_hierarchy($primary_category);
                    $items = array_merge($items, $category_items);
                } else {
                    $items[] = [
                        'title' => $primary_category->name,
                        'url' => \get_category_link($primary_category),
                        'type' => 'category'
                    ];
                }
            }
        }
        
        return $items;
    }
    
    /**
     * Get category hierarchy
     */
    private function get_category_hierarchy($category) {
        $items = [];
        
        if ($category->parent) {
            $parent_category = \get_category($category->parent);
            $parent_items = $this->get_category_hierarchy($parent_category);
            $items = array_merge($items, $parent_items);
        }
        
        $items[] = [
            'title' => $category->name,
            'url' => \get_category_link($category),
            'type' => 'category'
        ];
        
        return $items;
    }
    
    /**
     * Get parent pages hierarchy
     */
    private function get_parent_pages($parent_id) {
        $items = [];
        $parent_page = \get_post($parent_id);
        
        if ($parent_page) {
            if ($parent_page->post_parent) {
                $grandparent_items = $this->get_parent_pages($parent_page->post_parent);
                $items = array_merge($items, $grandparent_items);
            }
            
            $items[] = [
                'title' => $parent_page->post_title,
                'url' => \get_permalink($parent_page),
                'type' => 'page'
            ];
        }
        
        return $items;
    }
    
    /**
     * Get category breadcrumbs
     */
    private function get_category_breadcrumbs() {
        $category = \get_queried_object();
        $items = [];
        
        if (!$category) {
            return $items;
        }
        
        // Add blog page if set
        $blog_page_id = \get_option('page_for_posts');
        if ($blog_page_id) {
            $blog_page = \get_post($blog_page_id);
            $items[] = [
                'title' => $blog_page->post_title,
                'url' => \get_permalink($blog_page),
                'type' => 'blog'
            ];
        }
        
        // Add category hierarchy
        if ($this->settings['hierarchical_categories'] && $category->parent) {
            $parent_items = $this->get_category_hierarchy(\get_category($category->parent));
            $items = array_merge($items, $parent_items);
        }
        
        // Add current category
        if ($this->settings['show_current']) {
            $items[] = [
                'title' => $category->name,
                'url' => $this->settings['link_current'] ? \get_category_link($category) : '',
                'type' => 'current',
                'current' => true
            ];
        }
        
        return $items;
    }
    
    /**
     * Get tag breadcrumbs
     */
    private function get_tag_breadcrumbs() {
        $tag = \get_queried_object();
        $items = [];
        
        if (!$tag) {
            return $items;
        }
        
        // Add blog page if set
        $blog_page_id = \get_option('page_for_posts');
        if ($blog_page_id) {
            $blog_page = \get_post($blog_page_id);
            $items[] = [
                'title' => $blog_page->post_title,
                'url' => \get_permalink($blog_page),
                'type' => 'blog'
            ];
        }
        
        // Add current tag
        if ($this->settings['show_current']) {
            $items[] = [
                'title' => $this->settings['prefix_tag'] . ' ' . $tag->name,
                'url' => $this->settings['link_current'] ? \get_tag_link($tag) : '',
                'type' => 'current',
                'current' => true
            ];
        }
        
        return $items;
    }
    
    /**
     * Get author breadcrumbs
     */
    private function get_author_breadcrumbs() {
        $author = \get_queried_object();
        $items = [];
        
        if (!$author) {
            return $items;
        }
        
        // Add blog page if set
        $blog_page_id = \get_option('page_for_posts');
        if ($blog_page_id) {
            $blog_page = \get_post($blog_page_id);
            $items[] = [
                'title' => $blog_page->post_title,
                'url' => \get_permalink($blog_page),
                'type' => 'blog'
            ];
        }
        
        // Add current author
        if ($this->settings['show_current']) {
            $author_name = \get_the_author_meta('display_name', $author->ID);
            $items[] = [
                'title' => $this->settings['prefix_author'] . ' ' . $author_name,
                'url' => $this->settings['link_current'] ? \get_author_posts_url($author->ID) : '',
                'type' => 'current',
                'current' => true
            ];
        }
        
        return $items;
    }
    
    /**
     * Get date archive breadcrumbs
     */
    private function get_date_breadcrumbs() {
        $items = [];
        
        // Add blog page if set
        $blog_page_id = \get_option('page_for_posts');
        if ($blog_page_id) {
            $blog_page = \get_post($blog_page_id);
            $items[] = [
                'title' => $blog_page->post_title,
                'url' => \get_permalink($blog_page),
                'type' => 'blog'
            ];
        }
        
        // Add year
        if (\is_day() || \is_month()) {
            $year = \get_query_var('year');
            $items[] = [
                'title' => $year,
                'url' => \get_year_link($year),
                'type' => 'year'
            ];
        }
        
        // Add month
        if (\is_day()) {
            $month = \get_query_var('monthnum');
            $year = \get_query_var('year');
            $items[] = [
                'title' => \date_i18n('F', mktime(0, 0, 0, $month, 1)),
                'url' => \get_month_link($year, $month),
                'type' => 'month'
            ];
        }
        
        // Add current date
        if ($this->settings['show_current']) {
            $date_title = '';
            
            if (\is_year()) {
                $date_title = $this->settings['prefix_date'] . ' ' . \get_query_var('year');
            } elseif (\is_month()) {
                $month = \get_query_var('monthnum');
                $year = \get_query_var('year');
                $date_title = $this->settings['prefix_date'] . ' ' . \date_i18n('F Y', mktime(0, 0, 0, $month, 1, $year));
            } elseif (\is_day()) {
                $day = \get_query_var('day');
                $month = \get_query_var('monthnum');
                $year = \get_query_var('year');
                $date_title = $this->settings['prefix_date'] . ' ' . \date_i18n('F j, Y', mktime(0, 0, 0, $month, $day, $year));
            }
            
            if ($date_title) {
                $items[] = [
                    'title' => $date_title,
                    'url' => '',
                    'type' => 'current',
                    'current' => true
                ];
            }
        }
        
        return $items;
    }
    
    /**
     * Get 404 breadcrumbs
     */
    private function get_404_breadcrumbs() {
        $items = [];
        
        if ($this->settings['show_current']) {
            $items[] = [
                'title' => $this->settings['prefix_404'],
                'url' => '',
                'type' => 'current',
                'current' => true
            ];
        }
        
        return $items;
    }
    
    /**
     * Get post type archive breadcrumbs
     */
    private function get_post_type_archive_breadcrumbs() {
        $post_type = \get_query_var('post_type');
        $items = [];
        
        if (!$post_type) {
            return $items;
        }
        
        $post_type_obj = \get_post_type_object($post_type);
        
        if ($post_type_obj && $this->settings['show_current']) {
            $items[] = [
                'title' => $post_type_obj->labels->name,
                'url' => $this->settings['link_current'] ? \get_post_type_archive_link($post_type) : '',
                'type' => 'current',
                'current' => true
            ];
        }
        
        return $items;
    }
    
    /**
     * Get taxonomy breadcrumbs
     */
    private function get_taxonomy_breadcrumbs() {
        $term = \get_queried_object();
        $items = [];
        
        if (!$term || !isset($term->taxonomy)) {
            return $items;
        }
        
        $taxonomy = \get_taxonomy($term->taxonomy);
        
        // Add post type archive if associated
        if (!empty($taxonomy->object_type)) {
            $post_type = $taxonomy->object_type[0];
            $post_type_obj = \get_post_type_object($post_type);
            
            if ($post_type_obj && $post_type_obj->has_archive) {
                $items[] = [
                    'title' => $post_type_obj->labels->name,
                    'url' => \get_post_type_archive_link($post_type),
                    'type' => 'post_type_archive'
                ];
            }
        }
        
        // Add parent terms if hierarchical
        if (\is_taxonomy_hierarchical($term->taxonomy) && $term->parent) {
            $parent_items = $this->get_taxonomy_hierarchy($term->parent, $term->taxonomy);
            $items = array_merge($items, $parent_items);
        }
        
        // Add current term
        if ($this->settings['show_current']) {
            $items[] = [
                'title' => $term->name,
                'url' => $this->settings['link_current'] ? \get_term_link($term) : '',
                'type' => 'current',
                'current' => true
            ];
        }
        
        return $items;
    }
    
    /**
     * Get taxonomy hierarchy
     */
    private function get_taxonomy_hierarchy($parent_id, $taxonomy) {
        $items = [];
        $parent_term = \get_term($parent_id, $taxonomy);
        
        if ($parent_term && !is_wp_error($parent_term)) {
            if ($parent_term->parent) {
                $grandparent_items = $this->get_taxonomy_hierarchy($parent_term->parent, $taxonomy);
                $items = array_merge($items, $grandparent_items);
            }
            
            $items[] = [
                'title' => $parent_term->name,
                'url' => \get_term_link($parent_term),
                'type' => 'taxonomy'
            ];
        }
        
        return $items;
    }
    
    /**
     * Get search breadcrumbs
     */
    private function get_search_breadcrumbs() {
        $items = [];
        
        if ($this->settings['show_current']) {
            $search_query = \get_search_query();
            $items[] = [
                'title' => $this->settings['prefix_search'] . ' "' . $search_query . '"',
                'url' => $this->settings['link_current'] ? \get_search_link($search_query) : '',
                'type' => 'current',
                'current' => true
            ];
        }
        
        return $items;
    }
    
    /**
     * Build breadcrumb HTML
     */
    private function build_breadcrumb_html($items, $settings) {
        if (empty($items)) {
            return '';
        }
        
        $html_items = [];
        $separator = $this->get_separator();
        
        foreach ($items as $index => $item) {
            $is_current = isset($item['current']) && $item['current'];
            $is_last = $index === count($items) - 1;
            
            // Truncate title if needed
            $title = $this->truncate_text($item['title'], $settings['max_length']);
            
            if (!$is_current && !empty($item['url'])) {
                $html_items[] = sprintf(
                    '<span class="breadcrumb-item"><a href="%s" itemprop="item"><span itemprop="name">%s</span></a><meta itemprop="position" content="%d"></span>',
                    \esc_url($item['url']),
                    \esc_html($title),
                    $item['position']
                );
            } else {
                $html_items[] = sprintf(
                    '<span class="breadcrumb-item current"><span itemprop="name">%s</span><meta itemprop="position" content="%d"></span>',
                    \esc_html($title),
                    $item['position']
                );
            }
            
            // Add separator except for last item
            if (!$is_last) {
                $html_items[] = sprintf('<span class="breadcrumb-separator">%s</span>', $separator);
            }
        }
        
        $css_classes = ['khm-seo-breadcrumbs'];
        if (!empty($settings['custom_css_class'])) {
            $css_classes[] = $settings['custom_css_class'];
        }
        
        $html = sprintf(
            '<nav class="%s" itemscope itemtype="https://schema.org/BreadcrumbList" aria-label="Breadcrumb">%s</nav>',
            \esc_attr(implode(' ', $css_classes)),
            implode(' ', $html_items)
        );
        
        return \apply_filters('khm_seo_breadcrumb_html', $html, $items, $settings);
    }
    
    /**
     * Get separator
     */
    private function get_separator() {
        $separator = $this->settings['separator'];
        
        if ($separator === 'custom' && !empty($this->settings['custom_separator'])) {
            $separator = $this->settings['custom_separator'];
        }
        
        return \apply_filters('khm_seo_breadcrumb_separator', $separator);
    }
    
    /**
     * Truncate text
     */
    private function truncate_text($text, $max_length) {
        if ($max_length <= 0 || strlen($text) <= $max_length) {
            return $text;
        }
        
        return substr($text, 0, $max_length - 3) . '...';
    }
    
    /**
     * Generate schema markup
     */
    private function generate_schema_markup($items) {
        if (empty($items)) {
            return '';
        }
        
        $schema_items = [];
        
        foreach ($items as $item) {
            $schema_item = [
                '@type' => 'ListItem',
                'position' => $item['position'],
                'name' => $item['title']
            ];
            
            if (!empty($item['url'])) {
                $schema_item['item'] = $item['url'];
            }
            
            $schema_items[] = $schema_item;
        }
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $schema_items
        ];
        
        $schema = \apply_filters('khm_seo_breadcrumb_schema', $schema, $items);
        
        return sprintf(
            '<script type="application/ld+json">%s</script>',
            \wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Get cache key
     */
    private function get_cache_key() {
        global $wp_query;
        
        $key_parts = [
            'khm_seo_breadcrumbs',
            \get_queried_object_id(),
            \serialize($wp_query->query_vars),
            \serialize($this->settings)
        ];
        
        return 'khm_bc_' . \md5(implode('|', $key_parts));
    }
    
    /**
     * Clear breadcrumb cache
     */
    public function clear_breadcrumb_cache($post_id = null) {
        // Clear all breadcrumb-related transients
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_khm_bc_%' 
             OR option_name LIKE '_transient_timeout_khm_bc_%'"
        );
    }
    
    /**
     * Breadcrumb shortcode
     */
    public function breadcrumb_shortcode($atts) {
        $atts = \shortcode_atts([
            'show_home' => null,
            'show_current' => null,
            'separator' => null,
            'class' => null
        ], $atts);
        
        // Override settings with shortcode attributes
        $custom_settings = array_filter($atts, function($value) {
            return $value !== null;
        });
        
        if (!empty($custom_settings['class'])) {
            $custom_settings['custom_css_class'] = $custom_settings['class'];
            unset($custom_settings['class']);
        }
        
        return $this->generate_breadcrumbs($custom_settings);
    }
    
    /**
     * Handle breadcrumb preview AJAX
     */
    public function handle_breadcrumb_preview() {
        \check_ajax_referer('khm_seo_breadcrumbs_nonce', 'nonce');
        
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions');
        }
        
        // Generate preview HTML for different page types
        $previews = [
            'homepage' => $this->generate_preview_for_homepage(),
            'page' => $this->generate_preview_for_page(),
            'post' => $this->generate_preview_for_post(),
            'category' => $this->generate_preview_for_category(),
            'search' => $this->generate_preview_for_search()
        ];
        
        \wp_send_json_success($previews);
    }
    
    /**
     * Generate preview for different page types
     */
    private function generate_preview_for_homepage() {
        return '<span class="preview-note">Breadcrumbs are not displayed on the homepage.</span>';
    }
    
    private function generate_preview_for_page() {
        $items = [
            ['title' => $this->settings['home_text'], 'url' => '#', 'position' => 1],
            ['title' => 'About Us', 'url' => '#', 'position' => 2],
            ['title' => 'Our Team', 'url' => '', 'position' => 3, 'current' => true]
        ];
        
        return $this->build_breadcrumb_html($items, $this->settings);
    }
    
    private function generate_preview_for_post() {
        $items = [
            ['title' => $this->settings['home_text'], 'url' => '#', 'position' => 1],
            ['title' => 'Blog', 'url' => '#', 'position' => 2],
            ['title' => 'Technology', 'url' => '#', 'position' => 3],
            ['title' => 'How to Optimize Your Website for SEO', 'url' => '', 'position' => 4, 'current' => true]
        ];
        
        return $this->build_breadcrumb_html($items, $this->settings);
    }
    
    private function generate_preview_for_category() {
        $items = [
            ['title' => $this->settings['home_text'], 'url' => '#', 'position' => 1],
            ['title' => 'Blog', 'url' => '#', 'position' => 2],
            ['title' => 'Technology', 'url' => '', 'position' => 3, 'current' => true]
        ];
        
        return $this->build_breadcrumb_html($items, $this->settings);
    }
    
    private function generate_preview_for_search() {
        $items = [
            ['title' => $this->settings['home_text'], 'url' => '#', 'position' => 1],
            ['title' => $this->settings['prefix_search'] . ' "wordpress seo"', 'url' => '', 'position' => 2, 'current' => true]
        ];
        
        return $this->build_breadcrumb_html($items, $this->settings);
    }
    
    // Additional methods for other breadcrumb types, REST API endpoints, etc.
    // would be added here...
}

// Initialize breadcrumb system
new BreadcrumbSystem();

/**
 * Template function for displaying breadcrumbs
 */
function khm_seo_breadcrumbs($args = []) {
    if (class_exists('KHM_SEO\Breadcrumbs\BreadcrumbSystem')) {
        $breadcrumb_system = new BreadcrumbSystem();
        echo $breadcrumb_system->generate_breadcrumbs($args);
    }
}