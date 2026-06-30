<?php
/**
 * Content Registry Search Source
 *
 * Fulltext search over the content_registry table via MySQL MATCH...AGAINST.
 * Adds JSON field filtering for geo_flags, seo_metadata, smma_flags.
 *
 * @package KH\Editorial\Search\Sources
 */

namespace KH\Editorial\Search\Sources;

use KH\Editorial\Search\Interfaces\SearchSourceInterface;
use KH\Editorial\Search\Models\SearchQuery;
use KH\Editorial\Search\Models\SearchResult;
use KH\Editorial\Search\Models\ResultSet;
use KH\ContentRegistry\Services\ContentRegistryService;

defined( 'ABSPATH' ) || exit;

/**
 * Class RegistrySource
 */
class RegistrySource implements SearchSourceInterface {

    /**
     * Registry service instance.
     *
     * @var ContentRegistryService|null
     */
    private $registry_service = null;

    /**
     * {@inheritdoc}
     */
    public function name(): string {
        return 'registry';
    }

    /**
     * {@inheritdoc}
     */
    public function supports( SearchQuery $query ): bool {
        return ! empty( trim( $query->get_query() ) );
    }

    /**
     * Get or create the registry service.
     *
     * @return ContentRegistryService|null
     */
    private function get_registry(): ?ContentRegistryService {
        if ( null === $this->registry_service ) {
            if ( ! class_exists( '\KH\ContentRegistry\Services\ContentRegistryService' ) ) {
                return null;
            }
            $this->registry_service = ContentRegistryService::instance();
        }
        return $this->registry_service;
    }

    /**
     * {@inheritdoc}
     */
    public function search( SearchQuery $query ): ResultSet {
        $registry = $this->get_registry();
        if ( null === $registry ) {
            return ResultSet::empty( 'registry' );
        }

        $query_text = trim( $query->get_query() );
        if ( empty( $query_text ) ) {
            return ResultSet::empty( 'registry' );
        }

        // Use the existing search_articles method for the base query.
        $articles = $registry->search_articles(
            $query_text,
            $query->get_blog_id(),
            true // include atomic
        );

        // Apply JSON field filters.
        $filters = $query->get_filters();
        if ( ! empty( $filters ) ) {
            $articles = $this->apply_json_filters( $articles, $filters );
        }

        // Apply article_status filter if specified.
        $status_filter = $query->get_filter( 'article_status' );
        if ( ! empty( $status_filter ) ) {
            $statuses   = is_array( $status_filter ) ? $status_filter : [ $status_filter ];
            $articles   = array_filter( $articles, function ( $article ) use ( $statuses ) {
                return in_array( $article->article_status, $statuses, true );
            } );
        }

        // Apply parent_post_id filter if specified.
        $parent_filter = $query->get_filter( 'parent_post_id' );
        if ( null !== $parent_filter ) {
            $articles = array_filter( $articles, function ( $article ) use ( $parent_filter ) {
                return (int) ( $article->parent_post_id ?? 0 ) === (int) $parent_filter;
            } );
        }

        // Apply post_type filter (via content_type check against seo_metadata).
        $content_types = $query->get_content_types();
        if ( ! empty( $content_types ) ) {
            $articles = array_filter( $articles, function ( $article ) use ( $content_types ) {
                $type = $this->infer_content_type( $article );
                return in_array( $type, $content_types, true );
            } );
        }

        // Paginate.
        $total  = count( $articles );
        $offset = $query->get_offset();
        $limit  = $query->get_limit();
        $articles = array_slice( $articles, $offset, $limit );

        // Build results.
        $results = [];
        foreach ( $articles as $article ) {
            $content_type = $this->infer_content_type( $article );

            $results[] = new SearchResult(
                id:       'registry:' . $article->id,
                source:   'registry',
                content_type: $content_type,
                title:    $article->title,
                excerpt:  $article->excerpt ?? '',
                url:      $this->get_article_url( $article ),
                score:    $this->compute_registry_score( $article, $query_text ),
                content_type_metadata: [
                    'schema_type'      => $content_type,
                    'article_status'   => $article->article_status,
                    'parent_post_id'   => (int) ( $article->parent_post_id ?? 0 ),
                    'target_blog_id'   => (int) $article->target_blog_id,
                ],
                source_metadata: [
                    'search_method' => 'fulltext',
                ]
            );
        }

        return new ResultSet(
            results:  $results,
            total:    $total,
            page:     $query->get_page(),
            per_page: $query->get_limit(),
            source:   'registry'
        );
    }

