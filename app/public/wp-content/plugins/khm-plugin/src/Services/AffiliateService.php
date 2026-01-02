<?php
/**
 * Affiliate Tracking Service
 * 
 * Handles member affiliate URL generation and tracking
 */

namespace KHM\Services;

class AffiliateService {
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'khm_';
    }
    
    /**
     * Generate affiliate URL for member
     *
     * @param int $member_id
     * @param string $base_url
     * @param int $post_id
     * @return string
     */
    public function generate_affiliate_url(int $member_id, string $base_url, int $post_id = 0): string {
        // Generate unique affiliate code for member if not exists
        $affiliate_code = $this->get_or_create_affiliate_code($member_id);
        
        // Create tracking parameters
        $params = [
            'ref' => $affiliate_code,
            'utm_source' => 'affiliate',
            'utm_medium' => 'member_share',
            'utm_campaign' => 'article_' . $post_id,
            'utm_content' => $member_id
        ];
        
        // Add parameters to URL
        $separator = strpos($base_url, '?') !== false ? '&' : '?';
        $affiliate_url = $base_url . $separator . http_build_query($params);
        
        // Log the affiliate URL generation
        $this->log_affiliate_generation($member_id, $post_id, $affiliate_url);
        
        return $affiliate_url;
    }
    
    /**
     * Get or create affiliate code for member
     *
     * @param int $member_id
     * @return string
     */
    private function get_or_create_affiliate_code(int $member_id): string {
        global $wpdb;
        
        // Check if affiliate code exists
        $table = $this->table_prefix . 'affiliate_codes';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT affiliate_code FROM {$table} WHERE member_id = %d",
            $member_id
        ));
        
        if ($existing) {
            return $existing;
        }
        
        // Generate new affiliate code
        $affiliate_code = $this->generate_unique_code();
        
        // Store the code
        $wpdb->insert(
            $table,
            [
                'member_id' => $member_id,
                'affiliate_code' => $affiliate_code,
                'created_at' => current_time('mysql'),
                'status' => 'active'
            ],
            ['%d', '%s', '%s', '%s']
        );
        
        return $affiliate_code;
    }
    
    /**
     * Generate unique affiliate code
     *
     * @return string
     */
    private function generate_unique_code(): string {
        global $wpdb;
        $table = $this->table_prefix . 'affiliate_codes';
        
        do {
            // Generate 8-character code
            $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
            
            // Check if unique
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE affiliate_code = %s",
                $code
            ));
        } while ($exists > 0);
        
        return $code;
    }
    
    /**
     * Track affiliate link click
     *
     * @param string $affiliate_code
     * @param int $post_id
     * @param string $visitor_ip
     * @param string $user_agent
     * @return bool
     */
    public function track_click(string $affiliate_code, int $post_id, string $visitor_ip, string $user_agent): bool {
        global $wpdb;
        
        // Get member ID from affiliate code
        $member_id = $this->get_member_by_code($affiliate_code);
        if (!$member_id) {
            return false;
        }
        
        // Check for duplicate clicks (same IP within 24 hours)
        $table = $this->table_prefix . 'affiliate_clicks';
        $recent_click = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
             WHERE affiliate_code = %s 
             AND post_id = %d 
             AND visitor_ip = %s 
             AND clicked_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $affiliate_code,
            $post_id,
            $visitor_ip
        ));
        
        // Don't count duplicate clicks
        if ($recent_click > 0) {
            return false;
        }
        
        // Log the click
        $result = $wpdb->insert(
            $table,
            [
                'member_id' => $member_id,
                'affiliate_code' => $affiliate_code,
                'post_id' => $post_id,
                'visitor_ip' => $visitor_ip,
                'user_agent' => $user_agent,
                'clicked_at' => current_time('mysql'),
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'converted' => 0
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Track conversion (purchase/signup)
     *
     * @param string $affiliate_code
     * @param int $post_id
     * @param float $commission_amount
     * @param string $conversion_type
     * @return bool
     */
    public function track_conversion(string $affiliate_code, int $post_id, float $commission_amount, string $conversion_type = 'purchase'): bool {
        global $wpdb;
        
        $member_id = $this->get_member_by_code($affiliate_code);
        if (!$member_id) {
            return false;
        }
        
        // Update the most recent click to mark as converted
        $clicks_table = $this->table_prefix . 'affiliate_clicks';
        $wpdb->update(
            $clicks_table,
            ['converted' => 1, 'converted_at' => current_time('mysql')],
            [
                'affiliate_code' => $affiliate_code,
                'post_id' => $post_id,
                'converted' => 0
            ],
            ['%d', '%s'],
            ['%s', '%d', '%d']
        );
        
        // Log the conversion
        $conversions_table = $this->table_prefix . 'affiliate_conversions';
        $result = $wpdb->insert(
            $conversions_table,
            [
                'member_id' => $member_id,
                'affiliate_code' => $affiliate_code,
                'post_id' => $post_id,
                'conversion_type' => $conversion_type,
                'commission_amount' => $commission_amount,
                'converted_at' => current_time('mysql'),
                'status' => 'pending'
            ],
            ['%d', '%s', '%d', '%s', '%f', '%s', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get member by affiliate code
     *
     * @param string $affiliate_code
     * @return int|false
     */
    private function get_member_by_code(string $affiliate_code) {
        global $wpdb;
        
        $table = $this->table_prefix . 'affiliate_codes';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT member_id FROM {$table} WHERE affiliate_code = %s AND status = 'active'",
            $affiliate_code
        ));
    }
    
    /**
     * Get affiliate stats for member
     *
     * @param int $member_id
     * @param array $date_range
     * @return array
     */
    public function get_member_stats(int $member_id, array $date_range = []): array {
        global $wpdb;
        
        $where_date = '';
        if (!empty($date_range['start']) && !empty($date_range['end'])) {
            $where_date = $wpdb->prepare(
                " AND clicked_at BETWEEN %s AND %s",
                $date_range['start'],
                $date_range['end']
            );
        }
        
        // Get click stats
        $clicks_table = $this->table_prefix . 'affiliate_clicks';
        $clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$clicks_table} WHERE member_id = %d" . $where_date,
            $member_id
        ));
        
        $conversions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$clicks_table} WHERE member_id = %d AND converted = 1" . $where_date,
            $member_id
        ));
        
        // Get commission stats
        $conversions_table = $this->table_prefix . 'affiliate_conversions';
        $commission_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(commission_amount) as total_commissions,
                SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END) as paid_commissions,
                SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END) as pending_commissions
             FROM {$conversions_table} 
             WHERE member_id = %d" . str_replace('clicked_at', 'converted_at', $where_date),
            $member_id
        ));
        
        return [
            'clicks' => (int) $clicks,
            'conversions' => (int) $conversions,
            'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0,
            'total_commissions' => (float) ($commission_stats->total_commissions ?? 0),
            'paid_commissions' => (float) ($commission_stats->paid_commissions ?? 0),
            'pending_commissions' => (float) ($commission_stats->pending_commissions ?? 0)
        ];
    }
    
    /**
     * Log affiliate URL generation
     *
     * @param int $member_id
     * @param int $post_id
     * @param string $affiliate_url
     */
    private function log_affiliate_generation(int $member_id, int $post_id, string $affiliate_url): void {
        global $wpdb;
        
        $table = $this->table_prefix . 'affiliate_generations';
        $wpdb->insert(
            $table,
            [
                'member_id' => $member_id,
                'post_id' => $post_id,
                'affiliate_url' => $affiliate_url,
                'generated_at' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Create affiliate tables
     */
    public static function create_tables(): void {
        global $wpdb;
        $table_prefix = $wpdb->prefix . 'khm_';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Affiliate codes table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$table_prefix}affiliate_codes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            member_id bigint(20) NOT NULL,
            affiliate_code varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY affiliate_code (affiliate_code),
            KEY member_id (member_id)
        ) $charset_collate;";
        
        // Affiliate clicks table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$table_prefix}affiliate_clicks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            member_id bigint(20) NOT NULL,
            affiliate_code varchar(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            visitor_ip varchar(45) NOT NULL,
            user_agent text,
            clicked_at datetime NOT NULL,
            referrer text,
            converted tinyint(1) DEFAULT 0,
            converted_at datetime NULL,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY affiliate_code (affiliate_code),
            KEY post_id (post_id),
            KEY clicked_at (clicked_at)
        ) $charset_collate;";
        
        // Affiliate conversions table
        $sql3 = "CREATE TABLE IF NOT EXISTS {$table_prefix}affiliate_conversions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            member_id bigint(20) NOT NULL,
            affiliate_code varchar(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            conversion_type varchar(50) NOT NULL,
            commission_amount decimal(10,2) NOT NULL,
            converted_at datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            paid_at datetime NULL,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY affiliate_code (affiliate_code),
            KEY converted_at (converted_at)
        ) $charset_collate;";
        
        // Affiliate generations table (for tracking URL creation)
        $sql4 = "CREATE TABLE IF NOT EXISTS {$table_prefix}affiliate_generations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            member_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            affiliate_url text NOT NULL,
            generated_at datetime NOT NULL,
            ip_address varchar(45) NOT NULL,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY post_id (post_id),
            KEY generated_at (generated_at)
        ) $charset_collate;";
        
        // Social share tracking table
        $sql5 = "CREATE TABLE IF NOT EXISTS {$table_prefix}social_shares (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            platform varchar(50) NOT NULL,
            has_affiliate tinyint(1) DEFAULT 0,
            has_custom_message tinyint(1) DEFAULT 0,
            shared_at datetime NOT NULL,
            ip_address varchar(45) NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY platform (platform),
            KEY shared_at (shared_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);
    }
}