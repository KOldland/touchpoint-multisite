<?php
/**
 * Schema Validator Class
 * 
 * Comprehensive schema validation and testing system for Google Rich Results
 * and Schema.org compliance testing with debugging utilities.
 * 
 * @package KHM_SEO
 * @subpackage Validation
 * @version 1.0.0
 * @since 1.0.0
 */

namespace KHM_SEO\Validation;

use KHM_SEO\Schema\SchemaManager;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class SchemaValidator {
    
    /**
     * Schema Manager instance
     */
    private $schema_manager;
    
    /**
     * Validation cache
     */
    private $validation_cache;
    
    /**
     * Google Rich Results Test API endpoint
     */
    private const RICH_RESULTS_API = 'https://searchconsole.googleapis.com/v1/urlTestingTools/richResults:test';
    
    /**
     * Schema.org validation endpoint
     */
    private const SCHEMA_ORG_API = 'https://validator.schema.org/validate';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->schema_manager = new SchemaManager();
        $this->validation_cache = [];
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
            add_action('wp_ajax_khm_validate_schema', [$this, 'ajax_validate_schema']);
            add_action('wp_ajax_khm_test_rich_results', [$this, 'ajax_test_rich_results']);
            add_action('wp_ajax_khm_debug_schema', [$this, 'ajax_debug_schema']);
            add_action('wp_ajax_khm_bulk_validate', [$this, 'ajax_bulk_validate']);
        }
        
        // Content hooks
        add_action('save_post', [$this, 'validate_post_schema'], 20);
        add_action('wp_head', [$this, 'add_validation_meta'], 1);
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'khm-seo-admin',
            __('Schema Validation', 'khm-seo'),
            __('Validation', 'khm-seo'),
            'manage_options',
            'khm-seo-validation',
            [$this, 'render_validation_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'khm-seo-validation') === false) {
            return;
        }
        
        wp_enqueue_script(
            'khm-validation-admin',
            KHM_SEO_PLUGIN_URL . 'src/Validation/assets/js/validation-admin.js',
            ['jquery', 'wp-util'],
            KHM_SEO_VERSION,
            true
        );
        
        wp_enqueue_style(
            'khm-validation-admin',
            KHM_SEO_PLUGIN_URL . 'src/Validation/assets/css/validation-admin.css',
            [],
            KHM_SEO_VERSION
        );
        
        wp_localize_script('khm-validation-admin', 'khmValidation', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_validation_nonce'),
            'strings' => [
                'validating' => __('Validating...', 'khm-seo'),
                'testing' => __('Testing Rich Results...', 'khm-seo'),
                'debugging' => __('Debugging Schema...', 'khm-seo'),
                'bulkValidating' => __('Bulk Validating...', 'khm-seo'),
                'success' => __('Validation Complete', 'khm-seo'),
                'error' => __('Validation Error', 'khm-seo'),
                'noSchema' => __('No schema found', 'khm-seo'),
                'invalidSchema' => __('Invalid schema markup', 'khm-seo'),
                'richResultsPass' => __('Rich Results Test Passed', 'khm-seo'),
                'richResultsFail' => __('Rich Results Test Failed', 'khm-seo'),
                'validationScore' => __('Validation Score', 'khm-seo')
            ]
        ]);
    }
    
    /**
     * Render validation page
     */
    public function render_validation_page() {
        include KHM_SEO_PLUGIN_DIR . 'src/Validation/templates/validation-page.php';
    }
    
    /**
     * Validate schema markup
     */
    public function validate_schema($schema_data, $schema_type = null) {
        $validation_result = [
            'valid' => false,
            'score' => 0,
            'errors' => [],
            'warnings' => [],
            'suggestions' => [],
            'rich_results' => []
        ];
        
        try {
            // Basic structure validation
            $structure_validation = $this->validate_structure($schema_data);
            $validation_result = array_merge($validation_result, $structure_validation);
            
            // Schema.org compliance validation
            $compliance_validation = $this->validate_compliance($schema_data, $schema_type);
            $validation_result = array_merge_recursive($validation_result, $compliance_validation);
            
            // Required fields validation
            $required_validation = $this->validate_required_fields($schema_data, $schema_type);
            $validation_result = array_merge_recursive($validation_result, $required_validation);
            
            // Rich Results eligibility
            $rich_results_validation = $this->validate_rich_results_eligibility($schema_data, $schema_type);
            $validation_result = array_merge_recursive($validation_result, $rich_results_validation);
            
            // Calculate overall score
            $validation_result['score'] = $this->calculate_validation_score($validation_result);
            $validation_result['valid'] = $validation_result['score'] >= 80;
            
        } catch (Exception $e) {
            $validation_result['errors'][] = [
                'type' => 'exception',
                'message' => $e->getMessage(),
                'severity' => 'high'
            ];
        }
        
        return $validation_result;
    }
    
    /**
     * Validate schema structure
     */
    private function validate_structure($schema_data) {
        $result = [
            'errors' => [],
            'warnings' => []
        ];
        
        // Check if valid JSON
        if (is_string($schema_data)) {
            $decoded = json_decode($schema_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['errors'][] = [
                    'type' => 'json_syntax',
                    'message' => 'Invalid JSON syntax: ' . json_last_error_msg(),
                    'severity' => 'high'
                ];
                return $result;
            }
            $schema_data = $decoded;
        }
        
        // Check for @context
        if (!isset($schema_data['@context'])) {
            $result['errors'][] = [
                'type' => 'missing_context',
                'message' => 'Missing @context property',
                'severity' => 'high'
            ];
        } elseif ($schema_data['@context'] !== 'https://schema.org') {
            $result['warnings'][] = [
                'type' => 'context_format',
                'message' => 'Recommended @context format: https://schema.org',
                'severity' => 'medium'
            ];
        }
        
        // Check for @type
        if (!isset($schema_data['@type'])) {
            $result['errors'][] = [
                'type' => 'missing_type',
                'message' => 'Missing @type property',
                'severity' => 'high'
            ];
        }
        
        // Check for circular references
        if ($this->has_circular_reference($schema_data)) {
            $result['errors'][] = [
                'type' => 'circular_reference',
                'message' => 'Circular reference detected in schema',
                'severity' => 'high'
            ];
        }
        
        return $result;
    }
    
    /**
     * Validate Schema.org compliance
     */
    private function validate_compliance($schema_data, $schema_type) {
        $result = [
            'errors' => [],
            'warnings' => [],
            'suggestions' => []
        ];
        
        $type = $schema_data['@type'] ?? $schema_type;
        
        if (!$type) {
            return $result;
        }
        
        // Get expected properties for schema type
        $expected_properties = $this->get_expected_properties($type);
        
        // Check required properties
        foreach ($expected_properties['required'] as $property) {
            if (!isset($schema_data[$property])) {
                $result['errors'][] = [
                    'type' => 'missing_required_property',
                    'message' => "Missing required property: {$property}",
                    'property' => $property,
                    'severity' => 'high'
                ];
            }
        }
        
        // Check recommended properties
        foreach ($expected_properties['recommended'] as $property) {
            if (!isset($schema_data[$property])) {
                $result['warnings'][] = [
                    'type' => 'missing_recommended_property',
                    'message' => "Missing recommended property: {$property}",
                    'property' => $property,
                    'severity' => 'medium'
                ];
            }
        }
        
        // Check for deprecated properties
        foreach ($schema_data as $property => $value) {
            if (in_array($property, $expected_properties['deprecated'])) {
                $result['warnings'][] = [
                    'type' => 'deprecated_property',
                    'message' => "Deprecated property: {$property}",
                    'property' => $property,
                    'severity' => 'medium'
                ];
            }
        }
        
        // Validate property values
        foreach ($schema_data as $property => $value) {
            if ($property[0] === '@') continue;
            
            $property_validation = $this->validate_property_value($property, $value, $type);
            $result = array_merge_recursive($result, $property_validation);
        }
        
        return $result;
    }
    
    /**
     * Validate required fields for schema type
     */
    private function validate_required_fields($schema_data, $schema_type) {
        $result = [
            'errors' => [],
            'warnings' => []
        ];
        
        $type = $schema_data['@type'] ?? $schema_type;
        
        switch ($type) {
            case 'Article':
                $this->validate_article_fields($schema_data, $result);
                break;
                
            case 'Organization':
                $this->validate_organization_fields($schema_data, $result);
                break;
                
            case 'Product':
                $this->validate_product_fields($schema_data, $result);
                break;
                
            case 'Person':
                $this->validate_person_fields($schema_data, $result);
                break;
                
            case 'BreadcrumbList':
                $this->validate_breadcrumb_fields($schema_data, $result);
                break;
        }
        
        return $result;
    }
    
    /**
     * Validate Rich Results eligibility
     */
    private function validate_rich_results_eligibility($schema_data, $schema_type) {
        $result = [
            'rich_results' => [],
            'warnings' => []
        ];
        
        $type = $schema_data['@type'] ?? $schema_type;
        
        switch ($type) {
            case 'Article':
                $result['rich_results'] = $this->check_article_rich_results($schema_data);
                break;
                
            case 'Product':
                $result['rich_results'] = $this->check_product_rich_results($schema_data);
                break;
                
            case 'Organization':
                $result['rich_results'] = $this->check_organization_rich_results($schema_data);
                break;
                
            case 'BreadcrumbList':
                $result['rich_results'] = $this->check_breadcrumb_rich_results($schema_data);
                break;
        }
        
        return $result;
    }
    
    /**
     * Test with Google Rich Results Test API
     */
    public function test_rich_results($url) {
        $cache_key = 'rich_results_test_' . md5($url);
        
        // Check cache first
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $result = [
            'success' => false,
            'rich_results' => [],
            'errors' => [],
            'warnings' => []
        ];
        
        try {
            // Since Google's Rich Results Test API requires authentication,
            // we'll implement a local testing approach with structured data testing
            $result = $this->test_rich_results_local($url);
            
            // Cache result for 1 hour
            set_transient($cache_key, $result, HOUR_IN_SECONDS);
            
        } catch (Exception $e) {
            $result['errors'][] = [
                'type' => 'api_error',
                'message' => 'Rich Results Test API error: ' . $e->getMessage(),
                'severity' => 'high'
            ];
        }
        
        return $result;
    }
    
    /**
     * Local Rich Results testing
     */
    private function test_rich_results_local($url) {
        $result = [
            'success' => false,
            'rich_results' => [],
            'errors' => [],
            'warnings' => []
        ];
        
        // Fetch page content
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            $error_message = is_callable([$response, 'get_error_message']) ? 
                $response->get_error_message() : 'Unknown fetch error';
            
            $result['errors'][] = [
                'type' => 'fetch_error',
                'message' => 'Could not fetch URL: ' . $error_message,
                'severity' => 'high'
            ];
            return $result;
        }
        
        $content = wp_remote_retrieve_body($response);
        
        // Extract JSON-LD
        $json_ld_schemas = $this->extract_json_ld($content);
        
        // Test each schema for Rich Results eligibility
        foreach ($json_ld_schemas as $schema) {
            $schema_validation = $this->validate_schema($schema);
            
            if (!empty($schema_validation['rich_results'])) {
                $result['rich_results'] = array_merge($result['rich_results'], $schema_validation['rich_results']);
                $result['success'] = true;
            }
            
            $result['errors'] = array_merge($result['errors'], $schema_validation['errors']);
            $result['warnings'] = array_merge($result['warnings'], $schema_validation['warnings']);
        }
        
        return $result;
    }
    
    /**
     * Extract JSON-LD from HTML content
     */
    private function extract_json_ld($html) {
        $schemas = [];
        
        // Match JSON-LD script tags
        preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);
        
        foreach ($matches[1] as $json) {
            $decoded = json_decode(trim($json), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($decoded['@graph'])) {
                    $schemas = array_merge($schemas, $decoded['@graph']);
                } else {
                    $schemas[] = $decoded;
                }
            }
        }
        
        return $schemas;
    }
    
    /**
     * Debug schema markup
     */
    public function debug_schema($post_id = null) {
        $debug_info = [
            'timestamp' => current_time('mysql'),
            'post_id' => $post_id,
            'schemas' => [],
            'hooks' => [],
            'settings' => [],
            'errors' => []
        ];
        
        try {
            if ($post_id) {
                // Get post meta data for schemas
                $meta_data = get_post_meta($post_id, '_khm_seo_schema', true);
                if ($meta_data) {
                    $debug_info['schemas']['post_meta'] = [
                        'data' => $meta_data,
                        'validation' => $this->validate_schema($meta_data),
                        'source' => 'post_meta'
                    ];
                }
            }
            
            // Get global schema settings
            $organization_data = get_option('khm_seo_organization_schema', []);
            if (!empty($organization_data)) {
                $debug_info['schemas']['organization'] = [
                    'data' => $organization_data,
                    'validation' => $this->validate_schema($organization_data, 'Organization'),
                    'source' => 'global_settings'
                ];
            }
            
            // Get hook information
            $debug_info['hooks'] = $this->get_hook_debug_info();
            
            // Get plugin settings
            $debug_info['settings'] = $this->get_settings_debug_info();
            
        } catch (Exception $e) {
            $debug_info['errors'][] = [
                'type' => 'debug_error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
        
        return $debug_info;
    }
    
    /**
     * Bulk validate multiple posts/pages
     */
    public function bulk_validate($post_ids) {
        $results = [];
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }
            
            $post_result = [
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'schemas' => [],
                'overall_score' => 0,
                'has_errors' => false
            ];
            
            // Get post schema meta data
            $meta_data = get_post_meta($post_id, '_khm_seo_schema', true);
            if ($meta_data) {
                $validation = $this->validate_schema($meta_data);
                $post_result['schemas']['post_schema'] = $validation;
                
                if (!empty($validation['errors'])) {
                    $post_result['has_errors'] = true;
                }
            }
            
            // Calculate overall score
            $total_score = 0;
            $schema_count = count($post_result['schemas']);
            
            if ($schema_count > 0) {
                foreach ($post_result['schemas'] as $schema_validation) {
                    $total_score += $schema_validation['score'];
                }
                $post_result['overall_score'] = $total_score / $schema_count;
            }
            
            $results[] = $post_result;
        }
        
        return $results;
    }
    
    /**
     * Calculate validation score
     */
    private function calculate_validation_score($validation_result) {
        $score = 100;
        
        // Deduct points for errors
        foreach ($validation_result['errors'] as $error) {
            switch ($error['severity']) {
                case 'high':
                    $score -= 25;
                    break;
                case 'medium':
                    $score -= 15;
                    break;
                case 'low':
                    $score -= 5;
                    break;
            }
        }
        
        // Deduct points for warnings
        foreach ($validation_result['warnings'] as $warning) {
            switch ($warning['severity']) {
                case 'high':
                    $score -= 15;
                    break;
                case 'medium':
                    $score -= 10;
                    break;
                case 'low':
                    $score -= 3;
                    break;
            }
        }
        
        // Add points for Rich Results eligibility
        if (!empty($validation_result['rich_results'])) {
            $score += 10;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Get expected properties for schema type
     */
    private function get_expected_properties($type) {
        $properties = [
            'required' => [],
            'recommended' => [],
            'deprecated' => []
        ];
        
        switch ($type) {
            case 'Article':
                $properties['required'] = ['headline', 'author', 'datePublished'];
                $properties['recommended'] = ['dateModified', 'description', 'image', 'publisher', 'mainEntityOfPage'];
                break;
                
            case 'Organization':
                $properties['required'] = ['name'];
                $properties['recommended'] = ['url', 'logo', 'description', 'contactPoint', 'address'];
                break;
                
            case 'Product':
                $properties['required'] = ['name', 'description'];
                $properties['recommended'] = ['image', 'offers', 'brand', 'aggregateRating', 'review'];
                break;
                
            case 'Person':
                $properties['required'] = ['name'];
                $properties['recommended'] = ['description', 'image', 'sameAs', 'jobTitle'];
                break;
                
            case 'BreadcrumbList':
                $properties['required'] = ['itemListElement'];
                $properties['recommended'] = [];
                break;
        }
        
        return $properties;
    }
    
    // AJAX handlers
    public function ajax_validate_schema() {
        check_ajax_referer('khm_validation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'khm-seo'));
        }
        
        $schema_data = wp_unslash($_POST['schema_data'] ?? '');
        $schema_type = sanitize_text_field($_POST['schema_type'] ?? '');
        
        if (empty($schema_data)) {
            wp_send_json_error(__('No schema data provided', 'khm-seo'));
        }
        
        $validation_result = $this->validate_schema($schema_data, $schema_type);
        
        wp_send_json_success($validation_result);
    }
    
    public function ajax_test_rich_results() {
        check_ajax_referer('khm_validation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'khm-seo'));
        }
        
        $url = sanitize_text_field($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error(__('No URL provided', 'khm-seo'));
        }
        
        $test_result = $this->test_rich_results($url);
        
        wp_send_json_success($test_result);
    }
    
    public function ajax_debug_schema() {
        check_ajax_referer('khm_validation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'khm-seo'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        $debug_result = $this->debug_schema($post_id ?: null);
        
        wp_send_json_success($debug_result);
    }
    
    public function ajax_bulk_validate() {
        check_ajax_referer('khm_validation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized access', 'khm-seo'));
        }
        
        $post_ids = array_map('intval', $_POST['post_ids'] ?? []);
        
        if (empty($post_ids)) {
            wp_send_json_error(__('No posts provided', 'khm-seo'));
        }
        
        $bulk_result = $this->bulk_validate($post_ids);
        
        wp_send_json_success($bulk_result);
    }
    
    // Validation hooks
    public function validate_post_schema($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Get post schema meta data
        $meta_data = get_post_meta($post_id, '_khm_seo_schema', true);
        
        if ($meta_data) {
            $validation = $this->validate_schema($meta_data);
            
            // Store validation results
            update_post_meta($post_id, '_khm_schema_validation', $validation);
        }
    }
    
    public function add_validation_meta() {
        if (!is_singular()) {
            return;
        }
        
        global $post;
        if (!$post) {
            return;
        }
        
        $post_id = $post->ID;
        $validation = get_post_meta($post_id, '_khm_schema_validation', true);
        
        if ($validation && isset($validation['score'])) {
            echo "<!-- KHM SEO Schema Validation Score: " . esc_html($validation['score']) . " -->\n";
        }
    }
    
    // Helper methods for specific schema validation
    private function validate_article_fields($schema_data, &$result) {
        // Validate article-specific requirements
        if (empty($schema_data['headline'])) {
            $result['errors'][] = [
                'type' => 'missing_headline',
                'message' => 'Article schema requires a headline',
                'severity' => 'high'
            ];
        }
        
        if (empty($schema_data['author'])) {
            $result['errors'][] = [
                'type' => 'missing_author',
                'message' => 'Article schema requires an author',
                'severity' => 'high'
            ];
        }
        
        if (empty($schema_data['datePublished'])) {
            $result['errors'][] = [
                'type' => 'missing_date_published',
                'message' => 'Article schema requires a published date',
                'severity' => 'high'
            ];
        }
    }
    
    private function validate_organization_fields($schema_data, &$result) {
        if (empty($schema_data['name'])) {
            $result['errors'][] = [
                'type' => 'missing_name',
                'message' => 'Organization schema requires a name',
                'severity' => 'high'
            ];
        }
    }
    
    private function validate_product_fields($schema_data, &$result) {
        if (empty($schema_data['name'])) {
            $result['errors'][] = [
                'type' => 'missing_name',
                'message' => 'Product schema requires a name',
                'severity' => 'high'
            ];
        }
        
        if (empty($schema_data['description'])) {
            $result['errors'][] = [
                'type' => 'missing_description',
                'message' => 'Product schema requires a description',
                'severity' => 'high'
            ];
        }
    }
    
    private function validate_person_fields($schema_data, &$result) {
        if (empty($schema_data['name'])) {
            $result['errors'][] = [
                'type' => 'missing_name',
                'message' => 'Person schema requires a name',
                'severity' => 'high'
            ];
        }
    }
    
    private function validate_breadcrumb_fields($schema_data, &$result) {
        if (empty($schema_data['itemListElement'])) {
            $result['errors'][] = [
                'type' => 'missing_items',
                'message' => 'BreadcrumbList schema requires itemListElement',
                'severity' => 'high'
            ];
        }
    }
    
    private function check_article_rich_results($schema_data) {
        $rich_results = [];
        
        if (isset($schema_data['headline'], $schema_data['author'], $schema_data['datePublished'])) {
            $rich_results[] = [
                'type' => 'article',
                'eligible' => true,
                'description' => 'Eligible for Article rich results'
            ];
        }
        
        return $rich_results;
    }
    
    private function check_product_rich_results($schema_data) {
        $rich_results = [];
        
        if (isset($schema_data['name'], $schema_data['description'])) {
            $rich_results[] = [
                'type' => 'product',
                'eligible' => true,
                'description' => 'Eligible for Product rich results'
            ];
        }
        
        return $rich_results;
    }
    
    private function check_organization_rich_results($schema_data) {
        $rich_results = [];
        
        if (isset($schema_data['name'], $schema_data['url'])) {
            $rich_results[] = [
                'type' => 'organization',
                'eligible' => true,
                'description' => 'Eligible for Organization knowledge panel'
            ];
        }
        
        return $rich_results;
    }
    
    private function check_breadcrumb_rich_results($schema_data) {
        $rich_results = [];
        
        if (isset($schema_data['itemListElement']) && is_array($schema_data['itemListElement'])) {
            $rich_results[] = [
                'type' => 'breadcrumb',
                'eligible' => true,
                'description' => 'Eligible for Breadcrumb rich results'
            ];
        }
        
        return $rich_results;
    }
    
    private function validate_property_value($property, $value, $type) {
        $result = [
            'errors' => [],
            'warnings' => []
        ];
        
        // Basic validation rules
        if (is_string($value) && empty(trim($value))) {
            $result['warnings'][] = [
                'type' => 'empty_property',
                'message' => "Empty value for property: {$property}",
                'property' => $property,
                'severity' => 'low'
            ];
        }
        
        // URL validation
        if (in_array($property, ['url', 'sameAs', 'image']) && is_string($value)) {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                $result['errors'][] = [
                    'type' => 'invalid_url',
                    'message' => "Invalid URL for property: {$property}",
                    'property' => $property,
                    'severity' => 'medium'
                ];
            }
        }
        
        // Date validation
        if (in_array($property, ['datePublished', 'dateModified']) && is_string($value)) {
            if (!strtotime($value)) {
                $result['errors'][] = [
                    'type' => 'invalid_date',
                    'message' => "Invalid date format for property: {$property}",
                    'property' => $property,
                    'severity' => 'medium'
                ];
            }
        }
        
        return $result;
    }
    
    private function has_circular_reference($data, $visited = []) {
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $current_path = $visited;
                $current_path[] = $key;
                
                if (in_array($key, $visited)) {
                    return true;
                }
                
                if ($this->has_circular_reference($value, $current_path)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function get_hook_debug_info() {
        global $wp_filter;
        
        $hooks = [];
        $schema_hooks = ['wp_head', 'wp_footer', 'init', 'wp_loaded'];
        
        foreach ($schema_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                $hooks[$hook] = count($wp_filter[$hook]->callbacks);
            }
        }
        
        return $hooks;
    }
    
    private function get_settings_debug_info() {
        return [
            'schema_enabled' => get_option('khm_seo_schema_enabled', true),
            'validation_enabled' => get_option('khm_seo_validation_enabled', true),
            'cache_enabled' => get_option('khm_seo_cache_enabled', true),
            'debug_mode' => get_option('khm_seo_debug_mode', false),
        ];
    }
}