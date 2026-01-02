<?php

namespace KHM_SEO\Schema;

use Exception;

/**
 * Schema Validation System
 * 
 * Comprehensive structured data analysis, validation, and optimization system
 * for enhanced search engine visibility and rich snippet generation.
 * 
 * Features:
 * - JSON-LD parsing and validation
 * - Schema.org compliance checking
 * - Google Rich Snippets validation
 * - Structured data testing integration
 * - Error detection and reporting
 * - Optimization recommendations
 * - Performance impact analysis
 * - Automated schema generation
 * - Search Console integration
 * - Rich snippet preview
 * 
 * @package KHM_SEO\Schema
 * @since 1.0.0
 */
class SchemaValidator {

    /**
     * Schema validation configuration
     */
    private $config = [
        'google_testing_tool_api' => 'https://search.google.com/structured-data/testing-tool/validate',
        'schema_org_context' => 'https://schema.org',
        'supported_formats' => ['json-ld', 'microdata', 'rdfa'],
        'validation_timeout' => 30,
        'max_schema_size' => 102400, // 100KB
        'cache_duration' => 3600, // 1 hour
        'batch_size' => 10
    ];

    /**
     * Supported schema types with validation rules
     */
    private $schema_types = [
        'Article' => [
            'required' => ['headline', 'author', 'datePublished'],
            'recommended' => ['image', 'dateModified', 'publisher'],
            'validation' => 'validateArticleSchema'
        ],
        'Product' => [
            'required' => ['name', 'offers'],
            'recommended' => ['description', 'image', 'brand', 'aggregateRating'],
            'validation' => 'validateProductSchema'
        ],
        'Organization' => [
            'required' => ['name'],
            'recommended' => ['url', 'logo', 'contactPoint', 'address'],
            'validation' => 'validateOrganizationSchema'
        ],
        'Person' => [
            'required' => ['name'],
            'recommended' => ['image', 'jobTitle', 'worksFor'],
            'validation' => 'validatePersonSchema'
        ],
        'LocalBusiness' => [
            'required' => ['name', 'address'],
            'recommended' => ['telephone', 'openingHours', 'priceRange'],
            'validation' => 'validateLocalBusinessSchema'
        ],
        'WebSite' => [
            'required' => ['name', 'url'],
            'recommended' => ['potentialAction', 'author'],
            'validation' => 'validateWebSiteSchema'
        ],
        'WebPage' => [
            'required' => ['name'],
            'recommended' => ['description', 'mainEntity'],
            'validation' => 'validateWebPageSchema'
        ],
        'BreadcrumbList' => [
            'required' => ['itemListElement'],
            'recommended' => [],
            'validation' => 'validateBreadcrumbSchema'
        ],
        'Recipe' => [
            'required' => ['name', 'recipeIngredient', 'recipeInstructions'],
            'recommended' => ['image', 'cookTime', 'prepTime', 'nutrition'],
            'validation' => 'validateRecipeSchema'
        ],
        'Event' => [
            'required' => ['name', 'startDate'],
            'recommended' => ['location', 'description', 'endDate'],
            'validation' => 'validateEventSchema'
        ],
        'FAQ' => [
            'required' => ['mainEntity'],
            'recommended' => [],
            'validation' => 'validateFAQSchema'
        ],
        'HowTo' => [
            'required' => ['name', 'step'],
            'recommended' => ['description', 'image', 'totalTime'],
            'validation' => 'validateHowToSchema'
        ]
    ];

    /**
     * Validation results cache
     */
    private $validation_cache = [];

    /**
     * Initialize Schema Validator
     */
    public function __construct() {
        add_action('init', [$this, 'init_hooks']);
        add_action('wp_loaded', [$this, 'schedule_validation_tasks']);
        $this->init_validation_engine();
    }

    /**
     * Initialize WordPress hooks
     */
    public function init_hooks() {
        // AJAX handlers for schema validation
        add_action('wp_ajax_schema_validate_url', [$this, 'ajax_validate_url']);
        add_action('wp_ajax_schema_validate_markup', [$this, 'ajax_validate_markup']);
        add_action('wp_ajax_schema_get_recommendations', [$this, 'ajax_get_recommendations']);
        add_action('wp_ajax_schema_generate_markup', [$this, 'ajax_generate_markup']);
        add_action('wp_ajax_schema_test_rich_snippets', [$this, 'ajax_test_rich_snippets']);

        // Background processing hooks
        add_action('schema_validation_scan', [$this, 'run_site_schema_scan']);
        add_action('schema_validation_cleanup', [$this, 'cleanup_validation_cache']);
        add_action('schema_validation_analysis', [$this, 'analyze_schema_performance']);

        // Content hooks for automatic validation
        add_action('save_post', [$this, 'validate_post_schema'], 10, 1);
        add_action('wp_head', [$this, 'inject_schema_validation_script'], 1);
    }

