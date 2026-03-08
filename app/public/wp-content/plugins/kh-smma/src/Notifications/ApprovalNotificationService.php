<?php
declare( strict_types=1 );

namespace KH_SMMA\Notifications;

use KH_SMMA\Services\AuditLogger;

use function add_action;
use function do_action;
use function get_option;
use function get_post_meta;
use function get_userdata;
use function is_array;
use function is_string;
use function sanitize_text_field;
use function time;
use function update_option;
use function update_post_meta;
use function wp_mail;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ApprovalNotificationService {
    private AuditLogger $logger;

    public function __construct( AuditLogger $logger ) {
        $this->logger = $logger;
    }

    public function register(): void {
        add_action( 'kh_smma_sponsor_approval_decision_persisted', array( $this, 'handle_decision_event' ), 10, 2 );
    }

    /**
     * @param mixed $unused
     * @param mixed $decision
     */
    public function handle_decision_event( $unused = null, $decision = null ): void {
        if ( is_array( $unused ) && ! is_array( $decision ) ) {
            $this->handle_decision( $unused );
            return;
        }

        if ( is_array( $decision ) ) {
            $this->handle_decision( $decision );
        }
    }

    /**
     * @param array<string,mixed> $decision
     */
    public function handle_decision( array $decision ): void {
        $status = strtolower( sanitize_text_field( (string) ( $decision['status'] ?? '' ) ) );
        if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) {
            return;
        }

        $schedule_id = sanitize_text_field( (string) ( $decision['schedule_id'] ?? '' ) );
        if ( '' === $schedule_id ) {
            return;
        }

        $trace_id   = sanitize_text_field( (string) ( $decision['trace_id'] ?? '' ) );
        $reviewer_id = (int) ( $decision['reviewer_id'] ?? 0 );
        $timestamp  = sanitize_text_field( (string) ( $decision['timestamp'] ?? '' ) );
        $event_time = time();

        $guard_key = $this->idempotency_key( $schedule_id, $status, $trace_id );
        if ( (bool) get_option( $guard_key, false ) ) {
            return;
        }

        $review_notes = sanitize_text_field( (string) ( $decision['review_notes'] ?? '' ) );
        $reviewer_label = $this->reviewer_label( $reviewer_id );

        $recipients = $this->resolve_recipients( $schedule_id, $status );
        foreach ( $recipients as $recipient ) {
            $recipient_type = (string) $recipient['recipient_type'];
            $recipient_email = (string) ( $recipient['email'] ?? '' );

            $this->store_in_app_notification( $schedule_id, array(
                'schedule_id' => $schedule_id,
                'decision'    => $status,
                'reviewer_id' => (string) $reviewer_id,
                'timestamp'   => $timestamp,
                'recipient_type' => $recipient_type,
                'message'     => $this->in_app_message( $schedule_id, $status, $reviewer_label ),
            ) );

            if ( '' !== $recipient_email ) {
                $email = $this->build_email( $status, $schedule_id, $reviewer_label, $timestamp, $review_notes );
                wp_mail( $recipient_email, $email['subject'], $email['body'] );
            }

            $telemetry_event = 'approved' === $status
                ? 'sponsor.notification.approval_sent'
                : 'sponsor.notification.rejection_sent';

            do_action( 'kh_smma_telemetry_event', $telemetry_event, array(
                'trace_id'       => $trace_id,
                'schedule_id'    => $schedule_id,
                'recipient_type' => $recipient_type,
                'timestamp'      => $event_time,
            ) );

            $this->logger->log( 'sponsor.notification.sent', array(
                'object_type' => 'schedule',
                'object_id'   => (int) $schedule_id,
                'details'     => array(
                    'event_name'         => 'sponsor.notification.sent',
                    'schedule_id'        => $schedule_id,
                    'notification_type'  => $status,
                    'recipient_type'     => $recipient_type,
                    'timestamp'          => $event_time,
                    'trace_id'           => $trace_id,
                ),
            ) );
        }

        update_option( $guard_key, 1 );
    }

    /**
     * @param array<string,mixed> $decision
     * @return array{subject:string,body:string}
     */
    public function build_email( string $status, string $schedule_id, string $reviewer, string $timestamp, string $review_notes ): array {
        if ( 'approved' === $status ) {
            return array(
                'subject' => 'Schedule Approved',
                'body'    => "Your scheduled campaign has been approved.\n\nSchedule ID: {$schedule_id}\nReviewer: {$reviewer}\nApproved At: {$timestamp}\n\nThe campaign is now eligible for dispatch.",
            );
        }

        return array(
            'subject' => 'Schedule Rejected',
            'body'    => "Your scheduled campaign has been rejected.\n\nSchedule ID: {$schedule_id}\nReviewer: {$reviewer}\nReason: {$review_notes}\n\nPlease revise and resubmit.",
        );
    }

    /**
     * @return array<int,array{recipient_type:string,user_id:int,email:string}>
     */
    private function resolve_recipients( string $schedule_id, string $status ): array {
        $owner_id = (int) get_post_meta( (int) $schedule_id, '_kh_smma_created_by', true );
        $editor_id = (int) get_post_meta( (int) $schedule_id, '_kh_smma_editor_user_id', true );

        if ( $editor_id <= 0 ) {
            $editor_id = $owner_id;
        }

        $recipients = array();

        if ( $owner_id > 0 ) {
            $recipients[] = array(
                'recipient_type' => 'owner',
                'user_id'        => $owner_id,
                'email'          => $this->user_email( $owner_id ),
            );
        }

        if ( $editor_id > 0 ) {
            $recipients[] = array(
                'recipient_type' => 'editor',
                'user_id'        => $editor_id,
                'email'          => $this->user_email( $editor_id ),
            );
        }

        $sponsor_email = sanitize_text_field( (string) get_post_meta( (int) $schedule_id, '_kh_smma_sponsor_contact_email', true ) );
        if ( '' === $sponsor_email ) {
            $assets = get_post_meta( (int) $schedule_id, '_kh_smma_sponsor_assets', true );
            if ( is_array( $assets ) ) {
                $sponsor_email = sanitize_text_field( (string) ( $assets['contact_email'] ?? $assets['email'] ?? '' ) );
            }
        }

        if ( '' !== $sponsor_email && 'approved' === $status ) {
            $recipients[] = array(
                'recipient_type' => 'sponsor_contact',
                'user_id'        => 0,
                'email'          => $sponsor_email,
            );
        }

        if ( '' !== $sponsor_email && 'rejected' === $status ) {
            $notify_on_rejection = (bool) get_post_meta( (int) $schedule_id, '_kh_smma_notify_sponsor_on_rejection', true );
            if ( $notify_on_rejection ) {
                $recipients[] = array(
                    'recipient_type' => 'sponsor_contact',
                    'user_id'        => 0,
                    'email'          => $sponsor_email,
                );
            }
        }

        return $this->unique_recipients( $recipients );
    }

    private function store_in_app_notification( string $schedule_id, array $notification ): void {
        $existing = get_post_meta( (int) $schedule_id, '_kh_smma_in_app_notifications', true );
        if ( ! is_array( $existing ) ) {
            $existing = array();
        }

        $existing[] = $notification;
        if ( count( $existing ) > 50 ) {
            $existing = array_slice( $existing, -50 );
        }

        update_post_meta( (int) $schedule_id, '_kh_smma_in_app_notifications', $existing );
    }

    private function in_app_message( string $schedule_id, string $status, string $reviewer ): string {
        if ( 'approved' === $status ) {
            return "Schedule #{$schedule_id} approved by {$reviewer}";
        }

        return "Schedule #{$schedule_id} rejected — review required";
    }

    private function reviewer_label( int $reviewer_id ): string {
        if ( $reviewer_id <= 0 ) {
            return 'Unknown reviewer';
        }

        if ( function_exists( 'get_userdata' ) ) {
            $user = get_userdata( $reviewer_id );
            if ( $user && isset( $user->display_name ) && is_string( $user->display_name ) && '' !== $user->display_name ) {
                return $user->display_name;
            }
        }

        return 'User ' . $reviewer_id;
    }

    private function user_email( int $user_id ): string {
        if ( $user_id <= 0 || ! function_exists( 'get_userdata' ) ) {
            return '';
        }

        $user = get_userdata( $user_id );
        if ( ! $user || ! isset( $user->user_email ) || ! is_string( $user->user_email ) ) {
            return '';
        }

        return sanitize_text_field( $user->user_email );
    }

    /**
     * @param array<int,array{recipient_type:string,user_id:int,email:string}> $recipients
     * @return array<int,array{recipient_type:string,user_id:int,email:string}>
     */
    private function unique_recipients( array $recipients ): array {
        $unique = array();
        $seen   = array();

        foreach ( $recipients as $recipient ) {
            $key = (string) $recipient['recipient_type'] . '|' . (string) $recipient['user_id'] . '|' . (string) $recipient['email'];
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $unique[] = $recipient;
        }

        return $unique;
    }

    private function idempotency_key( string $schedule_id, string $status, string $trace_id ): string {
        return 'kh_smma_notif_guard_' . md5( $schedule_id . '|' . $status . '|' . $trace_id );
    }
}
