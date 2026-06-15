<?php

namespace KH\Editorial\Services;

use KH\Editorial\Core\LLMService;
use KH\Editorial\Database\AllocationTable;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles cross-site content allocation: clone posts to target sites,
 * optionally rewrite via LLM, and track lineage.
 */
class AllocationService {

    /**
     * All target sites in the network (excludes hub site, blog_id=1).
     *
     * slug => [ 'label' => human-readable, 'domain' => site domain ]
     */
    const TARGET_SITES = [
        'pricing'                => 'Revenue Operations',
        'aftermarket'            => 'Aftermarket Operations',
        'field-service'          => 'Field Service Management',
        'spare-parts'            => 'Spare Parts & Logistics',
        'ecommerce'              => 'Industrial eCommerce',
        'industrial'             => 'Industrial Operations',
        'aerospace'              => 'Aerospace Engineering',
        'utilities-ops'          => 'Utilities Operations',
        'built-env'              => 'Infrastructure Operations',
        'manufacturing-flagship' => 'Modern Manufacturing',
    ];

    /**
     * Map site slugs to blog path fragments for blog_id resolution.
     */
    const SLUG_TO_BLOG_PATH = [
        'pricing'                => 'pricing',
        'aftermarket'            => 'aftermarket',
        'field-service'          => 'field-service',
        'spare-parts'            => 'spare-parts',
        'ecommerce'              => 'ecommerce',
        'industrial'             => 'industrial',
        'aerospace'              => 'aerospace',
        'utilities-ops'          => 'utilities-ops',
        'built-env'              => 'built-env',
        'manufacturing-flagship' => 'manufacturing-flagship',
    ];

    /**
     * Get all available target sites with their blog IDs.
     *
     * @return array [{ slug, label, blog_id, url }]
     */
    public function get_available_sites(): array {
        $sites = [];

        foreach ( self::TARGET_SITES as $slug => $label ) {
            $blog_id = $this->resolve_blog_id( $slug );
            if ( ! $blog_id ) {
                continue;
            }

            $blog_details = get_blog_details( $blog_id );

            $sites[] = [
                'slug'    => $slug,
                'label'   => $label,
                'blog_id' => $blog_id,
                'url'     => $blog_details ? $blog_details->siteurl : '',
            ];
        }

        return $sites;
    }

    /**
     * Resolve a site slug to a blog_id.
     *
     * @param string $slug
     * @return int|null
     */
    public function resolve_blog_id( string $slug ): ?int {
        $path = self::SLUG_TO_BLOG_PATH[ $slug ] ?? null;
        if ( ! $path ) {
            return null;
        }

        $blog_id = get_blog_id_from_url( get_network()->domain, '/' . $path . '/' );
        if ( ! $blog_id || $blog_id === get_current_blog_id() ) {
            return null;
        }

        return (int) $blog_id;
    }

    /**
     * Clone a post to a target site, optionally rewriting content via LLM.
     *
     * @param int    $origin_post_id
     * @param int    $target_blog_id
     * @param bool   $rewrite       Whether to run the LLM rewrite agent.
     * @return array { success, target_post_id, edit_url, rewrite_applied, message }
     */
    public function clone_to_site( int $origin_post_id, int $target_blog_id, bool $rewrite = false ): array {
        // Check if already cloned
        $existing = AllocationTable::find( $origin_post_id, $target_blog_id );
        if ( $existing ) {
            $edit_url = $this->get_cross_site_edit_link( $target_blog_id, (int) $existing['target_post_id'] );
            return [
                'success'         => true,
                'target_post_id'  => (int) $existing['target_post_id'],
                'edit_url'        => $edit_url,
                'rewrite_applied' => (bool) $existing['rewrite_applied'],
                'message'         => __( 'Post already allocated to this site.', 'kh-editorial-intelligence' ),
            ];
        }

        // Ensure user exists on target blog
        $this->ensure_user_on_blog( get_current_user_id(), $target_blog_id );

        // Get the origin post
        $origin_post = get_post( $origin_post_id );
        if ( ! $origin_post ) {
            return [
                'success' => false,
                'message' => __( 'Origin post not found.', 'kh-editorial-intelligence' ),
            ];
        }

        // Switch to target blog
        switch_to_blog( $target_blog_id );

        // Build clone args
        $post_args = [
            'post_title'   => $origin_post->post_title,
            'post_content' => $origin_post->post_content,
            'post_excerpt' => $origin_post->post_excerpt,
            'post_type'    => $origin_post->post_type,
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
        ];

        // Insert the clone
        $target_post_id = wp_insert_post( $post_args, true );

        if ( is_wp_error( $target_post_id ) ) {
            restore_current_blog();
            return [
                'success' => false,
                'message' => $target_post_id->get_error_message(),
            ];
        }

        // Clone taxonomies
        $this->clone_taxonomies( $origin_post_id, $target_post_id );

        // Clone post meta (ACF fields, etc.)
        $this->clone_post_meta( $origin_post_id, $target_post_id );

        $rewrite_applied = false;

        // Run LLM rewrite if requested
        if ( $rewrite ) {
            $rewrite_result = $this->rewrite_for_site( $target_post_id, $target_blog_id );
            if ( $rewrite_result['success'] ) {
                $rewrite_applied = true;
            }
        }

        // Record allocation
        $record_id = AllocationTable::record(
            get_current_blog_id(), // origin (hub)
            $origin_post_id,
            $target_blog_id,
            $target_post_id,
            $rewrite_applied
        );

        // Get edit link for the target post
        $edit_url = get_edit_post_link( $target_post_id, 'raw' );

        restore_current_blog();

        return [
            'success'         => true,
            'target_post_id'  => $target_post_id,
            'edit_url'        => $edit_url,
            'rewrite_applied' => $rewrite_applied,
            'message'         => $rewrite_applied
                ? __( 'Post cloned and rewritten successfully.', 'kh-editorial-intelligence' )
                : __( 'Post cloned successfully (no rewrite).', 'kh-editorial-intelligence' ),
        ];
    }

