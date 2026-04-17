<?php
/**
 * Atomic Embedding Service
 *
 * Generates and persists OpenAI text embeddings for atomic_article posts.
 * Embeddings are stored in wp_atomic_embeddings for fast bulk retrieval
 * by the RAG search endpoint.
 *
 * Model: text-embedding-3-small  (1 536 dimensions, ~$0.02 / 1M tokens)
 *
 * Public API:
 *   queue_embed(int $post_id)                             Schedule embedding on next cron tick
 *   embed_now(int $post_id): true|\WP_Error               Embed synchronously (used by tests)
 *   get_all_embeddings(): array<int, float[]>             Returns {post_id => float[]} map
 *   cosine_similarity(float[] $a, float[] $b): float      Pure math, no I/O
 *   delete_embedding(int $post_id): void
 *
 * @package KHM\Atomic
 */

namespace KHM\Atomic;

use KHM\Migrations\AtomicEmbeddingsMigration;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic Embedding Service
 */
class AtomicEmbeddingService {

    /**
     * OpenAI embeddings endpoint.
     */
    const ENDPOINT = 'https://api.openai.com/v1/embeddings';

    /**
     * Model to use for embeddings.
     */
    const MODEL = 'text-embedding-3-small';

    /**
     * WP cron hook name.
     */
    const CRON_HOOK = 'khm_embed_atomic_article';

    /**
     * Register WP hooks (cron handler + post deletion cleanup).
     *
     * @return void
     */
    public function register(): void {
        add_action( self::CRON_HOOK, array( $this, 'handle_cron' ) );
        add_action( 'before_delete_post', array( $this, 'on_delete_post' ) );
    }

