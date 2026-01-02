<?php
/**
 * KH Events API Authentication
 *
 * Handles API authentication and authorization
 */

if (!defined('ABSPATH')) {
    exit;
}

class KH_Events_API_Auth {

    /**
     * Authentication methods
     */
    private $auth_methods = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->auth_methods = array(
            'api_key' => array(
                'name' => __('API Key', 'kh-events'),
                'description' => __('Simple API key authentication', 'kh-events'),
                'callback' => array($this, 'authenticate_api_key')
            ),
            'oauth2' => array(
                'name' => __('OAuth 2.0', 'kh-events'),
                'description' => __('OAuth 2.0 authentication flow', 'kh-events'),
                'callback' => array($this, 'authenticate_oauth2')
            ),
            'basic_auth' => array(
                'name' => __('Basic Authentication', 'kh-events'),
                'description' => __('HTTP Basic authentication', 'kh-events'),
                'callback' => array($this, 'authenticate_basic')
            )
        );
    }

    /**
     * Check API permissions for request
     */
    public function check_permissions($request) {
        $settings = get_option('kh_events_api_settings', array());
        $auth_method = $settings['auth_method'] ?? 'api_key';

        if (!isset($this->auth_methods[$auth_method])) {
            return new WP_Error('invalid_auth_method', __('Invalid authentication method', 'kh-events'), array('status' => 401));
        }

        $callback = $this->auth_methods[$auth_method]['callback'];
        return call_user_func($callback, $request);
    }

    /**
     * API Key authentication
     */
    private function authenticate_api_key($request) {
        $settings = get_option('kh_events_api_settings', array());
        $api_key = $settings['api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_Error('api_key_not_configured', __('API key not configured', 'kh-events'), array('status' => 401));
        }

        // Check Authorization header
        $auth_header = $request->get_header('authorization');

        if ($auth_header) {
            // Bearer token format
            if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
                $provided_key = $matches[1];
            }
        }

        // Check query parameter
        if (empty($provided_key)) {
            $provided_key = $request->get_param('api_key');
        }

        // Check request header
        if (empty($provided_key)) {
            $provided_key = $request->get_header('x_api_key');
        }

        if (empty($provided_key)) {
            return new WP_Error('missing_api_key', __('API key required', 'kh-events'), array('status' => 401));
        }

        if (!hash_equals($api_key, $provided_key)) {
            return new WP_Error('invalid_api_key', __('Invalid API key', 'kh-events'), array('status' => 401));
        }

        return true;
    }

    /**
     * OAuth 2.0 authentication
     */
    private function authenticate_oauth2($request) {
        // OAuth 2.0 implementation would go here
        // This is a placeholder for future implementation

        $access_token = $request->get_header('authorization');

        if (empty($access_token) || !preg_match('/Bearer\s+(.*)$/i', $access_token, $matches)) {
            return new WP_Error('missing_access_token', __('Access token required', 'kh-events'), array('status' => 401));
        }

        $token = $matches[1];

        // Verify token (this would need proper OAuth 2.0 server implementation)
        $valid = $this->verify_oauth_token($token);

        if (!$valid) {
            return new WP_Error('invalid_access_token', __('Invalid access token', 'kh-events'), array('status' => 401));
        }

        return true;
    }

    /**
     * Basic HTTP authentication
     */
    private function authenticate_basic($request) {
        $auth_header = $request->get_header('authorization');

        if (empty($auth_header) || !preg_match('/Basic\s+(.*)$/i', $auth_header, $matches)) {
            return new WP_Error('missing_credentials', __('Basic authentication credentials required', 'kh-events'), array('status' => 401));
        }

        $credentials = base64_decode($matches[1]);
        list($username, $password) = explode(':', $credentials, 2);

        // Verify credentials (this would check against WordPress users or custom user table)
        $valid = $this->verify_basic_credentials($username, $password);

        if (!$valid) {
            return new WP_Error('invalid_credentials', __('Invalid credentials', 'kh-events'), array('status' => 401));
        }

        return true;
    }

    /**
     * Verify OAuth token
     */
    private function verify_oauth_token($token) {
        // Placeholder for OAuth token verification
        // In a real implementation, this would check against a token storage system
        return false;
    }

    /**
     * Verify basic auth credentials
     */
    private function verify_basic_credentials($username, $password) {
        // Check against WordPress users
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return false;
        }

        // Check if user has API access permission
        return user_can($user, 'manage_options') || user_can($user, 'edit_posts');
    }

    /**
     * Generate API key
     */
    public function generate_api_key() {
        return wp_generate_password(32, false);
    }

    /**
     * Get available auth methods
     */
    public function get_auth_methods() {
        return $this->auth_methods;
    }

    /**
     * Rate limiting check
     */
    public function check_rate_limit($request) {
        $settings = get_option('kh_events_api_settings', array());
        $rate_limiting = $settings['rate_limiting'] ?? false;

        if (!$rate_limiting) {
            return true;
        }

        $client_ip = $this->get_client_ip();
        $limit = $settings['rate_limit'] ?? 100; // requests per hour
        $window = 3600; // 1 hour

        $transient_key = 'kh_events_api_rate_' . md5($client_ip);
        $requests = get_transient($transient_key);

        if ($requests === false) {
            $requests = 0;
        }

        if ($requests >= $limit) {
            return new WP_Error('rate_limit_exceeded', __('API rate limit exceeded', 'kh-events'), array('status' => 429));
        }

        set_transient($transient_key, $requests + 1, $window);

        return true;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (like X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Log API request
     */
    public function log_request($request, $response = null) {
        $settings = get_option('kh_events_api_settings', array());
        $logging = $settings['logging'] ?? false;

        if (!$logging) {
            return;
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'method' => $request->get_method(),
            'endpoint' => $request->get_route(),
            'ip' => $this->get_client_ip(),
            'user_agent' => $request->get_header('user_agent'),
            'response_code' => $response ? $response->get_status() : null
        );

        $logs = get_option('kh_events_api_logs', array());
        $logs[] = $log_entry;

        // Keep only last 1000 logs
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }

        update_option('kh_events_api_logs', $logs);
    }

    /**
     * Get API logs
     */
    public function get_logs($limit = 100) {
        $logs = get_option('kh_events_api_logs', array());
        return array_slice(array_reverse($logs), 0, $limit);
    }

    /**
     * Clear API logs
     */
    public function clear_logs() {
        update_option('kh_events_api_logs', array());
    }

    /**
     * Get API usage stats
     */
    public function get_usage_stats() {
        $logs = get_option('kh_events_api_logs', array());
        $stats = array(
            'total_requests' => count($logs),
            'requests_today' => 0,
            'requests_this_week' => 0,
            'requests_this_month' => 0,
            'top_endpoints' => array(),
            'response_codes' => array()
        );

        $today = date('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $month_ago = date('Y-m-d', strtotime('-30 days'));

        foreach ($logs as $log) {
            $log_date = date('Y-m-d', strtotime($log['timestamp']));

            if ($log_date === $today) {
                $stats['requests_today']++;
            }

            if ($log_date >= $week_ago) {
                $stats['requests_this_week']++;
            }

            if ($log_date >= $month_ago) {
                $stats['requests_this_month']++;
            }

            // Track endpoints
            $endpoint = $log['endpoint'];
            if (!isset($stats['top_endpoints'][$endpoint])) {
                $stats['top_endpoints'][$endpoint] = 0;
            }
            $stats['top_endpoints'][$endpoint]++;

            // Track response codes
            $code = $log['response_code'];
            if (!isset($stats['response_codes'][$code])) {
                $stats['response_codes'][$code] = 0;
            }
            $stats['response_codes'][$code]++;
        }

        // Sort top endpoints
        arsort($stats['top_endpoints']);
        $stats['top_endpoints'] = array_slice($stats['top_endpoints'], 0, 10);

        return $stats;
    }
}