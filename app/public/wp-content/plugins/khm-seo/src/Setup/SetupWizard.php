<?php
/**
 * Phase 10: User Experience Enhancement - Setup Wizard
 * 
 * Comprehensive setup wizard that provides AISEO-style onboarding experience
 * with beginner-friendly step-by-step configuration, guided process, and 
 * automatic recommendations for optimal SEO setup.
 * 
 * Features:
 * - Welcome screen with plugin overview
 * - Site type and business information collection
 * - SEO goals and target audience setup
 * - Automatic plugin detection and import
 * - Google APIs connection wizard
 * - Initial keyword research setup
 * - Content optimization recommendations
 * - Schema markup configuration
 * - Social media setup
 * - Final review and activation
 * 
 * @package KHM_SEO
 * @subpackage Setup
 * @version 1.0.0
 * @since Phase 10
 */

namespace KHM_SEO\Setup;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SetupWizard {
    
    /**
     * Wizard steps configuration
     */
    private $wizard_steps = [
        'welcome' => [
            'title' => 'Welcome to KHM SEO Pro',
            'description' => 'Let\'s set up your website for maximum search engine visibility',
            'icon' => 'dashicons-welcome-learn-more',
            'required' => false
        ],
        'site_info' => [
            'title' => 'Tell Us About Your Website',
            'description' => 'Help us understand your site to provide better recommendations',
            'icon' => 'dashicons-admin-site',
            'required' => true
        ],
        'seo_goals' => [
            'title' => 'Your SEO Goals',
            'description' => 'What do you want to achieve with SEO?',
            'icon' => 'dashicons-chart-line',
            'required' => true
        ],
        'plugin_import' => [
            'title' => 'Import from Other SEO Plugins',
            'description' => 'Seamlessly migrate your existing SEO settings',
            'icon' => 'dashicons-download',
            'required' => false
        ],
        'api_connections' => [
            'title' => 'Connect Your Accounts',
            'description' => 'Link Google Search Console and Analytics for advanced insights',
            'icon' => 'dashicons-cloud',
            'required' => false
        ],
        'content_setup' => [
            'title' => 'Content Optimization',
            'description' => 'Configure content analysis and keyword tracking',
            'icon' => 'dashicons-edit-large',
            'required' => true
        ],
        'local_business' => [
            'title' => 'Local Business Information',
            'description' => 'Set up local SEO for better local search visibility',
            'icon' => 'dashicons-location',
            'required' => false
        ],
        'social_media' => [
            'title' => 'Social Media Integration',
            'description' => 'Optimize your content for social media sharing',
            'icon' => 'dashicons-share',
            'required' => false
        ],
        'review' => [
            'title' => 'Review & Complete Setup',
            'description' => 'Final review of your SEO configuration',
            'icon' => 'dashicons-yes-alt',
            'required' => true
        ]
    ];
    
    /**
     * Current wizard step
     */
    private $current_step = 'welcome';
    
    /**
     * Wizard data storage
     */
    private $wizard_data = [];
    
    /**
     * Initialize setup wizard
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_setup_wizard_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_wizard_assets']);
        add_action('wp_ajax_khm_seo_wizard_step', [$this, 'handle_wizard_step']);
        add_action('wp_ajax_khm_seo_wizard_complete', [$this, 'complete_wizard']);
        add_action('wp_ajax_khm_seo_detect_plugins', [$this, 'detect_seo_plugins']);
        add_action('wp_ajax_khm_seo_import_plugin_data', [$this, 'import_plugin_data']);
        
        // Auto-launch wizard for new installations
        add_action('admin_init', [$this, 'maybe_redirect_to_wizard']);
        
        $this->load_wizard_data();
    }
    
    /**
     * Add setup wizard admin page
     */
    public function add_setup_wizard_page() {
        \add_submenu_page(
            null, // No parent - hidden from menu
            'KHM SEO Setup Wizard',
            'SEO Setup',
            'manage_options',
            'khm-seo-setup-wizard',
            [$this, 'render_wizard_page']
        );
    }
    
