<?php
/**
 * Result Set Collection
 *
 * Collection of SearchResult objects with pagination metadata and aggregations.
 *
 * @package KH\Editorial\Search\Models
 */

namespace KH\Editorial\Search\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Class ResultSet
 */
class ResultSet {

    /**
     * Search results.
     *
     * @var SearchResult[]
     */
    private array $results;

    /**
     * Total number of results available.
     *
     * @var int
     */
    private int $total;

    /**
     * Current page number.
     *
     * @var int
     */
    private int $page;

    /**
     * Results per page.
     *
     * @var int
     */
    private int $per_page;

    /**
     * Source that produced this result set.
     *
     * @var string
     */
    private string $source;

    /**
     * Optional aggregations (facet counts, source breakdowns, etc.).
     *
     * @var array
     */
    private array $aggregations;

    /**
     * Constructor.
     *
     * @param SearchResult[] $results      Array of search results.
     * @param int            $total        Total results available.
     * @param int            $page         Current page.
     * @param int            $per_page     Results per page.
     * @param string         $source       Source identifier.
     * @param array          $aggregations Optional aggregations.
     */
    public function __construct(
        array $results,
        int $total = 0,
        int $page = 1,
        int $per_page = 10,
        string $source = '',
        array $aggregations = []
    ) {
        $this->results      = $results;
        $this->total        = $total;
        $this->page         = $page;
        $this->per_page     = $per_page;
        $this->source       = $source;
        $this->aggregations = $aggregations;
    }

    /**
     * Create an empty result set.
     *
     * @param string $source Source identifier.
     * @return self
     */
    public static function empty( string $source = '' ): self {
        return new self( [], 0, 1, 10, $source );
    }

    /**
     * Create a ResultSet from an array (deserialisation for cache).
     *
     * @param array $data The result array (from to_array()).
     * @return self
     */
    public static function from_array( array $data ): self {
        $results = [];
        foreach ( $data['results'] ?? [] as $result_data ) {
            $results[] = SearchResult::from_array( $result_data );
        }

        return new self(
            results:      $results,
            total:        (int) ( $data['total'] ?? 0 ),
            page:         (int) ( $data['page'] ?? 1 ),
            per_page:     (int) ( $data['per_page'] ?? 10 ),
            source:       $data['source'] ?? '',
            aggregations: $data['aggregations'] ?? []
        );
    }

    /**
     * Convert to array for JSON serialisation.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'source'       => $this->source,
            'total'        => $this->total,
            'page'         => $this->page,
            'per_page'     => $this->per_page,
            'results'      => array_map(
                function ( SearchResult $result ) {
                    return $result->to_array();
                },
                $this->results
            ),
            'aggregations' => $this->aggregations,
        ];
    }

    /**
     * Get results.
     *
     * @return SearchResult[]
     */
    public function get_results(): array {
        return $this->results;
    }

    /**
     * Get total count.
     *
     * @return int
     */
    public function get_total(): int {
        return $this->total;
    }

    /**
     * Get page number.
     *
     * @return int
     */
    public function get_page(): int {
        return $this->page;
    }

    /**
     * Get per page.
     *
     * @return int
     */
    public function get_per_page(): int {
        return $this->per_page;
    }

    /**
     * Get source identifier.
     *
     * @return string
     */
    public function get_source(): string {
        return $this->source;
    }

    /**
     * Get aggregations.
     *
     * @return array
     */
    public function get_aggregations(): array {
        return $this->aggregations;
    }

    /**
     * Merge another ResultSet into this one (for cross-source merging).
     *
     * @param ResultSet $other Another result set to merge.
     * @return void
     */
    public function merge( ResultSet $other ): void {
        $this->results = array_merge( $this->results, $other->get_results() );
        $this->total  += $other->get_total();
    }

    /**
     * Deduplicate results by URL, keeping the highest score for duplicates.
     *
     * @return void
     */
    public function deduplicate(): void {
        $seen   = [];
        $unique = [];

        foreach ( $this->results as $result ) {
            $key = $result->get_dedup_key();

            if ( isset( $seen[ $key ] ) ) {
                // Keep the one with the higher score.
                if ( $result->get_score() > $seen[ $key ]->get_score() ) {
                    $seen[ $key ] = $result;
                }
            } else {
                $seen[ $key ] = $result;
            }
        }

        $this->results = array_values( $seen );
        $this->total   = count( $this->results );
    }

    /**
     * Sort results by score descending.
     *
     * @return void
     */
    public function sort_by_score(): void {
        usort( $this->results, function ( SearchResult $a, SearchResult $b ) {
            return $b->get_score() <=> $a->get_score();
        } );
    }

    /**
     * Slice results for pagination after merging.
     *
     * @param int $offset Offset.
     * @param int $length Length.
     * @return void
     */
    public function slice( int $offset, int $length ): void {
        $this->results = array_slice( $this->results, $offset, $length );
    }
}