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
                        . 'Rules: Max 160 characters. Capture the essence. No clickbait. '
                        . 'No quotation marks around the excerpt. Single sentence preferred. '
                        . 'Output valid JSON with one key "excerpt".',
                ],
                [
                    'role' => 'user',
                    'content' => "Generate an excerpt for this article:\n\nTitle: {$post->post_title}\n\nContent:\n{$content}",
                ],
            ];

            $route = \KH\Editorial\Core\LLMService::resolve_agent_model('excerpt');
            $result = \KH\Editorial\Core\LLMService::post_completion($messages, [
                'provider'    => $route['provider'],
                'model'       => $route['model'],
                'temperature' => 0.2,
                'max_tokens'  => 300,
                'response_format' => ['type' => 'json_object'],
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

            // Truncate to 160 chars for safety
            $excerpt = mb_substr(trim($excerpt), 0, 160);

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
}