    /**
     * Schedule embedding generation on the next available cron tick.
     * Idempotent — safe to call multiple times for the same post.
     *
     * @param int $post_id Atomic article post ID.
     * @return void
     */
    public function queue_embed( int $post_id ): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
            wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $post_id ) );
        }
    }

    /**
     * Cron handler — generate and store embedding for a post.
     *
     * @param int $post_id Post ID passed by the cron scheduler.
     * @return void
     */
    public function handle_cron( int $post_id ): void {
        $result = $this->embed_now( $post_id );

        if ( is_wp_error( $result ) ) {
            error_log( sprintf(
                '[KHM Atomic] Embedding failed for post %d: %s',
                $post_id,
                $result->get_error_message()
            ) );
        }
    }

    /**
     * Embed an arbitrary string (used by the search endpoint to embed a query).
     *
     * @param string $text Text to embed.
     * @return float[]|\WP_Error
     */
    public function embed_now_text( string $text ) {
        $text = mb_substr( trim( $text ), 0, 8000 );
        if ( empty( $text ) ) {
            return new \WP_Error( 'empty_text', 'Cannot embed empty text.' );
        }
        return $this->call_openai( $text );
    }

    /**
     * Generate and persist an embedding for a post synchronously.
     *
     * @param int $post_id Atomic article post ID.
     * @return true|\WP_Error
     */
    public function embed_now( int $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new \WP_Error( 'invalid_post', "Post {$post_id} not found." );
        }

        $text = $this->build_text_for_embedding( $post );

        $embedding = $this->call_openai( $text );
        if ( is_wp_error( $embedding ) ) {
            return $embedding;
        }

        $this->store_embedding( $post_id, $embedding );

        return true;
    }

    /**
     * Retrieve all stored embeddings as a map of post_id → float[].
     *
     * @return array<int, float[]>
     */
    public function get_all_embeddings(): array {
        global $wpdb;

        $table = AtomicEmbeddingsMigration::table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( "SELECT post_id, embedding FROM {$table}", ARRAY_A );

        $map = array();
        foreach ( (array) $rows as $row ) {
            $decoded = json_decode( $row['embedding'], true );
            if ( is_array( $decoded ) ) {
                $map[ (int) $row['post_id'] ] = $decoded;
            }
        }

        return $map;
    }

    /**
     * Delete the stored embedding for a post (called on post deletion).
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function delete_embedding( int $post_id ): void {
        global $wpdb;
        $table = AtomicEmbeddingsMigration::table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
    }

    /**
     * Compute cosine similarity between two float vectors.
     *
     * @param float[] $a Vector A.
     * @param float[] $b Vector B.
     * @return float Similarity in [-1, 1]; 1 = identical direction.
     */
    public function cosine_similarity( array $a, array $b ): float {
        if ( count( $a ) !== count( $b ) || empty( $a ) ) {
            return 0.0;
        }

        $dot    = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;

        $len = count( $a );
        for ( $i = 0; $i < $len; $i++ ) {
            $dot    += $a[ $i ] * $b[ $i ];
            $norm_a += $a[ $i ] * $a[ $i ];
            $norm_b += $b[ $i ] * $b[ $i ];
        }

        $denom = sqrt( $norm_a ) * sqrt( $norm_b );
        if ( $denom < 1e-10 ) {
            return 0.0;
        }

        return (float) ( $dot / $denom );
    }

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    /**
     * Clean up embedding when an atomic_article post is deleted.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function on_delete_post( int $post_id ): void {
        if ( AtomicArticlePostType::POST_TYPE === get_post_type( $post_id ) ) {
            $this->delete_embedding( $post_id );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a compact text representation of a post for embedding.
     * Title + excerpt + stripped body text, capped at 8 000 chars.
     *
     * @param \WP_Post $post Post object.
     * @return string
     */
    private function build_text_for_embedding( \WP_Post $post ): string {
        $parts = array(
            $post->post_title,
            $post->post_excerpt,
            wp_strip_all_tags( $post->post_content ),
        );

        $text = implode( "\n\n", array_filter( $parts ) );
        $text = preg_replace( '/\s+/', ' ', $text );

        return mb_substr( trim( $text ), 0, 8000 );
    }

    /**
     * Call the OpenAI embeddings API.
     *
     * @param string $text Input text.
     * @return float[]|\WP_Error
     */
    private function call_openai( string $text ) {
        $api_key = $this->get_api_key();
        if ( ! $api_key ) {
            return new \WP_Error( 'no_api_key', 'OpenAI API key not configured.' );
        }

        $response = wp_remote_post(
            self::ENDPOINT,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'model' => self::MODEL,
                    'input' => $text,
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== (int) $code ) {
            $msg = $body['error']['message'] ?? "HTTP {$code} from OpenAI embeddings endpoint.";
            return new \WP_Error( 'openai_error', $msg, array( 'status' => $code ) );
        }

        $embedding = $body['data'][0]['embedding'] ?? null;
        if ( ! is_array( $embedding ) ) {
            return new \WP_Error( 'unexpected_response', 'OpenAI did not return an embedding array.' );
        }

        return array_map( 'floatval', $embedding );
    }

    /**
     * Persist an embedding to wp_atomic_embeddings.
     *
     * @param int     $post_id   Post ID.
     * @param float[] $embedding Float array.
     * @return void
     */
    private function store_embedding( int $post_id, array $embedding ): void {
        global $wpdb;

        $table = AtomicEmbeddingsMigration::table_name();

        $wpdb->replace(
            $table,
            array(
                'post_id'    => $post_id,
                'embedding'  => wp_json_encode( $embedding ),
                'updated_at' => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s' )
        );
    }

    /**
     * Resolve the OpenAI API key using the same priority chain as LLMClient.
     *
     * @return string|null
     */
    private function get_api_key(): ?string {
        $sources = array(
            getenv( 'OPENAI_API_KEY' ),
            get_option( 'dual_gpt_openai_api_key' ),
            get_option( 'khm_geo_openai_api_key' ),
            defined( 'OPENAI_API_KEY' ) ? OPENAI_API_KEY : null,
        );

        foreach ( $sources as $key ) {
            if ( ! empty( $key ) ) {
                return $key;
            }
        }

        return null;
    }
}
