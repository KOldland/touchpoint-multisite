<?php

namespace KHM\Rest;

use WP_REST_Request;
use WP_REST_Response;
use KHM\Services\SubscriptionManagementService;
use KHM\Services\OrderRepository;
use KHM\Services\MembershipRepository;

class SubscriptionController {
    private SubscriptionManagementService $service;

    public function __construct( ?SubscriptionManagementService $service = null ) {
        $this->service = $service ?: new SubscriptionManagementService(
            new OrderRepository(),
            new MembershipRepository()
        );
    }

    public function register(): void {
        add_action('rest_api_init', function() {
            register_rest_route('khm/v1', '/subscription/cancel', [
                'methods' => 'POST',
                'callback' => [ $this, 'cancel' ],
                'permission_callback' => function() { return is_user_logged_in(); },
                'args' => [
                    'level_id' => [ 'required' => true, 'type' => 'integer' ],
                    'at_period_end' => [ 'required' => false, 'type' => 'boolean', 'default' => true ],
                ],
            ]);

            register_rest_route('khm/v1', '/subscription/reactivate', [
                'methods' => 'POST',
                'callback' => [ $this, 'reactivate' ],
                'permission_callback' => function() { return is_user_logged_in(); },
                'args' => [
                    'level_id' => [ 'required' => true, 'type' => 'integer' ],
                ],
            ]);

            register_rest_route('khm/v1', '/subscription/pause', [
                'methods' => 'POST',
                'callback' => [ $this, 'pause' ],
                'permission_callback' => function() { return is_user_logged_in(); },
                'args' => [
                    'level_id' => [ 'required' => true, 'type' => 'integer' ],
                    'resume_at' => [ 'required' => false, 'type' => 'string' ],
                ],
            ]);

            register_rest_route('khm/v1', '/subscription/resume', [
                'methods' => 'POST',
                'callback' => [ $this, 'resume' ],
                'permission_callback' => function() { return is_user_logged_in(); },
                'args' => [
                    'level_id' => [ 'required' => true, 'type' => 'integer' ],
                ],
            ]);
        });
    }

    public function cancel( WP_REST_Request $request ) {
        $userId = get_current_user_id();
        $levelId = (int) $request->get_param('level_id');
        $atPeriodEnd = (bool) $request->get_param('at_period_end');

        if (!$userId || !$levelId) {
            return new WP_REST_Response([ 'message' => __('Invalid request.', 'khm-membership') ], 400);
        }

        $result = $this->service->cancel($userId, $levelId, $atPeriodEnd);
        $status = $result['success'] ? 200 : 400;
        return new WP_REST_Response($result, $status);
    }

    public function reactivate( WP_REST_Request $request ) {
        $userId = get_current_user_id();
        $levelId = (int) $request->get_param('level_id');

        if (!$userId || !$levelId) {
            return new WP_REST_Response([ 'message' => __('Invalid request.', 'khm-membership') ], 400);
        }

        $result = $this->service->reactivate($userId, $levelId);
        $status = $result['success'] ? 200 : 400;
        return new WP_REST_Response($result, $status);
    }

    public function pause( WP_REST_Request $request ) {
        $userId  = get_current_user_id();
        $levelId = (int) $request->get_param('level_id');
        $resumeRaw = $request->get_param('resume_at');

        if (!$userId || !$levelId) {
            return new WP_REST_Response([ 'message' => __('Invalid request.', 'khm-membership') ], 400);
        }

        $resumeAt = null;
        if ( ! empty( $resumeRaw ) ) {
            try {
                $resumeAt = new \DateTime( (string) $resumeRaw );
            } catch ( \Exception $e ) {
                return new WP_REST_Response([ 'message' => __('Invalid resume date.', 'khm-membership') ], 400);
            }
        }

        $result = $this->service->pause( $userId, $levelId, $resumeAt );
        $status = $result['success'] ? 200 : 400;
        return new WP_REST_Response( $result, $status );
    }

    public function resume( WP_REST_Request $request ) {
        $userId  = get_current_user_id();
        $levelId = (int) $request->get_param('level_id');

        if (!$userId || !$levelId) {
            return new WP_REST_Response([ 'message' => __('Invalid request.', 'khm-membership') ], 400);
        }

        $result = $this->service->resume( $userId, $levelId );
        $status = $result['success'] ? 200 : 400;
        return new WP_REST_Response( $result, $status );
    }
}
