<?php
/**
 * Admin Manager for handling admin interface and meta boxes.
 *
 * @package KHM_SEO
 * @version 1.0.0
 */

namespace KHM_SEO\Admin;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin manager class for meta boxes and admin interface.
 */
class AdminManager {

    /**
     * Supported post types for SEO meta boxes.
     *
     * @var array
     */
    private $supported_post_types = array();

    /**
     * Initialize the admin manager.
     */
    public function __construct() {
        $this->supported_post_types = array( 'post', 'page' );
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        // Admin menu and settings
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'add_boost_visibility_pages' ), 20 );
        add_action( 'admin_post_khm_geo_save_post', array( $this, 'handle_geo_save_post' ) );
        
        // Meta boxes for posts and pages
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_boost_visibility_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_post_meta' ) );
        
        // Term meta for categories and tags
        add_action( 'init', array( $this, 'init_term_meta' ) );
        
        // Admin assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // Ajax handlers
        add_action( 'wp_ajax_khm_seo_analyze_content', array( $this, 'ajax_analyze_content' ) );

        // Post list actions
        add_filter( 'post_row_actions', array( $this, 'add_boost_visibility_row_actions' ), 10, 2 );
        add_filter( 'page_row_actions', array( $this, 'add_boost_visibility_row_actions' ), 10, 2 );
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'KHM SEO', 'khm-seo' ),
            __( 'KHM SEO', 'khm-seo' ),
            'manage_options',
            'khm-seo',
            array( $this, 'admin_page' ),
            'dashicons-search',
            80
        );

        add_submenu_page(
            'khm-seo',
            __( 'General Settings', 'khm-seo' ),
            __( 'General', 'khm-seo' ),
            'manage_options',
            'khm-seo',
            array( $this, 'admin_page' )
        );

        add_submenu_page(
            'khm-seo',
            __( 'Titles & Meta', 'khm-seo' ),
            __( 'Titles & Meta', 'khm-seo' ),
            'manage_options',
            'khm-seo-titles',
            array( $this, 'titles_page' )
        );

        add_submenu_page(
            'khm-seo',
            __( 'XML Sitemaps', 'khm-seo' ),
            __( 'Sitemaps', 'khm-seo' ),
            'manage_options',
            'khm-seo-sitemaps',
            array( $this, 'sitemaps_page' )
        );

        add_submenu_page(
            'khm-seo',
            __( 'Schema Markup', 'khm-seo' ),
            __( 'Schema', 'khm-seo' ),
            'manage_options',
            'khm-seo-schema',
            array( $this, 'schema_page' )
        );

        add_submenu_page(
            'khm-seo',
            __( 'SEO Tools', 'khm-seo' ),
            __( 'Tools', 'khm-seo' ),
            'manage_options',
            'khm-seo-tools',
            array( $this, 'tools_page' )
        );

        add_submenu_page(
            'khm-seo',
            __( 'Performance Monitor', 'khm-seo' ),
            __( 'Performance', 'khm-seo' ),
            'manage_options',
            'khm-seo-performance',
            array( $this, 'performance_page' )
        );
    }

    /**
     * Add Boost Visibility pages.
     */
    public function add_boost_visibility_pages() {
        add_submenu_page(
            'khm-seo',
            __( 'Boost Visibility', 'khm-seo' ),
            __( 'Boost Visibility', 'khm-seo' ),
            'edit_posts',
            'khm-seo-boost-visibility',
            array( $this, 'render_boost_visibility_page' )
        );

        add_submenu_page(
            'khm-seo',
            __( 'GEO Manager', 'khm-seo' ),
            __( 'GEO Manager', 'khm-seo' ),
            'edit_posts',
            'khm-seo-geo-post',
            array( $this, 'render_geo_post_page' )
        );

        add_submenu_page(
            'khm-seo',
            __( 'Post Health', 'khm-seo' ),
            __( 'Post Health', 'khm-seo' ),
            'edit_posts',
            'khm-seo-post-health',
            array( $this, 'render_post_health_page' )
        );
    }

    /**
     * Render the Boost Visibility hub.
     */
    public function render_boost_visibility_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        $default_post_type = array_key_first( $post_types );
        $selected_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : $default_post_type;
        if ( empty( $selected_type ) || ! isset( $post_types[ $selected_type ] ) ) {
            $selected_type = $default_post_type;
        }

        $post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
        $selected_post = $post_id ? get_post( $post_id ) : null;
        if ( $selected_post && 'publish' !== $selected_post->post_status ) {
            $selected_post = null;
        }

        $posts = get_posts( array(
            'post_type' => $selected_type,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        $social_url = $selected_post ? admin_url( 'admin.php?page=khm-seo-social-preview&post_type=' . $selected_type . '&post_id=' . $selected_post->ID ) : '';
        $geo_url = $selected_post ? admin_url( 'admin.php?page=khm-seo-geo-post&post_type=' . $selected_type . '&post_id=' . $selected_post->ID ) : '';
        $health_url = $selected_post ? admin_url( 'admin.php?page=khm-seo-post-health&post_type=' . $selected_type . '&post_id=' . $selected_post->ID ) : '';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Boost Visibility', 'khm-seo' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Publish first, then manage GEO, social previews, and post health from here.', 'khm-seo' ); ?>
            </p>

            <form method="get">
                <input type="hidden" name="page" value="khm-seo-boost-visibility" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="khm-boost-post-type"><?php esc_html_e( 'Content Type', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <select id="khm-boost-post-type" name="post_type">
                                    <?php foreach ( $post_types as $type_slug => $type_obj ) : ?>
                                        <option value="<?php echo esc_attr( $type_slug ); ?>" <?php selected( $selected_type, $type_slug ); ?>>
                                            <?php echo esc_html( $type_obj->labels->singular_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="khm-boost-post-id"><?php esc_html_e( 'Published Item', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <select id="khm-boost-post-id" name="post_id">
                                    <option value="0"><?php esc_html_e( 'Select a published item...', 'khm-seo' ); ?></option>
                                    <?php foreach ( $posts as $post_item ) : ?>
                                        <option value="<?php echo esc_attr( $post_item->ID ); ?>" <?php selected( $selected_post && $selected_post->ID === $post_item->ID ); ?>>
                                            <?php echo esc_html( $post_item->post_title ?: sprintf( __( '(Untitled #%d)', 'khm-seo' ), $post_item->ID ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Load Actions', 'khm-seo' ), 'primary', false ); ?>
            </form>

            <?php if ( $selected_post ) : ?>
                <hr />
                <h2><?php echo esc_html( get_the_title( $selected_post ) ); ?></h2>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url( $social_url ); ?>"><?php esc_html_e( 'Open Social Media Manager', 'khm-seo' ); ?></a>
                    <a class="button button-secondary" href="<?php echo esc_url( $geo_url ); ?>"><?php esc_html_e( 'Open GEO Manager', 'khm-seo' ); ?></a>
                    <a class="button button-secondary" href="<?php echo esc_url( $health_url ); ?>"><?php esc_html_e( 'Open Post Health', 'khm-seo' ); ?></a>
                </p>
            <?php else : ?>
                <div class="notice notice-info" style="margin-top:16px;">
                    <p><?php esc_html_e( 'Choose a published item to unlock GEO and Social workflows.', 'khm-seo' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render GEO manager page for a post.
     */
    public function render_geo_post_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        $default_post_type = array_key_first( $post_types );
        $selected_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : $default_post_type;
        if ( empty( $selected_type ) || ! isset( $post_types[ $selected_type ] ) ) {
            $selected_type = $default_post_type;
        }

        $post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
        $selected_post = $post_id ? get_post( $post_id ) : null;
        if ( $selected_post && 'publish' !== $selected_post->post_status ) {
            $selected_post = null;
        }

        $posts = get_posts( array(
            'post_type' => $selected_type,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'GEO Manager', 'khm-seo' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Manage GEO series placement for published content.', 'khm-seo' ); ?>
            </p>

            <form method="get">
                <input type="hidden" name="page" value="khm-seo-geo-post" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="khm-geo-post-type"><?php esc_html_e( 'Content Type', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <select id="khm-geo-post-type" name="post_type">
                                    <?php foreach ( $post_types as $type_slug => $type_obj ) : ?>
                                        <option value="<?php echo esc_attr( $type_slug ); ?>" <?php selected( $selected_type, $type_slug ); ?>>
                                            <?php echo esc_html( $type_obj->labels->singular_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="khm-geo-post-id"><?php esc_html_e( 'Published Item', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <select id="khm-geo-post-id" name="post_id">
                                    <option value="0"><?php esc_html_e( 'Select a published item...', 'khm-seo' ); ?></option>
                                    <?php foreach ( $posts as $post_item ) : ?>
                                        <option value="<?php echo esc_attr( $post_item->ID ); ?>" <?php selected( $selected_post && $selected_post->ID === $post_item->ID ); ?>>
                                            <?php echo esc_html( $post_item->post_title ?: sprintf( __( '(Untitled #%d)', 'khm-seo' ), $post_item->ID ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Load GEO Settings', 'khm-seo' ), 'primary', false ); ?>
            </form>

            <?php if ( $selected_post ) : ?>
                <hr />
                <h2><?php echo esc_html( get_the_title( $selected_post ) ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="khm_geo_save_post" />
                    <input type="hidden" name="post_id" value="<?php echo esc_attr( $selected_post->ID ); ?>" />
                    <?php
                    $series_manager = function_exists( 'khm_seo' ) && khm_seo()->get_geo_manager()
                        ? khm_seo()->get_geo_manager()->get_series_manager()
                        : null;
                    if ( $series_manager ) {
                        $post = $selected_post;
                        setup_postdata( $post );
                        $series_manager->render_series_meta_box( $post );
                        wp_reset_postdata();
                    } else {
                        echo '<p>' . esc_html__( 'GEO manager is not available.', 'khm-seo' ) . '</p>';
                    }
                    ?>
                    <?php submit_button( __( 'Save GEO Settings', 'khm-seo' ) ); ?>
                </form>
            <?php else : ?>
                <div class="notice notice-info" style="margin-top:16px;">
                    <p><?php esc_html_e( 'Choose a published item to manage GEO settings.', 'khm-seo' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle GEO save for post settings.
     */
    public function handle_geo_save_post() {
        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'khm-seo' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_die( esc_html__( 'Invalid post.', 'khm-seo' ) );
        }

        if ( function_exists( 'khm_seo' ) && khm_seo()->get_geo_manager() ) {
            $series_manager = khm_seo()->get_geo_manager()->get_series_manager();
            if ( $series_manager ) {
                $series_manager->on_post_save( $post_id, $post );
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=khm-seo-geo-post&post_type=' . $post->post_type . '&post_id=' . $post_id . '&updated=1' ) );
        exit;
    }

    /**
     * Render Post Health page.
     */
    public function render_post_health_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        $default_post_type = array_key_first( $post_types );
        $selected_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : $default_post_type;
        if ( empty( $selected_type ) || ! isset( $post_types[ $selected_type ] ) ) {
            $selected_type = $default_post_type;
        }

        $post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
        $selected_post = $post_id ? get_post( $post_id ) : null;
        if ( $selected_post && 'publish' !== $selected_post->post_status ) {
            $selected_post = null;
        }

        $posts = get_posts( array(
            'post_type' => $selected_type,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Post Health', 'khm-seo' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Quick health checks for published content.', 'khm-seo' ); ?>
            </p>

            <form method="get">
                <input type="hidden" name="page" value="khm-seo-post-health" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="khm-health-post-type"><?php esc_html_e( 'Content Type', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <select id="khm-health-post-type" name="post_type">
                                    <?php foreach ( $post_types as $type_slug => $type_obj ) : ?>
                                        <option value="<?php echo esc_attr( $type_slug ); ?>" <?php selected( $selected_type, $type_slug ); ?>>
                                            <?php echo esc_html( $type_obj->labels->singular_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="khm-health-post-id"><?php esc_html_e( 'Published Item', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <select id="khm-health-post-id" name="post_id">
                                    <option value="0"><?php esc_html_e( 'Select a published item...', 'khm-seo' ); ?></option>
                                    <?php foreach ( $posts as $post_item ) : ?>
                                        <option value="<?php echo esc_attr( $post_item->ID ); ?>" <?php selected( $selected_post && $selected_post->ID === $post_item->ID ); ?>>
                                            <?php echo esc_html( $post_item->post_title ?: sprintf( __( '(Untitled #%d)', 'khm-seo' ), $post_item->ID ) ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Load Health', 'khm-seo' ), 'primary', false ); ?>
            </form>

            <?php if ( $selected_post ) : ?>
                <?php
                $word_count = str_word_count( wp_strip_all_tags( $selected_post->post_content ) );
                $has_featured = has_post_thumbnail( $selected_post->ID );
                $seo_title = get_post_meta( $selected_post->ID, '_khm_seo_title', true );
                $seo_description = get_post_meta( $selected_post->ID, '_khm_seo_description', true );
                ?>
                <hr />
                <h2><?php echo esc_html( get_the_title( $selected_post ) ); ?></h2>
                <table class="widefat striped" style="max-width:720px;">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'Word Count', 'khm-seo' ); ?></th>
                            <td><?php echo esc_html( $word_count ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Featured Image', 'khm-seo' ); ?></th>
                            <td><?php echo esc_html( $has_featured ? __( 'Yes', 'khm-seo' ) : __( 'No', 'khm-seo' ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'SEO Title', 'khm-seo' ); ?></th>
                            <td><?php echo esc_html( $seo_title ? $seo_title : __( 'Not set', 'khm-seo' ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'SEO Description', 'khm-seo' ); ?></th>
                            <td><?php echo esc_html( $seo_description ? $seo_description : __( 'Not set', 'khm-seo' ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Last Updated', 'khm-seo' ); ?></th>
                            <td><?php echo esc_html( get_the_modified_date( '', $selected_post ) ); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="notice notice-info" style="margin-top:16px;">
                    <p><?php esc_html_e( 'Choose a published item to view post health.', 'khm-seo' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add Boost Visibility meta box on editor screen.
     */
    public function add_boost_visibility_meta_box() {
        $post_types = get_post_types( array( 'public' => true ) );
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'khm-seo-boost-visibility',
                __( 'Boost Visibility', 'khm-seo' ),
                array( $this, 'render_boost_visibility_meta_box' ),
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * Render Boost Visibility meta box.
     *
     * @param WP_Post $post
     */
    public function render_boost_visibility_meta_box( $post ) {
        if ( ! $post || 'publish' !== $post->post_status ) {
            echo '<p>' . esc_html__( 'Publish this content to unlock GEO and Social actions.', 'khm-seo' ) . '</p>';
            return;
        }

        $social_url = admin_url( 'admin.php?page=khm-seo-social-preview&post_type=' . $post->post_type . '&post_id=' . $post->ID );
        $geo_url = admin_url( 'admin.php?page=khm-seo-geo-post&post_type=' . $post->post_type . '&post_id=' . $post->ID );
        $health_url = admin_url( 'admin.php?page=khm-seo-post-health&post_type=' . $post->post_type . '&post_id=' . $post->ID );
        $hub_url = admin_url( 'admin.php?page=khm-seo-boost-visibility&post_type=' . $post->post_type . '&post_id=' . $post->ID );

        echo '<p><a class="button button-primary" href="' . esc_url( $hub_url ) . '">' . esc_html__( 'Boost Visibility', 'khm-seo' ) . '</a></p>';
        echo '<p><a class="button button-secondary" href="' . esc_url( $social_url ) . '">' . esc_html__( 'Social Media Manager', 'khm-seo' ) . '</a></p>';
        echo '<p><button class="button button-secondary khm-geo-suggestions-btn" type="button">' . esc_html__( 'GEO AnswerCards', 'khm-seo' ) . '</button></p>';
        echo '<p><a class="button button-secondary" href="' . esc_url( $health_url ) . '">' . esc_html__( 'Post Health', 'khm-seo' ) . '</a></p>';
    }

    /**
     * Add row actions for Boost Visibility workflows.
     *
     * @param array   $actions
     * @param WP_Post $post
     * @return array
     */
    public function add_boost_visibility_row_actions( $actions, $post ) {
        if ( ! $post || 'publish' !== $post->post_status ) {
            return $actions;
        }

        $actions['boost_visibility'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-boost-visibility&post_type=' . $post->post_type . '&post_id=' . $post->ID ) ) . '">' . esc_html__( 'Boost Visibility', 'khm-seo' ) . '</a>';
        $actions['boost_social'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-social-preview&post_type=' . $post->post_type . '&post_id=' . $post->ID ) ) . '">' . esc_html__( 'Social', 'khm-seo' ) . '</a>';
        $actions['boost_geo'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-geo-post&post_type=' . $post->post_type . '&post_id=' . $post->ID ) ) . '">' . esc_html__( 'GEO', 'khm-seo' ) . '</a>';
        $actions['boost_health'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-post-health&post_type=' . $post->post_type . '&post_id=' . $post->ID ) ) . '">' . esc_html__( 'Post Health', 'khm-seo' ) . '</a>';

        return $actions;
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'khm_seo_general', 'khm_seo_general' );
        register_setting( 'khm_seo_titles', 'khm_seo_titles' );
        register_setting( 'khm_seo_meta', 'khm_seo_meta' );
        register_setting( 'khm_seo_sitemap', 'khm_seo_sitemap' );
        register_setting( 'khm_seo_schema', 'khm_seo_schema' );
        register_setting( 'khm_seo_tools', 'khm_seo_tools' );
        register_setting( 'khm_seo_performance', 'khm_seo_performance' );
    }

    /**
     * Add meta boxes to post edit screens.
     */
    public function add_meta_boxes() {
        $post_types = get_post_types( array( 'public' => true ) );
        
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'khm-seo-meta',
                __( 'KHM SEO', 'khm-seo' ),
                array( $this, 'meta_box_callback' ),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Meta box callback.
     *
     * @param object $post Post object.
     */
    public function meta_box_callback( $post ) {
        wp_nonce_field( 'khm_seo_meta_box', 'khm_seo_meta_box_nonce' );
        
        // Get current values
        $title = get_post_meta( $post->ID, '_khm_seo_title', true );
        $description = get_post_meta( $post->ID, '_khm_seo_description', true );
        $keywords = get_post_meta( $post->ID, '_khm_seo_keywords', true );
        $robots = get_post_meta( $post->ID, '_khm_seo_robots', true );
        $canonical = get_post_meta( $post->ID, '_khm_seo_canonical', true );
        $focus_keyword = get_post_meta( $post->ID, '_khm_seo_focus_keyword', true );

        echo '<div id="khm-seo-meta-box">';
        
        // SEO Title
        echo '<div class="khm-seo-field">';
        echo '<label for="khm_seo_title"><strong>' . __( 'SEO Title', 'khm-seo' ) . '</strong></label>';
        echo '<input type="text" id="khm_seo_title" name="khm_seo_title" value="' . esc_attr( $title ) . '" class="widefat" />';
        echo '<p class="description">' . __( 'Recommended length: 50-60 characters', 'khm-seo' ) . '</p>';
        echo '</div>';

        // Meta Description
        echo '<div class="khm-seo-field">';
        echo '<label for="khm_seo_description"><strong>' . __( 'Meta Description', 'khm-seo' ) . '</strong></label>';
        echo '<textarea id="khm_seo_description" name="khm_seo_description" rows="3" class="widefat">' . esc_textarea( $description ) . '</textarea>';
        echo '<p class="description">' . __( 'Recommended length: 150-160 characters', 'khm-seo' ) . '</p>';
        echo '</div>';

        // Focus Keyword
        echo '<div class="khm-seo-field">';
        echo '<label for="khm_seo_focus_keyword"><strong>' . __( 'Focus Keyword', 'khm-seo' ) . '</strong></label>';
        echo '<input type="text" id="khm_seo_focus_keyword" name="khm_seo_focus_keyword" value="' . esc_attr( $focus_keyword ) . '" class="widefat" />';
        echo '<p class="description">' . __( 'The main keyword you want to rank for with this content', 'khm-seo' ) . '</p>';
        echo '</div>';

        // Keywords
        echo '<div class="khm-seo-field">';
        echo '<label for="khm_seo_keywords"><strong>' . __( 'Keywords', 'khm-seo' ) . '</strong></label>';
        echo '<input type="text" id="khm_seo_keywords" name="khm_seo_keywords" value="' . esc_attr( $keywords ) . '" class="widefat" />';
        echo '<p class="description">' . __( 'Comma-separated list of keywords', 'khm-seo' ) . '</p>';
        echo '</div>';

        // Robots
        echo '<div class="khm-seo-field">';
        echo '<label for="khm_seo_robots"><strong>' . __( 'Robots Meta', 'khm-seo' ) . '</strong></label>';
        echo '<select id="khm_seo_robots" name="khm_seo_robots" class="widefat">';
        echo '<option value=""' . selected( $robots, '', false ) . '>' . __( 'Default', 'khm-seo' ) . '</option>';
        echo '<option value="noindex"' . selected( $robots, 'noindex', false ) . '>' . __( 'No Index', 'khm-seo' ) . '</option>';
        echo '<option value="nofollow"' . selected( $robots, 'nofollow', false ) . '>' . __( 'No Follow', 'khm-seo' ) . '</option>';
        echo '<option value="noindex,nofollow"' . selected( $robots, 'noindex,nofollow', false ) . '>' . __( 'No Index, No Follow', 'khm-seo' ) . '</option>';
        echo '</select>';
        echo '</div>';

        // Canonical URL
        echo '<div class="khm-seo-field">';
        echo '<label for="khm_seo_canonical"><strong>' . __( 'Canonical URL', 'khm-seo' ) . '</strong></label>';
        echo '<input type="url" id="khm_seo_canonical" name="khm_seo_canonical" value="' . esc_attr( $canonical ) . '" class="widefat" />';
        echo '<p class="description">' . __( 'Leave blank to use default permalink', 'khm-seo' ) . '</p>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Save post meta.
     *
     * @param int $post_id Post ID.
     */
    public function save_post_meta( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['khm_seo_meta_box_nonce'] ) || 
             ! wp_verify_nonce( $_POST['khm_seo_meta_box_nonce'], 'khm_seo_meta_box' ) ) {
            return;
        }

        // Check if user has permission
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save meta fields
        $fields = array( 'title', 'description', 'keywords', 'robots', 'canonical', 'focus_keyword' );
        
        foreach ( $fields as $field ) {
            $meta_key = '_khm_seo_' . $field;
            $value = isset( $_POST[ 'khm_seo_' . $field ] ) ? sanitize_text_field( $_POST[ 'khm_seo_' . $field ] ) : '';
            
            if ( 'description' === $field ) {
                $value = sanitize_textarea_field( $_POST[ 'khm_seo_' . $field ] );
            }
            
            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook_suffix Admin page hook suffix.
     */
    public function enqueue_admin_scripts( $hook_suffix ) {
        // Only load on KHM SEO pages and post edit screens
        if ( strpos( $hook_suffix, 'khm-seo' ) !== false || 
             in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) ) {
            
            wp_enqueue_style( 
                'khm-seo-admin', 
                KHM_SEO_PLUGIN_URL . 'assets/css/admin.css', 
                array(), 
                KHM_SEO_VERSION 
            );
            
            wp_enqueue_script( 
                'khm-seo-admin', 
                KHM_SEO_PLUGIN_URL . 'assets/js/admin.js', 
                array( 'jquery' ), 
                KHM_SEO_VERSION, 
                true 
            );
            
            // Localize script
            wp_localize_script( 'khm-seo-admin', 'khmSeo', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'khm_seo_ajax' ),
                'strings'  => array(
                    'analyzing' => __( 'Analyzing...', 'khm-seo' ),
                    'good'      => __( 'Good', 'khm-seo' ),
                    'needs_improvement' => __( 'Needs Improvement', 'khm-seo' ),
                    'poor'      => __( 'Poor', 'khm-seo' )
                )
            ) );
        }
    }

    /**
     * Main admin page.
     */
    public function admin_page() {
        include KHM_SEO_PLUGIN_DIR . 'templates/admin/general.php';
    }

    /**
     * Titles & Meta admin page.
     */
    public function titles_page() {
        include KHM_SEO_PLUGIN_DIR . 'templates/admin/titles.php';
    }

    /**
     * Sitemaps admin page.
     */
    public function sitemaps_page() {
        include KHM_SEO_PLUGIN_DIR . 'templates/admin/sitemaps.php';
    }

    /**
     * Schema admin page.
     */
    public function schema_page() {
        include KHM_SEO_PLUGIN_DIR . 'templates/admin/schema.php';
    }

    /**
     * Tools admin page.
     */
    public function tools_page() {
        include KHM_SEO_PLUGIN_DIR . 'templates/admin/tools.php';
    }

    /**
     * Initialize term meta functionality.
     */
    public function init_term_meta() {
        $taxonomies = \get_taxonomies( array( 'public' => true ) );
        
        foreach ( $taxonomies as $taxonomy ) {
            // Add meta fields to add term form
            \add_action( "{$taxonomy}_add_form_fields", array( $this, 'add_term_meta_fields' ), 10, 2 );
            
            // Add meta fields to edit term form
            \add_action( "{$taxonomy}_edit_form_fields", array( $this, 'edit_term_meta_fields' ), 10, 2 );
            
            // Save term meta
            \add_action( "edited_{$taxonomy}", array( $this, 'save_term_meta' ), 10, 2 );
            \add_action( "created_{$taxonomy}", array( $this, 'save_term_meta' ), 10, 2 );
        }
    }

    /**
     * Add term meta fields to new term form.
     *
     * @param string $taxonomy The taxonomy slug.
     */
    public function add_term_meta_fields( $taxonomy ) {
        \wp_nonce_field( 'khm_seo_term_meta', 'khm_seo_term_meta_nonce' );
        ?>
        <div class="form-field khm-seo-term-meta">
            <h3><?php esc_html_e( 'SEO Settings', 'khm-seo' ); ?></h3>
            
            <div class="form-field">
                <label for="khm_seo_title"><?php esc_html_e( 'SEO Title', 'khm-seo' ); ?></label>
                <input type="text" name="khm_seo_title" id="khm_seo_title" value="" class="widefat" />
                <p><?php esc_html_e( 'Custom SEO title for this term. Leave blank to use default.', 'khm-seo' ); ?></p>
            </div>
            
            <div class="form-field">
                <label for="khm_seo_description"><?php esc_html_e( 'Meta Description', 'khm-seo' ); ?></label>
                <textarea name="khm_seo_description" id="khm_seo_description" rows="3" class="widefat"></textarea>
                <p><?php esc_html_e( 'Custom meta description for this term. Recommended length: 150-160 characters.', 'khm-seo' ); ?></p>
            </div>
            
            <div class="form-field">
                <label for="khm_seo_keywords"><?php esc_html_e( 'Keywords', 'khm-seo' ); ?></label>
                <input type="text" name="khm_seo_keywords" id="khm_seo_keywords" value="" class="widefat" />
                <p><?php esc_html_e( 'Comma-separated list of keywords for this term.', 'khm-seo' ); ?></p>
            </div>
            
            <div class="form-field">
                <label for="khm_seo_robots"><?php esc_html_e( 'Robots Meta', 'khm-seo' ); ?></label>
                <select name="khm_seo_robots" id="khm_seo_robots" class="widefat">
                    <option value=""><?php esc_html_e( 'Default', 'khm-seo' ); ?></option>
                    <option value="noindex"><?php esc_html_e( 'No Index', 'khm-seo' ); ?></option>
                    <option value="nofollow"><?php esc_html_e( 'No Follow', 'khm-seo' ); ?></option>
                    <option value="noindex,nofollow"><?php esc_html_e( 'No Index, No Follow', 'khm-seo' ); ?></option>
                </select>
            </div>
        </div>
        <?php
    }

    /**
     * Add term meta fields to edit term form.
     *
     * @param object $term     Current term object.
     * @param string $taxonomy Current taxonomy slug.
     */
    public function edit_term_meta_fields( $term, $taxonomy ) {
        $title = \get_term_meta( $term->term_id, 'khm_seo_title', true );
        $description = \get_term_meta( $term->term_id, 'khm_seo_description', true );
        $keywords = \get_term_meta( $term->term_id, 'khm_seo_keywords', true );
        $robots = \get_term_meta( $term->term_id, 'khm_seo_robots', true );
        
        \wp_nonce_field( 'khm_seo_term_meta', 'khm_seo_term_meta_nonce' );
        ?>
        <tr class="form-field khm-seo-term-meta">
            <th scope="row" colspan="2">
                <h3><?php esc_html_e( 'SEO Settings', 'khm-seo' ); ?></h3>
            </th>
        </tr>
        
        <tr class="form-field">
            <th scope="row"><label for="khm_seo_title"><?php esc_html_e( 'SEO Title', 'khm-seo' ); ?></label></th>
            <td>
                <input type="text" name="khm_seo_title" id="khm_seo_title" value="<?php echo \esc_attr( $title ); ?>" class="widefat" />
                <p class="description"><?php esc_html_e( 'Custom SEO title for this term. Leave blank to use default.', 'khm-seo' ); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row"><label for="khm_seo_description"><?php esc_html_e( 'Meta Description', 'khm-seo' ); ?></label></th>
            <td>
                <textarea name="khm_seo_description" id="khm_seo_description" rows="3" class="widefat"><?php echo \esc_textarea( $description ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Custom meta description for this term. Recommended length: 150-160 characters.', 'khm-seo' ); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row"><label for="khm_seo_keywords"><?php esc_html_e( 'Keywords', 'khm-seo' ); ?></label></th>
            <td>
                <input type="text" name="khm_seo_keywords" id="khm_seo_keywords" value="<?php echo \esc_attr( $keywords ); ?>" class="widefat" />
                <p class="description"><?php esc_html_e( 'Comma-separated list of keywords for this term.', 'khm-seo' ); ?></p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row"><label for="khm_seo_robots"><?php esc_html_e( 'Robots Meta', 'khm-seo' ); ?></label></th>
            <td>
                <select name="khm_seo_robots" id="khm_seo_robots" class="widefat">
                    <option value=""><?php esc_html_e( 'Default', 'khm-seo' ); ?></option>
                    <option value="noindex" <?php \selected( $robots, 'noindex' ); ?>><?php esc_html_e( 'No Index', 'khm-seo' ); ?></option>
                    <option value="nofollow" <?php \selected( $robots, 'nofollow' ); ?>><?php esc_html_e( 'No Follow', 'khm-seo' ); ?></option>
                    <option value="noindex,nofollow" <?php \selected( $robots, 'noindex,nofollow' ); ?>><?php esc_html_e( 'No Index, No Follow', 'khm-seo' ); ?></option>
                </select>
            </td>
        </tr>
        <?php
    }

    /**
     * Save term meta.
     *
     * @param int $term_id Term ID.
     */
    public function save_term_meta( $term_id ) {
        // Verify nonce
        if ( ! isset( $_POST['khm_seo_term_meta_nonce'] ) || 
             ! \wp_verify_nonce( $_POST['khm_seo_term_meta_nonce'], 'khm_seo_term_meta' ) ) {
            return;
        }

        // Check if user has permission
        if ( ! \current_user_can( 'manage_categories' ) ) {
            return;
        }

        // Save meta fields
        $fields = array( 'title', 'description', 'keywords', 'robots' );
        
        foreach ( $fields as $field ) {
            $meta_key = 'khm_seo_' . $field;
            $value = isset( $_POST[ $meta_key ] ) ? \sanitize_text_field( $_POST[ $meta_key ] ) : '';
            
            if ( 'description' === $field ) {
                $value = \sanitize_textarea_field( $_POST[ $meta_key ] );
            }
            
            \update_term_meta( $term_id, $meta_key, $value );
        }
    }

    /**
     * AJAX handler for content analysis.
     */
    public function ajax_analyze_content() {
        // Verify nonce
        if ( ! \wp_verify_nonce( $_POST['nonce'], 'khm_seo_ajax' ) ) {
            \wp_die( 'Security check failed' );
        }

        $content = \sanitize_textarea_field( $_POST['content'] );
        $focus_keyword = \sanitize_text_field( $_POST['focus_keyword'] );
        
        $analysis = $this->analyze_content( $content, $focus_keyword );
        
        \wp_send_json_success( $analysis );
    }

    /**
     * Analyze content for SEO.
     *
     * @param string $content        The content to analyze.
     * @param string $focus_keyword  The focus keyword.
     * @return array Analysis results.
     */
    private function analyze_content( $content, $focus_keyword = '' ) {
        $analysis = array(
            'word_count' => 0,
            'keyword_density' => 0,
            'readability_score' => 0,
            'suggestions' => array()
        );

        if ( empty( $content ) ) {
            return $analysis;
        }

        // Word count
        $words = \str_word_count( \strip_tags( $content ) );
        $analysis['word_count'] = $words;

        // Keyword density
        if ( ! empty( $focus_keyword ) ) {
            $keyword_count = \substr_count( \strtolower( $content ), \strtolower( $focus_keyword ) );
            $analysis['keyword_density'] = $words > 0 ? round( ( $keyword_count / $words ) * 100, 2 ) : 0;
        }

        // Basic readability (simplified Flesch score approximation)
        $sentences = \preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
        $sentence_count = count( $sentences );
        
        if ( $sentence_count > 0 && $words > 0 ) {
            $avg_sentence_length = $words / $sentence_count;
            $analysis['readability_score'] = max( 0, min( 100, 100 - $avg_sentence_length ) );
        }

        // Generate suggestions
        if ( $words < 300 ) {
            $analysis['suggestions'][] = __( 'Consider adding more content. Aim for at least 300 words.', 'khm-seo' );
        }

        if ( ! empty( $focus_keyword ) ) {
            if ( $analysis['keyword_density'] < 0.5 ) {
                $analysis['suggestions'][] = __( 'Consider using your focus keyword more often in the content.', 'khm-seo' );
            } elseif ( $analysis['keyword_density'] > 3 ) {
                $analysis['suggestions'][] = __( 'Your focus keyword density is too high. Consider using it less often.', 'khm-seo' );
            }
        }

        if ( $analysis['readability_score'] < 50 ) {
            $analysis['suggestions'][] = __( 'Try to use shorter sentences to improve readability.', 'khm-seo' );
        }

        return $analysis;
    }

    /**
     * Performance monitoring page.
     */
    public function performance_page() {
        // Check if performance monitor is available
        $plugin = \KHM_SEO\Core\Plugin::instance();
        $performance_monitor = $plugin->get_performance_monitor();
        
        if ( ! $performance_monitor ) {
            echo '<div class="wrap"><h1>' . __( 'Performance Monitor', 'khm-seo' ) . '</h1>';
            echo '<div class="notice notice-error"><p>' . __( 'Performance monitor is not available.', 'khm-seo' ) . '</p></div></div>';
            return;
        }
        
        // Render the performance dashboard
        $performance_monitor->render_dashboard();
    }
}
