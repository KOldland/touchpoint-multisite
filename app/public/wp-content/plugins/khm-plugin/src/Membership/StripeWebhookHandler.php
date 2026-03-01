<?php

namespace KHM\Membership;

class StripeWebhookHandler {
    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
        add_action( 'khm_process_membership_stripe_webhook_event', [ $this, 'process_queued_event' ], 10, 1 );
        add_action( 'khm_membership_webhook_cleanup', [ ProcessedWebhook::class, 'cleanup_old_events' ] );
        ProcessedWebhook::maybe_schedule_cleanup();
    }

    public function register_routes() {
        register_rest_route('kh-membership/v1', '/webhook/stripe', [
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
            return new \WP_REST_Response(['error' => 'Invalid signature'], 400);
        }

        if ( !isset($event->type) || !isset($event->id) ) {
            $this->emit_telemetry( 'webhook.invalid_event', [ 'payload_hash' => hash( 'sha256', (string) $payload ) ] );
            return new \WP_REST_Response(['error' => 'Invalid event'], 400);
        }

        $event_id = (string) $event->id;
        $event_type = (string) $event->type;

        $claim_status = ProcessedWebhook::claim_event( $event_id, $event_type, (string) $payload );
        if ( 'processed' === $claim_status ) {
            return new \WP_REST_Response(['status' => 'success', 'note' => 'already processed'], 200);
        }
        if ( 'processing' === $claim_status ) {
            return new \WP_REST_Response(['status' => 'success', 'note' => 'already processing'], 200);
        }

        $job = [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'data_object' => isset($event->data->object) ? json_decode( wp_json_encode( $event->data->object ), true ) : [],
            'trace_id' => wp_generate_uuid4(),
        ];

        if ( ! $this->enqueue_event_job( $job ) ) {
            ProcessedWebhook::mark_failed( $event_id, 'Failed to enqueue webhook job.' );
            $this->emit_telemetry( 'webhook.queue_failed', [ 'event_id' => $event_id, 'event_type' => $event_type ] );
            return new \WP_REST_Response(['error' => 'Failed to queue event'], 500);
        }

        $latency_ms = (int) round( ( microtime( true ) - $started_at ) * 1000 );
        $this->emit_telemetry( 'webhook.received', [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'latency_ms' => $latency_ms,
        ] );

        return new \WP_REST_Response(['status' => 'queued', 'id' => $event_id, 'type' => $event_type], 200);
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

        try {
            $object = $this->to_object( $job['data_object'] ?? [] );
            $this->route_event( $event_type, $object, $event_id );

            ProcessedWebhook::mark_processed( $event_id, 'Processed successfully.' );
            $this->emit_telemetry( 'webhook.processed', [
                'event_id' => $event_id,
                'event_type' => $event_type,
                'latency_ms' => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
            ] );
        } catch ( \Throwable $e ) {
            ProcessedWebhook::mark_failed( $event_id, $e->getMessage() );
            error_log( 'Stripe webhook processing failed for ' . $event_id . ': ' . $e->getMessage() );
            $this->emit_telemetry( 'webhook.failed', [
                'event_id' => $event_id,
                'event_type' => $event_type,
                'error' => $e->getMessage(),
            ] );
        }
    }

    private function route_event( string $event_type, $object, string $event_id ) : void {
        switch ( $event_type ) {
            case 'checkout.session.completed':
                $this->handle_checkout_session_completed( $object );
                break;
            case 'invoice.paid':
                $this->handle_invoice_paid( $object );
                break;
            case 'invoice.payment_failed':
                $this->handle_payment_failed( $object );
                break;
            case 'customer.subscription.updated':
                $this->handle_subscription_updated( $object );
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

    private function handle_checkout_session_completed($session) {
        if ( !isset($session->mode) || strtolower($session->mode) !== 'subscription' ) {
            return;
        }

        $metadata = isset($session->metadata) ? (array) $session->metadata : [];
        $user_id = isset($metadata['user_id']) ? intval($metadata['user_id']) : 0;
        $plan_id = isset($metadata['membership_level_id']) ? intval($metadata['membership_level_id']) : 0;

        if ( !$user_id || !$plan_id ) {
            error_log('Stripe webhook: Missing user_id or plan_id in checkout.session.completed metadata');
            return;
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        // Get subscription details from session
        $stripe_customer_id = isset($session->customer) ? $session->customer : null;
        $stripe_subscription_id = isset($session->subscription) ? $session->subscription : null;

        $wpdb->replace($user_membership_table, [
            'user_id' => $user_id,
            'tier_id' => $plan_id,
            'stripe_customer_id' => $stripe_customer_id,
            'stripe_subscription_id' => $stripe_subscription_id,
            'status' => 'active',
            'started_at' => current_time('mysql', 1),
        ]);

        $schedule_id = isset( $metadata['schedule_id'] ) ? absint( $metadata['schedule_id'] ) : 0;
        if ( $schedule_id > 0 ) {
            $this->record_paid_attribution( $user_id, $plan_id, $schedule_id );
        }
    }

    private function handle_invoice_paid($invoice) {
        $user_id = $this->get_user_id_by_stripe_customer($invoice->customer);
        if ( !$user_id ) {
            return;
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        $wpdb->update(
            $user_membership_table,
            ['status' => 'active'],
            ['user_id' => $user_id]
        );

        if ( ! empty( $invoice->payment_intent ) ) {
            update_user_meta( $user_id, 'khm_last_payment_intent_id', sanitize_text_field( (string) $invoice->payment_intent ) );
        }
        update_user_meta( $user_id, 'khm_last_payment_status', 'paid' );
    }

    private function handle_payment_failed($invoice) {
        $user_id = $this->get_user_id_by_stripe_customer($invoice->customer);
        if ( !$user_id ) {
            return;
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        $wpdb->update(
            $user_membership_table,
            ['status' => 'past_due'],
            ['user_id' => $user_id]
        );

        do_action( 'khm_membership_invoice_payment_failed', $user_id, $invoice );
    }

    private function handle_subscription_updated($subscription) {
        $user_id = $this->get_user_id_by_stripe_customer($subscription->customer);
        if ( !$user_id ) {
            return;
        }

        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';

        $update_data = [
            'status' => $subscription->status,
            'stripe_subscription_id' => $subscription->id,
        ];

        if ( isset($subscription->trial_end) && $subscription->trial_end ) {
            $update_data['trial_ends_at'] = gmdate('Y-m-d H:i:s', $subscription->trial_end);
        }

        if ( isset($subscription->cancel_at_period_end) && $subscription->cancel_at_period_end ) {
            if ( isset($subscription->cancel_at) && $subscription->cancel_at ) {
                $update_data['cancelled_at'] = gmdate('Y-m-d H:i:s', $subscription->cancel_at);
            }
        } else {
            $update_data['cancelled_at'] = null;
        }

        $wpdb->update(
            $user_membership_table,
            $update_data,
            ['user_id' => $user_id]
        );
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
                'status' => 'cancelled',
                'cancelled_at' => current_time('mysql', 1)
            ],
            ['user_id' => $user_id]
        );
    }

    private function handle_charge_refunded( $charge, string $event_id ): void {
        do_action( 'khm_membership_stripe_charge_refunded', $charge, $event_id );
        if ( function_exists( 'khm_handle_stripe_charge_refunded' ) ) {
            khm_handle_stripe_charge_refunded( $charge, $event_id );
        }
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

    private function record_paid_attribution( int $user_id, int $plan_id, int $schedule_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'promotion_attribution';

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE user_id = %d AND schedule_id = %d AND conversion_type = %s LIMIT 1",
                $user_id,
                $schedule_id,
                'paid'
            )
        );

        if ( $existing ) {
            return;
        }

        $user = get_user_by( 'id', $user_id );
        $wpdb->insert(
            $table,
            [
                'schedule_id' => $schedule_id,
                'user_id' => $user_id,
                'user_email' => $user ? $user->user_email : '',
                'conversion_type' => 'paid',
                'plan_id' => $plan_id,
                'reference_metadata' => wp_json_encode( [ 'source' => 'stripe.checkout.session.completed' ] ),
                'created_at' => current_time( 'mysql', 1 ),
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ]
        );
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

        $webhook_secret = trim( (string) get_option('khm_stripe_webhook_secret', '') );
        if ( '' === $webhook_secret ) {
            error_log( 'Stripe webhook rejected: khm_stripe_webhook_secret not configured.' );
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

        $window_seconds = (int) apply_filters( 'khm_membership_webhook_rate_limit_window', 60 );
        $max_requests = (int) apply_filters( 'khm_membership_webhook_rate_limit_max_requests', 100 );
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
}