    /**
     * Get allocation status for an origin post across all target sites.
     *
     * @param int $origin_post_id
     * @return array [{ slug, label, blog_id, allocated, target_post_id, edit_url, rewrite_applied }]
     */
    public function get_allocation_status( int $origin_post_id ): array {
        $allocations = AllocationTable::get_for_post( $origin_post_id );
        $sites       = $this->get_available_sites();
        $status      = [];

        foreach ( $sites as $site ) {
            $alloc = $allocations[ $site['blog_id'] ] ?? null;
            $status[] = [
                'slug'            => $site['slug'],
                'label'           => $site['label'],
                'blog_id'         => $site['blog_id'],
                'allocated'       => $alloc !== null,
                'target_post_id'  => $alloc ? (int) $alloc['target_post_id'] : null,
                'edit_url'        => $alloc ? $this->get_cross_site_edit_link( $site['blog_id'], (int) $alloc['target_post_id'] ) : '',
                'rewrite_applied' => $alloc ? (bool) $alloc['rewrite_applied'] : false,
            ];
        }

        return $status;
    }

    /**
     * Get a cross-site edit link by switching to the target blog temporarily.
     *
     * @param int $blog_id
     * @param int $post_id
     * @return string
     */
    public function get_cross_site_edit_link( int $blog_id, int $post_id ): string {
        switch_to_blog( $blog_id );
        $url = get_edit_post_link( $post_id, 'raw' );
        restore_current_blog();
        return $url ?: '';
    }

    /**
     * Rewrite cloned post content for the target site's audience via LLM.
     *
     * @param int $target_post_id The post ID on the target blog (must be switched).
     * @param int $target_blog_id
     * @return array { success, message }
     */
    private function rewrite_for_site( int $target_post_id, int $target_blog_id ): array {
        if ( ! LLMService::is_configured() ) {
            return [
                'success' => false,
                'message' => __( 'LLM not configured — skipping rewrite.', 'kh-editorial-intelligence' ),
            ];
        }

        $post       = get_post( $target_post_id );
        $site_label = $this->get_site_label_by_blog_id( $target_blog_id );

        if ( ! $post || ! $site_label ) {
            return [ 'success' => false, 'message' => 'Could not resolve post or site for rewrite.' ];
        }

        $prompt = $this->build_rewrite_prompt( $post, $site_label );

        try {
            $llm = new LLMService();
            $result = $llm->post_completion(
                'content_rewrite',
                $prompt,
                [ 'temperature' => 0.7, 'max_tokens' => 4096 ]
            );

            $parsed = $this->parse_rewrite_response( $result );

            if ( ! $parsed ) {
                return [ 'success' => false, 'message' => 'Failed to parse LLM rewrite response.' ];
            }

            // Update the cloned post with rewritten content
            wp_update_post( [
                'ID'           => $target_post_id,
                'post_title'   => $parsed['rewritten_title'] ?? $post->post_title,
                'post_content' => $parsed['rewritten_content'] ?? $post->post_content,
                'post_excerpt' => $parsed['rewritten_excerpt'] ?? $post->post_excerpt,
            ] );

            return [ 'success' => true, 'message' => 'Content rewritten for ' . $site_label . '.' ];
        } catch ( \Throwable $e ) {
            error_log( '[Allocation] LLM rewrite error for post ' . $target_post_id . ': ' . $e->getMessage() );
            return [ 'success' => false, 'message' => 'LLM rewrite failed: ' . $e->getMessage() ];
        }
    }