    /**
     * Apply JSON field filters to articles.
     *
     * @param object[] $articles Array of registry article objects.
     * @param array    $filters  Filter criteria.
     * @return object[]
     */
    private function apply_json_filters( array $articles, array $filters ): array {
        $json_fields = [ 'geo_flags', 'seo_metadata', 'smma_flags' ];

        foreach ( $json_fields as $field ) {
            $filter_value = $filters[ $field ] ?? null;
            if ( empty( $filter_value ) || ! is_array( $filter_value ) ) {
                continue;
            }

            $articles = array_filter( $articles, function ( $article ) use ( $field, $filter_value ) {
                $meta = $article->$field ?? [];
                if ( ! is_array( $meta ) ) {
                    return false;
                }

                // Check if all key-value pairs in filter_value exist in meta.
                foreach ( $filter_value as $key => $value ) {
                    if ( ! isset( $meta[ $key ] ) || $meta[ $key ] != $value ) {
                        return false;
                    }
                }
                return true;
            } );
        }

        return $articles;
    }

    /**
     * Infer content type from article data.
     *
     * @param object $article Registry article object.
     * @return string
     */
    private function infer_content_type( object $article ): string {
        // If it has a parent_post_id, it's likely an atomic_article.
        if ( ! empty( $article->parent_post_id ) ) {
            return 'atomic_article';
        }

        // Check seo_metadata for content_type hint.
        if ( ! empty( $article->seo_metadata ) && is_array( $article->seo_metadata ) ) {
            $type = $article->seo_metadata['content_type'] ?? '';
            if ( ! empty( $type ) ) {
                return $type;
            }
        }

        return 'article';
    }

    /**
     * Get the best URL for a registry article.
     *
     * @param object $article Registry article object.
     * @return string
     */
    private function get_article_url( object $article ): string {
        // If we have a parent_post_id, try to get its permalink.
        $parent_id = (int) ( $article->parent_post_id ?? 0 );
        if ( $parent_id > 0 ) {
            $permalink = get_permalink( $parent_id );
            if ( $permalink ) {
                return $permalink;
            }
        }

        // Otherwise use the slug-based route.
        $blog_id = (int) ( $article->target_blog_id ?? 1 );
        $slug    = sanitize_title( $article->slug ?? '' );

        if ( ! empty( $slug ) ) {
            return home_url( "/{$slug}/", $blog_id );
        }

        return '';
    }

    /**
     * Compute a normalised score for a registry article.
     *
     * @param object $article Registry article object.
     * @param string $query   Search query.
     * @return float
     */
    private function compute_registry_score( object $article, string $query ): float {
        $score = 0.5; // Base score for any MATCH result.

        // Boost for title matches.
        if ( ! empty( $article->title ) ) {
            $title_lower = mb_strtolower( $article->title );
            $query_lower = mb_strtolower( $query );

            if ( false !== strpos( $title_lower, $query_lower ) ) {
                $score += 0.3;
            }

            // Bonus for exact title match.
            if ( $title_lower === $query_lower ) {
                $score += 0.2;
            }
        }

        // Status-based boost: Live articles score higher.
        if ( 'Live' === ( $article->article_status ?? '' ) ) {
            $score += 0.1;
        }

        return min( 1.0, $score );
    }
}