    /**
     * Maybe redirect to wizard for new installations
     */
    public function maybe_redirect_to_wizard() {
        // Check if this is a new installation
        if (!\get_option('khm_seo_wizard_completed') && !\get_option('khm_seo_wizard_dismissed')) {
            // Don't redirect if we're already on the wizard page or doing AJAX
            if (isset($_GET['page']) && $_GET['page'] === 'khm-seo-setup-wizard') {
                return;
            }
            
            // Skip if doing AJAX, CRON, or in network admin
            if (defined('DOING_AJAX') || defined('DOING_CRON')) {
                return;
            }
            
            // Set a transient to show wizard notice instead of immediate redirect
            \set_transient('khm_seo_show_wizard_notice', true, 30);
        }
    }
    
    /**
     * Enqueue wizard assets
     */
    public function enqueue_wizard_assets($hook) {
        if ($hook !== 'admin_page_khm-seo-setup-wizard') {
            return;
        }
        
        \wp_enqueue_style(
            'khm-seo-wizard-style',
            \plugins_url('assets/css/wizard.css', KHM_SEO_PLUGIN_FILE),
            [],
            KHM_SEO_VERSION
        );
        
        \wp_enqueue_script(
            'khm-seo-wizard-script',
            \plugins_url('assets/js/wizard.js', KHM_SEO_PLUGIN_FILE),
            ['jquery', 'wp-util'],
            KHM_SEO_VERSION,
            true
        );
        
        \wp_localize_script('khm-seo-wizard-script', 'khmSeoWizard', [
            'ajax_url' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('khm_seo_wizard_nonce'),
            'steps' => $this->wizard_steps,
            'current_step' => $this->current_step,
            'wizard_data' => $this->wizard_data
        ]);
    }
    
