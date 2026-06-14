<?php

namespace KH\EditorialAuthor\API;

use KH\EditorialAuthor\Agents\AuthorOrchestrator;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class AuthorEndpoints {

    public function register_routes() {
        register_rest_route('editorial/v1', '/author/run', [
            'methods' => 'POST',
            'callback' => [$this, 'run_author_agent'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'mode' => [
                    'type' => 'string',
                    'required' => true,
                    'enum' => ['draft', 'abstract', 'enrichment'],
                ],
                'persona' => [
                    'type' => 'string',
                    'required' => false,
                    'enum' => ['journalist', 'analyst', 'veteran', 'editor', ''],
                ],
                'planner_session_id' => [
                    'type' => 'integer',
                    'required' => false,
                ],
                'article_id' => [
                    'type' => ['string', 'integer'],
                    'required' => false,
                ],
                'draft_content' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'instructions' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'core_settings' => [
                    'type' => 'object',
                    'required' => false,
                ],
                'author_policy' => [
                    'type' => 'object',
                    'required' => false,
                ],
            ],
        ]);

        register_rest_route('editorial/v1', '/author/job/(?P<id>[a-f0-9\-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_job_status'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        
        // Step 7: Image generate endpoint (replaces dual-gpt/v1/images/generate)
        register_rest_route("editorial/v1", "/images/generate", [
            "methods" => "POST",
            "callback" => [$this, "generate_image"],
            "permission_callback" => [$this, "check_permissions"],
            "args" => [
                "prompt"               => ["type" => "string"],
                "provider"             => ["type" => "string", "default" => "openai"],
                "model"                => ["type" => "string"],
                "title"                => ["type" => "string"],
                "summary"              => ["type" => "string"],
                "preset_key"           => ["type" => "string"],
                "store_in_media_library" => ["type" => "boolean", "default" => true],
                "post_id"              => ["type" => "integer"],
            ],
        ]);
        // Step 7: Image recommend endpoint (replaces dual-gpt/v1/images/recommend)
        register_rest_route("editorial/v1", "/images/recommend", [
            "methods" => "POST",
            "callback" => [$this, "recommend_image"],
            "permission_callback" => [$this, "check_permissions"],
            "args" => [
                "title"          => ["type" => "string"],
                "summary"        => ["type" => "string"],
                "preset_key"     => ["type" => "string"],
                "colour_palette" => ["type" => "string"],
            ],
        ]);
        register_rest_route('editorial/v1', '/excerpt/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_excerpt'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);
        register_rest_route('editorial/v1', '/author/persist', [
            'methods' => 'POST',
            'callback' => [$this, 'persist_draft'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'title'              => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'content'            => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'wp_kses_post'],
                'planner_session_id' => ['type' => 'integer', 'required' => true],
                'article_id'         => ['type' => ['string', 'integer'], 'required' => true],
                'author_policy'      => ['type' => 'object', 'required' => false],
                'id'                 => ['type' => 'integer', 'required' => false],
                'blocks'             => ['type' => 'array', 'required' => false],
            ],
        ]);

        register_rest_route('editorial/v1', '/abstract/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_abstract'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        register_rest_route('editorial/v1', '/abstract/save-to-post', [
            'methods' => 'POST',
            'callback' => [$this, 'save_abstract_to_post'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'post_id'      => ['type' => 'integer', 'required' => true],
                'abstract_data' => ['type' => 'object', 'required' => true],
            ],
        ]);
    }

    public function check_permissions() {
        return current_user_can('edit_posts');
    }

    public function run_author_agent(WP_REST_Request $request) {
        $params = $request->get_params();
        $orchestrator = \KH\EditorialAuthor\Core\AuthorPlugin::get_instance()->get_orchestrator();

        // Log persona for traceability
        if (!empty($params['persona'])) {
            error_log('[KH Author] Running with persona: ' . $params['persona']);
        }
        
        $result = $orchestrator->run($params, get_current_user_id());

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }

    public function get_job_status(WP_REST_Request $request) {
        $job_id = $request->get_param('id');
        $orchestrator = \KH\EditorialAuthor\Core\AuthorPlugin::get_instance()->get_orchestrator();
        
        // We'll need a way to get job status through orchestrator or bridge
        $bridge = new \KH\EditorialAuthor\Integration\IntelligenceBridge();
        $job = $bridge->get_job($job_id);

        if (is_wp_error($job)) {
            return $job;
        }

        if (!$job) {
            return new WP_Error('job_not_found', 'Job not found.', ['status' => 404]);
        }

        // Parse response if it exists
        if (!empty($job['response'])) {
            $job['response'] = json_decode($job['response'], true);
        }

        return new WP_REST_Response($job, 200);
    }

    /**
     * Persist an AI-generated draft as a formal WordPress post.
     */
    public function persist_draft(WP_REST_Request $request) {
        $id            = $request['id'] ? (int) $request['id'] : null;
        $title         = $request['title'];
        $content       = $request['content'];
        $blocks        = $request['blocks'] ?? [];
        $session_id    = (int) $request['planner_session_id'];
        $article_id    = $request['article_id'];
        $author_policy = $request['author_policy'] ?? [];
        $user_id       = get_current_user_id();
        $warnings      = [];

        // 1. Guard: If ID is provided, verify user can edit it
        if ($id) {
            if (!current_user_can('edit_post', $id)) {
                return new WP_Error('forbidden', 'You do not have permission to update this post.', ['status' => 403]);
            }
        }

        // 1b. Gutenberg block compilation (if raw blocks provided)
        if (!empty($blocks) && is_array($blocks)) {
            try {
                $compiler  = new \KH\EditorialAuthor\Services\GutenbergCompiler();
                $compiled  = $compiler->compile($blocks);
                if (!empty($compiled)) {
                    $content = $compiled;
                }
            } catch (\Throwable $e) {
                $warnings[] = __('Block compilation failed — falling back to raw HTML.', 'kh-editorial-author');
                error_log('[GutenbergCompiler] Compilation error: ' . $e->getMessage());
            }
        }

        // 2. Atomic Persist (Insert or Update)
        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'draft',
            'post_type'    => 'post',
            'post_author'  => $user_id,
        ];

        if ($id) {
            $post_data['ID'] = $id;
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return new WP_Error('insert_failed', 'Failed to create post: ' . $post_id->get_error_message(), ['status' => 500]);
        }

        // 2. AI Intent Persistence (Policy Snapshot)
        if (!empty($author_policy)) {
            update_post_meta($post_id, '_kh_ai_policy_snapshot', $author_policy);
        }

        // 3. Attribution Sync (Human + AI)
        try {
            $sync_provider = \KH\EditorialAuthor\Core\AuthorPlugin::get_instance()->get_author_sync();
            $synced = $sync_provider->sync_authors($post_id, $user_id);
            if (!$synced) {
                $warnings[] = __('Post created, but attribution sync failed. Ensure co-author profiles exist.', 'kh-editorial-author');
            }
        } catch (\Throwable $e) {
            $warnings[] = __('Attribution system error.', 'kh-editorial-author');
            error_log("[AuthorEndpoints] Attribution sync failed for post {$post_id}: " . $e->getMessage());
        }

        // 4. Planner Session Linkage
        try {
            $planner_bridge = new \KH\EditorialAuthor\Integration\PlannerBridge();
            $linked = $planner_bridge->link_article_to_post($session_id, $article_id, $post_id);
            if (!$linked) {
                $warnings[] = __('Post created, but failed to mark article idea as drafted in the planner.', 'kh-editorial-author');
            }
        } catch (\Throwable $e) {
            $warnings[] = __('Planner linkage system error.', 'kh-editorial-author');
            error_log("[AuthorEndpoints] Planner linkage failed for post {$post_id}: " . $e->getMessage());
        }

        return new WP_REST_Response([
            'success'  => true,
            'post_id'  => $post_id,
            'edit_url' => admin_url("post.php?post={$post_id}&action=edit"),
            'warnings' => $warnings
        ], 200);
    }

    // Step 7: Image generation callback (matches legacy dual-gpt/v1/images/generate response)
    public function generate_image(WP_REST_Request $request) {
        $params = $request->get_params();
        error_log( '[KH Image] generate_image endpoint called — provider=' . ($params['provider'] ?? 'none') . ', model=' . ($params['model'] ?? 'none') . ', prompt_len=' . strlen($params['prompt'] ?? '') );

        // Try the real ImageService if available
        if (class_exists('\\KH\\Editorial\\Services\\ImageService')) {
            try {
                $service = new \KH\Editorial\Services\ImageService();
                $result = $service->generate($params);

                if (!is_wp_error($result)) {
                    error_log( '[KH Image] generate_image SUCCESS — provider=' . ($result['provider'] ?? '?') . ', attachments=' . count($result['attachments'] ?? []) );
                    return new WP_REST_Response([
                        'success'     => true,
                        'image_url'   => $result['url'] ?? '',
                        'alt_text'    => $result['alt_text'] ?? '',
                        'caption'     => $result['caption'] ?? '',
                        'attachments' => $result['attachments'] ?? [],
                        'provider'    => $result['provider'] ?? $params['provider'] ?? 'openai',
                    ], 200);
                }

                // Return the error message for the UI
                $error_msg = $result->get_error_message();
                error_log( '[KH Image] generate_image FAILED — ' . $error_msg );
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $error_msg,
                ], 200);
            } catch (\Throwable $e) {
                error_log( '[KH Image] generate_image EXCEPTION — ' . $e->getMessage() );
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        }

        // Fallback: placeholder when ImageService not available
        $prompt = sanitize_text_field($params["prompt"] ?? "");
        return new WP_REST_Response([
            "success" => true,
            "image_url" => "https://via.placeholder.com/1024x1024?text=" . urlencode($prompt),
            "prompt" => $prompt,
            "attachments" => [],
        ], 200);
    }

    // Step 7: Image recommendation callback (matches legacy dual-gpt/v1/images/recommend response)
    public function recommend_image(WP_REST_Request $request) {
        $params = $request->get_params();

        // Try ImageService::recommend() if available
        if (class_exists('\\KH\\Editorial\\Services\\ImageService')) {
            try {
                $service = new \KH\Editorial\Services\ImageService();
                $recommendation = $service->recommend($params);

                return new WP_REST_Response([
                    "success" => true,
                    "prompt" => $recommendation['prompt'] ?? '',
                    "recommended_prompt" => $recommendation['prompt'] ?? '',
                    "alt_text" => $recommendation['alt_text'] ?? '',
                    "caption" => $recommendation['caption'] ?? '',
                    "negative_prompt" => $recommendation['negative_prompt'] ?? '',
                    "aspect_ratio" => $recommendation['aspect_ratio'] ?? '16:9',
                    "context" => $params['title'] ?? '',
                ], 200);
            } catch (\Throwable $e) {
                // Fall through to simple fallback
            }
        }

        // Fallback: build a simple prompt from title/context
        $title = sanitize_text_field($params["title"] ?? "");
        $summary = sanitize_text_field($params["summary"] ?? "");
        $prompt = "Create a publication-quality editorial image";
        if ($title) {
            $prompt .= " for the article '" . $title . "'";
        }
        $prompt .= ". Art direction: Paper-cut editorial illustration with geometric forms and tactile texture. Brand palette: Warm kraft beige, muted teal, deep blue, orange, yellow.";
        if ($summary) {
            $prompt .= " Article summary: " . $summary;
        }
        $prompt .= " Do NOT use photorealism, glossy 3D render, or cluttered backgrounds.";

        return new WP_REST_Response([
            "success" => true,
            "prompt" => $prompt,
            "recommended_prompt" => $prompt,
            "context" => $title,
        ], 200);
    }

    /**
     * Generate an excerpt from the full post content using the LLM.
     */
    public function generate_excerpt(WP_REST_Request $request) {
        $post_id = $request->get_param('post_id');
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found.', ['status' => 404]);
        }

        // Strip blocks/shortcodes to get clean plain text
        $content = wp_strip_all_tags($post->post_content, true);
        // Limit to first 3000 characters for LLM context
        $content = mb_substr($content, 0, 3000);

        if (empty(trim($content))) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Post content is empty.',
            ], 200);
        }

        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You generate concise, compelling article excerpts for publishing. '
                        . 'Rules: Minimum 100 words, maximum 200 words. Capture the essence. No clickbait. '
                        . 'No quotation marks around the excerpt. '
                        . 'Output valid JSON with one key "excerpt".',
                ],
                [
                    'role' => 'user',
                    'content' => "Generate an excerpt for this article:\n\nTitle: {$post->post_title}\n\nContent:\n{$content}",
                ],
            ];

            $route = \KH\Editorial\Core\LLMService::resolve_agent_model('excerpt');
            $result = \KH\Editorial\Core\LLMService::post_completion_with_retry($messages, [
                'provider'        => $route['provider'],
                'model'           => $route['model'],
                'temperature'     => 0.2,
                'max_tokens'      => 300,
                'response_format' => ['type' => 'json_object'],
                'fallback_chain'  => $route['fallback_chain'] ?? [],
            ]);

            if (is_wp_error($result)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message(),
                ], 200);
            }

            $data = json_decode($result['content'], true);
            $excerpt = $data['excerpt'] ?? '';

            // Fallback: try to extract just the text if JSON parsing failed
            if (empty($excerpt) && !empty($result['content'])) {
                $excerpt = trim($result['content']);
                // Remove JSON wrapper if present
                $excerpt = trim(preg_replace('/^.*?"excerpt"\s*:\s*"/', '', $excerpt), '"');
                $excerpt = mb_substr($excerpt, 0, 160);
            }

            if (empty($excerpt)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Failed to generate excerpt.',
                ], 200);
            }

            // Ensure the excerpt is at least 100 words
            $word_count = str_word_count(trim($excerpt));
            if ($word_count < 100) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Generated excerpt too short (' . $word_count . ' words). Minimum 100 words required.',
                ], 200);
            }

            // Truncate to 200 words for safety
            $words = explode(' ', trim($excerpt));
            if (count($words) > 200) {
                $excerpt = implode(' ', array_slice($words, 0, 200));
            }

            return new WP_REST_Response([
                'success' => true,
                'excerpt' => $excerpt,
            ], 200);

        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate an abstract from the full post content using the AbstractAgent.
     */
    public function generate_abstract(WP_REST_Request $request) {
        $post_id = $request->get_param('post_id');
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found.', ['status' => 404]);
        }

        // Strip blocks/shortcodes to get clean plain text
        $content = wp_strip_all_tags($post->post_content, true);
        // Use full content for abstract generation (unlike excerpt which limits to 3000 chars)
        // Limit to 10000 characters to keep LLM context manageable
        $content = mb_substr($content, 0, 10000);

        if (empty(trim($content))) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Post content is empty.',
            ], 200);
        }

        try {
            $orchestrator = \KH\EditorialAuthor\Core\AuthorPlugin::get_instance()->get_orchestrator();
            $result = $orchestrator->run([
                'mode'          => 'abstract',
                'draft_content' => $content,
            ], get_current_user_id());

            if (is_wp_error($result)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message(),
                ], 200);
            }

            $abstract_output = $result['output'] ?? [];

            return new WP_REST_Response([
                'success'   => true,
                'abstract'  => $abstract_output,
                'warnings'  => $result['warnings'] ?? [],
                'errors'    => $result['validation_errors'] ?? [],
            ], 200);

        } catch (\Throwable $e) {
            error_log('[Abstract] generate_abstract failed: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save abstract data to the ACF abstract Gutenberg block in the post.
     *
     * The acf/abstract block stores data as post meta using ACF conventions:
     *   - Simple fields: overview, context, application (post meta keys)
     *   - Repeater: key_points_N_bullet (N = 0-based index)
     * The block comment in post_content is a marker; ACF reads from get_field() / post meta.
     */
    public function save_abstract_to_post(WP_REST_Request $request) {
        $post_id       = $request->get_param('post_id');
        $abstract_data = $request->get_param('abstract_data');

        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', 'You do not have permission to edit this post.', ['status' => 403]);
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found.', ['status' => 404]);
        }

        // ---- Write simple fields as post meta (ACF reads these) ----
        update_post_meta($post_id, 'overview', sanitize_textarea_field($abstract_data['overview'] ?? ''));
        update_post_meta($post_id, 'context', sanitize_textarea_field($abstract_data['context'] ?? ''));
        update_post_meta($post_id, 'application', sanitize_textarea_field($abstract_data['application'] ?? ''));

        // ---- Write key_points as ACF repeater meta ----
        // ACF stores repeaters as: base_N_subfield (e.g., key_points_0_bullet, key_points_1_bullet)
        // + a count row: key_points => N

        // Delete any existing repeater rows
        delete_post_meta($post_id, 'key_points');
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
            $post_id,
            'key_points_%'
        ));

        $raw_key_points = $abstract_data['key_points'] ?? [];
        if (!is_array($raw_key_points)) {
            $raw_key_points = [];
        }

        // Write each bullet as a repeater sub-field
        $valid_count = 0;
        foreach ($raw_key_points as $i => $point) {
            $bullet_text = is_string($point) ? $point : ($point['bullet'] ?? '');
            $bullet_text = sanitize_text_field(trim($bullet_text));
            if ($bullet_text === '') {
                continue;
            }
            update_post_meta($post_id, "key_points_{$valid_count}_bullet", $bullet_text);
            $valid_count++;
        }

        // Write the count so ACF knows how many rows exist
        if ($valid_count > 0) {
            update_post_meta($post_id, 'key_points', $valid_count);
        }

        // Try ACF update_field as well if available (for ACF Pro in-block editing)
        if (function_exists('update_field')) {
            $acf_key_points = array_map(function($point) {
                $bullet_text = is_string($point) ? $point : ($point['bullet'] ?? '');
                return ['bullet' => sanitize_text_field(trim($bullet_text))];
            }, $raw_key_points);
            $acf_key_points = array_values(array_filter($acf_key_points, fn($p) => $p['bullet'] !== ''));

            update_field('field_abstract_overview', sanitize_textarea_field($abstract_data['overview'] ?? ''), $post_id);
            update_field('field_abstract_context', sanitize_textarea_field($abstract_data['context'] ?? ''), $post_id);
            update_field('field_abstract_application', sanitize_textarea_field($abstract_data['application'] ?? ''), $post_id);
            update_field('field_abstract_key_points', $acf_key_points, $post_id);
        }

        // ---- Insert/update the block marker in post_content ----
        // The block comment serves two consumers:
        // 1. ACF Gutenberg editor — reads _field_abstract_* keys with underscore prefix
        // 2. Frontend template / PDFService — reads simple keys (overview, context, key_points_0_bullet, etc.)
        // Include both formats so all consumers work.

        // Build simple flat keys for frontend/PDF backward compatibility
        $flat_block_data = [
            'overview'    => sanitize_textarea_field($abstract_data['overview'] ?? ''),
            'context'     => sanitize_textarea_field($abstract_data['context'] ?? ''),
            'application' => sanitize_textarea_field($abstract_data['application'] ?? ''),
            'key_points'  => $valid_count,
        ];
        // Add each bullet as flat key_points_N_bullet
        for ($i = 0; $i < $valid_count; $i++) {
            $flat_block_data["key_points_{$i}_bullet"] = get_post_meta($post_id, "key_points_{$i}_bullet", true);
        }

        // Build ACF-prefixed keys for the Gutenberg editor
        $acf_key_points_for_block = array_values(array_map(fn($p) => [
            'field_abstract_key_points_bullet' => sanitize_text_field(is_string($p) ? $p : ($p['bullet'] ?? ''))
        ], $raw_key_points));

        $acf_block_data = [
            '_field_abstract_overview'    => sanitize_textarea_field($abstract_data['overview'] ?? ''),
            '_field_abstract_context'     => sanitize_textarea_field($abstract_data['context'] ?? ''),
            '_field_abstract_application' => sanitize_textarea_field($abstract_data['application'] ?? ''),
            '_field_abstract_key_points'  => $acf_key_points_for_block,
        ];

        // Merge both — the _ prefixed keys take priority for ACF editor
        $block_data_for_marker = array_merge($flat_block_data, $acf_block_data);
        $block_json = wp_json_encode($block_data_for_marker, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $abstract_block = '<!-- wp:acf/abstract {"name":"acf/abstract","data":' . $block_json . ',"mode":"preview"} /-->';

        $current_content = $post->post_content;

        // Check if an abstract block marker already exists — replace it
        $pattern = '/<!-- wp:acf\/abstract \{"name":"acf\/abstract","data":\{.+?\},"mode":"[^"]*"\} \/-->/s';
        if (preg_match($pattern, $current_content)) {
            $new_content = preg_replace($pattern, $abstract_block, $current_content, 1);
        } else {
            // Prepend the abstract block to the content
            $new_content = $abstract_block . "\n\n" . $current_content;
        }

        // Store all abstract fields as post meta for PDF/reference
        $full_abstract_meta = [
            'overview'    => sanitize_textarea_field($abstract_data['overview'] ?? ''),
            'context'     => sanitize_textarea_field($abstract_data['context'] ?? ''),
            'application' => sanitize_textarea_field($abstract_data['application'] ?? ''),
            'key_points'  => $raw_key_points,
            'keywords'    => array_map('sanitize_text_field', (array) ($abstract_data['keywords'] ?? [])),
        ];
        update_post_meta($post_id, '_kh_abstract_data', $full_abstract_meta);

        // Update post content with the new block
        $updated = wp_update_post([
            'ID'           => $post_id,
            'post_content' => $new_content,
        ], true);

        if (is_wp_error($updated)) {
            error_log('[Abstract] save_abstract_to_post failed: ' . $updated->get_error_message());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update post: ' . $updated->get_error_message(),
            ], 200);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Abstract saved to post.',
        ], 200);
    }
}
