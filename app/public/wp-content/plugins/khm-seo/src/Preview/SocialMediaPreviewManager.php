<?php
/**
 * Social Media Preview Manager
 * 
 * Provides real-time previews of how content will appear on different social media platforms
 * including Facebook, Twitter, LinkedIn, WhatsApp, and more with accurate rendering.
 * 
 * @package KHM_SEO
 * @subpackage Preview
 * @version 1.0.0
 * @since 1.0.0
 */

namespace KHM_SEO\Preview;

use KHM_SEO\Social\SocialMediaManager;

if (!defined('ABSPATH')) {
    exit;
}

class SocialMediaPreviewManager {
    
    /**
     * Social Media Manager instance
     */
    private $social_manager;
    
    /**
     * Platform configurations
     */
    private $platforms;
    
    /**
     * Preview cache
     */
    private $preview_cache;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->social_manager = new SocialMediaManager();
        $this->preview_cache = [];
        $this->init_platforms();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin hooks
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
            add_action('add_meta_boxes', [$this, 'add_preview_meta_boxes']);
            
            // AJAX handlers
            add_action('wp_ajax_khm_generate_social_preview', [$this, 'ajax_generate_preview']);
            add_action('wp_ajax_khm_refresh_social_preview', [$this, 'ajax_refresh_preview']);
            add_action('wp_ajax_khm_update_social_meta', [$this, 'ajax_update_meta']);
            
