<?php

namespace KH\Editorial\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KeywordProvider
 * 
 * Handles interaction with DataForSEO for keyword metrics, suggestions, and trends.
 */
class KeywordProvider {

    private $base_url = 'https://api.dataforseo.com/v3/';

    /**
     * Get DataForSEO credentials from unified settings.
     * 
     * @return array
     */
    public function get_credentials() {
        $settings = get_option( 'kh_editorial_settings', [] );
        $login    = ! empty( $settings['dataforseo_login'] ) ? $settings['dataforseo_login'] : '';
        $password = ! empty( $settings['dataforseo_password'] ) ? $settings['dataforseo_password'] : '';

        // Fallback to constants/env for backward compatibility if needed
        if ( empty( $login ) && defined( 'DUAL_GPT_DATAFORSEO_LOGIN' ) ) {
            $login = DUAL_GPT_DATAFORSEO_LOGIN;
        }
        if ( empty( $password ) && defined( 'DUAL_GPT_DATAFORSEO_PASSWORD' ) ) {
            $password = DUAL_GPT_DATAFORSEO_PASSWORD;
        }

        return [
            'login'    => $login,
            'password' => $password,
        ];
    }

    /**
     * Get keyword suggestions.
     */
    public function keyword_suggestions( $seed, $limit = 50, $location = 'United States', $language = 'English' ) {
        $seed  = sanitize_text_field( $seed );
        $limit = max( 1, intval( $limit ) );

        if ( $seed === '' ) {
            return new \WP_Error( 'keyword_seed_empty', 'Keyword seed cannot be empty.' );
        }

        $payload = [
            [
                'keywords'      => [ $seed ],
                'location_name' => $location,
                'language_name' => $language,
                'limit'         => $limit,
            ]
        ];

        $response = $this->request( 'keywords_data/google_ads/keywords_for_keywords/live', $payload );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->extract_keyword_items( $response );
    }

    /**
     * Get search volume and metrics for a list of keywords.
     */
    public function keyword_metrics( $keywords, $location = 'United States', $language = 'English' ) {
        if ( ! is_array( $keywords ) ) {
            $keywords = [ $keywords ];
        }

        $keywords = array_values( array_filter( array_map( 'sanitize_text_field', $keywords ) ) );
        if ( empty( $keywords ) ) {
            return new \WP_Error( 'keyword_list_empty', 'Keyword list cannot be empty.' );
        }

        $payload = [
            [
                'keywords'      => $keywords,
                'location_name' => $location,
                'language_name' => $language,
            ]
        ];

        $response = $this->request( 'keywords_data/google_ads/search_volume/live', $payload );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->extract_keyword_items( $response );
    }

    /**
     * Internal request handler for DataForSEO API.
     */
    private function request( $endpoint, $payload ) {
        $credentials = $this->get_credentials();
        if ( empty( $credentials['login'] ) || empty( $credentials['password'] ) ) {
            return new \WP_Error( 'dataforseo_missing_credentials', 'DataForSEO credentials are not configured.' );
        }

        $cache_key = 'kh_editorial_dfseo_' . md5( $endpoint . wp_json_encode( $payload ) );
        $cached    = get_transient( $cache_key );
        if ( $cached ) {
            return $cached;
        }

        $url = $this->base_url . ltrim( $endpoint, '/' );
        $request_args = [
            'timeout'   => 30,
            'headers'   => [
                'Authorization' => 'Basic ' . base64_encode( $credentials['login'] . ':' . $credentials['password'] ),
                'Content-Type'  => 'application/json',
            ],
            'body'      => wp_json_encode( $payload ),
        ];

        $response = wp_remote_post( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status !== 200 ) {
            $body    = wp_remote_retrieve_body( $response );
            $decoded = json_decode( $body, true );
            $message = $decoded['status_message'] ?? $decoded['message'] ?? '';
            return new \WP_Error( 'dataforseo_http_error', "DataForSEO request failed with status $status ($message)", [ 'status' => $status ] );
        }

        $body    = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'dataforseo_invalid_json', 'DataForSEO response was invalid JSON.' );
        }

        set_transient( $cache_key, $decoded, 2 * HOUR_IN_SECONDS );

        return $decoded;
    }

    /**
     * Normalizes DataForSEO response into a flat structure.
     */
    private function extract_keyword_items( $response ) {
        $items = [];
        $tasks = $response['tasks'] ?? [];
        foreach ( $tasks as $task ) {
            $result = $task['result'] ?? [];
            foreach ( $result as $result_set ) {
                if ( isset( $result_set['items'] ) && is_array( $result_set['items'] ) ) {
                    $entries = $result_set['items'];
                } elseif ( isset( $result_set['keyword'] ) || isset( $result_set['search_volume'] ) ) {
                    $entries = [ $result_set ];
                } else {
                    $entries = [];
                }
                foreach ( $entries as $entry ) {
                    $keyword = $entry['keyword'] ?? ( $entry['query'] ?? '' );
                    if ( $keyword === '' ) {
                        continue;
                    }
                    $items[] = [
                        'keyword'       => $keyword,
                        'search_volume' => $entry['search_volume'] ?? ( $entry['volume'] ?? null ),
                        'cpc'           => $entry['cpc'] ?? ( $entry['average_cpc'] ?? null ),
                        'competition'   => $entry['competition'] ?? null,
                        'difficulty'    => $entry['difficulty'] ?? $entry['keyword_difficulty'] ?? null,
                    ];
                }
            }
        }
        return $items;
    }
}
