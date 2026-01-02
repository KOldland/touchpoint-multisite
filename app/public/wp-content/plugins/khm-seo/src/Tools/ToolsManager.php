<?php
/**
 * SEO Tools Manager - Phase 3.5
 * 
 * Comprehensive SEO tools and utilities for advanced optimization.
 * Provides robots.txt editing, search engine verification, and system monitoring.
 * 
 * Features:
 * - Robots.txt editor and validator
 * - Search engine verification meta tags
 * - XML sitemap management
 * - System health monitoring
 * - SEO audit tools
 * - Performance monitoring
 * - 404 error tracking
 * - Redirect management
 * 
 * @package KHM_SEO\Tools
 * @since 3.0.0
 * @version 3.0.0
 */

namespace KHM_SEO\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Tools Manager Class
 * Central hub for SEO tools and utilities
 */
class ToolsManager {
    
    /**
     * @var array Tools configuration
     */
    private $config;
    
    /**
     * @var array Search engine verification providers
     */
    private $verification_providers = array(
        'google' => array(
            'name' => 'Google Search Console',
            'meta_name' => 'google-site-verification',
            'help' => 'Get this from Google Search Console → Settings → Ownership verification',
        ),
        'bing' => array(
            'name' => 'Bing Webmaster Tools',
            'meta_name' => 'msvalidate.01',
            'help' => 'Get this from Bing Webmaster Tools → Settings → Verify ownership',
        ),
        'yandex' => array(
            'name' => 'Yandex Webmaster',
            'meta_name' => 'yandex-verification',
            'help' => 'Get this from Yandex Webmaster → Settings → Verification',
        ),
        'baidu' => array(
            'name' => 'Baidu Webmaster Tools',
            'meta_name' => 'baidu-site-verification',
            'help' => 'Get this from Baidu Webmaster Tools → Site verification',
        ),
        'pinterest' => array(
            'name' => 'Pinterest',
            'meta_name' => 'p:domain_verify',
            'help' => 'Get this from Pinterest Business → Settings → Claim website',
        ),
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        $this->init_hooks();
    }
    
    /**
     * Load tools configuration
     */
    private function load_config() {
        $defaults = array(
            'enable_robots_editor' => true,
            'enable_verification_tags' => true,
            'enable_404_monitoring' => true,
            'enable_redirect_management' => true,
            'enable_seo_audit' => true,
            'robots_txt_custom' => '',
            'verification_codes' => array(),
            'redirect_rules' => array(),
            '404_log_limit' => 100,
            'audit_frequency' => 'weekly',
        );
        
        $this->config = \wp_parse_args( \get_option( 'khm_seo_tools_settings', array() ), $defaults );
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Robots.txt handling
        if ( $this->config['enable_robots_editor'] ) {
            \add_filter( 'robots_txt', array( $this, 'generate_robots_txt' ), 10, 2 );
            \add_action( 'init', array( $this, 'handle_robots_txt_request' ) );
        }
        
        // Search engine verification
        if ( $this->config['enable_verification_tags'] ) {
            \add_action( 'wp_head', array( $this, 'output_verification_tags' ), 1 );
        }
        
        // 404 monitoring
        if ( $this->config['enable_404_monitoring'] ) {
            \add_action( 'wp', array( $this, 'monitor_404_errors' ) );
        }
        
        // Redirect management
        if ( $this->config['enable_redirect_management'] ) {
            \add_action( 'template_redirect', array( $this, 'handle_redirects' ) );
        }
        
        // Admin hooks
        \add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        \add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // AJAX handlers
        \add_action( 'wp_ajax_khm_seo_validate_robots', array( $this, 'ajax_validate_robots' ) );
        \add_action( 'wp_ajax_khm_seo_test_redirect', array( $this, 'ajax_test_redirect' ) );
        \add_action( 'wp_ajax_khm_seo_run_audit', array( $this, 'ajax_run_seo_audit' ) );
        \add_action( 'wp_ajax_khm_seo_clear_404_log', array( $this, 'ajax_clear_404_log' ) );
        
        // Scheduled tasks
        \add_action( 'khm_seo_run_audit', array( $this, 'run_scheduled_audit' ) );
        
        // Schedule audit if not already scheduled
        if ( ! \wp_next_scheduled( 'khm_seo_run_audit' ) ) {
            \wp_schedule_event( time(), $this->config['audit_frequency'], 'khm_seo_run_audit' );
        }
    }
    
