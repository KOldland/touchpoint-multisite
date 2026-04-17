<?php
/**
 * Atomic Article Custom Post Type
 *
 * Registers the 'atomic_article' CPT with the /atomic/ URL prefix.
 * Posts are generated automatically from parent WP posts via GPT decomposition.
 *
 * Meta keys stored on atomic_article posts:
 *   _atomic_parent_id      int    Parent post ID
 *   _atomic_schema_type    string One of: Article, FAQPage, HowTo, DefinedTerm
 *   _atomic_generated_at   string ISO 8601 timestamp of last GPT generation
 *
 * Meta keys stored on parent posts:
 *   _atomic_generate_enabled  bool   Whether to generate atomics on publish
 *   _atomic_article_ids        array  IDs of generated atomic_article posts
 *
 * @package KHM\Atomic
 */

namespace KHM\Atomic;

defined( 'ABSPATH' ) || exit;

/**
 * Atomic Article Post Type
 */
class AtomicArticlePostType {

    /**
     * CPT slug.
     */
    const POST_TYPE = 'atomic_article';

    /**
     * URL rewrite slug.
     */
    const REWRITE_SLUG = 'atomic';

    /**
     * Allowed schema types for the schema_type meta.
     */
    const SCHEMA_TYPES = array( 'Article', 'FAQPage', 'HowTo', 'DefinedTerm' );

    /**
     * Maximum atomic articles generated per parent post.
     */
    const MAX_PER_POST = 12;

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_filter( 'template_include', array( $this, 'template_include' ) );
    }

    /**
     * Register the 'atomic_article' CPT.
     *
     * @return void
     */
    public function register_post_type(): void {
        register_post_type(
            self::POST_TYPE,
            array(
                'label'               => __( 'Atomic Articles', 'khm-membership' ),
                'labels'              => array(
                    'name'               => __( 'Atomic Articles', 'khm-membership' ),
                    'singular_name'      => __( 'Atomic Article', 'khm-membership' ),
                    'add_new_item'       => __( 'Add New Atomic Article', 'khm-membership' ),
                    'edit_item'          => __( 'Edit Atomic Article', 'khm-membership' ),
                    'view_item'          => __( 'View Atomic Article', 'khm-membership' ),
                    'search_items'       => __( 'Search Atomic Articles', 'khm-membership' ),
                    'not_found'          => __( 'No atomic articles found.', 'khm-membership' ),
                    'not_found_in_trash' => __( 'No atomic articles found in Trash.', 'khm-membership' ),
                ),
                'public'              => true,
                'show_in_menu'        => 'edit.php',        // Shows under Posts
                'show_in_nav_menus'   => false,
                'show_in_rest'        => true,
                'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
                'rewrite'             => array(
                    'slug'       => self::REWRITE_SLUG,
                    'with_front' => false,
                ),
                'has_archive'         => false,
                'exclude_from_search' => false,             // Include in site search
                'map_meta_cap'        => true,
                'capability_type'     => 'post',
            )
        );
    }

    /**
     * Serve the plugin-side minimal template for atomic article singulars,
     * bypassing the active theme entirely.
     *
     * @param string $template Original template path.
     * @return string
     */
    public function template_include( $template ): string {
        if ( ! is_singular( self::POST_TYPE ) ) {
            return $template;
        }

        $plugin_template = dirname( __DIR__, 2 ) . '/templates/atomic-article.php';

        if ( file_exists( $plugin_template ) ) {
            return $plugin_template;
        }

        return $template;
    }

    /**
     * Get all atomic article IDs attached to a parent post.
     *
     * @param int $parent_id Parent post ID.
     * @return int[]
     */
    public static function get_ids_for_parent( int $parent_id ): array {
        $ids = get_post_meta( $parent_id, '_atomic_article_ids', true );
        return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
    }

    /**
     * Set the list of atomic IDs on a parent post.
     *
     * @param int   $parent_id Parent post ID.
     * @param int[] $ids       Atomic article post IDs.
     * @return void
     */
    public static function set_ids_for_parent( int $parent_id, array $ids ): void {
        update_post_meta( $parent_id, '_atomic_article_ids', array_map( 'intval', $ids ) );
    }

    /**
     * Check whether generation is enabled for a parent post.
     *
     * @param int $parent_id Parent post ID.
     * @return bool
     */
    public static function is_generation_enabled( int $parent_id ): bool {
        return (bool) get_post_meta( $parent_id, '_atomic_generate_enabled', true );
    }
}
