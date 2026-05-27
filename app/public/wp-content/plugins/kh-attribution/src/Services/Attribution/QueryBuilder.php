<?php
/**
 * KHM Attribution Query Builder
 * 
 * Optimized query building and execution for attribution analytics.
 * Focuses on performance and proper indexing for high-volume scenarios.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Query_Builder {
    
    private $wpdb;
    private $table_events;
    private $table_conversions;
    private $table_analytics;
    private $cache_manager;
    
    // Query optimization settings
    private $default_limit = 1000;
    private $max_limit = 10000;
    private $force_index_hints = true;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Set table names
        $this->table_events = $wpdb->prefix . 'khm_attribution_events';
        $this->table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        $this->table_analytics = $wpdb->prefix . 'khm_attribution_analytics';
        
        // Load cache manager if available
        if (class_exists('KHM_Attribution_Performance_Manager')) {
            $this->cache_manager = new KHM_Attribution_Performance_Manager();
        }
    }
    
    /**
     * Get attribution events with optimized queries
     */
    public function get_attribution_events($filters = array()) {
        $where_conditions = array();
        $params = array();
        
        // Build WHERE conditions
        if (!empty($filters['affiliate_id'])) {
            $where_conditions[] = "affiliate_id = %d";
            $params[] = $filters['affiliate_id'];
        }
        
        if (!empty($filters['session_id'])) {
            $where_conditions[] = "session_id = %s";
            $params[] = $filters['session_id'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "user_id = %d";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['utm_source'])) {
            $where_conditions[] = "utm_source = %s";
            $params[] = $filters['utm_source'];
        }
        
        if (!empty($filters['utm_medium'])) {
            $where_conditions[] = "utm_medium = %s";
            $params[] = $filters['utm_medium'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        // Handle attribution method filter
        if (!empty($filters['attribution_method'])) {
            if (is_array($filters['attribution_method'])) {
                $placeholders = implode(',', array_fill(0, count($filters['attribution_method']), '%s'));
                $where_conditions[] = "attribution_method IN ({$placeholders})";
                $params = array_merge($params, $filters['attribution_method']);
            } else {
                $where_conditions[] = "attribution_method = %s";
                $params[] = $filters['attribution_method'];
            }
        }
        
        // Only active (non-expired) events by default
        if (!isset($filters['include_expired']) || !$filters['include_expired']) {
            $where_conditions[] = "expires_at > NOW()";
        }
        
        // Build the query
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Add index hints for performance
        $index_hint = '';
        if ($this->force_index_hints) {
            if (!empty($filters['affiliate_id'])) {
                $index_hint = 'USE INDEX (idx_affiliate_performance)';
            } elseif (!empty($filters['session_id']) || !empty($filters['user_id'])) {
                $index_hint = 'USE INDEX (idx_session_user_lookup)';
            } elseif (!empty($filters['date_from']) || !empty($filters['date_to'])) {
                $index_hint = 'USE INDEX (idx_created_at)';
            }
        }
        
        // Handle ordering
        $order_by = $this->build_order_clause($filters['order_by'] ?? 'created_at DESC');
        
        // Handle limit
        $limit = min($filters['limit'] ?? $this->default_limit, $this->max_limit);
        $offset = $filters['offset'] ?? 0;
        
        $sql = "SELECT 
                    id, click_id, affiliate_id, session_id, user_id,
                    utm_source, utm_medium, utm_campaign, utm_content, utm_term,
                    ip_address, user_agent, referrer_url, landing_page,
                    screen_resolution, browser_language, timezone, fingerprint_hash,
                    created_at, expires_at, attribution_method
                FROM {$this->table_events} {$index_hint}
                {$where_clause}
                {$order_by}
                LIMIT %d OFFSET %d";
        
        $params[] = $limit;
        $params[] = $offset;
        
        // Use cached query if cache manager is available
        if ($this->cache_manager && empty($filters['no_cache'])) {
            $cache_key = 'events_' . md5(serialize($filters));
            return $this->cache_manager->execute_cached_query(
                $this->wpdb->prepare($sql, $params),
                $cache_key,
                $filters['cache_ttl'] ?? 1800
            );
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    /**
     * Get conversion data with attribution details
     */
    public function get_conversions_with_attribution($filters = array()) {
        $where_conditions = array();
        $params = array();
        
        // Build WHERE conditions for conversions table
        if (!empty($filters['affiliate_id'])) {
            $where_conditions[] = "c.affiliate_id = %d";
            $params[] = $filters['affiliate_id'];
        }
        
        if (!empty($filters['order_id'])) {
            $where_conditions[] = "c.order_id = %s";
            $params[] = $filters['order_id'];
        }
        
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '%s'));
                $where_conditions[] = "c.status IN ({$placeholders})";
                $params = array_merge($params, $filters['status']);
            } else {
                $where_conditions[] = "c.status = %s";
                $params[] = $filters['status'];
            }
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "c.created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "c.created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['min_commission'])) {
            $where_conditions[] = "c.commission_amount >= %f";
            $params[] = $filters['min_commission'];
        }
        
        if (!empty($filters['max_commission'])) {
            $where_conditions[] = "c.commission_amount <= %f";
            $params[] = $filters['max_commission'];
        }
        
        // Attribution confidence filter
        if (isset($filters['min_confidence'])) {
            $where_conditions[] = "c.attribution_confidence >= %f";
            $params[] = $filters['min_confidence'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Handle ordering
        $order_by = $this->build_order_clause($filters['order_by'] ?? 'c.created_at DESC');
        
        // Handle limit
        $limit = min($filters['limit'] ?? $this->default_limit, $this->max_limit);
        $offset = $filters['offset'] ?? 0;
        
        $sql = "SELECT 
                    c.id, c.order_id, c.click_id, c.affiliate_id,
                    c.order_value, c.commission_amount, c.commission_rate,
                    c.attribution_method, c.attribution_confidence, c.attribution_explanation,
                    c.multi_touch_data, c.created_at, c.processed_at, c.status,
                    e.utm_source, e.utm_medium, e.utm_campaign, e.utm_content, e.utm_term,
                    e.referrer_url, e.landing_page, e.ip_address, e.user_agent
                FROM {$this->table_conversions} c
                LEFT JOIN {$this->table_events} e ON c.click_id = e.click_id
                {$where_clause}
                {$order_by}
                LIMIT %d OFFSET %d";
        
        $params[] = $limit;
        $params[] = $offset;
        
        // Use cached query if available
        if ($this->cache_manager && empty($filters['no_cache'])) {
            $cache_key = 'conversions_' . md5(serialize($filters));
            return $this->cache_manager->execute_cached_query(
                $this->wpdb->prepare($sql, $params),
                $cache_key,
                $filters['cache_ttl'] ?? 1800
            );
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    /**
     * Get attribution analytics aggregated data
     */
    public function get_attribution_analytics($filters = array()) {
        $where_conditions = array();
        $params = array();
        $group_by_fields = array();
        $select_fields = array();
        
        // Base aggregation fields
        $select_fields[] = "SUM(clicks) as total_clicks";
        $select_fields[] = "SUM(conversions) as total_conversions";
        $select_fields[] = "SUM(commission_total) as total_commission";
        $select_fields[] = "AVG(CASE WHEN conversions > 0 THEN (conversions / clicks) * 100 ELSE 0 END) as conversion_rate";
        $select_fields[] = "AVG(CASE WHEN conversions > 0 THEN commission_total / conversions ELSE 0 END) as avg_commission_per_conversion";
        
        // Handle grouping
        $group_by = $filters['group_by'] ?? 'date';
        switch ($group_by) {
            case 'date':
                $select_fields[] = "date";
                $group_by_fields[] = "date";
                break;
                
            case 'affiliate':
                $select_fields[] = "affiliate_id";
                $group_by_fields[] = "affiliate_id";
                break;
                
            case 'source':
                $select_fields[] = "utm_source";
                $group_by_fields[] = "utm_source";
                break;
                
            case 'medium':
                $select_fields[] = "utm_medium";
                $group_by_fields[] = "utm_medium";
                break;
                
            case 'affiliate_source':
                $select_fields[] = "affiliate_id, utm_source";
                $group_by_fields[] = "affiliate_id, utm_source";
                break;
                
            case 'date_affiliate':
                $select_fields[] = "date, affiliate_id";
                $group_by_fields[] = "date, affiliate_id";
                break;
        }
        
        // Build WHERE conditions
        if (!empty($filters['affiliate_id'])) {
            if (is_array($filters['affiliate_id'])) {
                $placeholders = implode(',', array_fill(0, count($filters['affiliate_id']), '%d'));
                $where_conditions[] = "affiliate_id IN ({$placeholders})";
                $params = array_merge($params, $filters['affiliate_id']);
            } else {
                $where_conditions[] = "affiliate_id = %d";
                $params[] = $filters['affiliate_id'];
            }
        }
        
        if (!empty($filters['utm_source'])) {
            $where_conditions[] = "utm_source = %s";
            $params[] = $filters['utm_source'];
        }
        
        if (!empty($filters['utm_medium'])) {
            $where_conditions[] = "utm_medium = %s";
            $params[] = $filters['utm_medium'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "date <= %s";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $group_clause = !empty($group_by_fields) ? 'GROUP BY ' . implode(', ', $group_by_fields) : '';
        
        // Handle ordering
        $order_by = $this->build_order_clause($filters['order_by'] ?? 'total_commission DESC');
        
        // Handle limit
        $limit = min($filters['limit'] ?? $this->default_limit, $this->max_limit);
        $offset = $filters['offset'] ?? 0;
        
        $sql = "SELECT " . implode(', ', $select_fields) . "
                FROM {$this->table_analytics}
                {$where_clause}
                {$group_clause}
                {$order_by}
                LIMIT %d OFFSET %d";
        
        $params[] = $limit;
        $params[] = $offset;
        
        // Use cached query if available
        if ($this->cache_manager && empty($filters['no_cache'])) {
            $cache_key = 'analytics_' . md5(serialize($filters));
            return $this->cache_manager->execute_cached_query(
                $this->wpdb->prepare($sql, $params),
                $cache_key,
                $filters['cache_ttl'] ?? 3600 // Longer cache for analytics
            );
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    /**
     * Get performance metrics for specific time periods
     */
    public function get_performance_metrics($period = '24h', $filters = array()) {
        $date_condition = $this->get_date_condition_for_period($period);
        $cache_key = "performance_metrics_{$period}_" . md5(serialize($filters));
        
        // Build base filters
        $base_filters = array_merge($filters, array(
            'date_from' => $date_condition['start'],
            'date_to' => $date_condition['end'],
            'cache_ttl' => $this->get_cache_ttl_for_period($period)
        ));
        
        // Get click metrics
        $click_metrics = $this->get_click_metrics($base_filters);
        
        // Get conversion metrics
        $conversion_metrics = $this->get_conversion_metrics($base_filters);
        
        // Get attribution method breakdown
        $attribution_methods = $this->get_attribution_method_breakdown($base_filters);
        
        // Get top performers
        $top_affiliates = $this->get_top_performers($base_filters, 'affiliate', 10);
        $top_sources = $this->get_top_performers($base_filters, 'source', 10);
        
        return array(
            'period' => $period,
            'date_range' => $date_condition,
            'click_metrics' => $click_metrics,
            'conversion_metrics' => $conversion_metrics,
            'attribution_methods' => $attribution_methods,
            'top_affiliates' => $top_affiliates,
            'top_sources' => $top_sources,
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Get click metrics
     */
    private function get_click_metrics($filters) {
        $sql = "SELECT 
                    COUNT(*) as total_clicks,
                    COUNT(DISTINCT affiliate_id) as unique_affiliates,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    COUNT(DISTINCT utm_source) as unique_sources,
                    COUNT(DISTINCT utm_medium) as unique_mediums
                FROM {$this->table_events}
                WHERE created_at >= %s AND created_at <= %s";
        
        $params = array($filters['date_from'], $filters['date_to']);
        
        if (!empty($filters['affiliate_id'])) {
            $sql .= " AND affiliate_id = %d";
            $params[] = $filters['affiliate_id'];
        }
        
        return $this->wpdb->get_row($this->wpdb->prepare($sql, $params), ARRAY_A);
    }
    
    /**
     * Get conversion metrics
     */
    private function get_conversion_metrics($filters) {
        $sql = "SELECT 
                    COUNT(*) as total_conversions,
                    COUNT(DISTINCT affiliate_id) as converting_affiliates,
                    SUM(commission_amount) as total_commission,
                    AVG(commission_amount) as avg_commission,
                    AVG(attribution_confidence) as avg_confidence,
                    SUM(order_value) as total_order_value
                FROM {$this->table_conversions}
                WHERE created_at >= %s AND created_at <= %s
                AND status = 'attributed'";
        
        $params = array($filters['date_from'], $filters['date_to']);
        
        if (!empty($filters['affiliate_id'])) {
            $sql .= " AND affiliate_id = %d";
            $params[] = $filters['affiliate_id'];
        }
        
        return $this->wpdb->get_row($this->wpdb->prepare($sql, $params), ARRAY_A);
    }
    
    /**
     * Get attribution method breakdown
     */
    private function get_attribution_method_breakdown($filters) {
        $sql = "SELECT 
                    attribution_method,
                    COUNT(*) as count,
                    SUM(commission_amount) as total_commission,
                    AVG(attribution_confidence) as avg_confidence
                FROM {$this->table_conversions}
                WHERE created_at >= %s AND created_at <= %s
                AND status = 'attributed'
                AND attribution_method IS NOT NULL
                GROUP BY attribution_method
                ORDER BY count DESC";
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $filters['date_from'], $filters['date_to']), ARRAY_A);
    }
    
    /**
     * Get top performers
     */
    private function get_top_performers($filters, $type = 'affiliate', $limit = 10) {
        switch ($type) {
            case 'affiliate':
                $group_field = 'affiliate_id';
                $table = $this->table_conversions;
                break;
            case 'source':
                $group_field = 'utm_source';
                $table = $this->table_events;
                break;
            case 'medium':
                $group_field = 'utm_medium';
                $table = $this->table_events;
                break;
            default:
                return array();
        }
        
        if ($type === 'affiliate') {
            $sql = "SELECT 
                        {$group_field},
                        COUNT(*) as conversions,
                        SUM(commission_amount) as total_commission,
                        AVG(commission_amount) as avg_commission
                    FROM {$table}
                    WHERE created_at >= %s AND created_at <= %s
                    AND status = 'attributed'
                    GROUP BY {$group_field}
                    ORDER BY total_commission DESC
                    LIMIT %d";
        } else {
            $sql = "SELECT 
                        {$group_field},
                        COUNT(*) as clicks,
                        COUNT(DISTINCT affiliate_id) as affiliates
                    FROM {$table}
                    WHERE created_at >= %s AND created_at <= %s
                    AND {$group_field} IS NOT NULL
                    GROUP BY {$group_field}
                    ORDER BY clicks DESC
                    LIMIT %d";
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $filters['date_from'], $filters['date_to'], $limit), ARRAY_A);
    }
    
    /**
     * Build ORDER BY clause safely
     */
    private function build_order_clause($order_by) {
        // Whitelist of allowed order fields and directions
        $allowed_fields = array(
            'created_at', 'id', 'affiliate_id', 'commission_amount', 
            'order_value', 'attribution_confidence', 'clicks', 'conversions',
            'total_commission', 'conversion_rate', 'date'
        );
        
        $allowed_directions = array('ASC', 'DESC');
        
        // Parse order_by string
        $parts = explode(' ', trim($order_by));
        $field = $parts[0] ?? 'created_at';
        $direction = strtoupper($parts[1] ?? 'DESC');
        
        // Validate field and direction
        if (!in_array($field, $allowed_fields)) {
            $field = 'created_at';
        }
        
        if (!in_array($direction, $allowed_directions)) {
            $direction = 'DESC';
        }
        
        return "ORDER BY {$field} {$direction}";
    }
    
    /**
     * Get date condition for time periods
     */
    private function get_date_condition_for_period($period) {
        $end = current_time('mysql');
        
        switch ($period) {
            case '1h':
                $start = date('Y-m-d H:i:s', strtotime('-1 hour'));
                break;
            case '24h':
                $start = date('Y-m-d H:i:s', strtotime('-24 hours'));
                break;
            case '7d':
                $start = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30d':
                $start = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '90d':
                $start = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            default:
                $start = date('Y-m-d H:i:s', strtotime('-24 hours'));
                break;
        }
        
        return array('start' => $start, 'end' => $end);
    }
    
    /**
     * Get appropriate cache TTL for period
     */
    private function get_cache_ttl_for_period($period) {
        switch ($period) {
            case '1h':
                return 300; // 5 minutes
            case '24h':
                return 900; // 15 minutes
            case '7d':
                return 1800; // 30 minutes
            case '30d':
            case '90d':
                return 3600; // 1 hour
            default:
                return 900;
        }
    }
    
    /**
     * Get count for any query (for pagination)
     */
    public function get_count($base_query, $params = array()) {
        // Convert SELECT to COUNT query
        $count_query = preg_replace('/^SELECT .+ FROM/', 'SELECT COUNT(*) as total FROM', $base_query);
        
        // Remove ORDER BY and LIMIT clauses
        $count_query = preg_replace('/ORDER BY .+/i', '', $count_query);
        $count_query = preg_replace('/LIMIT .+/i', '', $count_query);
        
        $result = $this->wpdb->get_var(
            !empty($params) ? $this->wpdb->prepare($count_query, $params) : $count_query
        );
        
        return intval($result);
    }
    
    /**
     * Execute raw SQL with proper escaping and caching
     */
    public function execute_raw_query($sql, $params = array(), $cache_key = null, $cache_ttl = 3600) {
        if ($this->cache_manager && $cache_key) {
            return $this->cache_manager->execute_cached_query(
                $this->wpdb->prepare($sql, $params),
                $cache_key,
                $cache_ttl
            );
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
}
?>