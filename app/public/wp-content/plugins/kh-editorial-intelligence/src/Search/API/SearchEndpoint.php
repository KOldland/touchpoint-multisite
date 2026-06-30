<?php
/**
 * Unified Search REST Endpoint
 *
 * POST /kh-editorial/v1/search
 *
 * Combines RAG (semantic search), Registry (fulltext), and SERP (external)
 * into a single queryable endpoint.
 *
 * @package KH\Editorial\Search\API
 */

namespace KH\Editorial\Search\API;

use KH\Editorial\Search\SearchOrchestrator;
use KH\Editorial\Search\Models\SearchQuery;
use KH\Editorial\Search\Sources\RAGSource;
use KH\Editorial\Search\Sources\RegistrySource;
use KH\Editorial\Search\Sources\SERPSource;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchEndpoint
 */
class SearchEndpoint {

    /**
     * Orchestrator instance.
     *
     * @var SearchOrchestrator
     */
    private SearchOrchestrator $orchestrator;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->orchestrator = new SearchOrchestrator();
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
                '/search',
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'handle' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => $this->get_args_schema(),
                )
            );
        } );
    }

    /**
     * Check permission for the search endpoint.
     *
     * @return bool
     */
    public function check_permission(): bool {
        // Internal editorial use — requires edit_posts capability.
        return is_user_logged_in() && current_user_can( 'edit_posts' );
    }

    /**
     * Handle a search request.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle( \WP_REST_Request $request ) {
        // Build the query from the request.
        $query = SearchQuery::from_rest_request( $request );

        // Validate query is not empty.
        if ( empty( trim( $query->get_query() ) ) ) {
            return new \WP_Error(
                'empty_query',
                __( 'Search query must not be empty.', 'kh-editorial-intelligence' ),
                array( 'status' => 400 )
            );
        }

        // Register sources.
        $this->register_sources();

        // Execute search.
        try {
            $result_set = $this->orchestrator->search( $query );
            return rest_ensure_response( $result_set->to_array() );
        } catch ( \Exception $e ) {
            error_log( '[KH Search] Orchestration failed: ' . $e->getMessage() );
            return new \WP_Error(
                'search_failed',
                __( 'Search failed. Please try again.', 'kh-editorial-intelligence' ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Register search sources with the orchestrator.
     *
     * @return void
     */
    private function register_sources(): void {
        // RAG source (semantic search over atomic articles).
        if ( class_exists( '\KH\Editorial\Search\Sources\RAGSource' ) ) {
            $this->orchestrator->register_source( new RAGSource() );
        }

        // Registry source (fulltext search over content_registry).
        if ( class_exists( '\KH\Editorial\Search\Sources\RegistrySource' ) ) {
            $this->orchestrator->register_source( new RegistrySource() );
        }

        // SERP source (external search via SearchProvider).
        if ( class_exists( '\KH\Editorial\Search\Sources\SERPSource' ) ) {
            $this->orchestrator->register_source( new SERPSource() );
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
            'sources' => array(
                'type'              => 'array',
                'required'          => false,
                'default'           => array( 'rag', 'registry', 'serp' ),
                'items'             => array(
                    'type' => 'string',
                    'enum' => array( 'rag', 'registry', 'serp' ),
                ),
            ),
            'blog_id' => array(
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
            ),
            'limit' => array(
                'type'              => 'integer',
                'required'          => false,
                'default'           => 10,
                'sanitize_callback' => function ( $value ) {
                    return min( 100, max( 1, (int) $value ) );
                },
            ),
            'page' => array(
                'type'              => 'integer',
                'required'          => false,
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ),
            'filters' => array(
                'type'              => 'object',
                'required'          => false,
                'default'           => array(),
            ),
            'content_types' => array(
                'type'              => 'array',
                'required'          => false,
                'default'           => array(),
                'items'             => array(
                    'type' => 'string',
                ),
            ),
        );
    }
}