<?php
declare( strict_types=1 );

namespace KH_SMMA\API;

use KH_SMMA\SponsorApproval\ApprovalPermissionService;
use KH_SMMA\Sponsor\ApprovalSafetyService;
use KH_SMMA\Scheduling\ScheduleRepository;
use KH_SMMA\Services\AuditLogger;
use WP_REST_Request;
use WP_Error;

use function add_action;
use function current_user_can;
use function do_action;
use function get_current_user_id;
use function is_wp_error;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function time;
use function wp_generate_uuid4;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SponsorApprovalController {
    private const NAMESPACE = 'kh-smma/v1';

    private ScheduleRepository $repository;
    private AuditLogger $logger;
    private ApprovalPermissionService $permissions;
    private ApprovalSafetyService $safety;

    public function __construct( ScheduleRepository $repository, AuditLogger $logger, ?ApprovalPermissionService $permissions = null, ?ApprovalSafetyService $safety = null ) {
        $this->repository = $repository;
        $this->logger     = $logger;
        $this->permissions = $permissions ?: new ApprovalPermissionService();
        $this->safety      = $safety ?: new ApprovalSafetyService( $repository, $logger );
    }

    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/sponsor-approvals', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'list_schedules' ),
                'permission_callback' => array( $this, 'can_access' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/sponsor-approvals/review-started', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'review_started' ),
                'permission_callback' => array( $this, 'can_access' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/sponsor-approvals/approve', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'approve_schedules' ),
                'permission_callback' => array( $this, 'can_access' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/sponsor-approvals/reject', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'reject_schedules' ),
                'permission_callback' => array( $this, 'can_access' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/sponsor-approvals/history', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'approval_history' ),
                'permission_callback' => array( $this, 'can_access' ),
            ),
        ) );
    }

    public function list_schedules( WP_REST_Request $request ) {
        $filters = array(
            'sponsor_id'  => sanitize_text_field( (string) $request->get_param( 'sponsor_id' ) ),
            'status'      => sanitize_text_field( (string) $request->get_param( 'status' ) ),
            'date_from'   => sanitize_text_field( (string) $request->get_param( 'date_from' ) ),
            'date_to'     => sanitize_text_field( (string) $request->get_param( 'date_to' ) ),
            'search_term' => sanitize_text_field( (string) $request->get_param( 'search_term' ) ),
            'page'        => (int) $request->get_param( 'page' ),
            'per_page'    => (int) $request->get_param( 'per_page' ),
        );

        $filters = $this->permissions->enforce_sponsor_scope( $filters, get_current_user_id() );

        $result = $this->repository->getPendingApprovals( $filters );
        $rows = array();
        foreach ( $result['rows'] as $row ) {
            $row = $this->safety->apply_re_review_if_needed( $row, get_current_user_id() );
            $row['can_approve'] = $this->permissions->can_approve_schedule( $row, get_current_user_id() );
            $row['permission_message'] = $row['can_approve']
                ? ''
                : $this->permissions->permission_denied_message();
            $rows[] = $row;
        }

        $can_manage = $this->permissions->can_manage_approvals( get_current_user_id() );

        return rest_ensure_response( array(
            'rows'        => $rows,
            'total'       => $result['total'],
            'page'        => $result['page'],
            'per_page'    => $result['per_page'],
            'total_pages' => $result['total_pages'],
                'sponsors'    => $this->repository->getSponsors( $rows ),
            'permissions' => array(
                'can_manage_approvals' => $can_manage,
                'denied_message'       => $this->permissions->permission_denied_message(),
            ),
        ) );
    }

    public function review_started( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $schedule_ids = $payload['schedule_ids'] ?? array();
        if ( ! is_array( $schedule_ids ) || empty( $schedule_ids ) ) {
            $single = (string) ( $payload['schedule_id'] ?? '' );
            if ( '' !== $single ) {
                $schedule_ids = array( $single );
            }
        }

        $reviewer = (int) ( $payload['reviewer_user_id'] ?? get_current_user_id() );
        $timestamp = time();
        $count = 0;

        foreach ( $schedule_ids as $schedule_id ) {
            $clean_id = sanitize_text_field( (string) $schedule_id );
            if ( '' === $clean_id ) {
                continue;
            }

            $event_payload = array(
                'schedule_id'       => $clean_id,
                'reviewer_user_id'  => $reviewer,
                'timestamp'         => $timestamp,
            );

            $this->logger->log( 'sponsor.approval.review_started', array(
                'object_type' => 'schedule',
                'object_id'   => (int) $clean_id,
                'details'     => $event_payload,
                'user_id'     => get_current_user_id(),
            ) );

            do_action( 'kh_smma_telemetry_event', 'sponsor.approval.review_started', $event_payload );
            $count++;
        }

        return rest_ensure_response( array(
            'status' => 'ok',
            'count'  => $count,
        ) );
    }

    public function approve_schedules( WP_REST_Request $request ) {
        return $this->persist_decision( $request, 'approve' );
    }

    public function reject_schedules( WP_REST_Request $request ) {
        return $this->persist_decision( $request, 'reject' );
    }

    public function approval_history( WP_REST_Request $request ) {
        $schedule_id = sanitize_text_field( (string) $request->get_param( 'schedule_id' ) );
        if ( '' === $schedule_id ) {
            return rest_ensure_response( array(
                'error' => 'MISSING_SCHEDULE_ID',
            ) );
        }

        $history = $this->repository->getApprovalHistory( $schedule_id );

        $trace_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'trace_', true );
        do_action( 'kh_smma_telemetry_event', 'sponsor.approval.history_viewed', array(
            'trace_id'       => $trace_id,
            'schedule_id'    => $schedule_id,
            'viewer_user_id' => get_current_user_id(),
            'timestamp'      => time(),
        ) );

        return rest_ensure_response( array(
            'schedule_id' => $schedule_id,
            'history'     => $history,
        ) );
    }

    /**
     * @return array|WP_Error
     */
    private function persist_decision( WP_REST_Request $request, string $action ) {
        $payload = $request->get_json_params();
        $schedule_ids = $payload['schedule_ids'] ?? array();
        if ( ! is_array( $schedule_ids ) || empty( $schedule_ids ) ) {
            $single = (string) ( $payload['schedule_id'] ?? '' );
            if ( '' !== $single ) {
                $schedule_ids = array( $single );
            }
        }

        if ( empty( $schedule_ids ) ) {
            return new WP_Error( 'missing_schedule_ids', 'At least one schedule_id is required.' );
        }

        $reviewer_id = (int) ( $payload['reviewer_user_id'] ?? get_current_user_id() );
        $notes = sanitize_text_field( (string) ( $payload['review_notes'] ?? '' ) );
        $incoming_trace = sanitize_text_field( (string) $request->get_header( 'X-Trace-Id' ) );
        $trace_id = '' !== $incoming_trace
            ? $incoming_trace
            : ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'trace_', true ) );
        $permission_denied = 0;

        $results = array();
        $errors  = array();

        foreach ( $schedule_ids as $schedule_id ) {
            $id = sanitize_text_field( (string) $schedule_id );
            if ( '' === $id ) {
                continue;
            }

            $schedule = $this->repository->getSchedule( $id );
            if ( empty( $schedule ) || ! $this->permissions->can_approve_schedule( $schedule, get_current_user_id() ) ) {
                $permission_denied++;
                $this->record_permission_denied( $trace_id, $id, get_current_user_id() );
                $errors[] = array(
                    'schedule_id' => $id,
                    'code'        => 'permission_denied',
                    'message'     => $this->permissions->permission_denied_message(),
                );
                continue;
            }

            if ( 'approve' === $action ) {
                $approval_error = $this->safety->ensure_approvable( $schedule, get_current_user_id() );
                if ( is_wp_error( $approval_error ) ) {
                    $errors[] = array(
                        'schedule_id' => $id,
                        'code'        => 'COMPLIANCE_FAIL_APPROVAL_BLOCKED',
                        'message'     => 'Schedules with compliance FAIL cannot be approved. Variant must be edited and pass compliance before approval.',
                    );
                    continue;
                }
            }

            $result = 'approve' === $action
                ? $this->repository->approveSchedule( $id, $reviewer_id, $notes, $trace_id )
                : $this->repository->rejectSchedule( $id, $reviewer_id, $notes, $trace_id );

            if ( is_wp_error( $result ) ) {
                $code = method_exists( $result, 'get_error_code' )
                    ? (string) $result->get_error_code()
                    : (string) $result->getCode();
                $message = method_exists( $result, 'get_error_message' )
                    ? (string) $result->get_error_message()
                    : (string) $result->getMessage();

                if ( '' === $code || '0' === $code ) {
                    $error_map = $result->errors ?? array();
                    if ( is_array( $error_map ) && ! empty( $error_map ) ) {
                        $first_key = array_key_first( $error_map );
                        if ( is_string( $first_key ) && '' !== $first_key ) {
                            $code = $first_key;
                        }
                    }
                }

                $errors[] = array(
                    'schedule_id' => $id,
                    'code'        => '' !== $code ? $code : 'decision_failed',
                    'message'     => '' !== $message ? $message : 'Failed to persist decision.',
                );
                continue;
            }

            $results[] = $result;
        }

        if ( empty( $results ) && ! empty( $errors ) ) {
            $permission_only = $permission_denied === count( $errors );
            $is_transition = 'invalid_transition' === $errors[0]['code'];
            $is_compliance_block = 'COMPLIANCE_FAIL_APPROVAL_BLOCKED' === $errors[0]['code'];
            return rest_ensure_response( array(
                'error'   => $permission_only
                    ? 'APPROVAL_PERMISSION_DENIED'
                    : ( $is_transition ? 'INVALID_APPROVAL_TRANSITION' : ( $is_compliance_block ? 'COMPLIANCE_FAIL_APPROVAL_BLOCKED' : 'APPROVAL_PERSISTENCE_FAILED' ) ),
                'message' => $errors[0]['message'],
                'errors'  => $errors,
                'status'  => $permission_only ? 403 : ( $is_transition ? 409 : ( $is_compliance_block ? 409 : 400 ) ),
            ) );
        }

        $response = array(
            'results' => $results,
            'errors'  => $errors,
            'count'   => count( $results ),
            'approved' => count( $results ),
            'skipped'  => count( $errors ),
        );

        if ( 1 === count( $results ) && empty( $errors ) ) {
            $response = array_merge( $response, $results[0] );
        }

        return rest_ensure_response( $response );
    }

    private function record_permission_denied( string $trace_id, string $schedule_id, int $user_id ): void {
        $event_time = time();
        $payload = array(
            'trace_id'    => $trace_id,
            'schedule_id' => $schedule_id,
            'user_id'     => $user_id,
            'timestamp'   => $event_time,
        );

        $this->logger->record_event( $trace_id, 'sponsor.approval.permission_denied', $event_time, $payload );
        do_action( 'kh_smma_telemetry_event', 'sponsor.approval.permission_denied', $payload );
    }

    public function can_access(): bool {
        if ( current_user_can( 'manage_sponsors' )
            || current_user_can( 'edit_schedules' )
            || current_user_can( 'administrator' )
            || current_user_can( 'manage_options' ) ) {
            return true;
        }

        $this->logger->log( 'unauthorized_admin_access', array(
            'object_type' => 'rest_endpoint',
            'details'     => array(
                'endpoint' => 'kh-smma/v1/sponsor-approvals',
                'user_id'  => get_current_user_id(),
            ),
        ) );

        return false;
    }
}
