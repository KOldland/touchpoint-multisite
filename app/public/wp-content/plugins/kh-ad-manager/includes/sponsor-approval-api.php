<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register sponsor approval API endpoints.
 * 
 * Endpoints:
 * - POST /wp-json/kh-ad-manager/v1/sponsor-approve
 * - GET  /wp-json/kh-ad-manager/v1/sponsor-approvals/pending
 * - GET  /wp-json/kh-ad-manager/v1/sponsor-approvals/{schedule_id}
 */

add_action( 'rest_api_init', function() {
    register_rest_route( 'kh-ad-manager/v1', '/sponsor-approve', array(
        'methods'             => 'POST',
        'callback'            => 'kh_ad_manager_rest_sponsor_approve',
        'permission_callback' => 'kh_ad_manager_rest_approve_permission',
    ) );

    register_rest_route( 'kh-ad-manager/v1', '/sponsor-approvals/pending', array(
        'methods'             => 'GET',
        'callback'            => 'kh_ad_manager_rest_get_pending_approvals',
        'permission_callback' => 'kh_ad_manager_rest_approve_permission',
    ) );

    register_rest_route( 'kh-ad-manager/v1', '/sponsor-approvals/(?P<schedule_id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'kh_ad_manager_rest_get_approval_status',
        'permission_callback' => 'kh_ad_manager_rest_approve_permission',
    ) );
} );

/**
 * Permission callback: Require manage_options capability for sponsor approvals
 */
function kh_ad_manager_rest_approve_permission() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return new \WP_Error(
            'kh_ad_manager_forbidden',
            __( 'Insufficient permissions. Sponsor approvals require administrator privileges.', 'kh-ad-manager' ),
            array( 'status' => 403 )
        );
    }
    return true;
}

/**
 * POST /wp-json/kh-ad-manager/v1/sponsor-approve
 * 
 * Body:
 * {
 *   "schedule_id": 123,
 *   "sponsor_id": 456,
 *   "approver_id": 789,
 *   "notes": "Approved as-is",
 *   "decision": "approved" | "rejected"
 * }
 * 
 * Sets:
 * - _kh_smma_sponsor_approval_status
 * - _kh_smma_sponsor_approved_by
 * - _kh_smma_sponsor_approved_at
 * - _kh_smma_sponsor_approval_notes
 */
function kh_ad_manager_rest_sponsor_approve( \WP_REST_Request $request ) {
    $payload      = $request->get_json_params();
    $schedule_id  = absint( $payload['schedule_id'] ?? 0 );
    $sponsor_id   = absint( $payload['sponsor_id'] ?? 0 );
    $approver_id  = absint( get_current_user_id() );
    $notes        = sanitize_textarea_field( $payload['notes'] ?? '' );
    $decision     = sanitize_key( $payload['decision'] ?? 'approved' );

    if ( ! $schedule_id ) {
        return new \WP_Error(
            'kh_ad_manager_missing_schedule',
            __( 'schedule_id is required.', 'kh-ad-manager' ),
            array( 'status' => 400 )
        );
    }

    $schedule = get_post( $schedule_id );
    if ( ! $schedule || 'kh_smma_schedule' !== $schedule->post_type ) {
        return new \WP_Error(
            'kh_ad_manager_schedule_not_found',
            __( 'Schedule not found.', 'kh-ad-manager' ),
            array( 'status' => 404 )
        );
    }

    // Validate decision
    if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
        return new \WP_Error(
            'kh_ad_manager_invalid_decision',
            __( 'Decision must be "approved" or "rejected".', 'kh-ad-manager' ),
            array( 'status' => 400 )
        );
    }

    // If sponsor_id is provided, validate it
    if ( $sponsor_id ) {
        $sponsor = get_post( $sponsor_id );
        if ( ! $sponsor || 'kh_sponsor' !== $sponsor->post_type ) {
            return new \WP_Error(
                'kh_ad_manager_sponsor_not_found',
                __( 'Sponsor not found.', 'kh-ad-manager' ),
                array( 'status' => 404 )
            );
        }
    } else {
        // Try to get sponsor_id from schedule meta
        $sponsor_id = (int) get_post_meta( $schedule_id, '_kh_smma_sponsor_id', true );
    }

    // Record approval
    update_post_meta( $schedule_id, '_kh_smma_sponsor_approval_status', $decision );
    update_post_meta( $schedule_id, '_kh_smma_sponsor_approved_by', $approver_id );
    update_post_meta( $schedule_id, '_kh_smma_sponsor_approved_at', time() );
    update_post_meta( $schedule_id, '_kh_smma_sponsor_approval_notes', $notes );

    if ( $sponsor_id ) {
        update_post_meta( $schedule_id, '_kh_smma_sponsor_id', $sponsor_id );
    }

    // Log telemetry
    if ( is_callable( array( 'KH_SMMA\\Services\\ScheduleQueueProcessor', 'log_telemetry' ) ) ) {
        \KH_SMMA\Services\ScheduleQueueProcessor::log_telemetry( $schedule_id, array(
            'event'                 => 'sponsor_approval',
            'decision'              => $decision,
            'sponsor_id'            => $sponsor_id,
            'approver_id'           => $approver_id,
            'notes'                 => $notes,
        ) );
    }

    // If approved, transition schedule status if needed
    if ( 'approved' === $decision ) {
        $approval_status = get_post_meta( $schedule_id, '_kh_smma_approval_status', true );
        if ( 'pending' === $approval_status || 'requested' === $approval_status ) {
            // Sponsor approved; allow schedule to move forward if SMMA also approves
            update_post_meta( $schedule_id, '_kh_smma_sponsor_approval_status', 'approved' );
        }
    }

    return \rest_ensure_response( array(
        'success'  => true,
        'schedule_id' => $schedule_id,
        'sponsor_id'  => $sponsor_id,
        'decision'    => $decision,
        'approved_at' => time(),
        'approver_id' => $approver_id,
    ) );
}