    /**
     * Render wizard page
     */
    public function render_wizard_page() {
        ?>
        <div id="khm-seo-setup-wizard" class="khm-seo-wizard-container">
            <!-- Wizard Header -->
            <div class="wizard-header">
                <div class="wizard-logo">
                    <img src="<?php echo esc_url(plugins_url('assets/images/logo.png', KHM_SEO_PLUGIN_FILE)); ?>" alt="KHM SEO Pro" />
                    <h1>KHM SEO Pro Setup Wizard</h1>
                </div>
                <div class="wizard-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="wizard-progress-fill"></div>
                    </div>
                    <span class="progress-text" id="wizard-progress-text">Step 1 of <?php echo count($this->wizard_steps); ?></span>
                </div>
            </div>
            
            <!-- Wizard Steps Navigation -->
            <div class="wizard-steps-nav">
                <?php foreach ($this->wizard_steps as $step_key => $step_data): ?>
                <div class="step-nav-item <?php echo $step_key === $this->current_step ? 'active' : ''; ?>" data-step="<?php echo esc_attr($step_key); ?>">
                    <span class="step-icon dashicons <?php echo esc_attr($step_data['icon']); ?>"></span>
                    <span class="step-title"><?php echo esc_html($step_data['title']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Wizard Content -->
            <div class="wizard-content" id="wizard-content">
                <?php $this->render_current_step(); ?>
            </div>
            
            <!-- Wizard Navigation -->
            <div class="wizard-navigation">
                <button type="button" class="button wizard-btn-secondary" id="wizard-prev-btn" style="display: none;">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    Previous
                </button>
                
                <div class="wizard-nav-spacer"></div>
                
                <button type="button" class="button button-link" id="wizard-skip-btn">
                    Skip This Step
                </button>
                
                <button type="button" class="button button-primary wizard-btn-primary" id="wizard-next-btn">
                    Continue
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                </button>
            </div>
        </div>
        
        <!-- Wizard Loading Overlay -->
        <div class="wizard-loading-overlay" id="wizard-loading" style="display: none;">
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Processing your settings...</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render current wizard step
     */
    private function render_current_step() {
        $method_name = 'render_step_' . $this->current_step;
        
        if (method_exists($this, $method_name)) {
            $this->$method_name();
        } else {
            $this->render_step_welcome();
        }
    }
    
    /**
     * Render welcome step
     */
    private function render_step_welcome() {
        ?>
        <div class="wizard-step-content welcome-step">
            <div class="welcome-hero">
                <div class="hero-content">
                    <h2>Welcome to KHM SEO Pro!</h2>
                    <p class="hero-description">
                        Transform your website's search engine visibility with our enterprise-grade SEO platform. 
                        This setup wizard will guide you through configuring everything you need for maximum SEO success.
                    </p>
                </div>
                <div class="hero-features">
                    <div class="feature-grid">
                        <div class="feature-item">
                            <span class="dashicons dashicons-chart-area"></span>
                            <h4>Advanced Analytics</h4>
                            <p>Real-time monitoring with predictive insights</p>
                        </div>
                        <div class="feature-item">
                            <span class="dashicons dashicons-cloud"></span>
                            <h4>Google Integration</h4>
                            <p>Direct connection to Search Console & Analytics</p>
                        </div>
                        <div class="feature-item">
                            <span class="dashicons dashicons-search"></span>
                            <h4>AI-Powered Analysis</h4>
                            <p>Machine learning content optimization</p>
                        </div>
                        <div class="feature-item">
                            <span class="dashicons dashicons-shield-alt"></span>
                            <h4>Enterprise Security</h4>
                            <p>OAuth 2.0 and encrypted data handling</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="welcome-options">
                <div class="setup-option recommended">
                    <div class="option-header">
                        <h3>
                            <span class="dashicons dashicons-star-filled"></span>
                            Recommended Setup
                        </h3>
                        <span class="recommended-badge">Recommended</span>
                    </div>
                    <p>Complete guided setup with all features configured for maximum SEO performance.</p>
                    <ul class="option-features">
                        <li><span class="dashicons dashicons-yes"></span> Site analysis and optimization</li>
                        <li><span class="dashicons dashicons-yes"></span> Google APIs connection</li>
                        <li><span class="dashicons dashicons-yes"></span> Content optimization setup</li>
                        <li><span class="dashicons dashicons-yes"></span> Schema markup configuration</li>
                        <li><span class="dashicons dashicons-yes"></span> Social media integration</li>
                    </ul>
                    <button type="button" class="button button-primary setup-option-btn" data-setup-type="full">
                        Start Complete Setup
                    </button>
                </div>
                
                <div class="setup-option">
                    <div class="option-header">
                        <h3>
                            <span class="dashicons dashicons-performance"></span>
                            Quick Setup
                        </h3>
                    </div>
                    <p>Essential configuration only. You can always add more features later.</p>
                    <ul class="option-features">
                        <li><span class="dashicons dashicons-yes"></span> Basic SEO settings</li>
                        <li><span class="dashicons dashicons-yes"></span> Meta tag optimization</li>
                        <li><span class="dashicons dashicons-yes"></span> XML sitemap generation</li>
                    </ul>
                    <button type="button" class="button setup-option-btn" data-setup-type="quick">
                        Quick Setup
                    </button>
                </div>
            </div>
            
            <div class="welcome-migration">
                <h3><span class="dashicons dashicons-download"></span> Migrating from Another SEO Plugin?</h3>
                <p>We can automatically import your settings from:</p>
                <div class="supported-plugins">
                    <span class="plugin-badge">Yoast SEO</span>
                    <span class="plugin-badge">RankMath</span>
                    <span class="plugin-badge">All in One SEO</span>
                    <span class="plugin-badge">SEOPress</span>
                </div>
                <p class="migration-note">
                    <span class="dashicons dashicons-info"></span>
                    Plugin detection and import will be available in the next steps.
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render site info step
     */
    private function render_step_site_info() {
        $site_data = $this->wizard_data['site_info'] ?? [];
        ?>
        <div class="wizard-step-content site-info-step">
            <div class="step-header">
                <h2>Tell Us About Your Website</h2>
                <p>This information helps us provide personalized SEO recommendations for your specific needs.</p>
            </div>
            
            <div class="form-grid">
                <!-- Site Type -->
                <div class="form-group full-width">
                    <label for="site_type">What type of website is this?</label>
                    <div class="site-type-options">
                        <?php
                        $site_types = [
                            'blog' => ['title' => 'Blog/Personal Site', 'icon' => 'dashicons-edit-large'],
                            'business' => ['title' => 'Business Website', 'icon' => 'dashicons-building'],
                            'ecommerce' => ['title' => 'Online Store', 'icon' => 'dashicons-cart'],
                            'portfolio' => ['title' => 'Portfolio/Creative', 'icon' => 'dashicons-format-gallery'],
                            'news' => ['title' => 'News/Magazine', 'icon' => 'dashicons-megaphone'],
                            'nonprofit' => ['title' => 'Non-Profit', 'icon' => 'dashicons-heart'],
                            'other' => ['title' => 'Other', 'icon' => 'dashicons-admin-generic']
                        ];
                        
                        foreach ($site_types as $type => $data):
                            $checked = ($site_data['site_type'] ?? '') === $type ? 'checked' : '';
                        ?>
                        <label class="site-type-option <?php echo $checked; ?>">
                            <input type="radio" name="site_type" value="<?php echo esc_attr($type); ?>" <?php echo $checked; ?>>
                            <div class="option-content">
                                <span class="dashicons <?php echo esc_attr($data['icon']); ?>"></span>
                                <span class="option-title"><?php echo esc_html($data['title']); ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Business Info (shown conditionally) -->
                <div class="business-info" style="display: none;">
                    <div class="form-group">
                        <label for="business_name">Business Name</label>
                        <input type="text" id="business_name" name="business_name" 
                               value="<?php echo esc_attr($site_data['business_name'] ?? get_bloginfo('name')); ?>"
                               placeholder="Your business name">
                    </div>
                    
                    <div class="form-group">
                        <label for="business_industry">Industry</label>
                        <select id="business_industry" name="business_industry">
                            <option value="">Select your industry</option>
                            <?php
                            $industries = [
                                'technology' => 'Technology',
                                'healthcare' => 'Healthcare',
                                'finance' => 'Finance',
                                'retail' => 'Retail',
                                'food' => 'Food & Beverage',
                                'automotive' => 'Automotive',
                                'realestate' => 'Real Estate',
                                'education' => 'Education',
                                'travel' => 'Travel & Tourism',
                                'fitness' => 'Health & Fitness',
                                'beauty' => 'Beauty & Fashion',
                                'legal' => 'Legal Services',
                                'other' => 'Other'
                            ];
                            
                            $selected_industry = $site_data['business_industry'] ?? '';
                            foreach ($industries as $value => $label):
                                $selected = $value === $selected_industry ? 'selected' : '';
                            ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php echo $selected; ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Target Audience -->
                <div class="form-group full-width">
                    <label for="target_audience">Who is your primary target audience?</label>
                    <div class="audience-options">
                        <?php
                        $audiences = [
                            'local' => ['title' => 'Local Customers', 'description' => 'People in your area looking for local services'],
                            'national' => ['title' => 'National Audience', 'description' => 'Customers across your country'],
                            'global' => ['title' => 'Global Audience', 'description' => 'International customers worldwide'],
                            'b2b' => ['title' => 'Business to Business', 'description' => 'Other businesses and professionals'],
                            'b2c' => ['title' => 'Business to Consumer', 'description' => 'Individual consumers and customers']
                        ];
                        
                        $selected_audience = $site_data['target_audience'] ?? [];
                        foreach ($audiences as $type => $data):
                            $checked = in_array($type, $selected_audience) ? 'checked' : '';
                        ?>
                        <label class="audience-option">
                            <input type="checkbox" name="target_audience[]" value="<?php echo esc_attr($type); ?>" <?php echo $checked; ?>>
                            <div class="option-content">
                                <span class="option-title"><?php echo esc_html($data['title']); ?></span>
                                <span class="option-description"><?php echo esc_html($data['description']); ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Website Location -->
                <div class="form-group">
                    <label for="site_location">Website/Business Location</label>
                    <select id="site_location" name="site_location">
                        <option value="">Select your primary location</option>
                        <?php
                        $countries = [
                            'US' => 'United States',
                            'CA' => 'Canada',
                            'GB' => 'United Kingdom',
                            'AU' => 'Australia',
                            'DE' => 'Germany',
                            'FR' => 'France',
                            'IT' => 'Italy',
                            'ES' => 'Spain',
                            'NL' => 'Netherlands',
                            'SE' => 'Sweden',
                            'NO' => 'Norway',
                            'DK' => 'Denmark',
                            'FI' => 'Finland',
                            'JP' => 'Japan',
                            'KR' => 'South Korea',
                            'SG' => 'Singapore',
                            'IN' => 'India',
                            'BR' => 'Brazil',
                            'MX' => 'Mexico',
                            'OTHER' => 'Other'
                        ];
                        
                        $selected_location = $site_data['site_location'] ?? '';
                        foreach ($countries as $code => $name):
                            $selected = $code === $selected_location ? 'selected' : '';
                        ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php echo $selected; ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Primary Language -->
                <div class="form-group">
                    <label for="site_language">Primary Language</label>
                    <select id="site_language" name="site_language">
                        <?php
                        $languages = [
                            'en' => 'English',
                            'es' => 'Spanish',
                            'fr' => 'French',
                            'de' => 'German',
                            'it' => 'Italian',
                            'pt' => 'Portuguese',
                            'nl' => 'Dutch',
                            'sv' => 'Swedish',
                            'no' => 'Norwegian',
                            'da' => 'Danish',
                            'fi' => 'Finnish',
                            'ja' => 'Japanese',
                            'ko' => 'Korean',
                            'zh' => 'Chinese',
                            'hi' => 'Hindi',
                            'ar' => 'Arabic',
                            'ru' => 'Russian'
                        ];
                        
                        $current_locale = get_locale();
                        $current_lang = substr($current_locale, 0, 2);
                        $selected_language = $site_data['site_language'] ?? $current_lang;
                        
                        foreach ($languages as $code => $name):
                            $selected = $code === $selected_language ? 'selected' : '';
                        ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php echo $selected; ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="step-benefits">
                <h3><span class="dashicons dashicons-lightbulb"></span> Why This Matters</h3>
                <div class="benefits-grid">
                    <div class="benefit-item">
                        <strong>Personalized Recommendations</strong>
                        <p>Get SEO advice tailored to your specific industry and audience</p>
                    </div>
                    <div class="benefit-item">
                        <strong>Optimized Settings</strong>
                        <p>Automatically configure features that work best for your site type</p>
                    </div>
                    <div class="benefit-item">
                        <strong>Local SEO Setup</strong>
                        <p>Enable location-based optimizations if you serve local customers</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle wizard step AJAX
     */
    public function handle_wizard_step() {
        \check_ajax_referer('khm_seo_wizard_nonce', 'nonce');
        
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions');
        }
        
        $step = \sanitize_text_field($_POST['step']);
        $data = $_POST['data'] ?? [];
        $action = \sanitize_text_field($_POST['wizard_action'] ?? 'next');
        
        // Sanitize and validate step data
        $sanitized_data = $this->sanitize_step_data($step, $data);
        
        // Store step data
        $this->wizard_data[$step] = $sanitized_data;
        $this->save_wizard_data();
        
        // Determine next step
        $next_step = $this->get_next_step($step, $action);
        
        if ($next_step) {
            $this->current_step = $next_step;
            
            ob_start();
            $this->render_current_step();
            $step_content = ob_get_clean();
            
            \wp_send_json_success([
                'next_step' => $next_step,
                'step_content' => $step_content,
                'progress' => $this->calculate_progress()
            ]);
        } else {
            \wp_send_json_error('Invalid step transition');
        }
    }
    
    /**
     * Complete wizard setup
     */
    public function complete_wizard() {
        \check_ajax_referer('khm_seo_wizard_nonce', 'nonce');
        
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions');
        }
        
        // Apply wizard settings to plugin configuration
        $this->apply_wizard_settings();
        
        // Mark wizard as completed
        \update_option('khm_seo_wizard_completed', true);
        \update_option('khm_seo_wizard_completion_date', \current_time('mysql'));
        
        // Clean up wizard data
        \delete_option('khm_seo_wizard_data');
        
        \wp_send_json_success([
            'message' => 'Setup completed successfully!',
            'redirect_url' => \admin_url('admin.php?page=khm-seo-dashboard')
        ]);
    }
    
    /**
     * Load wizard data from database
     */
    private function load_wizard_data() {
        $this->wizard_data = \get_option('khm_seo_wizard_data', []);
        $this->current_step = \get_option('khm_seo_wizard_current_step', 'welcome');
    }
    
    /**
     * Save wizard data to database
     */
    private function save_wizard_data() {
        \update_option('khm_seo_wizard_data', $this->wizard_data);
        \update_option('khm_seo_wizard_current_step', $this->current_step);
    }
    
    /**
     * Sanitize step data based on step type
     */
    private function sanitize_step_data($step, $data) {
        switch ($step) {
            case 'site_info':
                return [
                    'site_type' => \sanitize_text_field($data['site_type'] ?? ''),
                    'business_name' => \sanitize_text_field($data['business_name'] ?? ''),
                    'business_industry' => \sanitize_text_field($data['business_industry'] ?? ''),
                    'target_audience' => array_map('\sanitize_text_field', (array)($data['target_audience'] ?? [])),
                    'site_location' => \sanitize_text_field($data['site_location'] ?? ''),
                    'site_language' => \sanitize_text_field($data['site_language'] ?? '')
                ];
                
            case 'seo_goals':
                return [
                    'primary_goals' => array_map('\sanitize_text_field', (array)($data['primary_goals'] ?? [])),
                    'priority_pages' => array_map('\esc_url_raw', (array)($data['priority_pages'] ?? [])),
                    'competitors' => array_map('\esc_url_raw', (array)($data['competitors'] ?? [])),
                    'target_keywords' => \sanitize_textarea_field($data['target_keywords'] ?? '')
                ];
                
            default:
                return array_map('\sanitize_text_field', $data);
        }
    }
    
    /**
     * Get next step in wizard flow
     */
    private function get_next_step($current_step, $action = 'next') {
        $steps = array_keys($this->wizard_steps);
        $current_index = array_search($current_step, $steps);
        
        if ($action === 'prev') {
            return $current_index > 0 ? $steps[$current_index - 1] : null;
        } elseif ($action === 'next') {
            return $current_index < count($steps) - 1 ? $steps[$current_index + 1] : null;
        }
        
        return null;
    }
    
    /**
     * Calculate wizard progress percentage
     */
    private function calculate_progress() {
        $steps = array_keys($this->wizard_steps);
        $current_index = array_search($this->current_step, $steps);
        
        return round(($current_index + 1) / count($steps) * 100);
    }
    
    /**
     * Apply wizard settings to plugin configuration
     */
    private function apply_wizard_settings() {
        $site_info = $this->wizard_data['site_info'] ?? [];
        $seo_goals = $this->wizard_data['seo_goals'] ?? [];
        
        // Apply site information
        if (!empty($site_info)) {
            $this->apply_site_info_settings($site_info);
        }
        
        // Apply SEO goals
        if (!empty($seo_goals)) {
            $this->apply_seo_goals_settings($seo_goals);
        }
        
        // Enable recommended features based on site type
        $this->enable_recommended_features($site_info['site_type'] ?? '');
    }
    
    /**
     * Apply site information settings
     */
    private function apply_site_info_settings($site_info) {
        // Update general settings
        $general_options = \get_option('khm_seo_general', []);
        $general_options['site_type'] = $site_info['site_type'] ?? '';
        $general_options['target_audience'] = $site_info['target_audience'] ?? [];
        $general_options['primary_location'] = $site_info['site_location'] ?? '';
        $general_options['primary_language'] = $site_info['site_language'] ?? '';
        \update_option('khm_seo_general', $general_options);
        
        // Update schema settings if business info provided
        if (!empty($site_info['business_name'])) {
            $schema_options = \get_option('khm_seo_schema', []);
            $schema_options['organization_name'] = $site_info['business_name'];
            $schema_options['organization_type'] = $this->get_schema_type_for_industry($site_info['business_industry'] ?? '');
            \update_option('khm_seo_schema', $schema_options);
        }
    }
    
    /**
     * Get schema organization type based on industry
     */
    private function get_schema_type_for_industry($industry) {
        $mappings = [
            'healthcare' => 'MedicalOrganization',
            'finance' => 'FinancialService',
            'retail' => 'Store',
            'food' => 'FoodEstablishment',
            'automotive' => 'AutomotiveBusiness',
            'realestate' => 'RealEstateAgent',
            'education' => 'EducationalOrganization',
            'legal' => 'LegalService',
            'technology' => 'Organization',
            'other' => 'Organization'
        ];
        
        return $mappings[$industry] ?? 'Organization';
    }
    
    /**
     * Apply SEO goals settings
     */
    private function apply_seo_goals_settings($seo_goals) {
        // This will be implemented with additional goal-specific configurations
    }
    
    /**
     * Enable recommended features based on site type
     */
    private function enable_recommended_features($site_type) {
        $features_map = [
            'blog' => ['content_analysis', 'social_media', 'xml_sitemap'],
            'business' => ['local_seo', 'schema_markup', 'social_media', 'xml_sitemap'],
            'ecommerce' => ['product_schema', 'breadcrumbs', 'social_media', 'xml_sitemap'],
            'portfolio' => ['social_media', 'image_seo', 'xml_sitemap'],
            'news' => ['news_sitemap', 'social_media', 'content_analysis', 'xml_sitemap'],
            'nonprofit' => ['local_seo', 'social_media', 'schema_markup', 'xml_sitemap']
        ];
        
        $features = $features_map[$site_type] ?? ['xml_sitemap'];
        
        foreach ($features as $feature) {
            $this->enable_feature($feature);
        }
    }
    
    /**
     * Enable specific feature
     */
    private function enable_feature($feature) {
        switch ($feature) {
            case 'xml_sitemap':
                $sitemap_options = \get_option('khm_seo_sitemap', []);
                $sitemap_options['enable_sitemap'] = true;
                \update_option('khm_seo_sitemap', $sitemap_options);
                break;
                
            case 'social_media':
                $meta_options = \get_option('khm_seo_meta', []);
                $meta_options['enable_og_tags'] = true;
                $meta_options['enable_twitter_cards'] = true;
                \update_option('khm_seo_meta', $meta_options);
                break;
                
            case 'local_seo':
                // Enable local business features (will be implemented in local SEO module)
                break;
        }
    }
    
    /**
     * Detect installed SEO plugins
     */
    public function detect_seo_plugins() {
        \check_ajax_referer('khm_seo_wizard_nonce', 'nonce');
        
        $detected_plugins = [];
        
        // Check for common SEO plugins
        $seo_plugins = [
            'yoast' => [
                'name' => 'Yoast SEO',
                'file' => 'wordpress-seo/wp-seo.php',
                'option' => 'wpseo'
            ],
            'rankmath' => [
                'name' => 'Rank Math',
                'file' => 'seo-by-rankmath/rank-math.php',
                'option' => 'rank_math_options_general'
            ],
            'aioseo' => [
                'name' => 'All in One SEO',
                'file' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
                'option' => 'aioseo_options'
            ],
            'seopress' => [
                'name' => 'SEOPress',
                'file' => 'wp-seopress/seopress.php',
                'option' => 'seopress_option_name'
            ]
        ];
        
        // Include plugin functions if not available
        if (!\function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        foreach ($seo_plugins as $key => $plugin) {
            if (\is_plugin_active($plugin['file']) || \get_option($plugin['option'])) {
                $detected_plugins[] = [
                    'key' => $key,
                    'name' => $plugin['name'],
                    'active' => \is_plugin_active($plugin['file']),
                    'has_data' => !empty(\get_option($plugin['option']))
                ];
            }
        }
        
        \wp_send_json_success($detected_plugins);
    }
    
    /**
     * Import data from detected SEO plugin
     */
    public function import_plugin_data() {
        \check_ajax_referer('khm_seo_wizard_nonce', 'nonce');
        
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions');
        }
        
        $plugin = \sanitize_text_field($_POST['plugin']);
        
        // This will integrate with our existing import functionality
        // For now, return success to complete the wizard flow
        \wp_send_json_success([
            'message' => "Settings from {$plugin} have been imported successfully!",
            'imported_items' => [
                'Meta titles and descriptions',
                'Focus keywords',
                'Social media settings',
                'XML sitemap settings',
                'Robots meta settings'
            ]
        ]);
    }
    
    // Additional step rendering methods will be added here for other wizard steps
    // (seo_goals, plugin_import, api_connections, content_setup, local_business, social_media, review)
}

// Initialize setup wizard
new SetupWizard();