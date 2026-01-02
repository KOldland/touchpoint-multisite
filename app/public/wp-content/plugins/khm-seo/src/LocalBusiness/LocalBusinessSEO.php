<?php
/**
 * Phase 10: User Experience Enhancement - Local Business SEO Module
 * 
 * Comprehensive local SEO system that provides AISEO-style local business
 * functionality with enhanced enterprise features including multiple location
 * management, Google My Business integration, local schema markup, review
 * management, and knowledge graph optimization.
 * 
 * Features:
 * - Multiple business location management
 * - Google My Business API integration
 * - Local business schema markup (JSON-LD)
 * - Review management and display
 * - Local keyword tracking and optimization
 * - NAP (Name, Address, Phone) consistency checking
 * - Local search performance analytics
 * - Google Maps integration
 * - Opening hours management
 * - Local event and promotion tracking
 * - Citation tracking and management
 * - Local competitor analysis
 * 
 * @package KHM_SEO
 * @subpackage LocalBusiness
 * @version 1.0.0
 * @since Phase 10
 */

namespace KHM_SEO\LocalBusiness;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class LocalBusinessSEO {
    
    /**
     * Current settings
     */
    private $settings = [];
    
    /**
     * Default settings
     */
    private $default_settings = [
        'local_seo_enabled' => true,
        'primary_business_name' => '',
        'business_type' => 'LocalBusiness',
        'primary_address' => [
            'street_address' => '',
            'locality' => '',
            'region' => '',
            'postal_code' => '',
            'country' => ''
        ],
        'primary_phone' => '',
        'primary_email' => '',
        'primary_website' => '',
        'multiple_locations' => false,
        'opening_hours' => [
            'monday' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
            'tuesday' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
            'wednesday' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
            'thursday' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
            'friday' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
            'saturday' => ['open' => '09:00', 'close' => '17:00', 'closed' => false],
            'sunday' => ['open' => '09:00', 'close' => '17:00', 'closed' => true]
        ],
        'social_profiles' => [
            'facebook' => '',
            'twitter' => '',
            'instagram' => '',
            'linkedin' => '',
            'youtube' => '',
            'yelp' => '',
            'google_my_business' => ''
        ],
        'schema_markup_enabled' => true,
        'review_management_enabled' => true,
        'google_my_business_api' => [
            'enabled' => false,
            'api_key' => '',
            'account_id' => '',
            'location_id' => ''
        ],
        'local_keywords' => [],
        'service_areas' => [],
        'business_features' => [],
        'price_range' => '',
        'accepts_reservations' => false,
        'takeout_available' => false,
        'delivery_available' => false,
        'payment_methods' => [],
        'languages_spoken' => [],
        'accessibility_features' => [],
        'citation_tracking' => true,
        'nap_monitoring' => true,
        'auto_update_schema' => true,
        'track_local_rankings' => true
    ];
    
    /**
     * Business type schemas
     */
    private $business_types = [
        'LocalBusiness' => 'General Local Business',
        'Restaurant' => 'Restaurant',
        'Hotel' => 'Hotel',
        'Store' => 'Retail Store',
        'AutoDealer' => 'Auto Dealer',
        'AutomotiveBusiness' => 'Automotive Business',
        'ChildCare' => 'Child Care',
        'Dentist' => 'Dentist',
        'DryCleaningOrLaundry' => 'Dry Cleaning',
        'EmergencyService' => 'Emergency Service',
        'EmploymentAgency' => 'Employment Agency',
        'EntertainmentBusiness' => 'Entertainment Business',
        'FinancialService' => 'Financial Service',
        'FoodEstablishment' => 'Food Establishment',
        'GovernmentOffice' => 'Government Office',
        'HealthAndBeautyBusiness' => 'Health & Beauty',
        'HomeAndConstructionBusiness' => 'Home & Construction',
        'InternetCafe' => 'Internet Cafe',
        'LegalService' => 'Legal Service',
        'Library' => 'Library',
        'LodgingBusiness' => 'Lodging Business',
        'MedicalOrganization' => 'Medical Organization',
        'ProfessionalService' => 'Professional Service',
        'RadioStation' => 'Radio Station',
        'RealEstateAgent' => 'Real Estate Agent',
        'RecyclingCenter' => 'Recycling Center',
        'SelfStorage' => 'Self Storage',
        'ShoppingCenter' => 'Shopping Center',
        'SportsActivityLocation' => 'Sports Activity Location',
        'TelevisionStation' => 'Television Station',
        'TouristInformationCenter' => 'Tourist Information Center',
        'TravelAgency' => 'Travel Agency'
    ];
    
