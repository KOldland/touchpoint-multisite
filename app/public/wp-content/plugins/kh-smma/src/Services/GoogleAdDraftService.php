<?php
namespace KH_SMMA\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Google Ad Draft Service
 *
 * Generates Google Ads campaign drafts with ad groups, headlines, descriptions,
 * and targeting keywords. Uses LLM for content generation with deterministic fallback.
 *
 * Schema:
 * {
 *   "ad_groups": [
 *     {
 *       "keyword_cluster": "primary keywords",
 *       "headlines": ["30 char max", "30 char", "30 char"],
 *       "descriptions": ["90 char max", "90 char"],
 *       "final_url": "landing page URL",
 *       "final_url_with_utm": "URL with UTM params",
 *       "cpc_suggestion": 2.50
 *     }
 *   ]
 * }
 */
class GoogleAdDraftService {
    /**
     * Generate a Google Ads draft from input.
     *
     * @param array $input Generation input with post_id, keywords, etc.
     * @return array|WP_Error Google Ads draft or error
     */
    public function generate( array $input ) {
        /**
         * Allow integrations to override draft generation.
         *
         * @param array|null $draft Generated draft (null to use default logic)
         * @param array $input Input parameters
         */
        $draft = apply_filters( 'kh_smma_google_ad_draft_override', null, $input );

        if ( is_array( $draft ) && ! empty( $draft ) ) {
            return $this->validate_and_normalize_schema( $draft );
        }

        // Attempt LLM generation first
        $llm_draft = $this->generate_with_llm( $input );
        if ( ! is_wp_error( $llm_draft ) && ! empty( $llm_draft['ad_groups'] ) ) {
            return $llm_draft;
        }

        // Fallback to deterministic generation
        return $this->generate_fallback( $input );
    }

    /**
     * Generate Google Ads draft using LLM.
     *
     * @param array $input Input parameters
     * @return array|WP_Error Draft or error
     */
    private function generate_with_llm( array $input ) {
        $llm_available = class_exists( '\\Dual_GPT\\Dual_GPT_LLM_Client' );
        if ( ! $llm_available ) {
            return new WP_Error( 'no_llm', 'LLM client not available' );
        }

        $client = new \Dual_GPT\Dual_GPT_LLM_Client();
        if ( ! $client->has_api_key() ) {
            return new WP_Error( 'no_api_key', 'LLM API key not configured' );
        }

        $system = $this->build_system_prompt();
        $user   = $this->build_user_prompt( $input );

        $response = $client->call( $system, $user, array(
            'json_mode'   => true,
            'temperature' => 0.5,
            'max_tokens'  => 1500,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $parsed = $this->parse_llm_response( $response );
        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        return $this->validate_and_normalize_schema( $parsed );
    }

    /**
     * Build system prompt for Google Ads generation.
     *
     * @return string System prompt
     */
    private function build_system_prompt(): string {
        return 'You are Google Ads Draft Generator v1. Create ad groups with keyword clusters, compelling headlines (30 char max), descriptions (90 char max), and CPC suggestions. Return valid JSON matching the schema: {"ad_groups": [{"keyword_cluster": "...", "headlines": ["...", "...", "..."], "descriptions": ["...", "..."], "final_url": "...", "cpc_suggestion": 0.00}]}. Optimize for click-through rate and relevance score.';
    }

    /**
     * Build user prompt with input context.
     *
     * @param array $input Input parameters
     * @return string User prompt as JSON
     */
    private function build_user_prompt( array $input ): string {
        $post_id = (int) ( $input['post_id'] ?? 0 );
        $post = $post_id ? get_post( $post_id ) : null;

        return wp_json_encode( array(
            'post_id'          => $post_id,
            'title'            => $input['title'] ?? ( $post ? $post->post_title : '' ),
            'canonical_url'    => $input['canonical_url'] ?? ( $post ? get_permalink( $post ) : '' ),
            'blocks_summary'   => $input['blocks_summary'] ?? '',
            'keywords'         => $input['keywords'] ?? array(),
            'intent_scores'    => $input['intent_scores'] ?? array(),
            'phase_tag'        => $input['phase_tag'] ?? 'Attention',
            'tone'             => $input['tone'] ?? 'Authority',
            'geo_targets'      => $input['geo_targets'] ?? array(),
            'num_ad_groups'    => $input['num_ad_groups'] ?? 2,
        ) );
    }

    /**
     * Parse LLM response into structured draft.
     *
     * @param array $response LLM API response
     * @return array|WP_Error Parsed draft or error
     */
    private function parse_llm_response( array $response ) {
        $body = $response['choices'][0]['message']['content'] ?? '';
        $decoded = json_decode( $body, true );

        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'invalid_json', 'LLM response is not valid JSON' );
        }

        if ( empty( $decoded['ad_groups'] ) || ! is_array( $decoded['ad_groups'] ) ) {
            return new WP_Error( 'missing_ad_groups', 'LLM response missing ad_groups array' );
        }

        return $decoded;
    }

