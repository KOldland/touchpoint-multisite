<?php
declare( strict_types=1 );

namespace KH_SMMA\Notifications;

use KH_SMMA\Services\AuditLogger;

use function add_action;
use function do_action;
use function get_userdata;
use function gmdate;
use function is_email;
use function sanitize_text_field;
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
        add_action( 'kh_smma_sponsor_approval_decision_persisted', array( $this, 'handle_decision' ), 10, 2 );
    }

    public function handle_decision( $unused, ?array $decision = null ): void {
        if ( ! is_array( $decision ) ) {
            return;
        }

        $schedule_id = sanitize_text_field( (string) ( $decision['schedule_id'] ?? '' ) );
        $status      = sanitize_text_field( (string) ( $decision['status'] ?? '' ) );
        $reviewer_id = (int) ( $decision['reviewer_id'] ?? 0 );
        $notes       = sanitize_text_field( (string) ( $decision['review_notes'] ?? '' ) );
        $trace_id    = sanitize_text_field( (string) ( $decision['trace_id'] ?? '' ) );
        $timestamp   = (int) ( $decision['timestamp'] ?? time() );

        if ( '' === $schedule_id || '' === $status ) {
            return;
        }

        $recipients = $this->collect_recipients( (int) $schedule_id );
        foreach ( $recipients as $recipient ) {
            $subject = 'approved' === $status
                ? sprintf( 'Schedule %s approved', $schedule_id )
                : sprintf( 'Schedule %s rejected', $schedule_id );
            $message = 'approved' === $status
                ? sprintf( 'Schedule %s was approved. Notes: %s', $schedule_id, '' !== $notes ? $notes : 'None provided' )
                : sprintf( 'Schedule %s was rejected. Notes: %s', $schedule_id, '' !== $notes ? $notes : 'None provided' );

            wp_mail( $recipient['email'], $subject, $message );

            $payload = array(
                'trace_id'      => $trace_id,
                'schedule_id'   => $schedule_id,
                'recipient'     => $recipient['email'],
                'recipient_id'  => $recipient['id'],
                'reviewer_id'   => $reviewer_id,
                'review_notes'  => $notes,
                'decision'      => $status,
                'timestamp'     => $timestamp,
                'sent_at'       => gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ),
            );

            $this->logger->log( 'smma_approval_notification_sent', array(
                'object_type' => 'schedule',
                'object_id'   => (int) $schedule_id,
                'details'     => $payload,
                'user_id'     => $reviewer_id,
            ) );

            do_action(
                'kh_smma_telemetry_event',
                'sponsor.notification.approval_sent',
                $payload
            );
        }
    }

    /**
     * @return array<int,array{id:int,email:string}>
     */
    private function collect_recipients( int $schedule_id ): array {
        $recipient_ids = array();
        foreach ( array( '_kh_smma_created_by', '_kh_smma_editor_user_id' ) as $meta_key ) {
            $value = (int) get_post_meta( $schedule_id, $meta_key, true );
            if ( $value > 0 ) {
                $recipient_ids[] = $value;
            }
        }

        $recipient_ids = array_values( array_unique( $recipient_ids ) );
        $recipients    = array();
        foreach ( $recipient_ids as $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user || empty( $user->user_email ) ) {
                continue;
            }

            $email = trim( (string) $user->user_email );
            if ( function_exists( 'sanitize_email' ) ) {
                $email = sanitize_email( $email );
            }
            if ( '' === $email || ( function_exists( 'is_email' ) && ! is_email( $email ) ) ) {
                continue;
            }

            $recipients[] = array(
                'id'    => $user_id,
                'email' => $email,
            );
        }

        return $recipients;
    }
}
