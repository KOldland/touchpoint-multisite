<?php

namespace KHM\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class TopicTaxonomyController {

    public function register(): void {
        register_rest_route('khm/v1', '/taxonomy/topics', [
            'methods' => 'POST',
            'callback' => [$this, 'create_topic'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'source' => [
                    'type' => 'string',
                    'required' => false,
                    'default' => 'unknown',
                ],
                'auto_select' => [
                    'type' => 'boolean',
                    'required' => false,
                    'default' => false,
                ],
            ],
        ]);
    }

    public function check_permissions() {
        if (!is_user_logged_in()) {
            return new WP_Error('unauthorized', 'Authentication required.', ['status' => 401]);
        }

        $user = wp_get_current_user();
        $user_id = (int) ($user->ID ?? 0);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Authentication required.', ['status' => 401]);
        }

        if (!$this->can_manage_topics($user_id)) {
            return new WP_Error('forbidden', 'You do not have permission to create topics.', ['status' => 403]);
        }

        return true;
    }

    public function create_topic(WP_REST_Request $request) {
        if (!taxonomy_exists('tp_topic')) {
            return new WP_Error('topic_taxonomy_missing', 'Topic taxonomy is not available.', ['status' => 500]);
        }

        $user_id = get_current_user_id();
        $rate_limited = $this->check_rate_limit($user_id);
        if ($rate_limited instanceof WP_Error) {
            return $rate_limited;
        }

        $source = sanitize_key((string) $request->get_param('source'));
        if (!in_array($source, ['planner', 'post_editor'], true)) {
            $source = 'unknown';
        }

        $raw_name = (string) $request->get_param('name');
        $normalized_name = $this->normalize_topic_name($raw_name);
        $name_length = function_exists('mb_strlen') ? mb_strlen($normalized_name) : strlen($normalized_name);

        if ($normalized_name === '' || $name_length < 2 || $name_length > 80) {
            return new WP_Error('invalid_topic_name', 'Topic name must be between 2 and 80 characters.', ['status' => 400]);
        }

        $slug = sanitize_title($normalized_name);
        if ($slug === '') {
            return new WP_Error('invalid_topic_name', 'Topic name could not be normalized.', ['status' => 400]);
        }

        $existing = $this->find_existing_topic($slug, $normalized_name);
        if ($existing) {
            $this->log_topic_event($user_id, $normalized_name, 'deduped', (int) $existing->term_id);
            return new WP_REST_Response([
                'ok' => true,
                'created' => false,
                'topic' => [
                    'id' => (int) $existing->term_id,
                    'slug' => (string) $existing->slug,
                    'name' => (string) $existing->name,
                    'taxonomy' => 'tp_topic',
                ],
                'meta' => [
                    'source' => $source,
                    'created_by' => (int) get_term_meta((int) $existing->term_id, 'tp_topic_created_by', true),
                    'created_at' => (string) get_term_meta((int) $existing->term_id, 'tp_topic_created_at', true),
                ],
                'message' => 'already_exists',
            ], 200);
        }

        $result = wp_insert_term($normalized_name, 'tp_topic', ['slug' => $slug]);

        if (is_wp_error($result)) {
            if ($result->get_error_code() === 'term_exists') {
                $term_id = (int) $result->get_error_data('term_exists');
                $term = get_term($term_id, 'tp_topic');
                if ($term && !is_wp_error($term)) {
                    $this->log_topic_event($user_id, $normalized_name, 'deduped_race', $term_id);
                    return new WP_REST_Response([
                        'ok' => true,
                        'created' => false,
                        'topic' => [
                            'id' => (int) $term->term_id,
                            'slug' => (string) $term->slug,
                            'name' => (string) $term->name,
                            'taxonomy' => 'tp_topic',
                        ],
                        'meta' => [
                            'source' => $source,
                            'created_by' => (int) get_term_meta((int) $term->term_id, 'tp_topic_created_by', true),
                            'created_at' => (string) get_term_meta((int) $term->term_id, 'tp_topic_created_at', true),
                        ],
                        'message' => 'already_exists',
                    ], 200);
                }
                return new WP_Error('topic_conflict', 'Topic creation conflicted with an existing term.', ['status' => 409]);
            }

            return new WP_Error('topic_create_failed', 'Failed to create topic.', ['status' => 500]);
        }

        $term_id = (int) ($result['term_id'] ?? 0);
        $term = $term_id > 0 ? get_term($term_id, 'tp_topic') : null;
        if (!$term || is_wp_error($term)) {
            return new WP_Error('topic_create_failed', 'Topic was created but could not be loaded.', ['status' => 500]);
        }

        $created_at = gmdate('c');
        update_term_meta($term_id, 'tp_topic_created_by', $user_id);
        update_term_meta($term_id, 'tp_topic_created_at', $created_at);
        update_term_meta($term_id, 'tp_topic_created_source', $source);

        $this->log_topic_event($user_id, $normalized_name, 'created', $term_id);

        return new WP_REST_Response([
            'ok' => true,
            'created' => true,
            'topic' => [
                'id' => (int) $term->term_id,
                'slug' => (string) $term->slug,
                'name' => (string) $term->name,
                'taxonomy' => 'tp_topic',
            ],
            'meta' => [
                'source' => $source,
                'created_by' => $user_id,
                'created_at' => $created_at,
            ],
            'message' => 'created',
        ], 200);
    }

    private function can_manage_topics(int $user_id): bool {
        $required_cap = apply_filters('khm_tp_topic_manage_capability', 'manage_tp_topics', $user_id);
        if (is_string($required_cap) && $required_cap !== '' && current_user_can($required_cap)) {
            return true;
        }

        if (current_user_can('edit_others_posts')) {
            return true;
        }

        return current_user_can('edit_posts');
    }

    private function normalize_topic_name(string $raw): string {
        $clean = wp_strip_all_tags($raw);
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = trim((string) $clean);

        if ($clean === '') {
            return '';
        }

        $tokens = preg_split('/\s+/', $clean) ?: [];
        $normalized_tokens = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            if (preg_match('/^[A-Z0-9&\-]{2,6}$/', $token)) {
                $normalized_tokens[] = $token;
                continue;
            }

            $lower = strtolower($token);
            $normalized_tokens[] = ucfirst($lower);
        }

        return implode(' ', $normalized_tokens);
    }

    private function find_existing_topic(string $slug, string $normalized_name) {
        $by_slug = get_term_by('slug', $slug, 'tp_topic');
        if ($by_slug && !is_wp_error($by_slug)) {
            return $by_slug;
        }

        global $wpdb;
        $term_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT t.term_id
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = %s AND LOWER(t.name) = LOWER(%s)
             LIMIT 1",
            'tp_topic',
            $normalized_name
        ));

        if ($term_id > 0) {
            $term = get_term($term_id, 'tp_topic');
            if ($term && !is_wp_error($term)) {
                return $term;
            }
        }

        return null;
    }

    private function check_rate_limit(int $user_id) {
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Authentication required.', ['status' => 401]);
        }

        $limit = 20;
        $window = 10 * MINUTE_IN_SECONDS;
        $key = 'khm_tp_topic_create_' . $user_id;
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return new WP_Error('rate_limited', 'Topic creation limit reached. Please wait before trying again.', ['status' => 429]);
        }

        set_transient($key, $count + 1, $window);
        return true;
    }

    private function log_topic_event(int $user_id, string $topic_name, string $result, int $term_id): void {
        error_log(sprintf(
            '[KHM Topic] user=%d topic="%s" result=%s term_id=%d',
            $user_id,
            $topic_name,
            $result,
            $term_id
        ));
    }
}
