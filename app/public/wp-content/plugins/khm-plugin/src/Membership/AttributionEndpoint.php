<?php

namespace KHM\Membership;

class AttributionEndpoint {
    public function __construct() {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes() {
        register_rest_route('kh-membership/v1', '/attribution', [
            'methods' => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => '__return_true'
        ]);
    }

    public function handle_request( \WP_REST_Request $req ) {
        $p = $req->get_json_params();
        // basic validation
        $conversion_type = sanitize_text_field($p['conversion_type'] ?? '');
        $allowed = ['signup','trial','paid','demo_request'];
        if (! in_array($conversion_type, $allowed, true)) {
          return new \WP_REST_Response(['error'=>'invalid conversion_type'], 400);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'promotion_attribution';
        $wpdb->insert($table, [
          'schedule_id' => isset($p['schedule_id']) ? intval($p['schedule_id']) : null,
          'sponsor_id' => isset($p['sponsor_id']) ? intval($p['sponsor_id']) : null,
          'user_id' => isset($p['user_id']) ? intval($p['user_id']) : null,
          'user_email' => isset($p['user_email']) ? sanitize_email($p['user_email']) : null,
          'utm_source' => sanitize_text_field($p['utm_source'] ?? ''),
          'utm_medium' => sanitize_text_field($p['utm_medium'] ?? ''),
          'utm_campaign' => sanitize_text_field($p['utm_campaign'] ?? ''),
          'utm_term' => sanitize_text_field($p['utm_term'] ?? ''),
          'utm_content' => sanitize_text_field($p['utm_content'] ?? ''),
          'phase_at_click' => sanitize_text_field($p['phase_at_click'] ?? ''),
          'conversion_type' => $conversion_type,
          'plan_id' => isset($p['plan_id']) ? intval($p['plan_id']) : null,
          'reference_metadata' => wp_json_encode($p),
          'created_at' => current_time('mysql',1)
        ]);
        return rest_ensure_response(['success'=>true, 'id'=>$wpdb->insert_id]);
    }
}
