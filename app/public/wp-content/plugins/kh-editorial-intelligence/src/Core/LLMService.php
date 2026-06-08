<?php

namespace KH\Editorial\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LLMService
 * 
 * Provides a standardized way for suite plugins to access the OpenAI API.
 */
class LLMService {

    /**
     * Get the configured API key from the Orchestrator settings.
     *
     * @return string|null
     */
    public static function get_api_key(): ?string {
        $settings = get_option( 'kh_editorial_settings', [] );
        return ! empty( $settings['openai_api_key'] ) ? $settings['openai_api_key'] : null;
    }

    /**
     * Get the configured default model.
     *
     * @return string
     */
    public static function get_model(): string {
        $settings = get_option( 'kh_editorial_settings', [] );
        return ! empty( $settings['openai_model'] ) ? $settings['openai_model'] : 'gpt-4o-mini';
    }

    /**
     * Check if the API is configured.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return ! empty( self::get_api_key() );
    }

    /**
     * Execute a chat completion request.
     * 
     * @param array $messages Array of message objects (role, content).
     * @param array $args     Additional API arguments.
     * @return array|\WP_Error Result including 'content' and 'usage' or error.
     */
    public static function post_completion( array $messages, array $args = [] ) {
        $api_key = self::get_api_key();
        if ( ! $api_key ) {
            return new \WP_Error( 'llm_unconfigured', 'OpenAI API key is missing.' );
        }

        $defaults = [
            'model'       => self::get_model(),
            'temperature' => 0.2,
        ];

        $payload = array_merge( $defaults, $args, [ 'messages' => $messages ] );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ]);

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $content = $body['choices'][0]['message']['content'] ?? '';

        if ( empty( $content ) ) {
            return new \WP_Error( 'llm_empty_response', 'LLM returned an empty response.' );
        }

        return [
            'content' => $content,
            'usage'   => $body['usage'] ?? [ 'total_tokens' => 0 ],
            'model'   => $body['model'] ?? $payload['model'],
        ];
    }

    /**
     * Execute a chat completion request with automatic retry and fallback.
     *
     * Retry strategy: 3 total attempts (initial + 2 retries) with exponential backoff.
     * Retryable errors: network timeouts, connection failures, HTTP 429/500/502/503.
     * Non-retryable errors: missing API key, empty response from API.
     * Fallback: On final failure, attempt one call with gpt-4o-mini if primary model was larger.
     *
     * @param array $messages Array of message objects (role, content).
     * @param array $args     Additional API arguments.
     * @return array|\WP_Error Result including 'content' and 'usage' or error.
     */
    public static function post_completion_with_retry( array $messages, array $args = [] ) {
        $max_attempts = 3;
        $backoff      = [ 2, 4 ];
        $last_error   = null;

        for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
            $result = self::post_completion( $messages, $args );

            if ( ! is_wp_error( $result ) ) {
                return $result;
            }

            $last_error = $result;
            $error_code = $result->get_error_code();

            if ( in_array( $error_code, [ 'llm_unconfigured', 'llm_empty_response' ], true ) ) {
                error_log( '[Editorial AI] LLM retry aborted (non-retryable): ' . $error_code . ' - ' . $result->get_error_message() );
                return $result;
            }

            if ( $attempt === $max_attempts ) {
                $primary_model = $args['model'] ?? self::get_model();
                if ( $primary_model !== 'gpt-4o-mini' ) {
                    error_log( '[Editorial AI] LLM retry exhausted. Attempting fallback with gpt-4o-mini.' );
                    $fallback_args          = $args;
                    $fallback_args['model'] = 'gpt-4o-mini';
                    $fallback_result = self::post_completion( $messages, $fallback_args );

                    if ( ! is_wp_error( $fallback_result ) ) {
                        error_log( '[Editorial AI] Fallback to gpt-4o-mini succeeded.' );
                        return $fallback_result;
                    }

                    error_log( '[Editorial AI] Fallback to gpt-4o-mini also failed: ' . $fallback_result->get_error_message() );
                }
                break;
            }

            $wait = $backoff[ $attempt - 1 ] ?? 4;
            error_log( sprintf(
                '[Editorial AI] LLM attempt %d/%d failed (%s). Retrying in %ds...',
                $attempt,
                $max_attempts,
                $error_code,
                $wait
            ) );
            sleep( $wait );
        }

        return $last_error;
    }
}