    /**
     * Generate robots.txt content
     * 
     * @param string $output Current robots.txt output
     * @param bool   $public Whether site is public
     * @return string Modified robots.txt content
     */
    public function generate_robots_txt( $output, $public ) {
        if ( ! $public ) {
            return $output;
        }
        
        $custom_robots = $this->config['robots_txt_custom'];
        
        if ( ! empty( $custom_robots ) ) {
            return $custom_robots;
        }
        
        // Generate default robots.txt
        $robots_content = $this->get_default_robots_txt();
        
        return \apply_filters( 'khm_seo_robots_txt_content', $robots_content );
    }
    
    /**
     * Get default robots.txt content
     * 
     * @return string Default robots.txt content
     */
    private function get_default_robots_txt() {
        $content = "# KHM SEO Generated Robots.txt\n\n";
        
        // User-agent: *
        $content .= "User-agent: *\n";
        
        // Allow all by default
        $content .= "Allow: /\n\n";
        
        // Disallow admin areas
        $content .= "# Disallow admin areas\n";
        $content .= "Disallow: /wp-admin/\n";
        $content .= "Disallow: /wp-includes/\n";
        $content .= "Disallow: /wp-content/plugins/\n";
        $content .= "Disallow: /wp-content/themes/\n";
        $content .= "Allow: /wp-content/uploads/\n\n";
        
        // Disallow search and internal pages
        $content .= "# Disallow search and internal pages\n";
        $content .= "Disallow: /?s=\n";
        $content .= "Disallow: /search/\n";
        $content .= "Disallow: /?attachment_id=\n";
        $content .= "Disallow: /author/\n\n";
        
        // Add XML sitemap if available
        $sitemap_url = \home_url( '/sitemap.xml' );
        if ( $this->sitemap_exists() ) {
            $content .= "# XML Sitemap\n";
            $content .= "Sitemap: $sitemap_url\n\n";
        }
        
        // Add crawl delay for aggressive bots
        $content .= "# Crawl delay for specific bots\n";
        $content .= "User-agent: Baiduspider\n";
        $content .= "Crawl-delay: 10\n\n";
        
        $content .= "User-agent: YandexBot\n";
        $content .= "Crawl-delay: 5\n\n";
        
        return \apply_filters( 'khm_seo_default_robots_txt', $content );
    }
    
    /**
     * Check if XML sitemap exists
     * 
     * @return bool Whether sitemap exists
     */
    private function sitemap_exists() {
        $sitemap_url = \home_url( '/sitemap.xml' );
        $response = \wp_remote_head( $sitemap_url );
        
        return ! \is_wp_error( $response ) && \wp_remote_retrieve_response_code( $response ) === 200;
    }
    
    /**
     * Handle custom robots.txt requests
     */
    public function handle_robots_txt_request() {
        if ( $_SERVER['REQUEST_URI'] === '/robots.txt' ) {
            // Let WordPress handle it with our filter
        }
    }
    
    /**
     * Output search engine verification tags
     */
    public function output_verification_tags() {
        if ( empty( $this->config['verification_codes'] ) ) {
            return;
        }
        
        echo "<!-- Search Engine Verification Tags -->\n";
        
        foreach ( $this->verification_providers as $provider => $data ) {
            $code = $this->config['verification_codes'][ $provider ] ?? '';
            
            if ( ! empty( $code ) ) {
                echo '<meta name="' . \esc_attr( $data['meta_name'] ) . '" content="' . \esc_attr( $code ) . '" />' . "\n";
            }
        }
    }
    
