<?php

namespace KH\Editorial\Services\AI;

use KH\Editorial\Core\LLMService;
use KH\Editorial\Core\Container;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RecommendationAgent
 * 
 * Generates thematic profiles for content and retrieves semantic recommendations.
 * 
 * @package KH\Editorial\Services\AI
 */
class RecommendationAgent {

    const CONTENT_LIMIT = 1200;
    private const META_KEY = '_kh_thematic_profile';
    private const CACHE_TTL = 21600; 

    /**
     * Initialize all lifecycle hooks.
     */
    public function init(): void {
        add_action( 'init', [ $this, 'register_meta' ] );
        add_action( 'save_post', [ $this, 'on_post_save' ], 20, 2 );
        add_action( 'before_delete_post', [ $this, 'on_post_delete' ] );
        
        // Register worker handler
        add_filter( 'kh_editorial_execute_job_thematic_tagging', [ $this, 'handle_tagging_job' ], 10, 2 );
    }

    /**
     * Register meta keys with standard WordPress API.
     */
    public function register_meta(): void {
        $registered = get_registered_meta_keys( 'post' );
        $meta_keys = [ 
            self::META_KEY, 
            '_kh_thematic_content_hash', 
            '_kh_thematic_profile_failed_at', 
            '_kh_thematic_profile_generated_at' 
        ];
        
        foreach ( $meta_keys as $key ) {
            if ( ! isset( $registered[$key] ) ) {
                register_meta( 'post', $key, [ 
                    'single'       => false, 
                    'show_in_rest' => false, 
                    'auth_callback' => '__return_true' 
                ] );
            }
        }
    }

    /**
     * Hook into post save to queue async tag generation.
     */
    public function on_post_save( int $post_id, WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( 'post' !== $post->post_type || 'publish' !== $post->post_status ) return;
        if ( wp_is_post_revision( $post_id ) ) return;

        $current_hash = md5( $post->post_content );
        $old_hash = get_post_meta( $post_id, '_kh_thematic_content_hash', true );

        if ( $current_hash !== $old_hash ) {
            try {
                if ( ! Container::has( 'AIStorage' ) ) return;
                
                $storage = Container::get( 'AIStorage' );
                $job_id = $storage->insert_job([
                    'type'       => 'thematic_tagging',
                    'session_id' => 'tagging-' . $post_id,
                    'payload'    => wp_json_encode([ 'post_id' => $post_id, 'hash' => $current_hash ]),
                    'idempotency_key' => 'tag-' . $post_id . '-' . $current_hash
                ]);

                if ( $job_id && ! is_wp_error( $job_id ) ) {
                    // Trigger the worker to process the job
                    do_action( 'kh_editorial_job_created', $job_id, 'thematic_tagging' );
                }
            } catch ( \Throwable $e ) {
                error_log( "[RecommendationAgent] Save hook failed: " . $e->getMessage() );
            }
        }
    }

    /**
     * Worker handler for thematic tagging jobs.
     */
    public function handle_tagging_job( $dummy, $job ): array {
        $payload = json_decode( $job['payload'], true );
        $post_id = (int) ($payload['post_id'] ?? 0);
        $hash    = $payload['hash'] ?? '';

        if ( ! $post_id ) return [];

        return $this->generate_thematic_profile( $post_id, $hash );
    }

