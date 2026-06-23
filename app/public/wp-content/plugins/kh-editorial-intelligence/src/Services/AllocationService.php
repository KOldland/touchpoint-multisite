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
        'pricing'                => 'Pricing in Manufacturing',
        'aftermarket'            => 'Aftermarket',
        'field-service'          => 'Field Service',
        'spare-parts'            => 'Spare Parts & Logistics',
        'ecommerce'              => 'eCommerce',
        'industrial'             => 'Industrial Equipment',
        'aerospace'              => 'Aerospace & Aviation',
        'utilities'              => 'Utilities Operations',
        'built-env'              => 'Built Environment',
        'manufacturing'          => 'Modern Manufacturing',
    ];

    /**
     * Map site slugs to immutable blog_ids.
     *
     * Override via `kh_allocation_blog_ids` filter in production (wp-config.php
     * or a mu-plugin). This avoids hardcoding domain paths that differ between
     * environments.
     *
     * @return array slug => blog_id
     */
    public function get_blog_id_map(): array {
        return apply_filters( 'kh_allocation_blog_ids', [
            'pricing'       => 19,
            'aftermarket'   => 21,
            'field-service' => 17,
            'spare-parts'   => 18,
            'ecommerce'     => 20,
            'industrial'    => 16,
            'aerospace'     => 13,
            'utilities'     => 22,
            'built-env'     => 15,
            'manufacturing' => 23,
        ] );
    }

    /**
     * Get all available target sites with their blog IDs.
     *
     * @return array [{ slug, label, blog_id, url }]
     */
    public function get_available_sites(): array {
        $blog_ids = $this->get_blog_id_map();
        $sites    = [];

        foreach ( self::TARGET_SITES as $slug => $label ) {
            $blog_id = $blog_ids[ $slug ] ?? null;
            if ( ! $blog_id || $blog_id === get_current_blog_id() ) {
                continue;
            }

            $blog_details = get_blog_details( $blog_id );
            if ( ! $blog_details ) {
                continue;
            }

            $sites[] = [
                'slug'    => $slug,
                'label'   => $label,
                'blog_id' => $blog_id,
                'url'     => $blog_details->siteurl,
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
        $blog_ids = $this->get_blog_id_map();
        $blog_id  = $blog_ids[ $slug ] ?? null;

        if ( ! $blog_id || $blog_id === get_current_blog_id() ) {
            return null;
        }

        return (int) $blog_id;
    }

    /**
     * Clone a post to a target site, optionally rewriting content via LLM.
     *
     * CRITICAL: All origin data (taxonomies, post meta, featured image) is
     * collected BEFORE switch_to_blog() so we read from the hub site's tables,
     * not the target site's (where the origin post doesn't exist).
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

        // Get the origin post
        $origin_post = get_post( $origin_post_id );
        if ( ! $origin_post ) {
            return [
                'success' => false,
                'message' => __( 'Origin post not found.', 'kh-editorial-intelligence' ),
            ];
        }

        // ── COLLECT ALL ORIGIN DATA BEFORE SWITCHING BLOGS ──
        // These must run on the hub site where the origin post exists.
        $taxonomy_data   = $this->collect_taxonomy_data( $origin_post_id );
        $meta_data       = $this->collect_post_meta_data( $origin_post_id );
        $thumbnail_url   = get_the_post_thumbnail_url( $origin_post_id, 'full' );
        $featured_image_id = get_post_thumbnail_id( $origin_post_id );
        $featured_image_alt = $featured_image_id
            ? get_post_meta( $featured_image_id, '_wp_attachment_image_alt', true )
            : '';

        // Capture origin blog ID before switching
        $origin_blog_id = get_current_blog_id();

        // Ensure user exists on target blog
        $this->ensure_user_on_blog( get_current_user_id(), $target_blog_id );

        // Switch to target blog
        switch_to_blog( $target_blog_id );

        // Build clone args — carry original author if possible, fallback to current user
        $original_author_id = (int) $origin_post->post_author;
        $target_author_id   = get_current_user_id();
        if ( $original_author_id && is_user_member_of_blog( $original_author_id, $target_blog_id ) ) {
            $target_author_id = $original_author_id;
        }

        $post_args = [
            'post_title'   => $origin_post->post_title,
            'post_content' => $origin_post->post_content,
            'post_excerpt' => $origin_post->post_excerpt,
            'post_type'    => $origin_post->post_type,
            'post_status'  => 'draft',
            'post_author'  => $target_author_id,
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

        // ── APPLY COLLECTED DATA TO THE TARGET POST ──
        $this->apply_taxonomy_data( $target_post_id, $taxonomy_data );
        $this->apply_post_meta_data( $target_post_id, $meta_data );

        // Clone featured image — sideload to target site's media library
        if ( $thumbnail_url ) {
            $new_thumb_id = $this->copy_attachment_to_target( $thumbnail_url );
            if ( $new_thumb_id ) {
                set_post_thumbnail( $target_post_id, $new_thumb_id );
                if ( $featured_image_alt ) {
                    update_post_meta( $new_thumb_id, '_wp_attachment_image_alt', $featured_image_alt );
                }
            }
        }

        // Remap multi_author references to target site
        $this->clone_author_profiles( $origin_post_id, $target_post_id );

        // ── COMMIT ALLOCATION RECORD IMMEDIATELY ──
        // Must happen before the LLM rewrite call so the clone is durable even
        // if the LLM times out. rewrite_applied starts as false and is updated
        // after a successful rewrite.
        $rewrite_applied = false;

        $record_id = AllocationTable::record(
            $origin_blog_id, // origin (hub)
            $origin_post_id,
            $target_blog_id,
            $target_post_id,
            $rewrite_applied  // starts false
        );

        // Get edit link for the target post
        $edit_url = get_edit_post_link( $target_post_id, 'raw' );

        restore_current_blog();

        // ── OPTIONAL LLM REWRITE (best-effort, after the clone is committed) ──
        $rewrite_message = '';
        if ( $rewrite ) {
            $rewrite_result = $this->rewrite_for_site( $target_post_id, $target_blog_id );
            if ( $rewrite_result['success'] ) {
                AllocationTable::update_rewrite_applied( $origin_post_id, $target_blog_id, true );
                $rewrite_applied = true;
                $rewrite_message = ' ' . $rewrite_result['message'];
            } else {
                // Rewrite failed but clone is safe — surface the error
                $rewrite_message = ' ' . sprintf(
                    /* translators: %s: error message from rewrite attempt */
                    __( 'Rewrite skipped: %s', 'kh-editorial-intelligence' ),
                    $rewrite_result['message'] ?? __( 'LLM unavailable', 'kh-editorial-intelligence' )
                );
                error_log( '[Allocation] Clone OK but rewrite failed for origin=' . $origin_post_id . ' target_blog=' . $target_blog_id . ': ' . json_encode( $rewrite_result ) );
            }
        }

        return [
            'success'         => true,
            'target_post_id'  => $target_post_id,
            'edit_url'        => $edit_url,
            'rewrite_applied' => $rewrite_applied,
            'message'         => $rewrite_applied
                ? ( __( 'Post cloned and rewritten successfully.', 'kh-editorial-intelligence' ) . $rewrite_message )
                : ( $rewrite
                    ? __( 'Post cloned but rewrite failed.', 'kh-editorial-intelligence' ) . $rewrite_message
                    : __( 'Post cloned successfully.', 'kh-editorial-intelligence' )
                ),
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
     * Rewrite an already-cloned post for a target site.
     *
     * Used by the standalone Rewrite button on already-allocated posts. Resolves
     * the target post from the allocation table, switches to the target blog,
     * runs the rewrite, and updates the allocation record.
     *
     * @param int $origin_post_id  Hub site post ID.
     * @param int $target_blog_id
     * @return array { success, message, edit_url }
     */
    public function rewrite_for_post( int $origin_post_id, int $target_blog_id ): array {
        $existing = AllocationTable::find( $origin_post_id, $target_blog_id );
        if ( ! $existing ) {
            return [
                'success' => false,
                'message' => __( 'Post has not been allocated to this site yet.', 'kh-editorial-intelligence' ),
            ];
        }

        $target_post_id = (int) $existing['target_post_id'];

        switch_to_blog( $target_blog_id );
        $rewrite_result = $this->rewrite_for_site( $target_post_id, $target_blog_id );

        if ( $rewrite_result['success'] ) {
            AllocationTable::update_rewrite_applied( $origin_post_id, $target_blog_id, true );
        }

        $edit_url = $this->get_cross_site_edit_link( $target_blog_id, $target_post_id );
        restore_current_blog();

        return array_merge( $rewrite_result, [ 'edit_url' => $edit_url ] );
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

        $post = get_post( $target_post_id );

        // Resolve the site slug from the blog ID so we can pull audience context
        $site_slug = $this->get_site_slug_by_blog_id( $target_blog_id );
        $site_label = $site_slug
            ? ( SiteAudienceProfile::get_label( $site_slug ) ?? 'target site' )
            : 'target site';

        if ( ! $post ) {
            return [ 'success' => false, 'message' => 'Could not resolve post for rewrite.' ];
        }

        $prompt = $this->build_rewrite_prompt( $post, $site_slug, $site_label );

        // Resolve agent model, provider, and fallback chain
        $route = LLMService::resolve_agent_model( 'content_rewrite' );

        // Build proper messages array for the LLM
        $messages = [
            [
                'role'    => 'user',
                'content' => $prompt,
            ],
        ];

        try {
            $result = LLMService::post_completion_with_retry( $messages, [
                'provider'       => $route['provider'],
                'model'          => $route['model'],
                'temperature'    => 0.7,
                'max_tokens'     => 4096,
                'fallback_chain' => $route['fallback_chain'],
            ] );

            if ( is_wp_error( $result ) ) {
                return [ 'success' => false, 'message' => $result->get_error_message() ];
            }

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
    private function build_rewrite_prompt( \WP_Post $post, string $site_slug, string $site_label ): string {
        $title   = $post->post_title;
        $excerpt = $post->post_excerpt ?: '';

        // Preserve semantic HTML tags so the LLM can maintain structure
        $content = $this->strip_non_semantic_tags( $post->post_content );
        // Truncate very long content to avoid token limits
        if ( strlen( $content ) > 8000 ) {
            $content = substr( $content, 0, 8000 ) . '...';
        }

        // Build audience context from the centralised profiles
        $audience_context = $site_slug
            ? SiteAudienceProfile::get_audience_context( $site_slug )
            : '';

        $audience_block = $audience_context
            ? "AUDIENCE PROFILE:\n{$audience_context}\n\n"
            : '';

        return <<<PROMPT
You are an editorial assistant rewriting content for a specific B2B publication audience.

The target publication is: {$site_label}

{$audience_block}Rewrite the following article to better serve this audience:

1. TITLE: Adjust to emphasize {$site_label}-relevant keywords and framing. Speak directly to the readers' priorities.
2. EXCERPT: Write 1-2 sentences that appeal specifically to {$site_label} readers, referencing their specific concerns.
3. BODY CONTENT: Lightly shift framing, examples, and emphasis toward {$site_label} without fabricating facts, removing core information, or changing the article's essential structure. Preserve the original HTML structure (headings, paragraphs, lists, links). Rewrite the text content while keeping all markup intact.

IMPORTANT: Maintain approximately the same word count as the original. Do not add invented statistics, quotes, or data points.

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
    private function get_site_slug_by_blog_id( int $blog_id ): ?string {
        foreach ( self::TARGET_SITES as $slug => $label ) {
            if ( $this->resolve_blog_id( $slug ) === $blog_id ) {
                return $slug;
            }
        }
        return null;
    }

    /**
     * Strip non-semantic HTML tags from post content, preserving structural markup
     * that the LLM should see: headings, paragraphs, lists, links, bold, italic.
     *
     * @param string $html
     * @return string
     */
    private function strip_non_semantic_tags( string $html ): string {
        // Allow these tags and their content
        $allowed = '<h1><h2><h3><h4><h5><h6><p><ul><ol><li><a><strong><b><em><i><blockquote><br><hr>';

        // Strip all tags not in the allowed list
        return strip_tags( $html, $allowed );
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

    // ──────────────────────────────────────────────
    // TAXONOMY COLLECTION & APPLICATION
    // ──────────────────────────────────────────────

    /**
     * Collect taxonomy data from the origin post BEFORE switching blogs.
     *
     * Returns an array of taxonomy => [ 'terms' => [ { slug, name, parent } ... ] ]
     * so we can recreate terms on the target site with proper names and hierarchy.
     *
     * @param int $origin_post_id
     * @return array
     */
    private function collect_taxonomy_data( int $origin_post_id ): array {
        $taxonomies = get_object_taxonomies( get_post_type( $origin_post_id ) );
        $data       = [];

        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $origin_post_id, $taxonomy, [
                'fields' => 'all',
            ] );

            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }

            $data[ $taxonomy ] = [
                'terms' => array_map( function ( $term ) {
                    return [
                        'slug'   => $term->slug,
                        'name'   => $term->name,
                        'parent' => $term->parent ? (int) $term->parent : 0,
                    ];
                }, $terms ),
            ];
        }

        return $data;
    }

    /**
     * Apply collected taxonomy data to the target post AFTER switching blogs.
     *
     * Creates terms on the target site if they don't exist, preserving names
     * and parent/child relationships.
     *
     * @param int   $target_post_id
     * @param array $taxonomy_data  From collect_taxonomy_data().
     */
    private function apply_taxonomy_data( int $target_post_id, array $taxonomy_data ): void {
        foreach ( $taxonomy_data as $taxonomy => $info ) {
            $term_ids = [];

            foreach ( $info['terms'] as $term_data ) {
                $slug = $term_data['slug'];

                // Check if term already exists on target site
                $existing = get_term_by( 'slug', $slug, $taxonomy );

                if ( $existing ) {
                    $term_ids[] = (int) $existing->term_id;
                    continue;
                }

                // Create the term — use the actual name, not the slug
                $insert_args = [];

                // Handle parent term if specified
                if ( $term_data['parent'] ) {
                    // Look up the parent term by slug on the hub to get its name,
                    // then try to find or create it on the target
                    $parent_term = get_term( $term_data['parent'], $taxonomy );
                    if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                        $target_parent = get_term_by( 'slug', $parent_term->slug, $taxonomy );
                        if ( ! $target_parent ) {
                            $parent_inserted = wp_insert_term(
                                $parent_term->name,
                                $taxonomy,
                                [ 'slug' => $parent_term->slug ]
                            );
                            if ( ! is_wp_error( $parent_inserted ) ) {
                                $insert_args['parent'] = (int) $parent_inserted['term_id'];
                            }
                        } else {
                            $insert_args['parent'] = (int) $target_parent->term_id;
                        }
                    }
                }

                $inserted = wp_insert_term(
                    $term_data['name'],
                    $taxonomy,
                    array_merge( [ 'slug' => $slug ], $insert_args )
                );

                if ( ! is_wp_error( $inserted ) ) {
                    $term_ids[] = (int) $inserted['term_id'];
                }
            }

            if ( ! empty( $term_ids ) ) {
                wp_set_object_terms( $target_post_id, $term_ids, $taxonomy );
            }
        }
    }

    // ──────────────────────────────────────────────
    // POST META COLLECTION & APPLICATION
    // ──────────────────────────────────────────────

    /**
     * Collect post meta from the origin post BEFORE switching blogs.
     *
     * Skips internal WP keys that shouldn't be copied.
     *
     * @param int $origin_post_id
     * @return array key => [ values... ]
     */
    private function collect_post_meta_data( int $origin_post_id ): array {
        $skip_keys = [
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_wp_page_template',
        ];

        $meta      = get_post_meta( $origin_post_id );
        $collected = [];

        foreach ( $meta as $key => $values ) {
            if ( in_array( $key, $skip_keys, true ) ) {
                continue;
            }

            $collected[ $key ] = array_map( 'maybe_unserialize', $values );
        }

        return $collected;
    }

    /**
     * Apply collected post meta to the target post AFTER switching blogs.
     *
     * Handles ACF fields (both field_* and _field_* keys) and all other custom meta.
     *
     * @param int   $target_post_id
     * @param array $meta_data From collect_post_meta_data().
     */
    private function apply_post_meta_data( int $target_post_id, array $meta_data ): void {
        foreach ( $meta_data as $key => $values ) {
            foreach ( $values as $value ) {
                update_post_meta( $target_post_id, $key, $value );
            }
        }
    }

    // ──────────────────────────────────────────────
    // AUTHOR PROFILE CLONING
    // ──────────────────────────────────────────────

    /**
     * Clone multi_author profiles from hub site to target site and remap
     * the post's author relationship IDs accordingly.
     *
     * The ACF relationship field (field_multi_author_relationship) stores
     * integer IDs that reference multi_author CPT posts. These only exist
     * on the hub (blog 1) and resolve as null on target blogs, causing
     * kh_get_post_authors() to return empty arrays on cloned posts.
     *
     * This method:
     * 1. Reads the origin post's author IDs
     * 2. For each author, checks if it already exists on the target site
     *    (matched by author_name meta)
     * 3. If not found, clones the multi_author profile to the target site
     * 4. Rewrites field_multi_author_relationship on the target post with
     *    the new local author IDs
     *
     * @param int $origin_post_id
     * @param int $target_post_id
     */
    private function clone_author_profiles( int $origin_post_id, int $target_post_id ): void {
        // Resolve origin author IDs from the hub post
        $origin_author_ids = [];
        if ( function_exists( 'get_field' ) ) {
            $raw = get_field( 'field_multi_author_relationship', $origin_post_id, false );
            if ( is_array( $raw ) ) {
                $origin_author_ids = array_map( 'intval', $raw );
            }
        }
        if ( empty( $origin_author_ids ) ) {
            $raw = get_post_meta( $origin_post_id, 'field_multi_author_relationship', true );
            if ( is_array( $raw ) ) {
                $origin_author_ids = array_map( 'intval', $raw );
            } elseif ( is_numeric( $raw ) ) {
                $origin_author_ids = [ (int) $raw ];
            }
        }

        if ( empty( $origin_author_ids ) ) {
            return; // No multi_author relationship on this post
        }

        $new_author_ids = [];

        foreach ( $origin_author_ids as $hub_author_id ) {
            // Read author data from the hub blog
            switch_to_blog( 1 );
            $author_name    = get_post_meta( $hub_author_id, 'author_name', true );
            $author_title   = get_post_meta( $hub_author_id, 'author_title', true );
            $author_company = get_post_meta( $hub_author_id, 'author_company', true );
            $author_bio     = get_post_meta( $hub_author_id, 'author_bio', true );
            $author_photo   = get_post_meta( $hub_author_id, 'author_photo', true );
            $author_slug    = get_post_field( 'post_name', $hub_author_id );
            $photo_src      = $author_photo ? wp_get_attachment_url( (int) $author_photo ) : '';
            restore_current_blog();

            if ( empty( $author_name ) ) {
                continue;
            }

            // Check if this author already exists on the target site
            $existing = get_posts( [
                'post_type'      => 'multi_author',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'   => 'author_name',
                        'value' => $author_name,
                    ],
                ],
            ] );

            if ( ! empty( $existing ) ) {
                $existing_id = (int) $existing[0];
                // Sync author photo to existing profile on target site
                if ( $photo_src && ! get_post_meta( $existing_id, 'author_photo', true ) ) {
                    $attachment_id = $this->copy_attachment_to_target( $photo_src );
                    if ( $attachment_id ) {
                        update_post_meta( $existing_id, 'author_photo', $attachment_id );
                    }
                }
                $new_author_ids[] = $existing_id;
                continue;
            }

            // Clone the author profile to the target site
            $new_author_id = wp_insert_post( [
                'post_type'   => 'multi_author',
                'post_title'  => $author_name,
                'post_name'   => $author_slug,
                'post_status' => 'publish',
                'meta_input'  => [
                    'author_name'    => $author_name,
                    'author_title'   => $author_title,
                    'author_company' => $author_company,
                    'author_bio'     => $author_bio,
                ],
            ] );

            if ( is_wp_error( $new_author_id ) || ! $new_author_id ) {
                continue;
            }

            $new_author_id = (int) $new_author_id;

            // Clone author photo attachment if present (photo_src already resolved above)
            if ( $photo_src ) {
                $attachment_id = $this->copy_attachment_to_target( $photo_src );
                if ( $attachment_id ) {
                    update_post_meta( $new_author_id, 'author_photo', $attachment_id );
                }
            }

            $new_author_ids[] = $new_author_id;
        }

        // Rewrite the relationship field on the target post
        if ( ! empty( $new_author_ids ) ) {
            update_post_meta( $target_post_id, 'field_multi_author_relationship', $new_author_ids );
            update_post_meta( $target_post_id, '_field_multi_author_relationship', 'field_multi_author_relationship' );

            // Also write to the 'authors' meta key used as fallback in kh_get_post_authors
            update_post_meta( $target_post_id, 'authors', $new_author_ids );
            update_post_meta( $target_post_id, '_authors', 'field_multi_author_relationship' );

            // Sync with ACF if available
            if ( function_exists( 'update_field' ) ) {
                update_field( 'field_multi_author_relationship', $new_author_ids, $target_post_id );
            }
        }
    }

    /**
     * Copy a media attachment from URL to the current blog.
     *
     * Tries download_url() first (HTTP), then falls back to a direct
     * filesystem copy for local environments where the site cannot
     * make HTTP requests to itself.
     *
     * @param string $attachment_url
     * @return int|false Attachment ID on success, false on failure.
     */
    private function copy_attachment_to_target( string $attachment_url ): int|false {
        // Try direct filesystem copy first — instant for local same-server URLs
        $tmp_file = $this->copy_attachment_via_filesystem( $attachment_url );

        // Fallback: HTTP download for external/remote URLs
        if ( ! $tmp_file || is_wp_error( $tmp_file ) ) {
            $tmp_file = download_url( $attachment_url );
        }

        if ( ! $tmp_file || is_wp_error( $tmp_file ) ) {
            return false;
        }

        $file_array = [
            'name'     => basename( parse_url( $attachment_url, PHP_URL_PATH ) ),
            'tmp_name' => $tmp_file,
        ];

        // Temporarily allow webp/avif mime types that WP core may reject
        $allow_webp = function ( $mimes ) {
            $mimes['webp'] = 'image/webp';
            return $mimes;
        };
        $allow_check = function ( $allowed, $file, $filename, $mimes ) {
            if ( ! $allowed && preg_match( '/\.webp$/i', $filename ) ) {
                return 'image/webp';
            }
            return $allowed;
        };

        add_filter( 'upload_mimes', $allow_webp, 999 );
        add_filter( 'wp_check_filetype_and_ext', $allow_check, 999, 4 );

        $attachment_id = media_handle_sideload( $file_array, 0 );

        remove_filter( 'upload_mimes', $allow_webp, 999 );
        remove_filter( 'wp_check_filetype_and_ext', $allow_check, 999 );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp_file );
            return false;
        }

        return (int) $attachment_id;
    }

    /**
     * Copy an attachment via direct filesystem access.
     *
     * Used as a fallback when download_url() fails (common in local dev
     * environments where the site cannot make HTTP requests to itself).
     *
     * @param string $attachment_url
     * @return string|false Path to temp file, or false on failure.
     */
    private function copy_attachment_via_filesystem( string $attachment_url ): string|false {
        // Determine the local filesystem path from the URL.
        // The upload URL structure is: {site_url}/wp-content/uploads/sites/{blog_id}/...
        $upload_dir = wp_upload_dir();
        $base_upload_url = $upload_dir['baseurl'];
        $base_upload_path = $upload_dir['basedir'];

        // Only attempt this if the URL points to our own site
        $site_url = get_site_url( 1 ); // hub site URL
        if ( ! str_starts_with( $attachment_url, $site_url ) ) {
            return false;
        }

        // Convert URL to filesystem path
        $relative_url = substr( $attachment_url, strlen( $site_url ) );
        $file_path = untrailingslashit( ABSPATH ) . $relative_url;

        // ABSPATH for multisite subdirectory install: fix double path segment
        if ( ! file_exists( $file_path ) ) {
            // Try without the /wp/ segment that ABSPATH adds
            $wp_content_pos = strpos( $relative_url, '/wp-content/' );
            if ( $wp_content_pos !== false ) {
                $alt_path = WP_CONTENT_DIR . substr( $relative_url, $wp_content_pos );
                $file_path = $alt_path;
            }
        }

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            return false;
        }

        // Copy to temp file
        $tmp_file = wp_tempnam( basename( $file_path ) );
        if ( ! $tmp_file ) {
            return false;
        }

        if ( ! copy( $file_path, $tmp_file ) ) {
            @unlink( $tmp_file );
            return false;
        }

        return $tmp_file;
    }
}