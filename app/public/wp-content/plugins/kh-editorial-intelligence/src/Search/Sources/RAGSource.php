<?php
/**
 * RAG Search Source
 *
 * Semantic search over atomic article embeddings using cosine similarity.
 * Requires the AtomicEmbeddingService and the atomic_embeddings table.
 *
 * @package KH\Editorial\Search\Sources
 */

namespace KH\Editorial\Search\Sources;

use KH\Editorial\Search\Interfaces\SearchSourceInterface;
use KH\Editorial\Search\Models\SearchQuery;
use KH\Editorial\Search\Models\SearchResult;
use KH\Editorial\Search\Models\ResultSet;
use KH\Editorial\Services\GEO\AtomicEmbeddingService;
use KH\Editorial\Database\AtomicEmbeddingsMigration;

defined( 'ABSPATH' ) || exit;

/**
 * Class RAGSource
 */
class RAGSource implements SearchSourceInterface {

    /**
     * Embedding service instance.
     *
     * @var AtomicEmbeddingService
     */
    private AtomicEmbeddingService $embedding_service;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->embedding_service = new AtomicEmbeddingService();
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string {
        return 'rag';
    }

    /**
     * {@inheritdoc}
     */
    public function supports( SearchQuery $query ): bool {
        // RAG requires a non-empty query.
        return ! empty( trim( $query->get_query() ) );
    }

    /**
     * {@inheritdoc}
     */
    public function search( SearchQuery $query ): ResultSet {
        $query_text = trim( $query->get_query() );

        if ( empty( $query_text ) ) {
            return ResultSet::empty( 'rag' );
        }

        // 1. Embed the query (with caching).
        $query_embedding = $this->get_query_embedding_cached( $query_text );
        if ( is_wp_error( $query_embedding ) ) {
            error_log( '[KH RAG] Query embedding failed: ' . $query_embedding->get_error_message() );
            return ResultSet::empty( 'rag' );
        }

        // 2. Load embeddings with pagination and optional blog_id filter.
        $embeddings = $this->get_paginated_embeddings(
            $query->get_limit(),
            $query->get_offset(),
            $query->get_blog_id()
        );

        if ( empty( $embeddings ) ) {
            return ResultSet::empty( 'rag' );
        }

        // 3. Rank by cosine similarity.
        $scored = [];
        foreach ( $embeddings as $row ) {
            $embedding = json_decode( $row['embedding'], true );
            if ( ! is_array( $embedding ) ) {
                continue;
            }
            $similarity = $this->embedding_service->cosine_similarity( $query_embedding, $embedding );
            $scored[]   = [
                'post_id'    => (int) $row['post_id'],
                'similarity' => $similarity,
            ];
        }

        usort( $scored, function ( $a, $b ) {
            return $b['similarity'] <=> $a['similarity'];
        } );

        // 4. Build results.
        $results = [];
        $total   = count( $scored );

        foreach ( $scored as $item ) {
            $post = get_post( $item['post_id'] );
            if ( ! $post ) {
                continue;
            }

            $parent_id = (int) get_post_meta( $item['post_id'], '_atomic_parent_id', true );

            $results[] = new SearchResult(
                id:       'rag:' . $item['post_id'],
                source:   'rag',
                content_type: 'atomic_article',
                title:    $post->post_title,
                excerpt:  $post->post_excerpt,
                url:      get_permalink( $item['post_id'] ),
                score:    (float) $item['similarity'],
                content_type_metadata: [
                    'schema_type'    => 'Article',
                    'parent_post_id' => $parent_id ?: null,
                ],
                source_metadata: [
                    'embedding_model' => AtomicEmbeddingService::MODEL,
                    'similarity'      => round( $item['similarity'], 4 ),
                ]
            );
        }

        return new ResultSet(
            results:  $results,
            total:    $total,
            page:     $query->get_page(),
            per_page: $query->get_limit(),
            source:   'rag'
        );
    }

    /**
     * Get query embedding with short TTL cache.
     *
     * @param string $text Query text.
     * @return array|\WP_Error
     */
    private function get_query_embedding_cached( string $text ) {
        $cache_key = 'khm_rag_query_embed_' . md5( $text );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $embedding = $this->embedding_service->embed_now_text( $text );
        if ( ! is_wp_error( $embedding ) ) {
            // Cache for 5 minutes.
            set_transient( $cache_key, $embedding, 5 * MINUTE_IN_SECONDS );
        }

        return $embedding;
    }

    /**
     * Get embeddings with pagination and optional blog_id filter.
     *
     * @param int      $limit   Number of rows.
     * @param int      $offset  Offset.
     * @param int|null $blog_id Optional blog_id filter.
     * @return array
     */
    private function get_paginated_embeddings( int $limit, int $offset, ?int $blog_id = null ): array {
        global $wpdb;

        $table = AtomicEmbeddingsMigration::table_name();

        $where = '';
        $params = [];

        if ( null !== $blog_id && $blog_id > 0 ) {
            $where   = 'WHERE blog_id = %d';
            $params[] = $blog_id;
        }

        $params[] = $limit;
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT post_id, embedding FROM {$table} {$where} ORDER BY post_id ASC LIMIT %d OFFSET %d",
            $params
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (array) $wpdb->get_results( $sql, ARRAY_A );
    }
}