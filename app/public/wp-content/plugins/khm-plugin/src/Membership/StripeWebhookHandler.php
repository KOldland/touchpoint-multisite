<?php

namespace KHM\Membership;

class StripeWebhookHandler {
    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route('kh-membership/v1', '/webhook/stripe', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => '__return_true'
        ]);
    }

    public function handle_request( \WP_REST_Request $req ) {
        $payload = $req->get_body();
        $sig_header = $req->get_header('stripe-signature');
        $event = null;

        // --- Signature Validation ---
        $webhook_secret = get_option('khm_stripe_webhook_secret', '');

        if ( !empty($webhook_secret) ) {
            try {
                \Stripe\Stripe::setApiKey(get_option('khm_stripe_secret_key', ''));
                $event = \Stripe\Webhook::constructEvent(
                    $payload, $sig_header, $webhook_secret
                );
            } catch(\UnexpectedValueException $e) {
                return new \WP_REST_Response(['error' => 'Invalid payload'], 400);
            } catch(\Stripe\Exception\SignatureVerificationException $e) {
                return new \WP_REST_Response(['error' => 'Invalid signature'], 400);
            }
        } else {
            // Fallback for development without signature verification
            $event = json_decode($payload);
        }

        if ( !isset($event->type) || !isset($event->id) ) {
            return new \WP_REST_Response(['error' => 'Invalid event'], 400);
        }

        // --- Idempotency Check ---
        if ( $this->has_processed_event($event->id) ) {
            // Event already processed, return success
            return new \WP_REST_Response(['status' => 'success', 'note' => 'already processed'], 200);
        }

        // --- Event Handling ---
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handle_checkout_session_completed($event->data->object);
                break;
            case 'invoice.paid':
                $this->handle_invoice_paid($event->data->object);
                break;
            case 'invoice.payment_failed':
                $this->handle_payment_failed($event->data->object);
                break;
            case 'customer.subscription.updated':
                $this->handle_subscription_updated($event->data->object);
                break;
            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted($event->data->object);
                break;
            default:
                // Unhandled event type - still mark as processed
        }

        // Mark event as processed
        $this->mark_event_processed($event->id, $event->type);

        return new \WP_REST_Response(['status' => 'success'], 200);
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

    private function has_processed_event($event_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'stripe_webhook_events';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE event_id = %s LIMIT 1",
            $event_id
        ));

        return !empty($exists);
    }

    private function mark_event_processed($event_id, $event_type) {
        global $wpdb;
        $table = $wpdb->prefix . 'stripe_webhook_events';

        // Create table if it doesn't exist
        $charset_collate = $wpdb->get_charset_collate();
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table'") != $table ) {
            $sql = "CREATE TABLE $table (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id VARCHAR(255) UNIQUE NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                processed_at DATETIME NOT NULL,
                KEY event_id_idx (event_id)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        $wpdb->insert($table, [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'processed_at' => current_time('mysql', 1)
        ]);
    }
}
