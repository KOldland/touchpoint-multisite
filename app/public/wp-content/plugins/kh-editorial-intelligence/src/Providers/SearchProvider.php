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
     * Perform a SERP search using the configured primary provider.
     *
     * @param string $query Search query string.
     * @param int    $num   Number of results to request.
     * @return array|\WP_Error
     */
    public function search( $query, $num = 10 ) {
        $settings = \KH\Editorial\Core\LLMService::get_settings();
        $primary = ! empty( $settings['search_primary'] ) ? $settings['search_primary'] : 'serpapi';

        switch ( $primary ) {
            case 'dataforseo':
                return $this->search_dataforseo( $query, $num );
            case 'serpapi':
            default:
                return $this->search_serpapi( $query, $num );
        }
    }

    /**
     * Perform a search using SerpAPI.
     *
     * @param string $query Search query.
     * @param int    $num   Number of results.
     * @return array|\WP_Error
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
            error_log( '[Editorial Search] SerpAPI request failed: ' . $response->get_error_message() );
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( $status < 200 || $status >= 300 ) {
            $error_msg = isset( $data['error'] ) ? $data['error'] : 'SerpAPI returned HTTP ' . $status;
            error_log( '[Editorial Search] SerpAPI error (HTTP ' . $status . '): ' . ( is_string( $error_msg ) ? $error_msg : wp_json_encode( $data ) ) );
            return new \WP_Error( 'serpapi_http_error', $error_msg, [ 'status' => $status ] );
        }

        if ( empty( $data ) || ( isset( $data['error'] ) && ! isset( $data['organic_results'] ) ) ) {
            $error_msg = isset( $data['error'] ) ? $data['error'] : 'SerpAPI returned empty or invalid response.';
            error_log( '[Editorial Search] SerpAPI invalid response: ' . wp_json_encode( $data ) );
            return new \WP_Error( 'serpapi_invalid_response', $error_msg );
        }

        return $data;
    }

    /**
     * Perform a SERP search using DataForSEO SERP Organic API.
     *
     * Uses the SERP Google Organic Live Advanced endpoint:
     * https://docs.dataforseo.com/v3/serp/google/organic/live/advanced/
     *
     * @param string $query Search query.
     * @param int    $depth Number of results pages (1-5).
     * @return array|\WP_Error
     */
    public function search_dataforseo( $query, $depth = 1 ) {
        $config = $this->get_provider_config( 'dataforseo' );
        if ( empty( $config['login'] ) || empty( $config['password'] ) ) {
            return new \WP_Error( 'missing_dataforseo_credentials', 'DataForSEO login/password not configured.' );
        }

        // Cache key to avoid repeated calls within the same hour
        $cache_key = 'kh_search_dfs_serp_' . md5( $query . '_' . $depth );
        $cached    = get_transient( $cache_key );
        if ( $cached ) {
            return $cached;
        }

        $depth     = max( 1, min( 5, (int) $depth ) );
        $payload   = [
            [
                'keyword'         => $query,
                'location_code'   => 2840,    // United States
                'language_code'   => 'en',
                'depth'           => $depth,
                'device'          => 'desktop',
                'os'              => 'windows',
            ],
        ];

        $url = 'https://api.dataforseo.com/v3/serp/google/organic/live/advanced';
        $request_args = [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $config['login'] . ':' . $config['password'] ),
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ];

        $response = wp_remote_post( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            error_log( '[Editorial Search] DataForSEO SERP request failed: ' . $response->get_error_message() );
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( $status < 200 || $status >= 300 ) {
            $message = isset( $data['status_message'] ) ? $data['status_message'] : 'DataForSEO SERP returned HTTP ' . $status;
            error_log( '[Editorial Search] DataForSEO SERP error (HTTP ' . $status . '): ' . $message );
            return new \WP_Error( 'dataforseo_serp_http_error', $message, [ 'status' => $status ] );
        }

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( '[Editorial Search] DataForSEO SERP invalid JSON response.' );
            return new \WP_Error( 'dataforseo_serp_invalid_json', 'DataForSEO SERP returned invalid JSON.' );
        }

        // Parse results into a SerpAPI-compatible format so callers don't break
        $organic_results = [];
        $tasks = $data['tasks'] ?? [];
        foreach ( $tasks as $task ) {
            $results = $task['result'] ?? [];
            foreach ( $results as $result_set ) {
                $items = $result_set['items'] ?? [];
                foreach ( $items as $item ) {
                    if ( $item['type'] === 'organic' ) {
                        $organic_results[] = [
                            'title'   => $item['title'] ?? '',
                            'link'    => $item['url'] ?? '',
                            'snippet' => $item['description'] ?? '',
                            'position' => $item['rank_absolute'] ?? 0,
                        ];
                    }
                }
            }
        }

        $output = [
            'organic_results' => $organic_results,
        ];

        // Cache for 1 hour (DataForSEO costs per request)
        set_transient( $cache_key, $output, HOUR_IN_SECONDS );

        return $output;
    }

    /**
     * Check if the configured search provider is ready.
     *
     * @return bool True if provider credentials are configured.
     */
    public function is_configured(): bool {
        $settings = \KH\Editorial\Core\LLMService::get_settings();
        $primary  = ! empty( $settings['search_primary'] ) ? $settings['search_primary'] : 'serpapi';

        switch ( $primary ) {
            case 'dataforseo':
                return ! empty( $settings['dataforseo_login'] ) && ! empty( $settings['dataforseo_password'] );
            case 'serpapi':
            default:
                return ! empty( $settings['serpapi_key'] );
        }
    }
}