    /**
     * Generate deterministic fallback draft.
     *
     * @param array $input Input parameters
     * @return array Google Ads draft
     */
    private function generate_fallback( array $input ): array {
        $post_id = (int) ( $input['post_id'] ?? 0 );
        $post = $post_id ? get_post( $post_id ) : null;
        $title = $input['title'] ?? ( $post ? $post->post_title : 'Learn More' );

        // Fallback URL if WordPress functions not available
        $default_url = function_exists( 'home_url' ) ? home_url() : 'https://example.com';
        $canonical_url = $input['canonical_url'] ?? ( $post ? get_permalink( $post ) : $default_url );

        $keywords = $input['keywords'] ?? array();
        $num_ad_groups = max( 1, min( 5, (int) ( $input['num_ad_groups'] ?? 2 ) ) );

        // Split keywords into clusters
        $keyword_clusters = $this->create_keyword_clusters( $keywords, $num_ad_groups );

        $ad_groups = array();
        foreach ( $keyword_clusters as $index => $cluster ) {
            $cluster_label = implode( ', ', array_slice( $cluster, 0, 3 ) );
            if ( empty( $cluster_label ) ) {
                $cluster_label = 'general';
            }

            $ad_groups[] = array(
                'keyword_cluster' => $cluster_label,
                'headlines' => array(
                    $this->truncate_headline( $title ),
                    $this->truncate_headline( 'Discover ' . $title ),
                    $this->truncate_headline( 'Expert Insights' ),
                ),
                'descriptions' => array(
                    $this->truncate_description( 'Explore expert insights and actionable strategies. Click to learn more.' ),
                    $this->truncate_description( 'Get the latest industry analysis and professional guidance.' ),
                ),
                'final_url' => $canonical_url,
                'final_url_with_utm' => $this->add_utm_params( $canonical_url, 'google_ads', 'cpc', 'ad_group_' . ( $index + 1 ) ),
                'cpc_suggestion' => $this->estimate_cpc( $cluster ),
            );
        }

        return array(
            'ad_groups' => $ad_groups,
            'metadata' => array(
                'generated_by' => 'smma-google-ads-v1',
                'model' => 'fallback',
                'created_at' => time(),
            ),
        );
    }

    /**
     * Validate and normalize draft schema.
     *
     * @param array $draft Draft to validate
     * @return array Normalized draft
     */
    private function validate_and_normalize_schema( array $draft ): array {
        if ( empty( $draft['ad_groups'] ) || ! is_array( $draft['ad_groups'] ) ) {
            return array( 'ad_groups' => array() );
        }

        $normalized = array();
        foreach ( $draft['ad_groups'] as $group ) {
            // Ensure required fields
            $keyword_cluster = sanitize_text_field( $group['keyword_cluster'] ?? 'general' );
            $headlines = array_slice( (array) ( $group['headlines'] ?? array() ), 0, 15 ); // Google allows up to 15
            $descriptions = array_slice( (array) ( $group['descriptions'] ?? array() ), 0, 4 ); // Google allows up to 4
            $final_url = esc_url_raw( $group['final_url'] ?? home_url() );
            $cpc_suggestion = (float) ( $group['cpc_suggestion'] ?? 0.00 );

            // Truncate to character limits
            $headlines = array_map( array( $this, 'truncate_headline' ), $headlines );
            $descriptions = array_map( array( $this, 'truncate_description' ), $descriptions );

            // Ensure minimum counts
            while ( count( $headlines ) < 3 ) {
                $headlines[] = $this->truncate_headline( 'Learn More' );
            }
            while ( count( $descriptions ) < 2 ) {
                $descriptions[] = $this->truncate_description( 'Expert insights and actionable strategies.' );
            }

            // Add UTM if not present
            $final_url_with_utm = ! empty( $group['final_url_with_utm'] )
                ? esc_url_raw( $group['final_url_with_utm'] )
                : $this->add_utm_params( $final_url, 'google_ads', 'cpc', sanitize_title( $keyword_cluster ) );

            $normalized[] = array(
                'keyword_cluster'      => $keyword_cluster,
                'headlines'            => array_values( array_filter( $headlines ) ),
                'descriptions'         => array_values( array_filter( $descriptions ) ),
                'final_url'            => $final_url,
                'final_url_with_utm'   => $final_url_with_utm,
                'cpc_suggestion'       => $cpc_suggestion,
            );
        }

        return array(
            'ad_groups' => $normalized,
            'metadata' => $draft['metadata'] ?? array(
                'generated_by' => 'smma-google-ads-v1',
                'created_at' => time(),
            ),
        );
    }

