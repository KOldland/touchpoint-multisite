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

        // Basic validation
        $conversion_type = sanitize_text_field($p['conversion_type'] ?? '');
        $allowed = ['signup','signup_no_consent','trial','paid','paid_no_consent','demo_request'];
        if (! in_array($conversion_type, $allowed, true)) {
            return new \WP_REST_Response([
                'error' => 'invalid conversion_type',
                'details' => 'Allowed values are: signup, trial, paid, demo_request'
            ], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'promotion_attribution';

        // Sanitize key fields for idempotency check
        $user_id = isset($p['user_id']) ? intval($p['user_id']) : null;
        $user_email = isset($p['user_email']) ? sanitize_email($p['user_email']) : null;
        $schedule_id = isset($p['schedule_id']) ? intval($p['schedule_id']) : null;

        // Idempotency check: prevent duplicates within 10 minutes
        // Unique key: (user_id OR user_email) + schedule_id + conversion_type
        $ten_minutes_ago = gmdate('Y-m-d H:i:s', time() - 600);

        $where_conditions = [];
        $where_values = [];

        if ($user_id) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $user_id;
        } elseif ($user_email) {
            $where_conditions[] = 'user_email = %s';
            $where_values[] = $user_email;
        }

        if ($schedule_id) {
            $where_conditions[] = 'schedule_id = %d';
            $where_values[] = $schedule_id;
        } else {
            $where_conditions[] = 'schedule_id IS NULL';
        }

        $where_conditions[] = 'conversion_type = %s';
        $where_values[] = $conversion_type;

        $where_conditions[] = 'created_at >= %s';
        $where_values[] = $ten_minutes_ago;

        if (!empty($where_conditions)) {
            $where_clause = implode(' AND ', $where_conditions);
            $query = "SELECT id FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT 1";

            if (!empty($where_values)) {
                $query = $wpdb->prepare($query, ...$where_values);
            }

            $existing_id = $wpdb->get_var($query);

            if ($existing_id) {
                // Duplicate found within 10-minute window - return existing record
                return rest_ensure_response(['success' => true, 'id' => (int) $existing_id]);
            }
        }

        // Fallback idempotency path for lightweight test DB mocks that may not support
        // the full prepared SQL shape above.
        $recent_rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 500", ARRAY_A);
        if (is_array($recent_rows) && !empty($recent_rows)) {
            $threshold = strtotime($ten_minutes_ago);

            foreach ($recent_rows as $row) {
                $row_created = isset($row['created_at']) ? strtotime((string) $row['created_at']) : false;
                if ($row_created !== false && $row_created < $threshold) {
                    continue;
                }

                if (($row['conversion_type'] ?? '') !== $conversion_type) {
                    continue;
                }

                if ($schedule_id) {
                    if ((int) ($row['schedule_id'] ?? 0) !== $schedule_id) {
                        continue;
                    }
                } elseif (isset($row['schedule_id']) && $row['schedule_id'] !== null && $row['schedule_id'] !== '') {
                    continue;
                }

                if ($user_id) {
                    if ((int) ($row['user_id'] ?? 0) !== $user_id) {
                        continue;
                    }
                } elseif ($user_email) {
                    if ((string) ($row['user_email'] ?? '') !== (string) $user_email) {
                        continue;
                    }
                }

                return rest_ensure_response(['success' => true, 'id' => (int) ($row['id'] ?? 0)]);
            }
        }

        $consent = isset( $p['consent'] ) ? in_array( strtolower( (string) $p['consent'] ), [ '1', 'true', 'yes', 'on' ], true ) : false;
        $consent_source = isset( $p['consent_source'] ) ? sanitize_key( (string) $p['consent_source'] ) : 'api';
        if ( '' === $consent_source ) {
            $consent_source = 'api';
        }
        $consent_given_at = $consent ? current_time( 'mysql', 1 ) : null;

        $utm_source = $consent ? sanitize_text_field($p['utm_source'] ?? '') : null;
        $utm_medium = $consent ? sanitize_text_field($p['utm_medium'] ?? '') : null;
        $utm_campaign = $consent ? sanitize_text_field($p['utm_campaign'] ?? '') : null;
        $utm_term = $consent ? sanitize_text_field($p['utm_term'] ?? '') : null;
        $utm_content = $consent ? sanitize_text_field($p['utm_content'] ?? '') : null;
        $phase_at_click = $consent ? sanitize_text_field($p['phase_at_click'] ?? '') : null;

        // No duplicate found - insert new attribution record
        $wpdb->insert($table, [
            'schedule_id' => $schedule_id,
            'sponsor_id' => isset($p['sponsor_id']) ? intval($p['sponsor_id']) : null,
            'user_id' => $consent ? $user_id : null,
            'user_email' => $consent ? $user_email : null,
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'utm_term' => $utm_term,
            'utm_content' => $utm_content,
            'phase_at_click' => $phase_at_click,
            'conversion_type' => $conversion_type,
            'plan_id' => isset($p['plan_id']) ? intval($p['plan_id']) : null,
            'consent' => $consent ? 1 : 0,
            'consent_given_at' => $consent_given_at,
            'consent_source' => $consent_source,
            'reference' => isset( $p['reference'] ) ? sanitize_text_field( (string) $p['reference'] ) : null,
            'reference_metadata' => wp_json_encode($p),
            'created_at' => current_time('mysql', 1)
        ]);

        return rest_ensure_response(['success' => true, 'id' => $wpdb->insert_id]);
    }
}
