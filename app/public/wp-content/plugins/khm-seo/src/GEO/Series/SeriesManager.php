<?php
/**
 * Series Manager
 *
 * Manages AnswerCard series for grouping related content and improving entity relationships
 *
 * @package KHM_SEO\GEO\Series
 * @since 2.0.0
 */

namespace KHM_SEO\GEO\Series;

use KHM_SEO\GEO\Entity\EntityManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * SeriesManager Class
 */
class SeriesManager {

    /**
     * @var EntityManager Entity manager instance
     */
    private $entity_manager;

    /**
     * @var SeriesTables Series database tables manager
     */
    private $series_tables;

    /**
     * @var array Series configuration
     */
    private $config = array();

    /**
     * Constructor - Initialize series management
     *
     * @param EntityManager $entity_manager
     */
    public function __construct( EntityManager $entity_manager ) {
        $this->entity_manager = $entity_manager;
        $this->init_hooks();
        $this->load_config();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin interface
        add_action( 'admin_menu', array( $this, 'add_series_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_series_admin_scripts' ) );

        // AJAX handlers
        add_action( 'wp_ajax_khm_geo_create_series', array( $this, 'ajax_create_series' ) );
        add_action( 'wp_ajax_khm_geo_update_series', array( $this, 'ajax_update_series' ) );
        add_action( 'wp_ajax_khm_geo_delete_series', array( $this, 'ajax_delete_series' ) );
        add_action( 'wp_ajax_khm_geo_add_to_series', array( $this, 'ajax_add_to_series' ) );
        add_action( 'wp_ajax_khm_geo_remove_from_series', array( $this, 'ajax_remove_from_series' ) );
        add_action( 'wp_ajax_khm_geo_reorder_series', array( $this, 'ajax_reorder_series' ) );

        // Content processing
        add_filter( 'khm_seo_schema_data', array( $this, 'add_series_schema' ), 10 );
        add_filter( 'the_content', array( $this, 'add_series_navigation' ), 20 );

        // Post save/update
        add_action( 'save_post', array( $this, 'on_post_save' ), 10, 2 );

        // Series metadata
        add_action( 'add_meta_boxes', array( $this, 'add_series_meta_box' ) );
    }

    /**
     * Load series configuration
     */
    private function load_config() {
        $this->config = array(
            'enabled' => true,
            'auto_create_series' => false,
            'default_series_type' => 'sequential',
            'max_series_items' => 50,
            'show_navigation' => true,
            'navigation_position' => 'bottom', // top, bottom, both
            'schema_enabled' => true,
            'auto_link_series' => true
        );

        // Allow override from options
        $saved_config = get_option( 'khm_geo_series_config', array() );
        $this->config = array_merge( $this->config, $saved_config );
    }

    /**
     * Add series admin menu
     */
    public function add_series_admin_menu() {
        add_submenu_page(
            'khm-seo-entities',
            __( 'Series Management', 'khm-seo' ),
            __( 'Series', 'khm-seo' ),
            'manage_options',
            'khm-seo-series',
            array( $this, 'render_series_admin_page' )
        );
    }

