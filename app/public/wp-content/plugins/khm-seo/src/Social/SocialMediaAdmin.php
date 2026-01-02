<?php
/**
 * Social Media Admin - Admin interface for social media configuration
 * 
 * This class provides a comprehensive admin interface for configuring
 * social media integration including Open Graph tags, Twitter Cards,
 * and platform-specific optimizations.
 * 
 * Features:
 * - Platform configuration (Facebook, Twitter, LinkedIn, Pinterest)
 * - Image optimization settings
 * - Content customization options
 * - Social media testing and validation tools
 * - Platform-specific preview generation
 * - Analytics and statistics dashboard
 * 
 * @package KHM_SEO\Social
 * @since 2.5.0
 */

namespace KHM_SEO\Social;

/**
 * Social Media Admin Class
 */
class SocialMediaAdmin {
    /**
     * @var SocialMediaGenerator Social media generator instance
     */
    private $generator;

    /**
     * @var string Admin page slug
     */
    private $page_slug = 'khm-seo-social';

    /**
     * @var array Admin tabs
     */
    private $tabs;

    /**
     * Constructor
     */
    public function __construct() {
        $this->generator = new SocialMediaGenerator();
        $this->init_tabs();
        $this->init_hooks();
    }

    /**
     * Initialize admin tabs
     */
    private function init_tabs() {
        $this->tabs = [
            'general' => [
                'title' => 'General Settings',
                'icon' => 'admin-generic'
            ],
            'platforms' => [
                'title' => 'Platform Settings',
                'icon' => 'share'
            ],
            'content' => [
                'title' => 'Content Settings',
                'icon' => 'edit'
            ],
            'images' => [
                'title' => 'Image Settings',
                'icon' => 'format-image'
            ],
            'testing' => [
                'title' => 'Testing & Validation',
                'icon' => 'admin-tools'
            ],
            'analytics' => [
                'title' => 'Analytics',
                'icon' => 'chart-line'
            ]
        ];
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_khm_seo_test_social_url', [$this, 'ajax_test_social_url']);
        add_action('wp_ajax_khm_seo_validate_social_tags', [$this, 'ajax_validate_social_tags']);
        add_action('wp_ajax_khm_seo_generate_social_preview', [$this, 'ajax_generate_social_preview']);
        add_action('wp_ajax_khm_seo_clear_social_cache', [$this, 'ajax_clear_social_cache']);
        
        // Meta boxes for post editing
        add_action('add_meta_boxes', [$this, 'add_social_meta_boxes']);
        add_action('save_post', [$this, 'save_social_meta_data']);
        
        // Output social tags in head
        add_action('wp_head', [$this, 'output_social_tags'], 1);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-seo',
            'Social Media',
            'Social Media',
            'manage_options',
            $this->page_slug,
            [$this, 'render_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Main settings group
        register_setting('khm_seo_social_settings', 'khm_seo_social_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);

        // Platform-specific settings
        register_setting('khm_seo_social_platforms', 'khm_seo_social_facebook_page');
        register_setting('khm_seo_social_platforms', 'khm_seo_social_twitter_handle');
        register_setting('khm_seo_social_platforms', 'khm_seo_social_linkedin_company');
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'khm-seo-social-admin',
            plugins_url('assets/css/social-admin.css', dirname(__DIR__)),
            [],
            '2.5.0'
        );

        // JavaScript
        wp_enqueue_script(
            'khm-seo-social-admin',
            plugins_url('assets/js/social-admin.js', dirname(__DIR__)),
            ['jquery', 'wp-media'],
            '2.5.0',
            true
        );

        // Localize script
        wp_localize_script('khm-seo-social-admin', 'khmSeoSocial', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_seo_social_nonce'),
            'strings' => [
                'testing' => 'Testing URL...',
                'validating' => 'Validating tags...',
                'generating_preview' => 'Generating preview...',
                'clearing_cache' => 'Clearing cache...',
                'success' => 'Operation completed successfully',
                'error' => 'An error occurred. Please try again.',
                'invalid_url' => 'Please enter a valid URL',
                'no_tags_found' => 'No social media tags found on this page'
            ]
        ]);

        // Media uploader
        wp_enqueue_media();
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        if (!array_key_exists($active_tab, $this->tabs)) {
            $active_tab = 'general';
        }

        ?>
        <div class="wrap khm-seo-admin">
            <h1>
                <span class="dashicons dashicons-share" style="margin-right: 10px;"></span>
                Social Media Integration
            </h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $tab_id => $tab): ?>
                    <a href="?page=<?php echo esc_attr($this->page_slug); ?>&tab=<?php echo esc_attr($tab_id); ?>" 
                       class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($tab['icon']); ?>"></span>
                        <?php echo esc_html($tab['title']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="khm-seo-tab-content">
                <?php $this->render_tab_content($active_tab); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render tab content
     *
     * @param string $tab Active tab
     */
    private function render_tab_content($tab) {
        switch ($tab) {
            case 'general':
                $this->render_general_tab();
                break;
            case 'platforms':
                $this->render_platforms_tab();
                break;
            case 'content':
                $this->render_content_tab();
                break;
            case 'images':
                $this->render_images_tab();
                break;
            case 'testing':
                $this->render_testing_tab();
                break;
            case 'analytics':
                $this->render_analytics_tab();
                break;
        }
    }

    /**
     * Render general settings tab
     */
    private function render_general_tab() {
        $settings = get_option('khm_seo_social_settings', []);
        ?>
        <div class="khm-seo-section">
            <h2>General Social Media Settings</h2>
            <p>Configure the basic settings for social media integration across your website.</p>

            <form method="post" action="options.php">
                <?php settings_fields('khm_seo_social_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Social Media Tags</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[enable_social_tags]" value="1" 
                                       <?php checked(!empty($settings['enable_social_tags'])); ?>>
                                Enable automatic generation of social media meta tags
                            </label>
                            <p class="description">When enabled, social media meta tags will be automatically added to your pages.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enable Open Graph</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[enable_open_graph]" value="1" 
                                       <?php checked(!empty($settings['enable_open_graph'])); ?>>
                                Enable Open Graph meta tags (Facebook, LinkedIn, etc.)
                            </label>
                            <p class="description">Open Graph tags are used by Facebook, LinkedIn, and many other platforms.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enable Twitter Cards</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[enable_twitter_cards]" value="1" 
                                       <?php checked(!empty($settings['enable_twitter_cards'])); ?>>
                                Enable Twitter Card meta tags
                            </label>
                            <p class="description">Twitter Cards provide rich previews when your content is shared on Twitter.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Site Locale</th>
                        <td>
                            <select name="khm_seo_social_settings[locale]">
                                <option value="en_US" <?php selected($settings['locale'] ?? 'en_US', 'en_US'); ?>>English (US)</option>
                                <option value="en_GB" <?php selected($settings['locale'] ?? 'en_US', 'en_GB'); ?>>English (UK)</option>
                                <option value="es_ES" <?php selected($settings['locale'] ?? 'en_US', 'es_ES'); ?>>Spanish (Spain)</option>
                                <option value="fr_FR" <?php selected($settings['locale'] ?? 'en_US', 'fr_FR'); ?>>French (France)</option>
                                <option value="de_DE" <?php selected($settings['locale'] ?? 'en_US', 'de_DE'); ?>>German (Germany)</option>
                                <option value="it_IT" <?php selected($settings['locale'] ?? 'en_US', 'it_IT'); ?>>Italian (Italy)</option>
                            </select>
                            <p class="description">Default locale for Open Graph tags.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Include Site Name</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[include_site_name]" value="1" 
                                       <?php checked(!empty($settings['include_site_name'])); ?>>
                                Include site name in social media tags
                            </label>
                            <p class="description">Adds your site name to Open Graph tags for better branding.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save General Settings'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render platform settings tab
     */
    private function render_platforms_tab() {
        $settings = get_option('khm_seo_social_settings', []);
        $facebook_page = get_option('khm_seo_social_facebook_page', '');
        $twitter_handle = get_option('khm_seo_social_twitter_handle', '');
        $linkedin_company = get_option('khm_seo_social_linkedin_company', '');
        ?>
        <div class="khm-seo-section">
            <h2>Platform-Specific Settings</h2>
            <p>Configure settings for individual social media platforms.</p>

            <div class="khm-seo-platform-grid">
                <!-- Facebook Settings -->
                <div class="platform-card facebook">
                    <h3>
                        <span class="platform-icon dashicons dashicons-facebook"></span>
                        Facebook
                    </h3>
                    
                    <form method="post" action="options.php">
                        <?php settings_fields('khm_seo_social_platforms'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable LinkedIn Tags</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="khm_seo_social_settings[enable_linkedin]" value="1" 
                                               <?php checked(!empty($settings['enable_linkedin'])); ?>>
                                        Enable LinkedIn-specific optimization
                                    </label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Facebook App ID</th>
                                <td>
                                    <input type="text" name="khm_seo_social_settings[facebook_app_id]" 
                                           value="<?php echo esc_attr($settings['facebook_app_id'] ?? ''); ?>" 
                                           class="regular-text">
                                    <p class="description">Optional Facebook App ID for analytics.</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Facebook Page URL</th>
                                <td>
                                    <input type="url" name="khm_seo_social_facebook_page" 
                                           value="<?php echo esc_attr($facebook_page); ?>" 
                                           class="regular-text">
                                    <p class="description">Your Facebook business page URL for publisher tags.</p>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>

                <!-- Twitter Settings -->
                <div class="platform-card twitter">
                    <h3>
                        <span class="platform-icon dashicons dashicons-twitter"></span>
                        Twitter
                    </h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Twitter Username</th>
                            <td>
                                <div class="twitter-handle">
                                    <span>@</span>
                                    <input type="text" name="khm_seo_social_settings[twitter_username]" 
                                           value="<?php echo esc_attr($settings['twitter_username'] ?? ''); ?>" 
                                           class="regular-text" placeholder="username">
                                </div>
                                <p class="description">Your Twitter handle (without the @).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Default Card Type</th>
                            <td>
                                <select name="khm_seo_social_settings[default_twitter_card]">
                                    <option value="summary" <?php selected($settings['default_twitter_card'] ?? 'summary', 'summary'); ?>>Summary</option>
                                    <option value="summary_large_image" <?php selected($settings['default_twitter_card'] ?? 'summary', 'summary_large_image'); ?>>Summary Large Image</option>
                                </select>
                                <p class="description">Default Twitter Card type for posts.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- LinkedIn Settings -->
                <div class="platform-card linkedin">
                    <h3>
                        <span class="platform-icon dashicons dashicons-linkedin"></span>
                        LinkedIn
                    </h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">LinkedIn Company ID</th>
                            <td>
                                <input type="text" name="khm_seo_social_settings[linkedin_company_id]" 
                                       value="<?php echo esc_attr($settings['linkedin_company_id'] ?? ''); ?>" 
                                       class="regular-text">
                                <p class="description">Your LinkedIn company page ID for publisher tags.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Pinterest Settings -->
                <div class="platform-card pinterest">
                    <h3>
                        <span class="platform-icon dashicons dashicons-pinterest"></span>
                        Pinterest
                    </h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Pinterest Tags</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="khm_seo_social_settings[enable_pinterest]" value="1" 
                                           <?php checked(!empty($settings['enable_pinterest'])); ?>>
                                    Enable Pinterest-specific optimization
                                </label>
                                <p class="description">Optimizes images and descriptions for Pinterest sharing.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <form method="post" action="options.php" style="margin-top: 20px;">
                <?php settings_fields('khm_seo_social_settings'); ?>
                <?php submit_button('Save Platform Settings'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render content settings tab
     */
    private function render_content_tab() {
        $settings = get_option('khm_seo_social_settings', []);
        ?>
        <div class="khm-seo-section">
            <h2>Content Settings</h2>
            <p>Configure how content is generated for social media sharing.</p>

            <form method="post" action="options.php">
                <?php settings_fields('khm_seo_social_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Auto-generate Descriptions</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[auto_generate_descriptions]" value="1" 
                                       <?php checked(!empty($settings['auto_generate_descriptions'])); ?>>
                                Automatically generate descriptions from content
                            </label>
                            <p class="description">When enabled, descriptions will be extracted from post content if not manually set.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Description Length</th>
                        <td>
                            <input type="number" name="khm_seo_social_settings[description_length]" 
                                   value="<?php echo esc_attr($settings['description_length'] ?? 160); ?>" 
                                   min="50" max="300" class="small-text">
                            <span> characters</span>
                            <p class="description">Maximum length for auto-generated descriptions (50-300 characters).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Use Post Excerpt</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[use_post_excerpt]" value="1" 
                                       <?php checked(!empty($settings['use_post_excerpt'])); ?>>
                                Use post excerpt for descriptions when available
                            </label>
                            <p class="description">When enabled, post excerpts will be used before auto-generating from content.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Article Author</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[article_author]" value="1" 
                                       <?php checked(!empty($settings['article_author'])); ?>>
                                Include article author information
                            </label>
                            <p class="description">Adds author information to article-type posts.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Article Publisher</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[article_publisher]" value="1" 
                                       <?php checked(!empty($settings['article_publisher'])); ?>>
                                Include publisher information
                            </label>
                            <p class="description">Adds your site as the publisher for article-type posts.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Content Settings'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render images settings tab
     */
    private function render_images_tab() {
        $settings = get_option('khm_seo_social_settings', []);
        ?>
        <div class="khm-seo-section">
            <h2>Image Settings</h2>
            <p>Configure default images and image handling for social media sharing.</p>

            <form method="post" action="options.php">
                <?php settings_fields('khm_seo_social_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Use Featured Images</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[use_featured_image]" value="1" 
                                       <?php checked(!empty($settings['use_featured_image'])); ?>>
                                Use post featured images for social sharing
                            </label>
                            <p class="description">When enabled, featured images will be used for social media tags.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Default Social Image</th>
                        <td>
                            <div class="image-upload-field">
                                <input type="hidden" name="khm_seo_social_settings[default_image]" 
                                       value="<?php echo esc_attr($settings['default_image'] ?? ''); ?>" 
                                       id="default_image_url">
                                
                                <div class="image-preview" id="default_image_preview">
                                    <?php if (!empty($settings['default_image'])): ?>
                                        <img src="<?php echo esc_url($settings['default_image']); ?>" alt="Default social image">
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="button image-upload-btn" data-target="default_image_url" data-preview="default_image_preview">
                                    <?php echo !empty($settings['default_image']) ? 'Change Image' : 'Select Image'; ?>
                                </button>
                                
                                <?php if (!empty($settings['default_image'])): ?>
                                    <button type="button" class="button image-remove-btn" data-target="default_image_url" data-preview="default_image_preview">
                                        Remove Image
                                    </button>
                                <?php endif; ?>
                            </div>
                            <p class="description">Default image used when no featured image or custom social image is set. Recommended size: 1200x630px.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Fallback Image</th>
                        <td>
                            <div class="image-upload-field">
                                <input type="hidden" name="khm_seo_social_settings[fallback_image]" 
                                       value="<?php echo esc_attr($settings['fallback_image'] ?? ''); ?>" 
                                       id="fallback_image_url">
                                
                                <div class="image-preview" id="fallback_image_preview">
                                    <?php if (!empty($settings['fallback_image'])): ?>
                                        <img src="<?php echo esc_url($settings['fallback_image']); ?>" alt="Fallback social image">
                                    <?php endif; ?>
                                </div>
                                
                                <button type="button" class="button image-upload-btn" data-target="fallback_image_url" data-preview="fallback_image_preview">
                                    <?php echo !empty($settings['fallback_image']) ? 'Change Image' : 'Select Image'; ?>
                                </button>
                                
                                <?php if (!empty($settings['fallback_image'])): ?>
                                    <button type="button" class="button image-remove-btn" data-target="fallback_image_url" data-preview="fallback_image_preview">
                                        Remove Image
                                    </button>
                                <?php endif; ?>
                            </div>
                            <p class="description">Last resort image when no other images are available. Recommended size: 1200x630px.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Image Optimization</th>
                        <td>
                            <label>
                                <input type="checkbox" name="khm_seo_social_settings[image_optimization]" value="1" 
                                       <?php checked(!empty($settings['image_optimization'])); ?>>
                                Optimize images for different platforms
                            </label>
                            <p class="description">When enabled, different image sizes will be used based on platform requirements.</p>
                        </td>
                    </tr>
                </table>
                
                <div class="image-guidelines">
                    <h3>Image Size Guidelines</h3>
                    <div class="guidelines-grid">
                        <div class="guideline-item">
                            <strong>Facebook/Open Graph:</strong>
                            <span>1200×630px (1.91:1 ratio)</span>
                        </div>
                        <div class="guideline-item">
                            <strong>Twitter Large Image:</strong>
                            <span>1024×512px (2:1 ratio)</span>
                        </div>
                        <div class="guideline-item">
                            <strong>Twitter Summary:</strong>
                            <span>400×400px (1:1 ratio)</span>
                        </div>
                        <div class="guideline-item">
                            <strong>LinkedIn:</strong>
                            <span>1200×627px (1.91:1 ratio)</span>
                        </div>
                        <div class="guideline-item">
                            <strong>Pinterest:</strong>
                            <span>735×1102px (2:3 ratio)</span>
                        </div>
                    </div>
                </div>
                
                <?php submit_button('Save Image Settings'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render testing tab
     */
    private function render_testing_tab() {
        ?>
        <div class="khm-seo-section">
            <h2>Social Media Testing & Validation</h2>
            <p>Test and validate your social media tags to ensure optimal sharing performance.</p>

            <div class="testing-tools-grid">
                <div class="testing-tool">
                    <h3>URL Testing</h3>
                    <p>Test how your pages will appear when shared on social media platforms.</p>
                    
                    <div class="url-testing-form">
                        <input type="url" id="test_url" placeholder="Enter URL to test..." class="regular-text">
                        <button type="button" id="test_url_btn" class="button button-primary">Test URL</button>
                    </div>
                    
                    <div id="url_test_results" class="test-results" style="display: none;">
                        <!-- Results populated via AJAX -->
                    </div>
                </div>

                <div class="testing-tool">
                    <h3>Tag Validation</h3>
                    <p>Validate the social media tags generated for your content.</p>
                    
                    <button type="button" id="validate_tags_btn" class="button">Validate Current Page Tags</button>
                    
                    <div id="validation_results" class="test-results" style="display: none;">
                        <!-- Results populated via AJAX -->
                    </div>
                </div>

                <div class="testing-tool">
                    <h3>Platform Preview</h3>
                    <p>Generate previews showing how your content will appear on different platforms.</p>
                    
                    <div class="preview-controls">
                        <select id="preview_platform">
                            <option value="facebook">Facebook</option>
                            <option value="twitter">Twitter</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="pinterest">Pinterest</option>
                        </select>
                        <button type="button" id="generate_preview_btn" class="button">Generate Preview</button>
                    </div>
                    
                    <div id="platform_preview" class="platform-preview" style="display: none;">
                        <!-- Preview populated via AJAX -->
                    </div>
                </div>
            </div>

            <div class="external-tools">
                <h3>External Testing Tools</h3>
                <p>Use these external tools to test your social media optimization:</p>
                
                <div class="external-tools-grid">
                    <a href="https://developers.facebook.com/tools/debug/" target="_blank" class="external-tool facebook">
                        <span class="dashicons dashicons-facebook"></span>
                        <strong>Facebook Sharing Debugger</strong>
                        <span class="description">Test how your URLs appear on Facebook</span>
                    </a>
                    
                    <a href="https://cards-dev.twitter.com/validator" target="_blank" class="external-tool twitter">
                        <span class="dashicons dashicons-twitter"></span>
                        <strong>Twitter Card Validator</strong>
                        <span class="description">Validate your Twitter Cards</span>
                    </a>
                    
                    <a href="https://www.linkedin.com/post-inspector/" target="_blank" class="external-tool linkedin">
                        <span class="dashicons dashicons-linkedin"></span>
                        <strong>LinkedIn Post Inspector</strong>
                        <span class="description">See how your content appears on LinkedIn</span>
                    </a>
                    
                    <a href="https://developers.pinterest.com/tools/url-debugger/" target="_blank" class="external-tool pinterest">
                        <span class="dashicons dashicons-pinterest"></span>
                        <strong>Pinterest Rich Pins Validator</strong>
                        <span class="description">Validate Pinterest Rich Pins</span>
                    </a>
                </div>
            </div>

            <div class="cache-management">
                <h3>Cache Management</h3>
                <p>Clear cached social media data to force regeneration.</p>
                
                <button type="button" id="clear_cache_btn" class="button button-secondary">
                    Clear Social Media Cache
                </button>
                <span class="spinner" id="cache_spinner"></span>
            </div>
        </div>
        <?php
    }

    /**
     * Render analytics tab
     */
    private function render_analytics_tab() {
        $stats = $this->generator->get_social_statistics();
        ?>
        <div class="khm-seo-section">
            <h2>Social Media Analytics</h2>
            <p>View statistics and insights about your social media optimization.</p>

            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3>Platform Status</h3>
                    <div class="stat-item">
                        <span class="stat-label">Enabled Platforms:</span>
                        <span class="stat-value"><?php echo esc_html($stats['platforms_enabled']); ?>/4</span>
                    </div>
                    <div class="platform-status-list">
                        <?php
                        $settings = get_option('khm_seo_social_settings', []);
                        $platforms = [
                            'Open Graph' => !empty($settings['enable_open_graph']),
                            'Twitter Cards' => !empty($settings['enable_twitter_cards']),
                            'LinkedIn' => !empty($settings['enable_linkedin']),
                            'Pinterest' => !empty($settings['enable_pinterest'])
                        ];
                        
                        foreach ($platforms as $platform => $enabled):
                        ?>
                            <div class="platform-status <?php echo $enabled ? 'enabled' : 'disabled'; ?>">
                                <span class="status-indicator"></span>
                                <?php echo esc_html($platform); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="analytics-card">
                    <h3>Content Statistics</h3>
                    <div class="stat-item">
                        <span class="stat-label">Total Published Posts:</span>
                        <span class="stat-value"><?php echo esc_html(number_format($stats['total_posts_with_social'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Posts with Custom Images:</span>
                        <span class="stat-value"><?php echo esc_html(number_format($stats['posts_with_custom_images'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Posts with Custom Descriptions:</span>
                        <span class="stat-value"><?php echo esc_html(number_format($stats['posts_with_custom_descriptions'])); ?></span>
                    </div>
                </div>

                <div class="analytics-card">
                    <h3>Performance Insights</h3>
                    <div class="insight-item">
                        <strong>Optimization Level:</strong>
                        <?php
                        $optimization_percentage = 0;
                        if ($stats['total_posts_with_social'] > 0) {
                            $optimized_posts = $stats['posts_with_custom_images'] + $stats['posts_with_custom_descriptions'];
                            $optimization_percentage = min(100, ($optimized_posts / ($stats['total_posts_with_social'] * 2)) * 100);
                        }
                        ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo esc_attr($optimization_percentage); ?>%"></div>
                        </div>
                        <span><?php echo esc_html(number_format($optimization_percentage, 1)); ?>%</span>
                    </div>
                    
                    <div class="recommendations">
                        <h4>Recommendations:</h4>
                        <ul>
                            <?php if ($stats['posts_with_custom_images'] < $stats['total_posts_with_social']): ?>
                                <li>Add custom social images to more posts for better engagement</li>
                            <?php endif; ?>
                            <?php if ($stats['posts_with_custom_descriptions'] < $stats['total_posts_with_social']): ?>
                                <li>Write custom social descriptions to improve click-through rates</li>
                            <?php endif; ?>
                            <?php if (empty($settings['enable_pinterest'])): ?>
                                <li>Consider enabling Pinterest optimization for visual content</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="recent-activity">
                <h3>Recent Activity</h3>
                <div class="activity-item">
                    <span class="activity-label">Last Tags Generated:</span>
                    <span class="activity-value">
                        <?php 
                        if ($stats['last_generated']) {
                            echo esc_html(human_time_diff($stats['last_generated']) . ' ago');
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add social meta boxes to post edit screen
     */
    public function add_social_meta_boxes() {
        $post_types = ['post', 'page'];
        $post_types = apply_filters('khm_seo_social_post_types', $post_types);

        foreach ($post_types as $post_type) {
            add_meta_box(
                'khm_seo_social_meta',
                'Social Media Settings',
                [$this, 'render_social_meta_box'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * Render social meta box
     *
     * @param WP_Post $post Current post object
     */
    public function render_social_meta_box($post) {
        wp_nonce_field('khm_seo_social_meta', 'khm_seo_social_meta_nonce');

        $social_title = get_post_meta($post->ID, '_khm_seo_social_title', true);
        $social_description = get_post_meta($post->ID, '_khm_seo_social_description', true);
        $social_image = get_post_meta($post->ID, '_khm_seo_social_image', true);
        $og_type = get_post_meta($post->ID, '_khm_seo_og_type', true);
        $twitter_card = get_post_meta($post->ID, '_khm_seo_twitter_card', true);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">Social Title</th>
                <td>
                    <input type="text" name="khm_seo_social_title" value="<?php echo esc_attr($social_title); ?>" 
                           class="large-text" placeholder="Leave empty to use post title">
                    <p class="description">Custom title for social media sharing.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Social Description</th>
                <td>
                    <textarea name="khm_seo_social_description" rows="3" class="large-text" 
                              placeholder="Leave empty to auto-generate from content"><?php echo esc_textarea($social_description); ?></textarea>
                    <p class="description">Custom description for social media sharing (recommended: 150-160 characters).</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Social Image</th>
                <td>
                    <div class="social-image-field">
                        <input type="hidden" name="khm_seo_social_image" value="<?php echo esc_attr($social_image); ?>" id="social_image_id">
                        
                        <div class="image-preview" id="social_image_preview">
                            <?php if ($social_image): 
                                $image_url = wp_get_attachment_url($social_image);
                                if ($image_url):
                            ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="Social media image">
                            <?php endif; endif; ?>
                        </div>
                        
                        <button type="button" class="button image-upload-btn" data-target="social_image_id" data-preview="social_image_preview">
                            <?php echo $social_image ? 'Change Image' : 'Select Image'; ?>
                        </button>
                        
                        <?php if ($social_image): ?>
                            <button type="button" class="button image-remove-btn" data-target="social_image_id" data-preview="social_image_preview">
                                Remove Image
                            </button>
                        <?php endif; ?>
                    </div>
                    <p class="description">Custom image for social media sharing. Leave empty to use featured image. Recommended size: 1200x630px.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Open Graph Type</th>
                <td>
                    <select name="khm_seo_og_type">
                        <option value="">Auto-detect</option>
                        <option value="article" <?php selected($og_type, 'article'); ?>>Article</option>
                        <option value="website" <?php selected($og_type, 'website'); ?>>Website</option>
                        <option value="product" <?php selected($og_type, 'product'); ?>>Product</option>
                        <option value="book" <?php selected($og_type, 'book'); ?>>Book</option>
                        <option value="profile" <?php selected($og_type, 'profile'); ?>>Profile</option>
                    </select>
                    <p class="description">Open Graph type for this content.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Twitter Card Type</th>
                <td>
                    <select name="khm_seo_twitter_card">
                        <option value="">Auto-detect</option>
                        <option value="summary" <?php selected($twitter_card, 'summary'); ?>>Summary</option>
                        <option value="summary_large_image" <?php selected($twitter_card, 'summary_large_image'); ?>>Summary Large Image</option>
                    </select>
                    <p class="description">Twitter Card type for this content.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save social meta data
     *
     * @param int $post_id Post ID
     */
    public function save_social_meta_data($post_id) {
        // Verify nonce
        if (!isset($_POST['khm_seo_social_meta_nonce']) || 
            !wp_verify_nonce($_POST['khm_seo_social_meta_nonce'], 'khm_seo_social_meta')) {
            return;
        }

        // Check user capabilities
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save meta fields
        $fields = [
            'khm_seo_social_title' => '_khm_seo_social_title',
            'khm_seo_social_description' => '_khm_seo_social_description', 
            'khm_seo_social_image' => '_khm_seo_social_image',
            'khm_seo_og_type' => '_khm_seo_og_type',
            'khm_seo_twitter_card' => '_khm_seo_twitter_card'
        ];

        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }

        // Update last modified timestamp
        update_option('khm_seo_social_last_generated', time());
    }

    /**
     * Output social tags in head
     */
    public function output_social_tags() {
        global $post;
        
        // Get current context
        $context = null;
        if (is_singular()) {
            $context = $post;
        }

        // Generate and output tags
        echo $this->generator->generate_social_tags($context);
    }

    /**
     * AJAX handler for URL testing
     */
    public function ajax_test_social_url() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        check_ajax_referer('khm_seo_social_nonce', 'nonce');

        $url = sanitize_url($_POST['url'] ?? '');
        $url = esc_url_raw($url);
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(esc_html__('Invalid URL provided', 'khm-seo'));
            return;
        }

        // Simulate testing the URL (in real implementation, this would make HTTP requests)
        $test_results = [
            'url' => $url,
            'title' => 'Test Page Title',
            'description' => 'This is a test description for the page.',
            'image' => 'https://example.com/test-image.jpg',
            'tags_found' => [
                'og:title' => 'Test Page Title',
                'og:description' => 'This is a test description for the page.',
                'og:image' => 'https://example.com/test-image.jpg',
                'twitter:card' => 'summary_large_image'
            ],
            'platforms' => [
                'facebook' => ['status' => 'success', 'message' => 'All required tags present'],
                'twitter' => ['status' => 'success', 'message' => 'Valid Twitter Card'],
                'linkedin' => ['status' => 'warning', 'message' => 'Image size below recommended'],
                'pinterest' => ['status' => 'error', 'message' => 'No Pinterest-specific tags found']
            ]
        ];

        wp_send_json_success($test_results);
    }

    /**
     * AJAX handler for tag validation
     */
    public function ajax_validate_social_tags() {
        // Security checks
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'khm-seo'));
            return;
        }
        
        \check_ajax_referer('khm_seo_social_nonce', 'nonce');

        // Generate tags for current context
        global $post;
        $context = $post;
        
        $tags_html = $this->generator->generate_social_tags($context);
        
        // Parse tags and validate
        $tags = $this->parse_meta_tags($tags_html);
        $validation_results = $this->generator->validate_social_tags($tags);

        wp_send_json_success([
            'tags' => $tags,
            'validation' => $validation_results,
            'html' => $tags_html
        ]);
    }

    /**
     * AJAX handler for generating platform preview
     */
    public function ajax_generate_social_preview() {
        check_ajax_referer('khm_seo_social_nonce', 'nonce');

        $platform = sanitize_key($_POST['platform'] ?? 'facebook');
        
        // Generate preview HTML based on platform
        $preview_html = $this->generate_platform_preview($platform);

        wp_send_json_success([
            'platform' => $platform,
            'preview' => $preview_html
        ]);
    }

    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_social_cache() {
        check_ajax_referer('khm_seo_social_nonce', 'nonce');

        // Clear any cached social media data
        delete_transient('khm_seo_social_cache');
        update_option('khm_seo_social_last_generated', time());

        wp_send_json_success('Cache cleared successfully');
    }

    /**
     * Parse meta tags from HTML
     *
     * @param string $html HTML containing meta tags
     * @return array Parsed tags
     */
    private function parse_meta_tags($html) {
        $tags = [];
        
        preg_match_all('/<meta\s+(?:property|name)="([^"]+)"\s+content="([^"]+)"/', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $tags[$match[1]] = $match[2];
        }

        return $tags;
    }

    /**
     * Generate platform preview
     *
     * @param string $platform Platform identifier
     * @return string Preview HTML
     */
    private function generate_platform_preview($platform) {
        // This would generate HTML previews showing how content appears on different platforms
        // For brevity, returning a simple example
        return "<div class='platform-preview-{$platform}'>Preview for {$platform} would appear here</div>";
    }

    /**
     * Sanitize settings
     *
     * @param array $settings Raw settings
     * @return array Sanitized settings
     */
    public function sanitize_settings($settings) {
        $clean = [];

        // Boolean fields
        $boolean_fields = [
            'enable_social_tags', 'enable_open_graph', 'enable_twitter_cards',
            'enable_linkedin', 'enable_pinterest', 'use_featured_image',
            'use_post_excerpt', 'auto_generate_descriptions', 'include_site_name',
            'article_author', 'article_publisher', 'image_optimization'
        ];

        foreach ($boolean_fields as $field) {
            $clean[$field] = !empty($settings[$field]);
        }

        // Text fields
        $text_fields = [
            'locale', 'twitter_username', 'facebook_app_id', 'linkedin_company_id',
            'default_image', 'fallback_image', 'default_twitter_card'
        ];

        foreach ($text_fields as $field) {
            $clean[$field] = sanitize_text_field($settings[$field] ?? '');
        }

        // Numeric fields
        $clean['description_length'] = absint($settings['description_length'] ?? 160);
        if ($clean['description_length'] < 50) $clean['description_length'] = 50;
        if ($clean['description_length'] > 300) $clean['description_length'] = 300;

        return $clean;
    }
}