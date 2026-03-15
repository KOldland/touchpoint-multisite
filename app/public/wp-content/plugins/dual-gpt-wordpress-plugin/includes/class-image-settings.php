<?php
/**
 * Image settings helper for Dual-GPT.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Image_Settings {
    public function get_default_presets() {
        return array(
            'layered_editorial_cutout' => array(
                'label' => 'Layered Editorial Cutout',
                'description' => 'Paper-cut editorial illustration with geometric forms, tactile texture, and symbolic business storytelling.',
                'art_direction' => 'Create a stylised editorial illustration using layered cut-paper, card, wood-block, or collage-inspired forms. Keep the composition clean, geometric, symbolic, and publication-ready.',
                'brand_palette' => 'Warm kraft beige, muted teal, deep blue, orange, yellow, sage, terracotta, soft coral.',
                'negative_prompt' => 'photorealism, glossy 3D render, cluttered background, stock-photo realism, cinematic lighting, neon palette, hyper-detail, text artifacts, watermark, logo',
                'aspect_ratio' => '16:9',
                'realism' => 20,
                'illustration_strength' => 88,
                'brand_strictness' => 70,
                'allow_text_overlays' => false,
                'prefer_people' => false,
                'prefer_clean_backgrounds' => true,
                'open_instructions' => 'Use simplified shapes, soft shadows, tactile material texture, and strong negative space. Translate complex editorial subjects into symbolic metaphors rather than literal scenes.',
            ),
        );
    }

    public function get_provider_catalog() {
        return array(
            'openai' => array(
                'label' => 'OpenAI',
                'supports' => array('text', 'image'),
            ),
            'anthropic' => array(
                'label' => 'Anthropic',
                'supports' => array('text'),
            ),
            'google' => array(
                'label' => 'Google',
                'supports' => array('text', 'image'),
            ),
        );
    }

    public function get_default_config() {
        return array(
            'text_provider' => 'openai',
            'image_provider' => 'google',
            'fallback_providers' => array('openai'),
            'default_preset_key' => 'layered_editorial_cutout',
            'presets' => $this->get_default_presets(),
            'providers' => array(
                'openai' => array(
                    'enabled' => false,
                    'api_key' => '',
                    'text_model' => 'gpt-4.1',
                    'image_model' => 'gpt-image-1',
                ),
                'anthropic' => array(
                    'enabled' => false,
                    'api_key' => '',
                    'text_model' => 'claude-3-7-sonnet-latest',
                ),
                'google' => array(
                    'enabled' => true,
                    'api_key' => '',
                    'text_model' => 'gemini-2.0-flash',
                    'image_model' => 'gemini-3.1-flash-image-preview',
                ),
            ),
            'house_style' => $this->get_default_presets()['layered_editorial_cutout'],
            'workflow' => array(
                'generator_capability' => 'edit_posts',
                'manager_capability' => 'manage_options',
                'max_variants' => 4,
                'auto_store_media' => true,
                'allow_featured_image_replace' => true,
                'moderation_level' => 'standard',
            ),
        );
    }

    public function get_config() {
        $saved = get_option('dual_gpt_image_settings', array());
        if (!is_array($saved)) {
            $saved = array();
        }

        return $this->merge_recursive_distinct($this->get_default_config(), $saved);
    }

    public function sanitize_config($value) {
        $defaults = $this->get_default_config();
        $catalog = $this->get_provider_catalog();
        $value = is_array($value) ? $value : array();

        $text_provider = sanitize_key($value['text_provider'] ?? $defaults['text_provider']);
        if (!isset($catalog[$text_provider])) {
            $text_provider = $defaults['text_provider'];
        }

        $image_provider = sanitize_key($value['image_provider'] ?? $defaults['image_provider']);
        if (!isset($catalog[$image_provider])) {
            $image_provider = $defaults['image_provider'];
        }

        $presets = $defaults['presets'];
        $default_preset_key = sanitize_key($value['default_preset_key'] ?? $defaults['default_preset_key']);
        if (!isset($presets[$default_preset_key])) {
            $default_preset_key = $defaults['default_preset_key'];
        }

        $fallbacks = array();
        foreach ((array) ($value['fallback_providers'] ?? $defaults['fallback_providers']) as $provider) {
            $provider = sanitize_key($provider);
            if (isset($catalog[$provider]) && !in_array($provider, $fallbacks, true)) {
                $fallbacks[] = $provider;
            }
        }

        $providers = array();
        foreach ($defaults['providers'] as $provider_key => $provider_defaults) {
            $provider_input = is_array($value['providers'][$provider_key] ?? null) ? $value['providers'][$provider_key] : array();
            $providers[$provider_key] = array(
                'enabled' => !empty($provider_input['enabled']),
                'api_key' => sanitize_text_field($provider_input['api_key'] ?? $provider_defaults['api_key']),
            );

            foreach ($provider_defaults as $field => $default_value) {
                if (in_array($field, array('enabled', 'api_key'), true)) {
                    continue;
                }
                $providers[$provider_key][$field] = sanitize_text_field($provider_input[$field] ?? $default_value);
            }
        }

        $presets_input = is_array($value['presets'] ?? null) ? $value['presets'] : array();
        $sanitized_presets = array();
        foreach ($presets as $preset_key => $preset_defaults) {
            $preset_input = is_array($presets_input[$preset_key] ?? null) ? $presets_input[$preset_key] : array();
            $sanitized_presets[$preset_key] = $this->sanitize_style_profile($preset_input, $preset_defaults);
            $sanitized_presets[$preset_key]['label'] = sanitize_text_field($preset_input['label'] ?? $preset_defaults['label']);
            $sanitized_presets[$preset_key]['description'] = sanitize_textarea_field($preset_input['description'] ?? $preset_defaults['description']);
        }

        $house_style = $sanitized_presets[$default_preset_key];

        $workflow_input = is_array($value['workflow'] ?? null) ? $value['workflow'] : array();
        $workflow = array(
            'generator_capability' => sanitize_key($workflow_input['generator_capability'] ?? $defaults['workflow']['generator_capability']),
            'manager_capability' => sanitize_key($workflow_input['manager_capability'] ?? $defaults['workflow']['manager_capability']),
            'max_variants' => max(1, min(8, intval($workflow_input['max_variants'] ?? $defaults['workflow']['max_variants']))),
            'auto_store_media' => !empty($workflow_input['auto_store_media']),
            'allow_featured_image_replace' => !empty($workflow_input['allow_featured_image_replace']),
            'moderation_level' => sanitize_key($workflow_input['moderation_level'] ?? $defaults['workflow']['moderation_level']),
        );

        return array(
            'text_provider' => $text_provider,
            'image_provider' => $image_provider,
            'fallback_providers' => $fallbacks,
            'default_preset_key' => $default_preset_key,
            'presets' => $sanitized_presets,
            'providers' => $providers,
            'house_style' => $house_style,
            'workflow' => $workflow,
        );
    }

    private function sanitize_percent($value) {
        return max(0, min(100, intval($value)));
    }

    private function sanitize_style_profile($input, $defaults) {
        return array(
            'art_direction' => sanitize_textarea_field($input['art_direction'] ?? $defaults['art_direction']),
            'brand_palette' => sanitize_text_field($input['brand_palette'] ?? $defaults['brand_palette']),
            'negative_prompt' => sanitize_textarea_field($input['negative_prompt'] ?? $defaults['negative_prompt']),
            'aspect_ratio' => sanitize_text_field($input['aspect_ratio'] ?? $defaults['aspect_ratio']),
            'realism' => $this->sanitize_percent($input['realism'] ?? $defaults['realism']),
            'illustration_strength' => $this->sanitize_percent($input['illustration_strength'] ?? $defaults['illustration_strength']),
            'brand_strictness' => $this->sanitize_percent($input['brand_strictness'] ?? $defaults['brand_strictness']),
            'allow_text_overlays' => !empty($input['allow_text_overlays']),
            'prefer_people' => !empty($input['prefer_people']),
            'prefer_clean_backgrounds' => !empty($input['prefer_clean_backgrounds']),
            'open_instructions' => sanitize_textarea_field($input['open_instructions'] ?? $defaults['open_instructions']),
        );
    }

    private function merge_recursive_distinct($defaults, $saved) {
        foreach ($saved as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = $this->merge_recursive_distinct($defaults[$key], $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
