<?php
/**
 * LLM Client for OpenAI API
 *
 * Wrapper for OpenAI API calls with retry/backoff logic and usage tracking.
 *
 * @package Dual_GPT
 */

namespace Dual_GPT;

defined( 'ABSPATH' ) || exit;

/**
 * LLM Client Class
 */
class Dual_GPT_LLM_Client {

    /**
     * OpenAI API base URL
     *
     * @var string
     */
    private $base_url = 'https://api.openai.com/v1';

    /**
     * API key
     *
     * @var string|null
     */
    private $api_key;

    /**
     * API key source for logging
     *
     * @var string|null
     */
    private $api_key_source;

    /**
     * Default model
     *
     * @var string
     */
    private $model;

    /**
     * Maximum retries
     *
     * @var int
     */
    private $max_retries = 3;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = $this->get_api_key();
        $this->model   = $this->get_model();
    }

    /**
     * Get API key from multiple sources.
     * 
     * Priority:
     * 1. Environment variable OPENAI_API_KEY
     * 2. Dual GPT plugin option (dual_gpt_openai_api_key)
     * 3. WordPress constant OPENAI_API_KEY
     *
     * @return string|null
     */
    private function get_api_key() {
        // 1. Environment variable (preferred for production)
        $env_key = getenv( 'OPENAI_API_KEY' );
        if ( $env_key ) {
            $this->api_key_source = 'env';
            return $env_key;
        }

        // 2. Dual GPT plugin option (shared with existing plugin)
        $dual_gpt_key = get_option( 'dual_gpt_openai_api_key' );
        if ( $dual_gpt_key ) {
            $this->api_key_source = 'dual_gpt_option';
            return $dual_gpt_key;
        }

        // 3. WordPress constant
        if ( defined( 'OPENAI_API_KEY' ) ) {
            $this->api_key_source = 'constant';
            return OPENAI_API_KEY;
        }

        // 4. Dual GPT constant
        if ( defined( 'DUAL_GPT_OPENAI_API_KEY' ) ) {
            $this->api_key_source = 'dual_gpt_constant';
            return DUAL_GPT_OPENAI_API_KEY;
        }

        $this->api_key_source = null;
        return null;
    }

    /**
     * Get API key source for debugging
     *
     * @return string|null
     */
    public function get_api_key_source() {
        return $this->api_key_source;
    }

    /**
     * Check if API key is configured
     *
     * @return bool
     */
    public function has_api_key() {
        return ! empty( $this->api_key );
    }

    /**
     * Get model to use
     *
     * @return string
     */
    private function get_model() {
        // Check environment/constant
        $env_model = getenv( 'DUAL_GPT_LLM_MODEL' );
        if ( $env_model ) {
            return $env_model;
        }

        if ( defined( 'DUAL_GPT_LLM_MODEL' ) ) {
            return DUAL_GPT_LLM_MODEL;
        }

        // Check option
        $option_model = get_option( 'dual_gpt_llm_model' );
        if ( $option_model ) {
            return $option_model;
        }

        // Default
        return 'gpt-4o-mini';
    }

    /**
     * Get the current model name
     *
     * @return string
     */
    public function get_model_name() {
        return $this->model;
    }

    /**
     * Make a chat completion request
     *
     * @param string $system_prompt System prompt.
     * @param string $user_prompt   User prompt.
     * @param array  $options       Additional options (temperature, max_tokens, etc.).
     * @return array|\WP_Error Response data or error.
     */
    public function call( $system_prompt, $user_prompt, $options = array() ) {
        if ( ! $this->api_key ) {
            return new \WP_Error(
                'no_api_key',
                __( 'OpenAI API key not configured. Set it in Dual GPT settings or as OPENAI_API_KEY environment variable.', 'dual-gpt' )
            );
        }

        $data = array(
            'model'       => $options['model'] ?? $this->model,
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => $system_prompt,
                ),
                array(
                    'role'    => 'user',
                    'content' => $user_prompt,
                ),
            ),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['max_tokens'] ?? 2000,
        );

        // Add JSON mode if requested
        if ( ! empty( $options['json_mode'] ) ) {
            $data['response_format'] = array( 'type' => 'json_object' );
        }

        return $this->make_request_with_retry( '/chat/completions', $data );
    }

    /**
     * Make request with retry and exponential backoff
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     * @return array|\WP_Error
     */
    private function make_request_with_retry( $endpoint, $data ) {
        $last_error = null;

        for ( $attempt = 0; $attempt < $this->max_retries; $attempt++ ) {
            if ( $attempt > 0 ) {
                // Exponential backoff: 1s, 2s, 4s
                $delay = pow( 2, $attempt - 1 );
                sleep( $delay );
            }

            $result = $this->make_request( $endpoint, $data );

            if ( ! is_wp_error( $result ) ) {
                return $result;
            }

            $last_error = $result;
            $error_code = $result->get_error_code();

            // Don't retry on certain errors
            if ( in_array( $error_code, array( 'no_api_key', 'invalid_api_key', 'invalid_request' ), true ) ) {
                break;
            }

            error_log( sprintf(
                '[Dual GPT LLMClient] Attempt %d failed: %s. Retrying...',
                $attempt + 1,
                $result->get_error_message()
            ) );
        }

        return $last_error;
    }

    /**
     * Make a single API request
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     * @return array|\WP_Error
     */
    private function make_request( $endpoint, $data ) {
        $url = $this->base_url . $endpoint;

        $args = array(
            'method'      => 'POST',
            'headers'     => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'        => wp_json_encode( $data ),
            'timeout'     => 120,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
        );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code === 401 ) {
            return new \WP_Error( 'invalid_api_key', __( 'Invalid OpenAI API key', 'dual-gpt' ) );
        }

        if ( $code === 429 ) {
            return new \WP_Error( 'rate_limited', __( 'OpenAI rate limit exceeded', 'dual-gpt' ) );
        }

        if ( $code >= 500 ) {
            return new \WP_Error( 'server_error', __( 'OpenAI server error', 'dual-gpt' ) );
        }

        if ( $code !== 200 ) {
            $message = $data['error']['message'] ?? __( 'Unknown API error', 'dual-gpt' );
            return new \WP_Error( 'api_error', $message );
        }

        return $data;
    }

    /**
     * Extract content from chat completion response
     *
     * @param array $response API response.
     * @return string|null
     */
    public function extract_content( $response ) {
        return $response['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Get usage info from response
     *
     * @param array $response API response.
     * @return array
     */
    public function get_usage( $response ) {
        return $response['usage'] ?? array(
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'total_tokens'      => 0,
        );
    }

    /**
     * Estimate cost based on usage
     *
     * @param array  $usage Usage data.
     * @param string $model Model name.
     * @return float Estimated cost in USD.
     */
    public function estimate_cost( $usage, $model = null ) {
        $model = $model ?? $this->model;

        // Pricing per 1M tokens (as of 2024)
        $pricing = array(
            'gpt-4o-mini' => array(
                'input'  => 0.15,
                'output' => 0.60,
            ),
            'gpt-4o' => array(
                'input'  => 2.50,
                'output' => 10.00,
            ),
            'gpt-4-turbo' => array(
                'input'  => 10.00,
                'output' => 30.00,
            ),
            'gpt-3.5-turbo' => array(
                'input'  => 0.50,
                'output' => 1.50,
            ),
        );

        $rates = $pricing[ $model ] ?? $pricing['gpt-4o-mini'];

        $prompt_cost     = ( $usage['prompt_tokens'] ?? 0 ) * $rates['input'] / 1000000;
        $completion_cost = ( $usage['completion_tokens'] ?? 0 ) * $rates['output'] / 1000000;

        return round( $prompt_cost + $completion_cost, 6 );
    }

    /**
     * Estimate tokens for a string (rough estimate)
     *
     * @param string $text Text to estimate.
     * @return int Estimated token count.
     */
    public function estimate_tokens( $text ) {
        // Rough estimate: ~4 characters per token for English
        return (int) ceil( strlen( $text ) / 4 );
    }
}