<?php

namespace KH\Planner\Agents;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PlannerOrchestrator
 * 
 * The 4-Phase Research Engine for the Editorial Planner.
 * Manages the flow from Discovery to Validation and Synopsis generation.
 */
class PlannerOrchestrator {

    /**
     * Static init for the orchestrator hooks.
     */
    public static function init() {
        // Reserved for background job hooks or specialized initialization
    }

    /**
     * Run the planner workflow for a session.
     */
    public function run( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'planner_session' ) {
            return new \WP_Error( 'invalid_session', 'Invalid planner session.' );
        }

        $topic    = $post->post_title;
        $includes = $this->normalize_terms( get_post_meta( $post_id, 'kh_planner_includes', true ) );
        $focus    = (int) get_post_meta( $post_id, 'kh_planner_focus_level', true ) ?: 50;
        
        // Read pillar and audience context from the session meta
        $pillar       = get_post_meta( $post_id, 'kh_planner_pillar', true ) ?: '';
        $audience_slug = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';
        
        // Resolve target blog_id from audience_slug for multisite-scoped coverage analysis
        $blog_id = null;
        if ( $audience_slug && class_exists( '\KH\Editorial\Services\AllocationService' ) ) {
            $alloc = new \KH\Editorial\Services\AllocationService();
            $blog_id = $alloc->resolve_blog_id( $audience_slug );
        }
        
        // Resolve audience context for Phase 1 — consistent with phases 2-4 pattern
        $audience_context = '';
        if ( $audience_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $audience_context = \KH\Editorial\Services\SiteAudienceProfile::get_audience_context( $audience_slug );
        }
        
        $research_agent = new ResearchAgent();
        $phase1_data = $research_agent->gather_discovery_inputs( $topic, $includes, '', $pillar, $audience_slug, $blog_id );

        $prompt = PromptFactory::get_phase1_prompt( $topic, $focus, wp_json_encode( $phase1_data ), $pillar, $audience_context );
        
