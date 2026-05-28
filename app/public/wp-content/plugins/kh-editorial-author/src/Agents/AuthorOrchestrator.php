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
        $this->enrichment_agent = new EnrichmentAgent();
    }

    public function run($params, $user_id) {
        $mode = $params['mode'];
        $planner_session_id = $params['planner_session_id'] ?? null;

        // Check budget via Intelligence Tier
        $budget_check = $this->intelligence->check_budget($user_id);
        if (is_wp_error($budget_check)) {
            return $budget_check;
        }

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
        if (!$session_id) {
            return new WP_Error('missing_session', 'Planner session ID is required for draft mode.');
        }

        $context = $this->planner->get_session_context($session_id);
        if (is_wp_error($context)) {
            return $context;
        }

        return $this->draft_agent->execute($context, $params['instructions'] ?? '', $user_id);
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

        return $this->enrichment_agent->execute($content, $citations);
    }
}
