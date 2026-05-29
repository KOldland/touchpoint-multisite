<?php

namespace KH\Editorial\Providers\Image;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ImageProviderInterface
 * 
 * Standard interface for all image generation providers.
 */
interface ImageProviderInterface {

    /**
     * Generate an image based on the provided parameters.
     *
     * @param array $params {
     *     @type string $prompt          The primary image prompt.
     *     @type string $negative_prompt Optional negative prompt.
     *     @type string $size            Image dimensions (e.g., '1024x1024').
     *     @type string $quality         Image quality (e.g., 'hd', 'standard').
     * }
     * @return array|\WP_Error Result containing image data or error.
     */
    public function generate( array $params );

    /**
     * Check if the provider is correctly configured (API keys, etc.).
     *
     * @return bool
     */
    public function is_configured();

    /**
     * Get the provider's unique identifier.
     *
     * @return string
     */
    public function get_id();
}
