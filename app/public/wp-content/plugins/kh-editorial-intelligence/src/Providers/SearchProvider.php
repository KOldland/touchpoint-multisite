<?php

namespace KH\Editorial\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SearchProvider
 * 
 * Centralized interface for various search engines (SerpAPI, Tavily, Bing, etc.).
 */
class SearchProvider {

    /**
     * Get the active provider chain.
     */
    public function get_active_chain() {
        $settings = \KH\Editorial\Core\LLMService::get_settings();
        $primary  = ! empty( $settings['search_primary'] ) ? $settings['search_primary'] : 'serpapi';
        $fallback = ! empty( $settings['search_fallback'] ) ? $settings['search_fallback'] : '';

        $chain = [ $primary ];
        if ( ! empty( $fallback ) ) {
            $parts = array_map( 'trim', explode( ',', $fallback ) );
            foreach ( $parts as $p ) {
                if ( ! in_array( $p, $chain ) ) {
                    $chain[] = $p;
                }
            }
        }
        return array_filter( $chain );
    }

    /**
     * Get configuration for a specific provider.
     */
    public function get_provider_config( $provider ) {
        $settings = \KH\Editorial\Core\LLMService::get_settings();
        
        switch ( $provider ) {
            case 'serpapi':
                return [
                    'api_key' => ! empty( $settings['serpapi_key'] ) ? $settings['serpapi_key'] : '',
                ];
            case 'tavily':
                return [
                    'api_key' => ! empty( $settings['tavily_key'] ) ? $settings['tavily_key'] : '',
                ];
            case 'dataforseo':
                return [
                    'login'    => ! empty( $settings['dataforseo_login'] ) ? $settings['dataforseo_login'] : '',
                    'password' => ! empty( $settings['dataforseo_password'] ) ? $settings['dataforseo_password'] : '',
                ];
            default:
                return [];
        }
    }

    /**
     * Perform a search using SerpAPI (Example Implementation).
     */
    public function search_serpapi( $query, $num = 10 ) {
        $config = $this->get_provider_config( 'serpapi' );
        if ( empty( $config['api_key'] ) ) {
            return new \WP_Error( 'missing_api_key', 'SerpAPI key not configured.' );
        }

        $url = add_query_arg( [
            'q'       => urlencode( $query ),
            'api_key' => $config['api_key'],
            'num'     => $num,
            'engine'  => 'google',
        ], 'https://serpapi.com/search' );

        $response = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }
}
