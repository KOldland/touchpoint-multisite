<?php
namespace KH\Attribution\Services\Attribution;

/**
 * Advanced Attribution Manager
 * 
 * Orchestrates attribution logic by delegating to specialized services.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KHM_Advanced_Attribution_Manager {
    
    private $attribution_window = 30; // days
    private $performance_manager;
    private $async_manager;
    private $query_builder;
    private $storage;
    private $commission_calculator;

    /**
     * Dependency Injection: Services injected via Bootstrap provider.
     */
    public function __construct($performance, $async, $query, $storage, $commission_calculator) {
        $this->performance_manager   = $performance;
        $this->async_manager         = $async;
        $this->query_builder         = $query;
        $this->storage               = $storage;
        $this->commission_calculator = $commission_calculator;

        $this->setup_attribution_tracking();
        $this->init_utm_standardization();
    }

    private function setup_attribution_tracking() {
        add_action('wp_loaded', array($this, 'process_affiliate_click'));
    }

    private function init_utm_standardization() {
        add_filter('khm_affiliate_url_utm_params', function($params) {
            $tracker = new ClickTracker($this->attribution_window);
            return $tracker->standardize_utm_params($params);
        });
    }

    /**
     * Process affiliate click detection from URL parameters
     */
    public function process_affiliate_click() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $affiliate_id = $_GET['aff'] ?? $_GET['affiliate_id'] ?? $_GET['ref'] ?? null;
        if (!$affiliate_id) return;

        // Use the existing tracking logic
        $this->handle_click_tracking($_GET + ['affiliate_id' => $affiliate_id]);
    }

    /**
     * Permission check for sensitive lookups
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options') || 
               current_user_can('manage_affiliates') || 
               current_user_can('view_affiliate_reports');
    }

    /**
     * Getter for attribution window
     */
    public function get_attribution_window() {
        return $this->attribution_window;
    }

    /**
     * Check if tracking should be loaded on current page
     */
    public function should_load_tracking() {
        if (is_admin()) return false;
        if ($this->is_bot_request()) return false;
        
        return apply_filters('khm_should_load_tracking', true);
    }

    /**
     * Basic bot detection
     */
    private function is_bot_request() {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($ua)) return true; // Treat empty UAs as potential bots
        
        $bots = [
            'bot', 'crawl', 'spider', 'slurp', 'google', 'bing', 'yahoo', 
            'duckduckgo', 'baiduspider', 'yandex', 'sogou', 'exabot', 'facebot',
            'facebookexternalhit', 'ia_archiver'
        ];
        
        foreach ($bots as $bot) {
            if (stripos($ua, $bot) !== false) return true;
        }
        
        return false;
    }

    /**
     * Initialize attribution system
     */
    public function init_attribution_system() {
        \KH\Attribution\Database\AttributionSchema::ensure_tables_exist();
    }

    /**
     * Handle click tracking via REST API
     */
    public function handle_click_tracking($request) {
        $tracker = new ClickTracker($this->attribution_window);
        $click_data = $tracker->handle_click($request);

        if (is_wp_error($click_data)) {
            return $click_data;
        }

        // Delegate storage
        $this->storage->store_event($click_data); 
        $this->storage->set_cookie($click_data['click_id'], $click_data['affiliate_id'], $click_data['utm_params'], $this->attribution_window);
        
        return [
            'success'         => true,
            'click_id'        => $click_data['click_id'],
            'attribution_window' => $this->attribution_window,
            'tracking_method' => $this->storage->get_tracking_method(true, false)
        ];
    }
    
    /**
     * Handle conversion tracking via REST API
     */
    public function handle_conversion_tracking($request) {
        $conversion_id    = sanitize_text_field($request['conversion_id']);
        $value            = floatval($request['value']);
        $currency         = sanitize_text_field($request['currency'] ?? 'USD');
        $attribution_data = $request['attribution_data'] ?? array();
        
        // 1. Delegate resolution to the new Resolver service
        $resolver = new ConversionResolver($this->storage);
        $attribution_result = $resolver->resolve($conversion_id, $attribution_data, $this->attribution_window);
        
        if (!$attribution_result) {
            return array('success' => false, 'message' => 'No valid attribution found');
        }
        
        // 2. Prepare the data
        $conversion_data = array(
            'conversion_id' => $conversion_id,
            'value'         => $value,
            'currency'      => $currency,
            'attribution'   => $attribution_result,
            'timestamp'     => current_time('mysql')
        );
        
        // 3. Delegate storage to the Storage service
        $this->storage->store_conversion($conversion_data);
        
        // 4. Delegate commission calculation and trigger hooks
        $commission = $this->commission_calculator->calculate($conversion_data);
        do_action('khm_commission_attributed', $commission, $conversion_data);
        
        return array(
            'success'     => true, 
            'attribution' => $attribution_result
        );
    }
    
    /**
     * Handle attribution lookup
     */
    public function handle_attribution_lookup($request) {
        $conversion_id = sanitize_text_field($request['conversion_id']);
        $explain       = isset($request['explain']) && filter_var($request['explain'], FILTER_VALIDATE_BOOLEAN);
        
        // 1. Delegate lookup to the resolver
        $resolver    = new ConversionResolver($this->storage);
        $attribution = $resolver->get_attribution($conversion_id);
        
        if (!$attribution) {
            return new \WP_Error('not_found', 'Attribution not found', array('status' => 404));
        }
        
        $response = array(
            'conversion_id' => $conversion_id, 
            'attribution'   => $attribution
        );
        
        // 2. Delegate explanation to the resolver
        if ($explain) {
            $response['explanation'] = $resolver->explain($attribution);
        }
        
        return $response;
    }
}