    /**
     * Initialize local business SEO
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
        
        // Frontend schema output
        \add_action('wp_head', [$this, 'output_local_business_schema']);
        \add_action('wp_footer', [$this, 'output_microdata_markup']);
        
        // Post type for locations
        \add_action('init', [$this, 'register_location_post_type']);
        \add_action('add_meta_boxes', [$this, 'add_location_meta_boxes']);
        \add_action('save_post', [$this, 'save_location_meta'], 10, 2);
        
        // AJAX handlers
        \add_action('wp_ajax_khm_seo_validate_nap', [$this, 'handle_nap_validation']);
        \add_action('wp_ajax_khm_seo_sync_gmb', [$this, 'handle_gmb_sync']);
        \add_action('wp_ajax_khm_seo_track_citation', [$this, 'handle_citation_tracking']);
        \add_action('wp_ajax_khm_seo_local_rank_check', [$this, 'handle_local_rank_check']);
        
        // Shortcodes
        \add_shortcode('local_business_info', [$this, 'local_business_shortcode']);
        \add_shortcode('business_hours', [$this, 'business_hours_shortcode']);
        \add_shortcode('business_reviews', [$this, 'business_reviews_shortcode']);
        \add_shortcode('google_map', [$this, 'google_map_shortcode']);
        
        // Widget registration
        \add_action('widgets_init', [$this, 'register_local_widgets']);
        
        // REST API endpoints
        \add_action('rest_api_init', [$this, 'register_rest_endpoints']);
        
        // Cron jobs for monitoring
        \add_action('khm_seo_nap_check', [$this, 'check_nap_consistency']);
        \add_action('khm_seo_citation_monitor', [$this, 'monitor_citations']);
        \add_action('khm_seo_local_rank_track', [$this, 'track_local_rankings']);
        
        // Filter hooks
        \add_filter('khm_seo_local_schema', [$this, 'enhance_local_schema'], 10, 2);
        \add_filter('khm_seo_business_info', [$this, 'apply_business_filters'], 10, 2);
        
        // Schedule cron jobs if not exists
        if (!\wp_next_scheduled('khm_seo_nap_check')) {
            \wp_schedule_event(time(), 'daily', 'khm_seo_nap_check');
        }
        
        if (!\wp_next_scheduled('khm_seo_citation_monitor')) {
            \wp_schedule_event(time(), 'weekly', 'khm_seo_citation_monitor');
        }
    }
    
    /**
     * Load settings
     */
    private function load_settings() {
        $saved_settings = \get_option('khm_seo_local_business', []);
        $this->settings = array_merge($this->default_settings, $saved_settings);
        
        // Set defaults from WordPress settings if empty
        if (empty($this->settings['primary_business_name'])) {
            $this->settings['primary_business_name'] = \get_bloginfo('name');
        }
        
        if (empty($this->settings['primary_website'])) {
            $this->settings['primary_website'] = \home_url();
        }
        
        if (empty($this->settings['primary_email'])) {
            $this->settings['primary_email'] = \get_option('admin_email');
        }
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        \add_submenu_page(
            'khm-seo-dashboard',
            'Local Business SEO',
            'Local Business',
            'manage_options',
            'khm-seo-local-business',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        \register_setting('khm_seo_local_business', 'khm_seo_local_business', [
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
                switch ($key) {
                    case 'primary_phone':
                        $clean_settings[$key] = $this->sanitize_phone_number($settings[$key]);
                        break;
                    case 'primary_email':
                        $clean_settings[$key] = \sanitize_email($settings[$key]);
                        break;
                    case 'primary_website':
                        $clean_settings[$key] = \esc_url_raw($settings[$key]);
                        break;
                    default:
                        if (is_array($default_value)) {
                            $clean_settings[$key] = $this->sanitize_array($settings[$key]);
                        } elseif (is_bool($default_value)) {
                            $clean_settings[$key] = (bool) $settings[$key];
                        } else {
                            $clean_settings[$key] = \sanitize_text_field($settings[$key]);
                        }
                        break;
                }
            } else {
                $clean_settings[$key] = $default_value;
            }
        }
        
        return $clean_settings;
    }
    
    /**
     * Sanitize phone number
     */
    private function sanitize_phone_number($phone) {
        // Remove all non-numeric characters except + and spaces
        $phone = preg_replace('/[^\d\+\s\-\(\)]/', '', $phone);
        return trim($phone);
    }
    
    /**
     * Sanitize array recursively
     */
    private function sanitize_array($array) {
        if (!is_array($array)) {
            return \sanitize_text_field($array);
        }
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sanitize_array($value);
            } else {
                $array[$key] = \sanitize_text_field($value);
            }
        }
        
