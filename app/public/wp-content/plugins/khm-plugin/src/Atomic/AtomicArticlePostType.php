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

        // Admin list table: parent post column + URL filtering
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
        add_action( 'pre_get_posts', array( $this, 'filter_by_parent' ) );
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
     * Add custom columns to the Atomic Articles admin list table.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function add_admin_columns( array $columns ): array {
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['parent_post'] = __( 'Parent Post', 'khm-membership' );
            }
        }
        return $new;
    }

    /**
     * Render custom column content for the Atomic Articles admin list table.
     *
     * @param string $column  Column key.
     * @param int    $post_id Current post ID.
     * @return void
     */
    public function render_admin_column( string $column, int $post_id ): void {
        if ( 'parent_post' !== $column ) {
            return;
        }

        $parent_id = (int) get_post_meta( $post_id, '_atomic_parent_id', true );
        if ( ! $parent_id ) {
            echo '<em>' . esc_html__( '—', 'khm-membership' ) . '</em>';
            return;
        }

        $parent = get_post( $parent_id );
        if ( ! $parent ) {
            echo '<em>' . esc_html__( 'Deleted', 'khm-membership' ) . '</em>';
            return;
        }

        $edit_url = get_edit_post_link( $parent_id );
        $title    = get_the_title( $parent );

        // Link to edit the parent post
        if ( $edit_url ) {
            printf(
                '<a href="%s">%s</a>',
                esc_url( $edit_url ),
                esc_html( $title ?: __( '(no title)', 'khm-membership' ) )
            );
        } else {
            echo esc_html( $title ?: __( '(no title)', 'khm-membership' ) );
        }

        // Quick filter link: show only atomics for this parent
        $filter_url = add_query_arg(
            array(
                'post_type'          => self::POST_TYPE,
                'atomic_parent_id'   => $parent_id,
            ),
            admin_url( 'edit.php' )
        );
        printf(
            ' <a href="%s" title="%s" style="font-size:0.85em;color:#2271b1;">↗</a>',
            esc_url( $filter_url ),
            esc_attr__( 'Filter by this parent', 'khm-membership' )
        );
    }

    /**
     * Filter the Atomic Articles admin list by parent post ID.
     *
     * Activates when `atomic_parent_id` is present in the URL.
     *
     * @param \WP_Query $query The current WP_Query.
     * @return void
     */
    public function filter_by_parent( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $post_type = $query->get( 'post_type' );
        if ( $post_type !== self::POST_TYPE ) {
            return;
        }

        $parent_id = isset( $_GET['atomic_parent_id'] ) ? absint( $_GET['atomic_parent_id'] ) : 0;
        if ( ! $parent_id ) {
            return;
        }

        $meta_query = $query->get( 'meta_query', array() );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = array();
        }

        $meta_query[] = array(
            'key'     => '_atomic_parent_id',
            'value'   => $parent_id,
            'type'    => 'NUMERIC',
            'compare' => '=',
        );

        $query->set( 'meta_query', $meta_query );
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