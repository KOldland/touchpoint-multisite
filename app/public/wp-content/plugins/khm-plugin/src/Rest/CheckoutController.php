<?php

namespace KHM\Rest;

use WP_REST_Request;
use WP_REST_Response;
use KHM\Services\LevelRepository;
use KHM\Gateways\StripeGateway;
use KHM\Services\LevelPriceResolver;

class CheckoutController {
    private LevelRepository $levels;

    public function __construct( ?LevelRepository $levels = null ) {
        $this->levels = $levels ?: new LevelRepository();
    }

    public function register(): void {
        add_action('rest_api_init', function() {
            register_rest_route('khm/v1', '/checkout/subscription', [
                'methods' => 'POST',
                'callback' => [ $this, 'create_subscription_checkout' ],
                'permission_callback' => '__return_true',
                'args' => [
                    'membership_level_id' => [ 'required' => true, 'type' => 'integer' ],
                    'email' => [ 'required' => false, 'type' => 'string' ],
                    'currency' => [ 'required' => false, 'type' => 'string' ],
                    'interval' => [ 'required' => false, 'type' => 'string' ],
                ],
            ]);
        });
    }

    public function create_subscription_checkout( WP_REST_Request $request ) {
        $levelId = (int) $request->get_param('membership_level_id');
        if ( $levelId < 1 ) {
            return new WP_REST_Response([ 'message' => __( 'Invalid membership level.', 'khm-membership' ) ], 400);
        }

        if ( ! function_exists('khm_get_membership_level') ) {
            return new WP_REST_Response([ 'message' => __( 'Membership system unavailable.', 'khm-membership' ) ], 500);
        }

        $level = khm_get_membership_level($levelId);
        if ( ! $level ) {
            return new WP_REST_Response([ 'message' => __( 'Membership level not found.', 'khm-membership' ) ], 404);
        }

        $priceId = $this->resolve_price_id($levelId, $request);
        if ( empty( $priceId ) ) {
            return new WP_REST_Response([ 
                'message' => sprintf(
                    __( 'No Stripe price configured for membership level %d. Please configure a Stripe Price ID in the membership level settings.', 'khm-membership' ),
                    $levelId
                )
            ], 400);
        }

        $secret = get_option('khm_stripe_secret_key', '');
        if ( empty( $secret ) ) {
            return new WP_REST_Response([ 'message' => __( 'Stripe is not configured.', 'khm-membership' ) ], 500);
        }

        $userId = get_current_user_id();
        $email = '';

        if ( $userId ) {
            $user = get_user_by('id', $userId);
            $email = $user ? $user->user_email : '';
        } else {
            $email = sanitize_email($request->get_param('email') ?? '');
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            return new WP_REST_Response([ 'message' => __( 'Valid email required.', 'khm-membership' ) ], 400);
        }

        $successUrl = apply_filters('khm_stripe_checkout_success_url', home_url('/account/'), $levelId, $userId ?: null);
        $cancelUrl  = apply_filters('khm_stripe_checkout_cancel_url', home_url('/checkout/'), $levelId, $userId ?: null);

        $metadata = [
            'purchase_type' => 'subscription',
            'membership_level_id' => (string) $levelId,
            'user_id' => $userId ? (string) $userId : '',
        ];

        try {
            $gateway = new StripeGateway([
                'secret_key' => $secret,
                'publishable_key' => get_option('khm_stripe_publishable_key', ''),
                'environment' => get_option('khm_stripe_environment', 'production'),
            ]);

        $params = [
            'mode' => 'subscription',
            'line_items' => [ [ 'price' => $priceId, 'quantity' => 1 ] ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $email,
            'allow_promotion_codes' => $this->resolve_allow_promotion_codes( $levelId ),
            'metadata' => $metadata,
        ];

            $params = apply_filters('khm_stripe_checkout_session_params', $params, $levelId, $userId ?: null, $email);

            $session = \Stripe\Checkout\Session::create($params);
        } catch ( \Throwable $e ) {
            error_log('Stripe checkout session error: ' . $e->getMessage());
            return new WP_REST_Response([ 'message' => __( 'Unable to create checkout session.', 'khm-membership' ) ], 500);
        }

        if ( empty( $session->url ) ) {
            return new WP_REST_Response([ 'message' => __( 'Checkout session missing URL.', 'khm-membership' ) ], 500);
        }

        return new WP_REST_Response([ 'url' => $session->url ], 200);
    }

    private function resolve_price_id( int $levelId, WP_REST_Request $request ): ?string {
        $currency = $request->get_param( 'currency' );
        $interval = $request->get_param( 'interval' );

        if ( function_exists( 'khm_get_level_price_id' ) ) {
            $priceId = khm_get_level_price_id(
                $levelId,
                is_string( $currency ) ? $currency : null,
                is_string( $interval ) && $interval !== '' ? $interval : 'monthly'
            );
        } elseif ( class_exists( LevelPriceResolver::class ) ) {
            $resolver = new LevelPriceResolver( $this->levels );
            $priceId = $resolver->get_price_id(
                $levelId,
                is_string( $currency ) ? $currency : null,
                is_string( $interval ) && $interval !== '' ? $interval : 'monthly'
            );
        } else {
            $priceId = null;
        }

        if ( $priceId ) {
            return $priceId;
        }

        // Final fallback: legacy filter only
        $filtered = apply_filters('khm_stripe_membership_price_map', null, $levelId);
        if ( is_string( $filtered ) && $filtered !== '' ) {
            return $filtered;
        }
        if ( is_array( $filtered ) && isset( $filtered[ $levelId ] ) ) {
            return $filtered[ $levelId ];
        }

        return null;
    }

    private function resolve_allow_promotion_codes( int $levelId ): bool {
        $meta = $this->levels->getMeta( $levelId, 'khm_level_meta', [] );
        if ( is_string( $meta ) ) {
            $decoded = json_decode( $meta, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $meta = $decoded;
            }
        }

        if ( is_array( $meta ) ) {
            $commerce = $meta['commerce'] ?? null;
            if ( is_array( $commerce ) && array_key_exists( 'allow_promotion_codes', $commerce ) ) {
                return (bool) $commerce['allow_promotion_codes'];
            }
        }

        return true;
    }
}