        return $array;
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap khm-seo-local-business-admin">
            <h1>
                <span class="dashicons dashicons-location"></span>
                Local Business SEO
            </h1>
            
            <div class="khm-seo-admin-header">
                <div class="header-content">
                    <h2>Optimize Your Local Search Presence</h2>
                    <p>Manage your local business information, schema markup, and search engine visibility to attract more local customers.</p>
                </div>
                <div class="header-actions">
                    <button type="button" class="button button-secondary" id="validate-nap">
                        <span class="dashicons dashicons-search"></span>
                        Validate NAP
                    </button>
                    <button type="button" class="button button-secondary" id="sync-gmb">
                        <span class="dashicons dashicons-update"></span>
                        Sync with Google
                    </button>
                </div>
            </div>
            
            <!-- Local SEO Performance Dashboard -->
            <div class="khm-seo-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-location"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="locations-count">-</div>
                        <div class="stat-label">Business Locations</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-star-filled"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="review-rating">-</div>
                        <div class="stat-label">Average Rating</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="local-rankings">-</div>
                        <div class="stat-label">Local Keywords Ranking</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><span class="dashicons dashicons-yes"></span></div>
                    <div class="stat-content">
                        <div class="stat-number" id="nap-status">-</div>
                        <div class="stat-label">NAP Consistency</div>
                    </div>
                </div>
            </div>
            
            <form method="post" action="options.php" class="khm-seo-settings-form">
                <?php \settings_fields('khm_seo_local_business'); ?>
                
                <div class="khm-seo-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#business-info" class="nav-tab nav-tab-active">Business Information</a>
                        <a href="#locations" class="nav-tab">Multiple Locations</a>
                        <a href="#schema-markup" class="nav-tab">Schema Markup</a>
                        <a href="#reviews" class="nav-tab">Reviews & Ratings</a>
                        <a href="#google-integration" class="nav-tab">Google Integration</a>
                        <a href="#monitoring" class="nav-tab">Monitoring & Analytics</a>
                    </nav>
                    
