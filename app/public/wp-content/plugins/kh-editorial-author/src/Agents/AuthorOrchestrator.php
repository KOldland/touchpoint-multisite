<?php

namespace KH\EditorialAuthor\Agents;

use KH\EditorialAuthor\Integration\IntelligenceBridge;
use KH\EditorialAuthor\Integration\PlannerBridge;
use WP_Error;

class AuthorOrchestrator {
    
    private $intelligence;
    private $planner;
    private $draft_agent;
    private $abstract_agent;
    private $enrichment_agent;

    public function __construct() {
        $this->intelligence = new IntelligenceBridge();
        $this->planner = new PlannerBridge();
        $this->draft_agent = new DraftAgent($this->intelligence);
        $this->abstract_agent = new AbstractAgent($this->intelligence);
        $this->enrichment_agent = new EnrichmentAgent($this->intelligence);

        $this->init_hooks();
    }

    private function init_hooks() {
        add_filter('kh_editorial_execute_job_draft', [$this, 'execute_draft_job'], 10, 2);
    }

    /**
     * Internal handler for the AI Worker to execute a draft job.
     */
    public function execute_draft_job($null, $job) {
        $prompt_data = json_decode($job['prompt'], true);
        if (empty($prompt_data) || empty($prompt_data['context'])) {
            return new WP_Error('invalid_job_prompt', 'Job prompt is missing context.');
        }

        // Unpack the policy and context
        $context = $prompt_data['context'];
        $policy = $prompt_data['author_policy'] ?? [];
        
        // Ensure context has the specific policy for prompt building
        $context['author_policy'] = $policy;

        // Extract session and article identifiers from the job payload
        $session_id = $job['session_id'] ?? null;
        $article_id = $job['article_id'] ?? null;

        $result = $this->draft_agent->execute(
            $context,
            $prompt_data['instructions'] ?? '',
            $job['created_by']
        );

        // If draft succeeded and we have both session and article IDs, link the post and set edit_url in session metadata
        if (!is_wp_error($result) && $session_id && $article_id) {
            // The DraftAgent may return a 'post_id' when it creates a WP post; use it if present
            $post_id = $result['post_id'] ?? 0;
            // Link the post and set edit_url (status set to 'drafted')
            $this->planner->link_article_to_post($session_id, $article_id, $post_id);

            // Update the article status to 'completed' and ensure edit_url is present
            $meta = get_post_meta($session_id, '_kh_planner_data', true);
            if (is_array($meta) && !empty($meta['articles'])) {
                foreach ($meta['articles'] as &$article) {
                    if (($article['id'] ?? '') == $article_id) {
                        $article['status'] = 'completed';
                        // edit_url should already be set by link_article_to_post, but ensure it exists
                        if (empty($article['edit_url'])) {
                            $article['edit_url'] = admin_url("post.php?post={$post_id}&action=edit");
                        }
                        break;
                    }
                }
                // Save updated metadata back to the session
                update_post_meta($session_id, '_kh_planner_data', $meta);
            }
        }

        return $result;
    }

    public function run($params, $user_id) {
        $mode = $params['mode'];
        $planner_session_id = $params['planner_session_id'] ?? null;

        // Check budget via Intelligence Tier
        $budget_check = $this->intelligence->check_budget($user_id);
        if (is_wp_error($budget_check)) {
            return $budget_check;
        }

        // Merge incoming overrides with defaults/session logic
        $policy_params = $params['author_policy'] ?? [];
        if (isset($params['core_settings']) && is_array($params['core_settings'])) {
            $policy_params = array_merge($policy_params, $params['core_settings']);
        }
        $params['merged_policy'] = \KH\EditorialAuthor\Core\AuthorPolicy::sanitize($policy_params);

        switch ($mode) {
            case 'draft':
                return $this->handle_draft($params, $user_id);
            case 'abstract':
                return $this->handle_abstract($params, $user_id);
            case 'enrichment':
                return $this->handle_enrichment($params);
            default:
                return new WP_Error('invalid_mode', 'Invalid author mode.');
        }
    }

    private function handle_draft($params, $user_id) {
        $session_id = $params['planner_session_id'];
        $article_id = $params['article_id'] ?? null;
        
        if (!$session_id) {
            return new WP_Error('missing_session', 'Planner session ID is required for draft mode.');
        }

        $context = $this->planner->get_session_context($session_id, $article_id);
        if (is_wp_error($context)) {
            return $context;
        }

        // If a specific article was requested but not isolated, return an error
        if ($article_id && empty($context['target_article'])) {
            return new WP_Error('article_not_found', 'The requested article was not found in this planning session.');
        }

        // Determine persona from params or default to editor
        $persona = $params['persona'] ?? 'editor';
        if (!in_array($persona, \KH\Editorial\Core\LLMService::PERSONAS, true)) {
            $persona = 'editor';
        }

        // Merge session-level policy with user overrides from UI
        $final_policy = \KH\EditorialAuthor\Core\AuthorPolicy::sanitize(
            array_merge(
                $context['author_policy'] ?? [], 
                $params['merged_policy'] ?? [],
                ['enrichment' => $context['brand_profile'] ?? []]
            )
        );

        // Ensure citations are in a list for the prompt factory
        $context['citations'] = array_values($context['citations'] ?? []);

        // Prepare job data for async processing
        $persona_config = \KH\Editorial\Core\LLMService::resolve_persona_model($persona);
        $job_data = [
            'session_id' => $session_id,
            'status'     => 'queued',
            'model'      => $persona_config['model'],
            'provider'   => $persona_config['provider'],
            'prompt'     => wp_json_encode([
                'context'       => $context,
                'instructions'  => $params['instructions'] ?? '',
                'author_policy' => $final_policy,
                'article_id'    => $article_id,
                'enrichment'    => $params['enrichment'] ?? [],
                'mode'          => 'draft',
                'persona'       => $persona,
            ]),
            'created_by' => $user_id,
        ];

        $job_id = $this->intelligence->create_job($job_data);

        if (is_wp_error($job_id)) {
            return $job_id;
        }

        // Trigger background processing (can be hooked by a worker)
        do_action('kh_editorial_job_created', $job_id, 'draft');

        return [
            'status' => 'queued',
            'job_id' => $job_id
        ];
    }

    private function handle_abstract($params, $user_id) {
        $content = $params['draft_content'] ?? '';
        if (empty($content)) {
            return new WP_Error('missing_content', 'Draft content is required for abstract mode.');
        }

        return $this->abstract_agent->execute($content, $user_id);
    }

    private function handle_enrichment($params) {
        $content = $params['draft_content'] ?? '';
        $session_id = $params['planner_session_id'] ?? null;
        
        $citations = [];
        if ($session_id) {
            $citations = $this->planner->get_verified_citations($session_id);
        }

        $options = [
            'generate_images' => !empty($params['generate_images']),
            'image_provider'  => $params['image_provider'] ?? 'openai',
            'title'           => $params['title'] ?? '',
        ];

        return $this->enrichment_agent->execute($content, $citations, $options);
    }
}
