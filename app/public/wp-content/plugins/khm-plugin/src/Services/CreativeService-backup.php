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
        // Sanitize data
        $creative_data = array(
            'name' => sanitize_text_field($data['name']),
            'type' => sanitize_text_field($data['type']),
            'content' => wp_kses_post($data['content']),
            'image_url' => esc_url_raw($data['image_url'] ?? ''),
            'alt_text' => sanitize_text_field($data['alt_text'] ?? ''),
            'landing_url' => esc_url_raw($data['landing_url'] ?? ''),
            'dimensions' => sanitize_text_field($data['dimensions'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => 'active',
            'created_at' => current_time('mysql')
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
                    $update_data[$field] = esc_url_raw($data[$field]);
                } elseif ($field === 'content') {
                    $update_data[$field] = wp_kses_post($data[$field]);
                } elseif ($field === 'description') {
                    $update_data[$field] = sanitize_textarea_field($data[$field]);
                } else {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
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
            require_once plugin_dir_path(__FILE__) . 'AffiliateService.php';
        }
        
        $affiliate_service = new KHM_AffiliateService();
        $creative = $this->get_creative($creative_id);
        
        if (!$creative) {
            return false;
        }
        
        // Use creative's landing URL or generate one
        $base_url = !empty($creative->landing_url) ? $creative->landing_url : home_url();
        
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
            'action' => sanitize_text_field($action),
            'platform' => sanitize_text_field($platform),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
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
            $class .= ' ' . sanitize_html_class($options['css_class']);
        }
        
        $html = '<div class="' . esc_attr($class) . '">';
        
        if ($affiliate_url) {
            $html .= '<a href="' . esc_url($affiliate_url) . '" ' . $target . '>';
        }
        
        if (!empty($creative->image_url)) {
            $html .= '<img src="' . esc_url($creative->image_url) . '" alt="' . esc_attr($creative->alt_text) . '">';
        }
        
        if (!empty($creative->content)) {
            $html .= '<div class="khm-creative-content">' . wp_kses_post($creative->content) . '</div>';
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
            $class .= ' ' . sanitize_html_class($options['css_class']);
        }
        
        $html = '<div class="' . esc_attr($class) . '">';
        
        if ($affiliate_url) {
            $html .= '<a href="' . esc_url($affiliate_url) . '" ' . $target . '>';
        }
        
        $html .= wp_kses_post($creative->content);
        
        if ($affiliate_url) {
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render social creative (for sharing)
     */
    private function render_social_creative($creative, $affiliate_url, $options) {
        $class = 'khm-creative khm-creative-social';
        
        if (isset($options['css_class'])) {
            $class .= ' ' . sanitize_html_class($options['css_class']);
        }
        
        $html = '<div class="' . esc_attr($class) . '">';
        
        // Social sharing buttons
        $platforms = array('facebook', 'twitter', 'linkedin', 'pinterest');
        
        foreach ($platforms as $platform) {
            $share_url = $this->generate_social_share_url($platform, $affiliate_url, $creative->content);
            $html .= '<a href="' . esc_url($share_url) . '" target="_blank" class="khm-social-' . $platform . '">';
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
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
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
        ) {$this->db->get_charset_collate()};";
        
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
        ) {$this->db->get_charset_collate()};";
        
        dbDelta($sql_creatives);
        dbDelta($sql_usage);
    }
}
    
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'khm_';
    }
    
    /**
     * Get all available creatives for affiliates
     *
     * @param array $args
     * @return array
     */
    public function get_creatives($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => 'active',
            'type' => '', // banner, text, video, social
            'category' => '', // membership, article, general
            'limit' => 20,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        $table = $this->table_prefix . 'creatives';
        
        $where_clauses = ['1=1'];
        $where_values = [];
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['type'])) {
            $where_clauses[] = 'type = %s';
            $where_values[] = $args['type'];
        }
        
        if (!empty($args['category'])) {
            $where_clauses[] = 'category = %s';
            $where_values[] = $args['category'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $limit_sql = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC {$limit_sql}",
                ...$where_values
            );
        } else {
            $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC {$limit_sql}";
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get creative by ID
     *
     * @param int $creative_id
     * @return object|null
     */
    public function get_creative($creative_id) {
        global $wpdb;
        
        $table = $this->table_prefix . 'creatives';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'active'",
            $creative_id
        ));
    }
    
    /**
     * Create new creative
     *
     * @param array $data
     * @return int|false
     */
    public function create_creative($data) {
        global $wpdb;
        
        $table = $this->table_prefix . 'creatives';
        
        $creative_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'type' => sanitize_text_field($data['type']),
            'category' => sanitize_text_field($data['category']),
            'content' => wp_kses_post($data['content']),
            'image_url' => esc_url_raw($data['image_url'] ?? ''),
            'alt_text' => sanitize_text_field($data['alt_text'] ?? ''),
            'landing_url' => esc_url_raw($data['landing_url'] ?? ''),
            'dimensions' => sanitize_text_field($data['dimensions'] ?? ''),
            'file_size' => intval($data['file_size'] ?? 0),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table, $creative_data, [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'
        ]);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update creative
     *
     * @param int $creative_id
     * @param array $data
     * @return bool
     */
    public function update_creative($creative_id, $data) {
        global $wpdb;
        
        $table = $this->table_prefix . 'creatives';
        
        $update_data = [
            'updated_at' => current_time('mysql')
        ];
        
        $allowed_fields = ['name', 'description', 'type', 'category', 'content', 
                          'image_url', 'alt_text', 'landing_url', 'dimensions', 'status'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                switch ($field) {
                    case 'image_url':
                    case 'landing_url':
                        $update_data[$field] = esc_url_raw($data[$field]);
                        break;
                    case 'content':
                        $update_data[$field] = wp_kses_post($data[$field]);
                        break;
                    case 'description':
                        $update_data[$field] = sanitize_textarea_field($data[$field]);
                        break;
                    default:
                        $update_data[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        
        return $wpdb->update($table, $update_data, ['id' => $creative_id], null, ['%d']);
    }
    
    /**
     * Delete creative
     *
     * @param int $creative_id
     * @return bool
     */
    public function delete_creative($creative_id) {
        global $wpdb;
        
        $table = $this->table_prefix . 'creatives';
        
        // Soft delete by setting status to 'deleted'
        return $wpdb->update(
            $table,
            ['status' => 'deleted', 'updated_at' => current_time('mysql')],
            ['id' => $creative_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Generate affiliate URL for creative
     *
     * @param int $creative_id
     * @param int $member_id
     * @return string|false
     */
    public function generate_creative_affiliate_url($creative_id, $member_id) {
        $creative = $this->get_creative($creative_id);
        
        if (!$creative || empty($creative->landing_url)) {
            return false;
        }
        
        // Use existing affiliate service to generate URL
        $affiliate_service = new AffiliateService();
        $affiliate_url = $affiliate_service->generate_affiliate_url(
            $member_id, 
            $creative->landing_url, 
            0, // No specific post ID for creatives
            ['creative_id' => $creative_id]
        );
        
        // Track creative usage
        $this->track_creative_usage($creative_id, $member_id);
        
        return $affiliate_url;
    }
    
    /**
     * Track creative usage by affiliate
     *
     * @param int $creative_id
     * @param int $member_id
     * @return bool
     */
    private function track_creative_usage($creative_id, $member_id) {
        global $wpdb;
        
        $table = $this->table_prefix . 'creative_usage';
        
        return $wpdb->insert($table, [
            'creative_id' => $creative_id,
            'member_id' => $member_id,
            'usage_type' => 'affiliate_generation',
            'used_at' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ], ['%d', '%d', '%s', '%s', '%s', '%s']);
    }
    
    /**
     * Get creative performance stats
     *
     * @param int $creative_id
     * @param int $days
     * @return array
     */
    public function get_creative_performance($creative_id, $days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get usage count
        $usage_table = $this->table_prefix . 'creative_usage';
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$usage_table} 
             WHERE creative_id = %d AND used_at >= %s",
            $creative_id, $date_from
        ));
        
        // Get click count (from affiliate clicks where creative was used)
        $clicks_table = $this->table_prefix . 'affiliate_clicks';
        $click_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$clicks_table} 
             WHERE creative_id = %d AND click_time >= %s",
            $creative_id, $date_from
        ));
        
        // Get conversion count
        $conversions_table = $this->table_prefix . 'affiliate_conversions';
        $conversion_data = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as conversions, SUM(commission_amount) as total_commissions
             FROM {$conversions_table} 
             WHERE creative_id = %d AND converted_at >= %s",
            $creative_id, $date_from
        ));
        
        $conversions = $conversion_data->conversions ?? 0;
        $total_commissions = $conversion_data->total_commissions ?? 0;
        
        return [
            'usage_count' => (int) $usage_count,
            'click_count' => (int) $click_count,
            'conversion_count' => (int) $conversions,
            'total_commissions' => (float) $total_commissions,
            'click_through_rate' => $usage_count > 0 ? round(($click_count / $usage_count) * 100, 2) : 0,
            'conversion_rate' => $click_count > 0 ? round(($conversions / $click_count) * 100, 2) : 0,
            'avg_commission' => $conversions > 0 ? round($total_commissions / $conversions, 2) : 0
        ];
    }
    
    /**
     * Get top performing creatives
     *
     * @param int $limit
     * @param int $days
     * @return array
     */
    public function get_top_performing_creatives($limit = 10, $days = 30) {
        $creatives = $this->get_creatives(['limit' => 100]); // Get more to rank
        $performance_data = [];
        
        foreach ($creatives as $creative) {
            $performance = $this->get_creative_performance($creative->id, $days);
            $performance_data[] = [
                'creative' => $creative,
                'performance' => $performance,
                'score' => $performance['conversion_count'] * 10 + $performance['click_count']
            ];
        }
        
        // Sort by performance score
        usort($performance_data, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return array_slice($performance_data, 0, $limit);
    }
    
    /**
     * Render creative for frontend display
     *
     * @param int $creative_id
     * @param int $member_id
     * @param array $options
     * @return string
     */
    public function render_creative($creative_id, $member_id = 0, $options = []) {
        $creative = $this->get_creative($creative_id);
        
        if (!$creative) {
            return '<p class="khm-error">Creative not found.</p>';
        }
        
        $affiliate_url = '';
        if ($member_id) {
            $affiliate_url = $this->generate_creative_affiliate_url($creative_id, $member_id);
        } else if (!empty($creative->landing_url)) {
            $affiliate_url = $creative->landing_url;
        }
        
        $output = '';
        
        switch ($creative->type) {
            case 'banner':
                $output = $this->render_banner_creative($creative, $affiliate_url, $options);
                break;
            case 'text':
                $output = $this->render_text_creative($creative, $affiliate_url, $options);
                break;
            case 'social':
                $output = $this->render_social_creative($creative, $affiliate_url, $options);
                break;
            case 'video':
                $output = $this->render_video_creative($creative, $affiliate_url, $options);
                break;
            default:
                $output = $this->render_generic_creative($creative, $affiliate_url, $options);
        }
        
        return $output;
    }
    
    /**
     * Render banner creative
     */
    private function render_banner_creative($creative, $affiliate_url, $options) {
        $target = !empty($options['new_window']) ? 'target="_blank"' : '';
        $class = 'khm-creative khm-creative-banner khm-creative-' . $creative->id;
        
        if (!empty($options['css_class'])) {
            $class .= ' ' . sanitize_html_class($options['css_class']);
        }
        
        $html = '<div class="' . esc_attr($class) . '">';
        
        if ($affiliate_url) {
            $html .= '<a href="' . esc_url($affiliate_url) . '" ' . $target . '>';
        }
        
        if ($creative->image_url) {
            $html .= '<img src="' . esc_url($creative->image_url) . '" alt="' . esc_attr($creative->alt_text) . '">';
        }
        
        if ($creative->content) {
            $html .= '<div class="khm-creative-content">' . wp_kses_post($creative->content) . '</div>';
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
        $target = !empty($options['new_window']) ? 'target="_blank"' : '';
        $class = 'khm-creative khm-creative-text khm-creative-' . $creative->id;
        
        if (!empty($options['css_class'])) {
            $class .= ' ' . sanitize_html_class($options['css_class']);
        }
        
        $html = '<div class="' . esc_attr($class) . '">';
        
        if ($affiliate_url) {
            $html .= '<a href="' . esc_url($affiliate_url) . '" ' . $target . '>';
        }
        
        $html .= wp_kses_post($creative->content);
        
        if ($affiliate_url) {
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render social creative
     */
    private function render_social_creative($creative, $affiliate_url, $options) {
        $class = 'khm-creative khm-creative-social khm-creative-' . $creative->id;
        
        if (!empty($options['css_class'])) {
            $class .= ' ' . sanitize_html_class($options['css_class']);
        }
        
        $html = '<div class="' . esc_attr($class) . '">';
        $html .= '<div class="khm-social-share-buttons">';
        
        // Generate social sharing URLs with affiliate link
        $platforms = ['facebook', 'twitter', 'linkedin', 'pinterest'];
        
        foreach ($platforms as $platform) {
            $share_url = $this->generate_social_share_url($platform, $affiliate_url, $creative->content);
            $html .= '<a href="' . esc_url($share_url) . '" target="_blank" class="khm-social-' . $platform . '">';
            $html .= ucfirst($platform);
            $html .= '</a>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render video creative
     */
    private function render_video_creative($creative, $affiliate_url, $options) {
        $class = 'khm-creative khm-creative-video khm-creative-' . $creative->id;
        
        $html = '<div class="' . esc_attr($class) . '">';
        
        if ($creative->image_url && strpos($creative->image_url, 'youtube.com') !== false) {
            // YouTube video
            $video_id = $this->extract_youtube_id($creative->image_url);
            $html .= '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe>';
        } else if ($creative->content) {
            // HTML5 video or other embed
            $html .= wp_kses_post($creative->content);
        }
        
        if ($affiliate_url) {
            $html .= '<div class="khm-video-cta">';
            $html .= '<a href="' . esc_url($affiliate_url) . '" target="_blank" class="khm-btn khm-btn-primary">Learn More</a>';
            $html .= '</div>';
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
    private function generate_social_share_url($platform, $url, $text = '') {
        $text = strip_tags($text);
        
        switch ($platform) {
            case 'facebook':
                return 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($url);
            case 'twitter':
                return 'https://twitter.com/intent/tweet?url=' . urlencode($url) . '&text=' . urlencode($text);
            case 'linkedin':
                return 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($url);
            case 'pinterest':
                return 'https://pinterest.com/pin/create/button/?url=' . urlencode($url) . '&description=' . urlencode($text);
            default:
                return $url;
        }
    }
    
    /**
     * Extract YouTube video ID
     */
    private function extract_youtube_id($url) {
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches);
        return $matches[1] ?? '';
    }
    
    /**
     * Create database tables for creative system
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $this->table_prefix;
        
        // Creatives table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$table_prefix}creatives (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            type varchar(50) NOT NULL DEFAULT 'banner',
            category varchar(50) NOT NULL DEFAULT 'general',
            content longtext,
            image_url varchar(500),
            alt_text varchar(255),
            landing_url varchar(500),
            dimensions varchar(50),
            file_size int(11) DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY type (type),
            KEY category (category),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Creative usage tracking table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$table_prefix}creative_usage (
            id int(11) NOT NULL AUTO_INCREMENT,
            creative_id int(11) NOT NULL,
            member_id int(11) NOT NULL,
            usage_type varchar(50) NOT NULL DEFAULT 'affiliate_generation',
            used_at datetime NOT NULL,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY creative_id (creative_id),
            KEY member_id (member_id),
            KEY used_at (used_at),
            KEY usage_type (usage_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        
        // Add creative_id column to existing affiliate tracking tables
        $this->add_creative_tracking_columns();
    }
    
    /**
     * Add creative tracking to existing affiliate tables
     */
    private function add_creative_tracking_columns() {
        global $wpdb;
        
        $tables = [
            $this->table_prefix . 'affiliate_clicks',
            $this->table_prefix . 'affiliate_conversions',
            $this->table_prefix . 'affiliate_generations'
        ];
        
        foreach ($tables as $table) {
            $column_exists = $wpdb->get_results($wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                'creative_id'
            ));
            
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN creative_id int(11) DEFAULT NULL AFTER post_id");
                $wpdb->query("ALTER TABLE {$table} ADD KEY creative_id (creative_id)");
            }
        }
    }
}