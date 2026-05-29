<?php

namespace KH\Editorial\Providers\Image;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use KH\Editorial\Core\LLMService;

/**
 * OpenAIImageProvider
 * 
 * OpenAI (DALL-E) implementation for image generation.
 */
class OpenAIImageProvider implements ImageProviderInterface {

    private $id = 'openai';

    /**
     * @inheritDoc
     */
    public function generate( array $params ) {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'openai_image_key_missing', 'OpenAI image API key is not configured.' );
        }

        $api_key = LLMService::get_api_key();
        
        $request = [
            'model'   => $params['model'] ?? 'dall-e-3',
            'prompt'  => (string) ($params['prompt'] ?? ''),
            'size'    => $params['size'] ?? '1024x1024',
            'quality' => $params['quality'] ?? 'standard',
        ];

        if ( ! empty( $params['dry_run'] ) ) {
            return [
                'provider' => $this->id,
                'mode'     => 'dry_run',
                'request'  => $request,
            ];
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
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
            $message = $decoded['error']['message'] ?? 'OpenAI image generation failed.';
            return new \WP_Error( 'openai_image_generation_failed', $message );
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
        return LLMService::is_configured();
    }

    /**
     * @inheritDoc
     */
    public function get_id() {
        return $this->id;
    }
}
