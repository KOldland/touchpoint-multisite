<?php
/**
 * Public Search REST Endpoint
 *
 * POST /kh-editorial/v1/public/search
 *
 * Public-facing search endpoint with IP-based rate limiting.
 * Queries RAG (semantic) + Registry (fulltext) — no SERP (too expensive for public).
 * No authentication required.
 *
 * @package KH\Editorial\Search\API
 */

namespace KH\Editorial\Search\API;

use KH\Editorial\Search\SearchOrchestrator;
use KH\Editorial\Search\Models\SearchQuery;
use KH\Editorial\Search\Sources\RAGSource;
use KH\Editorial\Search\Sources\RegistrySource;
use KH\Editorial\Search\Services\AnswerSynthesizer;
use KH\Editorial\Search\Services\SearchLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Class PublicSearchEndpoint
 */
class PublicSearchEndpoint {

    /**
     * Rate-limit: max requests per window.
     */
    const RATE_LIMIT_MAX = 10;

    /**
     * Rate-limit window in seconds.
     */
    const RATE_LIMIT_WINDOW = 60;

    /**
     * Maximum query length.
     */
    const MAX_QUERY_LENGTH = 500;

    /**
     * Orchestrator instance.
     *
     * @var SearchOrchestrator
     */
    private SearchOrchestrator $orchestrator;

    /**
     * Logger instance.
     *
     * @var SearchLogger
     */
    private SearchLogger $logger;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->orchestrator = new SearchOrchestrator();
        $this->logger       = new SearchLogger();
    }

    /**
     * Register the REST route.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'rest_api_init', function () {
            register_rest_route(
                'kh-editorial/v1',
                '/public/search',
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'handle' ),
                    'permission_callback' => '__return_true',
                    'args'                => $this->get_args_schema(),
                )
            );
        } );
    }

    /**
     * Handle a public search request.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle( \WP_REST_Request $request ) {
        $start_time  = microtime( true );
        $client_ip   = $this->get_client_ip();
        $rate_limited = false;

        // Rate limit by IP.
        $rate_error = $this->check_rate_limit();
        if ( is_wp_error( $rate_error ) ) {
            $rate_limited = true;
            $this->logger->log_query(
                query:        $request->get_param( 'query' ) ?: '',
                source:       'public',
                blog_id:      (int) get_current_blog_id(),
                result_count: 0,
                latency_ms:   0,
                synthesized:  false,
                success:      false,
                rate_limited: true,
                user_ip:      $client_ip
            );
            return $rate_error;
        }

        $query_text = trim( $request->get_param( 'query' ) );
        $query_text = mb_substr( $query_text, 0, self::MAX_QUERY_LENGTH );

        if ( empty( $query_text ) ) {
            return new \WP_Error(
                'empty_query',
                __( 'Search query must not be empty.', 'kh-editorial-intelligence' ),
                array( 'status' => 400 )
            );
        }

        // Build query — public search uses RAG + Registry only (no SERP).
        $query = new SearchQuery(
            query:   $query_text,
            sources: [ 'rag', 'registry' ],
            blog_id: null,
            limit:   10,
            page:    1,
            filters: [],
            content_types: []
        );

        // Register sources.
        $this->register_sources();

        try {
            $result_set = $this->orchestrator->search( $query );
            $results_array = $result_set->to_array();
            $search_results = $result_set->get_results();

            // Extract the best excerpt for fallback.
            $best_excerpt = $this->extract_best_excerpt( $search_results );

            // Optionally synthesize a human-readable answer via LLM.
            $answer = $best_excerpt;
            $settings = get_option( 'kh_editorial_settings', [] );
            if ( ! empty( $settings['enable_ai_answers'] )
                && class_exists( '\KH\Editorial\Search\Services\AnswerSynthesizer' )
                && ! empty( $search_results )
            ) {
                $synthesizer = new AnswerSynthesizer();
                $answer = $synthesizer->synthesize( $query_text, $search_results, $best_excerpt );
            }

            // Determine if answer synthesis was used and if it succeeded.
            $synthesis_used  = ! empty( $settings['enable_ai_answers'] ) && ! empty( $search_results );
            $synthesis_ok    = $synthesis_used && $answer !== $best_excerpt;

            $response = array_merge( $results_array, array(
                'answer' => $answer,
            ) );

            // Increment rate-limit counter.
            $this->increment_rate_limit();

            // Log the query.
            $elapsed_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );
            $this->logger->log_query(
                query:        $query_text,
                source:       'public',
                blog_id:      (int) get_current_blog_id(),
                result_count: count( $search_results ),
                latency_ms:   $elapsed_ms,
                synthesized:  $synthesis_used,
                success:      $synthesis_ok,
                rate_limited: false,
                user_ip:      $client_ip
            );

            return rest_ensure_response( $response );

        } catch ( \Exception $e ) {
            error_log( '[KH Public Search] Failed: ' . $e->getMessage() );
            return new \WP_Error(
                'search_failed',
                __( 'Search failed. Please try again.', 'kh-editorial-intelligence' ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Extract the best excerpt from results for the "answer" display.
     *
     * @param array $results Array of SearchResult objects.
     * @return string
     */
    private function extract_best_excerpt( array $results ): string {
        if ( empty( $results ) ) {
            return '';
        }

        // Take the highest-scored result's excerpt.
        $best = $results[0];
        $excerpt = $best->to_array()['excerpt'] ?? '';

        // If no excerpt, try to get the post excerpt directly.
        if ( empty( $excerpt ) ) {
            $id_parts = explode( ':', $best->get_id() );
            $post_id = (int) end( $id_parts );
            if ( $post_id > 0 ) {
                $post = get_post( $post_id );
                if ( $post ) {
                    $excerpt = ! empty( $post->post_excerpt )
                        ? $post->post_excerpt
                        : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
                }
            }
        }

        return $excerpt;
    }

    /**
     * Register search sources with the orchestrator.
     *
     * @return void
     */
    private function register_sources(): void {
        if ( class_exists( '\KH\Editorial\Search\Sources\RAGSource' ) ) {
            $this->orchestrator->register_source( new RAGSource() );
        }
        if ( class_exists( '\KH\Editorial\Search\Sources\RegistrySource' ) ) {
            $this->orchestrator->register_source( new RegistrySource() );
        }
    }

    /**
     * Get the argument schema for the REST endpoint.
     *
     * @return array
     */
    private function get_args_schema(): array {
        return array(
            'query' => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ( $value ) {
                    return is_string( $value ) && mb_strlen( trim( $value ) ) > 0;
                },
            ),
        );
    }

    /**
     * Check per-IP rate limit using WP transients.
     *
     * @return true|\WP_Error
     */
    private function check_rate_limit() {
        $key   = 'khm_public_search_' . md5( $this->get_client_ip() );
        $count = (int) get_transient( $key );

        if ( $count >= self::RATE_LIMIT_MAX ) {
            return new \WP_Error(
                'rate_limited',
                __( 'Too many search requests. Please wait a moment.', 'kh-editorial-intelligence' ),
                array( 'status' => 429 )
            );
        }

        return true;
    }

    /**
     * Increment per-IP rate-limit counter.
     *
     * @return void
     */
    private function increment_rate_limit(): void {
        $ip    = $this->get_client_ip();
        $key   = 'khm_public_search_' . md5( $ip );
        $count = (int) get_transient( $key );
        set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );
    }

    /**
     * Get a safe client IP for rate-limiting.
     *
     * @return string
     */
    private function get_client_ip(): string {
        return isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : 'unknown';
    }
}