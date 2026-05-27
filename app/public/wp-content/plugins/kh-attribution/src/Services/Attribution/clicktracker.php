<?php
namespace KH\Attribution\Services\Attribution;

class ClickTracker {

    private $attribution_window;

    public function __construct($attribution_window = 30) {
        $this->attribution_window = $attribution_window;
    }

    /**
     * Handle the core click tracking logic
     */
    public function handle_click($request) {
        $affiliate_id = intval($request['affiliate_id']);
        
        if (!$this->is_valid_affiliate($affiliate_id)) {
            return new \WP_Error('invalid_affiliate', 'Invalid affiliate ID', array('status' => 400));
        }
        
        $click_id = $this->generate_click_id();
        
        return [
            'click_id'     => $click_id,
            'affiliate_id' => $affiliate_id,
            'target_url'   => esc_url_raw($request['url'] ?? home_url($_SERVER['REQUEST_URI'])),
            'referrer'     => sanitize_text_field($request['referrer'] ?? ''),
            'utm_params'   => $this->standardize_utm_params([
                'utm_source'   => $request['utm_source'] ?? '',
                'utm_medium'   => $request['utm_medium'] ?? '',
                'utm_campaign' => $request['utm_campaign'] ?? '',
                'utm_term'     => $request['utm_term'] ?? '',
                'utm_content'  => $request['utm_content'] ?? ''
            ]),
            'client_data'  => $this->sanitize_client_data($request['client_data'] ?? []),
            'ip_address'   => $this->get_client_ip(),
            'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp'    => current_time('mysql'),
            'expires_at'   => date('Y-m-d H:i:s', strtotime('+' . $this->attribution_window . ' days'))
        ];
    }

    public function generate_click_id() {
        return 'click_' . uniqid() . '_' . wp_generate_password(8, false);
    }

    public function is_valid_affiliate($affiliate_id) {
        $user = get_user_by('ID', $affiliate_id);
        return $user && user_can($user, 'affiliate_access');
    }

    public function standardize_utm_params($utm_params) {
        $standardized = [];
        $corrections = [
            'utm_source' => [
                'gooogle' => 'google', 
                'facebok' => 'facebook', 
                'twiter' => 'twitter', 
                'instgram' => 'instagram',
                'goggle' => 'google'
            ],
            'utm_medium' => [
                'emai' => 'email', 
                'socail' => 'social', 
                'payed' => 'paid', 
                'bannner' => 'banner'
            ],
            'utm_campaign' => [
                'linikedin' => 'linkedin' // Typo from original bak file
            ]
        ];
        
        foreach ($utm_params as $param => $value) {
            $value = strtolower(trim($value));
            if (isset($corrections[$param][$value])) {
                $value = $corrections[$param][$value];
            }
            $value = sanitize_text_field($value);
            if (!empty($value)) $standardized[$param] = $value;
        }
        return $standardized;
    }

    public function sanitize_client_data($client_data) {
        $sanitized = [];
        $allowed = ['screen_width', 'screen_height', 'viewport_width', 'viewport_height', 'timezone', 'language', 'platform', 'referrer_domain'];
        foreach ($allowed as $field) {
            if (isset($client_data[$field])) {
                $sanitized[$field] = sanitize_text_field($client_data[$field]);
            }
        }
        return $sanitized;
    }

    public function get_client_ip() {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}