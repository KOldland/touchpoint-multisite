<?php
namespace KH\Attribution\Services\Attribution;

/**
 * ConversionResolver
 * 
 * Handles the logic of resolving attribution for a given conversion ID.
 */
class ConversionResolver {

    private $storage;

    public function __construct($storage) {
        $this->storage = $storage;
    }

    /**
     * Resolve attribution result
     */
    public function resolve($conversion_id, $additional_data, $attribution_window) {
        // Try multiple attribution methods in order of reliability
        $attribution_methods = array(
            'server_side_event'  => array($this, 'resolve_server_side_attribution'),
            'first_party_cookie' => array($this, 'resolve_cookie_attribution'), 
            'url_parameter'      => array($this, 'resolve_url_parameter_attribution'),
            'session_storage'    => array($this, 'resolve_session_attribution'),
            'fingerprint_match'  => array($this, 'resolve_fingerprint_attribution')
        );
        
        foreach ($attribution_methods as $method => $resolver) {
            $attribution = call_user_func($resolver, $conversion_id, $additional_data);
            
            if ($attribution && $this->is_valid_attribution($attribution, $attribution_window)) {
                $attribution['method'] = $method;
                $attribution['confidence'] = $this->calculate_attributed_confidence($attribution, $method);
                return $attribution;
            }
        }
        return false;
    }

    /**
     * Resolve server-side event attribution
     */
    private function resolve_server_side_attribution($conversion_id, $additional_data) {
        if (isset($additional_data['click_id'])) {
            return $this->storage->get_event_by_click_id($additional_data['click_id']);
        }
        if (isset($additional_data['affiliate_id'])) {
            return $this->storage->get_last_event_by_affiliate($additional_data['affiliate_id']);
        }
        return false;
    }

    /**
     * Resolve first-party cookie attribution
     */
    private function resolve_cookie_attribution($conversion_id, $additional_data) {
        $cookie_name = 'khm_attribution';
        if (!isset($_COOKIE[$cookie_name])) return false;
        
        $cookie_data = json_decode(stripslashes($_COOKIE[$cookie_name]), true);
        if (!$cookie_data || !isset($cookie_data['click_id'])) return false;
        
        if (isset($cookie_data['expires']) && time() > $cookie_data['expires']) return false;
        
        return $this->storage->get_event_by_click_id($cookie_data['click_id']);
    }

    /**
     * Resolve URL parameter attribution (Fallback)
     */
    private function resolve_url_parameter_attribution($conversion_id, $additional_data) {
        if (isset($additional_data['url_params']['click_id'])) {
            return $this->storage->get_event_by_click_id(sanitize_text_field($additional_data['url_params']['click_id']));
        }
        if (isset($additional_data['url_params']['aff_id'])) {
            return $this->storage->get_last_event_by_affiliate(intval($additional_data['url_params']['aff_id']));
        }
        return false;
    }

    /**
     * Resolve session attribution (Fallback)
     */
    private function resolve_session_attribution($conversion_id, $additional_data) {
        if (isset($additional_data['session_click_id'])) {
            return $this->storage->get_event_by_click_id(sanitize_text_field($additional_data['session_click_id']));
        }
        return false;
    }

    /**
     * Resolve fingerprint attribution (Fallback)
     */
    private function resolve_fingerprint_attribution($conversion_id, $additional_data) {
        if (!apply_filters('khm_attribution_fingerprinting_enabled', false)) {
            return false;
        }

        // Use IP and UA from additional data (preferred) or current request
        $ip = $additional_data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $additional_data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (empty($ip) || empty($ua)) return false;

        return $this->storage->get_last_event_by_ip_and_ua($ip, $ua);
    }

    /**
     * Validation logic for attribution results
     */
    public function is_valid_attribution($attribution, $attribution_window) {
        if (empty($attribution) || !isset($attribution['click_id'])) {
            return false;
        }
        
        $timestamp = isset($attribution['timestamp']) ? strtotime($attribution['timestamp']) : 0;
        $expiry = $timestamp + ($attribution_window * DAY_IN_SECONDS);
        
        return time() <= $expiry;
    }

    /**
     * Confidence scoring based on method
     */
    public function calculate_attributed_confidence($attribution, $method) {
        $scores = [
            'server_side_event'  => 1.0,
            'first_party_cookie' => 0.9,
            'url_parameter'      => 0.7,
            'session_storage'    => 0.6,
            'fingerprint_match'  => 0.4
        ];
        
        $base_score = $scores[$method] ?? 0.5;

        // Apply decay if timestamp exists
        if (isset($attribution['timestamp'])) {
            $days_ago = (time() - strtotime($attribution['timestamp'])) / DAY_IN_SECONDS;
            if ($days_ago > 7) {
                $base_score *= 0.8; // 20% reduction for events older than a week
            }
        }

        return round($base_score, 2);
    }

    /**
     * Get attribution info
     */
    public function get_attribution($conversion_id) {
        return $this->storage->get_conversion_attribution($conversion_id);
    }

    /**
     * Generate attribution explanation
     */
    public function explain($attribution) {
        return 'Attributed via ' . $attribution['method'] . ' with confidence ' . $attribution['confidence'];
    }
}