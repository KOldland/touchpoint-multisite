<?php
/**
 * Search Source Interface
 *
 * Contract that all search sources (RAG, Registry, SERP, etc.) must implement.
 * Each source is independently testable and can be enabled/disabled via config.
 *
 * @package KH\Editorial\Search\Interfaces
 */

namespace KH\Editorial\Search\Interfaces;

use KH\Editorial\Search\Models\SearchQuery;
use KH\Editorial\Search\Models\ResultSet;

defined( 'ABSPATH' ) || exit;

/**
 * Interface SearchSourceInterface
 */
interface SearchSourceInterface {

    /**
     * Execute a search query against this source.
     *
     * @param SearchQuery $query The query to execute.
     * @return ResultSet
     */
    public function search( SearchQuery $query ): ResultSet;

    /**
     * Whether this source can handle the given query.
     *
     * @param SearchQuery $query The query to check.
     * @return bool
     */
    public function supports( SearchQuery $query ): bool;

    /**
     * Unique source identifier (e.g. 'rag', 'registry', 'serp').
     *
     * @return string
     */
    public function name(): string;
}