    /**
     * Initialize the validation engine
     */
    private function init_validation_engine() {
        // Load schema.org vocabulary cache
        $this->load_schema_vocabulary();
        
        // Initialize validation cache
        $this->init_validation_cache();
        
        // Set up error reporting
        $this->init_error_reporting();
    }

    /**
     * Schedule automated validation tasks
     */
    public function schedule_validation_tasks() {
        // Daily full site schema scan
        if (!wp_next_scheduled('schema_validation_scan')) {
            wp_schedule_event(time(), 'daily', 'schema_validation_scan');
        }

        // Weekly cache cleanup
        if (!wp_next_scheduled('schema_validation_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'schema_validation_cleanup');
        }

        // Monthly schema performance analysis
        if (!wp_next_scheduled('schema_validation_analysis')) {
            wp_schedule_event(time(), 'monthly', 'schema_validation_analysis');
        }
    }

    /**
     * Validate schema markup for a specific URL
     */
    public function validate_url($url) {
        try {
            // Check cache first
            $cache_key = 'schema_validation_' . md5($url);
            $cached_result = $this->get_cached_validation($cache_key);
            if ($cached_result !== false) {
                return $cached_result;
            }

            // Fetch page content
            $page_content = $this->fetch_page_content($url);
            if (!$page_content) {
                throw new Exception("Unable to fetch page content from URL: {$url}");
            }

            // Extract schema markup
            $schemas = $this->extract_schema_markup($page_content);
            
            // Validate each schema
            $validation_results = [];
            foreach ($schemas as $schema) {
                $result = $this->validate_single_schema($schema);
                $validation_results[] = $result;
            }

            // Compile comprehensive result
            $final_result = [
                'url' => $url,
                'schemas_found' => count($schemas),
                'validation_results' => $validation_results,
                'overall_score' => $this->calculate_overall_schema_score($validation_results),
                'recommendations' => $this->generate_schema_recommendations($validation_results),
                'rich_snippet_opportunities' => $this->identify_rich_snippet_opportunities($validation_results),
                'errors' => $this->extract_validation_errors($validation_results),
                'warnings' => $this->extract_validation_warnings($validation_results),
                'validated_at' => current_time('mysql')
            ];

            // Cache result
            $this->cache_validation_result($cache_key, $final_result);

            // Store in database
            $this->store_validation_result($final_result);

            return $final_result;

        } catch (Exception $e) {
            error_log('Schema Validation Error for URL ' . $url . ': ' . $e->getMessage());
            return $this->get_error_result($url, $e->getMessage());
        }
    }

    /**
     * Validate raw schema markup
     */
    public function validate_markup($markup, $format = 'json-ld') {
        try {
            if (!in_array($format, $this->config['supported_formats'])) {
                throw new Exception("Unsupported schema format: {$format}");
            }

            // Parse markup based on format
            $parsed_schema = $this->parse_schema_markup($markup, $format);
            
            // Validate parsed schema
            $validation_result = $this->validate_single_schema($parsed_schema);
            
            // Add format-specific validation
            $format_validation = $this->validate_format_specific($markup, $format);
            
            return [
                'markup' => $markup,
                'format' => $format,
                'parsed_successfully' => $parsed_schema !== false,
                'validation_result' => $validation_result,
                'format_validation' => $format_validation,
                'recommendations' => $this->generate_markup_recommendations($validation_result),
                'optimizations' => $this->suggest_markup_optimizations($parsed_schema),
                'validated_at' => current_time('mysql')
            ];

        } catch (Exception $e) {
            error_log('Schema Markup Validation Error: ' . $e->getMessage());
            return $this->get_markup_error_result($markup, $format, $e->getMessage());
        }
    }

    /**
     * Extract schema markup from HTML content
     */
    private function extract_schema_markup($html_content) {
        $schemas = [];

        // Extract JSON-LD schemas
        $json_ld_schemas = $this->extract_json_ld_schemas($html_content);
        $schemas = array_merge($schemas, $json_ld_schemas);

        // Extract Microdata schemas
        $microdata_schemas = $this->extract_microdata_schemas($html_content);
        $schemas = array_merge($schemas, $microdata_schemas);

        // Extract RDFa schemas
        $rdfa_schemas = $this->extract_rdfa_schemas($html_content);
        $schemas = array_merge($schemas, $rdfa_schemas);

        return $schemas;
    }

    /**
     * Extract JSON-LD schemas from HTML
     */
    private function extract_json_ld_schemas($html_content) {
        $schemas = [];
        
        // Use DOMDocument to parse HTML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html_content);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $script_tags = $xpath->query('//script[@type="application/ld+json"]');

        foreach ($script_tags as $script_tag) {
            $json_content = trim($script_tag->textContent);
            
            if (!empty($json_content)) {
                $decoded = json_decode($json_content, true);
                
                if ($decoded !== null) {
                    $schemas[] = [
                        'type' => 'json-ld',
                        'raw' => $json_content,
                        'parsed' => $decoded,
                        'location' => $this->get_element_location($script_tag)
                    ];
                } else {
                    $schemas[] = [
                        'type' => 'json-ld',
                        'raw' => $json_content,
                        'parsed' => false,
                        'parse_error' => json_last_error_msg(),
                        'location' => $this->get_element_location($script_tag)
                    ];
                }
            }
        }

        return $schemas;
    }

    /**
     * Extract Microdata schemas from HTML
     */
    private function extract_microdata_schemas($html_content) {
        $schemas = [];
        
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html_content);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        
        // Find elements with itemscope and itemtype
        $microdata_elements = $xpath->query('//*[@itemscope and @itemtype]');
        
        foreach ($microdata_elements as $element) {
            $schema_data = $this->parse_microdata_element($element, $xpath);
            
            if (!empty($schema_data)) {
                $schemas[] = [
                    'type' => 'microdata',
                    'raw' => $dom->saveHTML($element),
                    'parsed' => $schema_data,
                    'location' => $this->get_element_location($element)
                ];
            }
        }

        return $schemas;
    }

