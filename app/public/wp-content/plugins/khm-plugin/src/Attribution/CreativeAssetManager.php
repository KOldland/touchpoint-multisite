<?php
/**
 * KHM Attribution Creative Asset Manager
 * 
 * Comprehensive creative asset management system for tracking, versioning,
 * and optimizing creative performance across all marketing channels
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Attribution_Creative_Asset_Manager {
    
    private $query_builder;
    private $performance_manager;
    private $cache_manager;
    private $asset_types = array();
    private $storage_engines = array();
    
    public function __construct() {
        $this->load_dependencies();
        $this->init_asset_types();
        $this->init_storage_engines();
        $this->setup_database_tables();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once dirname(__FILE__) . '/QueryBuilder.php';
        require_once dirname(__FILE__) . '/PerformanceManager.php';
        require_once dirname(__FILE__) . '/CacheManager.php';
        
        $this->query_builder = new KHM_Attribution_Query_Builder();
        $this->performance_manager = new KHM_Attribution_Performance_Manager();
        $this->cache_manager = new KHM_Attribution_Cache_Manager();
    }
    
    /**
     * Initialize supported asset types
     */
    private function init_asset_types() {
        $this->asset_types = array(
            'image' => array(
                'name' => 'Images',
                'formats' => array('jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'),
                'max_size' => 10485760, // 10MB
                'variants' => array('thumbnail', 'medium', 'large', 'original'),
                'optimization' => true
            ),
            'video' => array(
                'name' => 'Videos',
                'formats' => array('mp4', 'webm', 'mov', 'avi'),
                'max_size' => 104857600, // 100MB
                'variants' => array('480p', '720p', '1080p', 'original'),
                'optimization' => true
            ),
            'text' => array(
                'name' => 'Text Content',
                'formats' => array('txt', 'html', 'md'),
                'max_size' => 1048576, // 1MB
                'variants' => array('headline', 'description', 'cta', 'body'),
                'optimization' => false
            ),
            'audio' => array(
                'name' => 'Audio',
                'formats' => array('mp3', 'wav', 'aac', 'ogg'),
                'max_size' => 52428800, // 50MB
                'variants' => array('low', 'medium', 'high', 'original'),
                'optimization' => true
            ),
            'document' => array(
                'name' => 'Documents',
                'formats' => array('pdf', 'doc', 'docx', 'ppt', 'pptx'),
                'max_size' => 20971520, // 20MB
                'variants' => array('preview', 'thumbnail', 'original'),
                'optimization' => false
            ),
            'interactive' => array(
                'name' => 'Interactive Elements',
                'formats' => array('html', 'js', 'css'),
                'max_size' => 5242880, // 5MB
                'variants' => array('desktop', 'mobile', 'tablet'),
                'optimization' => true
            )
        );
    }
    
    /**
     * Initialize storage engines
     */
    private function init_storage_engines() {
        $this->storage_engines = array(
            'local' => array(
                'name' => 'Local File System',
                'enabled' => true,
                'path' => WP_CONTENT_DIR . '/uploads/khm-creatives/',
                'url_base' => WP_CONTENT_URL . '/uploads/khm-creatives/'
            ),
            'aws_s3' => array(
                'name' => 'Amazon S3',
                'enabled' => defined('KHM_AWS_S3_BUCKET'),
                'bucket' => defined('KHM_AWS_S3_BUCKET') ? KHM_AWS_S3_BUCKET : '',
                'region' => defined('KHM_AWS_S3_REGION') ? KHM_AWS_S3_REGION : 'us-east-1'
            ),
            'cloudinary' => array(
                'name' => 'Cloudinary',
                'enabled' => defined('KHM_CLOUDINARY_CLOUD_NAME'),
                'cloud_name' => defined('KHM_CLOUDINARY_CLOUD_NAME') ? KHM_CLOUDINARY_CLOUD_NAME : '',
                'api_key' => defined('KHM_CLOUDINARY_API_KEY') ? KHM_CLOUDINARY_API_KEY : ''
            ),
            'cdn' => array(
                'name' => 'CDN Storage',
                'enabled' => defined('KHM_CDN_ENDPOINT'),
                'endpoint' => defined('KHM_CDN_ENDPOINT') ? KHM_CDN_ENDPOINT : '',
                'api_key' => defined('KHM_CDN_API_KEY') ? KHM_CDN_API_KEY : ''
            )
        );
    }
    
    /**
     * Setup database tables for creative assets
     */
    private function setup_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Creative assets table
        $table_name = $wpdb->prefix . 'khm_creative_assets';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            asset_id varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            asset_type varchar(50) NOT NULL,
            file_format varchar(10) NOT NULL,
            file_size bigint(20) unsigned NOT NULL,
            dimensions varchar(50),
            duration int(11),
            checksum varchar(255),
            storage_engine varchar(50) NOT NULL DEFAULT 'local',
            storage_path text NOT NULL,
            storage_url text,
            metadata longtext,
            tags text,
            category varchar(100),
            brand varchar(100),
            campaign_id varchar(255),
            status varchar(20) NOT NULL DEFAULT 'active',
            version varchar(20) NOT NULL DEFAULT '1.0',
            parent_id bigint(20) unsigned,
            created_by bigint(20) unsigned,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY asset_id (asset_id),
            KEY asset_type (asset_type),
            KEY campaign_id (campaign_id),
            KEY parent_id (parent_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Creative asset variants table
        $table_name = $wpdb->prefix . 'khm_creative_variants';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            asset_id varchar(255) NOT NULL,
            variant_name varchar(100) NOT NULL,
            variant_type varchar(50) NOT NULL,
            storage_path text NOT NULL,
            storage_url text,
            file_size bigint(20) unsigned,
            dimensions varchar(50),
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY asset_id (asset_id),
            KEY variant_type (variant_type),
            UNIQUE KEY asset_variant (asset_id, variant_name)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Creative performance metrics table
        $table_name = $wpdb->prefix . 'khm_creative_performance';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            asset_id varchar(255) NOT NULL,
            channel varchar(100) NOT NULL,
            campaign_id varchar(255),
            date_recorded date NOT NULL,
            impressions bigint(20) unsigned DEFAULT 0,
            clicks bigint(20) unsigned DEFAULT 0,
            conversions bigint(20) unsigned DEFAULT 0,
            revenue decimal(15,2) DEFAULT 0,
            cost decimal(15,2) DEFAULT 0,
            engagement_rate decimal(5,4) DEFAULT 0,
            click_through_rate decimal(5,4) DEFAULT 0,
            conversion_rate decimal(5,4) DEFAULT 0,
            cost_per_click decimal(10,2) DEFAULT 0,
            cost_per_acquisition decimal(10,2) DEFAULT 0,
            return_on_ad_spend decimal(8,2) DEFAULT 0,
            engagement_time int(11) DEFAULT 0,
            bounce_rate decimal(5,4) DEFAULT 0,
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY asset_id (asset_id),
            KEY channel (channel),
            KEY date_recorded (date_recorded),
            KEY campaign_id (campaign_id),
            UNIQUE KEY unique_performance (asset_id, channel, date_recorded)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Creative A/B tests table
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            test_id varchar(255) NOT NULL,
            test_name varchar(255) NOT NULL,
            test_type varchar(50) NOT NULL,
            test_description text,
            hypothesis text,
            control_asset_id varchar(255) NOT NULL,
            variant_asset_ids text NOT NULL,
            traffic_allocation text NOT NULL,
            channel varchar(100) NOT NULL,
            campaign_id varchar(255),
            target_metric varchar(50) NOT NULL,
            confidence_level decimal(3,2) NOT NULL DEFAULT 0.95,
            minimum_sample_size int(11) NOT NULL DEFAULT 1000,
            test_duration int(11) NOT NULL DEFAULT 14,
            status varchar(20) NOT NULL DEFAULT 'draft',
            start_date datetime,
            end_date datetime,
            winner_asset_id varchar(255),
            statistical_significance decimal(5,4),
            lift_percentage decimal(8,4),
            results_summary longtext,
            created_by bigint(20) unsigned,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY test_id (test_id),
            KEY status (status),
            KEY channel (channel),
            KEY start_date (start_date)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Upload and register a new creative asset
     */
    public function upload_asset($file_data, $asset_metadata = array()) {
        try {
            // Validate file
            $validation_result = $this->validate_file($file_data);
            if (!$validation_result['valid']) {
                throw new Exception('File validation failed: ' . $validation_result['error']);
            }
            
            // Generate unique asset ID
            $asset_id = $this->generate_asset_id($file_data, $asset_metadata);
            
            // Determine storage engine
            $storage_engine = $this->select_storage_engine($file_data, $asset_metadata);
            
            // Process and store file
            $storage_result = $this->store_file($file_data, $asset_id, $storage_engine);
            
            // Generate asset variants
            $variants = $this->generate_asset_variants($file_data, $asset_id, $storage_result);
            
            // Extract metadata
            $extracted_metadata = $this->extract_file_metadata($file_data, $storage_result);
            
            // Register asset in database
            $asset_record = $this->register_asset($asset_id, $file_data, $asset_metadata, $storage_result, $extracted_metadata);
            
            // Register variants
            foreach ($variants as $variant) {
                $this->register_variant($asset_id, $variant);
            }
            
            // Initialize performance tracking
            $this->initialize_performance_tracking($asset_id);
            
            // Clear relevant caches
            $this->cache_manager->delete_cache_group('creative_assets');
            
            return array(
                'success' => true,
                'asset_id' => $asset_id,
                'asset_record' => $asset_record,
                'variants' => $variants,
                'storage_urls' => $this->get_asset_urls($asset_id)
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            );
        }
    }
    
    /**
     * Get asset information and performance data
     */
    public function get_asset($asset_id, $include_performance = true, $include_variants = true) {
        $cache_key = "asset_data_{$asset_id}_" . ($include_performance ? '1' : '0') . '_' . ($include_variants ? '1' : '0');
        
        $cached_data = $this->cache_manager->get_cache($cache_key, 'creative_assets');
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        global $wpdb;
        
        // Get main asset data
        $asset_table = $wpdb->prefix . 'khm_creative_assets';
        $asset = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $asset_table WHERE asset_id = %s",
            $asset_id
        ), ARRAY_A);
        
        if (!$asset) {
            return array('success' => false, 'error' => 'Asset not found');
        }
        
        // Parse metadata
        $asset['metadata'] = json_decode($asset['metadata'], true) ?: array();
        $asset['tags'] = !empty($asset['tags']) ? explode(',', $asset['tags']) : array();
        
        $result = array(
            'success' => true,
            'asset' => $asset,
            'urls' => $this->get_asset_urls($asset_id)
        );
        
        // Include variants if requested
        if ($include_variants) {
            $result['variants'] = $this->get_asset_variants($asset_id);
        }
        
        // Include performance data if requested
        if ($include_performance) {
            $result['performance'] = $this->get_asset_performance($asset_id);
            $result['performance_summary'] = $this->calculate_performance_summary($asset_id);
        }
        
        // Cache the result
        $this->cache_manager->set_cache($cache_key, $result, 1800, 'creative_assets'); // 30 minutes
        
        return $result;
    }
    
    /**
     * Update asset metadata and properties
     */
    public function update_asset($asset_id, $updates) {
        global $wpdb;
        
        $allowed_fields = array('name', 'description', 'tags', 'category', 'brand', 'campaign_id', 'status');
        $update_data = array();
        
        foreach ($updates as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                if ($field === 'tags' && is_array($value)) {
                    $update_data[$field] = implode(',', $value);
                } else {
                    $update_data[$field] = $value;
                }
            }
        }
        
        if (empty($update_data)) {
            return array('success' => false, 'error' => 'No valid fields to update');
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $table_name = $wpdb->prefix . 'khm_creative_assets';
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('asset_id' => $asset_id),
            array_fill(0, count($update_data), '%s'),
            array('%s')
        );
        
        if ($result === false) {
            return array('success' => false, 'error' => 'Database update failed');
        }
        
        // Clear cache
        $this->cache_manager->delete_cache_pattern("asset_data_{$asset_id}_*", 'creative_assets');
        
        return array('success' => true, 'updated_fields' => array_keys($update_data));
    }
    
    /**
     * Delete asset and all associated data
     */
    public function delete_asset($asset_id, $force_delete = false) {
        global $wpdb;
        
        // Get asset information
        $asset_info = $this->get_asset($asset_id, false, true);
        if (!$asset_info['success']) {
            return $asset_info;
        }
        
        $asset = $asset_info['asset'];
        
        // Check if asset is being used in active campaigns
        if (!$force_delete && $this->is_asset_in_use($asset_id)) {
            return array(
                'success' => false,
                'error' => 'Asset is currently in use and cannot be deleted. Use force_delete=true to override.'
            );
        }
        
        try {
            $wpdb->query('START TRANSACTION');
            
            // Delete physical files
            $this->delete_physical_files($asset_id, $asset_info['variants']);
            
            // Delete from database tables
            $tables = array(
                $wpdb->prefix . 'khm_creative_performance',
                $wpdb->prefix . 'khm_creative_variants',
                $wpdb->prefix . 'khm_creative_assets'
            );
            
            foreach ($tables as $table) {
                $wpdb->delete($table, array('asset_id' => $asset_id), array('%s'));
            }
            
            // Update A/B tests that reference this asset
            $this->update_ab_tests_for_deleted_asset($asset_id);
            
            $wpdb->query('COMMIT');
            
            // Clear caches
            $this->cache_manager->delete_cache_group('creative_assets');
            
            return array('success' => true, 'message' => 'Asset deleted successfully');
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'error' => 'Failed to delete asset: ' . $e->getMessage());
        }
    }
    
    /**
     * Search and filter assets
     */
    public function search_assets($filters = array(), $pagination = array()) {
        $defaults = array(
            'asset_type' => '',
            'category' => '',
            'brand' => '',
            'campaign_id' => '',
            'status' => 'active',
            'tags' => array(),
            'date_from' => '',
            'date_to' => '',
            'search_term' => '',
            'sort_by' => 'created_at',
            'sort_order' => 'DESC'
        );
        
        $pagination_defaults = array(
            'page' => 1,
            'per_page' => 20
        );
        
        $filters = array_merge($defaults, $filters);
        $pagination = array_merge($pagination_defaults, $pagination);
        
        // Build cache key
        $cache_key = 'asset_search_' . md5(serialize($filters) . serialize($pagination));
        $cached_result = $this->cache_manager->get_cache($cache_key, 'creative_assets');
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_creative_assets';
        
        // Build WHERE clause
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($filters['asset_type'])) {
            $where_conditions[] = "asset_type = %s";
            $where_values[] = $filters['asset_type'];
        }
        
        if (!empty($filters['category'])) {
            $where_conditions[] = "category = %s";
            $where_values[] = $filters['category'];
        }
        
        if (!empty($filters['brand'])) {
            $where_conditions[] = "brand = %s";
            $where_values[] = $filters['brand'];
        }
        
        if (!empty($filters['campaign_id'])) {
            $where_conditions[] = "campaign_id = %s";
            $where_values[] = $filters['campaign_id'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['search_term'])) {
            $where_conditions[] = "(name LIKE %s OR description LIKE %s OR tags LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search_term']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($filters['tags'])) {
            $tag_conditions = array();
            foreach ($filters['tags'] as $tag) {
                $tag_conditions[] = "tags LIKE %s";
                $where_values[] = '%' . $wpdb->esc_like($tag) . '%';
            }
            $where_conditions[] = '(' . implode(' OR ', $tag_conditions) . ')';
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= %s";
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= %s";
            $where_values[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Build ORDER BY clause
        $allowed_sort_fields = array('name', 'created_at', 'updated_at', 'file_size', 'asset_type');
        $sort_by = in_array($filters['sort_by'], $allowed_sort_fields) ? $filters['sort_by'] : 'created_at';
        $sort_order = strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Calculate pagination
        $offset = ($pagination['page'] - 1) * $pagination['per_page'];
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table_name $where_clause";
        $total_assets = $wpdb->get_var(!empty($where_values) ? $wpdb->prepare($count_sql, $where_values) : $count_sql);
        
        // Get assets
        $sql = "SELECT * FROM $table_name $where_clause ORDER BY $sort_by $sort_order LIMIT %d OFFSET %d";
        $where_values[] = $pagination['per_page'];
        $where_values[] = $offset;
        
        $assets = $wpdb->get_results($wpdb->prepare($sql, $where_values), ARRAY_A);
        
        // Process assets
        foreach ($assets as &$asset) {
            $asset['metadata'] = json_decode($asset['metadata'], true) ?: array();
            $asset['tags'] = !empty($asset['tags']) ? explode(',', $asset['tags']) : array();
            $asset['urls'] = $this->get_asset_urls($asset['asset_id']);
        }
        
        $result = array(
            'success' => true,
            'assets' => $assets,
            'pagination' => array(
                'current_page' => $pagination['page'],
                'per_page' => $pagination['per_page'],
                'total_assets' => intval($total_assets),
                'total_pages' => ceil($total_assets / $pagination['per_page'])
            ),
            'filters_applied' => $filters
        );
        
        // Cache result for 10 minutes
        $this->cache_manager->set_cache($cache_key, $result, 600, 'creative_assets');
        
        return $result;
    }
    
    /**
     * Record performance metrics for an asset
     */
    public function record_performance($asset_id, $channel, $performance_data, $date = null) {
        if ($date === null) {
            $date = current_time('Y-m-d');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_creative_performance';
        
        // Prepare performance data
        $defaults = array(
            'campaign_id' => '',
            'impressions' => 0,
            'clicks' => 0,
            'conversions' => 0,
            'revenue' => 0,
            'cost' => 0,
            'engagement_rate' => 0,
            'engagement_time' => 0,
            'bounce_rate' => 0,
            'metadata' => array()
        );
        
        $performance_data = array_merge($defaults, $performance_data);
        
        // Calculate derived metrics
        $click_through_rate = $performance_data['impressions'] > 0 ? 
            ($performance_data['clicks'] / $performance_data['impressions']) : 0;
        
        $conversion_rate = $performance_data['clicks'] > 0 ? 
            ($performance_data['conversions'] / $performance_data['clicks']) : 0;
        
        $cost_per_click = $performance_data['clicks'] > 0 ? 
            ($performance_data['cost'] / $performance_data['clicks']) : 0;
        
        $cost_per_acquisition = $performance_data['conversions'] > 0 ? 
            ($performance_data['cost'] / $performance_data['conversions']) : 0;
        
        $return_on_ad_spend = $performance_data['cost'] > 0 ? 
            ($performance_data['revenue'] / $performance_data['cost']) : 0;
        
        $insert_data = array(
            'asset_id' => $asset_id,
            'channel' => $channel,
            'campaign_id' => $performance_data['campaign_id'],
            'date_recorded' => $date,
            'impressions' => $performance_data['impressions'],
            'clicks' => $performance_data['clicks'],
            'conversions' => $performance_data['conversions'],
            'revenue' => $performance_data['revenue'],
            'cost' => $performance_data['cost'],
            'engagement_rate' => $performance_data['engagement_rate'],
            'click_through_rate' => $click_through_rate,
            'conversion_rate' => $conversion_rate,
            'cost_per_click' => $cost_per_click,
            'cost_per_acquisition' => $cost_per_acquisition,
            'return_on_ad_spend' => $return_on_ad_spend,
            'engagement_time' => $performance_data['engagement_time'],
            'bounce_rate' => $performance_data['bounce_rate'],
            'metadata' => json_encode($performance_data['metadata'])
        );
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert functionality
        $result = $wpdb->replace($table_name, $insert_data);
        
        if ($result === false) {
            return array('success' => false, 'error' => 'Failed to record performance data');
        }
        
        // Clear performance caches
        $this->cache_manager->delete_cache_pattern("performance_{$asset_id}_*", 'creative_assets');
        $this->cache_manager->delete_cache_pattern("asset_data_{$asset_id}_*", 'creative_assets');
        
        return array('success' => true, 'metrics_recorded' => array_keys($insert_data));
    }
    
    /**
     * Get performance data for an asset
     */
    public function get_asset_performance($asset_id, $filters = array()) {
        $defaults = array(
            'channel' => '',
            'campaign_id' => '',
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'group_by' => 'date' // date, channel, campaign
        );
        
        $filters = array_merge($defaults, $filters);
        
        $cache_key = "performance_{$asset_id}_" . md5(serialize($filters));
        $cached_data = $this->cache_manager->get_cache($cache_key, 'creative_assets');
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_creative_performance';
        
        // Build WHERE clause
        $where_conditions = array("asset_id = %s");
        $where_values = array($asset_id);
        
        if (!empty($filters['channel'])) {
            $where_conditions[] = "channel = %s";
            $where_values[] = $filters['channel'];
        }
        
        if (!empty($filters['campaign_id'])) {
            $where_conditions[] = "campaign_id = %s";
            $where_values[] = $filters['campaign_id'];
        }
        
        $where_conditions[] = "date_recorded >= %s";
        $where_values[] = $filters['date_from'];
        
        $where_conditions[] = "date_recorded <= %s";
        $where_values[] = $filters['date_to'];
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Build GROUP BY clause
        $group_by_field = $filters['group_by'] === 'channel' ? 'channel' : 
                         ($filters['group_by'] === 'campaign' ? 'campaign_id' : 'date_recorded');
        
        $sql = "
            SELECT 
                $group_by_field as group_key,
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                SUM(revenue) as total_revenue,
                SUM(cost) as total_cost,
                AVG(engagement_rate) as avg_engagement_rate,
                AVG(click_through_rate) as avg_click_through_rate,
                AVG(conversion_rate) as avg_conversion_rate,
                AVG(cost_per_click) as avg_cost_per_click,
                AVG(cost_per_acquisition) as avg_cost_per_acquisition,
                AVG(return_on_ad_spend) as avg_return_on_ad_spend,
                AVG(engagement_time) as avg_engagement_time,
                AVG(bounce_rate) as avg_bounce_rate,
                COUNT(*) as data_points
            FROM $table_name 
            $where_clause 
            GROUP BY $group_by_field 
            ORDER BY $group_by_field
        ";
        
        $performance_data = $wpdb->get_results($wpdb->prepare($sql, $where_values), ARRAY_A);
        
        // Calculate totals and averages
        $totals = array(
            'total_impressions' => array_sum(array_column($performance_data, 'total_impressions')),
            'total_clicks' => array_sum(array_column($performance_data, 'total_clicks')),
            'total_conversions' => array_sum(array_column($performance_data, 'total_conversions')),
            'total_revenue' => array_sum(array_column($performance_data, 'total_revenue')),
            'total_cost' => array_sum(array_column($performance_data, 'total_cost'))
        );
        
        $totals['overall_ctr'] = $totals['total_impressions'] > 0 ? 
            ($totals['total_clicks'] / $totals['total_impressions']) : 0;
        
        $totals['overall_conversion_rate'] = $totals['total_clicks'] > 0 ? 
            ($totals['total_conversions'] / $totals['total_clicks']) : 0;
        
        $totals['overall_roas'] = $totals['total_cost'] > 0 ? 
            ($totals['total_revenue'] / $totals['total_cost']) : 0;
        
        $result = array(
            'success' => true,
            'performance_data' => $performance_data,
            'totals' => $totals,
            'filters_applied' => $filters,
            'data_points' => count($performance_data)
        );
        
        // Cache for 15 minutes
        $this->cache_manager->set_cache($cache_key, $result, 900, 'creative_assets');
        
        return $result;
    }
    
    // Helper methods continue in next section...
    
    private function validate_file($file_data) {
        // Check if file exists and is valid
        if (!isset($file_data['tmp_name']) || !file_exists($file_data['tmp_name'])) {
            return array('valid' => false, 'error' => 'File not found or invalid');
        }
        
        // Get file extension
        $file_extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        
        // Determine asset type based on extension
        $asset_type = $this->determine_asset_type($file_extension);
        if (!$asset_type) {
            return array('valid' => false, 'error' => 'Unsupported file type');
        }
        
        // Check file size
        $max_size = $this->asset_types[$asset_type]['max_size'];
        if ($file_data['size'] > $max_size) {
            return array('valid' => false, 'error' => 'File size exceeds maximum allowed');
        }
        
        // Check MIME type
        $mime_type = mime_content_type($file_data['tmp_name']);
        if (!$this->is_valid_mime_type($mime_type, $asset_type)) {
            return array('valid' => false, 'error' => 'Invalid MIME type');
        }
        
        return array('valid' => true, 'asset_type' => $asset_type);
    }
    
    private function determine_asset_type($file_extension) {
        foreach ($this->asset_types as $type => $config) {
            if (in_array($file_extension, $config['formats'])) {
                return $type;
            }
        }
        return false;
    }
    
    private function is_valid_mime_type($mime_type, $asset_type) {
        $valid_mimes = array(
            'image' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'),
            'video' => array('video/mp4', 'video/webm', 'video/quicktime', 'video/avi'),
            'audio' => array('audio/mpeg', 'audio/wav', 'audio/aac', 'audio/ogg'),
            'document' => array('application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'text' => array('text/plain', 'text/html', 'text/markdown'),
            'interactive' => array('text/html', 'application/javascript', 'text/css')
        );
        
        return isset($valid_mimes[$asset_type]) && in_array($mime_type, $valid_mimes[$asset_type]);
    }
    
    private function generate_asset_id($file_data, $metadata) {
        $timestamp = time();
        $random = wp_generate_password(8, false);
        $file_hash = md5_file($file_data['tmp_name']);
        
        return 'khm_' . $timestamp . '_' . $random . '_' . substr($file_hash, 0, 8);
    }
    
    private function select_storage_engine($file_data, $metadata) {
        // For now, default to local storage
        // In production, this would implement logic to select optimal storage
        return 'local';
    }
    
    private function store_file($file_data, $asset_id, $storage_engine) {
        switch ($storage_engine) {
            case 'local':
                return $this->store_file_local($file_data, $asset_id);
            case 'aws_s3':
                return $this->store_file_s3($file_data, $asset_id);
            case 'cloudinary':
                return $this->store_file_cloudinary($file_data, $asset_id);
            default:
                throw new Exception('Invalid storage engine');
        }
    }
    
    private function store_file_local($file_data, $asset_id) {
        $upload_dir = $this->storage_engines['local']['path'];
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }
        
        // Generate filename
        $file_extension = pathinfo($file_data['name'], PATHINFO_EXTENSION);
        $filename = $asset_id . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file_data['tmp_name'], $file_path)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        return array(
            'storage_path' => $file_path,
            'storage_url' => $this->storage_engines['local']['url_base'] . $filename,
            'filename' => $filename
        );
    }
    
    private function generate_asset_variants($file_data, $asset_id, $storage_result) {
        $file_extension = pathinfo($file_data['name'], PATHINFO_EXTENSION);
        $asset_type = $this->determine_asset_type($file_extension);
        
        if (!$this->asset_types[$asset_type]['optimization']) {
            return array();
        }
        
        $variants = array();
        $variant_types = $this->asset_types[$asset_type]['variants'];
        
        foreach ($variant_types as $variant_type) {
            if ($variant_type === 'original') continue;
            
            $variant = $this->create_variant($storage_result['storage_path'], $variant_type, $asset_type);
            if ($variant) {
                $variants[] = array(
                    'variant_name' => $variant_type,
                    'variant_type' => $variant_type,
                    'storage_path' => $variant['path'],
                    'storage_url' => $variant['url'],
                    'file_size' => filesize($variant['path']),
                    'dimensions' => $variant['dimensions'] ?? '',
                    'metadata' => json_encode($variant['metadata'] ?? array())
                );
            }
        }
        
        return $variants;
    }
    
    private function create_variant($original_path, $variant_type, $asset_type) {
        // This is a simplified variant creation
        // In production, this would use image processing libraries for different types
        
        if ($asset_type === 'image') {
            return $this->create_image_variant($original_path, $variant_type);
        }
        
        // For non-image assets, return original for now
        return array(
            'path' => $original_path,
            'url' => str_replace($this->storage_engines['local']['path'], $this->storage_engines['local']['url_base'], $original_path),
            'dimensions' => '',
            'metadata' => array()
        );
    }
    
    private function create_image_variant($original_path, $variant_type) {
        // Define variant dimensions
        $dimensions = array(
            'thumbnail' => array(150, 150),
            'medium' => array(300, 300),
            'large' => array(800, 600)
        );
        
        if (!isset($dimensions[$variant_type])) {
            return false;
        }
        
        list($width, $height) = $dimensions[$variant_type];
        
        // Create variant filename
        $path_info = pathinfo($original_path);
        $variant_filename = $path_info['filename'] . '_' . $variant_type . '.' . $path_info['extension'];
        $variant_path = $path_info['dirname'] . '/' . $variant_filename;
        
        // Use WordPress image functions if available
        if (function_exists('wp_get_image_editor')) {
            $image_editor = wp_get_image_editor($original_path);
            if (!is_wp_error($image_editor)) {
                $image_editor->resize($width, $height, true);
                $saved = $image_editor->save($variant_path);
                
                if (!is_wp_error($saved)) {
                    return array(
                        'path' => $variant_path,
                        'url' => str_replace($this->storage_engines['local']['path'], $this->storage_engines['local']['url_base'], $variant_path),
                        'dimensions' => $width . 'x' . $height,
                        'metadata' => array('resized_from' => basename($original_path))
                    );
                }
            }
        }
        
        // Fallback: copy original file
        if (copy($original_path, $variant_path)) {
            return array(
                'path' => $variant_path,
                'url' => str_replace($this->storage_engines['local']['path'], $this->storage_engines['local']['url_base'], $variant_path),
                'dimensions' => 'original',
                'metadata' => array('note' => 'Variant creation failed, using original')
            );
        }
        
        return false;
    }
    
    private function extract_file_metadata($file_data, $storage_result) {
        $metadata = array(
            'original_filename' => $file_data['name'],
            'upload_date' => current_time('mysql'),
            'file_size_formatted' => size_format($file_data['size'])
        );
        
        // Extract additional metadata based on file type
        $file_path = $storage_result['storage_path'];
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if (in_array($file_extension, array('jpg', 'jpeg', 'png', 'gif'))) {
            $image_info = getimagesize($file_path);
            if ($image_info) {
                $metadata['dimensions'] = $image_info[0] . 'x' . $image_info[1];
                $metadata['width'] = $image_info[0];
                $metadata['height'] = $image_info[1];
                $metadata['mime_type'] = $image_info['mime'];
                
                // Extract EXIF data if available
                if (function_exists('exif_read_data') && in_array($file_extension, array('jpg', 'jpeg'))) {
                    $exif = @exif_read_data($file_path);
                    if ($exif) {
                        $metadata['exif'] = array(
                            'camera' => $exif['Model'] ?? '',
                            'date_taken' => $exif['DateTime'] ?? '',
                            'iso' => $exif['ISOSpeedRatings'] ?? '',
                            'aperture' => $exif['COMPUTED']['ApertureFNumber'] ?? ''
                        );
                    }
                }
            }
        }
        
        return $metadata;
    }
    
    private function register_asset($asset_id, $file_data, $asset_metadata, $storage_result, $extracted_metadata) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_assets';
        
        $file_extension = pathinfo($file_data['name'], PATHINFO_EXTENSION);
        $asset_type = $this->determine_asset_type($file_extension);
        
        $insert_data = array(
            'asset_id' => $asset_id,
            'name' => $asset_metadata['name'] ?? pathinfo($file_data['name'], PATHINFO_FILENAME),
            'description' => $asset_metadata['description'] ?? '',
            'asset_type' => $asset_type,
            'file_format' => $file_extension,
            'file_size' => $file_data['size'],
            'dimensions' => $extracted_metadata['dimensions'] ?? '',
            'checksum' => md5_file($storage_result['storage_path']),
            'storage_engine' => 'local',
            'storage_path' => $storage_result['storage_path'],
            'storage_url' => $storage_result['storage_url'],
            'metadata' => json_encode($extracted_metadata),
            'tags' => is_array($asset_metadata['tags'] ?? '') ? implode(',', $asset_metadata['tags']) : ($asset_metadata['tags'] ?? ''),
            'category' => $asset_metadata['category'] ?? '',
            'brand' => $asset_metadata['brand'] ?? '',
            'campaign_id' => $asset_metadata['campaign_id'] ?? '',
            'created_by' => get_current_user_id()
        );
        
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            throw new Exception('Failed to register asset in database');
        }
        
        return $insert_data;
    }
    
    private function register_variant($asset_id, $variant_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_variants';
        
        $variant_data['asset_id'] = $asset_id;
        
        return $wpdb->insert($table_name, $variant_data);
    }
    
    private function initialize_performance_tracking($asset_id) {
        // Create initial performance record
        $this->record_performance($asset_id, 'system', array(
            'impressions' => 0,
            'clicks' => 0,
            'conversions' => 0,
            'revenue' => 0,
            'cost' => 0
        ));
    }
    
    private function get_asset_urls($asset_id) {
        global $wpdb;
        
        // Get main asset URL
        $asset_table = $wpdb->prefix . 'khm_creative_assets';
        $main_url = $wpdb->get_var($wpdb->prepare(
            "SELECT storage_url FROM $asset_table WHERE asset_id = %s",
            $asset_id
        ));
        
        // Get variant URLs
        $variants_table = $wpdb->prefix . 'khm_creative_variants';
        $variant_urls = $wpdb->get_results($wpdb->prepare(
            "SELECT variant_name, storage_url FROM $variants_table WHERE asset_id = %s",
            $asset_id
        ), ARRAY_A);
        
        $urls = array('original' => $main_url);
        
        foreach ($variant_urls as $variant) {
            $urls[$variant['variant_name']] = $variant['storage_url'];
        }
        
        return $urls;
    }
    
    private function get_asset_variants($asset_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_variants';
        
        $variants = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE asset_id = %s",
            $asset_id
        ), ARRAY_A);
        
        foreach ($variants as &$variant) {
            $variant['metadata'] = json_decode($variant['metadata'], true) ?: array();
        }
        
        return $variants;
    }
    
    private function calculate_performance_summary($asset_id) {
        $performance_data = $this->get_asset_performance($asset_id);
        
        if (!$performance_data['success'] || empty($performance_data['performance_data'])) {
            return array('success' => false, 'error' => 'No performance data available');
        }
        
        $totals = $performance_data['totals'];
        
        return array(
            'success' => true,
            'summary' => array(
                'total_impressions' => $totals['total_impressions'],
                'total_clicks' => $totals['total_clicks'],
                'total_conversions' => $totals['total_conversions'],
                'total_revenue' => $totals['total_revenue'],
                'total_cost' => $totals['total_cost'],
                'click_through_rate' => $totals['overall_ctr'] * 100,
                'conversion_rate' => $totals['overall_conversion_rate'] * 100,
                'return_on_ad_spend' => $totals['overall_roas'],
                'performance_score' => $this->calculate_performance_score($totals)
            )
        );
    }
    
    private function calculate_performance_score($totals) {
        // Simple performance scoring algorithm
        $ctr_score = min(100, ($totals['overall_ctr'] * 100) * 10); // CTR * 10
        $conversion_score = min(100, ($totals['overall_conversion_rate'] * 100) * 5); // CR * 5
        $roas_score = min(100, $totals['overall_roas'] * 20); // ROAS * 20
        
        return ($ctr_score * 0.3) + ($conversion_score * 0.4) + ($roas_score * 0.3);
    }
    
    private function is_asset_in_use($asset_id) {
        global $wpdb;
        
        // Check if asset is referenced in active A/B tests
        $ab_tests_table = $wpdb->prefix . 'khm_creative_ab_tests';
        $active_tests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $ab_tests_table 
             WHERE (control_asset_id = %s OR variant_asset_ids LIKE %s) 
             AND status IN ('running', 'scheduled')",
            $asset_id,
            '%' . $asset_id . '%'
        ));
        
        return $active_tests > 0;
    }
    
    private function delete_physical_files($asset_id, $variants) {
        global $wpdb;
        
        // Delete main asset file
        $asset_table = $wpdb->prefix . 'khm_creative_assets';
        $storage_path = $wpdb->get_var($wpdb->prepare(
            "SELECT storage_path FROM $asset_table WHERE asset_id = %s",
            $asset_id
        ));
        
        if ($storage_path && file_exists($storage_path)) {
            unlink($storage_path);
        }
        
        // Delete variant files
        foreach ($variants as $variant) {
            if (file_exists($variant['storage_path'])) {
                unlink($variant['storage_path']);
            }
        }
    }
    
    private function update_ab_tests_for_deleted_asset($asset_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_creative_ab_tests';
        
        // Update tests where this asset is the control
        $wpdb->update(
            $table_name,
            array('status' => 'cancelled', 'results_summary' => 'Test cancelled due to asset deletion'),
            array('control_asset_id' => $asset_id, 'status' => 'running'),
            array('%s', '%s'),
            array('%s', '%s')
        );
        
        // Update tests where this asset is a variant
        $tests_with_variant = $wpdb->get_results($wpdb->prepare(
            "SELECT id, variant_asset_ids FROM $table_name WHERE variant_asset_ids LIKE %s AND status = 'running'",
            '%' . $asset_id . '%'
        ));
        
        foreach ($tests_with_variant as $test) {
            $variant_ids = json_decode($test->variant_asset_ids, true);
            $variant_ids = array_filter($variant_ids, function($id) use ($asset_id) {
                return $id !== $asset_id;
            });
            
            if (empty($variant_ids)) {
                // Cancel test if no variants left
                $wpdb->update(
                    $table_name,
                    array('status' => 'cancelled', 'results_summary' => 'Test cancelled due to asset deletion'),
                    array('id' => $test->id),
                    array('%s', '%s'),
                    array('%d')
                );
            } else {
                // Update variant list
                $wpdb->update(
                    $table_name,
                    array('variant_asset_ids' => json_encode(array_values($variant_ids))),
                    array('id' => $test->id),
                    array('%s'),
                    array('%d')
                );
            }
        }
    }
}

// Initialize the creative asset manager
new KHM_Attribution_Creative_Asset_Manager();
?>