    /**
     * Enqueue series admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_series_admin_scripts( $hook ) {
        if ( strpos( $hook, 'khm-seo-series' ) === false && $hook !== 'post.php' && $hook !== 'post-new.php' ) {
            return;
        }

        wp_enqueue_script(
            'khm-geo-series-admin',
            KHM_SEO_PLUGIN_URL . 'assets/js/geo-series-admin.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            KHM_SEO_VERSION,
            true
        );

        wp_enqueue_style(
            'khm-geo-series-admin',
            KHM_SEO_PLUGIN_URL . 'assets/css/geo-series-admin.css',
            array(),
            KHM_SEO_VERSION
        );

        wp_localize_script( 'khm-geo-series-admin', 'khmGeoSeries', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'khm_seo_ajax' ),
            'strings' => array(
                'confirmDelete' => __( 'Are you sure you want to delete this series?', 'khm-seo' ),
                'confirmRemove' => __( 'Are you sure you want to remove this item from the series?', 'khm-seo' ),
                'saving' => __( 'Saving...', 'khm-seo' ),
                'saved' => __( 'Saved!', 'khm-seo' ),
                'error' => __( 'Error occurred', 'khm-seo' )
            )
        ) );
    }

    /**
     * Render series admin page
     */
    public function render_series_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'AnswerCard Series Management', 'khm-seo' ); ?></h1>

            <div class="khm-series-container">
                <div class="khm-series-sidebar">
                    <button id="khm-create-series-btn" class="button button-primary">
                        <?php _e( 'Create New Series', 'khm-seo' ); ?>
                    </button>

                    <div id="khm-series-list">
                        <!-- Series list will be loaded here -->
                    </div>
                </div>

                <div class="khm-series-content">
                    <div id="khm-series-editor">
                        <p class="khm-series-placeholder">
                            <?php _e( 'Select a series to edit or create a new one.', 'khm-seo' ); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Create Series Modal -->
            <div id="khm-create-series-modal" class="khm-modal" style="display: none;">
                <div class="khm-modal-content">
                    <span class="khm-modal-close">&times;</span>
                    <h3><?php _e( 'Create New Series', 'khm-seo' ); ?></h3>

                    <form id="khm-create-series-form">
                        <p>
                            <label for="series-title"><?php _e( 'Series Title', 'khm-seo' ); ?></label>
                            <input type="text" id="series-title" name="title" required>
                        </p>

                        <p>
                            <label for="series-description"><?php _e( 'Description', 'khm-seo' ); ?></label>
                            <textarea id="series-description" name="description" rows="3"></textarea>
                        </p>

                        <p>
                            <label for="series-type"><?php _e( 'Series Type', 'khm-seo' ); ?></label>
                            <select id="series-type" name="type">
                                <option value="sequential"><?php _e( 'Sequential (Part 1, Part 2, etc.)', 'khm-seo' ); ?></option>
                                <option value="chronological"><?php _e( 'Chronological (by date)', 'khm-seo' ); ?></option>
                                <option value="thematic"><?php _e( 'Thematic (related topics)', 'khm-seo' ); ?></option>
                                <option value="custom"><?php _e( 'Custom ordering', 'khm-seo' ); ?></option>
                            </select>
                        </p>

                        <p>
                            <label>
                                <input type="checkbox" name="auto_progression" checked>
                                <?php _e( 'Enable automatic progression links', 'khm-seo' ); ?>
                            </label>
                        </p>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                <?php _e( 'Create Series', 'khm-seo' ); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for creating series
     */
    public function ajax_create_series() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $type = sanitize_text_field( $_POST['type'] ?? 'sequential' );
        $auto_progression = isset( $_POST['auto_progression'] ) ? 1 : 0;

        if ( empty( $title ) ) {
            wp_send_json_error( 'Series title is required' );
        }

        $series_data = array(
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'auto_progression' => $auto_progression,
            'created_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' )
        );

        $series_id = $this->create_series( $series_data );

        if ( $series_id ) {
            wp_send_json_success( array(
                'series_id' => $series_id,
                'message' => __( 'Series created successfully', 'khm-seo' )
            ) );
        } else {
            wp_send_json_error( 'Failed to create series' );
        }
    }

    /**
     * Create a new series
     *
     * @param array $series_data Series data
     * @return int|false Series ID or false on failure
     */
    public function create_series( $series_data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series';

        $result = $wpdb->insert(
            $table_name,
            array(
                'title' => $series_data['title'],
                'description' => $series_data['description'],
                'type' => $series_data['type'],
                'auto_progression' => $series_data['auto_progression'],
                'created_by' => $series_data['created_by'],
                'created_at' => $series_data['created_at'],
                'updated_at' => $series_data['updated_at']
            ),
            array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( $result === false ) {
            return false;
        }

        $series_id = $wpdb->insert_id;

        // Create initial series metadata
        $this->update_series_meta( $series_id, 'item_count', 0 );
        $this->update_series_meta( $series_id, 'last_updated', current_time( 'mysql' ) );

        do_action( 'khm_geo_series_created', $series_id, $series_data );

        return $series_id;
    }

    /**
     * AJAX handler for updating series
     */
    public function ajax_update_series() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $series_id = intval( $_POST['series_id'] ?? 0 );
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $type = sanitize_text_field( $_POST['type'] ?? '' );

        if ( ! $series_id || empty( $title ) ) {
            wp_send_json_error( 'Invalid series data' );
        }

        $update_data = array(
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'updated_at' => current_time( 'mysql' )
        );

        $updated = $this->update_series( $series_id, $update_data );

        if ( $updated ) {
            wp_send_json_success( array(
                'message' => __( 'Series updated successfully', 'khm-seo' )
            ) );
        } else {
            wp_send_json_error( 'Failed to update series' );
        }
    }

    /**
     * Update series data
     *
     * @param int $series_id Series ID
     * @param array $update_data Data to update
     * @return bool Success status
     */
    public function update_series( $series_id, $update_data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series';

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array( 'id' => $series_id ),
            $this->get_format_array( $update_data ),
            array( '%d' )
        );

        if ( $result !== false ) {
            $this->update_series_meta( $series_id, 'last_updated', current_time( 'mysql' ) );
            do_action( 'khm_geo_series_updated', $series_id, $update_data );
            return true;
        }

        return false;
    }

    /**
     * AJAX handler for deleting series
     */
    public function ajax_delete_series() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $series_id = intval( $_POST['series_id'] ?? 0 );

        if ( ! $series_id ) {
            wp_send_json_error( 'Invalid series ID' );
        }

        $deleted = $this->delete_series( $series_id );

        if ( $deleted ) {
            wp_send_json_success( array(
                'message' => __( 'Series deleted successfully', 'khm-seo' )
            ) );
        } else {
            wp_send_json_error( 'Failed to delete series' );
        }
    }

    /**
     * Delete a series
     *
     * @param int $series_id Series ID
     * @return bool Success status
     */
    public function delete_series( $series_id ) {
        global $wpdb;

        // Remove all items from series first
        $this->clear_series_items( $series_id );

        // Delete series metadata
        $this->delete_series_meta( $series_id );

        // Delete series
        $table_name = $wpdb->prefix . 'khm_geo_series';
        $result = $wpdb->delete( $table_name, array( 'id' => $series_id ), array( '%d' ) );

        if ( $result !== false ) {
            do_action( 'khm_geo_series_deleted', $series_id );
            return true;
        }

        return false;
    }

    /**
     * AJAX handler for adding item to series
     */
    public function ajax_add_to_series() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $series_id = intval( $_POST['series_id'] ?? 0 );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        $position = intval( $_POST['position'] ?? 0 );

        if ( ! $series_id || ! $post_id ) {
            wp_send_json_error( 'Invalid series or post ID' );
        }

        $added = $this->add_to_series( $series_id, $post_id, $position );

        if ( $added ) {
            wp_send_json_success( array(
                'message' => __( 'Item added to series successfully', 'khm-seo' )
            ) );
        } else {
            wp_send_json_error( 'Failed to add item to series' );
        }
    }

    /**
     * Add post to series
     *
     * @param int $series_id Series ID
     * @param int $post_id Post ID
     * @param int $position Position in series (0 = auto)
     * @return bool Success status
     */
    public function add_to_series( $series_id, $post_id, $position = 0 ) {
        global $wpdb;

        // Check if post is already in series
        if ( $this->is_post_in_series( $post_id, $series_id ) ) {
            return false;
        }

        // If position is 0, add to end
        if ( $position === 0 ) {
            $position = $this->get_next_series_position( $series_id );
        } else {
            // Shift existing items
            $this->shift_series_positions( $series_id, $position, 1 );
        }

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        $result = $wpdb->insert(
            $table_name,
            array(
                'series_id' => $series_id,
                'post_id' => $post_id,
                'position' => $position,
                'added_at' => current_time( 'mysql' )
            ),
            array( '%d', '%d', '%d', '%s' )
        );

        if ( $result !== false ) {
            $this->update_series_item_count( $series_id );
            update_post_meta( $post_id, '_khm_geo_series_id', $series_id );
            do_action( 'khm_geo_item_added_to_series', $series_id, $post_id, $position );
            return true;
        }

        return false;
    }

    /**
     * AJAX handler for removing item from series
     */
    public function ajax_remove_from_series() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $series_id = intval( $_POST['series_id'] ?? 0 );
        $post_id = intval( $_POST['post_id'] ?? 0 );

        if ( ! $series_id || ! $post_id ) {
            wp_send_json_error( 'Invalid series or post ID' );
        }

        $removed = $this->remove_from_series( $series_id, $post_id );

        if ( $removed ) {
            wp_send_json_success( array(
                'message' => __( 'Item removed from series successfully', 'khm-seo' )
            ) );
        } else {
            wp_send_json_error( 'Failed to remove item from series' );
        }
    }

    /**
     * Remove post from series
     *
     * @param int $series_id Series ID
     * @param int $post_id Post ID
     * @return bool Success status
     */
    public function remove_from_series( $series_id, $post_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        $result = $wpdb->delete(
            $table_name,
            array( 'series_id' => $series_id, 'post_id' => $post_id ),
            array( '%d', '%d' )
        );

        if ( $result !== false ) {
            $this->update_series_item_count( $series_id );
            $this->reorder_series_positions( $series_id );
            delete_post_meta( $post_id, '_khm_geo_series_id' );
            do_action( 'khm_geo_item_removed_from_series', $series_id, $post_id );
            return true;
        }

        return false;
    }

    /**
     * AJAX handler for reordering series
     */
    public function ajax_reorder_series() {
        check_ajax_referer( 'khm_seo_ajax', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }

        $series_id = intval( $_POST['series_id'] ?? 0 );
        $order = $_POST['order'] ?? array();

        if ( ! $series_id || empty( $order ) ) {
            wp_send_json_error( 'Invalid series ID or order data' );
        }

        $reordered = $this->reorder_series_items( $series_id, $order );

        if ( $reordered ) {
            wp_send_json_success( array(
                'message' => __( 'Series reordered successfully', 'khm-seo' )
            ) );
        } else {
            wp_send_json_error( 'Failed to reorder series' );
        }
    }

    /**
     * Reorder series items
     *
     * @param int $series_id Series ID
     * @param array $order Array of post IDs in new order
     * @return bool Success status
     */
    public function reorder_series_items( $series_id, $order ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        $success = true;
        foreach ( $order as $position => $post_id ) {
            $result = $wpdb->update(
                $table_name,
                array( 'position' => $position + 1 ),
                array( 'series_id' => $series_id, 'post_id' => $post_id ),
                array( '%d' ),
                array( '%d', '%d' )
            );

            if ( $result === false ) {
                $success = false;
            }
        }

        if ( $success ) {
            do_action( 'khm_geo_series_reordered', $series_id, $order );
        }

        return $success;
    }

    /**
     * Add series schema markup
     *
     * @param array $schemas Existing schemas
     * @return array Enhanced schemas
     */
    public function add_series_schema( $schemas ) {
        if ( ! $this->config['schema_enabled'] ) {
            return $schemas;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return $schemas;
        }

        $series_id = $this->get_post_series_id( $post_id );
        if ( ! $series_id ) {
            return $schemas;
        }

        $series_data = $this->get_series( $series_id );
        if ( ! $series_data ) {
            return $schemas;
        }

        $series_items = $this->get_series_items( $series_id );
        if ( empty( $series_items ) ) {
            return $schemas;
        }

        // Create series schema
        $series_schema = array(
            '@type' => 'ItemList',
            'name' => $series_data->title,
            'description' => $series_data->description,
            'numberOfItems' => count( $series_items ),
            'itemListElement' => array()
        );

        foreach ( $series_items as $item ) {
            $post = get_post( $item->post_id );
            if ( ! $post ) {
                continue;
            }

            $series_schema['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $item->position,
                'item' => array(
                    '@type' => 'Article',
                    'name' => get_the_title( $post ),
                    'url' => get_permalink( $post ),
                    'description' => get_the_excerpt( $post )
                )
            );
        }

        $schemas[] = $series_schema;

        return $schemas;
    }

    /**
     * Add series navigation to content
     *
     * @param string $content Post content
     * @return string Enhanced content
     */
    public function add_series_navigation( $content ) {
        if ( ! $this->config['show_navigation'] || ! is_single() ) {
            return $content;
        }

        $post_id = get_the_ID();
        $series_id = $this->get_post_series_id( $post_id );

        if ( ! $series_id ) {
            return $content;
        }

        $navigation = $this->generate_series_navigation( $series_id, $post_id );

        if ( empty( $navigation ) ) {
            return $content;
        }

        $position = $this->config['navigation_position'];

        if ( $position === 'top' || $position === 'both' ) {
            $content = $navigation . $content;
        }

        if ( $position === 'bottom' || $position === 'both' ) {
            $content .= $navigation;
        }

        return $content;
    }

    /**
     * Generate series navigation HTML
     *
     * @param int $series_id Series ID
     * @param int $current_post_id Current post ID
     * @return string Navigation HTML
     */
    private function generate_series_navigation( $series_id, $current_post_id ) {
        $series_data = $this->get_series( $series_id );
        $series_items = $this->get_series_items( $series_id );

        if ( ! $series_data || empty( $series_items ) ) {
            return '';
        }

        $current_position = null;
        foreach ( $series_items as $item ) {
            if ( $item->post_id == $current_post_id ) {
                $current_position = $item->position;
                break;
            }
        }

        if ( $current_position === null ) {
            return '';
        }

        $prev_item = null;
        $next_item = null;

        foreach ( $series_items as $item ) {
            if ( $item->position == $current_position - 1 ) {
                $prev_item = $item;
            }
            if ( $item->position == $current_position + 1 ) {
                $next_item = $item;
            }
        }

        ob_start();
        ?>
        <nav class="khm-series-navigation" aria-label="<?php printf( esc_attr__( 'Series: %s', 'khm-seo' ), $series_data->title ); ?>">
            <div class="khm-series-header">
                <h3><?php printf( __( 'Part %d of %s', 'khm-seo' ), $current_position, esc_html( $series_data->title ) ); ?></h3>
                <?php if ( $series_data->description ) : ?>
                    <p><?php echo esc_html( $series_data->description ); ?></p>
                <?php endif; ?>
            </div>

            <div class="khm-series-nav-links">
                <?php if ( $prev_item ) : ?>
                    <a href="<?php echo get_permalink( $prev_item->post_id ); ?>" class="khm-series-prev" rel="prev">
                        <span class="nav-label"><?php _e( 'Previous', 'khm-seo' ); ?></span>
                        <span class="nav-title"><?php echo get_the_title( $prev_item->post_id ); ?></span>
                    </a>
                <?php endif; ?>

                <?php if ( $next_item ) : ?>
                    <a href="<?php echo get_permalink( $next_item->post_id ); ?>" class="khm-series-next" rel="next">
                        <span class="nav-label"><?php _e( 'Next', 'khm-seo' ); ?></span>
                        <span class="nav-title"><?php echo get_the_title( $next_item->post_id ); ?></span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ( count( $series_items ) > 2 ) : ?>
                <div class="khm-series-overview">
                    <a href="#" class="khm-series-toggle"><?php _e( 'View Series Overview', 'khm-seo' ); ?></a>
                    <ol class="khm-series-list" style="display: none;">
                        <?php foreach ( $series_items as $item ) : ?>
                            <li class="<?php echo $item->post_id == $current_post_id ? 'current' : ''; ?>">
                                <a href="<?php echo get_permalink( $item->post_id ); ?>">
                                    <?php echo get_the_title( $item->post_id ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
        </nav>
        <?php

        return ob_get_clean();
    }

    /**
     * Add series meta box to post editor
     */
    public function add_series_meta_box() {
        add_meta_box(
            'khm-series-meta-box',
            __( 'AnswerCard Series', 'khm-seo' ),
            array( $this, 'render_series_meta_box' ),
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render series meta box
     *
     * @param WP_Post $post Current post
     */
    public function render_series_meta_box( $post ) {
        $current_series_id = $this->get_post_series_id( $post->ID );
        $all_series = $this->get_all_series();

        wp_nonce_field( 'khm_series_meta_box', 'khm_series_meta_box_nonce' );
        ?>
        <p>
            <label for="khm-series-select"><?php _e( 'Add to Series:', 'khm-seo' ); ?></label>
            <select id="khm-series-select" name="khm_series_id">
                <option value=""><?php _e( 'Select Series', 'khm-seo' ); ?></option>
                <?php foreach ( $all_series as $series ) : ?>
                    <option value="<?php echo esc_attr( $series->id ); ?>" <?php selected( $current_series_id, $series->id ); ?>>
                        <?php echo esc_html( $series->title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <?php if ( $current_series_id ) : ?>
            <p>
                <a href="#" id="khm-remove-from-series" class="button button-small">
                    <?php _e( 'Remove from Series', 'khm-seo' ); ?>
                </a>
            </p>
        <?php endif; ?>

        <div id="khm-series-position" style="<?php echo $current_series_id ? '' : 'display: none;'; ?>">
            <p>
                <label for="khm-series-position-input"><?php _e( 'Position in Series:', 'khm-seo' ); ?></label>
                <input type="number" id="khm-series-position-input" name="khm_series_position" min="1"
                       value="<?php echo esc_attr( $this->get_post_series_position( $post->ID ) ); ?>">
            </p>
        </div>
        <?php
    }

    /**
     * Handle post save for series metadata
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function on_post_save( $post_id, $post ) {
        // Verify nonce
        if ( ! isset( $_POST['khm_series_meta_box_nonce'] ) ||
             ! wp_verify_nonce( $_POST['khm_series_meta_box_nonce'], 'khm_series_meta_box' ) ) {
            return;
        }

        // Check if user can edit
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $series_id = intval( $_POST['khm_series_id'] ?? 0 );
        $position = intval( $_POST['khm_series_position'] ?? 0 );

        $current_series_id = $this->get_post_series_id( $post_id );

        // Remove from current series if different
        if ( $current_series_id && $current_series_id != $series_id ) {
            $this->remove_from_series( $current_series_id, $post_id );
        }

        // Add to new series if specified
        if ( $series_id && $series_id != $current_series_id ) {
            $this->add_to_series( $series_id, $post_id, $position );
        } elseif ( $series_id && $position > 0 ) {
            // Update position in current series
            $this->update_series_item_position( $series_id, $post_id, $position );
        }
    }

    /**
     * Get series by ID
     *
     * @param int $series_id Series ID
     * @return object|null Series data or null
     */
    public function get_series( $series_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series';

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $series_id )
        );
    }

    /**
     * Get all series
     *
     * @return array Series objects
     */
    public function get_all_series() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series';

        return $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY title ASC" );
    }

    /**
     * Get series items
     *
     * @param int $series_id Series ID
     * @return array Series items
     */
    public function get_series_items( $series_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE series_id = %d ORDER BY position ASC",
                $series_id
            )
        );
    }

    /**
     * Get post's series ID
     *
     * @param int $post_id Post ID
     * @return int|null Series ID or null
     */
    public function get_post_series_id( $post_id ) {
        return get_post_meta( $post_id, '_khm_geo_series_id', true );
    }

    /**
     * Get post's position in series
     *
     * @param int $post_id Post ID
     * @return int Position
     */
    public function get_post_series_position( $post_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT position FROM {$table_name} WHERE post_id = %d",
                $post_id
            )
        );
    }

    /**
     * Check if post is in series
     *
     * @param int $post_id Post ID
     * @param int $series_id Series ID
     * @return bool True if in series
     */
    public function is_post_in_series( $post_id, $series_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE series_id = %d AND post_id = %d",
                $series_id, $post_id
            )
        );

        return $count > 0;
    }

    /**
     * Get next position in series
     *
     * @param int $series_id Series ID
     * @return int Next position
     */
    private function get_next_series_position( $series_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        $max_position = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(position) FROM {$table_name} WHERE series_id = %d",
                $series_id
            )
        );

        return (int) $max_position + 1;
    }

    /**
     * Shift series positions
     *
     * @param int $series_id Series ID
     * @param int $from_position Position to start shifting from
     * @param int $shift_amount Amount to shift
     */
    private function shift_series_positions( $series_id, $from_position, $shift_amount ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table_name} SET position = position + %d WHERE series_id = %d AND position >= %d",
                $shift_amount, $series_id, $from_position
            )
        );
    }

    /**
     * Reorder series positions after removal
     *
     * @param int $series_id Series ID
     */
    private function reorder_series_positions( $series_id ) {
        $items = $this->get_series_items( $series_id );

        $position = 1;
        foreach ( $items as $item ) {
            $this->update_series_item_position( $series_id, $item->post_id, $position );
            $position++;
        }
    }

    /**
     * Update series item position
     *
     * @param int $series_id Series ID
     * @param int $post_id Post ID
     * @param int $position New position
     */
    private function update_series_item_position( $series_id, $post_id, $position ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        $wpdb->update(
            $table_name,
            array( 'position' => $position ),
            array( 'series_id' => $series_id, 'post_id' => $post_id ),
            array( '%d' ),
            array( '%d', '%d' )
        );
    }

    /**
     * Update series item count
     *
     * @param int $series_id Series ID
     */
    private function update_series_item_count( $series_id ) {
        $items = $this->get_series_items( $series_id );
        $this->update_series_meta( $series_id, 'item_count', count( $items ) );
    }

    /**
     * Clear all items from series
     *
     * @param int $series_id Series ID
     */
    private function clear_series_items( $series_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_items';

        $wpdb->delete( $table_name, array( 'series_id' => $series_id ), array( '%d' ) );
    }

    /**
     * Update series metadata
     *
     * @param int $series_id Series ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     */
    private function update_series_meta( $series_id, $key, $value ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_meta';

        $wpdb->replace(
            $table_name,
            array(
                'series_id' => $series_id,
                'meta_key' => $key,
                'meta_value' => maybe_serialize( $value )
            ),
            array( '%d', '%s', '%s' )
        );
    }

    /**
     * Delete series metadata
     *
     * @param int $series_id Series ID
     */
    private function delete_series_meta( $series_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'khm_geo_series_meta';

        $wpdb->delete( $table_name, array( 'series_id' => $series_id ), array( '%d' ) );
    }

    /**
     * Get format array for database operations
     *
     * @param array $data Data array
     * @return array Format array
     */
    private function get_format_array( $data ) {
        $formats = array();
        foreach ( $data as $key => $value ) {
            if ( is_int( $value ) ) {
                $formats[] = '%d';
            } elseif ( is_float( $value ) ) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }

    /**
     * Get series configuration
     *
     * @param string $key Configuration key
     * @return mixed Configuration value
     */
    public function get_config( $key = null ) {
        if ( $key ) {
            return $this->config[ $key ] ?? null;
        }

        return $this->config;
    }

    /**
     * Set series tables instance
     *
     * @param SeriesTables $series_tables Series tables instance
     */
    public function set_series_tables( $series_tables ) {
        $this->series_tables = $series_tables;
    }
}