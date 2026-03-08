<?php

namespace KHM\Membership;

use KHM\Services\MembershipRepository;

class PriceOverrideEndpoint {
    private const ROUTE = '/price-override';
    private const MIN_AMOUNT_CENTS = 0;
    private const MAX_AMOUNT_CENTS = 5000000;

    public function register_routes(): void {
        register_rest_route( 'kh-membership/v1', self::ROUTE, [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' ) || current_user_can( 'manage_khm' );
            },
        ] );
    }

    public function handle_request( \WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $payload = is_array( $payload ) ? $payload : [];

        $referenceId = sanitize_text_field( (string) ( $payload['reference_id'] ?? 'default' ) );
        $currency = sanitize_text_field( (string) ( $payload['currency'] ?? 'AUD' ) );
        $items = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : [];

        if ( '' === $referenceId || empty( $items ) ) {
            return new \WP_REST_Response( [
                'message' => 'reference_id and items are required.',
            ], 400 );
        }

        $normalizedItems = [];
        foreach ( $items as $item ) {
            $amount = isset( $item['amount_cents'] ) ? (int) $item['amount_cents'] : 0;
            if ( $amount < self::MIN_AMOUNT_CENTS || $amount > self::MAX_AMOUNT_CENTS ) {
                return new \WP_REST_Response( [
                    'message' => 'Override amount is outside the allowed range.',
                ], 422 );
            }

            $normalizedItems[] = [
                'key' => sanitize_key( (string) ( $item['key'] ?? '' ) ),
                'label' => sanitize_text_field( (string) ( $item['label'] ?? '' ) ),
                'amount_cents' => $amount,
            ];
        }

        $repo = new MembershipRepository();
        $saved = $repo->savePriceReviewOverride( $referenceId, [
            'reference_id' => $referenceId,
            'currency' => $currency,
            'items' => $normalizedItems,
            'updated_by' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
            'updated_at' => gmdate( 'c' ),
        ] );

        return new \WP_REST_Response( [
            'ok' => $saved,
            'reference_id' => $referenceId,
            'currency' => $currency,
            'items' => $normalizedItems,
            'total_amount_cents' => array_sum( array_map( static function ( array $item ): int {
                return (int) $item['amount_cents'];
            }, $normalizedItems ) ),
        ], $saved ? 200 : 500 );
    }
}
