<?php

defined('ABSPATH') or exit;

/**
 * TouchPoint MailChimp API Wrapper
 * 
 * Handles all communication with MailChimp API v3.0
 */
class TouchPoint_MailChimp_API {
    
    private static $instance = null;
    private $api_key = '';
    private $api_key_source = 'option';
    private $api_url = 'https://<dc>.api.mailchimp.com/3.0/';
    private $datacenter = '';
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Prefer environment/constant for secrets; fall back to option storage.
        if ( defined( 'TMC_API_KEY' ) && TMC_API_KEY ) {
            $this->api_key = TMC_API_KEY;
            $this->api_key_source = 'constant';
        } elseif ( getenv( 'TMC_API_KEY' ) ) {
            $this->api_key = getenv( 'TMC_API_KEY' );
            $this->api_key_source = 'env';
        } else {
            $this->api_key = get_option('tmc_api_key', '');
            $this->api_key_source = 'option';
        }

        if ($this->api_key) {
            $this->set_datacenter();
        }
    }
    
    /**
     * Set the datacenter from API key
     */
    private function set_datacenter() {
        if (strpos($this->api_key, '-') !== false) {
            $parts = explode('-', $this->api_key);
            $this->datacenter = array_pop($parts);
            $this->api_url = str_replace('<dc>', $this->datacenter, $this->api_url);
        }
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => __('API key is required', 'touchpoint-mailchimp')
            );
        }
        
        $response = $this->make_request('ping');
        
        if ($response['success']) {
            return array(
                'success' => true,
                'message' => __('Connection successful', 'touchpoint-mailchimp')
            );
        }
        
        return $response;
    }
    
    /**
     * Get all lists
     */
    public function get_lists() {
        $response = $this->make_request('lists', array('count' => 1000));
        
        if ($response['success'] && isset($response['data']['lists'])) {
            return array(
                'success' => true,
                'lists' => $response['data']['lists']
            );
        }
        
        return array(
            'success' => false,
            'error' => isset($response['error']) ? $response['error'] : __('Failed to fetch lists', 'touchpoint-mailchimp')
        );
    }
    
    /**
     * Get list details
     */
    public function get_list($list_id) {
        $response = $this->make_request('lists/' . $list_id);
        
        if ($response['success']) {
            return array(
                'success' => true,
                'list' => $response['data']
            );
        }
        
        return $response;
    }
    
    /**
     * Get list interest categories
     */
    public function get_list_interest_categories($list_id) {
        $response = $this->make_request('lists/' . $list_id . '/interest-categories', array('count' => 1000));
        
        if ($response['success'] && isset($response['data']['categories'])) {
            return array(
                'success' => true,
                'categories' => $response['data']['categories']
            );
        }
        
        return array(
            'success' => false,
            'error' => isset($response['error']) ? $response['error'] : __('Failed to fetch interest categories', 'touchpoint-mailchimp')
        );
    }
    
    /**
     * Get interests for a category
     */
    public function get_category_interests($list_id, $category_id) {
        $response = $this->make_request('lists/' . $list_id . '/interest-categories/' . $category_id . '/interests', array('count' => 1000));
        
        if ($response['success'] && isset($response['data']['interests'])) {
            return array(
                'success' => true,
                'interests' => $response['data']['interests']
            );
        }
        
        return array(
            'success' => false,
            'error' => isset($response['error']) ? $response['error'] : __('Failed to fetch interests', 'touchpoint-mailchimp')
        );
    }
    
    /**
     * Subscribe user to list
     */
    public function subscribe_to_list($list_id, $email, $merge_fields = array(), $interests = array()) {
        $subscriber_hash = md5(strtolower($email));
        
        $data = array(
            'email_address' => $email,
            'status' => get_option('tmc_double_optin', true) ? 'pending' : 'subscribed'
        );
        
        // Add merge fields
        if (!empty($merge_fields)) {
            $data['merge_fields'] = $merge_fields;
        }
        
        // Add interests
        if (!empty($interests)) {
            $interest_data = array();
            foreach ($interests as $interest_id) {
                $interest_data[$interest_id] = true;
            }
            $data['interests'] = $interest_data;
        }
        
        // Use PUT to create or update
        $response = $this->make_request('lists/' . $list_id . '/members/' . $subscriber_hash, $data, 'PUT');
        
        if ($response['success']) {
            return array(
                'success' => true,
                'subscriber' => $response['data']
            );
        }
        
        return $response;
    }
    
    /**
     * Update subscriber
     */
    public function update_subscriber($list_id, $email, $merge_fields = array(), $interests = array()) {
        $subscriber_hash = md5(strtolower($email));
        
        $data = array();
        
        // Add merge fields
        if (!empty($merge_fields)) {
            $data['merge_fields'] = $merge_fields;
        }
        
        // Add interests
        if (!empty($interests)) {
            $interest_data = array();
            foreach ($interests as $interest_id => $subscribed) {
                $interest_data[$interest_id] = (bool)$subscribed;
            }
            $data['interests'] = $interest_data;
        }
        
        if (empty($data)) {
            return array(
                'success' => false,
                'error' => __('No data to update', 'touchpoint-mailchimp')
            );
        }
        
        $response = $this->make_request('lists/' . $list_id . '/members/' . $subscriber_hash, $data, 'PATCH');
        
        if ($response['success']) {
            return array(
                'success' => true,
                'subscriber' => $response['data']
            );
        }
        
        return $response;
    }
    
    /**
     * Get subscriber
     */
    public function get_subscriber($list_id, $email) {
        $subscriber_hash = md5(strtolower($email));
        $response = $this->make_request('lists/' . $list_id . '/members/' . $subscriber_hash);
        
        if ($response['success']) {
            return array(
                'success' => true,
                'subscriber' => $response['data']
            );
        }
        
        return $response;
    }
    
    /**
     * Unsubscribe user from list
     */
    public function unsubscribe_from_list($list_id, $email) {
        $subscriber_hash = md5(strtolower($email));
        
        $data = array('status' => 'unsubscribed');
        
        $response = $this->make_request('lists/' . $list_id . '/members/' . $subscriber_hash, $data, 'PATCH');
        
        if ($response['success']) {
            return array(
                'success' => true,
                'subscriber' => $response['data']
            );
        }
        
        return $response;
    }
    
    /**
     * Add e-commerce store
     */
    public function add_store($store_data) {
        $response = $this->make_request('ecommerce/stores', $store_data, 'POST');
        
        if ($response['success']) {
            return array(
                'success' => true,
                'store' => $response['data']
            );
        }
        
        return $response;
    }
    
    /**
     * Add e-commerce customer
     */
    public function add_customer($store_id, $customer_data) {
        $response = $this->make_request('ecommerce/stores/' . $store_id . '/customers', $customer_data, 'POST');
        
        if ($response['success']) {
            return array(
                'success' => true,
                'customer' => $response['data']
            );
        }
        
        return $response;
    }
    
    /**
     * Add e-commerce order
     */
    public function add_order($store_id, $order_data) {
        $response = $this->make_request('ecommerce/stores/' . $store_id . '/orders', $order_data, 'POST');
        
        if ($response['success']) {
            return array(
                'success' => true,
                'order' => $response['data']
            );
        }
        
        return $response;
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $data = array(), $method = 'GET') {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'error' => __('API key not configured', 'touchpoint-mailchimp')
            );
        }
        
        $url = $this->api_url . $endpoint;
        
        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode('user:' . $this->api_key),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $attempts = 0;
        $max_attempts = 3;
        $response = null;

        do {
            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                break;
            }

            $response_code = wp_remote_retrieve_response_code($response);

            // Retry on 429/5xx with small backoff.
            if ($response_code == 429 || ($response_code >= 500 && $response_code < 600)) {
                $attempts++;
                if ($attempts >= $max_attempts) {
                    break;
                }
                $retry_after = intval(wp_remote_retrieve_header($response, 'retry-after'));
                if ($retry_after <= 0) {
                    $retry_after = pow(2, $attempts);
                }
                sleep(min($retry_after, 30));
                continue;
            }

            break;
        } while ($attempts < $max_attempts);
        
        if (is_wp_error($response)) {
            TouchPoint_MailChimp_Logger::log('API Error: ' . $response->get_error_message());
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $decoded_response = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300) {
            return array(
                'success' => true,
                'data' => $decoded_response
            );
        }
        
        $error_message = __('Unknown error', 'touchpoint-mailchimp');
        if (isset($decoded_response['title'])) {
            $error_message = $decoded_response['title'];
            if (isset($decoded_response['detail'])) {
                $error_message .= ': ' . $decoded_response['detail'];
            }
        }
        
        TouchPoint_MailChimp_Logger::log('API Error (' . $response_code . '): ' . $error_message);
        
        return array(
            'success' => false,
            'error' => $error_message,
            'response_code' => $response_code
        );
    }
}