            // Gutenberg integration
            add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        }
        
        // Frontend hooks for live preview
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('wp_head', [$this, 'add_preview_meta'], 1);
    }
    
    /**
     * Initialize platform configurations
     */
    private function init_platforms() {
        $this->platforms = [
            'facebook' => [
                'name' => 'Facebook',
                'icon' => '📘',
                'card_width' => 500,
                'card_height' => 261,
                'image_ratio' => '1.91:1',
                'title_limit' => 100,
                'description_limit' => 300,
                'required_meta' => ['og:title', 'og:description', 'og:image'],
                'preview_url' => 'https://developers.facebook.com/tools/debug/',
                'colors' => [
                    'background' => '#ffffff',
                    'border' => '#dadde1',
                    'title' => '#1d2129',
                    'description' => '#606770',
                    'link' => '#898f9c'
                ]
            ],
            'twitter' => [
                'name' => 'Twitter',
                'icon' => '🐦',
                'card_width' => 504,
                'card_height' => 251,
                'image_ratio' => '2:1',
                'title_limit' => 70,
                'description_limit' => 200,
                'required_meta' => ['twitter:title', 'twitter:description', 'twitter:image'],
                'preview_url' => 'https://cards-dev.twitter.com/validator',
                'colors' => [
                    'background' => '#ffffff',
                    'border' => '#cfd9de',
                    'title' => '#0f1419',
                    'description' => '#536471',
                    'link' => '#1d9bf0'
                ]
            ],
            'linkedin' => [
                'name' => 'LinkedIn',
                'icon' => '💼',
                'card_width' => 520,
                'card_height' => 272,
                'image_ratio' => '1.91:1',
                'title_limit' => 150,
                'description_limit' => 300,
                'required_meta' => ['og:title', 'og:description', 'og:image'],
                'preview_url' => 'https://www.linkedin.com/post-inspector/',
                'colors' => [
                    'background' => '#ffffff',
                    'border' => '#d0d0d0',
                    'title' => '#000000',
                    'description' => '#666666',
                    'link' => '#0073b1'
                ]
            ],
            'whatsapp' => [
                'name' => 'WhatsApp',
                'icon' => '💬',
                'card_width' => 400,
                'card_height' => 200,
                'image_ratio' => '1.91:1',
                'title_limit' => 65,
                'description_limit' => 160,
                'required_meta' => ['og:title', 'og:description', 'og:image'],
                'preview_url' => null,
                'colors' => [
                    'background' => '#ffffff',
                    'border' => '#e1e1e1',
                    'title' => '#000000',
                    'description' => '#8696a0',
                    'link' => '#25d366'
                ]
            ],
            'discord' => [
                'name' => 'Discord',
                'icon' => '🎮',
                'card_width' => 432,
                'card_height' => 232,
                'image_ratio' => '1.91:1',
                'title_limit' => 256,
                'description_limit' => 350,
                'required_meta' => ['og:title', 'og:description', 'og:image'],
                'preview_url' => null,
                'colors' => [
                    'background' => '#2f3136',
                    'border' => '#4f545c',
                    'title' => '#ffffff',
                    'description' => '#dcddde',
                    'link' => '#00b0f4'
                ]
            ],
            'slack' => [
                'name' => 'Slack',
                'icon' => '💬',
                'card_width' => 360,
                'card_height' => 200,
                'image_ratio' => '1.91:1',
                'title_limit' => 80,
                'description_limit' => 200,
                'required_meta' => ['og:title', 'og:description', 'og:image'],
                'preview_url' => null,
                'colors' => [
                    'background' => '#ffffff',
                    'border' => '#e8e8e8',
                    'title' => '#1d1c1d',
                    'description' => '#616061',
                    'link' => '#1264a3'
                ]
            ]
        ];
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        global $post;
        
        // Only load on post edit screens
        if (!in_array($hook, ['post.php', 'post-new.php']) || !$post) {
            return;
        }
        
        wp_enqueue_script(
            'khm-social-preview',
            KHM_SEO_PLUGIN_URL . 'src/Preview/assets/js/social-preview.js',
            ['jquery', 'wp-util', 'jquery-ui-tabs'],
            KHM_SEO_VERSION,
            true
        );
        
        wp_enqueue_style(
            'khm-social-preview',
            KHM_SEO_PLUGIN_URL . 'src/Preview/assets/css/social-preview.css',
            [],
            KHM_SEO_VERSION
        );
        
        wp_localize_script('khm-social-preview', 'khmSocialPreview', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('khm_social_preview_nonce'),
            'postId' => $post->ID,
            'platforms' => $this->platforms,
            'strings' => [
                'generating' => __('Generating preview...', 'khm-seo'),
                'refreshing' => __('Refreshing preview...', 'khm-seo'),
                'updating' => __('Updating meta tags...', 'khm-seo'),
                'success' => __('Preview updated successfully', 'khm-seo'),
                'error' => __('Error generating preview', 'khm-seo'),
                'noImage' => __('No image specified', 'khm-seo'),
                'imageTooSmall' => __('Image may be too small for optimal display', 'khm-seo'),
                'titleTooLong' => __('Title exceeds recommended length', 'khm-seo'),
                'descriptionTooLong' => __('Description exceeds recommended length', 'khm-seo'),
                'missingMeta' => __('Missing required meta tags', 'khm-seo')
            ]
        ]);
    }
    
    /**
     * Enqueue Gutenberg block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'khm-social-preview-block',
            KHM_SEO_PLUGIN_URL . 'src/Preview/assets/js/social-preview-block.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components'],
            KHM_SEO_VERSION,
            true
        );
        
        wp_localize_script('khm-social-preview-block', 'khmSocialPreviewBlock', [
            'platforms' => array_keys($this->platforms)
        ]);
    }
    
    /**
     * Enqueue frontend scripts for live preview
     */
    public function enqueue_frontend_scripts() {
        // Check if we're in a context where these functions are available
        if (!function_exists('is_single') || !function_exists('is_page')) {
            return;
        }
        
        if (!is_single() && !is_page()) {
            return;
        }
        
        // Only load for logged-in users with edit permissions
        if (!current_user_can('edit_posts')) {
            return;
        }
        
        wp_enqueue_script(
            'khm-social-preview-frontend',
            KHM_SEO_PLUGIN_URL . 'src/Preview/assets/js/social-preview-frontend.js',
            ['jquery'],
            KHM_SEO_VERSION,
            true
        );
        
        wp_enqueue_style(
            'khm-social-preview-frontend',
            KHM_SEO_PLUGIN_URL . 'src/Preview/assets/css/social-preview-frontend.css',
            [],
            KHM_SEO_VERSION
        );
    }
    
    /**
     * Add preview meta boxes to post edit screen
     */
    public function add_preview_meta_boxes() {
        $post_types = get_post_types(['public' => true]);
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'khm-social-preview',
                __('Social Media Previews', 'khm-seo'),
                [$this, 'render_preview_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render the social media preview meta box
     */
    public function render_preview_meta_box($post) {
        wp_nonce_field('khm_social_preview_meta', 'khm_social_preview_nonce');
        
        include KHM_SEO_PLUGIN_DIR . 'src/Preview/templates/preview-meta-box.php';
    }
    
    /**
     * Generate social media preview for a post
     */
    public function generate_preview($post_id, $platform = 'all') {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        $cache_key = "social_preview_{$post_id}_{$platform}";
        
        // Check cache first
        if (isset($this->preview_cache[$cache_key])) {
            return $this->preview_cache[$cache_key];
        }
        
        // Get cached version
        $cached_preview = get_transient($cache_key);
        if ($cached_preview !== false) {
            $this->preview_cache[$cache_key] = $cached_preview;
            return $cached_preview;
        }
        
        // Generate new preview
        $preview_data = [];
        
        if ($platform === 'all') {
            foreach (array_keys($this->platforms) as $platform_key) {
                $preview_data[$platform_key] = $this->generate_platform_preview($post, $platform_key);
            }
        } else {
            $preview_data[$platform] = $this->generate_platform_preview($post, $platform);
        }
        
        // Cache the result
        set_transient($cache_key, $preview_data, HOUR_IN_SECONDS);
        $this->preview_cache[$cache_key] = $preview_data;
        
        return $preview_data;
    }
    
    /**
     * Generate preview for specific platform
     */
    private function generate_platform_preview($post, $platform) {
        if (!isset($this->platforms[$platform])) {
            return false;
        }
        
        $platform_config = $this->platforms[$platform];
        $meta_data = $this->get_post_meta_data($post, $platform);
        
        $preview = [
            'platform' => $platform,
            'config' => $platform_config,
            'meta' => $meta_data,
            'card' => $this->generate_card_html($meta_data, $platform_config),
            'warnings' => $this->validate_meta_data($meta_data, $platform_config),
            'suggestions' => $this->get_optimization_suggestions($meta_data, $platform_config),
            'timestamp' => current_time('mysql')
        ];
        
        return $preview;
    }
    
    /**
     * Get post meta data for social sharing
     */
    private function get_post_meta_data($post, $platform) {
        // Get existing social meta or generate from post content
        $meta = [];
        
        // Title
        $custom_title = get_post_meta($post->ID, "_khm_seo_{$platform}_title", true);
        $meta['title'] = !empty($custom_title) ? $custom_title : $this->generate_default_title($post);
        
        // Description  
        $custom_description = get_post_meta($post->ID, "_khm_seo_{$platform}_description", true);
        $meta['description'] = !empty($custom_description) ? $custom_description : $this->generate_default_description($post);
        
        // Image
        $custom_image = get_post_meta($post->ID, "_khm_seo_{$platform}_image", true);
        $meta['image'] = !empty($custom_image) ? $custom_image : $this->get_default_image($post);
        
        // URL
        $meta['url'] = get_permalink($post->ID);
        
        // Site name
        $meta['site_name'] = get_bloginfo('name');
        
        // Author (for some platforms)
        $author = get_userdata($post->post_author);
        $meta['author'] = $author ? $author->display_name : '';
        
        // Published date
        $meta['published_time'] = get_the_date('c', $post->ID);
        
        // Platform-specific meta
        switch ($platform) {
            case 'twitter':
                $meta['card_type'] = 'summary_large_image';
                $meta['site'] = get_option('khm_seo_twitter_site', '');
                $meta['creator'] = get_option('khm_seo_twitter_creator', '');
                break;
                
            case 'facebook':
                $meta['type'] = 'article';
                $meta['app_id'] = get_option('khm_seo_facebook_app_id', '');
                break;
                
            case 'linkedin':
                $meta['type'] = 'article';
                break;
        }
        
        return $meta;
    }
    
    /**
     * Generate default title from post
     */
    private function generate_default_title($post) {
        $title = get_the_title($post->ID);
        
        if (empty($title)) {
            $title = $post->post_title;
        }
        
        return $title;
    }
    
    /**
     * Generate default description from post
     */
    private function generate_default_description($post) {
        // Try excerpt first
        $description = $post->post_excerpt;
        
        // If no excerpt, generate from content
        if (empty($description)) {
            $content = strip_tags($post->post_content);
            $content = preg_replace('/\s+/', ' ', $content);
            $description = substr(trim($content), 0, 300);
        }
        
        return $description;
    }
    
    /**
     * Get default image for post
     */
    private function get_default_image($post) {
        // Try featured image first
        $image_id = get_post_thumbnail_id($post->ID);
        if ($image_id) {
            $image = wp_get_attachment_image_src($image_id, 'large');
            if ($image) {
                return $image[0];
            }
        }
        
        // Try first image in content
        $content = $post->post_content;
        preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches);
        if (!empty($matches[1])) {
            return $matches[1];
        }
        
        // Default site image
        $default_image = get_option('khm_seo_default_image', '');
        if (!empty($default_image)) {
            return $default_image;
        }
        
        return '';
    }
    
    /**
     * Generate HTML for social media card
     */
    private function generate_card_html($meta, $config) {
        $card_html = '<div class="social-preview-card social-preview-' . esc_attr($config['name']) . '"';
        $card_html .= ' style="width: ' . $config['card_width'] . 'px; max-width: 100%; border: 1px solid ' . $config['colors']['border'] . '; background: ' . $config['colors']['background'] . '; border-radius: 8px; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
        
        // Image section
        if (!empty($meta['image'])) {
            $card_html .= '<div class="card-image" style="width: 100%; height: ' . ($config['card_height'] * 0.6) . 'px; background-image: url(\'' . esc_url($meta['image']) . '\'); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>';
        }
        
        // Content section
        $card_html .= '<div class="card-content" style="padding: 12px 16px;">';
        
        // Title
        $title = $this->truncate_text($meta['title'], $config['title_limit']);
        $card_html .= '<div class="card-title" style="font-size: 16px; font-weight: 600; line-height: 1.3; color: ' . $config['colors']['title'] . '; margin-bottom: 4px; word-wrap: break-word;">' . esc_html($title) . '</div>';
        
        // Description
        $description = $this->truncate_text($meta['description'], $config['description_limit']);
        $card_html .= '<div class="card-description" style="font-size: 14px; line-height: 1.4; color: ' . $config['colors']['description'] . '; margin-bottom: 8px; word-wrap: break-word;">' . esc_html($description) . '</div>';
        
        // URL
        $domain = parse_url($meta['url'], PHP_URL_HOST);
        $card_html .= '<div class="card-url" style="font-size: 13px; color: ' . $config['colors']['link'] . '; text-transform: uppercase; letter-spacing: 0.5px;">' . esc_html($domain) . '</div>';
        
        $card_html .= '</div>';
        $card_html .= '</div>';
        
        return $card_html;
    }
    
    /**
     * Validate meta data against platform requirements
     */
    private function validate_meta_data($meta, $config) {
        $warnings = [];
        
        // Check required meta tags
        foreach ($config['required_meta'] as $required) {
            $meta_key = str_replace(['og:', 'twitter:'], '', $required);
            if (empty($meta[$meta_key])) {
                $warnings[] = [
                    'type' => 'missing_meta',
                    'field' => $meta_key,
                    'message' => sprintf(__('Missing %s', 'khm-seo'), $required),
                    'severity' => 'high'
                ];
            }
        }
        
        // Check title length
        if (!empty($meta['title']) && strlen($meta['title']) > $config['title_limit']) {
            $warnings[] = [
                'type' => 'title_too_long',
                'field' => 'title',
                'message' => sprintf(__('Title exceeds %d characters (%d)', 'khm-seo'), $config['title_limit'], strlen($meta['title'])),
                'severity' => 'medium'
            ];
        }
        
        // Check description length
        if (!empty($meta['description']) && strlen($meta['description']) > $config['description_limit']) {
            $warnings[] = [
                'type' => 'description_too_long',
                'field' => 'description',
                'message' => sprintf(__('Description exceeds %d characters (%d)', 'khm-seo'), $config['description_limit'], strlen($meta['description'])),
                'severity' => 'medium'
            ];
        }
        
        // Check image
        if (!empty($meta['image'])) {
            $image_info = $this->get_image_info($meta['image']);
            if ($image_info) {
                $recommended_width = $config['card_width'] * 2; // 2x for retina
                if ($image_info['width'] < $recommended_width) {
                    $warnings[] = [
                        'type' => 'image_too_small',
                        'field' => 'image',
                        'message' => sprintf(__('Image width %dpx is smaller than recommended %dpx', 'khm-seo'), $image_info['width'], $recommended_width),
                        'severity' => 'low'
                    ];
                }
            }
        } else {
            $warnings[] = [
                'type' => 'missing_image',
                'field' => 'image',
                'message' => __('No image specified - may not display optimally', 'khm-seo'),
                'severity' => 'medium'
            ];
        }
        
        return $warnings;
    }
    
    /**
     * Get optimization suggestions
     */
    private function get_optimization_suggestions($meta, $config) {
        $suggestions = [];
        
        // Title optimization
        if (!empty($meta['title'])) {
            $title_length = strlen($meta['title']);
            $optimal_min = $config['title_limit'] * 0.7;
            
            if ($title_length < $optimal_min) {
                $suggestions[] = [
                    'type' => 'title_optimization',
                    'message' => __('Consider making the title longer for better engagement', 'khm-seo'),
                    'action' => 'expand_title'
                ];
            }
        }
        
        // Description optimization
        if (!empty($meta['description'])) {
            $desc_length = strlen($meta['description']);
            $optimal_min = $config['description_limit'] * 0.6;
            
            if ($desc_length < $optimal_min) {
                $suggestions[] = [
                    'type' => 'description_optimization',
                    'message' => __('Consider adding more detail to the description', 'khm-seo'),
                    'action' => 'expand_description'
                ];
            }
        }
        
        // Image optimization
        if (!empty($meta['image'])) {
            $image_info = $this->get_image_info($meta['image']);
            if ($image_info) {
                // Check aspect ratio
                $current_ratio = $image_info['width'] / $image_info['height'];
                $recommended_ratio = $config['card_width'] / ($config['card_height'] * 0.6);
                
                if (abs($current_ratio - $recommended_ratio) > 0.2) {
                    $suggestions[] = [
                        'type' => 'image_optimization',
                        'message' => sprintf(__('Image aspect ratio %s doesn\'t match recommended %s', 'khm-seo'), 
                            number_format($current_ratio, 2) . ':1', 
                            $config['image_ratio']),
                        'action' => 'optimize_image'
                    ];
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get image information
     */
    private function get_image_info($image_url) {
        $cache_key = 'image_info_' . md5($image_url);
        $cached_info = get_transient($cache_key);
        
        if ($cached_info !== false) {
            return $cached_info;
        }
        
        // Try to get image size
        $image_info = @getimagesize($image_url);
        
        if ($image_info !== false) {
            $info = [
                'width' => $image_info[0],
                'height' => $image_info[1],
                'type' => $image_info[2],
                'mime' => $image_info['mime']
            ];
            
            set_transient($cache_key, $info, DAY_IN_SECONDS);
            return $info;
        }
        
        return false;
    }
    
    /**
     * Truncate text to specified length
     */
    private function truncate_text($text, $length) {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length - 3) . '...';
    }
    
    /**
     * Refresh preview cache
     */
    public function refresh_preview($post_id, $platform = 'all') {
        // Clear cache
        $cache_keys = [];
        
        if ($platform === 'all') {
            foreach (array_keys($this->platforms) as $platform_key) {
                $cache_keys[] = "social_preview_{$post_id}_{$platform_key}";
            }
            $cache_keys[] = "social_preview_{$post_id}_all";
        } else {
            $cache_keys[] = "social_preview_{$post_id}_{$platform}";
        }
        
        foreach ($cache_keys as $key) {
            delete_transient($key);
            unset($this->preview_cache[$key]);
        }
        
        // Generate fresh preview
        return $this->generate_preview($post_id, $platform);
    }
    
    /**
     * Update social meta tags for post
     */
    public function update_social_meta($post_id, $platform, $meta_data) {
        foreach ($meta_data as $key => $value) {
            update_post_meta($post_id, "_khm_seo_{$platform}_{$key}", sanitize_text_field($value));
        }
        
        // Refresh preview after updating
        return $this->refresh_preview($post_id, $platform);
    }
    
    /**
     * Get all platforms
     */
    public function get_platforms() {
        return $this->platforms;
    }
    
    /**
     * Get platform configuration
     */
    public function get_platform_config($platform) {
        return isset($this->platforms[$platform]) ? $this->platforms[$platform] : false;
    }
    
    // AJAX Handlers
    
    /**
     * AJAX handler for generating preview
     */
    public function ajax_generate_preview() {
        check_ajax_referer('khm_social_preview_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Unauthorized access', 'khm-seo'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $platform = sanitize_text_field($_POST['platform'] ?? 'all');
        
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'khm-seo'));
        }
        
        $preview = $this->generate_preview($post_id, $platform);
        
        if ($preview) {
            wp_send_json_success($preview);
        } else {
            wp_send_json_error(__('Failed to generate preview', 'khm-seo'));
        }
    }
    
    /**
     * AJAX handler for refreshing preview
     */
    public function ajax_refresh_preview() {
        check_ajax_referer('khm_social_preview_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Unauthorized access', 'khm-seo'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $platform = sanitize_text_field($_POST['platform'] ?? 'all');
        
        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID', 'khm-seo'));
        }
        
        $preview = $this->refresh_preview($post_id, $platform);
        
        if ($preview) {
            wp_send_json_success($preview);
        } else {
            wp_send_json_error(__('Failed to refresh preview', 'khm-seo'));
        }
    }
    
    /**
     * AJAX handler for updating social meta
     */
    public function ajax_update_meta() {
        check_ajax_referer('khm_social_preview_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Unauthorized access', 'khm-seo'));
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $meta_data = $_POST['meta_data'] ?? [];
        
        if (!$post_id || !$platform) {
            wp_send_json_error(__('Invalid parameters', 'khm-seo'));
        }
        
        $updated_preview = $this->update_social_meta($post_id, $platform, $meta_data);
        
        if ($updated_preview) {
            wp_send_json_success($updated_preview);
        } else {
            wp_send_json_error(__('Failed to update meta data', 'khm-seo'));
        }
    }
    
    /**
     * Add preview meta to head for frontend testing
     */
    public function add_preview_meta() {
        if (!is_singular()) {
            return;
        }
        
        $post_id = function_exists('get_the_ID') ? get_the_ID() : get_queried_object_id();
        if (!$post_id) {
            return;
        }
        
        echo "<!-- KHM SEO Social Preview Active -->\n";
        echo "<script>window.khmSocialPreview = {postId: {$post_id}, active: true};</script>\n";
    }
}