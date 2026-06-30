<?php
/**
 * Search Result Value Object
 *
 * Standardised result format across all search sources.
 * Future-proof with content_type and content_type_metadata fields.
 *
 * @package KH\Editorial\Search\Models
 */

namespace KH\Editorial\Search\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchResult
 */
class SearchResult {

    /**
     * Unique result ID with source prefix (e.g. "rag:42", "registry:7", "serp:https://...").
     *
     * @var string
     */
    private string $id;

    /**
     * Source identifier: 'rag', 'registry', 'serp'.
     *
     * @var string
     */
    private string $source;

    /**
     * Content type: 'article', 'atomic_article', 'answer_card', etc.
     *
     * @var string
     */
    private string $content_type;

    /**
     * Result title.
     *
     * @var string
     */
    private string $title;

    /**
     * Result excerpt / snippet.
     *
     * @var string
     */
    private string $excerpt;

    /**
     * Result URL.
     *
     * @var string
     */
    private string $url;

    /**
     * Relevance score (0.0 – 1.0).
     *
     * @var float
     */
    private float $score;

    /**
     * Content-type-specific metadata.
     *
     * @var array
     */
    private array $content_type_metadata;

    /**
     * Source-specific metadata (e.g. embedding model, similarity).
     *
     * @var array
     */
    private array $source_metadata;

    /**
     * Constructor.
     *
     * @param string $id                   Unique result ID.
     * @param string $source               Source identifier.
     * @param string $content_type         Content type.
     * @param string $title                Result title.
     * @param string $excerpt              Result excerpt.
     * @param string $url                  Result URL.
     * @param float  $score                Relevance score.
     * @param array  $content_type_metadata Content-type metadata.
     * @param array  $source_metadata      Source-specific metadata.
     */
    public function __construct(
        string $id,
        string $source,
        string $content_type,
        string $title,
        string $excerpt,
        string $url,
        float $score,
        array $content_type_metadata = [],
        array $source_metadata = []
    ) {
        $this->id                    = $id;
        $this->source                = $source;
        $this->content_type          = $content_type;
        $this->title                 = $title;
        $this->excerpt               = $excerpt;
        $this->url                   = $url;
        $this->score                 = max( 0.0, min( 1.0, $score ) );
        $this->content_type_metadata = $content_type_metadata;
        $this->source_metadata       = $source_metadata;
    }

    /**
     * Convert to array for JSON serialisation.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'id'                    => $this->id,
            'source'                => $this->source,
            'content_type'          => $this->content_type,
            'title'                 => $this->title,
            'excerpt'               => $this->excerpt,
            'url'                   => $this->url,
            'score'                 => $this->score,
            'content_type_metadata' => $this->content_type_metadata,
            'source_metadata'       => $this->source_metadata,
        ];
    }

    /**
     * Get the deduplication key (URL-based).
     *
     * @return string
     */
    public function get_dedup_key(): string {
        return md5( $this->url );
    }

    /**
     * Get the result ID.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get the source.
     *
     * @return string
     */
    public function get_source(): string {
        return $this->source;
    }

    /**
     * Get the score.
     *
     * @return float
     */
    public function get_score(): float {
        return $this->score;
    }

    /**
     * Create a SearchResult from an array (deserialisation).
     *
     * @param array $data The result data array (from to_array()).
     * @return self
     */
    public static function from_array( array $data ): self {
        return new self(
            id:                    $data['id'] ?? '',
            source:                $data['source'] ?? '',
            content_type:          $data['content_type'] ?? '',
            title:                 $data['title'] ?? '',
            excerpt:               $data['excerpt'] ?? '',
            url:                   $data['url'] ?? '',
            score:                 (float) ( $data['score'] ?? 0.0 ),
            content_type_metadata: $data['content_type_metadata'] ?? [],
            source_metadata:       $data['source_metadata'] ?? []
        );
    }

    /**
     * Get the URL.
     *
     * @return string
     */
    public function get_url(): string {
        return $this->url;
    }
}
