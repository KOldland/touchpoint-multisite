<?php
/**
 * Gap Analyzer
 *
 * Cross-references SERP results against the content registry to identify
 * content gaps — topics that exist in external search but are not covered
 * by the user's content pipeline.
 *
 * @package KH\Editorial\Search
 */

namespace KH\Editorial\Search;

use KH\Editorial\Search\Models\SearchQuery;
use KH\Editorial\Search\Sources\SERPSource;
use KH\ContentRegistry\Services\ContentRegistryService;

defined( 'ABSPATH' ) || exit;

/**
 * Class GapAnalyzer
 */
class GapAnalyzer {

    /**
     * SERP source instance.
     *
     * @var SERPSource
     */
    private SERPSource $serp_source;

    /**
     * Registry service instance.
     *
     * @var ContentRegistryService|null
     */
    private $registry_service = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->serp_source = new SERPSource();

        if ( class_exists( '\KH\ContentRegistry\Services\ContentRegistryService' ) ) {
            $this->registry_service = ContentRegistryService::instance();
        }
    }

    /**
     * Perform gap analysis for a given topic or query.
     *
     * Compares SERP results against registry articles in Summary/Framework status
     * to identify content gaps.
     *
     * @param string   $query   The topic or query to analyse.
     * @param int|null $blog_id Optional blog_id scope.
     * @return array {
     *     @type string   $topic          The analysed topic.
     *     @type array    $serp_results    Top SERP results for this topic.
     *     @type array    $registry_items  Registry articles in pipeline.
     *     @type array    $gaps            Topics/angles from SERP not covered by registry.
     *     @type int      $gap_count       Number of identified gaps.
     *     @type float    $coverage_score  Fraction of SERP topics covered (0.0 - 1.0).
     * }
     */
    public function analyse( string $query, ?int $blog_id = null ): array {
        $result = [
            'topic'          => $query,
            'serp_results'   => [],
            'registry_items' => [],
            'gaps'           => [],
            'gap_count'      => 0,
            'coverage_score' => 0.0,
        ];

        // 1. Get SERP results.
        $serp_query = new SearchQuery(
            query:   $query,
            sources: [ 'serp' ],
            blog_id: $blog_id,
            limit:   20
        );

        $serp_set = $this->serp_source->search( $serp_query );
        $serp_results = $serp_set->get_results();

        $result['serp_results'] = array_map(
            function ( $r ) { return $r->to_array(); },
            $serp_results
        );

        // 2. Get registry articles in the pipeline (Summary/Framework).
        $registry_articles = [];
        if ( null !== $this->registry_service ) {
            $registry_articles = $this->registry_service->get_articles_for_gap_analysis();

            // Filter by blog_id if specified.
            if ( null !== $blog_id ) {
                $registry_articles = array_filter( $registry_articles, function ( $article ) use ( $blog_id ) {
                    return (int) ( $article->target_blog_id ?? 0 ) === $blog_id;
                } );
            }
        }

        $result['registry_items'] = array_values( array_map(
            function ( $article ) {
                return [
                    'id'             => (int) $article->id,
                    'title'          => $article->title,
                    'article_status' => $article->article_status,
                    'slug'           => $article->slug ?? '',
                    'target_blog_id' => (int) ( $article->target_blog_id ?? 0 ),
                ];
            },
            $registry_articles
        ) );

        // 3. Identify gaps.
        $gaps = $this->identify_gaps( $serp_results, $registry_articles, $query );
        $result['gaps']           = $gaps;
        $result['gap_count']      = count( $gaps );

        // 4. Compute coverage score.
        $serp_title_count = count( $serp_results );
        if ( $serp_title_count > 0 ) {
            $matched = $serp_title_count - count( $gaps );
            $result['coverage_score'] = round( $matched / $serp_title_count, 2 );
        }

        return $result;
    }

    /**
     * Identify which SERP results represent content gaps not covered by the registry.
     *
     * @param array    $serp_results      Array of SearchResult objects.
     * @param object[] $registry_articles Array of registry article objects.
     * @param string   $query             The original query.
     * @return array Array of gap descriptions.
     */
    private function identify_gaps( array $serp_results, array $registry_articles, string $query ): array {
        $gaps = [];

        // Build a lookup of registry titles (lowercase) for matching.
        $registry_titles = [];
        foreach ( $registry_articles as $article ) {
            $title = mb_strtolower( trim( $article->title ?? '' ) );
            if ( ! empty( $title ) ) {
                $registry_titles[] = $title;
            }
        }

        $query_lower = mb_strtolower( $query );

        foreach ( $serp_results as $result ) {
            $serp_title      = $result->get_title();
            $serp_url        = $result->get_url();
            $serp_title_lower = mb_strtolower( $serp_title );

            // Check if any registry article's title is a substring of the SERP title
            // or vice versa — this indicates the topic is already covered.
            $is_covered = false;

            // Direct check: does the SERP title closely match any registry title?
            foreach ( $registry_titles as $reg_title ) {
                // Check for significant overlap.
                $overlap = $this->compute_title_overlap( $serp_title_lower, $reg_title );
                if ( $overlap > 0.5 ) {
                    $is_covered = true;
                    break;
                }
            }

            if ( ! $is_covered ) {
                $gaps[] = [
                    'serp_title'   => $serp_title,
                    'serp_url'     => $serp_url,
                    'reason'       => 'No matching content found in pipeline',
                    'suggested_title' => $this->suggest_title( $serp_title, $query ),
                ];
            }
        }

        return $gaps;
    }

    /**
     * Compute title overlap between two strings (as a fraction of shared words).
     *
     * @param string $a First title (lowercase).
     * @param string $b Second title (lowercase).
     * @return float Overlap score (0.0 – 1.0).
     */
    private function compute_title_overlap( string $a, string $b ): float {
        $words_a = array_unique( array_filter( explode( ' ', $a ) ) );
        $words_b = array_unique( array_filter( explode( ' ', $b ) ) );

        if ( empty( $words_a ) || empty( $words_b ) ) {
            return 0.0;
        }

        $common = array_intersect( $words_a, $words_b );
        $total  = max( count( $words_a ), count( $words_b ) );

        return count( $common ) / $total;
    }

    /**
     * Suggest a title for a gap topic.
     *
     * @param string $serp_title The SERP result title.
     * @param string $query      The original query.
     * @return string
     */
    private function suggest_title( string $serp_title, string $query ): string {
        // Remove the site/brand name from the end of the title if present (e.g. " - Site Name").
        $cleaned = preg_replace( '/\s*[|\-–—]\s*[^|\-–—]+$/', '', $serp_title );
        $cleaned = trim( $cleaned );

        if ( ! empty( $cleaned ) && strlen( $cleaned ) > 10 ) {
            return $cleaned;
        }

        return $query . ': ' . $serp_title;
    }
}