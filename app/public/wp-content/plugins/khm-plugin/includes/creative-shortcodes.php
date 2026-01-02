<?php
/**
 * KHM Creative Shortcodes
 * 
 * WordPress shortcodes for displaying creative materials with affiliate tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Creative_Shortcodes {
    
    private $creative_service;
    
    public function __construct() {
        // Load CreativeService
        require_once dirname(__FILE__) . '/../src/Services/CreativeService.php';
        $this->creative_service = new KHM_CreativeService();
        
        // Register shortcodes
        add_shortcode('khm_creative', array($this, 'render_creative_shortcode'));
        add_shortcode('khm_creative_list', array($this, 'render_creative_list_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
    }
    
    /**
     * Enqueue frontend styles
     */
    public function enqueue_frontend_styles() {
        wp_add_inline_style('wp-block-library', $this->get_creative_css());
    }
    
    /**
     * Render single creative shortcode
     * 
     * Usage: [khm_creative id="1" member_id="123" platform="website" new_window="true" css_class="my-custom-class"]
     */
    public function render_creative_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'member_id' => 0,
            'platform' => 'website',
            'new_window' => 'false',
            'css_class' => ''
        ), $atts);
        
        $creative_id = intval($atts['id']);
        $member_id = intval($atts['member_id']);
        
        if (!$creative_id) {
            return '<p><em>Error: Creative ID is required</em></p>';
        }
        
        // Auto-detect member ID if not provided
        if (!$member_id && is_user_logged_in()) {
            $member_id = get_current_user_id();
        }
        
        $options = array(
            'platform' => sanitize_text_field($atts['platform']),
            'new_window' => ($atts['new_window'] === 'true'),
            'css_class' => sanitize_html_class($atts['css_class'])
        );
        
        return $this->creative_service->render_creative($creative_id, $member_id, $options);
    }
    
    /**
     * Render creative list shortcode
     * 
     * Usage: [khm_creative_list type="banner" limit="5" member_id="123" show_title="true"]
     */
    public function render_creative_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => '',
            'limit' => 10,
            'member_id' => 0,
            'show_title' => 'false',
            'columns' => 1,
            'platform' => 'website'
        ), $atts);
        
        $member_id = intval($atts['member_id']);
        
        // Auto-detect member ID if not provided
        if (!$member_id && is_user_logged_in()) {
            $member_id = get_current_user_id();
        }
        
        $args = array(
            'limit' => intval($atts['limit']),
            'status' => 'active'
        );
        
        if (!empty($atts['type'])) {
            $args['type'] = sanitize_text_field($atts['type']);
        }
        
        $creatives = $this->creative_service->get_creatives($args);
        
        if (empty($creatives)) {
            return '<p><em>No creatives found</em></p>';
        }
        
        $columns = max(1, intval($atts['columns']));
        $show_title = ($atts['show_title'] === 'true');
        $platform = sanitize_text_field($atts['platform']);
        
        $output = '<div class="khm-creative-grid khm-creative-grid-' . $columns . '">';
        
        foreach ($creatives as $creative) {
            $output .= '<div class="khm-creative-item">';
            
            if ($show_title) {
                $output .= '<h4 class="khm-creative-title">' . esc_html($creative->name) . '</h4>';
            }
            
            $options = array(
                'platform' => $platform,
                'new_window' => true
            );
            
            $output .= $this->creative_service->render_creative($creative->id, $member_id, $options);
            
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get CSS for creative display
     */
    private function get_creative_css() {
        return "
        .khm-creative {
            display: block;
            margin: 10px 0;
            text-decoration: none;
            transition: opacity 0.3s ease;
        }
        
        .khm-creative:hover {
            opacity: 0.8;
        }
        
        .khm-creative-banner {
            text-align: center;
        }
        
        .khm-creative-banner img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }
        
        .khm-creative-text {
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #007cba;
            border-radius: 4px;
        }
        
        .khm-creative-text:hover {
            background: #e9ecef;
        }
        
        .khm-creative-social {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 15px 0;
        }
        
        .khm-creative-social a {
            padding: 8px 16px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background 0.3s ease;
        }
        
        .khm-creative-social a:hover {
            background: #005a87;
            color: white;
        }
        
        .khm-social-facebook { background: #1877f2; }
        .khm-social-twitter { background: #1da1f2; }
        .khm-social-linkedin { background: #0077b5; }
        .khm-social-pinterest { background: #bd081c; }
        
        .khm-creative-grid {
            display: grid;
            gap: 20px;
            margin: 20px 0;
        }
        
        .khm-creative-grid-1 { grid-template-columns: 1fr; }
        .khm-creative-grid-2 { grid-template-columns: repeat(2, 1fr); }
        .khm-creative-grid-3 { grid-template-columns: repeat(3, 1fr); }
        .khm-creative-grid-4 { grid-template-columns: repeat(4, 1fr); }
        
        @media (max-width: 768px) {
            .khm-creative-grid-2,
            .khm-creative-grid-3,
            .khm-creative-grid-4 {
                grid-template-columns: 1fr;
            }
            
            .khm-creative-social {
                flex-wrap: wrap;
            }
        }
        
        .khm-creative-item {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 15px;
            background: white;
            transition: box-shadow 0.3s ease;
        }
        
        .khm-creative-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .khm-creative-title {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #333;
            border-bottom: 1px solid #e1e5e9;
            padding-bottom: 10px;
        }
        
        .khm-creative-content {
            margin-top: 10px;
        }
        ";
    }
}

// Initialize shortcodes
new KHM_Creative_Shortcodes();