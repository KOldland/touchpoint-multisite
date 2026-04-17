<?php
/**
 * Atomic RAG Search Endpoint
 *
 * POST /wp-json/khm/v1/atomic/search
 *
 * Implements a Retrieval-Augmented Generation (RAG) search over atomic articles:
 *
 *   1. Embeds the query string with text-embedding-3-small
 *   2. Loads all stored embeddings from wp_atomic_embeddings
 *   3. Ranks by cosine similarity → top-5 atomic articles
 *   4. Calls GPT-4o with the retrieved content to synthesise a grounded answer
 *   5. Returns {answer, sources[]} JSON
 *
 * Security:
 *   - Query capped at 500 chars
 *   - Per-IP rate limit via transients (10 requests / 60 s)
 *   - No user data stored; query is only sent to OpenAI
 *
 * @package KHM\Atomic
 */

namespace KHM\Atomic;

use KHM\GEO\LLMClient;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic Search Endpoint
 */
class AtomicSearchEndpoint {

    /**
     * Maximum query length in characters.
     */
    const MAX_QUERY_LENGTH = 500;

    /**
     * Number of top atomic articles to retrieve.
     */
    const TOP_K = 5;

    /**
     * Rate-limit: max requests per window.
     */
    const RATE_LIMIT_MAX = 10;

    /**
     * Rate-limit window in seconds.
     */
    const RATE_LIMIT_WINDOW = 60;

    /**
     * Embedding service instance.
     *
     * @var AtomicEmbeddingService
     */
    private AtomicEmbeddingService $embedding_service;

    /**
     * LLM client instance.
     *
     * @var LLMClient
     */
    private LLMClient $llm;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->embedding_service = new AtomicEmbeddingService();
        $this->llm               = new LLMClient();
    }

    /**
     * Register the REST route.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'rest_api_init', function () {
            register_rest_route(
                'khm/v1',
                '/atomic/search',
                array(
                    'methods'             => 'POST',
                    'callback'            => array( $this, 'handle' ),
                    'permission_callback' => '__return_true', // Public endpoint
                    'args'                => array(
                        'query' => array(
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => function ( $value ) {
                                return is_string( $value ) && mb_strlen( trim( $value ) ) > 0;
                            },
                        ),
                    ),
                )
            );
        } );
    }

    /**
     * Handle a search request.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle( \WP_REST_Request $request ) {
        // Rate limit by IP.
        $rate_error = $this->check_rate_limit();
        if ( is_wp_error( $rate_error ) ) {
            return $rate_error;
        }

        $query = mb_substr( trim( $request->get_param( 'query' ) ), 0, self::MAX_QUERY_LENGTH );

        if ( empty( $query ) ) {
            return new \WP_Error( 'empty_query', __( 'Query must not be empty.', 'khm-membership' ), array( 'status' => 400 ) );
        }

        if ( ! $this->llm->has_api_key() ) {
            return new \WP_Error( 'no_api_key', __( 'Search is not configured.', 'khm-membership' ), array( 'status' => 503 ) );
        }

        // 1. Embed the query.
        $query_embedding = $this->embedding_service->embed_now_text( $query );
        if ( is_wp_error( $query_embedding ) ) {
            return new \WP_Error( 'embed_failed', __( 'Failed to process query.', 'khm-membership' ), array( 'status' => 502 ) );
        }

        // 2. Load all stored embeddings and rank by cosine similarity.
        $all_embeddings = $this->embedding_service->get_all_embeddings();

        if ( empty( $all_embeddings ) ) {
            return rest_ensure_response( array(
                'answer'  => __( 'No content has been indexed yet. Please generate some atomic articles first.', 'khm-membership' ),
                'sources' => array(),
            ) );
        }

        $scored = array();
        foreach ( $all_embeddings as $post_id => $embedding ) {
            $scored[ $post_id ] = $this->embedding_service->cosine_similarity( $query_embedding, $embedding );
        }

        arsort( $scored );
        $top_ids = array_slice( array_keys( $scored ), 0, self::TOP_K );

        // 3. Build context from top atomic articles.
        $context_blocks = array();
        $sources        = array();

        foreach ( $top_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                continue;
            }

            $parent_id = (int) get_post_meta( $post_id, '_atomic_parent_id', true );

            $context_blocks[] = sprintf(
                "### %s\n%s\n%s",
                $post->post_title,
                $post->post_excerpt,
                wp_strip_all_tags( $post->post_content )
            );

            $source = array(
                'id'    => $post_id,
                'title' => $post->post_title,
                'url'   => get_permalink( $post_id ),
            );

            if ( $parent_id ) {
                $source['parent_title'] = get_the_title( $parent_id );
                $source['parent_url']   = get_permalink( $parent_id );
            }

            $sources[] = $source;
        }

        // 4. GPT synthesis.
        $answer = $this->synthesise_answer( $query, $context_blocks );
        if ( is_wp_error( $answer ) ) {
            return new \WP_Error( 'synthesis_failed', __( 'Failed to generate answer.', 'khm-membership' ), array( 'status' => 502 ) );
        }

        // 5. Increment rate-limit counter.
        $this->increment_rate_limit();

        return rest_ensure_response( array(
            'answer'  => $answer,
            'sources' => $sources,
        ) );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Synthesise a grounded answer from retrieved context using GPT.
     *
     * @param string   $query          The user's query.
     * @param string[] $context_blocks Markdown sections from top-k atomics.
     * @return string|\WP_Error
     */
    private function synthesise_answer( string $query, array $context_blocks ) {
        $context = implode( "\n\n---\n\n", $context_blocks );

        $system_prompt = <<<PROMPT
You are a helpful assistant answering questions based ONLY on the provided knowledge base articles.

Rules:
- Answer only from the provided articles. Do NOT use outside knowledge.
- If the articles do not contain enough information, say so clearly.
- Keep the answer concise: 2–4 short paragraphs, max 300 words.
- Write in plain English. No bullet-point lists unless the question explicitly asks for steps.
- Do not mention "atomic articles", "knowledge base", or the internal system.
PROMPT;

        $user_prompt = "KNOWLEDGE BASE:\n{$context}\n\nQUESTION: {$query}";

        $result = $this->llm->call(
            $system_prompt,
            $user_prompt,
            array(
                'model'       => 'gpt-4o',
                'temperature' => 0.2,
                'max_tokens'  => 600,
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $result['content'] ?? '';
    }

    /**
     * Check per-IP rate limit using WP transients.
     *
     * @return true|\WP_Error
     */
    private function check_rate_limit() {
        $key = 'khm_atomic_search_' . md5( $this->get_client_ip() );
        $count = (int) get_transient( $key );

        if ( $count >= self::RATE_LIMIT_MAX ) {
            return new \WP_Error(
                'rate_limited',
                __( 'Too many search requests. Please wait a moment.', 'khm-membership' ),
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
        $ip  = $this->get_client_ip();
        $key = 'khm_atomic_search_' . md5( $ip );
        $count = (int) get_transient( $key );
        set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );
    }

    /**
     * Get a safe client IP for rate-limiting.
     * Uses REMOTE_ADDR — does NOT trust X-Forwarded-For without validation.
     *
     * @return string
     */
    private function get_client_ip(): string {
        return isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : 'unknown';
    }
}
