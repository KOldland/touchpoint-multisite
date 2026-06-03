<?php
/**
 * OpenAI Connector for Dual-GPT Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_OpenAI_Connector {

    private $api_key;
    private $base_url = 'https://api.openai.com/v1';
    private $api_key_source = null;

    public function __construct() {
        $this->api_key = $this->get_api_key();
    }

    /**
     * Get API key from WordPress options or environment
     */
    private function get_api_key() {
        // Prefer environment variables so secrets stay out of the DB/wp-config
        $env_key = getenv('OPENAI_API_KEY');
        if ($env_key) {
            $this->api_key_source = 'env';
            return $env_key;
        }

        // First check wp-config constant
        if (defined('DUAL_GPT_OPENAI_API_KEY')) {
            $this->api_key_source = 'constant';
            return DUAL_GPT_OPENAI_API_KEY;
        }

        // Then check options
        $key = get_option('dual_gpt_openai_api_key');
        if ($key) {
            $this->api_key_source = 'option';
            return $key;
        }

        $this->api_key_source = null;
        return null;
    }

    /**
    * Expose where the API key was sourced from for notices/logging
    */
    public function get_api_key_source() {
        return $this->api_key_source;
    }

    /**
     * Make a request to OpenAI API with enhanced error handling
     */
    private function make_request($endpoint, $data, $method = 'POST', $retry_count = 0, $options = array()) {
        if (!$this->api_key) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        $url = $this->base_url . $endpoint;
        $max_retries = isset($options['max_retries']) ? max(0, intval($options['max_retries'])) : 3;
        $request_timeout = isset($options['timeout']) ? max(1, intval($options['timeout'])) : 300;
        $retry_on_rate_limit = array_key_exists('retry_on_rate_limit', $options) ? (bool) $options['retry_on_rate_limit'] : true;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => $request_timeout,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
        );

        if ($method !== 'GET') {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message();

            // Check if this is a retryable network error
            if ($this->is_retryable_network_error($error_code) && $retry_count < $max_retries) {
                sleep(pow(2, $retry_count + 1)); // Exponential backoff
                return $this->make_request($endpoint, $data, $method, $retry_count + 1, $options);
            }

            return new WP_Error($error_code, 'Network error: ' . $error_message);
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);

        // Handle rate limiting
        if ($code === 429) {
            $retry_after = isset($headers['retry-after']) ? intval($headers['retry-after']) : 60;

            if ($retry_on_rate_limit && $retry_count < $max_retries) {
                sleep(min($retry_after, 300)); // Wait up to 5 minutes
                return $this->make_request($endpoint, $data, $method, $retry_count + 1, $options);
            }

            return new WP_Error('rate_limited', 'Rate limit exceeded. Please try again later.');
        }

        // Handle server errors with retry
        if (in_array($code, array(500, 502, 503, 504)) && $retry_count < $max_retries) {
            sleep(pow(2, $retry_count + 1));
            return $this->make_request($endpoint, $data, $method, $retry_count + 1, $options);
        }

        if ($code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'OpenAI API error: ' . $body;

            return new WP_Error('api_error_' . $code, $error_message, array('status' => $code));
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Failed to decode API response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Check if a network error is retryable
     */
    private function is_retryable_network_error($error_code) {
        $retryable_errors = array(
            'http_request_failed',
            'connect',
            'timeout',
            'ssl',
        );

        foreach ($retryable_errors as $pattern) {
            if (strpos($error_code, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a chat completion with tools
     */
    public function create_chat_completion($messages, $model = 'gpt-4', $tools = array(), $tool_choice = 'auto', $options = array()) {
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => $tool_choice,
        );

        return $this->make_request('/chat/completions', $data, 'POST', 0, $options);
    }

    /**
     * Create a streaming chat completion
     */
    public function create_streaming_completion($messages, $model = 'gpt-4', $tools = array(), $options = array()) {
        // For streaming, we'd need to handle Server-Sent Events
        // This is a placeholder - full implementation would require SSE handling
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'tools' => $tools,
            'stream' => true,
        );

        return $this->make_request('/chat/completions', $data, 'POST', 0, $options);
    }

    /**
     * Calculate token usage and cost
     */
    public function calculate_cost($model, $prompt_tokens, $completion_tokens) {
        // Pricing as of 2024 (approximate)
        $pricing = array(
            'gpt-5.2' => array('prompt' => 0.03, 'completion' => 0.06),
            'gpt-5' => array('prompt' => 0.03, 'completion' => 0.06),
            'gpt-4.1' => array('prompt' => 0.01, 'completion' => 0.03),
            'gpt-4o' => array('prompt' => 0.005, 'completion' => 0.015),
            'gpt-4o-mini' => array('prompt' => 0.00015, 'completion' => 0.0006),
            'gpt-4' => array('prompt' => 0.03, 'completion' => 0.06),
            'gpt-4-turbo' => array('prompt' => 0.01, 'completion' => 0.03),
            'gpt-3.5-turbo' => array('prompt' => 0.0015, 'completion' => 0.002),
        );

        $model_pricing = isset($pricing[$model]) ? $pricing[$model] : $pricing['gpt-4'];

        $cost = ($prompt_tokens * $model_pricing['prompt'] + $completion_tokens * $model_pricing['completion']) / 1000;

        return array(
            'cost_usd' => $cost,
            'cost_micro' => intval($cost * 1000000), // Store in microdollars
        );
    }

    /**
     * Validate API key
     */
    public function validate_api_key() {
        $response = $this->make_request('/models', array(), 'GET');

        if (is_wp_error($response)) {
            return false;
        }

        return isset($response['data']);
    }
}
