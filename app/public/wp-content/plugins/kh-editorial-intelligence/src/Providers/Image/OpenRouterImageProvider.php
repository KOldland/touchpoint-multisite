<?php

namespace KH\Editorial\Providers\Image;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * OpenRouterImageProvider
 *
 * Generates images via OpenRouter's chat completions API, which provides
 * access to Flux, Recraft, Sourceful, and many other image models.
 * Using OpenRouter gives us redundancy, competitive pricing, and a unified
 * API for all image generation needs.
 */
class OpenRouterImageProvider implements ImageProviderInterface {

    private $id = 'openrouter';

    /**
     * @inheritDoc
     */
    public function generate( array $params ) {
        error_log( '[KH Image] OpenRouterProvider::generate() called with model=' . ($params['model'] ?? 'default') . ', prompt_len=' . strlen($params['prompt'] ?? '') );

        if ( ! $this->is_configured() ) {
            error_log( '[KH Image] OpenRouterProvider FAIL: API key not configured' );
            return new \WP_Error(
                'openrouter_image_key_missing',
                'OpenRouter API key is not configured. Set it under Editorial Studio > API Settings.'
            );
        }

        $api_key     = $this->resolve_api_key();
        $model       = $params['model'] ?? 'black-forest-labs/flux.2-klein-4b';
        $prompt      = (string) ( $params['prompt'] ?? '' );
        $aspect      = $this->resolve_aspect_ratio( $params );
        $image_size  = $params['image_size'] ?? '1K';

        if ( empty( $prompt ) ) {
            error_log( '[KH Image] OpenRouterProvider FAIL: empty prompt' );
            return new \WP_Error( 'openrouter_missing_prompt', 'Prompt is required for image generation.' );
        }

        error_log( '[KH Image] OpenRouterProvider sending to model=' . $model . ', aspect=' . $aspect . ', size=' . $image_size );

        $request = [
            'model'      => $model,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'modalities' => [ 'image' ],
            'stream'     => false,
        ];

        // Add image configuration if aspect ratio or size is provided
        $image_config = [];
        if ( ! empty( $aspect ) ) {
            $image_config['aspect_ratio'] = $aspect;
        }
        if ( ! empty( $image_size ) ) {
            $image_config['image_size'] = $image_size;
        }
        if ( ! empty( $image_config ) ) {
            $request['image_config'] = $image_config;
        }

        if ( ! empty( $params['dry_run'] ) ) {
            return [
                'provider' => $this->id,
                'mode'     => 'dry_run',
                'request'  => $request,
            ];
        }

        error_log( '[KH Image] OpenRouterProvider sending POST to ' . strlen(wp_json_encode($request)) . ' bytes for model=' . $model );

        $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', [
            'timeout' => 180,
            'headers' => [
                'Authorization'           => 'Bearer ' . $api_key,
                'Content-Type'            => 'application/json',
                'HTTP-Referer'            => home_url(),
                'X-OpenRouter-Title'      => 'KH Editorial Suite',
            ],
            'body' => wp_json_encode( $request ),
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[KH Image] OpenRouterProvider HTTP error: ' . $response->get_error_message() );
            return $response;
        }

        $status  = wp_remote_retrieve_response_code( $response );
        $body    = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        error_log( '[KH Image] OpenRouterProvider response status=' . $status . ' body_len=' . strlen($body) );

        if ( $status < 200 || $status >= 300 ) {
            $message = $decoded['error']['message'] ?? $decoded['error']['metadata']['raw_message'] ?? 'OpenRouter image generation failed.';
            error_log( '[KH Image] OpenRouterProvider API error: ' . $message );
            return new \WP_Error( 'openrouter_image_generation_failed', $message );
        }

        // OpenRouter returns images in choices[0].message.images[]
        $error = $decoded['choices'][0]['error'] ?? null;
        if ( $error ) {
            error_log( '[KH Image] OpenRouterProvider provider error: ' . ($error['message'] ?? 'unknown') );
            return new \WP_Error( 'openrouter_image_error', $error['message'] ?? 'Provider returned an error.' );
        }

        $image_count = count($decoded['choices'][0]['message']['images'] ?? []);
        error_log( '[KH Image] OpenRouterProvider SUCCESS - got ' . $image_count . ' image(s)' );

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

    /**
     * Resolve the OpenRouter API key from settings or constant.
     *
     * @return string
     */
    private function resolve_api_key() {
        $settings = get_option( 'kh_editorial_settings', [] );
        return $settings['openrouter_api_key'] ?? ( defined( 'OPENROUTER_API_KEY' ) ? OPENROUTER_API_KEY : '' );
    }

    /**
     * Resolve aspect ratio from the params, converting DALL-E style
     * size strings (e.g. '1024x1024') to OpenRouter format (e.g. '1:1').
     *
     * @param array $params
     * @return string
     */
    private function resolve_aspect_ratio( array $params ) {
        // Direct aspect_ratio param takes priority
        if ( ! empty( $params['aspect_ratio'] ) ) {
            $ar = $params['aspect_ratio'];
            // Already in X:Y format?
            if ( preg_match( '/^\d+:\d+$/', $ar ) ) {
                return $ar;
            }
        }

        // Convert from DALL-E size format (1024x1024, 1792x1024, 1024x1792)
        $size = $params['size'] ?? '';
        $map = [
            '1024x1024' => '1:1',
            '1792x1024' => '16:9',
            '1024x1792' => '9:16',
            '1024x768'  => '4:3',
            '768x1024'  => '3:4',
        ];
        if ( isset( $map[ $size ] ) ) {
            return $map[ $size ];
        }

        // Try to parse arbitrary WxH and reduce to ratio
        if ( preg_match( '/^(\d+)x(\d+)$/', $size, $m ) ) {
            $w = (int) $m[1];
            $h = (int) $m[2];
            $g = $this->gcd( $w, $h );
            return ( $w / $g ) . ':' . ( $h / $g );
        }

        return '16:9'; // Default
    }

    /**
     * Greatest common divisor (for aspect ratio reduction).
     */
    private function gcd( $a, $b ) {
        while ( $b !== 0 ) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }
        return $a;
    }
}