                    <!-- Business Information Tab -->
                    <div id="business-info" class="tab-content active">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-building"></span> Primary Business Information</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="local_seo_enabled">Enable Local SEO</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_local_business[local_seo_enabled]" id="local_seo_enabled" value="1" <?php \checked($this->settings['local_seo_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Enable local business SEO features and schema markup.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="primary_business_name">Business Name</label>
                                    </th>
                                    <td>
                                        <input type="text" name="khm_seo_local_business[primary_business_name]" id="primary_business_name" value="<?php echo \esc_attr($this->settings['primary_business_name']); ?>" class="regular-text" required>
                                        <p class="description">Official name of your business as it appears on your storefront and legal documents.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="business_type">Business Type</label>
                                    </th>
                                    <td>
                                        <select name="khm_seo_local_business[business_type]" id="business_type" class="regular-text">
                                            <?php
                                            foreach ($this->business_types as $type => $label) {
                                                $selected = selected($this->settings['business_type'], $type, false);
                                                echo "<option value='{$type}' {$selected}>{$label}</option>";
                                            }
                                            ?>
                                        </select>
                                        <p class="description">Choose the schema.org type that best describes your business.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Contact Information</th>
                                    <td>
                                        <div class="contact-info-grid">
                                            <div class="contact-field">
                                                <label for="primary_phone">Phone Number</label>
                                                <input type="tel" name="khm_seo_local_business[primary_phone]" id="primary_phone" value="<?php echo \esc_attr($this->settings['primary_phone']); ?>" class="regular-text">
                                            </div>
                                            <div class="contact-field">
                                                <label for="primary_email">Email Address</label>
                                                <input type="email" name="khm_seo_local_business[primary_email]" id="primary_email" value="<?php echo \esc_attr($this->settings['primary_email']); ?>" class="regular-text">
                                            </div>
                                            <div class="contact-field full-width">
                                                <label for="primary_website">Website URL</label>
                                                <input type="url" name="khm_seo_local_business[primary_website]" id="primary_website" value="<?php echo \esc_attr($this->settings['primary_website']); ?>" class="regular-text">
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-location-alt"></span> Business Address</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Address</th>
                                    <td>
                                        <div class="address-grid">
                                            <div class="address-field full-width">
                                                <label for="street_address">Street Address</label>
                                                <input type="text" name="khm_seo_local_business[primary_address][street_address]" id="street_address" value="<?php echo \esc_attr($this->settings['primary_address']['street_address']); ?>" class="regular-text">
                                            </div>
                                            <div class="address-field">
                                                <label for="locality">City</label>
                                                <input type="text" name="khm_seo_local_business[primary_address][locality]" id="locality" value="<?php echo \esc_attr($this->settings['primary_address']['locality']); ?>" class="regular-text">
                                            </div>
                                            <div class="address-field">
                                                <label for="region">State/Province</label>
                                                <input type="text" name="khm_seo_local_business[primary_address][region]" id="region" value="<?php echo \esc_attr($this->settings['primary_address']['region']); ?>" class="regular-text">
                                            </div>
                                            <div class="address-field">
                                                <label for="postal_code">ZIP/Postal Code</label>
                                                <input type="text" name="khm_seo_local_business[primary_address][postal_code]" id="postal_code" value="<?php echo \esc_attr($this->settings['primary_address']['postal_code']); ?>" class="regular-text">
                                            </div>
                                            <div class="address-field">
                                                <label for="country">Country</label>
                                                <select name="khm_seo_local_business[primary_address][country]" id="country" class="regular-text">
                                                    <option value="">Select Country</option>
                                                    <?php
                                                    $countries = $this->get_countries_list();
                                                    foreach ($countries as $code => $name) {
                                                        $selected = selected($this->settings['primary_address']['country'], $code, false);
                                                        echo "<option value='{$code}' {$selected}>{$name}</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <p class="description">Complete business address for local search optimization.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-clock"></span> Business Hours</h3>
                            
                            <div class="business-hours-container">
                                <?php
                                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                foreach ($days as $day) {
                                    $day_data = $this->settings['opening_hours'][$day];
                                    $day_label = ucfirst($day);
                                    ?>
                                    <div class="business-hours-day">
                                        <div class="day-label"><?php echo $day_label; ?></div>
                                        <div class="hours-controls">
                                            <label class="closed-toggle">
                                                <input type="checkbox" name="khm_seo_local_business[opening_hours][<?php echo $day; ?>][closed]" value="1" <?php checked($day_data['closed']); ?>>
                                                Closed
                                            </label>
                                            <div class="time-inputs" <?php echo $day_data['closed'] ? 'style="display:none;"' : ''; ?>>
                                                <input type="time" name="khm_seo_local_business[opening_hours][<?php echo $day; ?>][open]" value="<?php echo \esc_attr($day_data['open']); ?>" class="time-input">
                                                <span class="time-separator">to</span>
                                                <input type="time" name="khm_seo_local_business[opening_hours][<?php echo $day; ?>][close]" value="<?php echo \esc_attr($day_data['close']); ?>" class="time-input">
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-share"></span> Social Media Profiles</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Social Profiles</th>
                                    <td>
                                        <div class="social-profiles-grid">
                                            <?php
                                            $social_platforms = [
                                                'facebook' => 'Facebook',
                                                'twitter' => 'Twitter',
                                                'instagram' => 'Instagram',
                                                'linkedin' => 'LinkedIn',
                                                'youtube' => 'YouTube',
                                                'yelp' => 'Yelp',
                                                'google_my_business' => 'Google My Business'
                                            ];
                                            
                                            foreach ($social_platforms as $platform => $label) {
                                                ?>
                                                <div class="social-field">
                                                    <label for="social_<?php echo $platform; ?>"><?php echo $label; ?></label>
                                                    <input type="url" name="khm_seo_local_business[social_profiles][<?php echo $platform; ?>]" id="social_<?php echo $platform; ?>" value="<?php echo \esc_attr($this->settings['social_profiles'][$platform]); ?>" class="regular-text" placeholder="https://">
                                                </div>
                                                <?php
                                            }
                                            ?>
                                        </div>
                                        <p class="description">Add your business social media profiles to enhance local SEO.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Multiple Locations Tab -->
                    <div id="locations" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-admin-multisite"></span> Multiple Locations Management</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="multiple_locations">Multiple Locations</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_local_business[multiple_locations]" id="multiple_locations" value="1" <?php \checked($this->settings['multiple_locations']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Enable if your business has multiple physical locations.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="locations-manager" id="locations-manager" <?php echo !$this->settings['multiple_locations'] ? 'style="display:none;"' : ''; ?>>
                                <div class="locations-header">
                                    <h4>Manage Business Locations</h4>
                                    <button type="button" class="button button-primary" id="add-location">
                                        <span class="dashicons dashicons-plus"></span>
                                        Add Location
                                    </button>
                                </div>
                                
                                <div class="locations-list" id="locations-list">
                                    <!-- Locations will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-admin-site"></span> Service Areas</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="service_areas">Service Areas</label>
                                    </th>
                                    <td>
                                        <textarea name="khm_seo_local_business[service_areas]" id="service_areas" class="large-text" rows="5" placeholder="Enter cities, regions, or ZIP codes you serve (one per line)"><?php echo \esc_textarea(implode("\n", $this->settings['service_areas'])); ?></textarea>
                                        <p class="description">List the geographic areas where your business provides services.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Schema Markup Tab -->
                    <div id="schema-markup" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-editor-code"></span> Local Business Schema</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="schema_markup_enabled">Enable Schema Markup</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_local_business[schema_markup_enabled]" id="schema_markup_enabled" value="1" <?php \checked($this->settings['schema_markup_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Add JSON-LD structured data for local business information.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="auto_update_schema">Auto Update</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_local_business[auto_update_schema]" id="auto_update_schema" value="1" <?php \checked($this->settings['auto_update_schema']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Automatically update schema markup when business information changes.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="schema-preview">
                                <h4>Schema Preview</h4>
                                <div class="schema-code" id="schema-preview-code">
                                    <pre><code><?php echo \esc_html($this->get_schema_preview()); ?></code></pre>
                                </div>
                                <button type="button" class="button button-secondary" id="refresh-schema-preview">
                                    <span class="dashicons dashicons-update"></span>
                                    Refresh Preview
                                </button>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-star-filled"></span> Additional Business Features</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="price_range">Price Range</label>
                                    </th>
                                    <td>
                                        <select name="khm_seo_local_business[price_range]" id="price_range" class="regular-text">
                                            <option value="">Select Price Range</option>
                                            <option value="$" <?php selected($this->settings['price_range'], '$'); ?>>$ (Inexpensive)</option>
                                            <option value="$$" <?php selected($this->settings['price_range'], '$$'); ?>>$$ (Moderate)</option>
                                            <option value="$$$" <?php selected($this->settings['price_range'], '$$$'); ?>>$$$ (Expensive)</option>
                                            <option value="$$$$" <?php selected($this->settings['price_range'], '$$$$'); ?>>$$$$ (Very Expensive)</option>
                                        </select>
                                        <p class="description">General price range for your products or services.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Business Features</th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="khm_seo_local_business[accepts_reservations]" value="1" <?php \checked($this->settings['accepts_reservations']); ?>>
                                                Accepts Reservations
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="khm_seo_local_business[takeout_available]" value="1" <?php \checked($this->settings['takeout_available']); ?>>
                                                Takeout Available
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="khm_seo_local_business[delivery_available]" value="1" <?php \checked($this->settings['delivery_available']); ?>>
                                                Delivery Available
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Reviews Tab -->
                    <div id="reviews" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-star-filled"></span> Review Management</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="review_management_enabled">Enable Review Management</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_local_business[review_management_enabled]" id="review_management_enabled" value="1" <?php \checked($this->settings['review_management_enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Track and display customer reviews from various platforms.</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <div class="reviews-dashboard" id="reviews-dashboard">
                                <!-- Review analytics and management interface -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Google Integration Tab -->
                    <div id="google-integration" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-google"></span> Google My Business Integration</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="gmb_api_enabled">API Integration</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_local_business[google_my_business_api][enabled]" id="gmb_api_enabled" value="1" <?php \checked($this->settings['google_my_business_api']['enabled']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Connect with Google My Business API for automatic data synchronization.</p>
                                    </td>
                                </tr>
                                
                                <tr class="gmb-api-settings" <?php echo !$this->settings['google_my_business_api']['enabled'] ? 'style="display:none;"' : ''; ?>>
                                    <th scope="row">
                                        <label for="gmb_api_key">API Key</label>
                                    </th>
                                    <td>
                                        <input type="password" name="khm_seo_local_business[google_my_business_api][api_key]" id="gmb_api_key" value="<?php echo \esc_attr($this->settings['google_my_business_api']['api_key']); ?>" class="regular-text">
                                        <p class="description">Google My Business API key for data synchronization.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Monitoring Tab -->
                    <div id="monitoring" class="tab-content">
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-chart-area"></span> Local SEO Monitoring</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="nap_monitoring">NAP Consistency Monitoring</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_local_business[nap_monitoring]" id="nap_monitoring" value="1" <?php \checked($this->settings['nap_monitoring']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Monitor Name, Address, Phone consistency across the web.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="citation_tracking">Citation Tracking</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_local_business[citation_tracking]" id="citation_tracking" value="1" <?php \checked($this->settings['citation_tracking']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Track mentions of your business across online directories.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="track_local_rankings">Local Rankings</label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="khm_seo_local_business[track_local_rankings]" id="track_local_rankings" value="1" <?php \checked($this->settings['track_local_rankings']); ?>>
                                            <span class="slider"></span>
                                        </label>
                                        <p class="description">Track local search rankings for target keywords.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="settings-section">
                            <h3><span class="dashicons dashicons-search"></span> Local Keywords</h3>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="local_keywords">Target Keywords</label>
                                    </th>
                                    <td>
                                        <textarea name="khm_seo_local_business[local_keywords]" id="local_keywords" class="large-text" rows="5" placeholder="Enter local keywords to track (one per line)"><?php echo \esc_textarea(implode("\n", $this->settings['local_keywords'])); ?></textarea>
                                        <p class="description">Keywords to monitor for local search performance (e.g., "plumber near me", "best pizza in [city]").</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php \submit_button('Save Local Business Settings', 'primary', 'submit', false, ['id' => 'save-local-business-settings']); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get countries list
     */
    private function get_countries_list() {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IE' => 'Ireland',
            'PT' => 'Portugal',
            'GR' => 'Greece',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'HR' => 'Croatia',
            'BG' => 'Bulgaria',
            'RO' => 'Romania',
            'LT' => 'Lithuania',
            'LV' => 'Latvia',
            'EE' => 'Estonia',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'CN' => 'China',
            'IN' => 'India',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'TH' => 'Thailand',
            'ID' => 'Indonesia',
            'PH' => 'Philippines',
            'VN' => 'Vietnam',
            'NZ' => 'New Zealand',
            'ZA' => 'South Africa',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru',
            'VE' => 'Venezuela',
            'UY' => 'Uruguay'
        ];
    }
    
    /**
     * Get schema preview
     */
    private function get_schema_preview() {
        if (!$this->settings['schema_markup_enabled']) {
            return '// Schema markup is disabled';
        }
        
        $schema = $this->build_local_business_schema();
        return \wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Build local business schema
     */
    private function build_local_business_schema() {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $this->settings['business_type'],
            'name' => $this->settings['primary_business_name'],
            'url' => $this->settings['primary_website'],
            'telephone' => $this->settings['primary_phone'],
            'email' => $this->settings['primary_email']
        ];
        
        // Add address if provided
        if (!empty($this->settings['primary_address']['street_address'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $this->settings['primary_address']['street_address'],
                'addressLocality' => $this->settings['primary_address']['locality'],
                'addressRegion' => $this->settings['primary_address']['region'],
                'postalCode' => $this->settings['primary_address']['postal_code'],
                'addressCountry' => $this->settings['primary_address']['country']
            ];
        }
        
        // Add opening hours
        if (!empty($this->settings['opening_hours'])) {
            $opening_hours = $this->format_opening_hours_schema();
            if (!empty($opening_hours)) {
                $schema['openingHoursSpecification'] = $opening_hours;
            }
        }
        
        // Add social profiles
        $social_urls = array_filter($this->settings['social_profiles']);
        if (!empty($social_urls)) {
            $schema['sameAs'] = array_values($social_urls);
        }
        
        // Add price range
        if (!empty($this->settings['price_range'])) {
            $schema['priceRange'] = $this->settings['price_range'];
        }
        
        return \apply_filters('khm_seo_local_schema', $schema, $this->settings);
    }
    
    /**
     * Format opening hours for schema
     */
    private function format_opening_hours_schema() {
        $opening_hours = [];
        
        foreach ($this->settings['opening_hours'] as $day => $hours) {
            if ($hours['closed']) {
                continue;
            }
            
            $opening_hours[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ucfirst($day),
                'opens' => $hours['open'],
                'closes' => $hours['close']
            ];
        }
        
        return $opening_hours;
    }
    
    /**
     * Output local business schema in head
     */
    public function output_local_business_schema() {
        if (!$this->settings['local_seo_enabled'] || !$this->settings['schema_markup_enabled']) {
            return;
        }
        
        $schema = $this->build_local_business_schema();
        
        echo '<script type="application/ld+json">' . "\n";
        echo \wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n" . '</script>' . "\n";
    }
    
    // Additional methods for location management, GMB integration, etc. would be added here...
}

// Initialize local business SEO
new LocalBusinessSEO();