    /**
     * Monitor 404 errors
     */
    public function monitor_404_errors() {
        if ( ! \is_404() ) {
            return;
        }
        
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $referer = \wp_get_referer();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_address = $this->get_client_ip();
        
        // Get current 404 log
        $error_log = \get_option( 'khm_seo_404_log', array() );
        
        // Add new entry
        $error_entry = array(
            'url' => $request_uri,
            'referer' => $referer,
            'user_agent' => $user_agent,
            'ip_address' => $ip_address,
            'timestamp' => \current_time( 'mysql' ),
            'count' => 1,
        );
        
        // Check if URL already exists in log
        $existing_key = $this->find_404_entry( $error_log, $request_uri );
        
        if ( $existing_key !== false ) {
            // Increment count
            $error_log[ $existing_key ]['count']++;
            $error_log[ $existing_key ]['timestamp'] = \current_time( 'mysql' );
        } else {
            // Add new entry
            array_unshift( $error_log, $error_entry );
        }
        
        // Limit log size
        if ( count( $error_log ) > $this->config['404_log_limit'] ) {
            $error_log = array_slice( $error_log, 0, $this->config['404_log_limit'] );
        }
        
        \update_option( 'khm_seo_404_log', $error_log );
        
        // Trigger action for external handling
        \do_action( 'khm_seo_404_logged', $error_entry );
    }
    
    /**
     * Find 404 entry in log
     * 
     * @param array  $log 404 error log
     * @param string $url Request URL
     * @return int|false Array key or false if not found
     */
    private function find_404_entry( $log, $url ) {
        foreach ( $log as $key => $entry ) {
            if ( $entry['url'] === $url ) {
                return $key;
            }
        }
        return false;
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );
        
