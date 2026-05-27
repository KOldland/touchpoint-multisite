<?php

namespace KH\Editorial\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LLMService
 * 
 * Provides a standardized way for suite plugins to access the OpenAI API.
 */
class LLMService {

    /**
     * Get the configured API key from the Orchestrator settings.
     *
     * @return string|null
     */
    public static function get_api_key() {
        $settings = get_option( 'kh_editorial_settings', [] );
        return ! empty( $settings['openai_api_key'] ) ? $settings['openai_api_key'] : null;
    }

    /**
     * Get the configured default model.
     *
     * @return string
     */
    public static function get_model() {
        $settings = get_option( 'kh_editorial_settings', [] );
        return ! empty( $settings['openai_model'] ) ? $settings['openai_model'] : 'gpt-4o-mini';
    }

    /**
     * Check if the API is configured.
     *
     * @return bool
     */
    public static function is_configured() {
        return ! empty( self::get_api_key() );
    }
}
