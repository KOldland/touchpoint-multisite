<?php
declare( strict_types=1 );

namespace KH_SMMA\Scheduling;

use KH_SMMA\Sponsor\ApprovalTelemetryService;
use KH_SMMA\Services\AuditLogger;
use WP_Error;
use WP_Query;
use wpdb;

use function absint;
use function current_time;
use function do_action;
use function get_post_meta;
use function get_userdata;
use function is_array;
use function is_wp_error;
use function maybe_unserialize;
use function sanitize_text_field;
use function strtotime;
use function trim;
use function update_post_meta;
use function wp_generate_uuid4;
use function wp_date;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScheduleRepository {
    private ?AuditLogger $logger;
    private ?wpdb $db;
    private ?ApprovalTelemetryService $telemetry;

    public function __construct( ?AuditLogger $logger = null, ?wpdb $db = null, ?ApprovalTelemetryService $telemetry = null ) {
        $this->logger = $logger;
        $this->db     = $db;
        $this->telemetry = $telemetry;
    }

    public function getPendingApprovals( array $filters = array(), ?array $fixture_rows = null ): array {
        $normalized = $this->normalize_filters( $filters );

        if ( is_array( $fixture_rows ) ) {
            return $this->from_fixture_rows( $fixture_rows, $normalized );
        }

        return $this->from_wp_query( $normalized );
    }

    public function getSponsors( ?array $fixture_rows = null ): array {
        $rows = $this->getPendingApprovals(
            array(
                'status'   => 'all',
                'per_page' => 500,
                'page'     => 1,
            ),
            $fixture_rows
        )['rows'];

        $sponsors = array();
        foreach ( $rows as $row ) {
            $id = (string) ( $row['sponsor_id'] ?? '' );
            if ( '' === $id ) {
                continue;
            }
            $sponsors[ $id ] = (string) ( $row['sponsor_name'] ?? $id );
        }

        asort( $sponsors );
        $options = array();
        foreach ( $sponsors as $id => $name ) {
            $options[] = array(
                'id'   => $id,
                'name' => $name,
            );
        }

        return $options;
    }

    /**
     * @return array|WP_Error
     */
    public function approveSchedule( string $schedule_id, int $reviewer_id, string $notes = '', ?string $trace_id = null ) {
        return $this->persistDecision( $schedule_id, 'approved', $reviewer_id, $notes, $trace_id );
    }

    /**
     * @return array|WP_Error
     */
    public function rejectSchedule( string $schedule_id, int $reviewer_id, string $notes = '', ?string $trace_id = null ) {
        return $this->persistDecision( $schedule_id, 'rejected', $reviewer_id, $notes, $trace_id );
    }

    public function pendingApprovalsCount(): int {
        $result = $this->getPendingApprovals( array(
            'status'   => 'pending',
            'page'     => 1,
            'per_page' => 1,
        ) );

        return (int) ( $result['total'] ?? 0 );
    }

    /**
     * @return array{reject_count:int,approval_count:int,reject_rate:float}
     */
    public function sponsorDecisionStats( string $sponsor_id, int $window = 10 ): array {
        $result = $this->getPendingApprovals( array(
            'sponsor_id' => sanitize_text_field( $sponsor_id ),
            'status'     => 'all',
            'page'       => 1,
            'per_page'   => max( 1, min( 50, $window ) ),
        ) );

        $decisions = array_values( array_filter( (array) ( $result['rows'] ?? array() ), static function ( array $row ): bool {
            $status = (string) ( $row['approval_status'] ?? '' );
            return 'approved' === $status || 'rejected' === $status;
        } ) );

        $approval_count = count( $decisions );
        $reject_count = count( array_filter( $decisions, static function ( array $row ): bool {
            return 'rejected' === (string) ( $row['approval_status'] ?? '' );
        } ) );

        return array(
            'reject_count'   => $reject_count,
            'approval_count' => $approval_count,
            'reject_rate'    => $approval_count > 0 ? ( $reject_count / $approval_count ) : 0.0,
        );
    }

    /**
     * Lightweight schedule export context for manual bundle generation.
     */
    public function getScheduleForExport( string $schedule_id, ?array $fixture_row = null ): array {
        $clean_id = sanitize_text_field( $schedule_id );
        if ( '' === $clean_id ) {
            return array();
        }

        if ( is_array( $fixture_row ) ) {
            return array(
                'schedule_id' => $clean_id,
                'variant_id' => (string) ( $fixture_row['variant_id'] ?? '' ),
                'approval_status' => $this->normalize_status( (string) ( $fixture_row['approval_status'] ?? 'pending' ) ),
                'compliance_status' => strtoupper( (string) ( $fixture_row['compliance_status'] ?? 'OK' ) ),
                'compliance_reason' => (string) ( $fixture_row['compliance_reason'] ?? '' ),
                'boost_options' => (array) ( $fixture_row['boost_options'] ?? array() ),
            );
        }

        return array(
            'schedule_id' => $clean_id,
            'variant_id' => (string) get_post_meta( (int) $clean_id, '_kh_smma_variant_id', true ),
            'approval_status' => $this->normalize_status( (string) get_post_meta( (int) $clean_id, '_kh_smma_approval_status', true ) ),
            'compliance_status' => strtoupper( (string) get_post_meta( (int) $clean_id, '_kh_smma_compliance_status', true ) ?: 'OK' ),
            'compliance_reason' => (string) get_post_meta( (int) $clean_id, '_kh_smma_compliance_reason', true ),
            'boost_options' => (array) maybe_unserialize( get_post_meta( (int) $clean_id, '_kh_smma_boost_settings', true ) ),
        );
    }

    /**
     * Retrieve approval history for a schedule (latest event first).
     *
     * @param string     $schedule_id
     * @param array|null $fixture_records Deterministic records for tests.
     * @return array<int, array<string,mixed>>
     */
    public function getApprovalHistory( string $schedule_id, ?array $fixture_records = null ): array {
        $clean_id = sanitize_text_field( $schedule_id );
        if ( '' === $clean_id ) {
            return array();
        }

        $records = is_array( $fixture_records )
            ? $fixture_records
            : $this->fetch_history_records( $clean_id );

        $events = array();
        foreach ( $records as $record ) {
            $event = $this->normalize_history_record( $record, $clean_id );
            if ( null === $event ) {
                continue;
            }
            $events[] = $event;
        }

        usort( $events, function ( array $left, array $right ): int {
            return strcmp( (string) $right['timestamp'], (string) $left['timestamp'] );
        } );

        return $events;
    }

    /**
     * Lookup schedule ownership and approval state for permission checks.
     *
     * @param string     $schedule_id
     * @param array|null $fixture_row
     * @return array<string,mixed>
     */
    public function getSchedule( string $schedule_id, ?array $fixture_row = null ): array {
        $clean_id = sanitize_text_field( $schedule_id );
        if ( '' === $clean_id ) {
            return array();
        }

        if ( is_array( $fixture_row ) ) {
            return array(
                'schedule_id'     => (string) ( $fixture_row['schedule_id'] ?? $clean_id ),
                'sponsor_id'      => (string) ( $fixture_row['sponsor_id'] ?? '' ),
                'approval_status' => $this->normalize_status( (string) ( $fixture_row['approval_status'] ?? 'pending' ) ),
                'approval_required' => ! empty( $fixture_row['approval_required'] ),
                'approval_reason'   => (string) ( $fixture_row['approval_reason'] ?? '' ),
                'compliance_status' => strtoupper( (string) ( $fixture_row['compliance_status'] ?? 'OK' ) ),
                'ruleset_version'   => (string) ( $fixture_row['ruleset_version'] ?? '' ),
                'last_approved_compliance_status' => strtoupper( (string) ( $fixture_row['last_approved_compliance_status'] ?? '' ) ),
                'last_approved_ruleset_version'   => (string) ( $fixture_row['last_approved_ruleset_version'] ?? '' ),
                'claims_used'       => $this->normalize_list( $fixture_row['claims_used'] ?? array() ),
                'last_approved_allowed_claims' => $this->normalize_list( $fixture_row['last_approved_allowed_claims'] ?? array() ),
            );
        }

        return array(
            'schedule_id'     => $clean_id,
            'sponsor_id'      => (string) get_post_meta( (int) $clean_id, '_kh_smma_sponsor_id', true ),
            'approval_status' => $this->normalize_status( (string) get_post_meta( (int) $clean_id, '_kh_smma_approval_status', true ) ),
            'approval_required' => (bool) get_post_meta( (int) $clean_id, '_kh_smma_approval_required', true ),
            'approval_reason'   => (string) get_post_meta( (int) $clean_id, '_kh_smma_approval_reason', true ),
            'compliance_status' => strtoupper( (string) get_post_meta( (int) $clean_id, '_kh_smma_compliance_status', true ) ?: 'OK' ),
            'ruleset_version'   => (string) get_post_meta( (int) $clean_id, '_kh_smma_compliance_ruleset_version', true ),
            'last_approved_compliance_status' => strtoupper( (string) get_post_meta( (int) $clean_id, '_kh_smma_last_approved_compliance_status', true ) ),
            'last_approved_ruleset_version'   => (string) get_post_meta( (int) $clean_id, '_kh_smma_last_approved_ruleset_version', true ),
            'claims_used'       => $this->normalize_list( get_post_meta( (int) $clean_id, '_kh_smma_claims_used', true ) ),
            'last_approved_allowed_claims' => $this->normalize_list( get_post_meta( (int) $clean_id, '_kh_smma_last_approved_allowed_claims', true ) ),
        );
    }

    /**
     * Find schedules that reference claims removed from sponsor allowed_claims.
     *
     * @param string     $sponsor_id
     * @param array      $removed_claims
     * @param array|null $fixture_rows
     * @return array<int,array<string,mixed>>
     */
    public function findSchedulesImpactedByClaimChange( string $sponsor_id, array $removed_claims, ?array $fixture_rows = null ): array {
        $sponsor_id = sanitize_text_field( $sponsor_id );
        $removed_claims = $this->normalize_list( $removed_claims );
        if ( '' === $sponsor_id || empty( $removed_claims ) ) {
            return array();
        }

        $rows = is_array( $fixture_rows )
            ? array_map( array( $this, 'normalize_fixture_row' ), $fixture_rows )
            : $this->getPendingApprovals( array(
                'sponsor_id' => $sponsor_id,
                'status'     => 'all',
                'per_page'   => 500,
                'page'       => 1,
            ) )['rows'];

        $impacted = array();
        foreach ( $rows as $row ) {
            if ( (string) ( $row['sponsor_id'] ?? '' ) !== $sponsor_id ) {
                continue;
            }

            $claims_used = $this->normalize_list( $row['claims_used'] ?? array() );
            if ( empty( array_intersect( $claims_used, $removed_claims ) ) ) {
                continue;
            }

            $impacted[] = array(
                'schedule_id'        => (string) ( $row['schedule_id'] ?? '' ),
                'sponsor_id'         => (string) ( $row['sponsor_id'] ?? '' ),
                'variant_id'         => (string) ( $row['variant_id'] ?? '' ),
                'approval_status'    => (string) ( $row['approval_status'] ?? 'pending' ),
                'compliance_status'  => strtoupper( (string) ( $row['compliance_status'] ?? 'OK' ) ),
            );
        }

        return $impacted;
    }

    /**
     * @return true|WP_Error
     */
    public function markScheduleForReReview( string $schedule_id, string $reason ): bool {
        $post_id = absint( $schedule_id );
        if ( $post_id <= 0 ) {
            return false;
        }

        $persisted = $this->write_meta_atomically( $post_id, array(
            '_kh_smma_approval_status'   => 'pending',
            '_kh_smma_approval_required' => 1,
            '_kh_smma_approval_reason'   => sanitize_text_field( $reason ),
        ) );

        return ! is_wp_error( $persisted );
    }

    private function from_wp_query( array $filters ): array {
        $args = array(
            'post_type'      => 'kh_smma_schedule',
            'post_status'    => 'publish',
            'posts_per_page' => $filters['per_page'],
            'paged'          => $filters['page'],
            'orderby'        => 'date',
            'order'          => 'DESC',
            's'              => $filters['search_term'],
            'meta_query'     => array(),
        );

        if ( 'all' !== $filters['status'] ) {
            $args['meta_query'][] = array(
                'key'     => '_kh_smma_approval_status',
                'value'   => $this->raw_status_values( $filters['status'] ),
                'compare' => 'IN',
            );
        }

        if ( '' !== $filters['sponsor_id'] ) {
            $args['meta_query'][] = array(
                'key'     => '_kh_smma_sponsor_id',
                'value'   => $filters['sponsor_id'],
                'compare' => '=',
            );
        }

        if ( '' !== $filters['date_from'] || '' !== $filters['date_to'] ) {
            $start = '' !== $filters['date_from'] ? (int) strtotime( $filters['date_from'] . ' 00:00:00' ) : 0;
            $end   = '' !== $filters['date_to'] ? (int) strtotime( $filters['date_to'] . ' 23:59:59' ) : time();
            if ( $end <= 0 ) {
                $end = time();
            }
            $args['meta_query'][] = array(
                'key'     => '_kh_smma_scheduled_at',
                'value'   => array( $start, $end ),
                'type'    => 'NUMERIC',
                'compare' => 'BETWEEN',
            );
        }

        $query = new WP_Query( $args );
        $rows  = array();

        foreach ( $query->posts as $post ) {
            $row = $this->map_wp_post( $post );

            if ( '' !== $filters['search_term'] ) {
                $needle = strtolower( $filters['search_term'] );
                $id_hit = strpos( strtolower( (string) $row['schedule_id'] ), $needle ) !== false;
                $title_hit = strpos( strtolower( (string) $row['post_title'] ), $needle ) !== false;
                if ( ! $id_hit && ! $title_hit ) {
                    continue;
                }
            }

            $rows[] = $row;
        }

        $total = isset( $query->found_posts ) ? (int) $query->found_posts : count( $rows );
        return array(
            'rows'        => array_values( $rows ),
            'total'       => $total,
            'page'        => $filters['page'],
            'per_page'    => $filters['per_page'],
            'total_pages' => max( 1, (int) ceil( $total / max( 1, $filters['per_page'] ) ) ),
        );
    }

    private function from_fixture_rows( array $fixture_rows, array $filters ): array {
        $rows = array_map( array( $this, 'normalize_fixture_row' ), $fixture_rows );

        $rows = array_values( array_filter( $rows, function ( array $row ) use ( $filters ) {
            if ( 'all' !== $filters['status'] && $row['approval_status'] !== $filters['status'] ) {
                return false;
            }

            if ( '' !== $filters['sponsor_id'] && (string) $row['sponsor_id'] !== (string) $filters['sponsor_id'] ) {
                return false;
            }

            if ( '' !== $filters['date_from'] || '' !== $filters['date_to'] ) {
                $ts = (int) strtotime( (string) $row['requested_schedule_date'] );
                if ( '' !== $filters['date_from'] ) {
                    $from = (int) strtotime( $filters['date_from'] . ' 00:00:00' );
                    if ( $ts < $from ) {
                        return false;
                    }
                }
                if ( '' !== $filters['date_to'] ) {
                    $to = (int) strtotime( $filters['date_to'] . ' 23:59:59' );
                    if ( $ts > $to ) {
                        return false;
                    }
                }
            }

            if ( '' !== $filters['search_term'] ) {
                $needle = strtolower( $filters['search_term'] );
                $id_hit = strpos( strtolower( (string) $row['schedule_id'] ), $needle ) !== false;
                $title_hit = strpos( strtolower( (string) $row['post_title'] ), $needle ) !== false;
                if ( ! $id_hit && ! $title_hit ) {
                    return false;
                }
            }

            return true;
        } ) );

        $total = count( $rows );
        $offset = ( $filters['page'] - 1 ) * $filters['per_page'];
        $paged_rows = array_slice( $rows, $offset, $filters['per_page'] );

        return array(
            'rows'        => array_values( $paged_rows ),
            'total'       => $total,
            'page'        => $filters['page'],
            'per_page'    => $filters['per_page'],
            'total_pages' => max( 1, (int) ceil( $total / max( 1, $filters['per_page'] ) ) ),
        );
    }

    private function normalize_fixture_row( array $row ): array {
        $status = $this->normalize_status( (string) ( $row['approval_status'] ?? 'pending' ) );
        return array(
            'schedule_id'             => (string) ( $row['schedule_id'] ?? '' ),
            'variant_id'              => (string) ( $row['variant_id'] ?? '' ),
            'post_title'              => (string) ( $row['post_title'] ?? '' ),
            'sponsor_id'              => (string) ( $row['sponsor_id'] ?? '' ),
            'sponsor_name'            => (string) ( $row['sponsor_name'] ?? '' ),
            'submitter'               => (string) ( $row['submitter'] ?? '' ),
            'requested_schedule_date' => (string) ( $row['requested_schedule_date'] ?? '' ),
            'approval_status'         => $status,
            'approval_reason'         => (string) ( $row['approval_reason'] ?? '' ),
            'last_approved_by'        => (string) ( $row['last_approved_by'] ?? '' ),
            'last_approved_at'        => (string) ( $row['last_approved_at'] ?? '' ),
            'approval_required'       => ! empty( $row['approval_required'] ),
            'compliance_status'       => strtoupper( (string) ( $row['compliance_status'] ?? 'OK' ) ),
            'ruleset_version'         => (string) ( $row['ruleset_version'] ?? '' ),
            'last_approved_compliance_status' => strtoupper( (string) ( $row['last_approved_compliance_status'] ?? '' ) ),
            'last_approved_ruleset_version'   => (string) ( $row['last_approved_ruleset_version'] ?? '' ),
            'claims_used'             => $this->normalize_list( $row['claims_used'] ?? array() ),
            'allowed_claims'          => $this->normalize_list( $row['allowed_claims'] ?? array() ),
            'last_approved_allowed_claims' => $this->normalize_list( $row['last_approved_allowed_claims'] ?? array() ),
            'queue_label'             => $this->queue_label_for_status( $status ),
        );
    }

    private function map_wp_post( $post ): array {
        $schedule_id = (string) $post->ID;
        $sponsor_id = (string) get_post_meta( $post->ID, '_kh_smma_sponsor_id', true );
        $sponsor_name = (string) get_post_meta( $post->ID, '_kh_smma_sponsor_name', true );
        $scheduled_at = (int) get_post_meta( $post->ID, '_kh_smma_scheduled_at', true );
        $raw_status = (string) get_post_meta( $post->ID, '_kh_smma_approval_status', true );

        if ( '' === $sponsor_name && '' !== $sponsor_id && function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            $meta = kh_ad_manager_get_sponsor_meta( (int) $sponsor_id );
            if ( is_array( $meta ) && ! empty( $meta['name'] ) ) {
                $sponsor_name = (string) $meta['name'];
            }
        }
        if ( '' === $sponsor_name ) {
            $sponsor_name = $sponsor_id ?: '—';
        }

        $submitter = 'Unknown';
        $user = get_userdata( (int) $post->post_author );
        if ( $user && ! empty( $user->display_name ) ) {
            $submitter = (string) $user->display_name;
        }

        $status = $this->normalize_status( $raw_status );
        return array(
            'schedule_id'             => $schedule_id,
            'variant_id'              => (string) get_post_meta( $post->ID, '_kh_smma_variant_id', true ),
            'post_title'              => (string) $post->post_title,
            'sponsor_id'              => $sponsor_id,
            'sponsor_name'            => $sponsor_name,
            'submitter'               => $submitter,
            'requested_schedule_date' => $scheduled_at ? wp_date( 'Y-m-d H:i', $scheduled_at ) : '—',
            'approval_status'         => $status,
            'approval_reason'         => (string) get_post_meta( $post->ID, '_kh_smma_approval_reason', true ),
            'last_approved_by'        => (string) get_post_meta( $post->ID, '_kh_smma_approved_by', true ),
            'last_approved_at'        => (string) get_post_meta( $post->ID, '_kh_smma_approved_at', true ),
            'approval_required'       => (bool) get_post_meta( $post->ID, '_kh_smma_approval_required', true ),
            'compliance_status'       => strtoupper( (string) get_post_meta( $post->ID, '_kh_smma_compliance_status', true ) ?: 'OK' ),
            'ruleset_version'         => (string) get_post_meta( $post->ID, '_kh_smma_compliance_ruleset_version', true ),
            'last_approved_compliance_status' => strtoupper( (string) get_post_meta( $post->ID, '_kh_smma_last_approved_compliance_status', true ) ),
            'last_approved_ruleset_version'   => (string) get_post_meta( $post->ID, '_kh_smma_last_approved_ruleset_version', true ),
            'claims_used'             => $this->normalize_list( get_post_meta( $post->ID, '_kh_smma_claims_used', true ) ),
            'allowed_claims'          => $this->normalize_list( get_post_meta( $post->ID, '_kh_smma_allowed_claims', true ) ),
            'last_approved_allowed_claims' => $this->normalize_list( get_post_meta( $post->ID, '_kh_smma_last_approved_allowed_claims', true ) ),
            'queue_label'             => $this->queue_label_for_status( $status ),
        );
    }

    private function normalize_filters( array $filters ): array {
        $status = sanitize_text_field( (string) ( $filters['status'] ?? 'pending' ) );
        if ( ! in_array( $status, array( 'pending', 'approved', 'rejected', 'all' ), true ) ) {
            $status = 'pending';
        }

        $page = max( 1, absint( $filters['page'] ?? 1 ) );
        $per_page = absint( $filters['per_page'] ?? 25 );
        if ( $per_page < 1 || $per_page > 100 ) {
            $per_page = 25;
        }

        return array(
            'sponsor_id' => sanitize_text_field( (string) ( $filters['sponsor_id'] ?? '' ) ),
            'status'     => $status,
            'date_from'  => sanitize_text_field( (string) ( $filters['date_from'] ?? '' ) ),
            'date_to'    => sanitize_text_field( (string) ( $filters['date_to'] ?? '' ) ),
            'search_term'=> trim( sanitize_text_field( (string) ( $filters['search_term'] ?? '' ) ) ),
            'page'       => $page,
            'per_page'   => $per_page,
        );
    }

    private function normalize_status( string $raw ): string {
        $value = strtolower( trim( $raw ) );
        if ( in_array( $value, array( 'approved', 'auto_approved' ), true ) ) {
            return 'approved';
        }
        if ( in_array( $value, array( 'rejected', 'denied' ), true ) ) {
            return 'rejected';
        }
        return 'pending';
    }

    private function raw_status_values( string $status ): array {
        if ( 'approved' === $status ) {
            return array( 'approved', 'auto_approved' );
        }
        if ( 'rejected' === $status ) {
            return array( 'rejected', 'denied' );
        }
        return array( 'pending', 'requested', 'pending_approval', '' );
    }

    private function queue_label_for_status( string $status ): string {
        if ( 'approved' === $status ) {
            return 'Ready';
        }
        if ( 'rejected' === $status ) {
            return 'Rejected';
        }
        return 'Awaiting Approval';
    }

    /**
     * @return array|WP_Error
     */
    private function persistDecision( string $schedule_id, string $target_status, int $reviewer_id, string $notes, ?string $trace_id = null ) {
        $post_id = absint( $schedule_id );
        if ( $post_id <= 0 ) {
            return new WP_Error( 'invalid_schedule_id', 'Invalid schedule_id.' );
        }

        $current_status = $this->normalize_status( (string) get_post_meta( $post_id, '_kh_smma_approval_status', true ) );
        if ( 'pending' !== $current_status ) {
            return $this->invalid_transition_error( $current_status, $target_status );
        }

        $trace_id  = ( is_string( $trace_id ) && '' !== $trace_id )
            ? sanitize_text_field( $trace_id )
            : ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'trace_', true ) );
        $timestamp = current_time( 'mysql' );
        $notes     = sanitize_text_field( $notes );
        $sponsor_id = sanitize_text_field( (string) get_post_meta( $post_id, '_kh_smma_sponsor_id', true ) );

        $updates = array(
            '_kh_smma_approval_status' => $target_status,
            '_kh_smma_review_notes'    => $notes,
        );

        if ( 'approved' === $target_status ) {
            $compliance = strtoupper( (string) get_post_meta( $post_id, '_kh_smma_compliance_status', true ) ?: 'OK' );
            $ruleset    = (string) get_post_meta( $post_id, '_kh_smma_compliance_ruleset_version', true );
            $sponsor_id = sanitize_text_field( (string) get_post_meta( $post_id, '_kh_smma_sponsor_id', true ) );
            $updates['_kh_smma_approved_by'] = $reviewer_id;
            $updates['_kh_smma_approved_at'] = $timestamp;
            $updates['_kh_smma_approval_reason'] = '';
            $updates['_kh_smma_approval_required'] = 0;
            $updates['_kh_smma_last_approved_compliance_status'] = $compliance;
            $updates['_kh_smma_last_approved_ruleset_version'] = $ruleset;
            $updates['_kh_smma_last_approved_allowed_claims'] = $this->normalize_list( $this->current_allowed_claims_for_sponsor( $sponsor_id ) );
        } else {
            $updates['_kh_smma_rejected_by'] = $reviewer_id;
            $updates['_kh_smma_rejected_at'] = $timestamp;
        }

        $persisted = $this->write_meta_atomically( $post_id, $updates );
        if ( is_wp_error( $persisted ) ) {
            return $persisted;
        }

        $event_name = 'approved' === $target_status
            ? 'sponsor.approval.approved'
            : 'sponsor.approval.rejected';

        $audit_payload = array(
            'event_name'    => $event_name,
            'trace_id'      => $trace_id,
            'schedule_id'   => (string) $post_id,
            'reviewer_id'   => $reviewer_id,
            'review_notes'  => $notes,
            'timestamp'     => $timestamp,
        );

        if ( $this->logger ) {
            $this->logger->log( $event_name, array(
                'object_type' => 'schedule',
                'object_id'   => $post_id,
                'user_id'     => $reviewer_id,
                'details'     => $audit_payload,
            ) );
        }

        do_action( 'kh_smma_telemetry_event', $event_name, array(
            'trace_id'      => $trace_id,
            'schedule_id'   => (string) $post_id,
            'sponsor_id'    => $sponsor_id,
            'reviewer_id'   => $reviewer_id,
            'timestamp'     => $timestamp,
        ) );

        $schedule_context = array(
            'schedule_id' => (string) $post_id,
            'sponsor_id'  => $sponsor_id,
            'approval_status' => $target_status,
        );

        if ( 'approved' === $target_status ) {
            $this->telemetry()->approval_approved( $schedule_context, $reviewer_id, $notes, $trace_id );
        } else {
            $this->telemetry()->approval_rejected( $schedule_context, $reviewer_id, $notes, $trace_id );
        }

        if ( 'approved' === $target_status ) {
            $decision = array(
                'status'       => 'approved',
                'schedule_id'  => (string) $post_id,
                'approved_by'  => (string) $reviewer_id,
                'approved_at'  => $timestamp,
                'trace_id'     => $trace_id,
                'reviewer_id'  => $reviewer_id,
                'review_notes' => $notes,
                'timestamp'    => $timestamp,
            );

            do_action( 'kh_smma_sponsor_approval_decision_persisted', $decision );

            return array(
                'status'      => 'approved',
                'schedule_id' => (string) $post_id,
                'approved_by' => (string) $reviewer_id,
                'approved_at' => $timestamp,
                'trace_id'    => $trace_id,
            );
        }

        $decision = array(
            'status'       => 'rejected',
            'schedule_id'  => (string) $post_id,
            'rejected_by'  => (string) $reviewer_id,
            'rejected_at'  => $timestamp,
            'trace_id'     => $trace_id,
            'reviewer_id'  => $reviewer_id,
            'review_notes' => $notes,
            'timestamp'    => $timestamp,
        );

        do_action( 'kh_smma_sponsor_approval_decision_persisted', $decision );

        return array(
            'status'      => 'rejected',
            'schedule_id' => (string) $post_id,
            'rejected_by' => (string) $reviewer_id,
            'rejected_at' => $timestamp,
            'trace_id'    => $trace_id,
        );
    }

    private function telemetry(): ApprovalTelemetryService {
        if ( $this->telemetry instanceof ApprovalTelemetryService ) {
            return $this->telemetry;
        }

        if ( $this->logger instanceof AuditLogger ) {
            $this->telemetry = new ApprovalTelemetryService( $this, $this->logger );
            return $this->telemetry;
        }

        $this->logger = new class extends AuditLogger {
            public function __construct() {}

            public function log( $action, array $context = array() ) {
                return;
            }

            public function record_event( string $trace_id, string $event_name, int $timestamp, array $payload ): void {
                return;
            }
        };
        $this->telemetry = new ApprovalTelemetryService( $this, $this->logger );
        return $this->telemetry;
    }

    /**
     * @return true|WP_Error
     */
    private function write_meta_atomically( int $post_id, array $updates ) {
        $keys = array_keys( $updates );
        $previous = array();
        foreach ( $keys as $key ) {
            $previous[ $key ] = get_post_meta( $post_id, $key, true );
        }

        $transaction_started = $this->begin_transaction();

        foreach ( $updates as $key => $value ) {
            if ( false === update_post_meta( $post_id, $key, $value ) ) {
                if ( $transaction_started ) {
                    $this->rollback_transaction();
                }
                foreach ( $previous as $prev_key => $prev_value ) {
                    update_post_meta( $post_id, $prev_key, $prev_value );
                }
                return new WP_Error( 'approval_persistence_failed', 'Failed to persist approval decision.' );
            }
        }

        if ( $transaction_started ) {
            $this->commit_transaction();
        }

        return true;
    }

    private function begin_transaction(): bool {
        global $wpdb;
        if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
            return false !== $wpdb->query( 'START TRANSACTION' );
        }
        return false;
    }

    private function commit_transaction(): void {
        global $wpdb;
        if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
            $wpdb->query( 'COMMIT' );
        }
    }

    private function rollback_transaction(): void {
        global $wpdb;
        if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
            $wpdb->query( 'ROLLBACK' );
        }
    }

    private function invalid_transition_error( string $current_status, string $target_status ): WP_Error {
        if ( 'approved' === $current_status && 'rejected' === $target_status ) {
            return new WP_Error(
                'invalid_transition',
                'Invalid transition: approved → rejected is not allowed.'
            );
        }

        if ( 'rejected' === $current_status && 'approved' === $target_status ) {
            return new WP_Error(
                'invalid_transition',
                'Invalid transition: rejected → approved requires manual reset.'
            );
        }

        return new WP_Error(
            'invalid_transition',
            sprintf( 'Invalid transition: %s → %s is not allowed.', $current_status, $target_status )
        );
    }

    /**
     * @param mixed $value
     * @return array<int,string>
     */
    private function normalize_list( $value ): array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) {
                $value = $decoded;
            } else {
                $maybe = maybe_unserialize( $value );
                $value = is_array( $maybe ) ? $maybe : array_filter( array_map( 'trim', explode( ',', $value ) ) );
            }
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        $normalized = array();
        foreach ( $value as $entry ) {
            $item = sanitize_text_field( (string) $entry );
            if ( '' !== $item ) {
                $normalized[] = $item;
            }
        }

        return array_values( array_unique( $normalized ) );
    }

    /**
     * @return array<int,string>
     */
    private function current_allowed_claims_for_sponsor( string $sponsor_id ): array {
        if ( '' === $sponsor_id ) {
            return array();
        }

        if ( function_exists( 'kh_ad_manager_get_sponsor_meta' ) ) {
            $meta = kh_ad_manager_get_sponsor_meta( (int) $sponsor_id );
            if ( is_array( $meta ) ) {
                return $this->normalize_list( $meta['allowed_claims'] ?? array() );
            }
        }

        return array();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function fetch_history_records( string $schedule_id ): array {
        $db = $this->db;
        if ( null === $db ) {
            global $wpdb;
            $db = $wpdb instanceof wpdb ? $wpdb : null;
        }

        if ( ! $db ) {
            return array();
        }

        $table = $db->prefix . 'kh_smma_audit_log';
        $query = $db->prepare(
            "SELECT action, object_id, details, created_at
             FROM {$table}
             WHERE object_type = %s
               AND object_id = %d
               AND action IN (%s, %s, %s)
             ORDER BY created_at DESC",
            'schedule',
            absint( $schedule_id ),
            'sponsor.approval.review_started',
            'sponsor.approval.approved',
            'sponsor.approval.rejected'
        );

        $rows = $db->get_results( $query, ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>|null
     */
    private function normalize_history_record( array $record, string $schedule_id ): ?array {
        $action = (string) ( $record['action'] ?? $record['event_name'] ?? '' );
        $details = $record['details'] ?? array();

        if ( is_string( $details ) ) {
            if ( function_exists( 'maybe_unserialize' ) ) {
                $details = maybe_unserialize( $details );
            } else {
                $unserialized = @unserialize( $details );
                $details = false !== $unserialized || 'b:0;' === $details ? $unserialized : $details;
            }
        }

        if ( ! is_array( $details ) ) {
            $details = array();
        }

        $record_schedule_id = (string) ( $details['schedule_id'] ?? $record['schedule_id'] ?? $record['object_id'] ?? '' );
        if ( '' !== $record_schedule_id && $record_schedule_id !== (string) absint( $schedule_id ) && $record_schedule_id !== $schedule_id ) {
            return null;
        }

        $event = 'submitted';
        if ( 'sponsor.approval.approved' === $action || 'approved' === $action ) {
            $event = 'approved';
        } elseif ( 'sponsor.approval.rejected' === $action || 'rejected' === $action ) {
            $event = 'rejected';
        } elseif ( 'sponsor.approval.review_started' === $action || 'review_started' === $action ) {
            $event = 'submitted';
        }

        return array(
            'event'       => $event,
            'action'      => $action,
            'trace_id'    => (string) ( $details['trace_id'] ?? '' ),
            'schedule_id' => '' !== $record_schedule_id ? $record_schedule_id : (string) $schedule_id,
            'reviewer_id' => (string) ( $details['reviewer_id'] ?? $details['reviewer_user_id'] ?? '' ),
            'timestamp'   => (string) ( $details['timestamp'] ?? $record['timestamp'] ?? $record['created_at'] ?? '' ),
            'notes'       => (string) ( $details['review_notes'] ?? $details['notes'] ?? '' ),
        );
    }
}
