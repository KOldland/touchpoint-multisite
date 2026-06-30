<?php
/**
 * SERP Search Source
 *
 * External search via SearchProvider (DataForSEO, SerpAPI, etc.).
 * Maps raw API responses to the standardised SearchResult schema.
 *
 * @package KH\Editorial\Search\Sources
 */

namespace KH\Editorial\Search\Sources;

use KH\Editorial\Search\Interfaces\SearchSourceInterface;
use KH\Editorial\Search\Models\SearchQuery;
use KH\Editorial\Search\Models\SearchResult;
use KH\Editorial\Search\Models\ResultSet;
use KH\Editorial\Providers\SearchProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Class SERPSource
 */
class SERPSource implements SearchSourceInterface {

    /**
     * Search provider instance.
     *
     * @var SearchProvider|null
     */
    private ?SearchProvider $provider = null;

    /**
     * Constructor.
     */
    public function __construct() {
        if ( class_exists( '\KH\Editorial\Providers\SearchProvider' ) ) {
            $this->provider = new SearchProvider();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string {
        return 'serp';
    }

    /**
     * {@inheritdoc}
     */
    public function supports( SearchQuery $query ): bool {
        if ( null === $this->provider ) {
            return false;
        }
        return ! empty( trim( $query->get_query() ) ) && $this->provider->is_configured();
    }

    /**
     * {@inheritdoc}
     */
    public function search( SearchQuery $query ): ResultSet {
        if ( null === $this->provider ) {
            return ResultSet::empty( 'serp' );
        }

        $query_text = trim( $query->get_query() );
        if ( empty( $query_text ) ) {
            return ResultSet::empty( 'serp' );
        }

        // Determine the number of results to request from SERP.
        $serp_limit = min( $query->get_limit(), 20 ); // SERP providers typically cap at 20.

        $raw = $this->provider->search( $query_text, $serp_limit );
        if ( is_wp_error( $raw ) ) {
            error_log( '[KH SERP] Search failed: ' . $raw->get_error_message() );
            return ResultSet::empty( 'serp' );
        }

        // Parse results.
        $results      = [];
        $organic      = $raw['organic_results'] ?? [];
        $provider_name = $this->get_active_provider_name();

        foreach ( $organic as $index => $item ) {
            $title   = $item['title'] ?? '';
            $link    = $item['link'] ?? '';
            $snippet = $item['snippet'] ?? '';
            $position = $item['position'] ?? ( $index + 1 );

            if ( empty( $title ) && empty( $link ) ) {
                continue;
            }

            // Compute a normalised score based on position (lower position = higher score).
            $score = $this->position_to_score( $position );

            $results[] = new SearchResult(
                id:       'serp:' . md5( $link ),
                source:   'serp',
                content_type: 'article',
                title:    $title,
                excerpt:  $snippet,
                url:      $link,
                score:    $score,
                content_type_metadata: [
                    'schema_type' => 'WebPage',
                    'position'    => (int) $position,
                ],
                source_metadata: [
                    'provider'      => $provider_name,
                    'search_engine' => 'google',
                ]
            );
        }

        return new ResultSet(
            results:  $results,
            total:    count( $results ),
            page:     $query->get_page(),
            per_page: $query->get_limit(),
            source:   'serp'
        );
    }

    /**
     * Convert SERP position (1-based) to a normalised score.
     *
     * @param int $position Position in search results.
     * @return float Score between 0 and 1.
     */
    private function position_to_score( int $position ): float {
        // Position 1 = 1.0, position 10 = 0.1, position 20 = 0.05
        return max( 0.05, 1.0 / max( 1, $position ) );
    }

    /**
     * Get the active provider display name.
     *
     * @return string
     */
    private function get_active_provider_name(): string {
        if ( null === $this->provider ) {
            return 'unknown';
        }

        $settings = \KH\Editorial\Core\LLMService::get_settings();
        return ! empty( $settings['search_primary'] ) ? $settings['search_primary'] : 'serpapi';
    }
}