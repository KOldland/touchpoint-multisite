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
}
