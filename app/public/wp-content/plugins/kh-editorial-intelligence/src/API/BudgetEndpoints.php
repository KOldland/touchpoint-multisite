<?php
namespace KH\Editorial\API;

use KH\Editorial\Core\Container;

/**
 * BudgetEndpoints
 *
 * Exposes the legacy REST API routes for budget management within the new KH Editorial Intelligence plugin.
 */
class BudgetEndpoints {

    protected string $namespace = 'editorial/v1';

    /**
     * Register Endpoints.
     */
    public function register(): void {
        register_rest_route($this->namespace, '/budgets', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_budgets'],
                'permission_callback' => fn() => current_user_can('edit_posts'),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'update_budget'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ]
        ]);
    }

    /**
     * Get budgets
     */
    public function get_budgets(\WP_REST_Request $request) {
        $user_id = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'ai_budgets';
        
        $budget = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE scope = 'user' AND scope_id = %s", $user_id),
            ARRAY_A
        );

        if (!$budget) {
            $budget = [
                'scope'       => 'user',
                'scope_id'    => (string) $user_id,
                'token_limit' => 0,
                'token_used'  => 0,
                'reset_at'    => date('Y-m-d H:i:s', strtotime('+1 month'))
            ];
        }

        return new \WP_REST_Response([
            'budget' => $budget,
        ], 200);
    }

    /**
     * Update budget
     */
    public function update_budget(\WP_REST_Request $request) {
        $params = $request->get_params();

        if (!isset($params['scope']) || !isset($params['scope_id']) || !isset($params['token_limit'])) {
            return new \WP_Error('missing_fields', 'Required fields: scope, scope_id, token_limit', ['status' => 400]);
        }

        $scope       = sanitize_text_field($params['scope']);
        $scope_id    = sanitize_text_field($params['scope_id']);
        $token_limit = intval($params['token_limit']);

        global $wpdb;
        $table = $wpdb->prefix . 'ai_budgets';

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE scope = %s AND scope_id = %s", $scope, $scope_id)
        );

        if ($existing) {
            $wpdb->update(
                $table,
                ['token_limit' => $token_limit],
                ['id' => $existing->id]
            );
        } else {
            $wpdb->insert($table, [
                'scope'       => $scope,
                'scope_id'    => $scope_id,
                'period'      => 'monthly',
                'token_limit' => $token_limit,
                'token_used'  => 0,
                'reset_at'    => date('Y-m-d H:i:s', strtotime('+1 month')),
            ]);
        }

        return new \WP_REST_Response([
            'message'     => 'Budget updated',
            'scope'       => $scope,
            'scope_id'    => $scope_id,
            'token_limit' => $token_limit,
        ], 200);
    }
}