/**
 * GET /wp-json/kh-ad-manager/v1/sponsor-approvals/pending
 * 
 * Returns list of schedules pending sponsor approval.
 */
function kh_ad_manager_rest_get_pending_approvals( \WP_REST_Request $request ) {
    $args = array(
        'post_type'      => 'kh_smma_schedule',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'paged'          => absint( $request->get_param( 'paged' ) ?? 1 ),
        'meta_query'     => array(
            'relation' => 'OR',
            array(
                'key'   => '_kh_smma_sponsor_approval_status',
                'value' => 'pending',
            ),
            array(
                'key'     => '_kh_smma_sponsor_id',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ),
        ),
    );

    $query = new \WP_Query( $args );
    $pending = array();

    foreach ( $query->posts as $schedule ) {
        $sponsor_id = (int) get_post_meta( $schedule->ID, '_kh_smma_sponsor_id', true );
        $approval_status = get_post_meta( $schedule->ID, '_kh_smma_sponsor_approval_status', true );
        $payload = get_post_meta( $schedule->ID, '_kh_smma_payload', true );

        $pending[] = array(
            'schedule_id'         => $schedule->ID,
            'title'               => $schedule->post_title,
            'sponsor_id'          => $sponsor_id,
            'approval_status'     => $approval_status ?: 'pending',
            'variant_text'        => is_array( $payload ) ? $payload['message'] ?? $payload['text'] ?? '' : '',
            'scheduled_at'        => get_post_meta( $schedule->ID, '_kh_smma_scheduled_at', true ),
            'created_at'          => $schedule->post_date_gmt,
        );
    }

    return \rest_ensure_response( array(
        'pending'       => $pending,
        'total'         => (int) $query->found_posts,
        'total_pages'   => (int) $query->max_num_pages,
    ) );
}

/**
 * GET /wp-json/kh-ad-manager/v1/sponsor-approvals/{schedule_id}
 * 
 * Returns approval status and metadata for a specific schedule.
 */
function kh_ad_manager_rest_get_approval_status( \WP_REST_Request $request ) {
    $schedule_id = absint( $request->get_param( 'schedule_id' ) );

    if ( ! $schedule_id ) {
        return new \WP_Error(
            'kh_ad_manager_missing_schedule',
            __( 'schedule_id is required.', 'kh-ad-manager' ),
            array( 'status' => 400 )
        );
    }

    $schedule = get_post( $schedule_id );
    if ( ! $schedule || 'kh_smma_schedule' !== $schedule->post_type ) {
        return new \WP_Error(
            'kh_ad_manager_schedule_not_found',
            __( 'Schedule not found.', 'kh-ad-manager' ),
            array( 'status' => 404 )
        );
    }

    $sponsor_id = (int) get_post_meta( $schedule_id, '_kh_smma_sponsor_id', true );
    $approval_status = get_post_meta( $schedule_id, '_kh_smma_sponsor_approval_status', true );
    $approved_by = get_post_meta( $schedule_id, '_kh_smma_sponsor_approved_by', true );
    $approved_at = get_post_meta( $schedule_id, '_kh_smma_sponsor_approved_at', true );
    $notes = get_post_meta( $schedule_id, '_kh_smma_sponsor_approval_notes', true );

    return \rest_ensure_response( array(
        'schedule_id'         => $schedule_id,
        'sponsor_id'          => $sponsor_id,
        'approval_status'     => $approval_status ?: 'pending',
        'approved_by'         => $approved_by,
        'approved_at'         => $approved_at,
        'approval_notes'      => $notes,
        'smma_approval_status'=> get_post_meta( $schedule_id, '_kh_smma_approval_status', true ),
        'variant_text'        => get_post_meta( $schedule_id, '_kh_smma_payload', true ),
    ) );
}
