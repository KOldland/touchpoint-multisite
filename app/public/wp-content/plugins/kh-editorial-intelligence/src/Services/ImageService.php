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
            'openai'     => new \KH\Editorial\Providers\Image\OpenAIImageProvider(),
            'google'     => new \KH\Editorial\Providers\Image\GoogleImageProvider(),
            'openrouter' => new \KH\Editorial\Providers\Image\OpenRouterImageProvider(),
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
     * Build image generation parameters based on article context, art style, and colour palette.
     */
    public function recommend( $params ) {
        $styles = $this->get_art_styles();
        $palettes = $this->get_colour_palettes();

        $style_key = $params['preset_key'] ?? 'risograph_print';
        $palette_key = $params['colour_palette'] ?? 'terracotta_indigo';

        $art_direction = $styles[ $style_key ] ?? $styles['risograph_print'];
        $palette = $palettes[ $palette_key ] ?? $palettes['terracotta_indigo'];

        $title    = $params['title'] ?? '';
        $summary  = $params['summary'] ?? '';

        $prompt_parts = [
            "Create a publication-quality editorial image for the article '{$title}'.",
            "Art direction: {$art_direction}",
        ];

        if ( $summary ) {
            $prompt_parts[] = "Article summary: {$summary}";
        }
        $prompt_parts[] = "Colour palette: {$palette}.";
        $prompt_parts[] = "Do NOT include any text, words, letters, numbers, typography, or watermarks in the image.";

        return [
            'prompt'          => implode( ' ', $prompt_parts ),
            'negative_prompt' => 'text, words, letters, typography, watermark, signature, label, caption, title',
            'aspect_ratio'    => '16:9',
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

        // OpenRouter — images come as base64 data URLs in choices[0].message.images[]
        if ( ! empty( $response['choices'] ) ) {
            foreach ( $response['choices'] as $choice ) {
                $images = $choice['message']['images'] ?? [];
                foreach ( $images as $image ) {
                    $url = $image['image_url']['url'] ?? '';
                    if ( strpos( $url, 'data:image/' ) === 0 ) {
                        // Extract base64 data after the comma: data:image/png;base64,<data>
                        $parts = explode( ',', $url, 2 );
                        $assets[] = [
                            'type'      => 'base64',
                            'data'      => $parts[1] ?? $url,
                            'mime_type' => 'image/png',
                        ];
                    } elseif ( ! empty( $url ) ) {
                        $assets[] = [ 'type' => 'url', 'data' => $url ];
                    }
                }
            }
        }

        return $assets;
    }

    private function persist_single_asset( $asset, $context, $result ) {
        $title = $context['title'] ?? 'ai-generated-image';
        $filename_base = sanitize_title( $title ) . '-' . time();
        $max_size = 1400 * 1024; // 1400 KB — safe under WP's 1500 KB limit

        if ( $asset['type'] === 'url' ) {
            $temp_file = download_url( $asset['data'] );
            if ( is_wp_error( $temp_file ) ) return $temp_file;

            $file_array = [ 'name' => $filename_base . '.png', 'tmp_name' => $temp_file ];

            // Compress if too large
            if ( filesize( $temp_file ) > $max_size ) {
                $compressed = $this->compress_image( $temp_file, $filename_base );
                if ( ! is_wp_error( $compressed ) ) {
                    $file_array['tmp_name'] = $compressed;
                    $file_array['name']     = $filename_base . '.jpg';
                }
            }

            $attachment_id = media_handle_sideload( $file_array, $context['post_id'] ?? 0, $context['title'] ?? '' );
            if ( is_wp_error( $attachment_id ) ) @unlink( $temp_file );
            if ( isset( $compressed ) && file_exists( $compressed ) ) @unlink( $compressed );
        } else {
            $binary = base64_decode( $asset['data'] );

            // Compress if too large
            if ( strlen( $binary ) > $max_size ) {
                $temp_path = wp_tempnam( $filename_base );
                file_put_contents( $temp_path, $binary );
                $compressed = $this->compress_image( $temp_path, $filename_base );
                @unlink( $temp_path );

                if ( is_wp_error( $compressed ) ) {
                    // Fall back to original if compression fails
                    $upload = wp_upload_bits( $filename_base . '.png', null, $binary );
                    if ( ! empty( $upload['error'] ) ) return new \WP_Error( 'upload_fail', $upload['error'] );
                    $attachment_id = $this->insert_attachment( $upload['file'], $filename_base . '.png', $title, 'image/png', $context );
                } else {
                    $upload = wp_upload_bits( $filename_base . '.jpg', null, file_get_contents( $compressed ) );
                    @unlink( $compressed );
                    if ( ! empty( $upload['error'] ) ) return new \WP_Error( 'upload_fail', $upload['error'] );
                    $attachment_id = $this->insert_attachment( $upload['file'], $filename_base . '.jpg', $title, 'image/jpeg', $context );
                }
            } else {
                $upload = wp_upload_bits( $filename_base . '.png', null, $binary );
                if ( ! empty( $upload['error'] ) ) return new \WP_Error( 'upload_fail', $upload['error'] );
                $attachment_id = $this->insert_attachment( $upload['file'], $filename_base . '.png', $title, 'image/png', $context );
            }
        }

        if ( ! is_wp_error( $attachment_id ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $context['alt_text'] ?? '' );
            update_post_meta( $attachment_id, '_kh_ai_image_provider', $result['provider'] );
        }

        return $attachment_id;
    }

    /**
     * Compress/resize an image to fit under the WP upload size limit.
     * Resizes longest edge to 1600px and saves as JPEG quality 80.
     *
     * @param string $source_path  Absolute path to the source image.
     * @param string $filename_base  Base name for the output file (no extension).
     * @return string|\WP_Error  Path to compressed JPEG, or error.
     */
    private function compress_image( $source_path, $filename_base ) {
        if ( ! extension_loaded( 'gd' ) ) {
            return new \WP_Error( 'gd_missing', 'GD extension required for image compression.' );
        }

        $info = getimagesize( $source_path );
        if ( ! $info ) {
            return new \WP_Error( 'invalid_image', 'Could not read image dimensions.' );
        }

        list( $w, $h ) = $info;
        $max_dim = 1600;
        if ( $w <= $max_dim && $h <= $max_dim ) {
            // Just convert to JPEG with quality reduction
            $im = imagecreatefromstring( file_get_contents( $source_path ) );
        } else {
            // Resize
            $ratio = min( $max_dim / $w, $max_dim / $h );
            $nw = (int) round( $w * $ratio );
            $nh = (int) round( $h * $ratio );
            $src = imagecreatefromstring( file_get_contents( $source_path ) );
            $im  = imagecreatetruecolor( $nw, $nh );
            imagecopyresampled( $im, $src, 0, 0, 0, 0, $nw, $nh, $w, $h );
            imagedestroy( $src );
        }

        if ( ! $im ) {
            return new \WP_Error( 'image_create_failed', 'Could not create GD image.' );
        }

        $out_path = wp_tempnam( $filename_base . '-compressed.jpg' );
        imagejpeg( $im, $out_path, 80 );
        imagedestroy( $im );

        return $out_path;
    }

    /**
     * Insert an uploaded file as a WordPress attachment.
     */
    private function insert_attachment( $file_path, $filename, $title, $mime_type, $context ) {
        $attachment_id = wp_insert_attachment( [
            'post_title'     => $title,
            'post_mime_type' => $mime_type,
            'post_status'    => 'inherit',
        ], $file_path, $context['post_id'] ?? 0 );

        if ( is_wp_error( $attachment_id ) ) return $attachment_id;

        $metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        return $attachment_id;
    }

    public function get_art_styles() {
        return [
            'risograph_print'      => 'Minimalist Risograph print illustration with bold geometric vector shapes, subtle ink overlay registration errors, and high tactile grain texture.',
            'bauhaus_woodblock'    => 'Flat-lay minimal woodblock print illustration. Clean line art stamped onto a light natural wood grain texture. Structural, balanced geometric forms with soft shadows.',
            'chalk_matte_vector'   => 'Clean, flat 2D vector illustration with a chalky matte finish. High-contrast geometric shapes, sharp lines, subtle fine paper dust texture overlay, completely flat perspective.',
            'linocut_mono'         => 'Block-printed linocut illustration, bold hand-carved negative space, visible chiseled texture marks on a heavy cardstock background.',
            'knitted_yarn_craft'   => 'A 3D isometric knitted illustration made entirely from chunky woven yarn threads. Features detailed cable-knit patterns, visible purl stitches, and a soft, fuzzy wool texture. Intricate handcrafted fiber art style with gentle volumetric studio lighting and soft shadows.',
            'origami'              => 'Minimalist origami scene with sharp geometric folds, intricate paper creases, and delicate self-contained structures. Focus on clean lines and visible paper physics without flat layer cutouts.',
            'vintage_playroom'     => 'High-end editorial toy photography. A miniature scene constructed from simple, tactile playground materials: smooth painted wooden blocks, dense felt animal figures, and interlocking plastic brick infrastructure. Soft studio lighting with realistic shadows.',
            'pop_art'              => 'Bold retro comic-style graphic illustration. Heavy black ink outlines, dramatic high-contrast shading, and prominent Ben-Day dot screen patterns covering the large, flat color shapes.',
            'googie'               => 'Mid-century retro-futuristic cartoon illustration. Features sleek dynamic curves, flying saucer personal vehicles, elevated platforms on thin pylons, and bubble-dome architecture. Clean graphic ink lines with an optimistic 1950s aesthetic.',
            'steampunk'            => 'Photorealistic close-up depiction of complex, vintage clockwork machinery and mechanical engineering. Features intricate interlocking gears, visible steam pipes, polished pressure gauges, and riveted metal plates. Dramatic chiaroscuro studio lighting with soft rising steam.',
        ];
    }

    public function get_colour_palettes() {
        return [
            'terracotta_indigo'    => 'Cream paper background, muted terracotta red, deep indigo, mustard yellow, and sage green.',
            'birch_cobalt'         => 'Natural birch wood beige, charcoal gray, burnt orange, cobalt blue, and cream white.',
            'obsidian_cyan'        => 'Charcoal black background, electric cyan, muted coral pink, crisp white, and pale mint green.',
            'midnight_gold'        => 'Off-white textured paper, deep midnight navy blue, and a single accent color of bright amber gold.',
            'oatmeal_sage'         => 'Warm oatmeal cream, soft sage green, dusty rose pink, mustard yellow, and heather gray.',
            'alabaster_terracotta' => 'Crisp white, soft pastel yellow, mint green, and pale terracotta on a textured matte Washi paper background.',
            'primary_pine'         => 'Primary bold red, bright cobalt blue, sunny yellow, forest green, and natural pine wood beige.',
            'crimson_cyan'         => 'Saturated cyan blue, vibrant primary yellow, bright crimson red, and solid stark black.',
            'avocado_turquoise'    => 'Mint green, avocado, pale turquoise, burnt orange, and cream white.',
            'verdigris_brass'      => 'Aged brass, dark copper, verdigris patina, polished steel, and deep mahogany wood accents.',
        ];
    }
}
