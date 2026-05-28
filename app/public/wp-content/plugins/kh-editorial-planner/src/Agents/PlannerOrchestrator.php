<?php

namespace KH\Editorial\Planner\Agents;

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
        
        $research_agent = new ResearchAgent();
        $phase1_data = $research_agent->gather_discovery_inputs( $topic, $includes );

        $prompt = PromptFactory::get_phase1_prompt( $topic, $focus, wp_json_encode( $phase1_data ) );
        
        return $this->enqueue_job( $post_id, $prompt, 'planner-p1-' . $post_id, 'phase1_running' );
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
        $prompt = PromptFactory::get_phase2_prompt( 
            $post->post_title, 
            $phase1_payload['executive_summary'] ?? '', 
            wp_json_encode( $ranked_metrics ) 
        );

        return $this->enqueue_job( $post_id, $prompt, 'planner-p2-' . $post_id, 'phase2_running' );
    }

    /**
     * Run Phase 3: Deep Dive / Topic Generation.
     */
    public function run_phase3( $post_id ) {
        $post = get_post( $post_id );
        $phase1_payload = get_post_meta( $post_id, 'kh_planner_phase1_result', true );
        $phase2_payload = get_post_meta( $post_id, 'kh_planner_phase2_result', true );
        
        $prompt = PromptFactory::get_phase3_prompt( 
            $post->post_title, 
            $phase1_payload['executive_summary'] ?? '', 
            wp_json_encode( $phase2_payload['ranked_keywords'] ?? [] ) 
        );

        return $this->enqueue_job( $post_id, $prompt, 'planner-p3-' . $post_id, 'phase3_running' );
    }

    /**
     * Run Phase 4: Validation.
     */
    public function run_phase4( $post_id ) {
        $post = get_post( $post_id );
        $phase3_payload = get_post_meta( $post_id, 'kh_planner_phase3_result', true );
        
        $prompt = PromptFactory::get_phase4_prompt( 
            $post->post_title, 
            wp_json_encode( $phase3_payload['prioritized_topics'] ?? [] ) 
        );

        return $this->enqueue_job( $post_id, $prompt, 'planner-p4-' . $post_id, 'phase4_running' );
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

        $directives = $policy_agent->get_exclusion_directives( $post_id );
        $prompt = PromptFactory::get_final_synopsis_prompt( $post->post_title, wp_json_encode( $context ), $directives );

        return $this->enqueue_job( $post_id, $prompt, 'planner-final-' . $post_id, 'generating_synopses' );
    }

    /**
     * Finalize the session: Generate the dossier and set status to complete.
     */
    public function finalize_session( $post_id ) {
        $artifact_agent = new ArtifactAgent();
        $artifact_agent->generate_dossier( $post_id );
        
        update_post_meta( $post_id, 'kh_planner_status', 'completed' );
        return [ 'session_id' => $post_id, 'status' => 'completed' ];
    }

    /**
     * Internal helper to enqueue jobs via the Orchestrator Service.
     */
    private function enqueue_job( $post_id, $prompt, $idempotency_key, $next_status ) {
        if ( ! class_exists( '\KH\Editorial\Services\AI\AIStorage' ) ) {
            return new \WP_Error( 'infrastructure_missing', 'AI Storage service not found.' );
        }

        $storage = new \KH\Editorial\Services\AI\AIStorage();
        $job_id = $storage->create_job( [
            'session_id'      => $post_id,
            'prompt'          => $prompt,
            'model'           => \KH\Editorial\Core\LLMService::get_model(),
            'idempotency_key' => $idempotency_key,
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
