<?php
/**
 * Schema Admin Interface - Admin page for schema configuration and testing
 * 
 * Provides comprehensive admin interface for schema markup management,
 * validation tools, testing utilities, and configuration options.
 * 
 * @package KHM_SEO\Schema
 * @since 2.4.0
 */

namespace KHM_SEO\Schema;

/**
 * Schema Admin Class
 */
class SchemaAdmin {
    /**
     * @var SchemaGenerator Schema generator instance
     */
    private $generator;

    /**
     * @var array Page tabs configuration
     */
    private $tabs;

    /**
     * @var array Schema testing tools
     */
    private $testing_tools;

    /**
     * Constructor
     *
     * @param SchemaGenerator $generator Schema generator instance
     */
    public function __construct(SchemaGenerator $generator) {
        $this->generator = $generator;
        $this->init_tabs();
        $this->init_testing_tools();
        $this->init_hooks();
    }

    /**
     * Initialize admin tabs
     */
    private function init_tabs() {
        $this->tabs = [
            'general' => [
                'title' => 'General Settings',
                'icon' => 'admin-generic',
                'description' => 'Enable/disable schema types and basic configuration'
            ],
            'organization' => [
                'title' => 'Organization',
                'icon' => 'building',
                'description' => 'Business information and contact details'
            ],
            'content-types' => [
                'title' => 'Content Types',
                'icon' => 'admin-page',
                'description' => 'Configure schema for different post types'
            ],
            'validation' => [
                'title' => 'Validation & Testing',
                'icon' => 'admin-tools',
                'description' => 'Test and validate schema markup'
            ],
            'advanced' => [
                'title' => 'Advanced',
                'icon' => 'admin-settings',
                'description' => 'Advanced schema configuration and debugging'
            ],
            'statistics' => [
                'title' => 'Statistics',
                'icon' => 'chart-bar',
                'description' => 'Schema generation analytics and reports'
            ]
        ];
    }

    /**
     * Initialize testing tools
     */
    private function init_testing_tools() {
        $this->testing_tools = [
            'google_rich_results' => [
                'name' => 'Google Rich Results Test',
                'description' => 'Test schema with Google Rich Results testing tool',
                'url' => 'https://search.google.com/test/rich-results',
                'icon' => 'google'
            ],
            'schema_validator' => [
                'name' => 'Schema.org Validator',
                'description' => 'Validate against Schema.org specifications',
                'url' => 'https://validator.schema.org/',
                'icon' => 'validation'
            ],
            'structured_data_linter' => [
                'name' => 'Structured Data Linter',
                'description' => 'JSON-LD structural validation',
                'url' => 'http://linter.structured-data.org/',
                'icon' => 'code'
            ]
        ];
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_khm_seo_test_schema_url', [$this, 'ajax_test_schema_url']);
        add_action('wp_ajax_khm_seo_validate_custom_schema', [$this, 'ajax_validate_custom_schema']);
        add_action('wp_ajax_khm_seo_generate_sample_schema', [$this, 'ajax_generate_sample_schema']);
        add_action('wp_ajax_khm_seo_clear_schema_cache', [$this, 'ajax_clear_schema_cache']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-seo',
            'Structured Data',
            'Schema Markup',
            'manage_options',
            'khm-seo-schema',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General schema settings
        register_setting('khm_seo_schema_general', 'khm_seo_schema_settings', [
            'sanitize_callback' => [$this, 'sanitize_schema_settings']
        ]);
        
        // Organization settings
        register_setting('khm_seo_schema_organization', 'khm_seo_organization_settings', [
            'sanitize_callback' => [$this, 'sanitize_organization_settings']
        ]);
        
        // Content type mappings
        register_setting('khm_seo_schema_content', 'khm_seo_schema_content_types', [
            'sanitize_callback' => [$this, 'sanitize_content_type_settings']
        ]);
        
        // Advanced settings
        register_setting('khm_seo_schema_advanced', 'khm_seo_schema_advanced_settings', [
            'sanitize_callback' => [$this, 'sanitize_advanced_settings']
        ]);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (!str_contains($hook, 'khm-seo-schema')) {
            return;
        }

        wp_enqueue_style(
            'khm-seo-schema-admin',
            plugins_url('assets/css/schema-admin.css', KHM_SEO_PLUGIN_FILE),
            [],
            KHM_SEO_VERSION
        );

        wp_enqueue_script(
            'khm-seo-schema-admin',
            plugins_url('assets/js/schema-admin.js', KHM_SEO_PLUGIN_FILE),
            ['jquery', 'wp-util'],
            KHM_SEO_VERSION,
            true
        );

        wp_localize_script('khm-seo-schema-admin', 'khmSeoSchemaAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_seo_schema_admin'),
            'strings' => [
                'testing' => 'Testing schema...',
                'validating' => 'Validating schema...',
                'generating' => 'Generating sample...',
                'clearing_cache' => 'Clearing cache...',
                'success' => 'Success!',
                'error' => 'Error occurred',
                'confirm_clear_cache' => 'Clear all schema cache? This will force regeneration.'
            ],
            'testing_tools' => $this->testing_tools
        ]);
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
        <div class="wrap khm-seo-schema-admin">
            <h1>
                <span class="dashicons dashicons-networking"></span>
                Structured Data (Schema Markup)
            </h1>
            