        foreach ( $ip_headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ips = explode( ',', $_SERVER[ $header ] );
                $ip = trim( $ips[0] );
                
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Handle redirect rules
     */
    public function handle_redirects() {
        if ( empty( $this->config['redirect_rules'] ) ) {
            return;
        }
        
        $current_url = \home_url( $_SERVER['REQUEST_URI'] ?? '' );
        $parsed_url = parse_url( $current_url );
        $path = $parsed_url['path'] ?? '/';
        
        foreach ( $this->config['redirect_rules'] as $rule ) {
            if ( empty( $rule['from'] ) || empty( $rule['to'] ) ) {
                continue;
            }
            
            $match = false;
            
            // Exact match
            if ( $rule['type'] === 'exact' ) {
                $match = $path === $rule['from'];
            }
            // Regex match
            elseif ( $rule['type'] === 'regex' ) {
                $match = preg_match( $rule['from'], $path );
            }
            // Starts with
            elseif ( $rule['type'] === 'starts_with' ) {
                $match = strpos( $path, $rule['from'] ) === 0;
            }
            
            if ( $match ) {
                $redirect_url = $rule['to'];
                $status_code = intval( $rule['status'] ?? 301 );
                
                // Process regex replacements
                if ( $rule['type'] === 'regex' ) {
                    $redirect_url = preg_replace( $rule['from'], $rule['to'], $path );
                }
                
                // Ensure absolute URL
                if ( strpos( $redirect_url, 'http' ) !== 0 ) {
                    $redirect_url = \home_url( $redirect_url );
                }
                
                \wp_redirect( $redirect_url, $status_code );
                exit;
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        \add_submenu_page(
            'khm-seo',
            __( 'SEO Tools', 'khm-seo' ),
            __( 'Tools', 'khm-seo' ),
            'manage_options',
            'khm-seo-tools',
            array( $this, 'render_tools_page' )
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        \register_setting(
            'khm_seo_tools_settings',
            'khm_seo_tools_settings',
            array( $this, 'sanitize_settings' )
        );
    }
    
    /**
     * Sanitize settings
     * 
     * @param array $settings Raw settings
     * @return array Sanitized settings
     */
    public function sanitize_settings( $settings ) {
        $clean = array();
        
        // Boolean settings
        $boolean_fields = array(
            'enable_robots_editor', 'enable_verification_tags',
            'enable_404_monitoring', 'enable_redirect_management',
            'enable_seo_audit'
        );
        
        foreach ( $boolean_fields as $field ) {
            $clean[ $field ] = ! empty( $settings[ $field ] );
        }
        
        // Text settings
        $clean['robots_txt_custom'] = \sanitize_textarea_field( $settings['robots_txt_content'] ?? '' );
        $clean['404_log_limit'] = intval( $settings['404_log_limit'] ?? 100 );
        $clean['audit_frequency'] = \sanitize_text_field( $settings['audit_frequency'] ?? 'weekly' );
        
        // Verification codes
        if ( ! empty( $settings['verification_codes'] ) ) {
            $clean['verification_codes'] = array();
            foreach ( $this->verification_providers as $provider => $data ) {
                $code = \sanitize_text_field( $settings['verification_codes'][ $provider ] ?? '' );
                if ( ! empty( $code ) ) {
                    $clean['verification_codes'][ $provider ] = $code;
                }
            }
        }
        
        // Redirect rules
        if ( ! empty( $settings['redirect_rules'] ) && is_array( $settings['redirect_rules'] ) ) {
            $clean['redirect_rules'] = array();
            foreach ( $settings['redirect_rules'] as $rule ) {
                if ( ! empty( $rule['from'] ) && ! empty( $rule['to'] ) ) {
                    $clean['redirect_rules'][] = array(
                        'from' => \sanitize_text_field( $rule['from'] ),
                        'to' => \sanitize_text_field( $rule['to'] ),
                        'type' => \sanitize_text_field( $rule['type'] ?? 'exact' ),
                        'status' => intval( $rule['status'] ?? 301 ),
                    );
                }
            }
        }
        
        return $clean;
    }
    
    /**
     * Render tools admin page
     */
    public function render_tools_page() {
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        // Handle form submission
        if ( isset( $_POST['submit'] ) && \wp_verify_nonce( $_POST['khm_seo_tools_nonce'], 'khm_seo_tools' ) ) {
            $this->config = array_merge( $this->config, $_POST['khm_seo_tools_settings'] ?? array() );
            \update_option( 'khm_seo_tools_settings', $this->sanitize_settings( $this->config ) );
            
            echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully!', 'khm-seo' ) . '</p></div>';
        }
        
        include dirname( __FILE__ ) . '/templates/tools-admin.php';
    }
    
    /**
     * Run SEO audit
     * 
     * @return array Audit results
     */
    public function run_seo_audit() {
        $audit_results = array(
            'score' => 0,
            'issues' => array(),
            'recommendations' => array(),
            'passed_checks' => array(),
        );
        
        $checks = array(
            'robots_txt' => array( $this, 'audit_robots_txt' ),
            'sitemap' => array( $this, 'audit_sitemap' ),
            'meta_tags' => array( $this, 'audit_meta_tags' ),
            'images' => array( $this, 'audit_images' ),
            'internal_links' => array( $this, 'audit_internal_links' ),
            'performance' => array( $this, 'audit_performance' ),
            '404_errors' => array( $this, 'audit_404_errors' ),
        );
        
        $total_checks = count( $checks );
        $passed_checks = 0;
        
        foreach ( $checks as $check_name => $callback ) {
            if ( is_callable( $callback ) ) {
                $result = call_user_func( $callback );
                
                if ( $result['passed'] ) {
                    $passed_checks++;
                    $audit_results['passed_checks'][] = $result;
                } else {
                    $audit_results['issues'][] = $result;
                }
                
                if ( ! empty( $result['recommendations'] ) ) {
                    $audit_results['recommendations'] = array_merge(
                        $audit_results['recommendations'],
                        $result['recommendations']
                    );
                }
            }
        }
        
        $audit_results['score'] = round( ( $passed_checks / $total_checks ) * 100 );
        
        // Store audit results
        \update_option( 'khm_seo_last_audit', array(
            'timestamp' => \current_time( 'mysql' ),
            'results' => $audit_results,
        ) );
        
        return $audit_results;
    }
    
    /**
     * Audit robots.txt
     * 
     * @return array Audit result
     */
    private function audit_robots_txt() {
        $robots_url = \home_url( '/robots.txt' );
        $response = \wp_remote_get( $robots_url );
        
        if ( \is_wp_error( $response ) ) {
            return array(
                'name' => 'Robots.txt',
                'passed' => false,
                'message' => 'Robots.txt file is not accessible',
                'recommendations' => array( 'Ensure robots.txt is properly configured and accessible' ),
            );
        }
        
        $status_code = \wp_remote_retrieve_response_code( $response );
        $content = \wp_remote_retrieve_body( $response );
        
        if ( $status_code !== 200 ) {
            return array(
                'name' => 'Robots.txt',
                'passed' => false,
                'message' => "Robots.txt returns status code {$status_code}",
                'recommendations' => array( 'Fix robots.txt accessibility issues' ),
            );
        }
        
        if ( empty( $content ) ) {
            return array(
                'name' => 'Robots.txt',
                'passed' => false,
                'message' => 'Robots.txt file is empty',
                'recommendations' => array( 'Add proper robots.txt content' ),
            );
        }
        
        return array(
            'name' => 'Robots.txt',
            'passed' => true,
            'message' => 'Robots.txt is properly configured',
        );
    }
    
    /**
     * Audit XML sitemap
     * 
     * @return array Audit result
     */
    private function audit_sitemap() {
        $sitemap_url = \home_url( '/sitemap.xml' );
        $response = \wp_remote_get( $sitemap_url );
        
        if ( \is_wp_error( $response ) ) {
            return array(
                'name' => 'XML Sitemap',
                'passed' => false,
                'message' => 'XML sitemap is not accessible',
                'recommendations' => array( 'Generate and configure XML sitemap' ),
            );
        }
        
        $status_code = \wp_remote_retrieve_response_code( $response );
        
        if ( $status_code !== 200 ) {
            return array(
                'name' => 'XML Sitemap',
                'passed' => false,
                'message' => "XML sitemap returns status code {$status_code}",
                'recommendations' => array( 'Fix XML sitemap accessibility' ),
            );
        }
        
        return array(
            'name' => 'XML Sitemap',
            'passed' => true,
            'message' => 'XML sitemap is accessible',
        );
    }
    
    /**
     * Audit meta tags
     * 
     * @return array Audit result
     */
    private function audit_meta_tags() {
        $home_url = \home_url( '/' );
        $response = \wp_remote_get( $home_url );
        
        if ( \is_wp_error( $response ) ) {
            return array(
                'name' => 'Meta Tags',
                'passed' => false,
                'message' => 'Cannot access homepage to check meta tags',
                'recommendations' => array( 'Fix homepage accessibility' ),
            );
        }
        
        $content = \wp_remote_retrieve_body( $response );
        $issues = array();
        
        // Check for title tag
        if ( ! preg_match( '/<title[^>]*>(.*?)<\/title>/i', $content ) ) {
            $issues[] = 'Missing title tag';
        }
        
        // Check for meta description
        if ( ! preg_match( '/<meta[^>]*name=["\']description["\'][^>]*>/i', $content ) ) {
            $issues[] = 'Missing meta description';
        }
        
        // Check for viewport meta
        if ( ! preg_match( '/<meta[^>]*name=["\']viewport["\'][^>]*>/i', $content ) ) {
            $issues[] = 'Missing viewport meta tag';
        }
        
        if ( empty( $issues ) ) {
            return array(
                'name' => 'Meta Tags',
                'passed' => true,
                'message' => 'Essential meta tags are present',
            );
        }
        
        return array(
            'name' => 'Meta Tags',
            'passed' => false,
            'message' => 'Missing essential meta tags: ' . implode( ', ', $issues ),
            'recommendations' => array( 'Add missing meta tags to improve SEO' ),
        );
    }
    
    /**
     * Audit images
     * 
     * @return array Audit result
     */
    private function audit_images() {
        // Get recent posts with images
        $posts = \get_posts( array(
            'numberposts' => 10,
            'meta_query' => array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ),
            ),
        ) );
        
        if ( empty( $posts ) ) {
            return array(
                'name' => 'Image Optimization',
                'passed' => true,
                'message' => 'No recent posts with images to audit',
            );
        }
        
        $checked_images = 0;
        $missing_alt = 0;
        
        foreach ( $posts as $post ) {
            $thumbnail_id = \get_post_thumbnail_id( $post->ID );
            if ( $thumbnail_id ) {
                $checked_images++;
                $alt_text = \get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
                
                if ( empty( $alt_text ) ) {
                    $missing_alt++;
                }
            }
        }
        
        if ( $missing_alt > 0 ) {
            $percentage = round( ( $missing_alt / $checked_images ) * 100 );
            return array(
                'name' => 'Image Optimization',
                'passed' => false,
                'message' => "{$percentage}% of images are missing alt text",
                'recommendations' => array( 'Add descriptive alt text to all images' ),
            );
        }
        
        return array(
            'name' => 'Image Optimization',
            'passed' => true,
            'message' => 'Images have proper alt text',
        );
    }
    
    /**
     * Audit internal links
     * 
     * @return array Audit result
     */
    private function audit_internal_links() {
        $recent_posts = \get_posts( array( 'numberposts' => 5 ) );
        $total_links = 0;
        $internal_links = 0;
        
        foreach ( $recent_posts as $post ) {
            $content = $post->post_content;
            preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
            
            if ( ! empty( $matches[1] ) ) {
                $total_links += count( $matches[1] );
                
                foreach ( $matches[1] as $url ) {
                    if ( strpos( $url, \home_url() ) === 0 || strpos( $url, '/' ) === 0 ) {
                        $internal_links++;
                    }
                }
            }
        }
        
        if ( $total_links === 0 ) {
            return array(
                'name' => 'Internal Linking',
                'passed' => false,
                'message' => 'No links found in recent posts',
                'recommendations' => array( 'Add internal links to improve site structure' ),
            );
        }
        
        $internal_percentage = ( $internal_links / $total_links ) * 100;
        
        if ( $internal_percentage < 30 ) {
            return array(
                'name' => 'Internal Linking',
                'passed' => false,
                'message' => "Only {$internal_percentage}% of links are internal",
                'recommendations' => array( 'Increase internal linking between related content' ),
            );
        }
        
        return array(
            'name' => 'Internal Linking',
            'passed' => true,
            'message' => 'Good internal linking structure',
        );
    }
    
    /**
     * Audit performance
     * 
     * @return array Audit result
     */
    private function audit_performance() {
        $issues = array();
        
        // Check for caching
        if ( ! $this->has_caching_plugin() ) {
            $issues[] = 'No caching plugin detected';
        }
        
        // Check for image optimization
        if ( ! $this->has_image_optimization() ) {
            $issues[] = 'No image optimization plugin detected';
        }
        
        if ( empty( $issues ) ) {
            return array(
                'name' => 'Performance',
                'passed' => true,
                'message' => 'Basic performance optimizations in place',
            );
        }
        
        return array(
            'name' => 'Performance',
            'passed' => false,
            'message' => 'Performance issues: ' . implode( ', ', $issues ),
            'recommendations' => array( 'Install caching and image optimization plugins' ),
        );
    }
    
    /**
     * Audit 404 errors
     * 
     * @return array Audit result
     */
    private function audit_404_errors() {
        $error_log = \get_option( 'khm_seo_404_log', array() );
        
        if ( empty( $error_log ) ) {
            return array(
                'name' => '404 Errors',
                'passed' => true,
                'message' => 'No 404 errors detected recently',
            );
        }
        
        $recent_errors = array_filter( $error_log, function( $entry ) {
            return strtotime( $entry['timestamp'] ) > ( time() - WEEK_IN_SECONDS );
        } );
        
        $error_count = count( $recent_errors );
        
        if ( $error_count > 20 ) {
            return array(
                'name' => '404 Errors',
                'passed' => false,
                'message' => "{$error_count} 404 errors in the last week",
                'recommendations' => array( 'Review and fix broken links or add redirects' ),
            );
        }
        
        return array(
            'name' => '404 Errors',
            'passed' => true,
            'message' => "Only {$error_count} 404 errors in the last week",
        );
    }
    
    /**
     * Check if caching plugin is active
     * 
     * @return bool Whether caching is available
     */
    private function has_caching_plugin() {
        $caching_plugins = array(
            'wp-rocket/wp-rocket.php',
            'w3-total-cache/w3-total-cache.php',
            'wp-super-cache/wp-cache.php',
            'litespeed-cache/litespeed-cache.php',
            'wp-fastest-cache/wpFastestCache.php',
        );
        
        foreach ( $caching_plugins as $plugin ) {
            if ( \is_plugin_active( $plugin ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if image optimization is available
     * 
     * @return bool Whether image optimization is available
     */
    private function has_image_optimization() {
        $image_plugins = array(
            'smush/wp-smush.php',
            'shortpixel-image-optimiser/wp-shortpixel.php',
            'ewww-image-optimizer/ewww-image-optimizer.php',
            'imagify/imagify.php',
            'optimole-wp/optimole-wp.php',
        );
        
        foreach ( $image_plugins as $plugin ) {
            if ( \is_plugin_active( $plugin ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Run scheduled audit
     */
    public function run_scheduled_audit() {
        $audit_results = $this->run_seo_audit();
        
        // Send audit report to admin if score is below threshold
        if ( $audit_results['score'] < 70 ) {
            $this->send_audit_notification( $audit_results );
        }
        
        \do_action( 'khm_seo_audit_completed', $audit_results );
    }
    
    /**
     * Send audit notification email
     * 
     * @param array $audit_results Audit results
     */
    private function send_audit_notification( $audit_results ) {
        $admin_email = \get_option( 'admin_email' );
        $site_name = \get_bloginfo( 'name' );
        
        $subject = sprintf( __( 'SEO Audit Report for %s - Score: %d%%', 'khm-seo' ), $site_name, $audit_results['score'] );
        
        $message = sprintf( __( 'Your website %s has completed an SEO audit with a score of %d%%.', 'khm-seo' ), $site_name, $audit_results['score'] );
        $message .= "\n\n" . __( 'Issues found:', 'khm-seo' ) . "\n";
        
        foreach ( $audit_results['issues'] as $issue ) {
            $message .= "- " . $issue['name'] . ": " . $issue['message'] . "\n";
        }
        
        $message .= "\n" . sprintf( __( 'View full report: %s', 'khm-seo' ), \admin_url( 'admin.php?page=khm-seo-tools&tab=audit' ) );
        
        \wp_mail( $admin_email, $subject, $message );
    }
    
    /**
     * AJAX validate robots.txt
     */
    public function ajax_validate_robots() {
        \check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        $robots_content = \sanitize_textarea_field( $_POST['robots_content'] ?? '' );
        
        if ( empty( $robots_content ) ) {
            \wp_send_json_error( __( 'Robots.txt content is empty', 'khm-seo' ) );
        }
        
        // Basic validation
        $validation_results = array(
            'valid' => true,
            'warnings' => array(),
            'errors' => array(),
        );
        
        // Check for common issues
        if ( strpos( $robots_content, 'Disallow: /' ) !== false && strpos( $robots_content, 'Allow:' ) === false ) {
            $validation_results['warnings'][] = 'You are blocking all robots. This may not be intended.';
        }
        
        if ( strpos( $robots_content, 'Sitemap:' ) === false ) {
            $validation_results['warnings'][] = 'No sitemap reference found in robots.txt.';
        }
        
        \wp_send_json_success( $validation_results );
    }
    
    /**
     * AJAX test redirect
     */
    public function ajax_test_redirect() {
        \check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        $test_url = \sanitize_url( $_POST['test_url'] ?? '' );
        
        if ( empty( $test_url ) ) {
            \wp_send_json_error( __( 'Test URL is required', 'khm-seo' ) );
        }
        
        $response = \wp_remote_head( $test_url, array( 'redirection' => 0 ) );
        
        if ( \is_wp_error( $response ) ) {
            \wp_send_json_error( $response->get_error_message() );
        }
        
        $status_code = \wp_remote_retrieve_response_code( $response );
        $location = \wp_remote_retrieve_header( $response, 'location' );
        
        $result = array(
            'status_code' => $status_code,
            'redirect_url' => $location,
            'is_redirect' => in_array( $status_code, array( 301, 302, 307, 308 ) ),
        );
        
        \wp_send_json_success( $result );
    }
    
    /**
     * AJAX run SEO audit
     */
    public function ajax_run_seo_audit() {
        \check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        $audit_results = $this->run_seo_audit();
        
        \wp_send_json_success( $audit_results );
    }
    
    /**
     * AJAX clear 404 log
     */
    public function ajax_clear_404_log() {
        \check_ajax_referer( 'khm_seo_ajax', 'nonce' );
        
        \delete_option( 'khm_seo_404_log' );
        
        \wp_send_json_success( __( '404 error log cleared successfully', 'khm-seo' ) );
    }
}