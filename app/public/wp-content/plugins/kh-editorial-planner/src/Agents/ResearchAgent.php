<?php

namespace KH\Planner\Agents;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ResearchAgent
 * 
 * Responsible for gathering raw data (SERP, Keywords, Internal Coverage)
 * for the planner phases.
 */
class ResearchAgent {

    /**
     * Gather raw discovery data for a topic.
     */
    public function gather_discovery_inputs( $topic, $includes = [], $subgroup = '' ) {
        $keyword_provider = new \KH\Editorial\Providers\KeywordProvider();
        $search_provider  = new \KH\Editorial\Providers\SearchProvider();

        $queries = [
            $topic . ' trends',
            $topic . ' industry report',
        ];
        
        foreach ( array_slice( (array) $includes, 0, 2 ) as $inc ) {
            $queries[] = $inc . ' trends';
        }

        $serp_snapshot = [];
        foreach ( $queries as $query ) {
            $results = $search_provider->search_serpapi( $query, 5 );
            if ( ! is_wp_error( $results ) ) {
                $serp_snapshot[$query] = $results['organic_results'] ?? [];
            } else {
                // Fallback: simulated SERP data when external provider fails
                $serp_snapshot[$query] = [
                    [
                        'title'   => $query . ' - Overview',
                        'snippet' => 'Current developments and trends in ' . $query . ' from industry sources.',
                        'link'    => '',
                    ]
                ];
            }
        }

        $keywords = $keyword_provider->keyword_suggestions( $topic, 20 );
        if ( is_wp_error( $keywords ) ) {
            // Fallback: generate basic keyword variants from the topic
            $words = array_filter( explode( ' ', strtolower( $topic ) ) );
            $candidate_keywords = [];
            foreach ( $words as $word ) {
                $candidate_keywords[] = [
                    'keyword'       => $word,
                    'search_volume' => 100,
                    'difficulty'    => 50,
                ];
                $candidate_keywords[] = [
                    'keyword'       => $topic . ' ' . $word,
                    'search_volume' => 50,
                    'difficulty'    => 40,
                ];
            }
            if ( empty( $candidate_keywords ) ) {
                $candidate_keywords[] = [
                    'keyword'       => $topic,
                    'search_volume' => 200,
                    'difficulty'    => 60,
                ];
            }
        } else {
            $candidate_keywords = $keywords;
        }
        
        // Site Awareness: Internal Content Coverage
        $internal_coverage = $this->build_internal_content_coverage( $topic, $includes, $subgroup, $candidate_keywords );
        
        return [
            'serp_snapshot'      => $serp_snapshot,
            'candidate_keywords' => $candidate_keywords,
            'internal_coverage'  => $internal_coverage,
            'topic'              => $topic
        ];
    }

    /**
     * Build internal content coverage stats to identify gaps.
     */
    public function build_internal_content_coverage( $topic, $includes = [], $subgroup = '', $candidate_keywords = [] ) {
        $terms = array_unique( array_merge( 
            [ $topic ], 
            (array) $includes, 
            [ $subgroup ], 
            array_slice( wp_list_pluck( $candidate_keywords, 'keyword' ), 0, 10 ) 
        ) );
        $terms = array_filter( array_map( 'trim', $terms ) );

        if ( empty( $terms ) ) {
            return [ 'summary' => 'No terms to analyze.' ];
        }

        $posts = get_posts( [
            'post_type'      => [ 'post', 'atomic_article' ],
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'date',
            'order'          => 'DESC'
        ] );

        $stats = [];
        foreach ( $terms as $term ) {
            $stats[$term] = [ 'term' => $term, 'hits' => 0, 'latest' => '' ];
        }

        foreach ( $posts as $post ) {
            $content = strtolower( $post->post_title . ' ' . $post->post_content );
            foreach ( $terms as $term ) {
                if ( strpos( $content, strtolower( $term ) ) !== false ) {
                    $stats[$term]['hits']++;
                    if ( empty( $stats[$term]['latest'] ) ) {
                        $stats[$term]['latest'] = get_the_date( 'Y-m-d', $post );
                    }
                }
            }
        }

        return [
            'summary' => sprintf( 'Analyzed %d recent posts across %d terms.', count( $posts ), count( $terms ) ),
            'stats'   => array_values( $stats ),
            'gaps'    => array_values( array_filter( $stats, function($s) { return $s['hits'] === 0; } ) )
        ];
    }

    /**
     * Perform keyword enrichment (metrics and difficulty).
     */
    public function enrich_keywords( $keywords ) {
        if ( empty( $keywords ) ) {
            return [];
        }

        $keyword_provider = new \KH\Editorial\Providers\KeywordProvider();
        $metrics = $keyword_provider->keyword_metrics( $keywords );
        
        if ( is_wp_error( $metrics ) ) {
            return $metrics;
        }

        // In a real scenario, we might also fetch difficulty or SERP snapshots for top keywords here
        return $metrics;
    }

    /**
     * Rank keywords based on volume and strategic value.
     */
    public function rank_keywords( $metrics ) {
        // Simple ranking logic: Volume desc
        usort( $metrics, function( $a, $b ) {
            return ( $b['search_volume'] ?? 0 ) <=> ( $a['search_volume'] ?? 0 );
        } );

        return array_slice( $metrics, 0, 15 );
    }

    /**
     * Verify a citation via the Intelligence service.
     */
    public function verify_citation( $url, $title = '', $doi = '' ) {
        if ( class_exists( '\KH\Editorial\Services\CitationVerifier' ) ) {
            $verifier = new \KH\Editorial\Services\CitationVerifier();
            return $verifier->verify_citation( [
                'url'   => $url,
                'title' => $title,
                'doi'   => $doi
            ] );
        }

        return new \WP_Error( 'intelligence_missing', 'Citation verification service is not available.' );
    }

    /**
     * Placeholder for internal coverage logic (to be expanded later).
     */
    private function get_internal_coverage_placeholder( $topic ) {
        // Method was replaced by build_internal_content_coverage above.
        return [];
    }
}
