<?php
namespace KH\Editorial\API;

use KH\Editorial\Core\Container;

/**
 * AuditEndpoints
 *
 * Exposes the legacy REST API routes for audit logs within the new KH Editorial Intelligence plugin.
 */
class AuditEndpoints {
    
    protected string $namespace = 'editorial/v1';

    /**
     * Register Endpoints.
     */
    public function register(): void {
        register_rest_route($this->namespace, '/audit', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_audit_logs'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * Get audit logs
     */
    public function get_audit_logs(\WP_REST_Request $request) {
        $params = $request->get_params();
        $job_id = !empty($params['job_id']) ? sanitize_text_field($params['job_id']) : null;
        $limit  = intval($params['limit'] ?? 50);

        global $wpdb;
        $table_audit = $wpdb->prefix . 'ai_audit';

        $query = "SELECT * FROM {$table_audit}";
        $query_args = [];

        if ($job_id) {
            $query .= " WHERE job_id = %s";
            $query_args[] = $job_id;
        }

        $query .= " ORDER BY created_at DESC LIMIT %d";
        $query_args[] = $limit;

        $logs = $wpdb->get_results($wpdb->prepare($query, ...$query_args), ARRAY_A);

        return new \WP_REST_Response([
            'logs'  => $logs ?: [],
            'total' => count($logs ?: []),
        ], 200);
    }
}
