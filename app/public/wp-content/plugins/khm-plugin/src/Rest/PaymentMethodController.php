<?php

namespace KHM\Rest;

use WP_REST_Request;
use WP_REST_Response;
use KHM\Services\PaymentMethodService;
use KHM\Services\OrderRepository;

class PaymentMethodController {
    private PaymentMethodService $service;

    public function __construct(?PaymentMethodService $service = null)
    {
        $this->service = $service ?: new PaymentMethodService(new OrderRepository());
    }

    public function register(): void {
        add_action('rest_api_init', function() {
            register_rest_route('khm/v1', '/payment-method/setup-intent', [
                'methods' => 'POST',
                'callback' => [ $this, 'setup_intent' ],
                'permission_callback' => function() { return is_user_logged_in(); },
                'args' => [
                    'level_id' => [ 'required' => true, 'type' => 'integer' ],
                ],
            ]);

            register_rest_route('khm/v1', '/payment-method/update', [
                'methods' => 'POST',
                'callback' => [ $this, 'update' ],
                'permission_callback' => function() { return is_user_logged_in(); },
                'args' => [
                    'level_id' => [ 'required' => true, 'type' => 'integer' ],
                    'payment_method_id' => [ 'required' => true, 'type' => 'string' ],
                ],
            ]);
        });
    }

    public function setup_intent( WP_REST_Request $request ) {
        $userId = get_current_user_id();
        $levelId = (int) $request->get_param('level_id');

        if (!$userId || !$levelId) {
            return new WP_REST_Response([ 'message' => __('Invalid request.', 'khm-membership') ], 400);
        }

        $result = $this->service->createSetupIntent($userId, $levelId);
        $status = !empty($result['success']) ? 200 : 400;
        return new WP_REST_Response($result, $status);
    }

    public function update( WP_REST_Request $request ) {
        $userId = get_current_user_id();
        $levelId = (int) $request->get_param('level_id');
        $pm = (string) $request->get_param('payment_method_id');

        if (!$userId || !$levelId || empty($pm)) {
            return new WP_REST_Response([ 'message' => __('Invalid request.', 'khm-membership') ], 400);
        }

        $result = $this->service->applyPaymentMethod($userId, $levelId, $pm);
        $status = !empty($result['success']) ? 200 : 400;
        return new WP_REST_Response($result, $status);
    }
}