            <div class="schema-header">
                <div class="schema-status">
                    <?php echo $this->render_schema_status(); ?>
                </div>
                <div class="schema-quick-actions">
                    <?php echo $this->render_quick_actions(); ?>
                </div>
            </div>

            <nav class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $tab_key => $tab): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=khm-seo-schema&tab=' . $tab_key)); ?>" 
                       class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($tab['icon']); ?>"></span>
                        <?php echo esc_html($tab['title']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="tab-content">
                <?php 
                switch ($active_tab) {
                    case 'general':
                        $this->render_general_tab();
                        break;
                    case 'organization':
                        $this->render_organization_tab();
                        break;
                    case 'content-types':
                        $this->render_content_types_tab();
                        break;
                    case 'validation':
                        $this->render_validation_tab();
                        break;
                    case 'advanced':
                        $this->render_advanced_tab();
                        break;
                    case 'statistics':
                        $this->render_statistics_tab();
                        break;
                }
                ?>
            </div>
        </div>
        
        <div id="schema-test-modal" class="schema-modal" style="display: none;">
            <div class="schema-modal-content">
                <span class="schema-modal-close">&times;</span>
                <h3>Schema Test Results</h3>
                <div class="schema-modal-body"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render schema status
     */
    private function render_schema_status() {
        $settings = get_option('khm_seo_schema_settings', []);
        $is_enabled = !empty($settings['enable_schema']);
        $stats = $this->generator->get_schema_statistics();
        
        ob_start();
        ?>
        <div class="schema-status-card <?php echo $is_enabled ? 'status-enabled' : 'status-disabled'; ?>">
            <div class="status-icon">
                <span class="dashicons dashicons-<?php echo $is_enabled ? 'yes-alt' : 'marker'; ?>"></span>
            </div>
            <div class="status-info">
                <h3>Schema Status</h3>
                <p>
                    <?php if ($is_enabled): ?>
                        Schema markup is <strong>enabled</strong> and generating for <?php echo $stats['total_schemas_generated']; ?> items.
                    <?php else: ?>
                        Schema markup is currently <strong>disabled</strong>.
                    <?php endif; ?>
                </p>
                <div class="status-details">
                    <span class="detail-item">
                        <span class="dashicons dashicons-clock"></span>
                        Last generated: <?php echo $stats['last_generated'] ? human_time_diff($stats['last_generated']) . ' ago' : 'Never'; ?>
                    </span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render quick actions
     */
    private function render_quick_actions() {
        ob_start();
        ?>
        <div class="quick-actions">
            <button type="button" class="button button-primary" id="test-current-page">
                <span class="dashicons dashicons-admin-tools"></span>
                Test Current Page
            </button>
            <button type="button" class="button" id="validate-schema">
                <span class="dashicons dashicons-yes"></span>
                Validate Schema
            </button>
            <button type="button" class="button" id="clear-schema-cache">
                <span class="dashicons dashicons-trash"></span>
                Clear Cache
            </button>
            <div class="dropdown">
                <button type="button" class="button dropdown-toggle" id="external-tools">
                    <span class="dashicons dashicons-external"></span>
                    External Tools
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </button>
                <div class="dropdown-menu" id="external-tools-menu">
                    <?php foreach ($this->testing_tools as $tool_id => $tool): ?>
                        <a href="<?php echo esc_url($tool['url']); ?>" target="_blank" class="dropdown-item">
                            <span class="dashicons dashicons-<?php echo esc_attr($tool['icon']); ?>"></span>
                            <?php echo esc_html($tool['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render general settings tab
     */
    private function render_general_tab() {
        $settings = wp_parse_args(get_option('khm_seo_schema_settings', []), [
            'enable_schema' => true,
            'auto_output' => true,
            'output_location' => 'head',
            'enable_article' => true,
            'enable_organization' => true,
            'enable_person' => true,
            'enable_product' => false,
            'enable_recipe' => false,
            'enable_event' => false,
            'enable_faq' => false,
            'enable_breadcrumb' => true,
            'enable_website' => true
        ]);
        ?>
        <form method="post" action="options.php" class="schema-form">
            <?php settings_fields('khm_seo_schema_general'); ?>
            
            <h3>Schema Generation</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Enable Schema Markup</th>
                    <td>
                        <label>
                            <input type="checkbox" name="khm_seo_schema_settings[enable_schema]" 
                                   value="1" <?php checked($settings['enable_schema']); ?>>
                            Generate structured data markup
                        </label>
                        <p class="description">Enable automatic schema markup generation for your content.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Output Settings</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[auto_output]" 
                                       value="1" <?php checked($settings['auto_output']); ?>>
                                Automatically output schema markup
                            </label><br>
                            
                            <label>
                                Output location:
                                <select name="khm_seo_schema_settings[output_location]">
                                    <option value="head" <?php selected($settings['output_location'], 'head'); ?>>HTML Head</option>
                                    <option value="footer" <?php selected($settings['output_location'], 'footer'); ?>>Footer</option>
                                </select>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <h3>Schema Types</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Content Schemas</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[enable_article]" 
                                       value="1" <?php checked($settings['enable_article']); ?>>
                                Article (for blog posts and pages)
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[enable_product]" 
                                       value="1" <?php checked($settings['enable_product']); ?>>
                                Product (for e-commerce items)
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[enable_recipe]" 
                                       value="1" <?php checked($settings['enable_recipe']); ?>>
                                Recipe (for cooking content)
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[enable_event]" 
                                       value="1" <?php checked($settings['enable_event']); ?>>
                                Event (for upcoming events)
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[enable_faq]" 
                                       value="1" <?php checked($settings['enable_faq']); ?>>
                                FAQ (for question/answer content)
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Site-wide Schemas</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[enable_organization]" 
                                       value="1" <?php checked($settings['enable_organization']); ?>>
                                Organization (business information)
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[enable_person]" 
                                       value="1" <?php checked($settings['enable_person']); ?>>
                                Person (author profiles)
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[enable_website]" 
                                       value="1" <?php checked($settings['enable_website']); ?>>
                                WebSite (site information and search)
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="khm_seo_schema_settings[enable_breadcrumb]" 
                                       value="1" <?php checked($settings['enable_breadcrumb']); ?>>
                                BreadcrumbList (navigation structure)
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render organization tab
     */
    private function render_organization_tab() {
        $settings = wp_parse_args(get_option('khm_seo_organization_settings', []), [
            'organization_name' => get_bloginfo('name'),
            'organization_type' => 'Organization',
            'organization_logo' => '',
            'organization_description' => get_bloginfo('description'),
            'contact_phone' => '',
            'contact_email' => get_option('admin_email'),
            'address_street' => '',
            'address_city' => '',
            'address_region' => '',
            'address_postal_code' => '',
            'address_country' => '',
            'social_facebook' => '',
            'social_twitter' => '',
            'social_instagram' => '',
            'social_linkedin' => '',
            'social_youtube' => ''
        ]);
        ?>
        <form method="post" action="options.php" class="schema-form">
            <?php settings_fields('khm_seo_schema_organization'); ?>
            
            <h3>Basic Information</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Organization Name</th>
                    <td>
                        <input type="text" name="khm_seo_organization_settings[organization_name]" 
                               value="<?php echo esc_attr($settings['organization_name']); ?>" 
                               class="regular-text" required>
                        <p class="description">Your business or organization name.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Organization Type</th>
                    <td>
                        <select name="khm_seo_organization_settings[organization_type]" class="regular-text">
                            <option value="Organization" <?php selected($settings['organization_type'], 'Organization'); ?>>Organization</option>
                            <option value="Corporation" <?php selected($settings['organization_type'], 'Corporation'); ?>>Corporation</option>
                            <option value="LocalBusiness" <?php selected($settings['organization_type'], 'LocalBusiness'); ?>>Local Business</option>
                            <option value="EducationalOrganization" <?php selected($settings['organization_type'], 'EducationalOrganization'); ?>>Educational Organization</option>
                            <option value="GovernmentOrganization" <?php selected($settings['organization_type'], 'GovernmentOrganization'); ?>>Government Organization</option>
                            <option value="NGO" <?php selected($settings['organization_type'], 'NGO'); ?>>Non-Governmental Organization</option>
                        </select>
                        <p class="description">Select the most appropriate type for your organization.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Logo URL</th>
                    <td>
                        <input type="url" name="khm_seo_organization_settings[organization_logo]" 
                               value="<?php echo esc_url($settings['organization_logo']); ?>" 
                               class="regular-text">
                        <button type="button" class="button" id="upload-logo">Upload Logo</button>
                        <p class="description">Logo should be at least 112x112 pixels. Recommended: 600x400px or larger.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Description</th>
                    <td>
                        <textarea name="khm_seo_organization_settings[organization_description]" 
                                  rows="3" cols="50" class="large-text"><?php echo esc_textarea($settings['organization_description']); ?></textarea>
                        <p class="description">Brief description of your organization.</p>
                    </td>
                </tr>
            </table>
            
            <h3>Contact Information</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Phone Number</th>
                    <td>
                        <input type="tel" name="khm_seo_organization_settings[contact_phone]" 
                               value="<?php echo esc_attr($settings['contact_phone']); ?>" 
                               class="regular-text">
                        <p class="description">Primary contact phone number.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Email Address</th>
                    <td>
                        <input type="email" name="khm_seo_organization_settings[contact_email]" 
                               value="<?php echo esc_attr($settings['contact_email']); ?>" 
                               class="regular-text">
                        <p class="description">Primary contact email address.</p>
                    </td>
                </tr>
            </table>
            
            <h3>Address</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Street Address</th>
                    <td>
                        <input type="text" name="khm_seo_organization_settings[address_street]" 
                               value="<?php echo esc_attr($settings['address_street']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">City</th>
                    <td>
                        <input type="text" name="khm_seo_organization_settings[address_city]" 
                               value="<?php echo esc_attr($settings['address_city']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">State/Region</th>
                    <td>
                        <input type="text" name="khm_seo_organization_settings[address_region]" 
                               value="<?php echo esc_attr($settings['address_region']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Postal Code</th>
                    <td>
                        <input type="text" name="khm_seo_organization_settings[address_postal_code]" 
                               value="<?php echo esc_attr($settings['address_postal_code']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Country</th>
                    <td>
                        <input type="text" name="khm_seo_organization_settings[address_country]" 
                               value="<?php echo esc_attr($settings['address_country']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
            </table>
            
            <h3>Social Media Profiles</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Facebook</th>
                    <td>
                        <input type="url" name="khm_seo_organization_settings[social_facebook]" 
                               value="<?php echo esc_url($settings['social_facebook']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Twitter</th>
                    <td>
                        <input type="url" name="khm_seo_organization_settings[social_twitter]" 
                               value="<?php echo esc_url($settings['social_twitter']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Instagram</th>
                    <td>
                        <input type="url" name="khm_seo_organization_settings[social_instagram]" 
                               value="<?php echo esc_url($settings['social_instagram']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">LinkedIn</th>
                    <td>
                        <input type="url" name="khm_seo_organization_settings[social_linkedin]" 
                               value="<?php echo esc_url($settings['social_linkedin']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">YouTube</th>
                    <td>
                        <input type="url" name="khm_seo_organization_settings[social_youtube]" 
                               value="<?php echo esc_url($settings['social_youtube']); ?>" 
                               class="regular-text">
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render validation tab
     */
    private function render_validation_tab() {
        ?>
        <div class="validation-tools">
            <div class="tool-section">
                <h3>Schema Testing Tools</h3>
                <p>Use these tools to test and validate your schema markup:</p>
                
                <div class="testing-tools-grid">
                    <?php foreach ($this->testing_tools as $tool_id => $tool): ?>
                        <div class="testing-tool-card">
                            <div class="tool-icon">
                                <span class="dashicons dashicons-<?php echo esc_attr($tool['icon']); ?>"></span>
                            </div>
                            <div class="tool-info">
                                <h4><?php echo esc_html($tool['name']); ?></h4>
                                <p><?php echo esc_html($tool['description']); ?></p>
                                <a href="<?php echo esc_url($tool['url']); ?>" target="_blank" class="button button-secondary">
                                    Open Tool
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="tool-section">
                <h3>Built-in Validation</h3>
                <p>Test and validate schema for any URL on your site:</p>
                
                <div class="validation-form">
                    <div class="url-input-group">
                        <label for="test-url">Enter URL to test:</label>
                        <input type="url" id="test-url" placeholder="<?php echo esc_url(home_url()); ?>" class="regular-text">
                        <button type="button" class="button button-primary" id="test-url-schema">Test Schema</button>
                    </div>
                    
                    <div id="validation-results" class="validation-results" style="display: none;">
                        <h4>Validation Results</h4>
                        <div class="results-content"></div>
                    </div>
                </div>
            </div>
            
            <div class="tool-section">
                <h3>Custom Schema Validator</h3>
                <p>Validate custom JSON-LD schema markup:</p>
                
                <div class="custom-validation-form">
                    <label for="custom-schema">Paste JSON-LD schema:</label>
                    <textarea id="custom-schema" rows="10" cols="50" class="large-text code" 
                              placeholder='{"@context": "https://schema.org", "@type": "Article", ...}'></textarea>
                    <div class="form-actions">
                        <button type="button" class="button button-primary" id="validate-custom-schema">Validate</button>
                        <button type="button" class="button" id="generate-sample">Generate Sample</button>
                    </div>
                    
                    <div id="custom-validation-results" class="validation-results" style="display: none;">
                        <h4>Custom Validation Results</h4>
                        <div class="results-content"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render statistics tab
     */
    private function render_statistics_tab() {
        $stats = $this->generator->get_schema_statistics();
        ?>
        <div class="schema-statistics">
            <div class="stats-overview">
                <div class="stat-card">
                    <h3>Total Schemas</h3>
                    <div class="stat-number"><?php echo number_format($stats['total_schemas_generated']); ?></div>
                    <p>Generated schema items</p>
                </div>
                
                <div class="stat-card">
                    <h3>Last Generated</h3>
                    <div class="stat-text">
                        <?php 
                        if ($stats['last_generated']) {
                            echo esc_html(human_time_diff($stats['last_generated']) . ' ago');
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </div>
                    <p>Most recent generation</p>
                </div>
                
                <div class="stat-card">
                    <h3>Validation Errors</h3>
                    <div class="stat-number error"><?php echo number_format($stats['validation_errors']); ?></div>
                    <p>Schema validation issues</p>
                </div>
            </div>
            
            <div class="stats-breakdown">
                <h3>Schema Types Breakdown</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Content Type</th>
                            <th>Schema Generated</th>
                            <th>Post Count</th>
                            <th>Coverage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['schemas_by_type'] as $type => $count): ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?></td>
                                <td><?php echo number_format($count); ?></td>
                                <td><?php echo number_format($count); ?></td>
                                <td>100%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Test schema for URL
     */
    public function ajax_test_schema_url() {
        check_ajax_referer('khm_seo_schema_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $url = esc_url_raw($_POST['url']);
        
        if (empty($url)) {
            wp_send_json_error('No URL provided');
        }
        
        // Test schema generation for the URL
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Could not access URL: ' . $response->get_error_message());
        }
        
        $content = wp_remote_retrieve_body($response);
        
        // Extract schema from content
        preg_match_all('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $content, $matches);
        
        if (empty($matches[1])) {
            wp_send_json_error('No schema markup found on this page');
        }
        
        $schemas = [];
        foreach ($matches[1] as $schema_json) {
            $decoded = json_decode($schema_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $schemas[] = $decoded;
            }
        }
        
        wp_send_json_success([
            'url' => $url,
            'schemas_found' => count($schemas),
            'schemas' => $schemas
        ]);
    }

    /**
     * Sanitize schema settings
     */
    public function sanitize_schema_settings($settings) {
        $clean = [];
        
        $boolean_fields = [
            'enable_schema', 'auto_output', 'enable_article', 'enable_organization',
            'enable_person', 'enable_product', 'enable_recipe', 'enable_event',
            'enable_faq', 'enable_breadcrumb', 'enable_website'
        ];
        
        foreach ($boolean_fields as $field) {
            $clean[$field] = !empty($settings[$field]);
        }
        
        $clean['output_location'] = in_array($settings['output_location'] ?? '', ['head', 'footer']) 
            ? $settings['output_location'] : 'head';
            
        return $clean;
    }

    /**
     * Sanitize organization settings
     */
    public function sanitize_organization_settings($settings) {
        return array_map('sanitize_text_field', $settings);
    }
}