    /**
     * Cleanup on delete.
     */
    public function on_post_delete( int $post_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->postmeta, [ 'post_id' => $post_id, 'meta_key' => self::META_KEY ] );
        delete_post_meta( $post_id, '_kh_thematic_content_hash' );
        delete_post_meta( $post_id, '_kh_thematic_profile_failed_at' );
        delete_post_meta( $post_id, '_kh_thematic_profile_generated_at' );
        $this->invalidate_cache( $post_id );
    }

    /**
     * Generate thematic profile via LLM.
     */
    public function generate_thematic_profile( int $post_id, string $content_hash = '' ): array {
        $post = get_post( $post_id );
        if ( ! $post ) return [];

        $allowed_themes = [ 'FSI', 'Healthcare', 'Technology', 'Public Sector', 'Energy', 'Strategy', 'Technical', 'Strategic', 'Operational', 'General' ];
        $prompt = sprintf(
            "Analyze this article and extract 5-7 core thematic tags. ONLY use these themes: %s. Return JSON only: {\"tags\": [\"tag1\", \"tag2\"]}.\n\nTitle: %s\nContent: %s",
            implode(', ', $allowed_themes),
            $post->post_title,
            mb_substr( wp_strip_all_tags( $post->post_content ), 0, self::CONTENT_LIMIT )
        );

        $result = LLMService::post_completion( [
            [ 'role' => 'system', 'content' => $prompt ]
        ]);

        if ( is_wp_error( $result ) ) {
            update_post_meta( $post_id, '_kh_thematic_profile_failed_at', time() );
            return [];
        }

        $decoded = json_decode( preg_replace('/^```(?:json)?[\r\n]+|```[\r\n]*$/', '', trim($result['content'])), true );
        $tags = (array) ($decoded['tags'] ?? []);

        if ( ! empty( $tags ) && is_array( $tags ) ) {
            global $wpdb;
            $wpdb->delete( $wpdb->postmeta, [ 'post_id' => $post_id, 'meta_key' => self::META_KEY ] );
            foreach ( $tags as $tag ) {
                add_post_meta( $post_id, self::META_KEY, sanitize_text_field( $tag ) );
            }
            update_post_meta( $post_id, '_kh_thematic_content_hash', $content_hash ?: md5($post->post_content) );
            delete_post_meta( $post_id, '_kh_thematic_profile_failed_at' );
            $this->invalidate_cache( $post_id );
        } else {
            update_post_meta( $post_id, '_kh_thematic_profile_failed_at', time() );
        }

        return $tags;
    }

    /**
     * Get hybrid recommendations based on thematic profile and categories.
     */
    public function get_recommendations( int $post_id, int $limit = 3, bool $force = false ): array {
        $limit = min( $limit, 10 );
        $user_role = $this->get_current_user_role();
        $cache_key = 'kh_rec_' . $post_id . '_' . $user_role;
        
        if ( $force ) {
            $this->invalidate_cache( $post_id );
            // Also force a re-generation of the profile if forced
            $this->generate_thematic_profile( $post_id );
        }

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) return $cached;

        $raw_profile = (array) get_post_meta( $post_id, self::META_KEY, false );
        $profile = array_filter( array_map( 'sanitize_text_field', $raw_profile ) );
        
        $posts = [];
        
        if ( ! empty( $profile ) ) {
            $query = new \WP_Query( [
                'post_type'      => 'post',
                'posts_per_page' => $limit,
                'post__not_in'   => [ $post_id ],
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => self::META_KEY,
                        'value'   => array_values( $profile ),
                        'compare' => 'IN',
                    ]
                ],
                'orderby'        => 'date',
                'order'          => 'DESC'
            ] );
            $posts = $query->posts;
        }

        if ( count( $posts ) < $limit ) {
            $posts = array_merge( $posts, $this->get_category_fallback( $post_id, $limit - count( $posts ), wp_list_pluck( $posts, 'ID' ) ) );
        }

        shuffle( $posts );
        $results = $this->format_posts( $posts );
        set_transient( $cache_key, $results, self::CACHE_TTL );
        
        return $results;
    }

    private function get_category_fallback( int $post_id, int $limit, array $exclude_ids ): array {
        if ( ! is_object_in_taxonomy( get_post_type( $post_id ), 'category' ) ) return [];
        $excluded_cats = (array) get_option( 'kh_editorial_excluded_categories', [] );
        $cats = wp_get_post_categories( $post_id );
        if ( is_wp_error( $cats ) || empty( $cats ) ) return [];

        $query = new \WP_Query( [
            'post_type'        => 'post',
            'posts_per_page'   => $limit,
            'post__not_in'     => array_merge( [ $post_id ], $exclude_ids ),
            'post_status'      => 'publish',
            'category__in'     => $cats,
            'category__not_in' => $excluded_cats,
            'orderby'          => 'date',
            'order'            => 'DESC'
        ] );
        return $query->posts;
    }

    private function invalidate_cache( int $post_id ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( '_transient_kh_rec_' . $post_id . '_' ) . '%'
        ));
    }

    private function get_current_user_role(): string {
        if ( ! is_user_logged_in() ) return 'guest';
        $user = wp_get_current_user();
        return ! empty( $user->roles ) ? reset( $user->roles ) : 'subscriber';
    }

    private function format_posts( array $posts ): array {
        return array_map( function( WP_Post $post ) {
            return [
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'excerpt'   => wp_trim_words( $post->post_excerpt ?: $post->post_content, 15 ),
                'image_url' => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '',
                'url'       => get_permalink( $post->ID )
            ];
        }, $posts );
    }
}
