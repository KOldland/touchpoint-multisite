<?php
namespace KH\ContentRegistry\Services;

use WP_Error;

class ContentRegistryService {
    private static $instance = null;
    private $table;

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->base_prefix . 'content_registry';
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function invalidate_article_cache($blog_id, $slug) {
        wp_cache_delete("article_registry_{$blog_id}_{$slug}", 'content_registry');
    }

    public function create_article(array $args, bool $create_wp_post = true): int|WP_Error {
        global $wpdb;
        
        // Validate required fields
        $required = ['target_blog_id', 'slug', 'article_status', 'title'];
        foreach ($required as $field) {
            if (empty($args[$field])) {
                return new WP_Error('missing_field', "Missing required field: $field");
    }
}
        
        // Prepare data with JSON encoding
        $data = [
            'target_blog_id' => $args['target_blog_id'],
            'slug' => sanitize_title($args['slug']),
            'article_status' => $args['article_status'],
            'title' => sanitize_text_field($args['title']),
            'content_body' => $args['content_body'] ?? '',
            'excerpt' => $args['excerpt'] ?? '',
            'seo_metadata' => isset($args['seo_metadata']) ? wp_json_encode($args['seo_metadata']) : null,
            'sponsor_commentary' => $args['sponsor_commentary'] ?? '',
            'smma_flags' => isset($args['smma_flags']) ? wp_json_encode($args['smma_flags']) : null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        if (isset($args['parent_post_id'])) {
            $data['parent_post_id'] = $args['parent_post_id'];
        }
        
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        $registry_id = $wpdb->insert_id;
        
        // Create WordPress post for Gutenberg editing (optional)
        if ($create_wp_post) {
            $post_id = $this->create_wordpress_post($args, $registry_id);
            if ($post_id) {
                $wpdb->update(
                    $this->table,
                    ['wp_post_id' => $post_id],
                    ['id' => $registry_id]
                );
            }
        }
        
        $this->invalidate_article_cache($data['target_blog_id'], $data['slug']);
        return $registry_id;
    }
    
    /**
     * Create a WordPress post for the article.
     */
    private function create_wordpress_post(array $args, int $registry_id): int|WP_Error {
        // Posts are always created on the current blog (blog 1 for the admin context)
        // target_blog_id is preserved in the registry table for routing purposes
        
        $post_data = [
            'post_title' => $args['title'],
            'post_content' => $args['content_body'] ?? '',
            'post_excerpt' => $args['excerpt'] ?? '',
            'post_status' => $this->map_status_to_wp($args['article_status']),
            'post_type' => 'post',
            'post_author' => get_current_user_id() ?: 1,
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Store registry ID in post meta
        update_post_meta($post_id, '_registry_id', $registry_id);
        
        return $post_id;
    }
    
    /**
     * Map article status to WordPress post status.
     */
    private function map_status_to_wp(string $status): string {
        $map = [
            'Summary' => 'draft',
            'Framework' => 'draft',
            'Commentary' => 'draft',
            'Draft' => 'draft',
            'Scheduled' => 'future',
            'Live' => 'publish',
            'Cloned' => 'draft',
            'CloneRw' => 'draft',
        ];
        return $map[$status] ?? 'draft';
    }

    public function get_article_by_id(int $id): ?object {
        global $wpdb;
        $query = $wpdb->prepare("SELECT * FROM $this->table WHERE id = %d", $id);
        $article = $wpdb->get_row($query);
        
        if ($article) {
            $this->decode_json_fields($article);
        }
        
        return $article;
    }

    public function get_article_for_route(int $blog_id, string $slug): ?object {
        $cache_key = "article_registry_{$blog_id}_{$slug}";
        $article = wp_cache_get($cache_key, 'content_registry');
        
        if ($article === false) {
            global $wpdb;
            $query = $wpdb->prepare(
                "SELECT * FROM $this->table 
                 WHERE target_blog_id = %d AND slug = %s AND article_status = 'Live'",
                $blog_id, $slug
            );
            $article = $wpdb->get_row($query);
            
            if ($article) {
                $this->decode_json_fields($article);
                wp_cache_set($cache_key, $article, 'content_registry', 3600);
            }
        }
        
        return $article;
    }

    /**
     * Get an article by WordPress post ID and target blog ID.
     *
     * Used by AllocationService to find the registry entry for cloned posts.
     *
     * @param int $wp_post_id The WordPress post ID.
     * @param int $target_blog_id The target blog ID.
     * @return object|null
     */
    public function get_article_by_wp_post_id( int $wp_post_id, int $target_blog_id ): ?object {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT * FROM $this->table WHERE wp_post_id = %d AND target_blog_id = %d LIMIT 1",
            $wp_post_id,
            $target_blog_id
        );
        $article = $wpdb->get_row($query);

        if ($article) {
            $this->decode_json_fields($article);
        }

        return $article;
    }

    public function update_article(int $id, array $data): bool|WP_Error {
        global $wpdb;
        
        // Get current article to invalidate cache
        $article = $this->get_article_by_id($id);
        if (!$article) {
            return new WP_Error('not_found', 'Article not found');
        }
        
        // Prepare update data
        $update_data = ['updated_at' => current_time('mysql')];
        $allowed_fields = [
            'title', 'content_body', 'excerpt', 'sponsor_commentary', 
            'seo_metadata', 'smma_flags', 'geo_flags', 'article_status',
            'wp_post_id', 'clone_type', 'parent_post_id'
        ];
        
        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'seo_metadata' || $field === 'smma_flags' || $field === 'geo_flags') {
                    $update_data[$field] = wp_json_encode($data[$field]);
                } else {
                    $update_data[$field] = $data[$field];
                }
            }
        }
        
        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );
        
        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        $this->invalidate_article_cache($article->target_blog_id, $article->slug);
        return true;
    }

    public function delete_article(int $id): bool|WP_Error {
        global $wpdb;
        
        $article = $this->get_article_by_id($id);
        if (!$article) {
            return new WP_Error('not_found', 'Article not found');
        }
        
        $result = $wpdb->delete($this->table, ['id' => $id]);
        
        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        $this->invalidate_article_cache($article->target_blog_id, $article->slug);
        return true;
    }

    public function transition_status(int $id, string $new_status): bool|WP_Error {
        $valid_transitions = [
            'Summary' => ['Framework'],
            'Framework' => ['Commentary', 'Draft'],
            'Commentary' => ['Draft'],
            'Draft' => ['Scheduled', 'Live'],
            'Scheduled' => ['Live'],
            'Cloned' => ['Draft', 'Live'],
            'CloneRw' => ['Draft', 'Live']
        ];
        
        $article = $this->get_article_by_id($id);
        if (!$article) {
            return new WP_Error('not_found', 'Article not found');
        }
        
        $current_status = $article->article_status;
        
        if (!in_array($new_status, $valid_transitions[$current_status] ?? [])) {
            return new WP_Error('invalid_transition', "Invalid status transition: $current_status to $new_status");
        }
        
        return $this->update_article($id, ['article_status' => $new_status]);
    }

    public function get_articles_by_status(string $status, ?int $blog_id = null): array {
        global $wpdb;
        
        // Include: top-level (no parent) OR Summaries/Frameworks/Cloned/CloneRw (they ARE the parent, created by planner or clone process)
        // Exclude: atomic children (have parent_post_id AND are NOT one of the allowed parent statuses)
        $query = "SELECT * FROM $this->table WHERE article_status = %s AND (parent_post_id IS NULL OR parent_post_id = 0 OR article_status IN ('Summary','Framework','Cloned','CloneRw'))";
        $params = [$status];
        
        if ($blog_id) {
            $query .= " AND target_blog_id = %d";
            $params[] = $blog_id;
        }
        
        $articles = $wpdb->get_results($wpdb->prepare($query, $params));
        
        foreach ($articles as $article) {
            $this->decode_json_fields($article);
        }
        
        return $articles;
    }

    public function get_articles_for_gap_analysis(): array {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT * FROM $this->table 
             WHERE article_status IN ('Summary', 'Framework')
             AND (parent_post_id IS NULL OR parent_post_id = 0)"
        );
        
        $articles = $wpdb->get_results($query);
        
        foreach ($articles as $article) {
            $this->decode_json_fields($article);
        }
        
        return $articles;
    }

    public function get_atomic_articles(int $parent_post_id): array {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT * FROM $this->table 
             WHERE parent_post_id = %d",
            $parent_post_id
        );
        
        $articles = $wpdb->get_results($query);
        
        foreach ($articles as $article) {
            $this->decode_json_fields($article);
        }
        
        return $articles;
    }

    /**
     * Search articles using FULLTEXT MATCH...AGAINST.
     *
     * Falls back to LIKE search if FULLTEXT index is missing.
     * Supports pagination via limit/offset.
     *
     * @param string   $query          Search query.
     * @param int|null $blog_id        Optional blog_id filter.
     * @param bool     $include_atomic Whether to include atomic articles.
     * @param int      $limit          Max results (default 50).
     * @param int      $offset         Pagination offset (default 0).
     * @return array
     */
    public function search_articles(string $query, ?int $blog_id = null, bool $include_atomic = true, int $limit = 50, int $offset = 0): array {
        global $wpdb;
        
        // Check if FULLTEXT index exists; fall back to LIKE search if missing.
        if (!$this->has_fulltext_index()) {
            return $this->search_articles_fallback($query, $blog_id, $include_atomic, $limit, $offset);
        }
        
        $sql = "SELECT * FROM $this->table 
                WHERE MATCH(title, content_body, excerpt) AGAINST (%s IN BOOLEAN MODE)";
        $params = [$query];
        
        if ($blog_id) {
            $sql .= " AND target_blog_id = %d";
            $params[] = $blog_id;
        }
        
        if (!$include_atomic) {
            $sql .= " AND parent_post_id IS NULL";
        }
        
        $sql .= " ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $articles = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        foreach ($articles as $article) {
            $this->decode_json_fields($article);
        }
        
        return $articles;
    }

    /**
     * Fulltext search with JSON field filtering support.
     *
     * Supports filtering by geo_flags, seo_metadata, smma_flags using JSON_CONTAINS.
     *
     * @param string   $query          Search query.
     * @param int|null $blog_id        Optional blog_id filter.
     * @param bool     $include_atomic Whether to include atomic articles.
     * @param array    $json_filters   JSON field filters, e.g. ['geo_flags' => ['country' => 'US']].
     * @param int      $limit          Max results.
     * @param int      $offset         Pagination offset.
     * @return array
     */
    public function search_articles_with_filters(string $query, ?int $blog_id = null, bool $include_atomic = true, array $json_filters = [], int $limit = 50, int $offset = 0): array {
        global $wpdb;
        
        if (!$this->has_fulltext_index()) {
            return [];
        }
        
        $sql = "SELECT * FROM $this->table 
                WHERE MATCH(title, content_body, excerpt) AGAINST (%s IN BOOLEAN MODE)";
        $params = [$query];
        
        if ($blog_id) {
            $sql .= " AND target_blog_id = %d";
            $params[] = $blog_id;
        }
        
        if (!$include_atomic) {
            $sql .= " AND parent_post_id IS NULL";
        }
        
        // Apply JSON field filters.
        foreach ($json_filters as $field => $value) {
            if (!in_array($field, ['geo_flags', 'seo_metadata', 'smma_flags'], true)) {
                continue;
            }
            $sql .= " AND JSON_CONTAINS($field, %s)";
            $params[] = wp_json_encode($value);
        }
        
        $sql .= " ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $articles = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        foreach ($articles as $article) {
            $this->decode_json_fields($article);
        }
        
        return $articles;
    }

    /**
     * Fallback search using LIKE (when FULLTEXT index is missing).
     *
     * @param string   $query          Search query.
     * @param int|null $blog_id        Optional blog_id filter.
     * @param bool     $include_atomic Whether to include atomic articles.
     * @param int      $limit          Max results.
     * @param int      $offset         Pagination offset.
     * @return array
     */
    private function search_articles_fallback(string $query, ?int $blog_id = null, bool $include_atomic = true, int $limit = 50, int $offset = 0): array {
        global $wpdb;
        
        $like = '%' . $wpdb->esc_like($query) . '%';
        
        $sql = "SELECT * FROM $this->table 
                WHERE (title LIKE %s OR content_body LIKE %s OR excerpt LIKE %s)";
        $params = [$like, $like, $like];
        
        if ($blog_id) {
            $sql .= " AND target_blog_id = %d";
            $params[] = $blog_id;
        }
        
        if (!$include_atomic) {
            $sql .= " AND parent_post_id IS NULL";
        }
        
        $sql .= " ORDER BY updated_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $articles = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        foreach ($articles as $article) {
            $this->decode_json_fields($article);
        }
        
        return $articles;
    }

    /**
     * Check if the content_registry table has a FULLTEXT index on search columns.
     *
     * @return bool
     */
    public function has_fulltext_index(): bool {
        global $wpdb;
        
        $indexes = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW INDEX FROM {$this->table} WHERE Key_name = %s AND Index_type = %s",
                'idx_fulltext_search',
                'FULLTEXT'
            )
        );
        
        return !empty($indexes);
    }

    public function append_sponsor_commentary(int $id, string $commentary): bool|WP_Error {
        global $wpdb;
        
        $article = $this->get_article_by_id($id);
        if (!$article) {
            return new WP_Error('not_found', 'Article not found');
        }
        
        $new_commentary = $article->sponsor_commentary . "\n\n" . sanitize_textarea_field($commentary);
        return $this->update_article($id, ['sponsor_commentary' => $new_commentary]);
    }

    public function update_smma_flags(int $id, array $flags): bool|WP_Error {
        return $this->update_article($id, ['smma_flags' => $flags]);
    }

    public function update_geo_flags(int $id, array $flags): bool|WP_Error {
        return $this->update_article($id, ['geo_flags' => $flags]);
    }

    public function update_seo_metadata(int $id, array $metadata): bool|WP_Error {
        return $this->update_article($id, ['seo_metadata' => $metadata]);
    }

    public function get_seo_metadata(int $id): ?array {
        $article = $this->get_article_by_id($id);
        return $article ? (array) $article->seo_metadata : null;
    }

    /**
     * Create a Summary entry in the registry.
     *
     * @param string $slug              The article slug.
     * @param array  $synopsis          The synopsis data.
     * @param int    $planner_session_id The planner session post ID.
     * @param array  $planner_data      The planner phase data.
     * @return int|WP_Error The registry ID or error.
     */
    public function create_summary(string $slug, array $synopsis, int $planner_session_id, array $planner_data = []) {
        global $wpdb;
        
        $data = [
            'target_blog_id'    => 0, // Will be set when framework transitions to draft
            'slug'              => sanitize_title($slug),
            'article_status'    => 'Summary',
            'title'             => sanitize_text_field($synopsis['headline'] ?? $slug),
            'content_body'      => '', // Summaries don't have content body
            'excerpt'           => wp_json_encode($synopsis), // Store full synopsis as excerpt
            'seo_metadata'      => null,
            'sponsor_commentary' => '',
            'smma_flags'        => null,
            'parent_post_id'    => null,
            'planner_session_id' => $planner_session_id,
            'planner_data'      => !empty($planner_data) ? wp_json_encode($planner_data) : null,
            'created_at'        => current_time('mysql'),
            'updated_at'        => current_time('mysql')
        ];
        
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        $registry_id = $wpdb->insert_id;
        
        $this->invalidate_article_cache(0, $slug);
        return $registry_id;
    }
    
    /**
     * Create a Framework entry in the registry.
     *
     * @param string $slug               The article slug.
     * @param array  $framework          The framework data.
     * @param int    $planner_session_id The planner session post ID.
     * @param array  $planner_data       The planner phase data.
     * @return int|WP_Error The registry ID or error.
     */
    public function create_framework(string $slug, array $framework, int $planner_session_id, array $planner_data = []) {
        global $wpdb;
        
        $data = [
            'target_blog_id'     => 0, // Will be set when framework transitions to draft
            'slug'               => sanitize_title($slug),
            'article_status'     => 'Framework',
            'title'              => sanitize_text_field($framework['title'] ?? $slug),
            'content_body'       => wp_json_encode($framework), // Store full framework as content_body
            'excerpt'            => '',
            'seo_metadata'       => null,
            'sponsor_commentary' => '',
            'smma_flags'         => null,
            'parent_post_id'     => null,
            'planner_session_id' => $planner_session_id,
            'planner_data'       => !empty($planner_data) ? wp_json_encode($planner_data) : null,
            'created_at'         => current_time('mysql'),
            'updated_at'         => current_time('mysql')
        ];
        
        $result = $wpdb->insert($this->table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        $registry_id = $wpdb->insert_id;
        
        $this->invalidate_article_cache(0, $slug);
        return $registry_id;
    }
    
    /**
     * Create a Summary entry AND a WordPress draft post with the summary ACF block.
     *
     * Called directly by AIWorker (no HTTP loopback). Creates the registry row,
     * creates a WP post on the target blog, inserts the acf/summary block marker,
     * and stores ACF-compatible post meta.
     *
     * @param string $slug           Article slug.
     * @param array  $synopsis       Synopsis data (headline, summary, key_points).
     * @param int    $parent_post_id Planner session post ID.
     * @param array  $planner_data   Planner phase data.
     * @return array{registry_id: int, post_id: int}|WP_Error
     */
    public function create_summary_with_post( string $slug, array $synopsis, int $parent_post_id, array $planner_data = [] ) {
        global $wpdb;

        $headline     = sanitize_text_field( $synopsis['headline'] ?? $slug );
        $summary_text = $synopsis['summary'] ?? '';
        $key_points   = $synopsis['key_points'] ?? [];
        $target_blog_id = 0; // Will be set when framework transitions to draft

        // 1. Insert registry row
        $insert_data = [
            'target_blog_id'      => $target_blog_id,
            'slug'                => sanitize_title( $slug ),
            'article_status'      => 'Summary',
            'title'               => $headline,
            'content_body'        => '',
            'excerpt'             => wp_json_encode( $synopsis ),
            'seo_metadata'        => null,
            'sponsor_commentary'  => '',
            'smma_flags'          => null,
            'parent_post_id'      => null,
            'planner_session_id'  => $parent_post_id,
            'planner_data'        => ! empty( $planner_data ) ? wp_json_encode( $planner_data ) : null,
            'created_at'          => current_time( 'mysql' ),
            'updated_at'          => current_time( 'mysql' ),
        ];

        $result = $wpdb->insert( $this->table, $insert_data );
        if ( $result === false ) {
            return new WP_Error( 'db_error', $wpdb->last_error );
        }
        $registry_id = $wpdb->insert_id;

        // 2. Create WordPress draft post
        // Posts are always created on the current blog (blog 1 for the admin context)
        // target_blog_id is preserved in the registry table for routing purposes
        $post_data = [
            'post_title'   => $headline,
            'post_content' => '',
            'post_status'  => 'draft',
            'post_type'    => 'post',
            'post_author'  => get_current_user_id() ?: 1,
        ];
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // 3. Link registry to post
        $wpdb->update(
            $this->table,
            [ 'wp_post_id' => $post_id, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $registry_id ]
        );

        // 4. Store ACF post meta
        $valid_points = array_values( array_filter( $key_points, fn( $p ) => ! empty( trim( $p ) ) ) );
        update_post_meta( $post_id, 'kh_summary_headline', $headline );
        update_post_meta( $post_id, 'kh_summary_text', $summary_text );
        update_post_meta( $post_id, 'kh_summary_key_points', count( $valid_points ) );
        foreach ( $valid_points as $i => $point ) {
            update_post_meta( $post_id, "kh_summary_key_points_{$i}_bullet", sanitize_text_field( $point ) );
        }

        // 5. Insert acf/summary block marker into post_content
        $block_data = [
            'kh_summary_headline' => $headline,
            'kh_summary_text'     => $summary_text,
            'kh_summary_key_points' => count( $valid_points ),
        ];
        foreach ( $valid_points as $i => $point ) {
            $block_data[ "kh_summary_key_points_{$i}_bullet" ] = sanitize_text_field( $point );
        }

        if ( class_exists( '\KH\ContentRegistry\Blocks\BlockRegistrar' ) ) {
            $block_marker = \KH\ContentRegistry\Blocks\BlockRegistrar::build_block_marker( 'acf/summary', $block_data );
            $heading_block = '<!-- wp:heading {"level":2} --><h2>Summary</h2><!-- /wp:heading -->';
            $new_content = $heading_block . "\n\n" . $block_marker;
            // Upsert doesn't matter here since we are inserting into empty content anyway
            wp_update_post( [
                'ID'           => $post_id,
                'post_content' => wp_slash( $new_content ),
            ], true );
        }

        // 6. Invalidate cache
        $this->invalidate_article_cache( $target_blog_id, $slug );

        return [
            'registry_id' => $registry_id,
            'post_id'     => $post_id,
        ];
    }

    /**
     * Flatten a framework field value to a displayable string.
     *
     * The LLM output has nested objects/arrays (e.g. article_idea: {title, summary, keywords}).
     * ACF post meta fields are flat strings. This helper extracts readable text.
     */
    private function flatten_framework_field( $value ): string {
        if ( is_string( $value ) ) {
            return $value;
        }
        if ( ! is_array( $value ) ) {
            return '';
        }
        // Check if it's an object-like array with known keys
        $title   = $value['title'] ?? $value['headline'] ?? '';
        $summary = $value['summary'] ?? $value['text'] ?? '';
        if ( ! empty( $title ) && ! empty( $summary ) ) {
            return "**{$title}**\n\n{$summary}";
        }
        if ( ! empty( $title ) ) {
            return $title;
        }
        if ( ! empty( $summary ) ) {
            return $summary;
        }
        // Numeric array: implode with line breaks
        $parts = array_filter( $value, 'is_string' );
        if ( ! empty( $parts ) ) {
            return implode( "\n\n", $parts );
        }
        // Nested arrays: try extracting 'tone' or 'structure' from writer_guidance
        $nested_parts = [];
        foreach ( $value as $sub_key => $sub_val ) {
            if ( is_string( $sub_val ) ) {
                $nested_parts[] = "**" . ucfirst( str_replace( '_', ' ', $sub_key ) ) . ":** {$sub_val}";
            }
        }
        if ( ! empty( $nested_parts ) ) {
            return implode( "\n\n", $nested_parts );
        }
        // Fallback: JSON encode
        return wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    public function insert_framework_block( int $post_id, array $framework ) {
        global $wpdb;

        // Look up registry entry to get target_blog_id for multisite switching
        $entry = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE wp_post_id = %d",
                $post_id
            )
        );

        if ( ! $entry ) {
            return new WP_Error( 'registry_not_found', "No registry entry found for post {$post_id}." );
        }

        // Posts are now all on blog 1, so no switch_to_blog needed
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'post_not_found', "Post {$post_id} not found." );
        }

        // 1. Update registry entry status to Framework
        $wpdb->update(
            $this->table,
            [ 'article_status' => 'Framework', 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $entry->id ]
        );

        // Extract citations from observations and build formatted observations string
        $citations = [];
        $observations = $framework['observations'] ?? '';
        $formatted_obs = [];

        if ( is_array( $observations ) ) {
            $obs_array = $observations;
        } elseif ( is_string( $observations ) ) {
            $obs_array = json_decode( $observations, true ) ?? [];
        } else {
            $obs_array = [];
        }

        foreach ( $obs_array as $obs ) {
            if ( is_array( $obs ) ) {
                $headline = $obs['headline'] ?? $obs['title'] ?? '';
                $detail   = $obs['detail'] ?? $obs['summary'] ?? $obs['text'] ?? '';
                $evidence = $obs['evidence'] ?? [];

                $obs_text = [];
                if ( $headline && $detail ) {
                    $obs_text[] = "**{$headline}**\n{$detail}";
                } elseif ( $headline ) {
                    $obs_text[] = $headline;
                } elseif ( $detail ) {
                    $obs_text[] = $detail;
                }

                if ( ! empty( $evidence ) && is_array( $evidence ) ) {
                    foreach ( $evidence as $ev ) {
                        $idx = $ev['citation_index'] ?? '';
                        $snippet = $ev['passage_snippet'] ?? '';
                        if ( $snippet ) {
                            $citations[] = [
                                'text' => $snippet,
                                'link' => $ev['url'] ?? '',
                            ];
                            // Append citation notation if appropriate
                            $obs_text[] = "[{$idx}] \"{$snippet}\"";
                        }
                    }
                }
                
                if ( ! empty( $obs_text ) ) {
                    $formatted_obs[] = implode( "\n", $obs_text );
                }
            }
        }
        
        $observations_string = !empty($formatted_obs) ? implode( "\n\n", $formatted_obs ) : $this->flatten_framework_field( $observations );

        // 2. Flatten framework fields and store ACF post meta
        $fields = [
            'kh_framework_article_idea'    => $this->flatten_framework_field( $framework['article_idea'] ?? '' ),
            'kh_framework_overview'        => $this->flatten_framework_field( $framework['overview'] ?? '' ),
            'kh_framework_context'         => $this->flatten_framework_field( $framework['context'] ?? '' ),
            'kh_framework_application'     => $this->flatten_framework_field( $framework['application'] ?? '' ),
            'kh_framework_writer_guidance' => $this->flatten_framework_field( $framework['writer_guidance'] ?? '' ),
            'kh_framework_observations'    => $observations_string,
        ];
        foreach ( $fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        // 3. Prepare block markers
        if ( class_exists( '\KH\ContentRegistry\Blocks\BlockRegistrar' ) ) {
            $content = $post->post_content;
            
            // Clean out existing framework/footnotes blocks
            $escaped_fr = preg_quote( 'acf/framework', '/' );
            $content = preg_replace( '/<!-- wp:' . $escaped_fr . '(?: .*?)? \/-->\s*/s', '', $content );
            $escaped_fn = preg_quote( 'acf/footnotes', '/' );
            $content = preg_replace( '/<!-- wp:' . $escaped_fn . '(?: .*?)? \/-->\s*/s', '', $content );
            $content = preg_replace( '/<!-- wp:heading {"level":2} -->\s*<h2>Framework<\/h2>\s*<!-- \/wp:heading -->\s*/s', '', $content );

            // Build Framework Block
            $framework_block = \KH\ContentRegistry\Blocks\BlockRegistrar::build_block_marker( 'acf/framework', $fields );
            
            // Build Footnotes Block
            $footnotes_block = '';
            if ( ! empty( $citations ) ) {
                $footnote_data = [];
                foreach ( $citations as $i => $cite ) {
                    $footnote_data["footnotes_{$i}_reference_text"] = $cite['text'];
                    $footnote_data["footnotes_{$i}_reference_link"] = $cite['link'];
                }
                $footnote_data['footnotes'] = count($citations);
                // Reference the original ACF field key from kh-ad-manager so ACF maps block data correctly
                $footnote_data['_footnotes'] = 'field_685be9239f51e';
                $footnotes_block = \KH\ContentRegistry\Blocks\BlockRegistrar::build_block_marker( 'acf/footnotes', $footnote_data );
                
                // Store ACF meta for footnotes (keys already have 'footnotes_' prefix, so store directly)
                update_post_meta( $post_id, 'footnotes', count($citations) );
                foreach ( $citations as $i => $cite ) {
                    update_post_meta( $post_id, "footnotes_{$i}_reference_text", sanitize_textarea_field( $cite['text'] ) );
                    update_post_meta( $post_id, "footnotes_{$i}_reference_link", esc_url_raw( $cite['link'] ) );
                }
            }

            // Append at the bottom
            $heading_block = '<!-- wp:heading {"level":2} --><h2>Framework</h2><!-- /wp:heading -->';
            
            // Re-combine: original content (which has Summary), then Framework heading, Framework block, Footnotes block
            $new_content_parts = [ trim($content), $heading_block, $framework_block ];
            if ( $footnotes_block ) {
                $new_content_parts[] = $footnotes_block;
            }
            
            // Filter out empty parts and join
            $new_content = implode( "\n\n", array_filter( $new_content_parts ) );

            wp_update_post( [
                'ID'           => $post_id,
                'post_content' => wp_slash( $new_content ),
            ], true );
        }

        return [ 'post_id' => $post_id ];
    }

    private function decode_json_fields(object &$article) {
        $json_fields = ['seo_metadata', 'smma_flags', 'geo_flags', 'planner_data'];
        
        foreach ($json_fields as $field) {
            if (!empty($article->$field)) {
                $article->$field = json_decode($article->$field, true);
            } else {
                $article->$field = [];
            }
        }
    }

     /**
      * Insert a Commentary block into an existing post.
      *
      * Inserts the commentary block after the Framework block if present,
      * or after the Summary block otherwise.
      *
      * @param int   $post_id          The WordPress post ID.
      * @param array $commentary       Commentary data with keys:
      *                                 - commentary_text (required)
      *                                 - source_name
      *                                 - source_url
      *                                 - source_company
      *                                 - source_title
      *                                 - citations (array of {title, text, url})
      * @return array|WP_Error
      */
     public function insert_commentary_block( int $post_id, array $commentary ) {
         global $wpdb;

         $post = get_post( $post_id );
         if ( ! $post ) {
             return new WP_Error( 'post_not_found', "Post {$post_id} not found." );
         }

         // Build block data
         $block_data = [
             'kh_commentary_text'        => $commentary['commentary_text'] ?? '',
             'kh_commentary_source_name' => $commentary['source_name'] ?? '',
             'kh_commentary_source_url'    => $commentary['source_url'] ?? '',
             'kh_commentary_source_company' => $commentary['source_company'] ?? '',
             'kh_commentary_source_title'   => $commentary['source_title'] ?? '',
         ];

         // Add citations as repeater data
         $citations = $commentary['citations'] ?? [];
         $valid_citations = array_values( array_filter( $citations, fn( $c ) => ! empty( $c['title'] ) || ! empty( $c['text'] ) ) );
         $block_data['kh_commentary_citations'] = count( $valid_citations );
         foreach ( $valid_citations as $i => $citation ) {
             $block_data[ "kh_commentary_citations_{$i}_title" ] = sanitize_text_field( $citation['title'] ?? '' );
             $block_data[ "kh_commentary_citations_{$i}_text" ] = sanitize_textarea_field( $citation['text'] ?? '' );
             $block_data[ "kh_commentary_citations_{$i}_url" ] = esc_url_raw( $citation['url'] ?? '' );
         }

         // Store as post meta
         BlockRegistrar::store_block_meta( $post_id, 'kh_commentary', [
             'commentary_text' => $commentary['commentary_text'] ?? '',
             'source_name'     => $commentary['source_name'] ?? '',
             'source_url'      => $commentary['source_url'] ?? '',
             'source_company'  => $commentary['source_company'] ?? '',
             'source_title'    => $commentary['source_title'] ?? '',
             'citations'       => $valid_citations,
         ] );

          // Build and insert block marker
          $block_marker = BlockRegistrar::build_block_marker( 'acf/commentary', $block_data );
          
          // Insert after Framework/footnotes, or at the end if no Framework
          $content = $post->post_content;
          
          // Remove existing commentary blocks and heading to prevent duplicates
          $content = preg_replace( '/<!-- wp:acf\/commentary .*? \/-->\s*/s', '', $content );
          $content = preg_replace( '/<!-- wp:heading \\{"level":2\\} -->\s*<h2>Commentary<\/h2>\s*<!-- \/wp:heading -->\s*/s', '', $content );
          
          // Build the commentary section with heading wrapper
          $commentary_heading = '<!-- wp:heading {"level":2} --><h2>Commentary</h2><!-- /wp:heading -->';
          
          // Check if a commentary heading already exists (don't add a second one)
          $has_commentary_heading = strpos( $content, '<h2>Commentary</h2>' ) !== false;
          
          if ( ! $has_commentary_heading ) {
              $commentary_section = $commentary_heading . "\n\n" . $block_marker;
          } else {
              $commentary_section = $block_marker;
          }
          
          // Try to insert after footnotes block, otherwise after framework, otherwise at end
          if ( strpos( $content, 'wp:acf/footnotes' ) !== false ) {
              // Insert after footnotes
              $content = preg_replace(
                  '/(<!-- wp:acf\/footnotes.*?\/-->)/s',
                  '$1' . "\n\n" . $commentary_section,
                  $content
              );
          } elseif ( strpos( $content, 'wp:acf/framework' ) !== false ) {
              // Insert after framework heading and block
              $content = preg_replace(
                  '/(<!-- wp:heading \\{"level":2\\} --><h2>Framework<\/h2><!-- \/wp:heading -->\s*<!-- wp:acf\/framework.*?\/-->)/s',
                  '$1' . "\n\n" . $commentary_section,
                  $content
              );
          } else {
              // Append at the end
              $content = rtrim( $content ) . "\n\n" . $commentary_section;
          }

          wp_update_post( [
              'ID'           => $post_id,
              'post_content' => wp_slash( $content ),
          ], true );

        return [ 'post_id' => $post_id ];
    }

    /**
     * Check if a post has at least one commentary block.
     *
     * @param int $wp_post_id The WordPress post ID.
     * @return bool
     */
    public function article_has_commentary_block( int $wp_post_id ): bool {
        $post = get_post( $wp_post_id );
        if ( ! $post ) {
            return false;
        }
        return strpos( $post->post_content, 'wp:acf/commentary' ) !== false;
    }

    /**
     * Check commentary status for a post.
     *
     * Returns whether the post has commentary blocks and the count.
     *
     * @param int $wp_post_id The WordPress post ID.
     * @return array{has_commentary: bool, commentary_count: int}
     */
    public function check_commentary_for_post( int $wp_post_id ): array {
        $post = get_post( $wp_post_id );
        if ( ! $post ) {
            return [ 'has_commentary' => false, 'commentary_count' => 0 ];
        }
        preg_match_all( '/<!-- wp:acf\/commentary /s', $post->post_content, $matches );
        return [
            'has_commentary'  => count( $matches[0] ) > 0,
            'commentary_count' => count( $matches[0] ),
        ];
    }

    /**
     * Update an article's registry row by WordPress post ID.
     *
     * @param int   $wp_post_id The WordPress post ID.
     * @param array $data       Fields to update.
     * @return bool|WP_Error
     */
    public function update_article_by_post_id( int $wp_post_id, array $data ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE wp_post_id = %d LIMIT 1",
            $wp_post_id
        ) );
        if ( ! $row ) {
            return new WP_Error( 'not_found', 'No registry entry found for this post.' );
        }
        return $this->update_article( (int) $row->id, $data );
    }

    /**
     * Create a child registry entry for a cloned post.
     *
     * Used by AllocationService to record parent/child relationships in the
     * registry after Virtual Router cloning.
     *
     * @param int    $parent_wp_post_id The parent post's WordPress post ID.
     * @param int    $target_blog_id    The target blog ID this was cloned to.
     * @param string $clone_type        One of 'clone' or 'clone_rewrite'.
     * @param int    $child_wp_post_id  The target (cloned) WordPress post ID.
     * @param array  $child_data        Optional override data (title, slug, content).
     * @return int|WP_Error The new child registry ID or error.
     */
    public function create_child_article( int $parent_wp_post_id, int $target_blog_id, string $clone_type, int $child_wp_post_id, array $child_data = [] ) {
        global $wpdb;

        // Validate clone_type
        $valid_clone_types = [ 'clone', 'clone_rewrite' ];
        if ( ! in_array( $clone_type, $valid_clone_types, true ) ) {
            return new WP_Error( 'invalid_clone_type', "Invalid clone_type: {$clone_type}" );
        }

        // Determine the article_status from clone_type
        $status_map = [
            'clone'         => 'Cloned',
            'clone_rewrite' => 'CloneRw',
        ];
        $article_status = $status_map[ $clone_type ];

        // Get parent registry entry to copy relevant fields
        $parent_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE wp_post_id = %d AND (parent_post_id IS NULL OR parent_post_id = 0) LIMIT 1",
            $parent_wp_post_id
        ) );

        if ( ! $parent_row ) {
            // Fallback: parent might be a non-registry post, create minimal entry
            $parent_post = get_post( $parent_wp_post_id );
            $title  = $child_data['title'] ?? ( $parent_post ? $parent_post->post_title : 'Cloned Post' );
            $slug   = $child_data['slug'] ?? ( $parent_post ? $parent_post->post_name . '-clone-' . $target_blog_id : 'clone-' . $target_blog_id . '-' . time() );
            $content_body = $child_data['content_body'] ?? ( $parent_post ? $parent_post->post_content : '' );
            $excerpt = $child_data['excerpt'] ?? ( $parent_post ? $parent_post->post_excerpt : '' );
        } else {
            $this->decode_json_fields( $parent_row );
            $title  = $child_data['title'] ?? $parent_row->title;
            $slug   = $child_data['slug'] ?? ( $parent_row->slug . '-clone-' . $target_blog_id );
            $content_body = $child_data['content_body'] ?? $parent_row->content_body ?? '';
            $excerpt = $child_data['excerpt'] ?? $parent_row->excerpt ?? '';
        }

        $insert_data = [
            'parent_post_id'     => $parent_wp_post_id,
            'clone_type'         => $clone_type,
            'wp_post_id'         => $child_wp_post_id,
            'target_blog_id'     => $target_blog_id,
            'slug'               => sanitize_title( $slug ),
            'article_status'     => $article_status,
            'title'              => sanitize_text_field( $title ),
            'content_body'       => $content_body,
            'excerpt'            => $excerpt,
            'seo_metadata'       => null,
            'sponsor_commentary' => '',
            'smma_flags'         => null,
            'created_at'         => current_time( 'mysql' ),
            'updated_at'         => current_time( 'mysql' ),
        ];

        $result = $wpdb->insert( $this->table, $insert_data );

        if ( $result === false ) {
            return new WP_Error( 'db_error', $wpdb->last_error );
        }

        $registry_id = $wpdb->insert_id;

        $this->invalidate_article_cache( $target_blog_id, sanitize_title( $slug ) );

        return $registry_id;
    }

    /**
     * Get all child (cloned) registry entries for a given parent WP post ID.
     *
     * @param int $wp_post_id The parent WordPress post ID.
     * @return array Array of child registry rows with decoded JSON fields.
     */
    public function get_child_posts( int $wp_post_id ): array {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE parent_post_id = %d
             ORDER BY created_at DESC",
            $wp_post_id
        );

        $articles = $wpdb->get_results( $query );

        foreach ( $articles as $article ) {
            $this->decode_json_fields( $article );
        }

        return $articles;
    }
}