    /**
     * Build the rewrite prompt for the LLM.
     *
     * @param \WP_Post $post
     * @param string   $site_label
     * @return string
     */
    private function build_rewrite_prompt( \WP_Post $post, string $site_label ): string {
        $title   = $post->post_title;
        $excerpt = $post->post_excerpt ?: '';
        $content = wp_strip_all_tags( $post->post_content );
        // Truncate very long content to avoid token limits
        if ( strlen( $content ) > 8000 ) {
            $content = substr( $content, 0, 8000 ) . '...';
        }

        return <<<PROMPT
You are an editorial assistant rewriting content for a specific audience.

The target publication focuses on: {$site_label}

Rewrite the following article to better serve this audience:

1. TITLE: Adjust to emphasize {$site_label}-relevant keywords and framing.
2. EXCERPT: Write 1-2 sentences that appeal specifically to {$site_label} readers.
3. BODY CONTENT: Lightly shift framing, examples, and emphasis toward {$site_label} without fabricating facts, removing core information, or changing the article's essential structure.

Original title: {$title}
Original excerpt: {$excerpt}
Original content: {$content}

Return your response as a JSON object with exactly these three keys:
{
  "rewritten_title": "...",
  "rewritten_excerpt": "...",
  "rewritten_content": "..."
}

Return ONLY the JSON object, no other text.
PROMPT;
    }

    /**
     * Parse the LLM rewrite response into an associative array.
     *
     * @param array|string $response
     * @return array|null
     */
    private function parse_rewrite_response( $response ): ?array {
        $text = '';

        if ( is_string( $response ) ) {
            $text = $response;
        } elseif ( is_array( $response ) && isset( $response['content'] ) ) {
            $text = $response['content'];
        } elseif ( is_array( $response ) && isset( $response['choices'][0]['message']['content'] ) ) {
            $text = $response['choices'][0]['message']['content'];
        } else {
            return null;
        }

        // Try to extract JSON from the response
        $text = trim( $text );

        // Remove markdown code blocks if present
        if ( str_starts_with( $text, '```' ) ) {
            $text = preg_replace( '/^```(?:json)?\s*/', '', $text );
            $text = preg_replace( '/\s*```$/', '', $text );
        }

        $parsed = json_decode( $text, true );

        if ( ! is_array( $parsed ) ) {
            return null;
        }

        return [
            'rewritten_title'   => $parsed['rewritten_title'] ?? null,
            'rewritten_excerpt' => $parsed['rewritten_excerpt'] ?? null,
            'rewritten_content' => $parsed['rewritten_content'] ?? null,
        ];
    }

    /**
     * Get the human-readable site label from a blog_id.
     *
     * @param int $blog_id
     * @return string|null
     */
    private function get_site_label_by_blog_id( int $blog_id ): ?string {
        foreach ( self::TARGET_SITES as $slug => $label ) {
            if ( $this->resolve_blog_id( $slug ) === $blog_id ) {
                return $label;
            }
        }
        return null;
    }

    /**
     * Ensure a user exists on the target blog with at least editor capability.
     *
     * @param int $user_id
     * @param int $blog_id
     */
    private function ensure_user_on_blog( int $user_id, int $blog_id ): void {
        if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
            add_user_to_blog( $blog_id, $user_id, 'editor' );
        }
    }

    /**
     * Clone taxonomies from origin post to target post.
     *
     * @param int $origin_post_id
     * @param int $target_post_id
     */
    private function clone_taxonomies( int $origin_post_id, int $target_post_id ): void {
        $taxonomies = get_object_taxonomies( get_post_type( $origin_post_id ) );

        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $origin_post_id, $taxonomy, [ 'fields' => 'slugs' ] );

            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }

            // Ensure terms exist on target site
            $term_ids = [];
            foreach ( $terms as $slug ) {
                $term = get_term_by( 'slug', $slug, $taxonomy );
                if ( ! $term ) {
                    // Create the term if it doesn't exist on target
                    $inserted = wp_insert_term( $slug, $taxonomy );
                    if ( ! is_wp_error( $inserted ) ) {
                        $term_ids[] = (int) $inserted['term_id'];
                    }
                } else {
                    $term_ids[] = (int) $term->term_id;
                }
            }

            if ( ! empty( $term_ids ) ) {
                wp_set_object_terms( $target_post_id, $term_ids, $taxonomy );
            }
        }
    }

    /**
     * Clone post meta from origin to target.
     * Handles ACF fields and all other meta keys, skipping internal WP keys.
     *
     * @param int $origin_post_id
     * @param int $target_post_id
     */
    private function clone_post_meta( int $origin_post_id, int $target_post_id ): void {
        $skip_keys = [
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_wp_page_template',
        ];

        $meta = get_post_meta( $origin_post_id );

        foreach ( $meta as $key => $values ) {
            if ( in_array( $key, $skip_keys, true ) ) {
                continue;
            }

            // ACF fields start with underscore as the field key reference;
            // the actual value is stored without underscore. We need both.
            foreach ( $values as $value ) {
                update_post_meta( $target_post_id, $key, maybe_unserialize( $value ) );
            }
        }
    }
}