    /**
     * Create keyword clusters for ad groups.
     *
     * @param array $keywords Keywords to cluster
     * @param int $num_clusters Number of clusters to create
     * @return array Array of keyword clusters
     */
    private function create_keyword_clusters( array $keywords, int $num_clusters ): array {
        if ( empty( $keywords ) ) {
            return array_fill( 0, $num_clusters, array( 'general' ) );
        }

        $keywords = array_filter( array_map( 'trim', $keywords ) );
        $chunk_size = max( 1, (int) ceil( count( $keywords ) / $num_clusters ) );

        return array_chunk( $keywords, $chunk_size );
    }

    /**
     * Truncate text to Google Ads headline limit (30 characters).
     *
     * @param string $text Text to truncate
     * @return string Truncated text
     */
    private function truncate_headline( string $text ): string {
        $text = sanitize_text_field( $text );
        if ( mb_strlen( $text ) <= 30 ) {
            return $text;
        }
        return mb_substr( $text, 0, 27 ) . '...';
    }

    /**
     * Truncate text to Google Ads description limit (90 characters).
     *
     * @param string $text Text to truncate
     * @return string Truncated text
     */
    private function truncate_description( string $text ): string {
        $text = sanitize_text_field( $text );
        if ( mb_strlen( $text ) <= 90 ) {
            return $text;
        }
        return mb_substr( $text, 0, 87 ) . '...';
    }

    /**
     * Add UTM parameters to URL for tracking.
     *
     * @param string $url Base URL
     * @param string $source UTM source
     * @param string $medium UTM medium
     * @param string $campaign UTM campaign
     * @return string URL with UTM parameters
     */
    private function add_utm_params( string $url, string $source, string $medium, string $campaign ): string {
        // Fallback sanitization if WordPress functions not available
        $sanitize_field = function_exists( 'sanitize_text_field' ) ? 'sanitize_text_field' : 'trim';
        $sanitize_slug = function_exists( 'sanitize_title' ) ? 'sanitize_title' : function( $str ) {
            return strtolower( preg_replace( '/[^a-z0-9_-]+/i', '_', trim( $str ) ) );
        };

        $params = array(
            'utm_source'   => $sanitize_field( $source ),
            'utm_medium'   => $sanitize_field( $medium ),
            'utm_campaign' => $sanitize_slug( $campaign ),
        );

        // Use WordPress add_query_arg if available, otherwise build URL manually
        if ( function_exists( 'add_query_arg' ) ) {
            return add_query_arg( $params, $url );
        }

        // Fallback: manually append query params
        $query_string = http_build_query( $params );
        $separator = ( strpos( $url, '?' ) !== false ) ? '&' : '?';
        return $url . $separator . $query_string;
    }

    /**
     * Estimate CPC (cost per click) based on keywords.
     *
     * @param array $keywords Keywords in cluster
     * @return float Estimated CPC in dollars
     */
    private function estimate_cpc( array $keywords ): float {
        // Simple heuristic: base CPC $1.50, adjusted by keyword count and length
        $base_cpc = 1.50;
        $keyword_count = count( $keywords );

        // More keywords = more competitive = higher CPC
        $competition_multiplier = 1 + ( $keyword_count * 0.1 );

        // Longer keywords (more specific) = lower CPC
        $avg_length = 0;
        if ( $keyword_count > 0 ) {
            $total_length = array_sum( array_map( 'strlen', $keywords ) );
            $avg_length = $total_length / $keyword_count;
        }
        $specificity_discount = $avg_length > 15 ? 0.8 : 1.0;

        $estimated = $base_cpc * $competition_multiplier * $specificity_discount;

        // Round to 2 decimals, min $0.50, max $10.00
        return round( max( 0.50, min( 10.00, $estimated ) ), 2 );
    }
}
