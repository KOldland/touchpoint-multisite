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
            6
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
        $selected_type = isset( $_GET['khm_post_type'] ) ? sanitize_key( $_GET['khm_post_type'] ) : $default_post_type;
        if ( empty( $selected_type ) || ! isset( $post_types[ $selected_type ] ) ) {
            $selected_type = $default_post_type;
        }

        $post_id = isset( $_GET['khm_post_id'] ) ? (int) $_GET['khm_post_id'] : 0;
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

        $social_url = $selected_post ? admin_url( 'admin.php?page=khm-seo-social-preview&khm_post_type=' . $selected_type . '&khm_post_id=' . $selected_post->ID ) : '';
        $geo_url = $selected_post ? admin_url( 'admin.php?page=khm-seo-geo-post&khm_post_type=' . $selected_type . '&khm_post_id=' . $selected_post->ID ) : '';
        $health_url = $selected_post ? admin_url( 'admin.php?page=khm-seo-post-health&khm_post_type=' . $selected_type . '&khm_post_id=' . $selected_post->ID ) : '';

        $dep_smma   = class_exists( 'KH_SMMA\Services\SmmaGenerator' );
        $dep_agent  = class_exists( 'KHM_SEO_AGENT\API\Rest_Api' );
        $dep_adman  = function_exists( 'kh_ad_manager_get_sponsor_meta' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Boost Visibility', 'khm-seo' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Publish first, then manage GEO, social previews, and post health from here.', 'khm-seo' ); ?>
            </p>

            <?php if ( ! $dep_smma || ! $dep_agent || ! $dep_adman ) : ?>
            <div class="notice notice-warning inline" style="padding:8px 12px;margin-bottom:16px;">
                <strong><?php esc_html_e( 'Plugin bundle status', 'khm-seo' ); ?></strong>
                <ul style="margin:.4em 0 0 1em;list-style:disc;">
                    <?php if ( ! $dep_smma ) : ?>
                    <li><?php esc_html_e( 'KH SMMA — inactive. Social variant generation and approval workflows unavailable.', 'khm-seo' ); ?></li>
                    <?php endif; ?>
                    <?php if ( ! $dep_agent ) : ?>
                    <li><?php esc_html_e( 'KHM SEO Agent — inactive. AI keyword analysis unavailable.', 'khm-seo' ); ?></li>
                    <?php endif; ?>
                    <?php if ( ! $dep_adman ) : ?>
                    <li><?php esc_html_e( 'KH Ad Manager — inactive. Sponsor name resolution unavailable.', 'khm-seo' ); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="get">
                <input type="hidden" name="page" value="khm-seo-boost-visibility" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="khm-boost-post-type"><?php esc_html_e( 'Content Type', 'khm-seo' ); ?></label>
                            </th>
                            <td>
                                <select id="khm-boost-post-type" name="khm_post_type">
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
                                <select id="khm-boost-post-id" name="khm_post_id">
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

                <hr />
                <h2><?php esc_html_e( 'Promotion Planner', 'khm-seo' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Create promotion variants & optionally boost visibility on LinkedIn or Google.', 'khm-seo' ); ?>
                </p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Title', 'khm-seo' ); ?></th>
                            <th><?php esc_html_e( 'Published', 'khm-seo' ); ?></th>
                            <th><?php esc_html_e( 'Phase', 'khm-seo' ); ?></th>
                            <th><?php esc_html_e( 'SEO Score', 'khm-seo' ); ?></th>
                            <th><?php esc_html_e( 'GEO Score / Policy', 'khm-seo' ); ?></th>
                            <th><?php esc_html_e( 'Sponsor', 'khm-seo' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'khm-seo' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $posts as $post_item ) : ?>
                            <?php
                            $seo_score = (int) get_post_meta( $post_item->ID, '_khm_seo_score', true );
                            $geo_score = (int) get_post_meta( $post_item->ID, '_khm_geo_score', true );
                            $geo_policy = '';
                            $policy_sponsor_id = 0;
                            $sponsor_name = '';
                            $summary_source = $post_item->post_excerpt ? $post_item->post_excerpt : $post_item->post_content;
                            $post_summary = wp_trim_words( wp_strip_all_tags( $summary_source ), 24, '...' );

                            // Get phase information for current user
                            $phase_data = array(
                                'phase' => 'Attention',
                                'signals' => array(),
                                'color' => '#0073aa',
                            );

                            if ( class_exists( 'KH_SMMA\\Services\\PhaseEngine' ) && isset( $GLOBALS['wpdb'] ) && $GLOBALS['wpdb'] instanceof \wpdb ) {
                                $phase_engine = new \KH_SMMA\Services\PhaseEngine( $GLOBALS['wpdb'] );
                                $user_phase = $phase_engine->get_user_phase( get_current_user_id() );
                                if ( is_array( $user_phase ) && ! empty( $user_phase['assigned_phase'] ) ) {
                                    $phase_data['phase'] = $user_phase['assigned_phase'];
                                    $phase_data['signals'] = array_slice( $user_phase['top_signals'] ?? array(), 0, 3 );
                                }
                            }

                            // Set phase colors
                            $phase_colors = array(
                                'Attention' => '#0073aa',
                                'Antagonistic' => '#f0a000',
                                'Anxiety' => '#dc3232',
                                'Acceptance' => '#46b450',
                            );
                            $phase_data['color'] = $phase_colors[ $phase_data['phase'] ] ?? '#0073aa';

                            if ( function_exists( 'khm_seo' ) && khm_seo()->get_geo_manager() ) {
                                $geo_manager = khm_seo()->get_geo_manager();
                                if ( method_exists( $geo_manager, 'getSponsorPolicyForPost' ) ) {
                                    $policy = $geo_manager->getSponsorPolicyForPost( $post_item->ID, 'global' );
                                    if ( is_array( $policy ) ) {
                                        $geo_policy = $policy['policy'] ?? '';
                                        $policy_sponsor_id = ! empty( $policy['sponsor_id'] ) ? (int) $policy['sponsor_id'] : 0;
                                        if ( ! empty( $policy['sponsor_id'] ) && function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
                                            $sponsor_meta = kh_ad_manager_get_sponsor_meta( (int) $policy['sponsor_id'] );
                                            $sponsor_name = $sponsor_meta['name'] ?? '';
                                        }
                                    }
                                }
                            }

                            // Check for pending schedules
                            $pending_count = 0;
                            $pending_query = new \WP_Query( array(
                                'post_type' => 'kh_smma_schedule',
                                'post_status' => 'publish',
                                'posts_per_page' => -1,
                                'fields' => 'ids',
                                'meta_query' => array(
                                    'relation' => 'AND',
                                    array(
                                        'key' => '_kh_smma_payload',
                                        'value' => sprintf( '"post_id":%d', $post_item->ID ),
                                        'compare' => 'LIKE',
                                    ),
                                    array(
                                        'key' => '_kh_smma_approval_status',
                                        'value' => 'pending',
                                        'compare' => '=',
                                    ),
                                ),
                            ) );
                            $pending_count = $pending_query->found_posts;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $post_item->post_title ?: sprintf( __( '(Untitled #%d)', 'khm-seo' ), $post_item->ID ) ); ?></strong>
                                </td>
                                <td><?php echo esc_html( get_the_date( '', $post_item ) ); ?></td>
                                <td>
                                    <span
                                        class="khm-phase-badge"
                                        style="display: inline-block; padding: 4px 10px; border-radius: 3px; background-color: <?php echo esc_attr( $phase_data['color'] ); ?>; color: #fff; font-size: 12px; font-weight: 600;"
                                        title="<?php echo esc_attr( implode( ', ', $phase_data['signals'] ) ?: __( 'No signals available', 'khm-seo' ) ); ?>"
                                    >
                                        <?php echo esc_html( $phase_data['phase'] ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $seo_score ?: '—' ); ?></td>
                                <td>
                                    <?php echo esc_html( $geo_score ?: '—' ); ?>
                                    <?php if ( $geo_policy ) : ?>
                                        <br /><small><?php echo esc_html( strtoupper( $geo_policy ) ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $sponsor_name ?: '—' ); ?></td>
                                <td>
                                    <button
                                        class="button khm-smma-promote-btn"
                                        data-post-id="<?php echo esc_attr( $post_item->ID ); ?>"
                                        data-post-title="<?php echo esc_attr( $post_item->post_title ); ?>"
                                        data-post-summary="<?php echo esc_attr( $post_summary ); ?>"
                                        data-phase="<?php echo esc_attr( $phase_data['phase'] ); ?>"
                                        data-sponsor-id="<?php echo esc_attr( $policy_sponsor_id ); ?>"
                                        data-sponsor-policy="<?php echo esc_attr( $geo_policy ); ?>"
                                        data-post-url="<?php echo esc_url( get_permalink( $post_item ) ); ?>"
                                    >
                                        <?php esc_html_e( 'Promote', 'khm-seo' ); ?>
                                    </button>
                                    <button
                                        class="button khm-smma-boost-btn"
                                        data-post-id="<?php echo esc_attr( $post_item->ID ); ?>"
                                        data-post-title="<?php echo esc_attr( $post_item->post_title ); ?>"
                                        data-post-summary="<?php echo esc_attr( $post_summary ); ?>"
                                        data-phase="<?php echo esc_attr( $phase_data['phase'] ); ?>"
                                        data-sponsor-id="<?php echo esc_attr( $policy_sponsor_id ); ?>"
                                        data-sponsor-policy="<?php echo esc_attr( $geo_policy ); ?>"
                                        data-post-url="<?php echo esc_url( get_permalink( $post_item ) ); ?>"
                                    >
                                        <?php esc_html_e( 'Boost', 'khm-seo' ); ?>
                                    </button>
                                    <?php if ( $pending_count > 0 ) : ?>
                                        <a
                                            class="button button-primary"
                                            href="#pending-approvals"
                                            title="<?php echo esc_attr( sprintf( __( '%d pending approval(s)', 'khm-seo' ), $pending_count ) ); ?>"
                                        >
                                            <?php echo esc_html( sprintf( __( 'Pending (%d)', 'khm-seo' ), $pending_count ) ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <hr id="pending-approvals" />
                <h2><?php esc_html_e( 'Pending Sponsor Approvals', 'khm-seo' ); ?></h2>
                <?php
                $pending = new \WP_Query( array(
                    'post_type'      => 'kh_smma_schedule',
                    'post_status'    => 'publish',
                    'posts_per_page' => 20,
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => '_kh_smma_approval_status',
                            'value'   => 'pending',
                            'compare' => '=',
                        ),
                    ),
                ) );
                ?>
                <?php if ( $pending->have_posts() ) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Schedule', 'khm-seo' ); ?></th>
                                <th><?php esc_html_e( 'Post', 'khm-seo' ); ?></th>
                                <th><?php esc_html_e( 'Variant Preview', 'khm-seo' ); ?></th>
                                <th><?php esc_html_e( 'Phase', 'khm-seo' ); ?></th>
                                <th><?php esc_html_e( 'Sponsor', 'khm-seo' ); ?></th>
                                <th><?php esc_html_e( 'Compliance', 'khm-seo' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'khm-seo' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $pending->posts as $schedule_id ) : ?>
                                <?php
                                $payload = get_post_meta( $schedule_id, '_kh_smma_payload', true );
                                $source_post_id = isset( $payload['post_id'] ) ? (int) $payload['post_id'] : 0;
                                $source_post = $source_post_id ? get_post( $source_post_id ) : null;
                                $sponsor_id = (int) get_post_meta( $schedule_id, '_kh_smma_sponsor_id', true );
                                $sponsor_name = '';
                                if ( $sponsor_id && function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
                                    $sponsor = kh_ad_manager_get_sponsor_meta( $sponsor_id );
                                    $sponsor_name = $sponsor['name'] ?? '';
                                }
                                $variant_text = $payload['text'] ?? '';
                                $phase_tag = $payload['phase_tag'] ?? 'Attention';
                                $compliance_notes = get_post_meta( $schedule_id, '_kh_smma_compliance_notes', true );
                                $scheduled_at = get_post_meta( $schedule_id, '_kh_smma_scheduled_at', true );

                                // Set phase colors
                                $phase_colors = array(
                                    'Attention' => '#0073aa',
                                    'Antagonistic' => '#f0a000',
                                    'Anxiety' => '#dc3232',
                                    'Acceptance' => '#46b450',
                                );
                                $phase_color = $phase_colors[ $phase_tag ] ?? '#0073aa';
                                ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo esc_html( $schedule_id ); ?></strong>
                                        <?php if ( $scheduled_at ) : ?>
                                            <br /><small><?php echo esc_html( date_i18n( 'M j, Y g:i a', $scheduled_at ) ); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $source_post ? $source_post->post_title : __( 'Unknown', 'khm-seo' ) ); ?></td>
                                    <td>
                                        <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo esc_html( $variant_text ); ?>
                                        </div>
                                        <a href="#" class="khm-smma-preview-btn" data-schedule-id="<?php echo esc_attr( $schedule_id ); ?>">
                                            <?php esc_html_e( 'View Full', 'khm-seo' ); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span
                                            class="khm-phase-badge"
                                            style="display: inline-block; padding: 4px 10px; border-radius: 3px; background-color: <?php echo esc_attr( $phase_color ); ?>; color: #fff; font-size: 12px; font-weight: 600;"
                                        >
                                            <?php echo esc_html( $phase_tag ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( $sponsor_name ?: '—' ); ?></td>
                                    <td>
                                        <?php if ( $compliance_notes ) : ?>
                                            <span
                                                class="khm-compliance-badge"
                                                style="display: inline-block; padding: 4px 8px; border-radius: 3px; background-color: <?php echo ( strpos( $compliance_notes, 'FAIL' ) !== false ? '#dc3232' : ( strpos( $compliance_notes, 'WARN' ) !== false ? '#f0a000' : '#46b450' ) ); ?>; color: #fff; font-size: 11px;"
                                                title="<?php echo esc_attr( $compliance_notes ); ?>"
                                            >
                                                <?php echo esc_html( strpos( $compliance_notes, 'FAIL' ) !== false ? 'FAIL' : ( strpos( $compliance_notes, 'WARN' ) !== false ? 'WARN' : 'OK' ) ); ?>
                                            </span>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button
                                            class="button button-primary khm-smma-approve-btn"
                                            data-schedule-id="<?php echo esc_attr( $schedule_id ); ?>"
                                        >
                                            <?php esc_html_e( 'Approve', 'khm-seo' ); ?>
                                        </button>
                                        <button
                                            class="button khm-smma-edit-variant-btn"
                                            data-schedule-id="<?php echo esc_attr( $schedule_id ); ?>"
                                            data-variant-text="<?php echo esc_attr( $variant_text ); ?>"
                                        >
                                            <?php esc_html_e( 'Edit', 'khm-seo' ); ?>
                                        </button>
                                        <button
                                            class="button khm-smma-reject-btn"
                                            data-schedule-id="<?php echo esc_attr( $schedule_id ); ?>"
                                        >
                                            <?php esc_html_e( 'Reject', 'khm-seo' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e( 'No pending approvals.', 'khm-seo' ); ?></p>
                <?php endif; ?>

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
        $selected_type = isset( $_GET['khm_post_type'] ) ? sanitize_key( $_GET['khm_post_type'] ) : $default_post_type;
        if ( empty( $selected_type ) || ! isset( $post_types[ $selected_type ] ) ) {
            $selected_type = $default_post_type;
        }

        $post_id = isset( $_GET['khm_post_id'] ) ? (int) $_GET['khm_post_id'] : 0;
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
                                <select id="khm-geo-post-type" name="khm_post_type">
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
                                <select id="khm-geo-post-id" name="khm_post_id">
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

        wp_safe_redirect( admin_url( 'admin.php?page=khm-seo-geo-post&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post_id . '&updated=1' ) );
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
        $selected_type = isset( $_GET['khm_post_type'] ) ? sanitize_key( $_GET['khm_post_type'] ) : $default_post_type;
        if ( empty( $selected_type ) || ! isset( $post_types[ $selected_type ] ) ) {
            $selected_type = $default_post_type;
        }

        $post_id = isset( $_GET['khm_post_id'] ) ? (int) $_GET['khm_post_id'] : 0;
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
                                <select id="khm-health-post-type" name="khm_post_type">
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
                                <select id="khm-health-post-id" name="khm_post_id">
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

        $social_url = admin_url( 'admin.php?page=khm-seo-social-preview&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID );
        $geo_url = admin_url( 'admin.php?page=khm-seo-geo-post&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID );
        $health_url = admin_url( 'admin.php?page=khm-seo-post-health&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID );
        $hub_url = admin_url( 'admin.php?page=khm-seo-boost-visibility&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID );

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

        $actions['boost_visibility'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-boost-visibility&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID ) ) . '">' . esc_html__( 'Boost Visibility', 'khm-seo' ) . '</a>';
        $actions['smma_promote'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-boost-visibility&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID . '&smma_action=promote' ) ) . '">' . esc_html__( 'Promote', 'khm-seo' ) . '</a>';
        $actions['smma_boost'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-boost-visibility&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID . '&smma_action=boost' ) ) . '">' . esc_html__( 'Boost', 'khm-seo' ) . '</a>';
        $actions['boost_social'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-social-preview&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID ) ) . '">' . esc_html__( 'Social', 'khm-seo' ) . '</a>';
        $actions['boost_geo'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-geo-post&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID ) ) . '">' . esc_html__( 'GEO', 'khm-seo' ) . '</a>';
        $actions['boost_health'] = '<a href="' . esc_url( admin_url( 'admin.php?page=khm-seo-post-health&khm_post_type=' . $post->post_type . '&khm_post_id=' . $post->ID ) ) . '">' . esc_html__( 'Post Health', 'khm-seo' ) . '</a>';

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
                'smma'     => array(
                    'enabled' => class_exists( 'KH_SMMA\\Services\\SmmaGenerator' ),
                    'rest_url' => esc_url_raw( rest_url( 'kh-smma/v1/' ) ),
                    'rest_nonce' => wp_create_nonce( 'wp_rest' ),
                    'dashboard_url' => admin_url( 'admin.php?page=kh-smma-dashboard' ),
                    'default_channel' => 'linkedin',
                    'default_budget_cents' => 5000,
                    'default_currency' => 'AUD',
                ),
                'strings'  => array(
                    'analyzing' => __( 'Analyzing...', 'khm-seo' ),
                    'good'      => __( 'Good', 'khm-seo' ),
                    'needs_improvement' => __( 'Needs Improvement', 'khm-seo' ),
                    'poor'      => __( 'Poor', 'khm-seo' ),
                    'smmaGenerating' => __( 'Generating social variant...', 'khm-seo' ),
                    'smmaGenerated' => __( 'Variant ready for scheduling.', 'khm-seo' ),
                    'smmaScheduling' => __( 'Queuing boost workflow...', 'khm-seo' ),
                    'smmaScheduleReady' => __( 'Boost bundle prepared and awaiting manual export.', 'khm-seo' ),
                    'smmaApprovalQueued' => __( 'Queued for sponsor approval.', 'khm-seo' ),
                    'smmaSponsorRequired' => __( 'Boosting requires a sponsor policy on this post.', 'khm-seo' ),
                    'smmaMissingDeps' => __( 'KH Social Manager is unavailable on this environment.', 'khm-seo' ),
                    'smmaPromptEdit' => __( 'Edit the variant text before sending it back for compliance review:', 'khm-seo' ),
                    'smmaPromptReject' => __( 'Reason for rejecting this schedule (optional):', 'khm-seo' ),
                    'smmaPromptPreview' => __( 'Variant preview', 'khm-seo' ),
                    'smmaApproved' => __( 'Schedule approved.', 'khm-seo' ),
                    'smmaRejected' => __( 'Schedule rejected.', 'khm-seo' ),
                    'smmaUpdated' => __( 'Variant updated.', 'khm-seo' )
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
