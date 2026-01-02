<?php
/**
 * AJAX Handlers for Social Strip
 *
 * This class handles all AJAX requests for the Social Strip plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KSS_Ajax_Handlers {

    private $khm_integration;

    /**
     * Constructor
     */
    public function __construct($khm_integration = null) {
        $this->khm_integration = $khm_integration;
        $this->init_ajax_handlers();
    }

    /**
     * Initialize AJAX handlers
     */
    private function init_ajax_handlers() {
        // Only add handlers if KHM integration is not already handling them
        if (!$this->khm_integration) {
            // Basic AJAX handlers for when KHM integration is not available
            add_action('wp_ajax_kss_basic_action', [$this, 'handle_basic_action']);
            add_action('wp_ajax_nopriv_kss_basic_action', [$this, 'handle_basic_action']);
        }

        // General plugin AJAX handlers (not KHM specific)
        add_action('wp_ajax_kss_get_post_data', [$this, 'handle_get_post_data']);
        add_action('wp_ajax_nopriv_kss_get_post_data', [$this, 'handle_get_post_data']);
        
        add_action('wp_ajax_kss_update_settings', [$this, 'handle_update_settings']);
        
        // Affiliate tracking handlers
        add_action('wp_ajax_kss_track_affiliate_click', [$this, 'handle_affiliate_click']);
        add_action('wp_ajax_nopriv_kss_track_affiliate_click', [$this, 'handle_affiliate_click']);

        // Share telemetry handler
        add_action('wp_ajax_kss_track_share', [$this, 'handle_track_share']);
        add_action('wp_ajax_nopriv_kss_track_share', [$this, 'handle_track_share']);
    }

    /**
     * Track social share events so other systems (SMMA, PPC, analytics) can react.
     */
    public function handle_track_share() {
        check_ajax_referer('kss_modal_nonce', 'nonce');

        $platform = sanitize_key($_POST['platform'] ?? '');
        $post_id  = absint($_POST['post_id'] ?? 0);
        $share_url = esc_url_raw($_POST['url'] ?? '');
        $content  = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
        $char_count = absint($_POST['char_count'] ?? strlen($content));
        $source   = sanitize_key($_POST['source'] ?? 'social_strip_modal');

        if (empty($platform) || empty($share_url)) {
            wp_send_json_error('Missing share context');
        }

        // Enforce same-site HTTPS URLs to avoid open redirects.
        $parsed = wp_parse_url($share_url);
        $site   = wp_parse_url(home_url());
        if (empty($parsed['scheme']) || !in_array($parsed['scheme'], ['https','http'], true)) {
            wp_send_json_error('Invalid share URL');
        }
        if (!empty($site['host']) && !empty($parsed['host']) && $site['host'] !== $parsed['host']) {
            $share_url = home_url();
        }

        // Lightweight rate limit to reduce spam.
        $rate_key = 'kss_share_rate_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $count = (int) get_transient($rate_key);
        if ($count >= 30) {
            wp_send_json_error('Too many share events. Please slow down.', 429);
        }
        set_transient($rate_key, $count + 1, MINUTE_IN_SECONDS);

        $hashtags = $_POST['hashtags'] ?? [];
        if (!is_array($hashtags)) {
            $hashtags = [$hashtags];
        }
        $hashtags = array_values(array_filter(array_map(function($tag) {
            return sanitize_text_field(wp_unslash($tag));
        }, $hashtags)));

        $meta = [];
        if (!empty($_POST['meta']) && is_array($_POST['meta'])) {
            foreach ($_POST['meta'] as $key => $value) {
                $meta[sanitize_key($key)] = sanitize_text_field(wp_unslash($value));
            }
        }

        $share_event = [
            'platform'   => $platform,
            'post_id'    => $post_id,
            'share_url'  => $share_url,
            'content'    => $content,
            'hashtags'   => $hashtags,
            'char_count' => $char_count,
            'user_id'    => get_current_user_id(),
            'timestamp'  => current_time('mysql'),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'meta'       => $meta,
            'source'     => $source,
        ];

        if ($post_id) {
            $events = get_post_meta($post_id, '_kss_share_events', true);
            if (!is_array($events)) {
                $events = [];
            }
            array_unshift($events, $share_event);
            if (count($events) > 25) {
                $events = array_slice($events, 0, 25);
            }
            update_post_meta($post_id, '_kss_share_events', $events);
        }

        /**
         * Allow external systems (SMMA/PPC/Analytics) to react to share events.
         *
         * @since 1.3
         *
         * @param array $share_event Details about the share event.
         */
        do_action('kss_share_tracked', $share_event);

        wp_send_json_success(['logged' => true]);
    }

    /**
     * Handle basic action AJAX (fallback when KHM not available)
     */
    public function handle_basic_action() {
        check_ajax_referer('kss_basic_nonce', 'nonce');
        
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        
        switch ($action_type) {
            case 'share':
                $this->handle_basic_share();
                break;
            case 'info':
                $this->handle_basic_info();
                break;
            default:
                wp_send_json_error('Unknown action type');
        }
    }

    /**
     * Handle basic share functionality
     */
    private function handle_basic_share() {
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }

        wp_send_json_success([
            'title' => $post->post_title,
            'url' => get_permalink($post_id),
            'excerpt' => wp_trim_words($post->post_content, 20)
        ]);
    }

    /**
     * Handle basic info request
     */
    private function handle_basic_info() {
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        wp_send_json_success([
            'khm_available' => function_exists('khm_is_marketing_suite_ready') && khm_is_marketing_suite_ready(),
            'user_logged_in' => is_user_logged_in(),
            'post_id' => $post_id
        ]);
    }

    /**
     * Handle get post data AJAX
     */
    public function handle_get_post_data() {
        $post_id = intval($_POST['post_id'] ?? $_GET['post_id'] ?? 0);
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }

        $response_data = [
            'id' => $post_id,
            'title' => $post->post_title,
            'excerpt' => wp_trim_words($post->post_content, 30),
            'url' => get_permalink($post_id),
            'author' => get_the_author_meta('display_name', $post->post_author),
            'date' => get_the_date('F j, Y', $post_id),
            'featured_image' => get_the_post_thumbnail_url($post_id, 'medium')
        ];

        // Add KHM-specific data if available
        if (function_exists('kss_get_enhanced_widget_data')) {
            $enhanced_data = kss_get_enhanced_widget_data($post_id);
            $response_data = array_merge($response_data, $enhanced_data);
        }

        wp_send_json_success($response_data);
    }

    /**
     * Handle settings update AJAX
     */
    public function handle_update_settings() {
        check_ajax_referer('kss_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $settings = $_POST['settings'] ?? [];
        
        // Sanitize settings
        $sanitized_settings = [];
        foreach ($settings as $key => $value) {
            $sanitized_key = sanitize_key($key);
            $sanitized_value = sanitize_text_field($value);
            $sanitized_settings[$sanitized_key] = $sanitized_value;
        }

        // Save settings
        $saved = update_option('kss_settings', $sanitized_settings);
        
        if ($saved) {
            wp_send_json_success(['message' => 'Settings saved successfully']);
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }

    /**
     * Handle affiliate click tracking
     */
    public function handle_affiliate_click() {
        $post_id = intval($_POST['post_id'] ?? 0);
        $affiliate_id = sanitize_text_field($_POST['affiliate_id'] ?? '');
        $click_type = sanitize_text_field($_POST['click_type'] ?? 'general');
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        // Track the affiliate click
        $tracking_data = [
            'post_id' => $post_id,
            'affiliate_id' => $affiliate_id,
            'click_type' => $click_type,
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => current_time('mysql'),
            'referrer' => wp_get_referer()
        ];

        // Fire action for affiliate tracking systems
        do_action('kss_affiliate_click_tracked', $tracking_data);

        // Save to database if tracking table exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'kss_affiliate_clicks';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $wpdb->insert($table_name, $tracking_data);
        }

        wp_send_json_success(['message' => 'Click tracked']);
    }

    /**
     * Get settings for frontend
     */
    public static function get_frontend_settings() {
        $settings = get_option('kss_settings', []);
        
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kss_basic_nonce'),
            'settings_nonce' => wp_create_nonce('kss_settings_nonce'),
            'user_logged_in' => is_user_logged_in(),
            'current_user_id' => get_current_user_id(),
            'khm_available' => function_exists('khm_is_marketing_suite_ready') && khm_is_marketing_suite_ready(),
            'plugin_settings' => $settings
        ];
    }
}

// Enqueue settings for JavaScript
add_action('wp_enqueue_scripts', function() {
    if (is_admin()) return;
    
    wp_localize_script('kss-social-strip', 'kss_ajax', KSS_Ajax_Handlers::get_frontend_settings());
});
