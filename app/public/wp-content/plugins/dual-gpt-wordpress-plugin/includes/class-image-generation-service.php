<?php
/**
 * Image generation orchestration service.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Image_Generation_Service {
    private $settings;
    private $registry;
    private $prompt_builder;

    public function __construct($settings = null, $registry = null, $prompt_builder = null) {
        $this->settings = $settings instanceof Dual_GPT_Image_Settings ? $settings : new Dual_GPT_Image_Settings();
        $this->registry = $registry instanceof Dual_GPT_Image_Provider_Registry ? $registry : new Dual_GPT_Image_Provider_Registry($this->settings);
        $this->prompt_builder = $prompt_builder instanceof Dual_GPT_Image_Prompt_Builder ? $prompt_builder : new Dual_GPT_Image_Prompt_Builder($this->settings);
    }

    public function get_public_config() {
        $config = $this->settings->get_config();
        return array(
            'text_provider' => $config['text_provider'],
            'image_provider' => $config['image_provider'],
            'fallback_providers' => $config['fallback_providers'],
            'default_preset_key' => $config['default_preset_key'],
            'presets' => $config['presets'],
            'house_style' => $config['house_style'],
            'workflow' => $config['workflow'],
            'provider_status' => $this->registry->get_provider_status(),
        );
    }

    public function recommend($payload) {
        return $this->prompt_builder->build_recommendation($payload);
    }

    public function generate($payload) {
        $payload = is_array($payload) ? $payload : array();
        $provider = sanitize_key($payload['provider'] ?? '');
        $dry_run = !empty($payload['dry_run']);
        $config = $this->settings->get_config();
        $store_in_media_library = array_key_exists('store_in_media_library', $payload)
            ? !empty($payload['store_in_media_library'])
            : !empty($config['workflow']['auto_store_media']);
        $set_featured_image = !empty($payload['set_featured_image']);

        $recommendation = $this->recommend($payload);
        $image_provider = $this->registry->get_image_provider($provider);

        if (!$image_provider) {
            return new WP_Error('image_provider_unavailable', 'Requested image provider is not available yet.', array('status' => 400));
        }

        $result = $image_provider->generate(array(
            'prompt' => sanitize_textarea_field($payload['prompt'] ?? $recommendation['prompt']),
            'negative_prompt' => sanitize_textarea_field($payload['negative_prompt'] ?? $recommendation['negative_prompt']),
            'size' => sanitize_text_field($payload['size'] ?? '1536x1024'),
            'quality' => sanitize_text_field($payload['quality'] ?? 'high'),
            'post_id' => intval($payload['post_id'] ?? 0),
            'alt_text' => sanitize_text_field($payload['alt_text'] ?? $recommendation['alt_text']),
            'caption' => sanitize_text_field($payload['caption'] ?? $recommendation['caption']),
            'dry_run' => $dry_run,
            'recommendation' => $recommendation,
            'title' => sanitize_text_field($payload['title'] ?? $recommendation['article_context']['title'] ?? ''),
        ));

        if (is_wp_error($result) || $dry_run || !$store_in_media_library) {
            return $result;
        }

        $attachments = $this->persist_generated_images($result, array(
            'post_id' => intval($payload['post_id'] ?? 0),
            'alt_text' => sanitize_text_field($payload['alt_text'] ?? $recommendation['alt_text']),
            'caption' => sanitize_text_field($payload['caption'] ?? $recommendation['caption']),
            'title' => sanitize_text_field($payload['title'] ?? $recommendation['article_context']['title'] ?? 'AI image'),
            'set_featured_image' => $set_featured_image,
        ));

        if (is_wp_error($attachments)) {
            return $attachments;
        }

        $result['attachments'] = $attachments;
        $result['stored_in_media_library'] = !empty($attachments);

        return $result;
    }

    private function persist_generated_images($provider_result, $context) {
        $assets = $this->extract_generated_assets($provider_result);
        if (empty($assets)) {
            return new WP_Error('image_assets_missing', 'Provider response did not contain a usable image asset.', array('status' => 500));
        }

        $attachments = array();
        foreach ($assets as $index => $asset) {
            $attachment = $this->persist_single_asset($asset, $context, $provider_result, $index);
            if (is_wp_error($attachment)) {
                return $attachment;
            }
            $attachments[] = $attachment;
        }

        return $attachments;
    }

    private function extract_generated_assets($provider_result) {
        $response = $provider_result['response'] ?? array();
        $assets = array();

        $candidates = isset($response['candidates']) && is_array($response['candidates']) ? $response['candidates'] : array();
        foreach ($candidates as $candidate) {
            $parts = $candidate['content']['parts'] ?? array();
            if (!is_array($parts)) {
                continue;
            }

            foreach ($parts as $part) {
                if (!is_array($part) || empty($part['inlineData']['data'])) {
                    continue;
                }

                $assets[] = array(
                    'type' => 'base64',
                    'data' => $part['inlineData']['data'],
                    'mime_type' => sanitize_mime_type($part['inlineData']['mimeType'] ?? 'image/png'),
                    'revised_prompt' => sanitize_text_field($part['text'] ?? ''),
                );
            }
        }

        $google_predictions = isset($response['predictions']) && is_array($response['predictions']) ? $response['predictions'] : array();
        foreach ($google_predictions as $prediction) {
            if (!is_array($prediction)) {
                continue;
            }

            if (!empty($prediction['bytesBase64Encoded'])) {
                $assets[] = array(
                    'type' => 'base64',
                    'data' => $prediction['bytesBase64Encoded'],
                    'mime_type' => sanitize_mime_type($prediction['mimeType'] ?? 'image/png'),
                    'revised_prompt' => sanitize_text_field($prediction['prompt'] ?? ''),
                );
            }
        }

        $items = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!empty($item['b64_json'])) {
                $assets[] = array(
                    'type' => 'base64',
                    'data' => $item['b64_json'],
                    'mime_type' => $this->guess_base64_image_mime_type($item['b64_json']),
                    'revised_prompt' => sanitize_text_field($item['revised_prompt'] ?? ''),
                );
                continue;
            }

            if (!empty($item['url'])) {
                $assets[] = array(
                    'type' => 'url',
                    'data' => esc_url_raw($item['url']),
                    'mime_type' => '',
                    'revised_prompt' => sanitize_text_field($item['revised_prompt'] ?? ''),
                );
            }
        }

        return $assets;
    }

    private function persist_single_asset($asset, $context, $provider_result, $index) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        if ($asset['type'] === 'base64') {
            $persisted = $this->persist_base64_asset($asset, $context, $index);
        } else {
            $persisted = $this->persist_url_asset($asset, $context, $index);
        }

        if (is_wp_error($persisted)) {
            return $persisted;
        }

        $attachment_id = $persisted['attachment_id'];
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $context['alt_text']);
        update_post_meta($attachment_id, '_dual_gpt_image_provider', sanitize_text_field($provider_result['provider'] ?? ''));
        update_post_meta($attachment_id, '_dual_gpt_image_prompt', sanitize_textarea_field($provider_result['request']['prompt'] ?? ''));
        update_post_meta($attachment_id, '_dual_gpt_image_request', wp_json_encode($provider_result['request'] ?? array()));
        update_post_meta($attachment_id, '_dual_gpt_image_response_meta', wp_json_encode(array(
            'revised_prompt' => $asset['revised_prompt'] ?? '',
            'mode' => $provider_result['mode'] ?? '',
        )));

        if ($context['caption'] !== '') {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_excerpt' => $context['caption'],
            ));
        }

        if (!empty($context['set_featured_image']) && !empty($context['post_id'])) {
            set_post_thumbnail(intval($context['post_id']), $attachment_id);
        }

        return $persisted;
    }

    private function persist_base64_asset($asset, $context, $index) {
        $binary = base64_decode((string) $asset['data'], true);
        if ($binary === false || $binary === '') {
            return new WP_Error('image_decode_failed', 'Failed to decode generated image payload.', array('status' => 500));
        }

        $extension = $this->mime_type_to_extension($asset['mime_type']);
        $filename = $this->build_filename($context['title'], $index, $extension);
        $uploaded = wp_upload_bits($filename, null, $binary);

        if (!empty($uploaded['error'])) {
            return new WP_Error('image_upload_failed', $uploaded['error'], array('status' => 500));
        }

        return $this->create_attachment_record($uploaded['file'], $uploaded['url'], $asset['mime_type'], $context);
    }

    private function persist_url_asset($asset, $context, $index) {
        $temp_file = download_url($asset['data'], 120);
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        $extension = pathinfo(parse_url($asset['data'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
        if ($extension === '') {
            $extension = 'png';
        }
        $filename = $this->build_filename($context['title'], $index, $extension);

        $file_array = array(
            'name' => $filename,
            'tmp_name' => $temp_file,
        );

        $attachment_id = media_handle_sideload($file_array, intval($context['post_id'] ?? 0), $context['title']);
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            return $attachment_id;
        }

        return array(
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
        );
    }

    private function create_attachment_record($file_path, $url, $mime_type, $context) {
        $attachment_id = wp_insert_attachment(array(
            'post_title' => sanitize_text_field($context['title']),
            'post_mime_type' => sanitize_mime_type($mime_type),
            'post_status' => 'inherit',
            'post_parent' => intval($context['post_id'] ?? 0),
        ), $file_path, intval($context['post_id'] ?? 0));

        if (is_wp_error($attachment_id) || !$attachment_id) {
            return is_wp_error($attachment_id)
                ? $attachment_id
                : new WP_Error('attachment_insert_failed', 'Failed to create attachment.', array('status' => 500));
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        return array(
            'attachment_id' => $attachment_id,
            'url' => $url,
        );
    }

    private function build_filename($title, $index, $extension) {
        $base = sanitize_title($title ?: 'ai-generated-image');
        $suffix = $index > 0 ? '-' . ($index + 1) : '';
        return trim($base . $suffix, '-') . '.' . ltrim($extension, '.');
    }

    private function guess_base64_image_mime_type($base64) {
        $binary = base64_decode((string) $base64, true);
        if ($binary === false || $binary === '') {
            return 'image/png';
        }

        if (function_exists('getimagesizefromstring')) {
            $image_info = @getimagesizefromstring($binary);
            if (!empty($image_info['mime'])) {
                return sanitize_mime_type($image_info['mime']);
            }
        }

        return 'image/png';
    }

    private function mime_type_to_extension($mime_type) {
        $map = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        );

        return $map[$mime_type] ?? 'png';
    }
}
