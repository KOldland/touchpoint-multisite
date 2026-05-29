<?php

namespace KH\Editorial\Providers\Image;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GoogleImageProvider
 * 
 * Google Gemini (Imagen) implementation for image generation.
 */
class GoogleImageProvider implements ImageProviderInterface {

    private $id = 'google';

    /**
     * @inheritDoc
     */
    public function generate( array $params ) {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'google_image_key_missing', 'Google AI API key is not configured.' );
        }

        $api_key = $this->resolve_api_key();
        $model   = $params['model'] ?? 'gemini-1.5-flash'; // Fallback to stable Gemini flash
        $prompt  = $this->build_prompt( $params );

        $request = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => [ 'IMAGE' ],
                'imageConfig' => [
                    'aspectRatio' => $this->normalize_aspect_ratio( $params['aspect_ratio'] ?? '16:9' ),
                ],
            ],
        ];

        if ( ! empty( $params['dry_run'] ) ) {
            return [
                'provider' => $this->id,
                'mode'     => 'dry_run',
                'request'  => $request,
            ];
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            rawurlencode( $model )
        );

        $response = wp_remote_post( $url, [
            'timeout' => 180,
            'headers' => [
                'x-goog-api-key' => $api_key,
                'Content-Type'   => 'application/json',
            ],
            'body' => wp_json_encode( $request ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status  = wp_remote_retrieve_response_code( $response );
        $body    = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        if ( $status < 200 || $status >= 300 ) {
            $message = $decoded['error']['message'] ?? 'Google image generation failed.';
            return new \WP_Error( 'google_image_generation_failed', $message );
        }

        return [
            'provider' => $this->id,
            'mode'     => 'live',
            'request'  => $request,
            'response' => $decoded,
        ];
    }

    /**
     * @inheritDoc
     */
    public function is_configured() {
        return ! empty( $this->resolve_api_key() );
    }

    /**
     * @inheritDoc
     */
    public function get_id() {
        return $this->id;
    }

    private function resolve_api_key() {
        $settings = get_option( 'kh_editorial_settings', [] );
        return $settings['google_ai_key'] ?? ( defined( 'GOOGLE_AI_API_KEY' ) ? GOOGLE_AI_API_KEY : '' );
    }

    private function build_prompt( $params ) {
        $parts = [];
        $base_prompt = trim( (string) ($params['prompt'] ?? '') );
        if ( $base_prompt !== '' ) {
            $parts[] = $base_prompt;
        }

        $negative_prompt = trim( (string) ($params['negative_prompt'] ?? '') );
        if ( $negative_prompt !== '' ) {
            $parts[] = 'Avoid the following: ' . $negative_prompt . '.';
        }

        return implode( "\n", $parts );
    }

    private function normalize_aspect_ratio( $value ) {
        $allowed = [ '1:1', '3:4', '4:3', '9:16', '16:9' ];
        return in_array( $value, $allowed, true ) ? $value : '16:9';
    }
}
