<?php
/**
 * Google Gemini image-backed provider via generateContent.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Google_Image_Provider {
    private $settings;

    public function __construct($settings = null) {
        $this->settings = $settings instanceof Dual_GPT_Image_Settings ? $settings : new Dual_GPT_Image_Settings();
    }

    public function generate($payload) {
        $config = $this->settings->get_config();
        $provider_config = $config['providers']['google'] ?? array();

        if (empty($provider_config['enabled'])) {
            return new WP_Error('google_image_disabled', 'Google image generation is disabled.', array('status' => 400));
        }

        $api_key = $this->resolve_api_key($provider_config);
        if ($api_key === '') {
            return new WP_Error('google_image_key_missing', 'Google AI API key is not configured.', array('status' => 400));
        }

        $model = sanitize_text_field($provider_config['image_model'] ?? 'gemini-3.1-flash-image-preview');
        $prompt = $this->build_prompt($payload);
        $request = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt,
                        ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'responseModalities' => array('IMAGE'),
                'imageConfig' => array(
                    'aspectRatio' => $this->normalize_aspect_ratio($payload['aspect_ratio'] ?? $payload['size'] ?? '16:9'),
                ),
            ),
        );

        $image_size = sanitize_text_field($payload['image_size'] ?? '');
        if ($image_size !== '') {
            $request['generationConfig']['imageConfig']['imageSize'] = $image_size;
        }

        if (!empty($payload['editorial_accuracy'])) {
            $request['tools'] = array(
                array(
                    'google_search' => (object) array(),
                ),
            );
        }

        if (!empty($payload['dry_run'])) {
            return array(
                'provider' => 'google',
                'mode' => 'dry_run',
                'request' => $request,
                'meta' => array(
                    'alt_text' => $payload['alt_text'] ?? '',
                    'caption' => $payload['caption'] ?? '',
                    'model' => $model,
                ),
            );
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            rawurlencode($model)
        );

        $response = wp_remote_post($url, array(
            'timeout' => 180,
            'headers' => array(
                'x-goog-api-key' => $api_key,
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
            $message = $decoded['error']['message'] ?? 'Google image generation failed.';
            return new WP_Error('google_image_generation_failed', $message, array('status' => $status));
        }

        return array(
            'provider' => 'google',
            'mode' => 'live',
            'request' => $request,
            'response' => $decoded,
            'meta' => array(
                'alt_text' => $payload['alt_text'] ?? '',
                'caption' => $payload['caption'] ?? '',
                'model' => $model,
            ),
        );
    }

    private function resolve_api_key($provider_config) {
        if (defined('DUAL_GPT_GOOGLE_AI_API_KEY')) {
            return (string) DUAL_GPT_GOOGLE_AI_API_KEY;
        }

        $env = getenv('DUAL_GPT_GOOGLE_AI_API_KEY');
        if ($env !== false && $env !== '') {
            return (string) $env;
        }

        $shared_env = getenv('GEMINI_API_KEY');
        if ($shared_env !== false && $shared_env !== '') {
            return (string) $shared_env;
        }

        $shared_google_env = getenv('GOOGLE_API_KEY');
        if ($shared_google_env !== false && $shared_google_env !== '') {
            return (string) $shared_google_env;
        }

        return (string) ($provider_config['api_key'] ?? '');
    }

    private function build_prompt($payload) {
        $parts = array();
        $base_prompt = trim((string) ($payload['prompt'] ?? ''));
        if ($base_prompt !== '') {
            $parts[] = $base_prompt;
        }

        $text_in_image = trim((string) ($payload['text_in_image'] ?? ''));
        if ($text_in_image !== '') {
            $parts[] = 'Render the following text in the image exactly as written: "' . $text_in_image . '".';
        }

        $negative_prompt = trim((string) ($payload['negative_prompt'] ?? ''));
        if ($negative_prompt !== '') {
            $parts[] = 'Avoid the following: ' . $negative_prompt . '.';
        }

        if (!empty($payload['editorial_accuracy'])) {
            $parts[] = 'Use Google Search grounding to improve real-world accuracy for landmarks, products, brands, and current details.';
        }

        return implode("\n", $parts);
    }

    private function normalize_aspect_ratio($value) {
        $value = sanitize_text_field((string) $value);
        $allowed = array('1:1', '3:4', '4:3', '9:16', '16:9');
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return '16:9';
    }
}
