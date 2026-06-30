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

    public function create_article(array $args): int|WP_Error {
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
        
        // Create WordPress post for Gutenberg editing
        $post_id = $this->create_wordpress_post($args, $registry_id);
        if ($post_id) {
            $wpdb->update(
                $this->table,
                ['wp_post_id' => $post_id],
                ['id' => $registry_id]
            );
        }
        
        $this->invalidate_article_cache($data['target_blog_id'], $data['slug']);
        return $registry_id;
    }
    
    /**
     * Create a WordPress post for the article.
     */
    private function create_wordpress_post(array $args, int $registry_id): int|WP_Error {
        $target_blog_id = $args['target_blog_id'] ?? get_current_blog_id();
        
        // Switch to the target blog to create the post there
        if (function_exists('switch_to_blog') && $target_blog_id != get_current_blog_id()) {
            switch_to_blog($target_blog_id);
        }
        
        $post_data = [
            'post_title' => $args['title'],
            'post_content' => $args['content_body'] ?? '',
            'post_excerpt' => $args['excerpt'] ?? '',
            'post_status' => $this->map_status_to_wp($args['article_status']),
            'post_type' => 'post',
            'post_author' => get_current_user_id() ?: 1,
        ];
        
        $post_id = wp_insert_post($post_data);
        
        // Restore the current blog
        if (function_exists('restore_current_blog') && $target_blog_id != get_current_blog_id()) {
            restore_current_blog();
        }
        
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
            'Draft' => 'draft',
            'Scheduled' => 'future',
            'Live' => 'publish',
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
            'seo_metadata', 'smma_flags', 'geo_flags', 'article_status'
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
            'Framework' => ['Draft'],
            'Draft' => ['Scheduled', 'Live'],
            'Scheduled' => ['Live']
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
        
        $query = "SELECT * FROM $this->table WHERE article_status = %s";
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
             WHERE article_status IN ('Summary', 'Framework')"
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

    private function decode_json_fields(object &$article) {
        $json_fields = ['seo_metadata', 'smma_flags', 'geo_flags'];
        
        foreach ($json_fields as $field) {
            if (!empty($article->$field)) {
                $article->$field = json_decode($article->$field, true);
            } else {
                $article->$field = [];
            }
        }
    }
}