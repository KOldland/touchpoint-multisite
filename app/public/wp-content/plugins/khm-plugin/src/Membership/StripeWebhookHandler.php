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

        // --- Signature Validation (placeholder) ---
        // In a real application, you would use the Stripe SDK to verify the signature.
        // For now, we will just parse the JSON.
        //
        // try {
        //     $event = \Stripe\Webhook::constructEvent(
        //         $payload, $sig_header, $endpoint_secret
        //     );
        // } catch(\UnexpectedValueException $e) {
        //     // Invalid payload
        //     return new \WP_REST_Response(['error' => 'Invalid payload'], 400);
        // } catch(\Stripe\Exception\SignatureVerificationException $e) {
        //     // Invalid signature
        //     return new \WP_REST_Response(['error' => 'Invalid signature'], 400);
        // }

        $event = json_decode($payload);

        if ( !isset($event->type) ) {
            return new \WP_REST_Response(['error' => 'Invalid event'], 400);
        }

        // --- Event Handling ---
        switch ($event->type) {
            case 'invoice.paid':
                // Update user_membership status to 'active'
                $this->handle_invoice_paid($event->data->object);
                break;
            case 'invoice.payment_failed':
                // Update user_membership status to 'past_due'
                $this->handle_payment_failed($event->data->object);
                break;
            case 'customer.subscription.updated':
                // Update user_membership status, tier, etc.
                $this->handle_subscription_updated($event->data->object);
                break;
            case 'checkout.session.completed':
                // Fulfillment for one-time payments or initial subscription
                $this->handle_checkout_session_completed($event->data->object);
                break;
            default:
                // Unhandled event type
        }

        return new \WP_REST_Response(['status' => 'success'], 200);
    }

    private function handle_invoice_paid($invoice) {
        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $stripe_customer_id = $invoice->customer;

        $user = get_user_by('meta_key', 'stripe_customer_id', $stripe_customer_id);
        if ($user) {
            $wpdb->update(
                $user_membership_table,
                ['status' => 'active'],
                ['user_id' => $user->ID]
            );
        }
    }

    private function handle_payment_failed($invoice) {
        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $stripe_customer_id = $invoice->customer;

        $user = get_user_by('meta_key', 'stripe_customer_id', $stripe_customer_id);
        if ($user) {
            $wpdb->update(
                $user_membership_table,
                ['status' => 'past_due'],
                ['user_id' => $user->ID]
            );
        }
    }

    private function handle_subscription_updated($subscription) {
        global $wpdb;
        $user_membership_table = $wpdb->prefix . 'user_membership';
        $stripe_customer_id = $subscription->customer;
        
        $user = get_user_by('meta_key', 'stripe_customer_id', $stripe_customer_id);
        if ($user) {
            $wpdb->update(
                $user_membership_table,
                [
                    'status' => $subscription->status,
                    'tier_id' => $this->get_plan_id_from_stripe_plan($subscription->items->data[0]->plan->id),
                    'trial_ends_at' => $subscription->trial_end ? date('Y-m-d H:i:s', $subscription->trial_end) : null,
                    'cancelled_at' => $subscription->cancel_at_period_end ? date('Y-m-d H:i:s', $subscription->cancel_at) : null,
                ],
                ['user_id' => $user->ID]
            );
        }
    }
    
    private function handle_checkout_session_completed($session) {
        // This is where you would fulfill the purchase, e.g. by creating a
        // user membership record. This logic might overlap with the signup endpoint.
    }

    private function get_plan_id_from_stripe_plan($stripe_plan_id) {
        // You would need to implement a mapping from Stripe plan IDs to your internal plan IDs
        return 0;
    }
}
