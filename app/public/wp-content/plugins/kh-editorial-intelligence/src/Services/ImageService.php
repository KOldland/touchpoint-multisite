<?php

namespace KH\Editorial\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ImageService
 * 
 * Centralized utility for AI image generation (DALL-E, Imagen, etc.)
 */
class ImageService {

    /**
     * @var ImageProviderInterface[]
     */
    private $providers = [];

    public function __construct() {
        $this->providers = [
            'openai' => new \KH\Editorial\Providers\Image\OpenAIImageProvider(),
            'google' => new \KH\Editorial\Providers\Image\GoogleImageProvider(),
        ];
    }

    /**
     * Generate an image using the specified provider.
     */
    public function generate( $params ) {
        $provider_id = $params['provider'] ?? 'openai';
        $provider    = $this->providers[ $provider_id ] ?? null;

        if ( ! $provider ) {
            return new \WP_Error( 'invalid_provider', "Image provider '{$provider_id}' not found." );
        }

        // 1. Build Recommendation (Logic from Prompt Builder)
        $recommendation = $this->recommend( $params );
        
        // 2. Merge Recommendation with Overrides
        $final_params = wp_parse_args( $params, $recommendation );

        // 3. Execute Generation
        $result = $provider->generate( $final_params );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // 4. Persist to Media Library (if requested)
        if ( ! empty( $params['store_in_media_library'] ) || get_option( 'kh_editorial_auto_store_media', true ) ) {
            $attachments = $this->persist_to_media_library( $result, $final_params );
            if ( is_wp_error( $attachments ) ) {
                return $attachments;
            }
            $result['attachments'] = $attachments;
        }

        return $result;
    }

    /**
     * Build image generation parameters based on article context and house style.
     */
    public function recommend( $params ) {
        $presets = $this->get_presets();
        $preset_key = $params['preset_key'] ?? 'layered_editorial_cutout';
        $style = $presets[ $preset_key ] ?? $presets['layered_editorial_cutout'];

        $title    = $params['title'] ?? '';
        $summary  = $params['summary'] ?? '';
        $audience = $params['audience'] ?? '';

        $prompt_parts = [
            "Create a publication-quality editorial image for the article '{$title}'.",
            "Art direction: {$style['art_direction']}",
        ];

        if ( $summary ) {
            $prompt_parts[] = "Article summary: {$summary}";
        }
        if ( $audience ) {
            $prompt_parts[] = "Target audience: {$audience}";
        }
        if ( ! empty( $style['brand_palette'] ) ) {
            $prompt_parts[] = "Brand palette: {$style['brand_palette']}.";
        }

        return [
            'prompt'          => implode( ' ', $prompt_parts ),
            'negative_prompt' => $style['negative_prompt'] ?? '',
            'aspect_ratio'    => $style['aspect_ratio'] ?? '16:9',
            'alt_text'        => $title ? "Editorial illustration for {$title}" : "Editorial illustration",
            'caption'         => $summary ? wp_trim_words( $summary, 18, '...' ) : $title,
        ];
    }

    /**
     * Sideload generated images into the WordPress Media Library.
     */
    public function persist_to_media_library( $provider_result, $context ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $assets = $this->extract_assets( $provider_result );
        if ( empty( $assets ) ) {
            return new \WP_Error( 'no_assets', 'Provider response did not contain usable image assets.' );
        }

        $attachments = [];
        foreach ( $assets as $index => $asset ) {
            $attachment_id = $this->persist_single_asset( $asset, $context, $provider_result );
            if ( is_wp_error( $attachment_id ) ) {
                return $attachment_id;
            }
            $attachments[] = [
                'id'  => $attachment_id,
                'url' => wp_get_attachment_url( $attachment_id ),
            ];
        }

        return $attachments;
    }

    private function extract_assets( $result ) {
        $assets   = [];
        $response = $result['response'] ?? [];

        // OpenAI (DALL-E)
        if ( ! empty( $response['data'] ) ) {
            foreach ( $response['data'] as $item ) {
                if ( ! empty( $item['url'] ) ) {
                    $assets[] = [ 'type' => 'url', 'data' => $item['url'] ];
                } elseif ( ! empty( $item['b64_json'] ) ) {
                    $assets[] = [ 'type' => 'base64', 'data' => $item['b64_json'] ];
                }
            }
        }

        // Google (Imagen)
        if ( ! empty( $response['candidates'] ) ) {
            foreach ( $response['candidates'] as $candidate ) {
                foreach ( $candidate['content']['parts'] ?? [] as $part ) {
                    if ( ! empty( $part['inlineData']['data'] ) ) {
                        $assets[] = [
                            'type'      => 'base64',
                            'data'      => $part['inlineData']['data'],
                            'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png'
                        ];
                    }
                }
            }
        }

        return $assets;
    }

    private function persist_single_asset( $asset, $context, $result ) {
        $title = $context['title'] ?? 'ai-generated-image';
        $filename = sanitize_title( $title ) . '-' . time() . '.png';

        if ( $asset['type'] === 'url' ) {
            $temp_file = download_url( $asset['data'] );
            if ( is_wp_error( $temp_file ) ) return $temp_file;

            $file_array = [ 'name' => $filename, 'tmp_name' => $temp_file ];
            $attachment_id = media_handle_sideload( $file_array, $context['post_id'] ?? 0, $context['title'] ?? '' );
            if ( is_wp_error( $attachment_id ) ) @unlink( $temp_file );
        } else {
            $binary = base64_decode( $asset['data'] );
            $upload = wp_upload_bits( $filename, null, $binary );
            if ( ! empty( $upload['error'] ) ) return new \WP_Error( 'upload_fail', $upload['error'] );

            $attachment_id = wp_insert_attachment( [
                'post_title'     => $title,
                'post_mime_type' => $asset['mime_type'] ?? 'image/png',
                'post_status'    => 'inherit',
            ], $upload['file'], $context['post_id'] ?? 0 );

            $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }

        if ( ! is_wp_error( $attachment_id ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $context['alt_text'] ?? '' );
            update_post_meta( $attachment_id, '_kh_ai_image_provider', $result['provider'] );
        }

        return $attachment_id;
    }

    public function get_presets() {
        return [
            'layered_editorial_cutout' => [
                'label'           => 'Layered Editorial Cutout',
                'art_direction'   => 'Paper-cut editorial illustration with geometric forms and tactile texture.',
                'brand_palette'   => 'Warm kraft beige, muted teal, deep blue, orange, yellow.',
                'negative_prompt' => 'photorealism, glossy 3D render, cluttered background',
                'aspect_ratio'    => '16:9',
            ],
        ];
    }
}
