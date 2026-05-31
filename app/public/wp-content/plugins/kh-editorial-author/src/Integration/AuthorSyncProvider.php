<?php

namespace KH\EditorialAuthor\Integration;

/**
 * AuthorSyncProvider
 * 
 * Synchronizes AI drafting with the legacy multiple-authors plugin.
 * Ensures Human/AI hybrid attribution for all generated content.
 */
class AuthorSyncProvider {

    private const AI_AUTHOR_NAME = 'AI Assistant';
    private const AI_AUTHOR_SLUG = 'ai-assistant';
    private const AI_AUTHOR_TITLE = 'Editorial Intelligence';
    private const CPT_NAME = 'multi_author';
    private const ACF_FIELD_KEY = 'field_multi_author_relationship';

    /**
     * Sync co-authors for a specific post.
     *
     * @param int $post_id The ID of the drafted post.
     * @param int $user_id The ID of the human editor who triggered the job.
     * @return bool Success or failure.
     */
    public function sync_authors( int $post_id, int $user_id ): bool {
        if ( ! post_type_exists( self::CPT_NAME ) ) {
            error_log( "[AuthorSyncProvider] Multiple Authors plugin is not active." );
            return false;
        }

        $author_ids = [];

        // 1. Resolve Human Multi-Author Profile
        $human_author_id = $this->resolve_human_author_id( $user_id );
        if ( $human_author_id ) {
            $author_ids[] = (string) $human_author_id;
        }

        // 2. Resolve AI Multi-Author Profile
        $ai_author_id = $this->resolve_ai_author_id();
        if ( $ai_author_id ) {
            $author_ids[] = (string) $ai_author_id;
        }

        if ( empty( $author_ids ) ) {
            return false;
        }

        // 3. Persist to Meta (Standard + ACF compatibility)
        update_post_meta( $post_id, 'authors', $author_ids );
        update_post_meta( $post_id, self::ACF_FIELD_KEY, $author_ids );
        
        // ACF UI Persistence: Update the hidden reference key so it shows in the Admin dashboard
        update_post_meta( $post_id, '_authors', self::ACF_FIELD_KEY );

        return true;
    }

    /**
     * Find or create the AI Assistant multi_author profile.
     */
    public function resolve_ai_author_id(): int {
        $existing = get_posts( [
            'post_type'      => self::CPT_NAME,
            'name'           => self::AI_AUTHOR_SLUG, // Hardened search via slug
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post_status'    => 'publish'
        ] );

        if ( ! empty( $existing ) ) {
            return (int) $existing[0];
        }

        // Create the profile if it doesn't exist
        $post_id = wp_insert_post( [
            'post_type'    => self::CPT_NAME,
            'post_title'   => self::AI_AUTHOR_NAME,
            'post_name'    => self::AI_AUTHOR_SLUG,
            'post_content' => 'Automated editorial intelligence providing research and drafting support.',
            'post_excerpt' => 'AI-driven content generation and verification agent.',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id()
        ] );

        if ( ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, 'author_name', self::AI_AUTHOR_NAME );
            update_post_meta( $post_id, 'author_title', self::AI_AUTHOR_TITLE );
            update_post_meta( $post_id, 'author_bio', 'Automated editorial intelligence providing research and drafting support.' );
            error_log( "[AuthorSyncProvider] Created AI Assistant profile: ID {$post_id}" );
            return (int) $post_id;
        }

        return 0;
    }

    /**
     * Resolve a WordPress User ID to a Multi-Author CPT ID.
     */
    public function resolve_human_author_id( int $user_id ): int {
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) return 0;

        // Try searching by name in the meta field
        $existing = get_posts( [
            'post_type'      => self::CPT_NAME,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'author_name',
                    'value' => $user->display_name,
                ]
            ]
        ] );

        if ( ! empty( $existing ) ) {
            return (int) $existing[0];
        }

        // Fallback: Search by post title
        $existing_title = get_posts( [
            'post_type'      => self::CPT_NAME,
            'title'          => $user->display_name,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        return ! empty( $existing_title ) ? (int) $existing_title[0] : 0;
    }
}
