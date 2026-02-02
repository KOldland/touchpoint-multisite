<?php
namespace KH_SMMA\API;

use KH_SMMA\Services\FeatureFlags;
use KH_SMMA\Services\SmmaGenerator;
use KH_SMMA\Services\AuditLogger;
use KH_SMMA\Services\PhaseEngine;
use KH_SMMA\Services\ScheduleQueueProcessor;
use KH_SMMA\Services\ComplianceValidator;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RestController {
    private FeatureFlags $flags;
    private SmmaGenerator $generator;
    private AuditLogger $logger;
    private ?PhaseEngine $phase_engine;
    private ?ComplianceValidator $compliance_validator;

    public function __construct( FeatureFlags $flags, SmmaGenerator $generator, AuditLogger $logger, PhaseEngine $phase_engine = null, ComplianceValidator $compliance_validator = null ) {
        $this->flags = $flags;
        $this->generator = $generator;
        $this->logger = $logger;
        $this->phase_engine = $phase_engine;
        $this->compliance_validator = $compliance_validator ?? new ComplianceValidator();
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

        register_rest_route( 'kh-smma/v1', '/variant-edit', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_variant_edit' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        register_rest_route( 'kh-smma/v1', '/reject', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_reject' ),
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

        $phase_context = $payload['phase_context'] ?? array();
        if ( empty( $phase_context ) ) {
            $phase_context = $this->resolve_phase_context( $payload );
        }

        $phase_tag = $payload['phase_tag'] ?? '';
        if ( empty( $phase_tag ) && ! empty( $phase_context['assigned_phase'] ) ) {
            $phase_tag = $phase_context['assigned_phase'];
        }
        if ( empty( $phase_tag ) ) {
            $phase_tag = 'Attention';
        }

        $result = $this->generator->generate( array(
            'post_id' => (int) $payload['post_id'],
            'blocks_json' => $payload['blocks_json'] ?? array(),
            'phase_tag' => $phase_tag,
            'num_variants' => $payload['num_variants'] ?? 1,
            'series' => (bool) ( $payload['series'] ?? false ),
            'tone' => $payload['tone'] ?? 'Authority',
            'geo_targets' => $payload['geo_targets'] ?? array(),
            'sponsor_context' => $payload['sponsor_context'] ?? array(),
            'user_controls' => $payload['user_controls'] ?? array(),
            'keywords' => $payload['keywords'] ?? array(),
            'intent_scores' => $payload['intent_scores'] ?? array(),
            'audience_presets' => $payload['audience_presets'] ?? array(),
            'phase_context' => $phase_context,
            'user_id' => (int) ( $payload['user_id'] ?? get_current_user_id() ),
        ) );

        $this->logger->log( 'smma_generate', array(
            'object_type' => 'post',
            'object_id' => (int) $payload['post_id'],
            'details' => array(
                'variants' => is_array( $result['variants'] ) ? count( $result['variants'] ) : 0,
                'phase_tag' => $phase_tag,
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
                'phase_tag' => $phase_tag,
            ),
        ) );

        return rest_ensure_response( array(
            'variants' => $result['variants'],
        ) );
    }

    private function resolve_phase_context( array $payload ): array {
        if ( ! $this->phase_engine ) {
            return array();
        }

        $user_id = (int) ( $payload['user_id'] ?? get_current_user_id() );
        if ( ! $user_id ) {
            return array();
        }

        $context = $this->phase_engine->get_user_phase( $user_id );
        return is_array( $context ) ? $context : array();
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

        // Determine if approval is required based on sponsor or boost settings
        $approval_required = false;
        if ( ! empty( $sponsor_context['approval_required'] ) ) {
            $approval_required = true;
        } elseif ( $boost && ! empty( $boost_settings['requires_approval'] ) ) {
            $approval_required = true;
        }

        $created = array();
        foreach ( $schedule as $item ) {
            $scheduled_at = isset( $item['scheduled_at'] ) ? (int) $item['scheduled_at'] : time();
            $variant_id   = sanitize_text_field( $item['variant_id'] ?? '' );
            $geo          = sanitize_text_field( $item['geo'] ?? '' );
            $variant_text = $item['text'] ?? '';

            $variant_payload = array(
                'post_id' => $post_id,
                'variant_id' => $variant_id,
                'channel' => 'linkedin',
                'geo' => $geo,
                'text' => $variant_text,
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

            // Determine initial approval and schedule status
            $approval_status = $approval_required ? 'pending' : 'auto_approved';
            $schedule_status = $approval_required ? 'awaiting_approval' : 'pending';

            update_post_meta( $schedule_id, '_kh_smma_payload', $variant_payload );
            update_post_meta( $schedule_id, '_kh_smma_scheduled_at', $scheduled_at );
            update_post_meta( $schedule_id, '_kh_smma_schedule_status', $schedule_status );
            update_post_meta( $schedule_id, '_kh_smma_delivery_mode', 'manual_export' );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_id', isset( $sponsor_context['sponsor_id'] ) ? (int) $sponsor_context['sponsor_id'] : 0 );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_mode', sanitize_text_field( $sponsor_context['policy'] ?? '' ) );
            update_post_meta( $schedule_id, '_kh_smma_sponsor_assets', $sponsor_context['sponsor_assets'] ?? array() );
            update_post_meta( $schedule_id, '_kh_smma_boost_mode', $boost ? 'linkedin' : 'none' );
            update_post_meta( $schedule_id, '_kh_smma_boost_settings', $boost_settings );
            update_post_meta( $schedule_id, '_kh_smma_approval_status', $approval_status );
            update_post_meta( $schedule_id, '_kh_smma_approval_required', $approval_required );

            if ( ! $approval_required ) {
                update_post_meta( $schedule_id, '_kh_smma_approved_by', 'system' );
                update_post_meta( $schedule_id, '_kh_smma_approved_at', time() );
            }

            ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
                'mode' => 'schedule',
                'provider' => 'smma',
                'payload_preview' => $variant_payload,
            ) );

            $created[] = array(
                'schedule_id' => $schedule_id,
                'schedule_status' => $schedule_status,
                'approval_status' => $approval_status,
                'approval_required' => $approval_required,
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

    public function handle_variant_edit( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $schedule_id = (int) ( $payload['schedule_id'] ?? 0 );
        $updated_text = $payload['updated_text'] ?? '';

        if ( ! $schedule_id || '' === $updated_text ) {
            return new WP_Error( 'kh_smma_invalid_edit', __( 'schedule_id and updated_text are required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $schedule_payload = get_post_meta( $schedule_id, '_kh_smma_payload', true );
        if ( empty( $schedule_payload ) ) {
            return new WP_Error( 'kh_smma_schedule_not_found', __( 'Schedule not found.', 'kh-smma' ), array( 'status' => 404 ) );
        }

        $sponsor_id = (int) get_post_meta( $schedule_id, '_kh_smma_sponsor_id', true );
        $sponsor_context = array(
            'sponsor_id' => $sponsor_id,
            'sponsor_policy' => get_post_meta( $schedule_id, '_kh_smma_sponsor_mode', true ),
            'sponsor_assets' => get_post_meta( $schedule_id, '_kh_smma_sponsor_assets', true ),
            'channel' => $schedule_payload['channel'] ?? 'linkedin',
            'phase_tag' => $schedule_payload['phase_tag'] ?? 'Attention',
        );

        // Get allowed claims for sponsor if applicable
        if ( $sponsor_id && function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            $sponsor_meta = kh_ad_manager_get_sponsor_meta( $sponsor_id );
            $sponsor_context['allowed_claims'] = $sponsor_meta['allowed_claims'] ?? array();
        }

        $compliance_check = $this->compliance_validator->validate( $updated_text, $sponsor_context );
        if ( ! $compliance_check['passed'] ) {
            return new WP_Error( 'kh_smma_compliance_failed', $compliance_check['message'], array( 'status' => 422 ) );
        }

        $schedule_payload['text'] = sanitize_textarea_field( $updated_text );
        $schedule_payload['edited_at'] = time();
        $schedule_payload['edited_by'] = get_current_user_id();

        update_post_meta( $schedule_id, '_kh_smma_payload', $schedule_payload );
        update_post_meta( $schedule_id, '_kh_smma_compliance_notes', $compliance_check['notes'] );

        $this->logger->log( 'smma_variant_edit', array(
            'object_type' => 'schedule',
            'object_id' => $schedule_id,
            'details' => array(
                'compliance_passed' => $compliance_check['passed'],
                'edited_by' => get_current_user_id(),
            ),
            'user_id' => get_current_user_id(),
        ) );

        return rest_ensure_response( array(
            'status' => 'updated',
            'compliance' => $compliance_check,
        ) );
    }

    public function handle_reject( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $schedule_id = (int) ( $payload['schedule_id'] ?? 0 );
        $reason = sanitize_textarea_field( $payload['reason'] ?? '' );

        if ( ! $schedule_id ) {
            return new WP_Error( 'kh_smma_missing_schedule', __( 'schedule_id is required.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        $current_status = get_post_meta( $schedule_id, '_kh_smma_approval_status', true );
        if ( 'rejected' === $current_status ) {
            return new WP_Error( 'kh_smma_already_rejected', __( 'This schedule is already rejected.', 'kh-smma' ), array( 'status' => 400 ) );
        }

        update_post_meta( $schedule_id, '_kh_smma_approval_status', 'rejected' );
        update_post_meta( $schedule_id, '_kh_smma_rejected_by', get_current_user_id() );
        update_post_meta( $schedule_id, '_kh_smma_rejected_at', time() );
        update_post_meta( $schedule_id, '_kh_smma_rejection_reason', $reason );
        update_post_meta( $schedule_id, '_kh_smma_schedule_status', 'rejected' );

        $this->logger->log( 'smma_schedule_reject', array(
            'object_type' => 'schedule',
            'object_id' => $schedule_id,
            'details' => array(
                'reason' => $reason,
                'rejected_by' => get_current_user_id(),
            ),
            'user_id' => get_current_user_id(),
        ) );

        ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'mode' => 'reject',
            'provider' => 'smma',
            'rejection_reason' => $reason,
        ) );

        return rest_ensure_response( array(
            'status' => 'rejected',
            'schedule_id' => $schedule_id,
        ) );
    }

}