    /**
     * Extract RDFa schemas from HTML
     */
    private function extract_rdfa_schemas($html_content) {
        $schemas = [];
        
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html_content);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        
        // Find elements with typeof attribute
        $rdfa_elements = $xpath->query('//*[@typeof]');
        
        foreach ($rdfa_elements as $element) {
            $schema_data = $this->parse_rdfa_element($element, $xpath);
            
            if (!empty($schema_data)) {
                $schemas[] = [
                    'type' => 'rdfa',
                    'raw' => $dom->saveHTML($element),
                    'parsed' => $schema_data,
                    'location' => $this->get_element_location($element)
                ];
            }
        }

        return $schemas;
    }

    /**
     * Validate a single schema object
     */
    private function validate_single_schema($schema) {
        if (!$schema || !isset($schema['parsed'])) {
            return $this->get_invalid_schema_result('Schema parsing failed');
        }

        $parsed_data = $schema['parsed'];
        
        // Handle both single schemas and arrays of schemas
        if (!isset($parsed_data['@type'])) {
            // Check if it's an array of schemas
            if (is_array($parsed_data) && isset($parsed_data[0]['@type'])) {
                $results = [];
                foreach ($parsed_data as $single_schema) {
                    $results[] = $this->validate_schema_object($single_schema);
                }
                return [
                    'type' => 'multiple',
                    'count' => count($results),
                    'results' => $results,
                    'overall_valid' => !in_array(false, array_column($results, 'valid'))
                ];
            }
            return $this->get_invalid_schema_result('No @type found in schema');
        }

        return $this->validate_schema_object($parsed_data);
    }

    /**
     * Validate a schema object against schema.org rules
     */
    private function validate_schema_object($schema_object) {
        $schema_type = $schema_object['@type'] ?? 'Unknown';
        
        $validation_result = [
            'schema_type' => $schema_type,
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'recommendations' => [],
            'completeness_score' => 0,
            'rich_snippet_eligible' => false
        ];

        // Check if schema type is supported
        if (!isset($this->schema_types[$schema_type])) {
            $validation_result['warnings'][] = "Schema type '{$schema_type}' is not in our validation database";
            return $validation_result;
        }

        $type_config = $this->schema_types[$schema_type];

        // Validate required properties
        foreach ($type_config['required'] as $required_prop) {
            if (!isset($schema_object[$required_prop])) {
                $validation_result['valid'] = false;
                $validation_result['errors'][] = "Required property '{$required_prop}' is missing";
            }
        }

        // Check recommended properties
        $present_recommended = 0;
        foreach ($type_config['recommended'] as $recommended_prop) {
            if (isset($schema_object[$recommended_prop])) {
                $present_recommended++;
            } else {
                $validation_result['recommendations'][] = "Consider adding recommended property '{$recommended_prop}'";
            }
        }

        // Calculate completeness score
        $total_properties = count($type_config['required']) + count($type_config['recommended']);
        $present_properties = count($type_config['required']) + $present_recommended;
        $validation_result['completeness_score'] = $total_properties > 0 ? 
            round(($present_properties / $total_properties) * 100) : 0;

        // Perform schema-specific validation
        if (isset($type_config['validation']) && method_exists($this, $type_config['validation'])) {
            $specific_validation = $this->{$type_config['validation']}($schema_object);
            $validation_result = array_merge_recursive($validation_result, $specific_validation);
        }

        // Check rich snippet eligibility
        $validation_result['rich_snippet_eligible'] = $this->check_rich_snippet_eligibility($schema_object, $schema_type);

        // Validate property values
        $this->validate_property_values($schema_object, $validation_result);

        return $validation_result;
    }

    /**
     * Schema-specific validation methods
     */
    private function validateArticleSchema($schema) {
        $validation = ['errors' => [], 'warnings' => [], 'recommendations' => []];

        // Validate author
        if (isset($schema['author'])) {
            if (!is_array($schema['author']) && !isset($schema['author']['@type'])) {
                $validation['warnings'][] = 'Author should be structured with @type Person or Organization';
            }
        }

        // Validate image
        if (isset($schema['image'])) {
            if (!$this->validate_image_structure($schema['image'])) {
                $validation['warnings'][] = 'Image should include url, width, and height properties';
            }
        }

        // Validate publisher
        if (isset($schema['publisher'])) {
            if (!isset($schema['publisher']['@type']) || !isset($schema['publisher']['name'])) {
                $validation['errors'][] = 'Publisher must have @type and name properties';
            }
            if (!isset($schema['publisher']['logo'])) {
                $validation['recommendations'][] = 'Publisher should include a logo for rich snippets';
            }
        }

        // Validate dates
        if (isset($schema['datePublished']) && !$this->validate_iso_date($schema['datePublished'])) {
            $validation['errors'][] = 'datePublished must be in ISO 8601 format';
        }

        if (isset($schema['dateModified']) && !$this->validate_iso_date($schema['dateModified'])) {
            $validation['errors'][] = 'dateModified must be in ISO 8601 format';
        }

        return $validation;
    }

    private function validateProductSchema($schema) {
        $validation = ['errors' => [], 'warnings' => [], 'recommendations' => []];

        // Validate offers
        if (isset($schema['offers'])) {
            if (!$this->validate_offers_structure($schema['offers'])) {
                $validation['errors'][] = 'Offers must include price and availability';
            }
        }

        // Validate aggregateRating
        if (isset($schema['aggregateRating'])) {
            if (!$this->validate_rating_structure($schema['aggregateRating'])) {
                $validation['warnings'][] = 'AggregateRating should include ratingValue, bestRating, and reviewCount';
            }
        }

        // Check for reviews
        if (!isset($schema['review']) && !isset($schema['aggregateRating'])) {
            $validation['recommendations'][] = 'Consider adding reviews or aggregate rating for better rich snippets';
        }

        return $validation;
    }

    private function validateOrganizationSchema($schema) {
        $validation = ['errors' => [], 'warnings' => [], 'recommendations' => []];

        // Validate logo
        if (isset($schema['logo'])) {
            if (!$this->validate_image_structure($schema['logo'])) {
                $validation['warnings'][] = 'Logo should be a high-quality image with proper dimensions';
            }
        }

        // Validate address
        if (isset($schema['address'])) {
            if (!$this->validate_address_structure($schema['address'])) {
                $validation['warnings'][] = 'Address should include streetAddress, addressLocality, and addressCountry';
            }
        }

        // Validate contact point
        if (isset($schema['contactPoint'])) {
            if (!$this->validate_contact_point($schema['contactPoint'])) {
                $validation['warnings'][] = 'ContactPoint should include telephone and contactType';
            }
        }

        return $validation;
    }

    private function validateLocalBusinessSchema($schema) {
        $validation = ['errors' => [], 'warnings' => [], 'recommendations' => []];

        // Validate address (required for local business)
        if (!isset($schema['address'])) {
            $validation['errors'][] = 'Local business must have an address';
        } elseif (!$this->validate_address_structure($schema['address'])) {
            $validation['errors'][] = 'Address must include streetAddress, addressLocality, and addressCountry';
        }

        // Validate opening hours
        if (isset($schema['openingHours']) && !$this->validate_opening_hours($schema['openingHours'])) {
            $validation['warnings'][] = 'Opening hours format may not be recognized by search engines';
        }

        // Check for geo coordinates
        if (!isset($schema['geo'])) {
            $validation['recommendations'][] = 'Add geo coordinates for better local search visibility';
        }

        return $validation;
    }

    /**
     * Validation helper methods
     */
    private function validate_image_structure($image) {
        if (is_string($image)) return true; // Simple URL is acceptable
        
        if (is_array($image)) {
            return isset($image['url']) || isset($image['@id']);
        }
        
        return false;
    }

    private function validate_iso_date($date) {
        return preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(\.\d{3})?([+-]\d{2}:\d{2}|Z))?$/', $date);
    }

    private function validate_offers_structure($offers) {
        if (!is_array($offers)) return false;
        
        // Handle single offer vs array of offers
        $offers_array = isset($offers[0]) ? $offers : [$offers];
        
        foreach ($offers_array as $offer) {
            if (!isset($offer['price']) || !isset($offer['availability'])) {
                return false;
            }
        }
        
        return true;
    }

    private function validate_rating_structure($rating) {
        return isset($rating['ratingValue']) && 
               isset($rating['bestRating']) && 
               isset($rating['reviewCount']);
    }

    private function validate_address_structure($address) {
        return is_array($address) && 
               isset($address['addressLocality']) && 
               isset($address['addressCountry']);
    }

    private function validate_contact_point($contactPoint) {
        return isset($contactPoint['telephone']) && 
               isset($contactPoint['contactType']);
    }

    private function validate_opening_hours($hours) {
        // Basic validation for opening hours format
        if (is_array($hours)) {
            foreach ($hours as $hour) {
                if (!preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)/', $hour)) {
                    return false;
                }
            }
            return true;
        }
        
        return preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)/', $hours);
    }

    /**
     * Property value validation
     */
    private function validate_property_values($schema_object, &$validation_result) {
        // Validate URLs
        foreach ($schema_object as $property => $value) {
            if ($this->is_url_property($property) && !$this->validate_url_format($value)) {
                $validation_result['warnings'][] = "Property '{$property}' should be a valid URL";
            }
            
            if ($this->is_email_property($property) && !$this->validate_email_format($value)) {
                $validation_result['warnings'][] = "Property '{$property}' should be a valid email address";
            }
        }
    }

    private function is_url_property($property) {
        $url_properties = ['url', 'sameAs', 'logo', 'image', 'mainEntityOfPage'];
        return in_array($property, $url_properties);
    }

    private function is_email_property($property) {
        $email_properties = ['email'];
        return in_array($property, $email_properties);
    }

    private function validate_url_format($value) {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }
        
        if (is_array($value) && isset($value['@id'])) {
            return filter_var($value['@id'], FILTER_VALIDATE_URL) !== false;
        }
        
        return true; // Allow complex structures
    }

    private function validate_email_format($value) {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }
        
        return true;
    }

    /**
     * Rich snippet eligibility check
     */
    private function check_rich_snippet_eligibility($schema_object, $schema_type) {
        $rich_snippet_types = [
            'Article', 'Recipe', 'Product', 'Event', 'Organization',
            'LocalBusiness', 'FAQ', 'HowTo', 'BreadcrumbList'
        ];

        if (!in_array($schema_type, $rich_snippet_types)) {
            return false;
        }

        // Type-specific eligibility checks
        switch ($schema_type) {
            case 'Article':
                return isset($schema_object['headline']) && 
                       isset($schema_object['author']) && 
                       isset($schema_object['datePublished']);
                       
            case 'Product':
                return isset($schema_object['name']) && 
                       isset($schema_object['offers']);
                       
            case 'Recipe':
                return isset($schema_object['name']) && 
                       isset($schema_object['recipeIngredient']) && 
                       isset($schema_object['recipeInstructions']);
                       
            default:
                return true;
        }
    }

    /**
     * Generate comprehensive recommendations
     */
    private function generate_schema_recommendations($validation_results) {
        $recommendations = [];

        foreach ($validation_results as $result) {
            if (isset($result['recommendations'])) {
                $recommendations = array_merge($recommendations, $result['recommendations']);
            }

            // Add performance-based recommendations
            if (isset($result['completeness_score']) && $result['completeness_score'] < 80) {
                $recommendations[] = "Improve schema completeness (currently {$result['completeness_score']}%) by adding recommended properties";
            }

            if (isset($result['rich_snippet_eligible']) && !$result['rich_snippet_eligible']) {
                $recommendations[] = "Schema is not eligible for rich snippets - add required properties for better search visibility";
            }
        }

        // Remove duplicates and prioritize
        return array_unique($recommendations);
    }

    /**
     * Calculate overall schema score
     */
    private function calculate_overall_schema_score($validation_results) {
        if (empty($validation_results)) {
            return 0;
        }

        $total_score = 0;
        $valid_results = 0;

        foreach ($validation_results as $result) {
            if (isset($result['completeness_score'])) {
                $total_score += $result['completeness_score'];
                $valid_results++;
            } elseif (isset($result['results'])) {
                // Handle multiple schemas
                foreach ($result['results'] as $sub_result) {
                    if (isset($sub_result['completeness_score'])) {
                        $total_score += $sub_result['completeness_score'];
                        $valid_results++;
                    }
                }
            }
        }

        return $valid_results > 0 ? round($total_score / $valid_results) : 0;
    }

    /**
     * Utility methods for data retrieval and parsing
     */
    private function fetch_page_content($url) {
        $response = wp_remote_get($url, [
            'timeout' => $this->config['validation_timeout'],
            'headers' => [
                'User-Agent' => 'KHM-SEO-Schema-Validator/1.0'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception("HTTP {$status_code} response from {$url}");
        }

        return wp_remote_retrieve_body($response);
    }

    private function parse_microdata_element($element, $xpath) {
        $data = [];
        $itemtype = $element->getAttribute('itemtype');
        
        if ($itemtype) {
            $data['@type'] = $this->extract_schema_type_from_url($itemtype);
            $data['@context'] = $this->config['schema_org_context'];
        }

        // Find properties within this element
        $properties = $xpath->query('.//*[@itemprop]', $element);
        
        foreach ($properties as $prop_element) {
            $prop_name = $prop_element->getAttribute('itemprop');
            $prop_value = $this->extract_microdata_value($prop_element);
            
            if ($prop_value !== null) {
                $data[$prop_name] = $prop_value;
            }
        }

        return $data;
    }

    private function parse_rdfa_element($element, $xpath) {
        $data = [];
        $typeof = $element->getAttribute('typeof');
        
        if ($typeof) {
            $data['@type'] = $typeof;
            $data['@context'] = $this->config['schema_org_context'];
        }

        // Find properties within this element
        $properties = $xpath->query('.//*[@property]', $element);
        
        foreach ($properties as $prop_element) {
            $prop_name = $prop_element->getAttribute('property');
            $prop_value = $this->extract_rdfa_value($prop_element);
            
            if ($prop_value !== null) {
                $data[$prop_name] = $prop_value;
            }
        }

        return $data;
    }

    private function extract_microdata_value($element) {
        $tag_name = strtolower($element->tagName);
        
        switch ($tag_name) {
            case 'meta':
                return $element->getAttribute('content');
            case 'img':
                return $element->getAttribute('src');
            case 'a':
                return $element->getAttribute('href');
            case 'time':
                $datetime = $element->getAttribute('datetime');
                return $datetime ?: $element->textContent;
            default:
                return trim($element->textContent);
        }
    }

    private function extract_rdfa_value($element) {
        if ($element->hasAttribute('content')) {
            return $element->getAttribute('content');
        }
        
        if ($element->hasAttribute('href')) {
            return $element->getAttribute('href');
        }
        
        if ($element->hasAttribute('src')) {
            return $element->getAttribute('src');
        }
        
        return trim($element->textContent);
    }

    private function extract_schema_type_from_url($url) {
        $parts = explode('/', rtrim($url, '/'));
        return end($parts);
    }

    private function get_element_location($element) {
        return [
            'tag' => $element->tagName,
            'line' => $element->getLineNo()
        ];
    }

    /**
     * AJAX handlers
     */
    public function ajax_validate_url() {
        if (!wp_verify_nonce($_POST['nonce'], 'schema_validator')) {
            wp_die('Security check failed');
        }

        $url = filter_var($_POST['url'] ?? '', FILTER_SANITIZE_URL);
        if (empty($url)) {
            wp_send_json_error(['message' => 'URL is required']);
            return;
        }

        $result = $this->validate_url($url);
        wp_send_json_success($result);
    }

    public function ajax_validate_markup() {
        if (!wp_verify_nonce($_POST['nonce'], 'schema_validator')) {
            wp_die('Security check failed');
        }

        $markup = $_POST['markup'] ?? '';
        $format = sanitize_text_field($_POST['format'] ?? 'json-ld');

        if (empty($markup)) {
            wp_send_json_error(['message' => 'Markup is required']);
            return;
        }

        $result = $this->validate_markup($markup, $format);
        wp_send_json_success($result);
    }

    /**
     * Cache and storage methods
     */
    private function get_cached_validation($cache_key) {
        return get_transient($cache_key);
    }

    private function cache_validation_result($cache_key, $result) {
        set_transient($cache_key, $result, $this->config['cache_duration']);
    }

    private function store_validation_result($result) {
        global $wpdb;
        $table = $wpdb->prefix . 'gsc_schema_validation';
        
        $wpdb->insert($table, [
            'url' => $result['url'],
            'schemas_found' => $result['schemas_found'],
            'overall_score' => $result['overall_score'],
            'validation_data' => json_encode($result),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Error handling methods
     */
    private function get_error_result($url, $error_message) {
        return [
            'url' => $url,
            'error' => true,
            'message' => $error_message,
            'schemas_found' => 0,
            'overall_score' => 0,
            'validated_at' => current_time('mysql')
        ];
    }

    private function get_markup_error_result($markup, $format, $error_message) {
        return [
            'markup' => $markup,
            'format' => $format,
            'error' => true,
            'message' => $error_message,
            'validated_at' => current_time('mysql')
        ];
    }

    private function get_invalid_schema_result($error_message) {
        return [
            'valid' => false,
            'error' => true,
            'message' => $error_message,
            'completeness_score' => 0,
            'rich_snippet_eligible' => false
        ];
    }

    /**
     * Background processing methods
     */
    public function run_site_schema_scan() {
        // Implementation for full site schema scanning
        return true;
    }

    public function cleanup_validation_cache() {
        // Clean up old validation cache entries
        return true;
    }

    public function analyze_schema_performance() {
        // Analyze schema performance impact
        return true;
    }

    /**
     * Initialization helper methods
     */
    private function load_schema_vocabulary() {
        // Load schema.org vocabulary for validation
        return true;
    }

    private function init_validation_cache() {
        // Initialize validation cache
        $this->validation_cache = [];
        return true;
    }

    private function init_error_reporting() {
        // Set up error reporting
        return true;
    }

    /**
     * Additional helper methods (placeholder implementations)
     */
    private function parse_schema_markup($markup, $format) {
        if ($format === 'json-ld') {
            return json_decode($markup, true);
        }
        return false; // Placeholder for other formats
    }

    private function validate_format_specific($markup, $format) {
        return ['valid' => true, 'warnings' => []];
    }

    private function generate_markup_recommendations($validation_result) {
        return [];
    }

    private function suggest_markup_optimizations($schema) {
        return [];
    }

    private function identify_rich_snippet_opportunities($validation_results) {
        return [];
    }

    private function extract_validation_errors($validation_results) {
        $errors = [];
        foreach ($validation_results as $result) {
            if (isset($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            }
        }
        return $errors;
    }

    private function extract_validation_warnings($validation_results) {
        $warnings = [];
        foreach ($validation_results as $result) {
            if (isset($result['warnings'])) {
                $warnings = array_merge($warnings, $result['warnings']);
            }
        }
        return $warnings;
    }
}