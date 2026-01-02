<?php
/**
 * KHM Creative Materials System
 * 
 * Professional marketing materials management for affiliates
 * Enhanced version inspired by SliceWP but integrated with KHM's superior affiliate system
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_CreativeService {
    
    private $db;
    private $table_name;
    private $usage_table;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name = $wpdb->prefix . 'khm_creatives';
        $this->usage_table = $wpdb->prefix . 'khm_creative_usage';
    }
    
    /**
     * Initialize CreativeService - Create database tables
     */
    public function init() {
        $this->create_tables();
    }
    
    /**
     * Get creatives with filtering and pagination
     */
    public function get_creatives($args = array()) {
        $defaults = array(
            'type' => '',
            'status' => 'active',
            'limit' => 20,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        // Merge with defaults
        $args = array_merge($defaults, $args);
        
        $where_conditions = array("status = %s");
        $values = array($args['status']);
        
        if (!empty($args['type'])) {
            $where_conditions[] = "type = %s";
            $values[] = $args['type'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = $this->db->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE {$where_clause} 
             ORDER BY {$args['order_by']} {$args['order']} 
             LIMIT %d OFFSET %d",
            array_merge($values, array($args['limit'], $args['offset']))
        );
        
        return $this->db->get_results($sql);
    }
    
    /**
     * Get single creative by ID
     */
    public function get_creative($id) {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );
    }
    
    /**
     * Create new creative
     */
    public function create_creative($data) {
        // Sanitize data using WordPress functions
        $creative_data = array(
            'name' => $this->sanitize_text($data['name']),
            'type' => $this->sanitize_text($data['type']),
            'content' => $this->sanitize_html($data['content']),
            'image_url' => $this->sanitize_url($data['image_url'] ?? ''),
            'alt_text' => $this->sanitize_text($data['alt_text'] ?? ''),
            'landing_url' => $this->sanitize_url($data['landing_url'] ?? ''),
            'dimensions' => $this->sanitize_text($data['dimensions'] ?? ''),
            'description' => $this->sanitize_textarea($data['description'] ?? ''),
            'status' => 'active',
            'created_at' => $this->current_datetime()
        );
        
        $result = $this->db->insert($this->table_name, $creative_data);
        
        if ($result === false) {
            return false;
        }
        
        return $this->db->insert_id;
    }
    
    /**
     * Update creative
     */
    public function update_creative($id, $data) {
        $creative = $this->get_creative($id);
        if (!$creative) {
            return false;
        }
        
        $update_data = array();
        
        // Only update provided fields
        $allowed_fields = array('name', 'content', 'image_url', 'alt_text', 'landing_url', 'dimensions', 'description', 'status');
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, array('image_url', 'landing_url'))) {
                    $update_data[$field] = $this->sanitize_url($data[$field]);
                } elseif ($field === 'content') {
                    $update_data[$field] = $this->sanitize_html($data[$field]);
                } elseif ($field === 'description') {
                    $update_data[$field] = $this->sanitize_textarea($data[$field]);
                } else {
                    $update_data[$field] = $this->sanitize_text($data[$field]);
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = $this->current_datetime();
        
        return $this->db->update(
            $this->table_name,
            $update_data,
            array('id' => $id)
        );
    }
    
    /**
     * Delete creative
     */
    public function delete_creative($id) {
        return $this->db->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Generate affiliate URL for creative
     */
    public function generate_creative_affiliate_url($creative_id, $member_id, $platform = '') {
        // Get AffiliateService
        if (!class_exists('KHM_AffiliateService')) {
            require_once dirname(__FILE__) . '/AffiliateService.php';
        }
        
        $affiliate_service = new KHM_AffiliateService();
        $creative = $this->get_creative($creative_id);
        
        if (!$creative) {
            return false;
        }
        
        // Use creative's landing URL or generate one
        $base_url = !empty($creative->landing_url) ? $creative->landing_url : $this->get_home_url();
        
        // Add creative context
        $context = array(
            'creative_id' => $creative_id,
            'creative_type' => $creative->type,
            'platform' => $platform
        );
        
        return $affiliate_service->generate_affiliate_url($member_id, $base_url, $context);
    }
    
    /**
     * Track creative usage
     */
    public function track_usage($creative_id, $member_id, $action = 'view', $platform = '') {
        $usage_data = array(
            'creative_id' => $creative_id,
            'member_id' => $member_id,
            'action' => $this->sanitize_text($action),
            'platform' => $this->sanitize_text($platform),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => $this->current_datetime()
        );
        
        return $this->db->insert($this->usage_table, $usage_data);
    }
    
    /**
     * Get creative performance analytics
     */
    public function get_creative_performance($creative_id, $days = 30) {
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get usage stats
        $stats = $this->db->get_row(
            $this->db->prepare(
                "SELECT 
                    COUNT(*) as total_views,
                    COUNT(DISTINCT member_id) as unique_users,
                    COUNT(CASE WHEN action = 'click' THEN 1 END) as clicks,
                    COUNT(CASE WHEN action = 'conversion' THEN 1 END) as conversions
                 FROM {$this->usage_table} 
                 WHERE creative_id = %d AND created_at >= %s",
                $creative_id, $start_date
            )
        );
        
        // Calculate CTR and conversion rate
        $ctr = $stats->total_views > 0 ? ($stats->clicks / $stats->total_views) * 100 : 0;
        $conversion_rate = $stats->clicks > 0 ? ($stats->conversions / $stats->clicks) * 100 : 0;
        
        return array(
            'views' => $stats->total_views,
            'unique_users' => $stats->unique_users,
            'clicks' => $stats->clicks,
            'conversions' => $stats->conversions,
            'ctr' => round($ctr, 2),
            'conversion_rate' => round($conversion_rate, 2),
            'period_days' => $days
        );
    }
    
    /**
     * Render creative for display
     */
    public function render_creative($creative_id, $member_id, $options = array()) {
        $creative = $this->get_creative($creative_id);
        
        if (!$creative || $creative->status !== 'active') {
            return '';
        }
        
        // Track view
        $this->track_usage($creative_id, $member_id, 'view', $options['platform'] ?? '');
        
        // Generate affiliate URL
        $affiliate_url = $this->generate_creative_affiliate_url(
            $creative_id, 
            $member_id, 
            $options['platform'] ?? ''
        );
        
        // Render based on type
        switch ($creative->type) {
            case 'banner':
                return $this->render_banner_creative($creative, $affiliate_url, $options);
            case 'text':
                return $this->render_text_creative($creative, $affiliate_url, $options);
            case 'video':
                return $this->render_video_creative($creative, $affiliate_url, $options);
            case 'social':
                return $this->render_social_creative($creative, $affiliate_url, $options);
            default:
                return $this->render_generic_creative($creative, $affiliate_url, $options);
        }
    }
    
    /**
     * Render banner creative
     */
    private function render_banner_creative($creative, $affiliate_url, $options) {
        $class = 'khm-creative khm-creative-banner';
        $target = isset($options['new_window']) && $options['new_window'] ? 'target="_blank"' : '';
        
        if (isset($options['css_class'])) {
            $class .= ' ' . $this->sanitize_html_class($options['css_class']);
        }
        
        $html = '<div class="' . $this->escape_attr($class) . '">';
        
        if ($affiliate_url) {
            $html .= '<a href="' . $this->escape_url($affiliate_url) . '" ' . $target . '>';
        }
        
        if (!empty($creative->image_url)) {
            $html .= '<img src="' . $this->escape_url($creative->image_url) . '" alt="' . $this->escape_attr($creative->alt_text) . '">';
        }
        
        if (!empty($creative->content)) {
            $html .= '<div class="khm-creative-content">' . $this->sanitize_html($creative->content) . '</div>';
        }
        
        if ($affiliate_url) {
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render text creative
     */
    private function render_text_creative($creative, $affiliate_url, $options) {
        $class = 'khm-creative khm-creative-text';
        $target = isset($options['new_window']) && $options['new_window'] ? 'target="_blank"' : '';
        
        if (isset($options['css_class'])) {
            $class .= ' ' . $this->sanitize_html_class($options['css_class']);
        }
        
        $html = '<div class="' . $this->escape_attr($class) . '">';
        
        if ($affiliate_url) {
            $html .= '<a href="' . $this->escape_url($affiliate_url) . '" ' . $target . '>';
        }
        
        $html .= $this->sanitize_html($creative->content);
        
        if ($affiliate_url) {
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render video creative
     */
    private function render_video_creative($creative, $affiliate_url, $options) {
        return $this->render_text_creative($creative, $affiliate_url, $options);
    }
    
    /**
     * Render social creative (for sharing)
     */
    private function render_social_creative($creative, $affiliate_url, $options) {
        $class = 'khm-creative khm-creative-social';
        
        if (isset($options['css_class'])) {
            $class .= ' ' . $this->sanitize_html_class($options['css_class']);
        }
        
        $html = '<div class="' . $this->escape_attr($class) . '">';
        
        // Social sharing buttons
        $platforms = array('facebook', 'twitter', 'linkedin', 'pinterest');
        
        foreach ($platforms as $platform) {
            $share_url = $this->generate_social_share_url($platform, $affiliate_url, $creative->content);
            $html .= '<a href="' . $this->escape_url($share_url) . '" target="_blank" class="khm-social-' . $platform . '">';
            $html .= ucfirst($platform);
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render generic creative
     */
    private function render_generic_creative($creative, $affiliate_url, $options) {
        return $this->render_text_creative($creative, $affiliate_url, $options);
    }
    
    /**
     * Generate social share URL
     */
    private function generate_social_share_url($platform, $url, $text) {
        $encoded_url = urlencode($url);
        $encoded_text = urlencode($text);
        
        switch ($platform) {
            case 'facebook':
                return "https://www.facebook.com/sharer/sharer.php?u={$encoded_url}";
            case 'twitter':
                return "https://twitter.com/intent/tweet?url={$encoded_url}&text={$encoded_text}";
            case 'linkedin':
                return "https://www.linkedin.com/sharing/share-offsite/?url={$encoded_url}";
            case 'pinterest':
                return "https://pinterest.com/pin/create/button/?url={$encoded_url}&description={$encoded_text}";
            default:
                return $url;
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        // Creatives table
        $sql_creatives = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type enum('banner','text','video','social','other') NOT NULL DEFAULT 'banner',
            content longtext,
            image_url varchar(500),
            alt_text varchar(255),
            landing_url varchar(500),
            dimensions varchar(50),
            description text,
            status enum('active','inactive','archived') NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime,
            PRIMARY KEY (id),
            KEY type_status (type, status),
            KEY created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        // Usage tracking table
        $sql_usage = "CREATE TABLE IF NOT EXISTS {$this->usage_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            creative_id bigint(20) unsigned NOT NULL,
            member_id bigint(20) unsigned NOT NULL,
            action enum('view','click','conversion') NOT NULL DEFAULT 'view',
            platform varchar(50),
            ip_address varchar(45),
            user_agent text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY creative_member (creative_id, member_id),
            KEY action_created (action, created_at),
            KEY platform (platform)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        // Execute table creation
        $this->db->query($sql_creatives);
        $this->db->query($sql_usage);
    }
    
    // Helper methods for WordPress function compatibility
    
    private function sanitize_text($text) {
        return htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8');
    }
    
    private function sanitize_textarea($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    private function sanitize_html($html) {
        // Basic HTML sanitization
        $allowed_tags = '<p><br><strong><em><a><img><div><span><h1><h2><h3><h4><h5><h6><ul><ol><li>';
        return strip_tags($html, $allowed_tags);
    }
    
    private function sanitize_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    private function sanitize_html_class($class) {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $class);
    }
    
    private function escape_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    private function escape_url($url) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
    
    private function current_datetime() {
        return date('Y-m-d H:i:s');
    }
    
    private function get_home_url() {
        return isset($_SERVER['HTTP_HOST']) ? 
            'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] : 
            'https://example.com';
    }
}