<?php
/**
 * OpenAI-backed image provider.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_OpenAI_Image_Provider {
    private $settings;

    public function __construct($settings = null) {
        $this->settings = $settings instanceof Dual_GPT_Image_Settings ? $settings : new Dual_GPT_Image_Settings();
    }

    public function generate($payload) {
        $config = $this->settings->get_config();
        $provider_config = $config['providers']['openai'] ?? array();

        if (empty($provider_config['enabled'])) {
            return new WP_Error('openai_image_disabled', 'OpenAI image generation is disabled.', array('status' => 400));
        }

        $api_key = $this->resolve_api_key($provider_config);
        if ($api_key === '') {
            return new WP_Error('openai_image_key_missing', 'OpenAI image API key is not configured.', array('status' => 400));
        }

        $request = array(
            'model' => sanitize_text_field($provider_config['image_model'] ?? 'gpt-image-1'),
            'prompt' => (string) ($payload['prompt'] ?? ''),
            'size' => sanitize_text_field($payload['size'] ?? '1536x1024'),
            'quality' => sanitize_text_field($payload['quality'] ?? 'high'),
        );

        if (!empty($payload['dry_run'])) {
            return array(
                'provider' => 'openai',
                'mode' => 'dry_run',
                'request' => $request,
                'meta' => array(
                    'alt_text' => $payload['alt_text'] ?? '',
                    'caption' => $payload['caption'] ?? '',
                ),
            );
        }

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($request),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $message = $decoded['error']['message'] ?? 'OpenAI image generation failed.';
            return new WP_Error('openai_image_generation_failed', $message, array('status' => $status));
        }

        return array(
            'provider' => 'openai',
            'mode' => 'live',
            'request' => $request,
            'response' => $decoded,
            'meta' => array(
                'alt_text' => $payload['alt_text'] ?? '',
                'caption' => $payload['caption'] ?? '',
            ),
        );
    }

    private function resolve_api_key($provider_config) {
        if (defined('DUAL_GPT_IMAGE_OPENAI_API_KEY')) {
            return (string) DUAL_GPT_IMAGE_OPENAI_API_KEY;
        }

        $env = getenv('DUAL_GPT_IMAGE_OPENAI_API_KEY');
        if ($env !== false && $env !== '') {
            return (string) $env;
        }

        $shared_env = getenv('OPENAI_API_KEY');
        if ($shared_env !== false && $shared_env !== '') {
            return (string) $shared_env;
        }

        if (defined('DUAL_GPT_OPENAI_API_KEY')) {
            return (string) DUAL_GPT_OPENAI_API_KEY;
        }

        return (string) ($provider_config['api_key'] ?? '');
    }
}
