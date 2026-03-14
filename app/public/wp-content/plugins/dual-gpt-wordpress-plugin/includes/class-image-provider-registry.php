<?php
/**
 * Image provider registry for Dual-GPT.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Image_Provider_Registry {
    private $settings;

    public function __construct($settings = null) {
        $this->settings = $settings instanceof Dual_GPT_Image_Settings ? $settings : new Dual_GPT_Image_Settings();
    }

    public function get_text_provider($provider = '') {
        $config = $this->settings->get_config();
        $provider = sanitize_key($provider ?: $config['text_provider']);

        switch ($provider) {
            case 'openai':
                return new Dual_GPT_OpenAI_Image_Provider($this->settings);
            case 'anthropic':
            case 'google':
                return null;
        }

        return null;
    }

    public function get_image_provider($provider = '') {
        $config = $this->settings->get_config();
        $provider = sanitize_key($provider ?: $config['image_provider']);

        switch ($provider) {
            case 'google':
                return new Dual_GPT_Google_Image_Provider($this->settings);
            case 'openai':
                return new Dual_GPT_OpenAI_Image_Provider($this->settings);
            case 'anthropic':
                return null;
        }

        return null;
    }

    public function get_provider_status() {
        $config = $this->settings->get_config();
        $catalog = $this->settings->get_provider_catalog();
        $status = array();

        foreach ($catalog as $provider_key => $provider_meta) {
            $provider_config = $config['providers'][$provider_key] ?? array();
            $status[$provider_key] = array(
                'label' => $provider_meta['label'],
                'supports' => $provider_meta['supports'],
                'enabled' => !empty($provider_config['enabled']),
                'configured' => !empty($provider_config['api_key']),
            );
        }

        return $status;
    }
}
