<?php

namespace KHM\Membership;

class StripeWebhookHandler {
    private const STATUS_TRIAL = 'trial';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_PAST_DUE = 'past_due';
    private const STATUS_PENDING_CANCEL = 'pending_cancel';
    private const STATUS_CANCELED = 'canceled';
    private const DEFAULT_RATE_LIMIT_WINDOW_SECONDS = 60;
    private const DEFAULT_RATE_LIMIT_MAX_REQUESTS = 100;
    private const VALID_STATUSES = [ 'trial', 'active', 'past_due', 'pending_cancel', 'canceled' ];

    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
        add_action( 'khm_process_membership_stripe_webhook_event', [ $this, 'process_queued_event' ], 10, 1 );
        add_action( 'khm_membership_webhook_cleanup', [ ProcessedWebhook::class, 'cleanup_old_events' ] );
        add_action( 'khm_membership_webhook_audit_cleanup', [ MembershipWebhookAuditLogger::class, 'cleanup_old_rows' ] );
        ProcessedWebhook::maybe_schedule_cleanup();
        MembershipWebhookAuditLogger::maybe_create_table();
        MembershipWebhookAuditLogger::maybe_schedule_cleanup();
        MembershipWebhookOperationStore::maybe_create_table();
        MembershipWebhookDeadLetterStore::maybe_create_table();
    }

    public function register_routes() {
        register_rest_route('kh-membership/v1', '/webhook/stripe', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('khm/v1', '/webhooks/stripe', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => '__return_true'
        ]);
    }

    public function handle_request( \WP_REST_Request $req ) {
        $started_at = microtime( true );
        if ( $this->is_rate_limited() ) {
            $this->emit_telemetry( 'webhook.rate_limited', [ 'ip' => $this->get_client_ip() ] );
            return new \WP_REST_Response( [ 'error' => 'Rate limit exceeded' ], 429 );
        }

        $payload = $req->get_body();
        $sig_header = $this->get_signature_header( $req );
        $event = $this->verify_event_signature( $payload, $sig_header );
        if ( is_wp_error( $event ) ) {
            $this->emit_telemetry( 'webhook.invalid_signature', [
                'code' => $event->get_error_code(),
                'message' => $event->get_error_message(),
            ] );
            return new \WP_REST_Response( [ 'error' => 'Invalid signature' ], 400 );
        }

        if ( ! isset( $event->type ) || ! isset( $event->id ) ) {
            $this->emit_telemetry( 'webhook.invalid_event', [ 'payload_hash' => hash( 'sha256', (string) $payload ) ] );
            return new \WP_REST_Response( [ 'error' => 'Invalid event' ], 400 );
        }

        $event_id = (string) $event->id;
        $event_type = (string) $event->type;

        $claim_status = ProcessedWebhook::claim_event( $event_id, $event_type, (string) $payload );
        if ( 'processed' === $claim_status ) {
            return new \WP_REST_Response( [ 'status' => 'success', 'note' => 'already processed' ], 200 );
        }
        if ( 'processing' === $claim_status ) {
            return new \WP_REST_Response( [ 'status' => 'success', 'note' => 'already processing' ], 200 );
        }

        $job = [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'data_object' => isset( $event->data->object ) ? json_decode( wp_json_encode( $event->data->object ), true ) : [],
            'event_created' => isset( $event->created ) ? (int) $event->created : 0,
            'trace_id' => wp_generate_uuid4(),
        ];

        if ( ! $this->enqueue_event_job( $job ) ) {
            ProcessedWebhook::mark_failed( $event_id, 'Failed to enqueue webhook job.' );
            MembershipWebhookDeadLetterStore::store( $event_id, $event_type, (string) $payload, 'enqueue_failed' );
            $this->emit_telemetry( 'webhook.queue_failed', [ 'event_id' => $event_id, 'event_type' => $event_type ] );
            return new \WP_REST_Response( [ 'error' => 'Failed to queue event' ], 500 );
        }

        $latency_ms = (int) round( ( microtime( true ) - $started_at ) * 1000 );
        $this->emit_telemetry( 'webhook.received', [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'latency_ms' => $latency_ms,
        ] );

        return new \WP_REST_Response( [ 'status' => 'queued', 'id' => $event_id, 'type' => $event_type ], 200 );
    }

    public function process_queued_event( $job ) {
        if ( ! is_array( $job ) ) {
            return;
        }

        $event_id = isset( $job['event_id'] ) ? sanitize_text_field( (string) $job['event_id'] ) : '';
        $event_type = isset( $job['event_type'] ) ? sanitize_text_field( (string) $job['event_type'] ) : '';
        if ( '' === $event_id || '' === $event_type ) {
            return;
        }

        $existing = ProcessedWebhook::get_event( $event_id );
        if ( is_array( $existing ) && isset( $existing['status'] ) && $existing['status'] === ProcessedWebhook::STATUS_PROCESSED ) {
            return;
        }

        ProcessedWebhook::mark_processing( $event_id, 'Queued worker started.' );
        $started_at = microtime( true );
        MembershipWebhookAuditLogger::log( $event_id, $event_type, 'processing', null, null, 'Worker started.' );

        $object = null;
        try {
            $object = $this->to_object( $job['data_object'] ?? [] );
            $this->route_event( $event_type, $object, $event_id, isset( $job['event_created'] ) ? (int) $job['event_created'] : 0 );

            ProcessedWebhook::mark_processed( $event_id, 'Processed successfully.' );
            MembershipWebhookAuditLogger::log( $event_id, $event_type, 'success', null, null, 'Event processed successfully.' );
            $this->emit_telemetry( 'webhook.processed', [
                'event_id' => $event_id,
                'event_type' => $event_type,
                'latency_ms' => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
            ] );
        } catch ( \Throwable $e ) {
            $operation_key = $this->get_operation_key_for_event( $event_type, $object );
            if ( $operation_key !== '' ) {
                $this->mark_operation_failed( $operation_key, $e );
            }
            ProcessedWebhook::mark_failed( $event_id, $e->getMessage() );
            MembershipWebhookDeadLetterStore::store(
                $event_id,
                $event_type,
                wp_json_encode( $job ),
                'processing_failed',
                $e->getMessage()
            );
            MembershipWebhookAuditLogger::log( $event_id, $event_type, 'failed', null, null, $e->getMessage() );
            error_log( 'Stripe webhook processing failed for ' . $event_id . ': ' . $e->getMessage() );
            $this->emit_telemetry( 'webhook.failed', [
                'event_id' => $event_id,
                'event_type' => $event_type,
                'error' => $e->getMessage(),
            ] );
        }
    }

    private function get_operation_key_for_event( string $event_type, $object ): string {
        if ( ! is_object( $object ) ) {
            return '';
        }
        switch ( $event_type ) {
            case 'checkout.session.completed':
                return ! empty( $object->id ) ? 'checkout_session:' . (string) $object->id : '';
            case 'invoice.paid':
                return ! empty( $object->id ) ? 'invoice_paid:' . (string) $object->id : '';
            case 'invoice.payment_failed':
                return ! empty( $object->id ) ? 'invoice_failed:' . (string) $object->id : '';
            case 'customer.subscription.updated':
                if ( empty( $object->id ) ) {
                    return '';
                }
                return 'subscription_updated:' . (string) $object->id . ':' . md5( wp_json_encode( $object ) ?: '' );
            case 'charge.refunded':
                return ! empty( $object->id ) ? 'charge_refunded:' . (string) $object->id : '';
            default:
                return '';
        }
    }

    private function route_event( string $event_type, $object, string $event_id, int $event_created = 0 ) : void {
        switch ( $event_type ) {
            case 'checkout.session.completed':
                $this->handle_checkout_session_completed( $object, $event_id );
                break;
            case 'invoice.paid':
                $this->handle_invoice_paid( $object, $event_id, $event_created );
                break;
            case 'invoice.payment_failed':
                $this->handle_payment_failed( $object, $event_id, $event_created );
                break;
            case 'customer.subscription.updated':
                $this->handle_subscription_updated( $object, $event_id, $event_created );
                break;
            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted( $object );
                break;
            case 'charge.refunded':
                $this->handle_charge_refunded( $object, $event_id );
                break;
            default:
                // Intentionally acknowledge unhandled events to prevent infinite retries.
                break;
        }
    }

    private function handle_checkout_session_completed($session, string $event_id) {
        $session_id = isset( $session->id ) ? (string) $session->id : '';
        if ( '' === $session_id ) {
            throw new \RuntimeException( 'checkout.session.completed missing session id' );
        }
        $operation_key = 'checkout_session:' . $session_id;
        if ( ! $this->claim_operation( $operation_key, $event_id, 'checkout.session.completed', $session_id ) ) {
            return;
        }

        $mode = strtolower( (string) ( $session->mode ?? '' ) );
        if ( ! in_array( $mode, [ 'subscription', 'payment' ], true ) ) {
            $this->audit_handler( 'checkout.session.completed', $operation_key, $session_id, null, 'ignored', 'Unsupported checkout mode: ' . $mode );
            $this->mark_operation_succeeded( $operation_key );
            return;
        }

        $metadata = isset($session->metadata) ? (array) $session->metadata : [];
        $user_id = isset($metadata['user_id']) ? intval($metadata['user_id']) : 0;
        $plan_id = isset($metadata['membership_level_id']) ? intval($metadata['membership_level_id']) : 0;
        $user_id = $this->resolve_or_create_user_from_session( $session, $metadata, $user_id );

        if ( ! $user_id ) {
            throw new \RuntimeException( 'checkout.session.completed unable to resolve user' );
        }

        update_user_meta( $user_id, 'khm_last_schedule_id', isset( $metadata['schedule_id'] ) ? absint( $metadata['schedule_id'] ) : 0 );
        update_user_meta( $user_id, 'khm_last_sponsor_id', isset( $metadata['sponsor_id'] ) ? absint( $metadata['sponsor_id'] ) : 0 );
        update_user_meta( $user_id, 'khm_last_utm_source', isset( $metadata['utm_source'] ) ? sanitize_text_field( (string) $metadata['utm_source'] ) : '' );
        update_user_meta( $user_id, 'khm_last_utm_medium', isset( $metadata['utm_medium'] ) ? sanitize_text_field( (string) $metadata['utm_medium'] ) : '' );
        update_user_meta( $user_id, 'khm_last_utm_campaign', isset( $metadata['utm_campaign'] ) ? sanitize_text_field( (string) $metadata['utm_campaign'] ) : '' );
        update_user_meta( $user_id, 'khm_last_phase_at_click', isset( $metadata['phase_at_click'] ) ? sanitize_text_field( (string) $metadata['phase_at_click'] ) : '' );
        update_user_meta(
            $user_id,
            'khm_last_attribution_consent',
            isset( $metadata['consent'] ) && in_array( strtolower( (string) $metadata['consent'] ), [ '1', 'true', 'yes', 'on' ], true ) ? 1 : 0
        );

        if ( 'subscription' === $mode ) {
            if ( ! $plan_id ) {
                throw new \RuntimeException( 'checkout.session.completed missing membership tier metadata' );
            }
            $tier_slug = isset( $metadata['tier_slug'] ) ? sanitize_key( (string) $metadata['tier_slug'] ) : '';
            $metadata_price_id = isset( $metadata['stripe_price_id'] ) ? sanitize_text_field( (string) $metadata['stripe_price_id'] ) : '';
            if ( $tier_slug === '' || $metadata_price_id === '' ) {
                throw new \RuntimeException( 'checkout.session.completed missing tier_slug or stripe_price_id metadata' );
            }
            if ( ! TierRegistry::validate_price_match( $tier_slug, $metadata_price_id ) ) {
                $this->audit_handler(
                    'checkout.session.completed',
                    $operation_key,
                    $session_id,
                    $user_id,
                    'security_mismatch',
                    'Tier to price mismatch detected in checkout metadata.',
                    [ 'tier_slug' => $tier_slug, 'stripe_price_id' => $metadata_price_id ]
                );
                $this->log_security_anomaly(
                    'checkout_tier_price_mismatch',
                    'Checkout metadata tier/price mismatch.',
                    [
                        'event_id' => $event_id,
                        'session_id' => $session_id,
                        'user_id' => $user_id,
                        'tier_slug' => $tier_slug,
                        'stripe_price_id' => $metadata_price_id,
                    ]
                );
                throw new \RuntimeException( 'checkout.session.completed tier/price mismatch' );
            }

            global $wpdb;
            $user_membership_table = $wpdb->prefix . 'user_membership';

            $stripe_customer_id = isset($session->customer) ? $session->customer : null;
            $stripe_subscription_id = isset($session->subscription) ? $session->subscription : null;
            $stripe_price_id = $metadata_price_id;
            $trial_days = isset( $metadata['trial_days'] ) ? max( 0, (int) $metadata['trial_days'] ) : 0;
            $status = $trial_days > 0 ? 'trial' : 'active';

            $wpdb->replace($user_membership_table, [
                'user_id' => $user_id,
                'tier_id' => $plan_id,
                'tier_slug' => $tier_slug,
                'stripe_customer_id' => $stripe_customer_id,
                'stripe_subscription_id' => $stripe_subscription_id,
                'stripe_price_id' => $stripe_price_id,
                'status' => $status,
                'trial_ends_at' => $trial_days > 0 ? gmdate( 'Y-m-d H:i:s', time() + ( $trial_days * 86400 ) ) : null,
                'trial_end_date' => $trial_days > 0 ? gmdate( 'Y-m-d H:i:s', time() + ( $trial_days * 86400 ) ) : null,
                'started_at' => current_time('mysql', 1),
            ]);

            if ( $stripe_price_id !== '' ) {
                update_user_meta( $user_id, 'khm_membership_last_price_id', $stripe_price_id );
            }

            $schedule_id = isset( $metadata['schedule_id'] ) ? absint( $metadata['schedule_id'] ) : 0;
            if ( $schedule_id <= 0 && '' !== $session_id ) {
                $temp = $this->get_temp_attribution_payload( $session_id );
                if ( isset( $temp['schedule_id'] ) ) {
                    $schedule_id = absint( $temp['schedule_id'] );
                }
            }

            $this->record_paid_attribution( $user_id, $plan_id, $schedule_id, $metadata, $session_id );
            if ( $schedule_id <= 0 ) {
                $this->emit_telemetry( 'membership.attribution.missing', [
                    'user_id' => $user_id,
                    'reference' => $session_id,
                    'reason' => 'missing_schedule_id',
                ] );
            }

            $this->emit_telemetry( 'membership.signup', [
                'user_id' => $user_id,
                'tier' => $plan_id,
                'attribution_id' => isset( $metadata['attribution_id'] ) ? (string) $metadata['attribution_id'] : '',
                'payment_type' => 'subscription',
            ] );
            $this->emit_telemetry( 'landing.success', [
                'user_id' => $user_id,
                'schedule_id' => $schedule_id,
                'sponsor_id' => isset( $metadata['sponsor_id'] ) ? absint( $metadata['sponsor_id'] ) : 0,
                'utm_source' => isset( $metadata['utm_source'] ) ? sanitize_text_field( (string) $metadata['utm_source'] ) : '',
                'utm_campaign' => isset( $metadata['utm_campaign'] ) ? sanitize_text_field( (string) $metadata['utm_campaign'] ) : '',
                'reference' => $session_id,
            ] );

            $this->maybe_send_welcome_email( $user_id, $plan_id, $metadata, $session_id, $trial_days );
            if ( $trial_days <= 0 ) {
                $this->maybe_send_payment_email(
                    $user_id,
                    'session:' . $session_id,
                    0.0,
                    'GBP',
                    $metadata,
                    $session_id,
                    $plan_id
                );
            }

            $this->audit_handler(
                'checkout.session.completed',
                $operation_key,
                $session_id,
                $user_id,
                'success',
                'Subscription membership activated.',
                [ 'tier' => $plan_id, 'mode' => $mode, 'subscription_id' => $stripe_subscription_id ]
            );
            $this->mark_operation_succeeded( $operation_key );
            return;
        }

        $credits_amount = $this->resolve_checkout_credits_amount( $metadata );
        if ( $credits_amount <= 0 ) {
            throw new \RuntimeException( 'checkout.session.completed payment mode missing credits metadata' );
        }

        $this->record_credit_checkout( $session, $user_id, $credits_amount, $operation_key );
        $this->emit_telemetry( 'membership.signup', [
            'user_id' => $user_id,
            'tier' => $plan_id ?: 0,
            'attribution_id' => isset( $metadata['attribution_id'] ) ? (string) $metadata['attribution_id'] : '',
            'payment_type' => 'credits',
        ] );
        $this->audit_handler(
            'checkout.session.completed',
            $operation_key,
            $session_id,
            $user_id,
            'success',
            'Credit checkout processed.',
            [ 'credits_amount' => $credits_amount, 'mode' => $mode ]
        );
        $this->mark_operation_succeeded( $operation_key );
    }

    private function handle_invoice_paid($invoice, string $event_id, int $event_created = 0) {
        $invoice_id = isset( $invoice->id ) ? (string) $invoice->id : '';
        if ( '' === $invoice_id ) {
            throw new \RuntimeException( 'invoice.paid missing invoice id' );
        }
        $operation_key = 'invoice_paid:' . $invoice_id;
        if ( ! $this->claim_operation( $operation_key, $event_id, 'invoice.paid', $invoice_id ) ) {
            return;
        }
        $subscription_id = isset( $invoice->subscription ) ? (string) $invoice->subscription : '';
        if ( '' !== $subscription_id && $this->is_stale_subscription_event( $subscription_id, $event_created ) ) {
            $this->audit_handler( 'invoice.paid', $operation_key, $invoice_id, null, 'ignored', 'Stale invoice.paid ignored by event ordering guard.', [ 'subscription_id' => $subscription_id ] );
            $this->mark_operation_succeeded( $operation_key );
            return;
        }
        $user_id = $this->get_user_id_by_stripe_subscription_or_customer(
            $subscription_id,
            isset( $invoice->customer ) ? (string) $invoice->customer : ''
        );
        if ( !$user_id ) {
            throw new \RuntimeException( 'invoice.paid unable to resolve user' );
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $period_end = isset( $invoice->period_end ) ? (int) $invoice->period_end : 0;
        $next_renewal = $period_end > 0 ? gmdate( 'Y-m-d H:i:s', $period_end ) : null;

        $update = [
            'status' => 'active',
            'last_payment_date' => current_time( 'mysql', 1 ),
        ];
        if ( $next_renewal ) {
            $update['current_period_end'] = $next_renewal;
        }
        $wpdb->update( $user_membership_table, $update, [ 'user_id' => $user_id ] );

        update_user_meta( $user_id, 'khm_membership_last_payment_date', current_time( 'mysql', 1 ) );
        if ( $next_renewal ) {
            update_user_meta( $user_id, 'khm_membership_next_renewal_date', $next_renewal );
        }
        if ( ! empty( $invoice->payment_intent ) ) {
            update_user_meta( $user_id, 'khm_last_payment_intent_id', sanitize_text_field( (string) $invoice->payment_intent ) );
        }
        update_user_meta( $user_id, 'khm_last_payment_status', 'paid' );
        if ( '' !== $subscription_id ) {
            $this->update_subscription_event_cursor( $subscription_id, $event_created );
        }
        $this->emit_telemetry( 'membership.renewal', [ 'user_id' => $user_id, 'invoice_id' => $invoice_id, 'subscription_id' => $subscription_id ] );
        $metadata = [
            'schedule_id' => get_user_meta( $user_id, 'khm_last_schedule_id', true ),
            'sponsor_id' => get_user_meta( $user_id, 'khm_last_sponsor_id', true ),
            'utm_source' => get_user_meta( $user_id, 'khm_last_utm_source', true ),
            'utm_medium' => get_user_meta( $user_id, 'khm_last_utm_medium', true ),
            'utm_campaign' => get_user_meta( $user_id, 'khm_last_utm_campaign', true ),
            'phase_at_click' => get_user_meta( $user_id, 'khm_last_phase_at_click', true ),
            'consent' => get_user_meta( $user_id, 'khm_last_attribution_consent', true ) ? '1' : '0',
        ];
        $amount_paid = isset( $invoice->amount_paid ) ? ((float) $invoice->amount_paid / 100.0) : 0.0;
        $currency = isset( $invoice->currency ) ? strtoupper( sanitize_text_field( (string) $invoice->currency ) ) : 'GBP';
        $this->maybe_send_payment_email( $user_id, 'invoice:' . $invoice_id, $amount_paid, $currency, $metadata, $invoice_id );
        $this->audit_handler( 'invoice.paid', $operation_key, $invoice_id, $user_id, 'success', 'Invoice paid applied.', [ 'subscription_id' => $subscription_id ] );
        $this->mark_operation_succeeded( $operation_key );
    }

    private function handle_payment_failed($invoice, string $event_id, int $event_created = 0) {
        $invoice_id = isset( $invoice->id ) ? (string) $invoice->id : '';
        if ( '' === $invoice_id ) {
            throw new \RuntimeException( 'invoice.payment_failed missing invoice id' );
        }
        $operation_key = 'invoice_failed:' . $invoice_id;
        if ( ! $this->claim_operation( $operation_key, $event_id, 'invoice.payment_failed', $invoice_id ) ) {
            return;
        }
        $subscription_id = isset( $invoice->subscription ) ? (string) $invoice->subscription : '';
        if ( '' !== $subscription_id && $this->is_stale_subscription_event( $subscription_id, $event_created ) ) {
            $this->audit_handler( 'invoice.payment_failed', $operation_key, $invoice_id, null, 'ignored', 'Stale invoice.payment_failed ignored by event ordering guard.', [ 'subscription_id' => $subscription_id ] );
            $this->mark_operation_succeeded( $operation_key );
            return;
        }

        $user_id = $this->get_user_id_by_stripe_subscription_or_customer(
            $subscription_id,
            isset( $invoice->customer ) ? (string) $invoice->customer : ''
        );
        if ( !$user_id ) {
            throw new \RuntimeException( 'invoice.payment_failed unable to resolve user' );
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $failure_reason = '';
        if ( isset( $invoice->last_finalization_error ) && isset( $invoice->last_finalization_error->message ) ) {
            $failure_reason = (string) $invoice->last_finalization_error->message;
        }

        $wpdb->update( $user_membership_table, [ 'status' => 'past_due' ], [ 'user_id' => $user_id ] );

        $retry_count = (int) get_user_meta( $user_id, 'khm_membership_payment_failed_retry_count', true );
        update_user_meta( $user_id, 'khm_membership_payment_failed_retry_count', $retry_count + 1 );
        if ( $failure_reason !== '' ) {
            update_user_meta( $user_id, 'khm_membership_last_failure_reason', sanitize_text_field( $failure_reason ) );
        }
        if ( '' !== $subscription_id ) {
            $this->update_subscription_event_cursor( $subscription_id, $event_created );
        }
        do_action( 'khm_membership_invoice_payment_failed', $user_id, $invoice );
        $this->emit_telemetry( 'membership.payment_failed', [ 'user_id' => $user_id, 'invoice_id' => $invoice_id, 'reason' => $failure_reason ] );
        $this->audit_handler( 'invoice.payment_failed', $operation_key, $invoice_id, $user_id, 'success', 'Invoice payment failure applied.', [ 'reason' => $failure_reason ] );
        $this->mark_operation_succeeded( $operation_key );
    }

    private function handle_subscription_updated($subscription, string $event_id, int $event_created = 0) {
        $subscription_id = isset( $subscription->id ) ? (string) $subscription->id : '';
        if ( '' === $subscription_id ) {
            throw new \RuntimeException( 'customer.subscription.updated missing subscription id' );
        }
        $operation_key = 'subscription_updated:' . $subscription_id . ':' . md5( wp_json_encode( $subscription ) ?: '' );
        if ( ! $this->claim_operation( $operation_key, $event_id, 'customer.subscription.updated', $subscription_id ) ) {
            return;
        }
        if ( $this->is_stale_subscription_event( $subscription_id, $event_created ) ) {
            $this->audit_handler( 'customer.subscription.updated', $operation_key, $subscription_id, null, 'ignored', 'Stale subscription update ignored by event ordering guard.' );
            $this->mark_operation_succeeded( $operation_key );
            return;
        }

        $user_id = $this->get_user_id_by_stripe_subscription_or_customer(
            $subscription_id,
            isset( $subscription->customer ) ? (string) $subscription->customer : ''
        );
        if ( !$user_id ) {
            throw new \RuntimeException( 'customer.subscription.updated unable to resolve user' );
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        $remote_status = isset($subscription->status) ? (string) $subscription->status : '';
        $update_data = [
            'status' => $this->normalize_subscription_status( $remote_status ),
            'stripe_subscription_id' => $subscription->id,
        ];

        $remote_status = isset( $subscription->status ) ? strtolower( (string) $subscription->status ) : '';
        if ( in_array( $remote_status, [ 'trialing' ], true ) ) {
            $update_data['status'] = 'trial';
        } elseif ( in_array( $remote_status, [ 'active' ], true ) ) {
            $update_data['status'] = 'active';
        } elseif ( in_array( $remote_status, [ 'past_due', 'unpaid' ], true ) ) {
            $update_data['status'] = 'past_due';
        } elseif ( in_array( $remote_status, [ 'canceled', 'cancelled' ], true ) ) {
            $update_data['status'] = 'canceled';
        } else {
            $update_data['status'] = 'past_due';
        }

        if ( isset($subscription->trial_end) && $subscription->trial_end ) {
            $update_data['trial_ends_at'] = gmdate('Y-m-d H:i:s', $subscription->trial_end);
            $update_data['trial_end_date'] = gmdate('Y-m-d H:i:s', $subscription->trial_end);
        }

        if ( isset($subscription->cancel_at_period_end) && $subscription->cancel_at_period_end ) {
            $update_data['status'] = self::STATUS_PENDING_CANCEL;
            $update_data['status'] = 'pending_cancel';
            $update_data['cancel_at_period_end'] = 1;
            if ( isset($subscription->cancel_at) && $subscription->cancel_at ) {
                $update_data['cancelled_at'] = gmdate('Y-m-d H:i:s', $subscription->cancel_at);
            }
        } else {
            $update_data['cancel_at_period_end'] = 0;
            $update_data['cancelled_at'] = null;
        }
        if ( isset( $subscription->current_period_end ) && $subscription->current_period_end ) {
            $update_data['current_period_end'] = gmdate( 'Y-m-d H:i:s', (int) $subscription->current_period_end );
        }
        $price_id = $this->extract_subscription_price_id( $subscription );
        if ( $price_id !== '' ) {
            $update_data['stripe_price_id'] = $price_id;
            $tier = TierRegistry::find_tier_by_price( $price_id );
            if ( $tier ) {
                $resolved_slug = (string) ( $tier['slug'] ?? '' );
                if ( $resolved_slug !== '' ) {
                    $update_data['tier_slug'] = $resolved_slug;
                    $resolved_tier_id = $this->find_plan_id_by_tier_slug( $resolved_slug );
                    if ( $resolved_tier_id > 0 ) {
                        $update_data['tier_id'] = $resolved_tier_id;
                    }
                }
            } else {
                $this->log_security_anomaly(
                    'subscription_unknown_price_id',
                    'Subscription updated contains unknown stripe_price_id.',
                    [
                        'event_id' => $event_id,
                        'subscription_id' => $subscription_id,
                        'user_id' => $user_id,
                        'stripe_price_id' => $price_id,
                    ]
                );
            }
        }
        if ( ! in_array( (string) ( $update_data['status'] ?? '' ), self::VALID_STATUSES, true ) ) {
            $update_data['status'] = 'past_due';
        }

        $wpdb->update(
            $user_membership_table,
            $update_data,
            ['user_id' => $user_id]
        );

        $status = (string) ( $update_data['status'] ?? '' );
        if ( $status === 'active' ) {
            $this->emit_telemetry( 'membership.subscription_updated', [ 'user_id' => $user_id, 'subscription_id' => $subscription_id, 'status' => $status ] );
        } elseif ( in_array( $status, [ 'canceled', 'cancelled' ], true ) ) {
            $this->emit_telemetry( 'membership.subscription_updated', [ 'user_id' => $user_id, 'subscription_id' => $subscription_id, 'status' => 'canceled' ] );
        } else {
            $this->emit_telemetry( 'membership.subscription_updated', [ 'user_id' => $user_id, 'subscription_id' => $subscription_id, 'status' => $status ] );
        }
        $this->audit_handler(
            'customer.subscription.updated',
            $operation_key,
            $subscription_id,
            $user_id,
            'success',
            'Subscription update applied.',
            [ 'status' => $status, 'stripe_price_id' => $price_id ]
        );
        $this->update_subscription_event_cursor( $subscription_id, $event_created );
        $this->mark_operation_succeeded( $operation_key );
    }

    private function handle_subscription_deleted($subscription) {
        $user_id = $this->get_user_id_by_stripe_customer($subscription->customer);
        if ( !$user_id ) {
            return;
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        $wpdb->update(
            $user_membership_table,
            [
                'status' => self::STATUS_CANCELED,
                'status' => 'canceled',
                'cancelled_at' => current_time('mysql', 1)
            ],
            ['user_id' => $user_id]
        );
    }

    private function handle_charge_refunded( $charge, string $event_id ): void {
        $charge_id = isset( $charge->id ) ? (string) $charge->id : '';
        if ( '' === $charge_id ) {
            throw new \RuntimeException( 'charge.refunded missing charge id' );
        }
        $operation_key = 'charge_refunded:' . $charge_id;
        if ( ! $this->claim_operation( $operation_key, $event_id, 'charge.refunded', $charge_id ) ) {
            return;
        }

        $credit_refund_applied = $this->handle_credit_refund_reversal( $charge, $event_id );
        if ( ! $credit_refund_applied ) {
            // Subscription/system refund: audit only, no auto-cancel here.
            $this->audit_handler(
                'charge.refunded',
                $operation_key,
                $charge_id,
                null,
                'success',
                'Refund recorded (non-credit or no credit purchase match).'
            );
        }

        do_action( 'khm_membership_stripe_charge_refunded', $charge, $event_id );
        if ( function_exists( 'khm_handle_stripe_charge_refunded' ) ) {
            khm_handle_stripe_charge_refunded( $charge, $event_id );
        }
        $this->emit_telemetry( 'membership.refund', [ 'charge_id' => $charge_id, 'event_id' => $event_id ] );
        $this->mark_operation_succeeded( $operation_key );
    }

    private function get_user_id_by_stripe_customer($stripe_customer_id) {
        if ( empty($stripe_customer_id) ) {
            return null;
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $user_membership_table WHERE stripe_customer_id = %s LIMIT 1",
            $stripe_customer_id
        ));

        return $user_id ? intval($user_id) : null;
    }

    private function record_paid_attribution( int $user_id, int $plan_id, int $schedule_id, array $metadata = [], string $session_id = '' ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'promotion_attribution';

        $temp_payload = $this->get_temp_attribution_payload( $session_id );
        $metadata = array_merge( $temp_payload, $metadata );

        $consent = isset( $metadata['consent'] ) ? in_array( strtolower( (string) $metadata['consent'] ), [ '1', 'true', 'yes', 'on' ], true ) : false;
        if ( ! isset( $metadata['consent'] ) && isset( $temp_payload['consent'] ) ) {
            $consent = in_array( strtolower( (string) $temp_payload['consent'] ), [ '1', 'true', 'yes', 'on' ], true );
        }

        $conversion_type = $consent ? 'paid' : 'paid_no_consent';
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d AND schedule_id = %d AND conversion_type = %s LIMIT 1",
                $user_id,
                $schedule_id,
                $conversion_type
            )
        );

        if ( $existing ) {
            return;
        }

        if ( ! $consent ) {
            $reference = [
                'source' => 'stripe.checkout.session.completed',
                'stripe_session_id' => $session_id,
                'idempotency_key' => isset( $metadata['idempotency_key'] ) ? sanitize_text_field( (string) $metadata['idempotency_key'] ) : '',
                'reference' => $session_id,
                'consent' => false,
                'utm_redacted' => true,
            ];

            $wpdb->insert(
                $table,
                [
                    'schedule_id' => $schedule_id > 0 ? $schedule_id : null,
                    'sponsor_id' => isset( $metadata['sponsor_id'] ) ? absint( $metadata['sponsor_id'] ) : 0,
                    'user_id' => $user_id,
                    'user_email' => '',
                    'utm_source' => '',
                    'utm_medium' => '',
                    'utm_campaign' => '',
                    'utm_term' => '',
                    'utm_content' => '',
                    'phase_at_click' => '',
                    'conversion_type' => 'paid_no_consent',
                    'plan_id' => $plan_id,
                    'consent' => 0,
                    'consent_given_at' => null,
                    'consent_source' => 'webhook',
                    'reference' => $session_id,
                    'reference_metadata' => wp_json_encode( $reference ),
                    'created_at' => current_time( 'mysql', 1 ),
                ],
                [ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ]
            );

            $this->emit_telemetry( 'membership.attribution.confirmed', [
                'user_id' => $user_id,
                'schedule_id' => $schedule_id,
                'reference' => $session_id,
                'consent' => 0,
            ] );
            return;
        }

        $user = get_user_by( 'id', $user_id );
        $reference = [
            'source' => 'stripe.checkout.session.completed',
            'stripe_session_id' => $session_id,
            'idempotency_key' => isset( $metadata['idempotency_key'] ) ? sanitize_text_field( (string) $metadata['idempotency_key'] ) : '',
            'reference' => $session_id,
        ];

        $wpdb->insert(
            $table,
            [
                'schedule_id' => $schedule_id,
                'sponsor_id' => isset( $metadata['sponsor_id'] ) ? absint( $metadata['sponsor_id'] ) : 0,
                'user_id' => $user_id,
                'user_email' => $user ? $user->user_email : '',
                'utm_source' => isset( $metadata['utm_source'] ) ? sanitize_text_field( (string) $metadata['utm_source'] ) : '',
                'utm_medium' => isset( $metadata['utm_medium'] ) ? sanitize_text_field( (string) $metadata['utm_medium'] ) : '',
                'utm_campaign' => isset( $metadata['utm_campaign'] ) ? sanitize_text_field( (string) $metadata['utm_campaign'] ) : '',
                'utm_term' => isset( $metadata['utm_term'] ) ? sanitize_text_field( (string) $metadata['utm_term'] ) : '',
                'utm_content' => isset( $metadata['utm_content'] ) ? sanitize_text_field( (string) $metadata['utm_content'] ) : '',
                'phase_at_click' => isset( $metadata['phase_at_click'] ) ? sanitize_text_field( (string) $metadata['phase_at_click'] ) : '',
                'conversion_type' => 'paid',
                'plan_id' => $plan_id,
                'consent' => 1,
                'consent_given_at' => current_time( 'mysql', 1 ),
                'consent_source' => 'webhook',
                'reference' => $session_id,
                'reference_metadata' => wp_json_encode( $reference ),
                'created_at' => current_time( 'mysql', 1 ),
            ],
            [ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ]
        );

        $this->emit_telemetry( 'membership.attribution.created', [
            'user_id' => $user_id,
            'schedule_id' => $schedule_id,
            'sponsor_id' => isset( $metadata['sponsor_id'] ) ? absint( $metadata['sponsor_id'] ) : 0,
            'reference' => $session_id,
        ] );
        $this->emit_telemetry( 'membership.attribution.confirmed', [
            'user_id' => $user_id,
            'schedule_id' => $schedule_id,
            'reference' => $session_id,
            'consent' => 1,
        ] );
    }

    private function get_temp_attribution_payload( string $session_id ): array {
        if ( '' === $session_id || ! class_exists( '\\KHM\\Services\\MembershipRepository' ) ) {
            return [];
        }

        try {
            $repository = new \KHM\Services\MembershipRepository();
            $record = $repository->getTempAttribution( $session_id );
            if ( ! is_array( $record ) || ! isset( $record['payload'] ) || ! is_array( $record['payload'] ) ) {
                return [];
            }

            return $record['payload'];
        } catch ( \Throwable $e ) {
            return [];
        }
    }

    private function maybe_send_welcome_email( int $user_id, int $plan_id, array $metadata, string $reference, int $trial_days ): void {
        if ( ! $this->are_transactional_emails_enabled() ) {
            $this->emit_telemetry( 'membership.email.skipped', [
                'type' => 'welcome',
                'user_id' => $user_id,
                'reason' => 'transactional_emails_disabled',
                'reference' => $reference,
            ] );
            MembershipWebhookAuditLogger::log( '', 'membership.email.welcome', 'skipped', null, $user_id, 'Transactional emails disabled.' );
            return;
        }

        $idempotency_key = sprintf( 'welcome_v1:%d:%d', $user_id, $plan_id );
        if ( $this->has_sent_email_key( $user_id, $idempotency_key ) ) {
            return;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return;
        }

        $email_data = $this->build_membership_email_data( $user_id, $plan_id, $metadata, $reference );
        $email_data['trial_end'] = $trial_days > 0 ? gmdate( 'Y-m-d', time() + ( $trial_days * DAY_IN_SECONDS ) ) : '';

        $sent = false;
        try {
            $sent = $this->send_membership_email(
                'welcome',
                (string) $user->user_email,
                __( 'Welcome to your membership', 'khm-membership' ),
                $email_data
            );
        } catch ( \Throwable $e ) {
            $this->emit_email_failure( 'welcome', $user_id, $e->getMessage() );
            MembershipWebhookAuditLogger::log( '', 'membership.email.welcome', 'failed', null, $user_id, $e->getMessage() );
            return;
        }

        if ( $sent ) {
            $this->mark_email_key_sent( $user_id, $idempotency_key );
            $this->emit_telemetry( 'membership.email.welcome.sent', [
                'user_id' => $user_id,
                'reference' => $reference,
            ] );
            MembershipWebhookAuditLogger::log( '', 'membership.email.welcome', 'success', null, $user_id, 'Welcome email sent.' );
            return;
        }

        $this->emit_email_failure( 'welcome', $user_id, 'send_failed' );
        MembershipWebhookAuditLogger::log( '', 'membership.email.welcome', 'failed', null, $user_id, 'Welcome email send failed.' );
    }

    private function maybe_send_payment_email( int $user_id, string $id_reference, float $amount, string $currency, array $metadata, string $reference, int $plan_id = 0 ): void {
        if ( ! $this->are_transactional_emails_enabled() ) {
            $this->emit_telemetry( 'membership.email.skipped', [
                'type' => 'payment',
                'user_id' => $user_id,
                'reason' => 'transactional_emails_disabled',
                'reference' => $id_reference,
            ] );
            MembershipWebhookAuditLogger::log( '', 'membership.email.payment', 'skipped', null, $user_id, 'Transactional emails disabled.' );
            return;
        }

        $idempotency_key = 'payment_v1:' . sanitize_key( str_replace( ':', '_', $id_reference ) );
        if ( $this->has_sent_email_key( $user_id, $idempotency_key ) ) {
            return;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return;
        }

        if ( $plan_id <= 0 ) {
            $plan_id = (int) get_user_meta( $user_id, 'khm_last_plan_id', true );
        } else {
            update_user_meta( $user_id, 'khm_last_plan_id', $plan_id );
        }

        $email_data = $this->build_membership_email_data( $user_id, $plan_id, $metadata, $reference );
        $email_data['amount'] = $amount;
        $email_data['currency'] = $currency ?: 'GBP';

        $sent = false;
        try {
            $sent = $this->send_membership_email(
                'payment_confirmation',
                (string) $user->user_email,
                __( 'Your membership payment confirmation', 'khm-membership' ),
                $email_data
            );
        } catch ( \Throwable $e ) {
            $this->emit_email_failure( 'payment', $user_id, $e->getMessage() );
            MembershipWebhookAuditLogger::log( '', 'membership.email.payment', 'failed', null, $user_id, $e->getMessage() );
            return;
        }

        if ( $sent ) {
            $this->mark_email_key_sent( $user_id, $idempotency_key );
            $this->emit_telemetry( 'membership.email.payment.sent', [
                'user_id' => $user_id,
                'reference' => $id_reference,
            ] );
            MembershipWebhookAuditLogger::log( '', 'membership.email.payment', 'success', null, $user_id, 'Payment email sent.' );
            return;
        }

        $this->emit_email_failure( 'payment', $user_id, 'send_failed' );
        MembershipWebhookAuditLogger::log( '', 'membership.email.payment', 'failed', null, $user_id, 'Payment email send failed.' );
    }

    private function emit_email_failure( string $type, int $user_id, string $error ): void {
        $payload = [
            'type' => $type,
            'user_id' => $user_id,
            'error' => $error,
        ];
        $this->emit_telemetry( 'membership.email.failed', $payload );
        $this->emit_telemetry( 'membership.email.sent.failed', $payload );
    }

    private function send_membership_email( string $template, string $recipient, string $subject, array $data ): bool {
        $service = $this->get_membership_email_service();
        return $service
            ->setSubject( $subject )
            ->send( $template, $recipient, $data );
    }

    private function get_membership_email_service() {
        $plugin_dir = dirname( __DIR__, 2 );
        $use_enhanced = (bool) apply_filters(
            'khm_membership_use_enhanced_email_service',
            (bool) get_option( 'khm_membership_use_enhanced_email_service', true )
        );

        if ( $use_enhanced && class_exists( '\KHM\Services\EnhancedEmailService' ) ) {
            return new \KHM\Services\EnhancedEmailService( $plugin_dir );
        }

        return new \KHM\Services\EmailService( $plugin_dir );
    }

    private function are_transactional_emails_enabled(): bool {
        $enabled = (bool) get_option( 'khm_membership_transactional_emails_enabled', false );
        return (bool) apply_filters( 'khm_membership_transactional_emails_enabled', $enabled );
    }

    private function build_membership_email_data( int $user_id, int $plan_id, array $metadata, string $reference ): array {
        $user = get_user_by( 'id', $user_id );
        $tier_name = $this->resolve_membership_tier_name( $plan_id );
        $schedule_id = isset( $metadata['schedule_id'] ) ? absint( $metadata['schedule_id'] ) : 0;
        $sponsor_id = isset( $metadata['sponsor_id'] ) ? absint( $metadata['sponsor_id'] ) : 0;
        $sponsor_name = $this->resolve_sponsor_name( $sponsor_id );

        return [
            'user_name' => $user ? (string) $user->display_name : __( 'Member', 'khm-membership' ),
            'membership_tier' => $tier_name,
            'start_date' => gmdate( 'Y-m-d' ),
            'trial_end' => '',
            'amount' => '',
            'currency' => 'GBP',
            'schedule_id' => $schedule_id > 0 ? (string) $schedule_id : '',
            'sponsor_name' => $sponsor_name,
            'utm_source' => isset( $metadata['utm_source'] ) ? sanitize_text_field( (string) $metadata['utm_source'] ) : '',
            'reference' => $reference,
            'support_contact' => get_option( 'admin_email' ),
            'account_url' => home_url( '/account/' ),
            'preferences_url' => home_url( '/account/' ),
            'siteurl' => home_url(),
            'sitename' => get_bloginfo( 'name' ),
        ];
    }

    private function resolve_membership_tier_name( int $plan_id ): string {
        if ( $plan_id <= 0 ) {
            return __( 'Membership', 'khm-membership' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'membership_tier';
        $name = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT name FROM {$table} WHERE id = %d LIMIT 1",
                $plan_id
            )
        );

        return $name ? sanitize_text_field( (string) $name ) : __( 'Membership', 'khm-membership' );
    }

    private function resolve_sponsor_name( int $sponsor_id ): string {
        if ( $sponsor_id <= 0 ) {
            return '';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'khm_sponsors';
        $name = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT name FROM {$table} WHERE id = %d LIMIT 1",
                $sponsor_id
            )
        );

        return $name ? sanitize_text_field( (string) $name ) : '';
    }

    private function has_sent_email_key( int $user_id, string $idempotency_key ): bool {
        $meta_key = '_khm_email_sent_' . md5( $idempotency_key );
        return (bool) get_user_meta( $user_id, $meta_key, true );
    }

    private function mark_email_key_sent( int $user_id, string $idempotency_key ): void {
        $meta_key = '_khm_email_sent_' . md5( $idempotency_key );
        update_user_meta( $user_id, $meta_key, current_time( 'mysql', 1 ) );
    }

    private function normalize_subscription_status( string $status ): string {
        $normalized = strtolower( trim( $status ) );
        if ( in_array( $normalized, [ 'trialing', 'trial' ], true ) ) {
            return self::STATUS_TRIAL;
        }
        if ( in_array( $normalized, [ 'active' ], true ) ) {
            return self::STATUS_ACTIVE;
        }
        if ( in_array( $normalized, [ 'past_due', 'unpaid' ], true ) ) {
            return self::STATUS_PAST_DUE;
        }
        if ( in_array( $normalized, [ 'canceled', 'cancelled' ], true ) ) {
            return self::STATUS_CANCELED;
        }

        return self::STATUS_PAST_DUE;
    }

    private function verify_event_signature( string $payload, string $sig_header ) {
        $skip_verification = (bool) apply_filters(
            'khm_membership_webhook_skip_signature_verification',
            false,
            $payload,
            $sig_header
        );
        if ( $skip_verification ) {
            $decoded = json_decode( $payload );
            return is_object( $decoded ) ? $decoded : new \WP_Error( 'khm_invalid_payload', 'Invalid payload.' );
        }

        $webhook_secret = '';
        if ( function_exists( 'khm_get_stripe_secret' ) ) {
            $webhook_secret = trim( (string) ( khm_get_stripe_secret( 'KH_STRIPE_WEBHOOK_SECRET' ) ?? '' ) );
        }
        if ( '' === $webhook_secret ) {
            error_log( 'Stripe webhook rejected: KH_STRIPE_WEBHOOK_SECRET not configured.' );
            return new \WP_Error( 'khm_missing_webhook_secret', 'Missing webhook secret.' );
        }

        if ( ! class_exists( '\Stripe\Webhook' ) ) {
            error_log( 'Stripe webhook rejected: Stripe SDK unavailable.' );
            return new \WP_Error( 'khm_missing_stripe_sdk', 'Stripe SDK unavailable.' );
        }

        try {
            return \Stripe\Webhook::constructEvent( $payload, $sig_header, $webhook_secret );
        } catch (\UnexpectedValueException $e) {
            return new \WP_Error( 'khm_invalid_payload', $e->getMessage() );
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new \WP_Error( 'khm_invalid_signature', $e->getMessage() );
        } catch (\Throwable $e) {
            return new \WP_Error( 'khm_webhook_verify_failed', $e->getMessage() );
        }
    }

    private function enqueue_event_job( array $job ): bool {
        if ( function_exists( 'wp_schedule_single_event' ) ) {
            return (bool) wp_schedule_single_event( time() + 1, 'khm_process_membership_stripe_webhook_event', [ $job ] );
        }

        $this->process_queued_event( $job );
        return true;
    }

    private function is_rate_limited(): bool {
        if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
            return false;
        }

        $window_default = self::DEFAULT_RATE_LIMIT_WINDOW_SECONDS;
        $max_default = self::DEFAULT_RATE_LIMIT_MAX_REQUESTS;

        if ( defined( 'KHM_MEMBERSHIP_WEBHOOK_RATE_LIMIT_WINDOW' ) ) {
            $window_default = (int) KHM_MEMBERSHIP_WEBHOOK_RATE_LIMIT_WINDOW;
        }
        if ( defined( 'KHM_MEMBERSHIP_WEBHOOK_RATE_LIMIT_MAX_REQUESTS' ) ) {
            $max_default = (int) KHM_MEMBERSHIP_WEBHOOK_RATE_LIMIT_MAX_REQUESTS;
        }

        $window_seconds = (int) apply_filters( 'khm_membership_webhook_rate_limit_window', $window_default );
        $max_requests = (int) apply_filters( 'khm_membership_webhook_rate_limit_max_requests', $max_default );
        $window_seconds = max( 5, $window_seconds );
        $max_requests = max( 1, $max_requests );

        $ip = $this->get_client_ip();
        $key = 'khm_wh_rl_' . md5( $ip . '|' . gmdate( 'YmdHi' ) );
        $count = (int) get_transient( $key );
        $count++;
        set_transient( $key, $count, $window_seconds );

        return $count > $max_requests;
    }

    private function get_client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $value = (string) $_SERVER[ $key ];
                if ( 'HTTP_X_FORWARDED_FOR' === $key && strpos( $value, ',' ) !== false ) {
                    $parts = explode( ',', $value );
                    $value = trim( (string) reset( $parts ) );
                }
                return sanitize_text_field( $value );
            }
        }

        return 'unknown';
    }

    private function get_signature_header( \WP_REST_Request $req ): string {
        if ( method_exists( $req, 'get_header' ) ) {
            $header = (string) $req->get_header( 'stripe-signature' );
            if ( '' !== $header ) {
                return $header;
            }
        }

        if ( method_exists( $req, 'get_headers' ) ) {
            $headers = (array) $req->get_headers();
            foreach ( [ 'stripe-signature', 'Stripe-Signature', 'HTTP_STRIPE_SIGNATURE' ] as $key ) {
                if ( isset( $headers[ $key ] ) ) {
                    $value = $headers[ $key ];
                    if ( is_array( $value ) ) {
                        return (string) reset( $value );
                    }
                    return (string) $value;
                }
            }
        }

        if ( isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) {
            return (string) $_SERVER['HTTP_STRIPE_SIGNATURE'];
        }

        return '';
    }

    private function emit_telemetry( string $metric, array $context = [] ): void {
        do_action( 'khm_membership_webhook_telemetry', $metric, $context );
        error_log( 'KHM webhook ' . $metric . ' ' . wp_json_encode( $context ) );
    }

    private function to_object( $value ) {
        if ( is_object( $value ) ) {
            return $value;
        }
        if ( ! is_array( $value ) ) {
            return (object) [];
        }
        return json_decode( wp_json_encode( $value ) );
    }

    private function resolve_or_create_user_from_session( $session, array $metadata, int $user_id ): int {
        if ( $user_id > 0 ) {
            return $user_id;
        }

        $email = '';
        if ( isset( $metadata['guest_email'] ) && is_email( $metadata['guest_email'] ) ) {
            $email = sanitize_email( (string) $metadata['guest_email'] );
        } elseif ( isset( $session->customer_details ) && isset( $session->customer_details->email ) && is_email( $session->customer_details->email ) ) {
            $email = sanitize_email( (string) $session->customer_details->email );
        } elseif ( isset( $session->customer_email ) && is_email( $session->customer_email ) ) {
            $email = sanitize_email( (string) $session->customer_email );
        }

        if ( $email === '' ) {
            return 0;
        }

        $existing_user = get_user_by( 'email', $email );
        if ( $existing_user && isset( $existing_user->ID ) ) {
            return (int) $existing_user->ID;
        }

        $username_base = sanitize_user( current( explode( '@', $email ) ) ?: 'member', true );
        if ( $username_base === '' ) {
            $username_base = 'member';
        }
        $username = $username_base;
        $attempt = 0;
        while ( username_exists( $username ) && $attempt < 10 ) {
            $attempt++;
            $username = $username_base . '_' . wp_rand( 1000, 99999 );
        }

        $created_user = wp_create_user( $username, wp_generate_password( 20, true ), $email );
        if ( is_wp_error( $created_user ) ) {
            return 0;
        }

        $new_user_id = (int) $created_user;
        if ( class_exists( 'WP_User' ) ) {
            $u = new \WP_User( $new_user_id );
            if ( method_exists( $u, 'set_role' ) ) {
                $u->set_role( 'subscriber' );
            }
        }
        update_user_meta( $new_user_id, 'khm_guest_account', 1 );
        update_user_meta( $new_user_id, 'khm_guest_origin', 'stripe_checkout' );
        update_user_meta( $new_user_id, 'khm_guest_created_at', current_time( 'mysql', 1 ) );

        return $new_user_id;
    }

    private function resolve_checkout_credits_amount( array $metadata ): int {
        foreach ( [ 'credits_amount', 'credits', 'credit_quantity', 'credit_units' ] as $key ) {
            if ( isset( $metadata[ $key ] ) ) {
                $value = (int) $metadata[ $key ];
                if ( $value > 0 ) {
                    return $value;
                }
            }
        }
        return 0;
    }

    private function record_credit_checkout( $session, int $user_id, int $credits_amount, string $operation_key ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_membership_credit_purchases';
        $charset_collate = $wpdb->get_charset_collate();
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                stripe_checkout_session_id VARCHAR(255) NOT NULL,
                stripe_payment_intent_id VARCHAR(255) NULL,
                stripe_charge_id VARCHAR(255) NULL,
                credits_added INT NOT NULL DEFAULT 0,
                status VARCHAR(32) NOT NULL DEFAULT 'completed',
                refund_event_id VARCHAR(255) NULL,
                manual_review_required TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                refunded_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_checkout_session (stripe_checkout_session_id),
                KEY idx_payment_intent (stripe_payment_intent_id),
                KEY idx_charge_id (stripe_charge_id),
                KEY idx_user_id (user_id)
            ) {$charset_collate}"
        );

        $session_id = isset( $session->id ) ? (string) $session->id : '';
        $payment_intent = isset( $session->payment_intent ) ? (string) $session->payment_intent : '';
        if ( $session_id === '' ) {
            throw new \RuntimeException( 'Missing session id for credit checkout.' );
        }

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE stripe_checkout_session_id = %s LIMIT 1",
                $session_id
            )
        );
        if ( $exists ) {
            return;
        }

        $credit_service = null;
        if ( class_exists( '\KHM\Services\CreditService' ) && class_exists( '\KHM\Services\MembershipRepository' ) && class_exists( '\KHM\Services\LevelRepository' ) ) {
            $credit_service = new \KHM\Services\CreditService( new \KHM\Services\MembershipRepository(), new \KHM\Services\LevelRepository() );
        }
        if ( ! $credit_service ) {
            throw new \RuntimeException( 'Credit service unavailable for payment-mode checkout.' );
        }

        $wpdb->query( 'START TRANSACTION' );
        try {
            $credited = $credit_service->addBonusCredits( $user_id, $credits_amount, 'stripe_checkout:' . $session_id );
            if ( ! $credited ) {
                throw new \RuntimeException( 'Failed to credit user after payment checkout.' );
            }

            $inserted = $wpdb->insert(
                $table,
                [
                    'user_id' => $user_id,
                    'stripe_checkout_session_id' => $session_id,
                    'stripe_payment_intent_id' => $payment_intent !== '' ? $payment_intent : null,
                    'credits_added' => $credits_amount,
                    'status' => 'completed',
                    'created_at' => current_time( 'mysql', 1 ),
                    'updated_at' => current_time( 'mysql', 1 ),
                ],
                [ '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
            );
            if ( false === $inserted ) {
                throw new \RuntimeException( 'Failed to record credit purchase.' );
            }

            $wpdb->query( 'COMMIT' );
        } catch ( \Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            $this->audit_handler( 'checkout.session.completed', $operation_key, $session_id, $user_id, 'failed', $e->getMessage() );
            throw $e;
        }
    }

    private function handle_credit_refund_reversal( $charge, string $event_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'khm_membership_credit_purchases';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return false;
        }

        $charge_id = isset( $charge->id ) ? (string) $charge->id : '';
        $payment_intent = isset( $charge->payment_intent ) ? (string) $charge->payment_intent : '';
        $row = null;
        if ( $payment_intent !== '' ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE stripe_payment_intent_id = %s LIMIT 1", $payment_intent ) );
        }
        if ( ! $row && $charge_id !== '' ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE stripe_charge_id = %s LIMIT 1", $charge_id ) );
        }
        if ( ! $row ) {
            return false;
        }

        if ( isset( $row->refunded_at ) && ! empty( $row->refunded_at ) ) {
            return true;
        }

        $user_id = (int) ( $row->user_id ?? 0 );
        $credits_added = (int) ( $row->credits_added ?? 0 );
        if ( $user_id <= 0 || $credits_added <= 0 ) {
            return false;
        }

        $credit_service = new \KHM\Services\CreditService( new \KHM\Services\MembershipRepository(), new \KHM\Services\LevelRepository() );
        $current_balance = $credit_service->getUserCredits( $user_id );
        if ( $current_balance < $credits_added ) {
            $wpdb->update(
                $table,
                [
                    'manual_review_required' => 1,
                    'refund_event_id' => $event_id,
                    'status' => 'manual_review',
                    'updated_at' => current_time( 'mysql', 1 ),
                ],
                [ 'id' => (int) $row->id ],
                [ '%d', '%s', '%s', '%s' ],
                [ '%d' ]
            );
            $this->audit_handler( 'charge.refunded', 'charge_refunded:' . (string) $charge_id, (string) $charge_id, $user_id, 'manual_review', 'Refund credits already consumed; manual review required.' );
            return true;
        }

        $deducted = $credit_service->useCredits( $user_id, $credits_added, 'refund_reversal', (int) $row->id );
        if ( ! $deducted ) {
            throw new \RuntimeException( 'Failed to deduct credits for refund reversal.' );
        }

        $wpdb->update(
            $table,
            [
                'refund_event_id' => $event_id,
                'status' => 'refunded',
                'refunded_at' => current_time( 'mysql', 1 ),
                'updated_at' => current_time( 'mysql', 1 ),
                'stripe_charge_id' => $charge_id !== '' ? $charge_id : ( $row->stripe_charge_id ?? null ),
            ],
            [ 'id' => (int) $row->id ],
            [ '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        $this->audit_handler( 'charge.refunded', 'charge_refunded:' . (string) $charge_id, (string) $charge_id, $user_id, 'success', 'Credits deducted due to refund.', [ 'credits_reversed' => $credits_added ] );
        return true;
    }

    private function get_user_id_by_stripe_subscription_or_customer( string $subscription_id, string $customer_id ): ?int {
        if ( $subscription_id !== '' ) {
            global $wpdb;
            $user_membership_table = $wpdb->prefix . 'user_membership';
            $user_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT user_id FROM {$user_membership_table} WHERE stripe_subscription_id = %s LIMIT 1",
                    $subscription_id
                )
            );
            if ( $user_id ) {
                return (int) $user_id;
            }
        }
        return $this->get_user_id_by_stripe_customer( $customer_id );
    }

    private function claim_operation( string $operation_key, string $event_id, string $event_type, string $object_id = '', ?int $user_id = null ): bool {
        $claim = MembershipWebhookOperationStore::claim( $operation_key, $event_id, $event_type, $object_id !== '' ? $object_id : null, $user_id );
        if ( 'claimed' === $claim ) {
            return true;
        }
        if ( 'duplicate' === $claim ) {
            $this->audit_handler( $event_type, $operation_key, $object_id, $user_id, 'duplicate', 'Operation already completed.' );
            return false;
        }

        $this->audit_handler( $event_type, $operation_key, $object_id, $user_id, 'busy', 'Operation already processing in another worker.' );
        return false;
    }

    private function mark_operation_succeeded( string $operation_key ): void {
        MembershipWebhookOperationStore::mark_succeeded( $operation_key );
    }

    private function mark_operation_failed( string $operation_key, \Throwable $e ): void {
        MembershipWebhookOperationStore::mark_failed( $operation_key, $e->getMessage() );
    }

    private function is_stale_subscription_event( string $subscription_id, int $event_created ): bool {
        if ( '' === $subscription_id || $event_created <= 0 ) {
            return false;
        }
        $cursor = $this->get_subscription_event_cursor( $subscription_id );
        return $cursor > 0 && $event_created < $cursor;
    }

    private function update_subscription_event_cursor( string $subscription_id, int $event_created ): void {
        if ( '' === $subscription_id || $event_created <= 0 ) {
            return;
        }
        $cursor_key = $this->get_subscription_event_cursor_key( $subscription_id );
        $cursor = (int) get_option( $cursor_key, 0 );
        if ( $event_created > $cursor ) {
            update_option( $cursor_key, $event_created );
        }
    }

    private function get_subscription_event_cursor( string $subscription_id ): int {
        if ( '' === $subscription_id ) {
            return 0;
        }
        return (int) get_option( $this->get_subscription_event_cursor_key( $subscription_id ), 0 );
    }

    private function get_subscription_event_cursor_key( string $subscription_id ): string {
        return 'khm_membership_sub_event_cursor_' . md5( $subscription_id );
    }

    private function extract_subscription_price_id( $subscription ): string {
        if ( ! isset( $subscription->items ) || ! isset( $subscription->items->data ) || ! is_array( $subscription->items->data ) ) {
            return '';
        }
        foreach ( $subscription->items->data as $item ) {
            if ( is_object( $item ) && isset( $item->price ) && is_object( $item->price ) && ! empty( $item->price->id ) ) {
                return sanitize_text_field( (string) $item->price->id );
            }
            if ( is_array( $item ) && isset( $item['price'] ) && is_array( $item['price'] ) && ! empty( $item['price']['id'] ) ) {
                return sanitize_text_field( (string) $item['price']['id'] );
            }
        }
        return '';
    }

    private function find_plan_id_by_tier_slug( string $tier_slug ): int {
        $tier_slug = sanitize_key( $tier_slug );
        if ( '' === $tier_slug ) {
            return 0;
        }
        global $wpdb;
        $membership_tier_table = $wpdb->prefix . 'membership_tier';
        $plan_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$membership_tier_table} WHERE slug = %s LIMIT 1",
                $tier_slug
            )
        );
        return $plan_id ? (int) $plan_id : 0;
    }

    private function log_security_anomaly( string $code, string $message, array $context = [] ): void {
        $payload = [
            'code' => $code,
            'message' => $message,
            'context' => $context,
        ];
        error_log( 'KHM membership security anomaly ' . wp_json_encode( $payload ) );
        do_action( 'khm_membership_webhook_security_anomaly', $payload );
        $this->emit_telemetry( 'webhook.security_anomaly', $payload );
    }

    private function audit_handler(
        string $event_type,
        string $operation_key,
        string $object_id,
        ?int $user_id,
        string $outcome,
        string $message,
        array $context = []
    ): void {
        MembershipWebhookAuditLogger::log(
            $operation_key,
            $event_type,
            $outcome,
            $object_id,
            $user_id,
            $message,
            $context,
            $operation_key
        );
    }
}
