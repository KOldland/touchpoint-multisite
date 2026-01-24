<?php
namespace KH_SMMA\API;

use KH_SMMA\Services\FeatureFlags;
use KH_SMMA\Services\SmmaGenerator;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\ScheduleQueueProcessor;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestController {
    private FeatureFlags $flags;
    private SmmaGenerator $generator;
    private AuditLogger $logger;

    public function __construct( FeatureFlags $flags, SmmaGenerator $generator, AuditLogger $logger ) {
        $this->flags = $flags;
        $this->generator = $generator;
        $this->logger = $logger;
    }

    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route( 'kh-smma/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_generate' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/schedule', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_schedule' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/boost/prepare', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_boost_prepare' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/approve', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_approve' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/sponsor/(?P<id>\\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'handle_get_sponsor' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/seo-table', array(
            'methods' => 'GET',
            'callback' => array( $this, 'handle_seo_table' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );
    }

    public function check_permissions( WP_REST_Request $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'kh_smma_forbidden', __( 'Insufficient permissions.', 'kh-smma' ), array( 'status' => 403 ) );
        }

        if ( ! $this->flags->is_enabled( 'smma' ) ) {
            return new WP_Error( 'kh_smma_disabled', __( 'SMMA feature is disabled by feature flag.', 'kh-smma' ), array( 'status' => 403 ) );
        }

        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'kh_smma_bad_nonce', __( 'Invalid REST nonce.', 'kh-smma' ), array( 'status' => 401 ) );
        }

        return true;
    }

    public function handle_generate( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        if ( empty( $payload['post_id'] ) ) {
            return new WP_Error( 'kh_smma_missing_post', __( 'post_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $result = $this->generator->generate( array(
            'post_id' => (int) $payload['post_id'],
            'blocks_json' => $payload['blocks_json'] ?? array(),
            'phase_tag' => $payload['phase_tag'] ?? 'Attention',
            'num_variants' => $payload['num_variants'] ?? 1,
            'series' => (bool) ( $payload['series'] ?? false ),
            'tone' => $payload['tone'] ?? 'Authority',
            'geo_targets' => $payload['geo_targets'] ?? array(),
            'sponsor_context' => $payload['sponsor_context'] ?? array(),
            'user_controls' => $payload['user_controls'] ?? array(),
            'keywords' => $payload['keywords'] ?? array(),
            'intent_scores' => $payload['intent_scores'] ?? array(),
            'audience_presets' => $payload['audience_presets'] ?? array(),
        ) );

        $this->logger->log( 'smma_generate', array(
            'object_type' => 'post',
            'object_id' => (int) $payload['post_id'],
            'details' => array(
                'variants' => is_array( $result['variants'] ) ? count( $result['variants'] ) : 0,
                'phase_tag' => $payload['phase_tag'] ?? 'Attention',
                'tone' => $payload['tone'] ?? 'Authority',
            ),
            'user_id' => get_current_user_id(),
        ) );

        ScheduleQueueProcessor::log_telemetry( (int) $payload['post_id'], array(
            'mode' => 'generate',
            'provider' => 'smma',
            'request' => array(
                'post_id' => (int) $payload['post_id'],
                'variants' => $payload['num_variants'] ?? 1,
                'phase_tag' => $payload['phase_tag'] ?? 'Attention',
            ),
        ) );

        return rest_ensure_response( array(
            'variants' => $result['variants'],
        ) );
    }

    public function handle_schedule( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $post_id = (int) ( $payload['post_id'] ?? 0 );
        $schedule = $payload['schedule'] ?? array();

        if ( ! $post_id || empty( $schedule ) || ! is_array( $schedule ) ) {
            return new WP_Error( 'kh_smma_invalid_schedule', __( 'post_id and schedule are required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $boost = ! empty( $payload['boost'] );
        $boost_settings = $payload['boost_settings'] ?? array();
        $sponsor_context = $payload['sponsor_context'] ?? array();

        $created = array();
        foreach ( $schedule as $item ) {
            $scheduled_at = isset( $item['scheduled_at'] ) ? (int) $item['scheduled_at'] : time();
            $variant_id   = sanitize_text_field( $item['variant_id'] ?? '' );
            $geo          = sanitize_text_field( $item['geo'] ?? '' );
            $variant_payload = array(
                'post_id' => $post_id,
                'variant_id' => $variant_id,
                'channel' => 'linkedin',
                'geo' => $geo,
                'meta' => array(
                    'source' => 'smma_rest',
                    'generated_by' => 'smma-ai-v1',
                ),
            );

            $schedule_id = wp_insert_post( array(
                'post_type' => 'kh_smma_schedule',
                'post_title' => sprintf( __( 'SMMA Schedule – %d', 'kh-smma' ), $post_id ),
                'post_status' => 'publish',
            ), true );

            if ( is_wp_error( $schedule_id ) ) {
                return $schedule_id;
            }

            update_post_meta( $schedule_id, '_kh_smma_payload', $variant_payload );
            update_post_meta( $schedule_id, '_kh_smma_scheduled_at', $scheduled_at );
            update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'pending' );
            update_post_meta( $schedule_id, '_kh_smma_delivery_mode', 'manual_export' );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_id', isset( $sponsor_context['sponsor_id'] ) ? (int) $sponsor_context['sponsor_id'] : 0 );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_mode', sanitize_text_field( $sponsor_context['policy'] ?? '' ) );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_assets', $sponsor_context['sponsor_assets'] ?? array() );
            update_post_meta( $schedule_id, '_kh_smma_boost_mode', $boost ? 'linkedin' : 'none' );
            update_post_meta( $schedule_id, '_kh_smma_boost_settings', $boost_settings );
            update_post_meta( $schedule_id, '_kh_smma_approval_status', 'pending' );

            ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
                'mode' => 'schedule',
                'provider' => 'smma',
                'payload_preview' => $variant_payload,
            ) );

            $created[] = array(
                'schedule_id' => $schedule_id,
                'status' => 'pending',
            );
        }

        return rest_ensure_response( array(
            'created' => $created,
        ) );
    }

    public function handle_boost_prepare( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $schedule_id = (int) ( $payload['schedule_id'] ?? 0 );

        if ( ! $schedule_id ) {
            return new WP_Error( 'kh_smma_missing_schedule', __( 'schedule_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $schedule_payload = get_post_meta( $schedule_id, '_kh_smma_payload', true );
        $export_bundle = array(
            'schedule_id' => $schedule_id,
            'account_id' => 0,
            'payload' => $schedule_payload,
            'generated' => time(),
        );

        update_post_meta( $schedule_id, '_kh_smma_export_bundle', $export_bundle );
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'awaiting_manual_export' );

        return rest_ensure_response( array(
            'status' => 'awaiting_manual_export',
            'bundle' => $export_bundle,
        ) );
    }

    public function handle_approve( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $schedule_id = (int) ( $payload['schedule_id'] ?? 0 );

        if ( ! $schedule_id ) {
            return new WP_Error( 'kh_smma_missing_schedule', __( 'schedule_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        update_post_meta( $schedule_id, '_kh_smma_approval_status', 'approved' );
        update_post_meta( $schedule_id, '_kh_smma_approved_by', get_current_user_id() );
        update_post_meta( $schedule_id, '_kh_smma_approved_at', time() );

        return rest_ensure_response( array( 'status' => 'approved' ) );
    }

    public function handle_get_sponsor( WP_REST_Request $request ) {
        $id = (int) $request->get_param( 'id' );
        if ( ! $id ) {
            return new WP_Error( 'kh_smma_missing_sponsor', __( 'Sponsor ID is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        if ( ! function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            return new WP_Error( 'kh_smma_sponsor_missing', __( 'Sponsorship Manager not available.', 'kh-smma' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( kh_ad_manager_get_sponsor_meta( $id ) );
    }

    public function handle_seo_table( WP_REST_Request $request ) {
        $posts = get_posts( array(
            'post_type' => array( 'post', 'page' ),
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        $rows = array();
        foreach ( $posts as $post ) {
            $rows[] = array(
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'seo_score' => (int) get_post_meta( $post->ID, '_khm_seo_score', true ),
                'geo_score' => (int) get_post_meta( $post->ID, '_khm_geo_score', true ),
                'sponsor' => '',
                'promote' => true,
                'boost' => true,
            );
        }

        return rest_ensure_response( array( 'rows' => $rows ) );
    }
}
