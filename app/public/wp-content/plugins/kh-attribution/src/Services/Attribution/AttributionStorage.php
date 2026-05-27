<?php
namespace KH\Attribution\Services\Attribution;

/**
 * AttributionStorage
 * 
 * Handles all database interactions and cookie management for the attribution system.
 */
class AttributionStorage {

    private $attribution_window;

    public function __construct($attribution_window = 30) {
        $this->attribution_window = $attribution_window;
    }

    /**
     * Store attribution event in database
     */
    public function store_event($attribution_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_attribution_events';
        
        return $wpdb->insert(
            $table_name,
            [
                'click_id'     => $attribution_data['click_id'],
                'affiliate_id' => $attribution_data['affiliate_id'],
                'target_url'   => $attribution_data['target_url'],
                'referrer'     => $attribution_data['referrer'],
                'utm_params'   => json_encode($attribution_data['utm_params']),
                'client_data'  => json_encode($attribution_data['client_data']),
                'ip_address'   => $attribution_data['ip_address'],
                'user_agent'   => $attribution_data['user_agent'],
                'created_at'   => $attribution_data['timestamp'],
                'expires_at'   => $attribution_data['expires_at']
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Store conversion in database
     */
    public function store_conversion($conversion_data) {
        global $wpdb;
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        
        return $wpdb->insert(
            $table_conversions,
            [
                'order_id'           => $conversion_data['conversion_id'],
                'order_value'        => $conversion_data['value'],
                'currency'           => $conversion_data['currency'],
                'attribution_method' => $conversion_data['attribution']['method'],
                'confidence_score'   => $conversion_data['attribution']['confidence'] ?? 0,
                'created_at'         => $conversion_data['timestamp'],
                'status'             => 'attributed'
            ],
            ['%s', '%f', '%s', '%s', '%f', '%s', '%s']
        );
    }

    /**
     * Set attribution cookie with 1P domain
     */
    public function set_cookie($click_id, $affiliate_id, $utm_params, $window) {
        $expire_time = time() + ($window * DAY_IN_SECONDS);
        
        $cookie_data = [
            'click_id'     => $click_id,
            'affiliate_id' => $affiliate_id,
            'utm_params'   => $utm_params,
            'timestamp'    => time(),
            'expires'      => $expire_time
        ];

        // Modern array signature for PHP 7.3+ (SameSite support)
        setcookie('khm_attribution', json_encode($cookie_data), [
            'expires'  => $expire_time,
            'path'     => '/',
            'domain'   => '', // Current domain
            'secure'   => is_ssl(),
            'httponly' => false, // Need JS access for fallback
            'samesite' => 'Lax'
        ]);
    }

        /**
     * Helper: Get tracking method
     */
    public function get_tracking_method($server_side_events, $cookieless_fallback) {
        if ($server_side_events) return 'hybrid_server_side';
        if ($cookieless_fallback) return 'cookieless_fallback';
        return 'cookie_only';
    }

    /**
     * Get event by click ID
     */
    public function get_event_by_click_id($click_id) {

        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_attribution_events';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE click_id = %s",
            $click_id
        ), ARRAY_A);
    }

    /**
     * Get last event by affiliate ID
     */
    public function get_last_event_by_affiliate($affiliate_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_attribution_events';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE affiliate_id = %d ORDER BY timestamp DESC LIMIT 1",
            $affiliate_id
        ), ARRAY_A);
    }

    /**
     * Get conversion attribution record
     */
    public function get_conversion_attribution($conversion_id) {
        global $wpdb;
        $table_conversions = $wpdb->prefix . 'khm_conversion_tracking';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_conversions WHERE order_id = %s",
            $conversion_id
        ), ARRAY_A);

        if (!$row) return null;

        return [
            'method'     => $row['attribution_method'],
            'order_id'   => $row['order_id'],
            'value'      => $row['order_value'],
            'confidence' => $row['confidence_score'] ?? 0
        ];
    }

    /**
     * Get last event by IP and User Agent (Fingerprinting)
     */
    public function get_last_event_by_ip_and_ua($ip, $ua) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'khm_attribution_events';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE ip_address = %s AND user_agent = %s ORDER BY timestamp DESC LIMIT 1",
            $ip,
            $ua
        ), ARRAY_A);
    }
}