        return $this->enqueue_job( $post_id, $prompt, 'research_phase1', 'planner-p1-' . $post_id, 'phase1_running' );
    }

    /**
     * Run Phase 2: Keyword Qualification.
     * 
     * This phase takes the candidate keywords from Phase 1, fetches real metrics,
     * and uses an LLM to prioritize them.
     */
    public function run_phase2( $post_id ) {
        $post = get_post( $post_id );
        $phase1_payload = get_post_meta( $post_id, 'kh_planner_phase1_result', true );
        
        if ( empty( $phase1_payload['candidate_keywords'] ) ) {
            return new \WP_Error( 'no_keywords', 'No candidate keywords found in Phase 1 result.' );
        }

        $research_agent = new ResearchAgent();
        $metrics = $research_agent->enrich_keywords( $phase1_payload['candidate_keywords'] );
        
        if ( is_wp_error( $metrics ) ) {
            return $metrics;
        }

        $ranked_metrics = $research_agent->rank_keywords( $metrics );

        // Read pillar and audience context from session meta
        $pillar           = get_post_meta( $post_id, 'kh_planner_pillar', true ) ?: '';
        $audience_slug    = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';
        $audience_context = '';
        if ( $audience_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $audience_context = \KH\Editorial\Services\SiteAudienceProfile::get_audience_context( $audience_slug );
        }

        $prompt = PromptFactory::get_phase2_prompt( 
            $post->post_title, 
            $phase1_payload['executive_summary'] ?? '', 
            wp_json_encode( $ranked_metrics ),
            $pillar,
            $audience_context
        );

        return $this->enqueue_job( $post_id, $prompt, 'research_phase2', 'planner-p2-' . $post_id, 'phase2_running' );
    }

    /**
     * Run Phase 3: Deep Dive / Topic Generation.
     */
    public function run_phase3( $post_id ) {
        $post = get_post( $post_id );
        $phase1_payload = get_post_meta( $post_id, 'kh_planner_phase1_result', true );
        $phase2_payload = get_post_meta( $post_id, 'kh_planner_phase2_result', true );

        // Read pillar and audience context from session meta
        $pillar           = get_post_meta( $post_id, 'kh_planner_pillar', true ) ?: '';
        $audience_slug    = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';
        $audience_context = '';
        if ( $audience_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $audience_context = \KH\Editorial\Services\SiteAudienceProfile::get_audience_context( $audience_slug );
        }
        
        $prompt = PromptFactory::get_phase3_prompt( 
            $post->post_title, 
            $phase1_payload['executive_summary'] ?? '', 
            wp_json_encode( $phase2_payload['ranked_keywords'] ?? [] ),
            $pillar,
            $audience_context
        );

        return $this->enqueue_job( $post_id, $prompt, 'research_phase3', 'planner-p3-' . $post_id, 'phase3_running' );
    }

    /**
     * Run Phase 4: Validation with real citation verification.
     *
     * Gathers citations from Phase 1 trend data, runs them through
     * CitationVerifier (CrossRef/OpenAlex/URL metadata), and includes
     * tier + authority scores in the LLM prompt context.
     */
    public function run_phase4( $post_id ) {
        $post = get_post( $post_id );
        $phase1_payload = get_post_meta( $post_id, 'kh_planner_phase1_result', true );
        $phase3_payload = get_post_meta( $post_id, 'kh_planner_phase3_result', true );

        // Read pillar and audience context from session meta
        $pillar           = get_post_meta( $post_id, 'kh_planner_pillar', true ) ?: '';
        $audience_slug    = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';
        $audience_context = '';
        if ( $audience_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $audience_context = \KH\Editorial\Services\SiteAudienceProfile::get_audience_context( $audience_slug );
        }

        // Gather citations from Phase 1 trends and verify them
        $verified_citations = [];
        $trends = $phase1_payload['trends'] ?? [];
        foreach ( $trends as $trend ) {
            foreach ( $trend['citations'] ?? [] as $citation ) {
                if ( ! empty( $citation['url'] ) ) {
                    $result = $this->verify_single_citation( $citation );
                    if ( ! is_wp_error( $result ) ) {
                        $verified_citations[] = $result;
                    }
                }
            }
        }

        // Persist verified citations to the DB
        if ( ! empty( $verified_citations ) && class_exists( '\KH\Planner\Core\CitationStore' ) ) {
            $store = new \KH\Planner\Core\CitationStore();
            $store->save_citations( $post_id, null, $verified_citations );
        }

        $verification_context = [
            'verified_citations_count' => count( $verified_citations ),
            'verified_citations'       => $verified_citations,
        ];

        $prompt = PromptFactory::get_phase4_prompt(
            $post->post_title,
            wp_json_encode( $phase3_payload['prioritized_topics'] ?? [] ),
            wp_json_encode( $verification_context ),
            $pillar,
            $audience_context
        );

        return $this->enqueue_job( $post_id, $prompt, 'research_phase4', 'planner-p4-' . $post_id, 'phase4_running' );
    }

    /**
     * Verify a single citation candidate via the Intelligence CitationVerifier.
     */
    private function verify_single_citation( $citation ) {
        if ( ! class_exists( '\KH\Editorial\Services\CitationVerifier' ) ) {
            return new \WP_Error( 'citation_verifier_missing', 'CitationVerifier service not available.' );
        }

        $verifier = new \KH\Editorial\Services\CitationVerifier();
        return $verifier->verify_citation( [
            'url'         => $citation['url'] ?? '',
            'title'       => $citation['title'] ?? '',
            'doi'         => $citation['doi'] ?? '',
            'source_type' => $citation['source_type'] ?? 'industry',
        ] );
    }

    /**
     * Run Final Step: Synopsis Generation.
     */
    public function run_final_generation( $post_id ) {
        $post = get_post( $post_id );
        $policy_agent = new PolicyAgent();
        
        // Gather all previous context to give the LLM full "memory"
        $context = [
            'phase1' => get_post_meta( $post_id, 'kh_planner_phase1_result', true ),
            'phase2' => get_post_meta( $post_id, 'kh_planner_phase2_result', true ),
            'phase3' => get_post_meta( $post_id, 'kh_planner_phase3_result', true ),
            'phase4' => get_post_meta( $post_id, 'kh_planner_phase4_result', true ),
        ];

        // Read pillar and audience context from session meta
        $pillar           = get_post_meta( $post_id, 'kh_planner_pillar', true ) ?: '';
        $audience_slug    = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';
        $audience_context = '';
        if ( $audience_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $audience_context = \KH\Editorial\Services\SiteAudienceProfile::get_audience_context( $audience_slug );
        }

        $directives = $policy_agent->get_exclusion_directives( $post_id );
        $prompt = PromptFactory::get_final_synopsis_prompt( $post->post_title, wp_json_encode( $context ), $directives, $pillar, $audience_context );

        return $this->enqueue_job( $post_id, $prompt, 'framework', 'planner-final-' . $post_id, 'generating_synopses' );
    }

    /**
     * Finalize the session: Generate the dossier and set status to complete.
     */
    public function finalize_session( $post_id ) {
        $artifact_agent = new ArtifactAgent();
        $artifact_agent->generate_dossier( $post_id );

        // Build and persist a framework brief from session data
        $this->save_framework_brief( $post_id );

        update_post_meta( $post_id, 'kh_planner_status', 'completed' );
        return [ 'session_id' => $post_id, 'status' => 'completed' ];
    }

    /**
     * Build and persist a framework brief from the completed session data.
     */
    private function save_framework_brief( $post_id ) {
        if ( ! class_exists( '\KH\Planner\Core\BriefStore' ) ) {
            return;
        }

        $post = get_post( $post_id );
        $phase1 = get_post_meta( $post_id, 'kh_planner_phase1_result', true );
        $phase2 = get_post_meta( $post_id, 'kh_planner_phase2_result', true );
        $phase3 = get_post_meta( $post_id, 'kh_planner_phase3_result', true );
        $phase4 = get_post_meta( $post_id, 'kh_planner_phase4_result', true );
        $synopses = get_post_meta( $post_id, 'kh_planner_final_synopses', true );

        // Gather citation IDs from the CitationStore
        $citation_ids = [];
        if ( class_exists( '\KH\Planner\Core\CitationStore' ) ) {
            $citation_store = new \KH\Planner\Core\CitationStore();
            $citations = $citation_store->get_citations_by_session( $post_id, true );
            $citation_ids = wp_list_pluck( $citations, 'id' );
        }

        // Read pillar and audience context from the session
        $pillar       = get_post_meta( $post_id, 'kh_planner_pillar', true ) ?: '';
        $audience_slug = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';

        $store = new \KH\Planner\Core\BriefStore();
        $brief_id = $store->save_brief( $post_id, [
            'title'          => $post->post_title,
            'overview'       => $phase1['executive_summary'] ?? '',
            'context'        => wp_json_encode( [
                'phase4_validation' => $phase4['validation_summary'] ?? '',
                'synopses'          => $synopses['synopses'] ?? [],
                'pillar'            => $pillar,
                'audience_slug'     => $audience_slug,
            ] ),
            'key_themes'     => $phase3['prioritized_topics'] ?? [],
            'citations'      => $citation_ids,
            'writer_guidance'=> $synopses['synopses'] ?? [],
            'scoring'        => $phase4['validated_topics'] ?? [],
            'metadata'       => [
                'phase2_keywords' => $phase2['ranked_keywords'] ?? [],
                'generated_at'    => current_time( 'mysql' ),
                'pillar'          => $pillar,
                'audience_slug'   => $audience_slug,
            ],
        ] );

        if ( $brief_id ) {
            // Link citations to this brief
            if ( ! empty( $citation_ids ) && class_exists( '\KH\Planner\Core\CitationStore' ) ) {
                $citation_store->link_citations_to_brief( $brief_id, $post_id );
            }

            update_post_meta( $post_id, 'kh_planner_brief_id', $brief_id );
        }
    }

    /**
     * Internal helper to enqueue jobs via the Orchestrator Service.
     */
    private function enqueue_job( $post_id, $prompt, $agent_key, $idempotency_key, $next_status ) {
        if ( ! \KH\Editorial\Core\LLMService::is_configured() ) {
            return new \WP_Error( 'llm_unconfigured', 'LLM API key is not configured. Cannot enqueue job.' );
        }

        if ( ! class_exists( '\KH\Editorial\Services\AI\AIStorage' ) ) {
            return new \WP_Error( 'infrastructure_missing', 'AI Storage service not found.' );
        }

        $route = \KH\Editorial\Core\LLMService::resolve_agent_model( $agent_key );

        $storage = new \KH\Editorial\Services\AI\AIStorage();

        // Check user budget before enqueuing to prevent wasted API calls
        $author_id = (int) get_post_field( 'post_author', $post_id );
        $budget = $storage->check_budget( $author_id );
        if ( ! $budget['has_budget'] ) {
            return new \WP_Error(
                'budget_exhausted',
                sprintf(
                    'User budget exhausted. Limit: %d tokens, Used: %d tokens.',
                    $budget['token_limit'],
                    $budget['token_used']
                )
            );
        }

        $job_id = $storage->insert_job( [
            'session_id'      => $post_id,
            'prompt'          => $prompt,
            'model'           => $route['model'],
            'provider'        => $route['provider'],
            'idempotency_key' => $idempotency_key,
            'type'            => 'planner',
            'created_by'      => get_current_user_id()
        ] );

        if ( is_wp_error( $job_id ) ) {
            return $job_id;
        }

        update_post_meta( $post_id, 'kh_planner_status', $next_status );
        update_post_meta( $post_id, 'kh_planner_current_job', $job_id );
        
        return [ 'session_id' => $post_id, 'job_id' => $job_id, 'status' => $next_status ];
    }


    private function normalize_terms( $terms ) {
        if ( is_string( $terms ) ) {
            $terms = array_filter( array_map( 'trim', explode( ',', $terms ) ) );
        }
        return is_array( $terms ) ? $terms : [];
    }
}
