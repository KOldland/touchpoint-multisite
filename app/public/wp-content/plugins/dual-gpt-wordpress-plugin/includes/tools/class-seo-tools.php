<?php
/**
 * SEO Tools for Dual-GPT Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_SEO_Tools {

    public function get_tool_definitions() {
        return array(
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'tool_get_page_content',
                    'description' => 'Fetch page content and SEO metadata for a post ID',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_id' => array(
                                'type' => 'integer',
                                'description' => 'Post ID',
                            ),
                        ),
                        'required' => array('post_id'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'tool_analyze_content',
                    'description' => 'Analyze content using KHM SEO analysis engine',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'content' => array(
                                'type' => 'string',
                                'description' => 'Content to analyze',
                            ),
                            'keyword' => array(
                                'type' => 'string',
                                'description' => 'Optional focus keyword',
                            ),
                            'post_id' => array(
                                'type' => 'integer',
                                'description' => 'Optional post ID',
                            ),
                        ),
                        'required' => array('content'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'tool_suggest_faqs',
                    'description' => 'Suggest FAQ questions from content headings',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'content' => array(
                                'type' => 'string',
                                'description' => 'Content to analyze',
                            ),
                            'keyword' => array(
                                'type' => 'string',
                                'description' => 'Optional focus keyword',
                            ),
                        ),
                        'required' => array('content'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'tool_suggest_internal_links',
                    'description' => 'Suggest internal links based on shared taxonomy',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_id' => array(
                                'type' => 'integer',
                                'description' => 'Post ID',
                            ),
                            'limit' => array(
                                'type' => 'integer',
                                'description' => 'Max number of suggestions',
                                'default' => 5,
                            ),
                        ),
                        'required' => array('post_id'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'tool_preview_apply',
                    'description' => 'Preview actions without writing to the database',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_id' => array(
                                'type' => 'integer',
                                'description' => 'Post ID',
                            ),
                            'actions' => array(
                                'type' => 'array',
                                'items' => array('type' => 'object'),
                                'description' => 'Proposed actions',
                            ),
                        ),
                        'required' => array('post_id', 'actions'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'tool_apply_actions',
                    'description' => 'Apply approved actions to SEO metadata (requires confirmation)',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_id' => array(
                                'type' => 'integer',
                                'description' => 'Post ID',
                            ),
                            'actions' => array(
                                'type' => 'array',
                                'items' => array('type' => 'object'),
                                'description' => 'Actions to apply',
                            ),
                            'acting_user_id' => array(
                                'type' => 'integer',
                                'description' => 'User ID performing the action',
                            ),
                            'idempotency_key' => array(
                                'type' => 'string',
                                'description' => 'Idempotency key to prevent repeat writes',
                            ),
                            'job_id' => array(
                                'type' => 'string',
                                'description' => 'Dual-GPT job ID for audit logging',
                            ),
                        ),
                        'required' => array('post_id', 'actions', 'acting_user_id', 'idempotency_key', 'job_id'),
                    ),
                ),
            ),
        );
    }

    public function execute_tool($tool_name, $arguments) {
        if (!method_exists($this, $tool_name)) {
            return array('error' => 'Tool not found');
        }

        return call_user_func_array(array($this, $tool_name), $arguments);
    }

    public function tool_get_page_content($args) {
        $post_id = intval($args['post_id'] ?? 0);
        if (!$post_id) {
            return array('error' => 'post_id is required');
        }

        $post = get_post($post_id);
        if (!$post) {
            return array('error' => 'Post not found');
        }

        return array(
            'post_id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'meta' => array(
                'seo_title' => get_post_meta($post_id, '_khm_seo_title', true),
                'meta_description' => get_post_meta($post_id, '_khm_seo_description', true),
                'schema_flags' => get_post_meta($post_id, '_khm_seo_schema_config', true),
            ),
            'images' => $this->extract_images($post->post_content),
            'taxonomies' => $this->get_taxonomies($post_id),
        );
    }

    public function tool_analyze_content($args) {
        $content = $args['content'] ?? '';
        $keyword = $args['keyword'] ?? '';
        $post_id = intval($args['post_id'] ?? 0);

        if (!function_exists('khm_seo') || !khm_seo()) {
            return array('error' => 'KHM SEO not available');
        }

        $analysis_engine = khm_seo()->get_analysis_engine();
        if (!$analysis_engine) {
            return array('error' => 'KHM SEO analysis engine not available');
        }

        $data = array(
            'post_id' => $post_id,
            'title' => $post_id ? get_the_title($post_id) : '',
            'content' => $content,
            'meta_description' => $post_id ? get_post_meta($post_id, '_khm_seo_description', true) : '',
            'focus_keyword' => $keyword,
        );

        return $analysis_engine->analyze($data);
    }

    public function tool_suggest_faqs($args) {
        $content = $args['content'] ?? '';
        $headings = array();

        if (preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/', $content, $matches)) {
            $headings = array_map('wp_strip_all_tags', $matches[1]);
        }

        $faqs = array();
        foreach (array_slice($headings, 0, 3) as $heading) {
            $faqs[] = array(
                'question' => 'What is ' . trim($heading) . '?',
                'answer' => 'Answer should be confirmed by a human editor before publishing.',
            );
        }

        return $faqs;
    }

    public function tool_suggest_internal_links($args) {
        $post_id = intval($args['post_id'] ?? 0);
        $limit = intval($args['limit'] ?? 5);

        if (!$post_id) {
            return array('error' => 'post_id is required');
        }

        $terms = wp_get_post_terms($post_id, array('category', 'post_tag'));
        $term_ids = wp_list_pluck($terms, 'term_id');

        $query_args = array(
            'post_type' => get_post_type($post_id),
            'post_status' => 'publish',
            'posts_per_page' => max(1, $limit),
            'post__not_in' => array($post_id),
        );

        if (!empty($term_ids)) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $term_ids,
                ),
            );
        }

        $posts = get_posts($query_args);
        $suggestions = array();

        foreach ($posts as $post) {
            $suggestions[] = array(
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'suggested_anchor' => $post->post_title,
            );
        }

        return $suggestions;
    }

    public function tool_preview_apply($args) {
        $post_id = intval($args['post_id'] ?? 0);
        $actions = $args['actions'] ?? array();

        if (!$post_id || empty($actions)) {
            return array('error' => 'post_id and actions are required');
        }

        $preview_lines = array();
        foreach ($actions as $action) {
            $action_type = $action['action_type'] ?? '';
            $payload = $action['payload'] ?? array();
            $preview_lines[] = sprintf('%s: %s', $action_type, wp_json_encode($payload));
        }

        return array(
            'preview_html' => '<pre>' . esc_html(implode("\n", $preview_lines)) . '</pre>',
        );
    }

    public function tool_apply_actions($args) {
        $post_id = intval($args['post_id'] ?? 0);
        $actions = $args['actions'] ?? array();
        $acting_user_id = intval($args['acting_user_id'] ?? 0);
        $idempotency_key = sanitize_text_field($args['idempotency_key'] ?? '');
        $job_id = sanitize_text_field($args['job_id'] ?? '');

        if (!$post_id || empty($actions) || !$acting_user_id || !$idempotency_key || !$job_id) {
            return array('error' => 'post_id, actions, acting_user_id, idempotency_key, and job_id are required');
        }

        if (!current_user_can('edit_post', $post_id)) {
            return array('error' => 'Permission denied');
        }

        $last_idempotency = get_post_meta($post_id, '_khm_seo_agent_last_idempotency', true);
        if (!empty($last_idempotency) && $last_idempotency === $idempotency_key) {
            return array('error' => 'Duplicate idempotency key');
        }

        $db = new Dual_GPT_DB_Handler();
        $budget = $db->check_user_budget($acting_user_id);
        if ($budget['token_used'] >= $budget['token_limit']) {
            return array('error' => 'Budget exceeded');
        }

        $rollback = array();
        $changes = array();

        foreach ($actions as $action) {
            $action_type = $action['action_type'] ?? '';
            $payload = $action['payload'] ?? array();

            switch ($action_type) {
                case 'set_meta_title':
                    $meta_key = '_khm_seo_title';
                    $old_value = get_post_meta($post_id, $meta_key, true);
                    update_post_meta($post_id, $meta_key, sanitize_text_field($payload['value'] ?? ''));
                    $changes[] = array('meta_key' => $meta_key, 'old' => $old_value, 'new' => get_post_meta($post_id, $meta_key, true));
                    break;
                case 'set_meta_description':
                    $meta_key = '_khm_seo_description';
                    $old_value = get_post_meta($post_id, $meta_key, true);
                    update_post_meta($post_id, $meta_key, sanitize_textarea_field($payload['value'] ?? ''));
                    $changes[] = array('meta_key' => $meta_key, 'old' => $old_value, 'new' => get_post_meta($post_id, $meta_key, true));
                    break;
                case 'set_focus_keyword':
                    $meta_key = '_khm_seo_focus_keyword';
                    $old_value = get_post_meta($post_id, $meta_key, true);
                    update_post_meta($post_id, $meta_key, sanitize_text_field($payload['value'] ?? ''));
                    $changes[] = array('meta_key' => $meta_key, 'old' => $old_value, 'new' => get_post_meta($post_id, $meta_key, true));
                    break;
                case 'set_keywords':
                    $meta_key = '_khm_seo_keywords';
                    $old_value = get_post_meta($post_id, $meta_key, true);
                    update_post_meta($post_id, $meta_key, sanitize_text_field($payload['value'] ?? ''));
                    $changes[] = array('meta_key' => $meta_key, 'old' => $old_value, 'new' => get_post_meta($post_id, $meta_key, true));
                    break;
                case 'set_robots':
                    $meta_key = '_khm_seo_robots';
                    $old_value = get_post_meta($post_id, $meta_key, true);
                    update_post_meta($post_id, $meta_key, sanitize_text_field($payload['value'] ?? ''));
                    $changes[] = array('meta_key' => $meta_key, 'old' => $old_value, 'new' => get_post_meta($post_id, $meta_key, true));
                    break;
                case 'set_canonical':
                    $meta_key = '_khm_seo_canonical';
                    $old_value = get_post_meta($post_id, $meta_key, true);
                    update_post_meta($post_id, $meta_key, sanitize_text_field($payload['value'] ?? ''));
                    $changes[] = array('meta_key' => $meta_key, 'old' => $old_value, 'new' => get_post_meta($post_id, $meta_key, true));
                    break;
                default:
                    return array('error' => 'Unsupported action: ' . $action_type);
            }

            $rollback[] = array(
                'action_type' => $action_type,
                'payload' => array(
                    'post_id' => $post_id,
                    'value' => $old_value,
                ),
            );
        }

        $db->insert_audit_log($job_id, 'tool_call', array(
            'tool' => 'tool_apply_actions',
            'post_id' => $post_id,
            'acting_user_id' => $acting_user_id,
            'idempotency_key' => $idempotency_key,
            'changes' => $changes,
            'rollback' => $rollback,
        ));

        update_post_meta($post_id, '_khm_seo_agent_last_idempotency', $idempotency_key);

        return array(
            'applied' => true,
            'changes' => $changes,
            'rollback_data' => $rollback,
        );
    }

    private function extract_images($content) {
        $images = array();
        if (preg_match_all('/wp-image-(\d+)/', $content, $matches)) {
            $ids = array_unique(array_map('intval', $matches[1]));
            foreach ($ids as $id) {
                $images[] = array(
                    'id' => $id,
                    'alt' => get_post_meta($id, '_wp_attachment_image_alt', true),
                );
            }
        }

        return $images;
    }

    private function get_taxonomies($post_id) {
        $taxonomies = array();
        $post_type = get_post_type($post_id);
        $taxes = get_object_taxonomies($post_type);
        foreach ($taxes as $tax) {
            $terms = get_the_terms($post_id, $tax);
            $taxonomies[$tax] = is_array($terms) ? wp_list_pluck($terms, 'name') : array();
        }

        return $taxonomies;
    }
}
