<?php
/**
 * Search Query Value Object
 *
 * Encapsulates all parameters for a search request across sources.
 * Immutable after construction.
 *
 * @package KH\Editorial\Search\Models
 */

namespace KH\Editorial\Search\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchQuery
 */
class SearchQuery {

    /**
     * The search query string.
     *
     * @var string
     */
    private string $query;

    /**
     * Which sources to query (default: all).
     *
     * @var string[]
     */
    private array $sources;

    /**
     * Multisite scope (null = all sites, or specific blog_id).
     *
     * @var int|null
     */
    private ?int $blog_id;

    /**
     * Maximum results per source (before merging).
     *
     * @var int
     */
    private int $limit;

    /**
     * Page number for pagination (1-indexed).
     *
     * @var int
     */
    private int $page;

    /**
     * Optional filters: status, post_type, date_range, etc.
     *
     * @var array
     */
    private array $filters;

    /**
     * Content type filter (empty = all types).
     *
     * @var string[]
     */
    private array $content_types;

    /**
     * Constructor.
     *
     * @param string   $query         Search query string.
     * @param array    $sources       Source names to query.
     * @param int|null $blog_id       Multisite scope.
     * @param int      $limit         Results per source.
     * @param int      $page          Page number.
     * @param array    $filters       Additional filters.
     * @param array    $content_types Content type filter.
     */
    public function __construct(
        string $query,
        array $sources = [ 'rag', 'registry', 'serp' ],
        ?int $blog_id = null,
        int $limit = 10,
        int $page = 1,
        array $filters = [],
        array $content_types = []
    ) {
        $this->query         = $query;
        $this->sources       = $sources;
        $this->blog_id       = $blog_id;
        $this->limit         = max( 1, min( 100, $limit ) );
        $this->page          = max( 1, $page );
        $this->filters       = $filters;
        $this->content_types = $content_types;
    }

    /**
     * Create from a REST request.
     *
     * @param \WP_REST_Request $request Incoming request.
     * @return self
     */
    public static function from_rest_request( \WP_REST_Request $request ): self {
        return new self(
            query:         sanitize_text_field( $request->get_param( 'query' ) ),
            sources:       (array) ( $request->get_param( 'sources' ) ?: [ 'rag', 'registry', 'serp' ] ),
            blog_id:       $request->has_param( 'blog_id' ) ? (int) $request->get_param( 'blog_id' ) : null,
            limit:         (int) ( $request->get_param( 'limit' ) ?: 10 ),
            page:          (int) ( $request->get_param( 'page' ) ?: 1 ),
            filters:       (array) ( $request->get_param( 'filters' ) ?: [] ),
            content_types: (array) ( $request->get_param( 'content_types' ) ?: [] )
        );
    }

    /**
     * Get the query string.
     *
     * @return string
     */
    public function get_query(): string {
        return $this->query;
    }

    /**
     * Get requested sources.
     *
     * @return string[]
     */
    public function get_sources(): array {
        return $this->sources;
    }

    /**
     * Get blog_id filter.
     *
     * @return int|null
     */
    public function get_blog_id(): ?int {
        return $this->blog_id;
    }

    /**
     * Get limit per source.
     *
     * @return int
     */
    public function get_limit(): int {
        return $this->limit;
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
     * Get offset for SQL queries.
     *
     * @return int
     */
    public function get_offset(): int {
        return ( $this->page - 1 ) * $this->limit;
    }

    /**
     * Get filters array.
     *
     * @return array
     */
    public function get_filters(): array {
        return $this->filters;
    }

    /**
     * Get specific filter value.
     *
     * @param string $key Filter key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_filter( string $key, $default = null ) {
        return $this->filters[ $key ] ?? $default;
    }

    /**
     * Get content type filter.
     *
     * @return string[]
     */
    public function get_content_types(): array {
        return $this->content_types;
    }

    /**
     * Check if a specific source is requested.
     *
     * @param string $source Source name.
     * @return bool
     */
    public function has_source( string $source ): bool {
        return in_array( $source, $this->sources, true );
    }

    /**
     * Check if a content type is requested (or all types).
     *
     * @param string $content_type Content type to check.
     * @return bool
     */
    public function accepts_content_type( string $content_type ): bool {
        return empty( $this->content_types ) || in_array( $content_type, $this->content_types, true );
    }
}