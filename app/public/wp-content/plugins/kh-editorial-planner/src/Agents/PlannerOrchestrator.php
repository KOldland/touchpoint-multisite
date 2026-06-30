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
     * @var array Cached dependency status for graceful degradation.
     */
    private static $dependency_status = [];

    /**
     * Static init for the orchestrator hooks.
     * 
     * @param array $deps Dependency status array (optional, for forward compatibility).
     */
    public static function init( $deps = [] ) {
        // Store dependency status for use in methods that need it
        if ( ! empty( $deps ) ) {
            self::$dependency_status = $deps;
        }
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
        $settings = get_option( 'kh_editorial_settings', [] );
        $focus    = isset( $settings['planner_focus_level'] ) ? (int) $settings['planner_focus_level'] : 50;
        
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
        $pillar_keywords = [];
        if ( $audience_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $audience_profile = \KH\Editorial\Services\SiteAudienceProfile::get_profile( $audience_slug );
            if ( $audience_profile ) {
                $audience_context = \KH\Editorial\Services\SiteAudienceProfile::get_audience_context( $audience_slug );
                foreach ( $audience_profile['pillars'] as $p ) {
                    if ( ( $p['name'] ?? '' ) === $pillar ) {
                        $pillar_keywords = $p['keywords'] ?? [];
                        break;
                    }
                }
            }
        }
        
        $research_agent = new ResearchAgent();
        $phase1_data = $research_agent->gather_discovery_inputs( $topic, $includes, '', $pillar, $audience_slug, $blog_id );

        $prompt = PromptFactory::get_phase1_prompt( $topic, $focus, wp_json_encode( $phase1_data ), $pillar, $audience_context, $pillar_keywords );
        
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

        // Extract keyword strings from objects if Phase 1 stored full keyword objects
        $keyword_strings = array_map( function( $kw ) {
            return is_array( $kw ) ? ( $kw['keyword'] ?? '' ) : (string) $kw;
        }, $phase1_payload['candidate_keywords'] );
        $keyword_strings = array_values( array_filter( $keyword_strings ) );

        if ( empty( $keyword_strings ) ) {
            return new \WP_Error( 'no_valid_keywords', 'No valid keyword strings could be extracted from Phase 1 result.' );
        }

        $research_agent = new ResearchAgent();
        $metrics = $research_agent->enrich_keywords( $keyword_strings );
        
        if ( is_wp_error( $metrics ) ) {
            // DataForSEO is unavailable (SSL, auth, network). Fall back to
            // bare keyword strings so the LLM can still prioritise them.
            error_log( '[PLANNER] Phase 2 enrich_keywords failed: ' . $metrics->get_error_message() . ' — falling back to raw keywords' );
            $ranked_metrics = $keyword_strings;
        } else {
            $ranked_metrics = $research_agent->rank_keywords( $metrics );
        }

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

        // Gather citations from Phase 1 trends and verify them.
        // Preserve ALL citations (even unverifiable ones) so the LLM can still
        // reference them with appropriate confidence flags.
        $verified_citations   = [];
        $unverified_citations = [];
        $trends = $phase1_payload['trends'] ?? [];
        foreach ( $trends as $trend ) {
            foreach ( $trend['citations'] ?? [] as $citation ) {
                // Convert string citations to structured format
                if ( is_string( $citation ) ) {
                    $unverified_citations[] = [
                        'title'       => $citation,
                        'url'         => '',
                        'source_type' => 'industry',
                        'confidence'  => 0.1,
                        'note'        => 'Phase 1 trend reference — URL not available for verification.',
                    ];
                    continue;
                }
                if ( ! empty( $citation['url'] ) ) {
                    $result = $this->verify_single_citation( $citation );
                    if ( ! is_wp_error( $result ) ) {
                        $result['confidence'] = max( $result['confidence'] ?? 0.0, 0.7 );
                        $verified_citations[] = $result;
                    } else {
                        // Keep unverifiable citations with a low confidence flag
                        $unverified_citations[] = [
                            'url'         => $citation['url'],
                            'title'       => $citation['title'] ?? '',
                            'source_type' => $citation['source_type'] ?? 'industry',
                            'confidence'  => 0.1,
                            'note'        => 'Verification unavailable — include if contextually relevant.',
                        ];
                    }
                } else {
                    // Citations without URLs — include as unverified
                    $unverified_citations[] = [
                        'title'       => $citation['title'] ?? 'Unnamed reference',
                        'url'         => '',
                        'source_type' => $citation['source_type'] ?? 'industry',
                        'confidence'  => 0.1,
                        'note'        => 'No URL available for verification — include if contextually relevant.',
                    ];
                }
            }
        }

        // Persist verified citations to the DB; don't persist unverifiable ones
        if ( ! empty( $verified_citations ) && class_exists( '\KH\Planner\Core\CitationStore' ) ) {
            $store = new \KH\Planner\Core\CitationStore();
            $store->save_citations( $post_id, null, $verified_citations );
        }

        $verification_context = [
            'verified_citations_count'   => count( $verified_citations ),
            'unverified_citations_count' => count( $unverified_citations ),
            'verified_citations'         => $verified_citations,
            'unverified_citations'       => $unverified_citations,
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
     * Build a synopsis plan from Phase 3/4 topics.
     *
     * Returns a { plan: { "Topic Name": count } } map so the UI can render
     * per-topic sliders and the user can adjust counts before generation.
     */
    public function run_synopsis_plan( $post_id ) {
        $phase3 = get_post_meta( $post_id, 'kh_planner_phase3_result', true ) ?: [];
        $phase4 = get_post_meta( $post_id, 'kh_planner_phase4_result', true ) ?: [];

        $topics = $phase4['validated_topics'] ?? $phase3['prioritized_topics'] ?? [];

        if ( empty( $topics ) ) {
            return new \WP_Error( 'no_topics', 'No prioritized or validated topics found for this session.' );
        }

        $plan = [];
        foreach ( $topics as $topic ) {
            $title = $topic['topic'] ?? ( $topic['title'] ?? __( 'Untitled', 'kh-editorial-planner' ) );
            $plan[ $title ] = 1; // default 1 synopsis per topic
        }

        return [
            'session_id' => $post_id,
            'plan'       => $plan,
        ];
    }

    /**
     * Generate article synopses from the synopsis plan.
     *
     * Delegates to run_final_generation which builds the full-context prompt
     * and enqueues a single LLM job to produce all synopses at once.
     */
    public function run_synopses( $post_id ) {
        return $this->run_final_generation( $post_id );
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
        
        // Read synopsis_count from session meta (per-session override), fall back to 4
        $session_meta = $this->get_planner_meta_array( $post_id );
        $synopsis_count = in_array( (int) ( $session_meta['synopsis_count'] ?? 4 ), [ 1, 4, 8 ] ) ? (int) $session_meta['synopsis_count'] : 4;
        
        $prompt = PromptFactory::get_final_synopsis_prompt( $post->post_title, wp_json_encode( $context ), $directives, $pillar, $audience_context, $synopsis_count );

        return $this->enqueue_job( $post_id, $prompt, 'framework', 'planner-final-' . $post_id, 'generating_synopses' );
    }

    /**
     * Run a "Dive Deeper" research action for a specific article.
     *
     * Dispatches an LLM job to find additional citations for the given
     * article headline. Results are stored in session meta so the frontend
     * can retrieve them.
     */
    public function run_dive_deeper( $post_id, $article_id, $params = [] ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'planner_session' ) {
            return new \WP_Error( 'invalid_session', 'Invalid planner session.' );
        }

        $pillar           = get_post_meta( $post_id, 'kh_planner_pillar', true ) ?: '';
        $audience_slug    = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';
        $audience_context = '';
        if ( $audience_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $audience_context = \KH\Editorial\Services\SiteAudienceProfile::get_audience_context( $audience_slug );
        }

        // Always request 4 fresh citations — the existing synopsis citations may
        // be low-quality or from training data, so we want real research-backed
        // citations regardless of what's already stored.
        $needed = 4;

        $prompt = PromptFactory::get_research_dive_prompt( $article_id, $pillar, $audience_context, $needed );

        // Use a unique idempotency key per attempt so re-runs create a fresh job.
        // Must fit in varchar(64) column.
        $short_slug = substr( md5( $article_id . time() ), 0, 12 );
        $idempotency_key = 'dive-' . $post_id . '-' . $short_slug;

        return $this->enqueue_job( $post_id, $prompt, 'research', $idempotency_key, 'phase_complete' );
    }

    /**
     * Run Framework Generation for a specific article.
     *
     * Collects citations via web search, builds an LLM prompt requesting a
     * structured editorial framework, enqueues the job, and stores the
     * framework status in session meta.
     *
     * @param int    $post_id    Session post ID.
     * @param string $article_id Article ID from session meta.
     * @param bool   $force      If true, generate a unique idempotency key.
     * @return array|\WP_Error
     */
    public function run_framework_generation( $post_id, $article_id, $force = false ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'planner_session' ) {
            return new \WP_Error( 'invalid_session', 'Invalid planner session.' );
        }

        // Load session meta and find the target article
        $session_meta = $this->get_planner_meta_array( $post_id );
        $articles     = $session_meta['articles'] ?? [];
        $article      = null;
        $article_idx  = null;
        foreach ( $articles as $idx => $a ) {
            if ( ( $a['id'] ?? '' ) === $article_id ) {
                $article     = $a;
                $article_idx = $idx;
                break;
            }
        }
        if ( ! $article ) {
            return new \WP_Error( 'article_not_found', 'Article not found in session meta.', [ 'status' => 404 ] );
        }

        $pillar        = get_post_meta( $post_id, 'kh_planner_pillar', true ) ?: '';
        $audience_slug = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';
        $audience_context = '';
        if ( $audience_slug && class_exists( '\KH\Editorial\Services\SiteAudienceProfile' ) ) {
            $audience_context = \KH\Editorial\Services\SiteAudienceProfile::get_audience_context( $audience_slug );
        }

        // Collect citations via web search
        $citations = $this->collect_framework_citations( $article );
        if ( is_wp_error( $citations ) ) {
            $this->update_article_framework_status( $post_id, $articles, $article_idx, 'failed', [
                'error_message' => $citations->get_error_message(),
                'completed_at'  => current_time( 'mysql' ),
            ] );
            return $citations;
        }

        // Build the LLM prompt
        $prompt = $this->build_framework_prompt( $article, $citations, $pillar, $audience_context );

        // Build idempotency key (with force re-run support)
        $base_key = 'fw-' . $post_id . '-' . substr( md5( $article_id ), 0, 12 );
        $idempotency_key = $force ? $base_key . '-r' . time() : $base_key;

        // Enqueue the job using the 'framework' agent key so it resolves to
        // the profile-configured framework model (gpt-4o-mini in Balanced)
        // which supports response_format: json_object for structured output.
        $result = $this->enqueue_job( $post_id, $prompt, 'framework', $idempotency_key, 'phase_complete' );
        if ( is_wp_error( $result ) ) {
            $this->update_article_framework_status( $post_id, $articles, $article_idx, 'failed', [
                'error_message' => $result->get_error_message(),
                'completed_at'  => current_time( 'mysql' ),
            ] );
            return $result;
        }

        // Store framework status as 'running' in session meta
        $this->update_article_framework_status( $post_id, $articles, $article_idx, 'running', [
            'started_at' => current_time( 'mysql' ),
            'job_id'     => $result['job_id'],
        ] );

        return [
            'session_id' => $post_id,
            'article_id' => $article_id,
            'job_id'     => $result['job_id'],
            'citations'  => $citations,
        ];
    }

    /**
     * Update the framework status for an article in session meta.
     */
    private function update_article_framework_status( $post_id, &$articles, $article_idx, $status, $extra = [] ) {
        if ( null === $article_idx || ! isset( $articles[ $article_idx ] ) ) {
            return;
        }
        $articles[ $article_idx ]['framework'] = array_merge(
            [ 'status' => $status ],
            $extra
        );
        
        // Create registry entry when framework generation completes
        if ( $status === 'completed' && class_exists( '\KH\ContentRegistry\Services\ContentRegistryService' ) ) {
            $article = &$articles[ $article_idx ];
            $framework_content = $article['framework']['content'] ?? '';
            
            if ( ! empty( $framework_content ) && empty( $article['framework']['registry_id'] ) ) {
                $registry = \KH\ContentRegistry\Services\ContentRegistryService::instance();
                
                // Resolve target_blog_id from audience_slug
                $audience_slug = get_post_meta( $post_id, 'kh_planner_audience_slug', true ) ?: '';
                $blog_id = null;
                if ( $audience_slug && class_exists( '\KH\Editorial\Services\AllocationService' ) ) {
                    $alloc = new \KH\Editorial\Services\AllocationService();
                    $blog_id = $alloc->resolve_blog_id( $audience_slug );
                }
                
                if ( $blog_id ) {
                    $result = $registry->create_article([
                        'target_blog_id' => $blog_id,
                        'slug' => sanitize_title( $article['headline'] ?? 'framework-' . time() ),
                        'article_status' => 'Framework',
                        'title' => $article['headline'] ?? '',
                        'content_body' => $framework_content,
                    ]);
                    
                    if ( ! is_wp_error( $result ) ) {
                        $article['framework']['registry_id'] = $result;
                    } else {
                        error_log( 'ContentRegistry create_article failed: ' . $result->get_error_message() );
                    }
                }
            }
        }
        
        $session_meta = $this->get_planner_meta_array( $post_id );
        $session_meta['articles'] = $articles;
        update_post_meta( $post_id, 'kh_planner_meta', wp_json_encode( $session_meta ) );
    }

    /**
     * Collect citation candidates for a framework brief via web search.
     *
     * Expanded version: generates more queries, collects up to 15 candidates,
     * classifies source types, enriches metadata, scores authority, and
     * enforces diversity for the final selection.
     *
     * @param array $article Article data from session meta.
     * @return array|\WP_Error Array of citation arrays or error.
     */
    private function collect_framework_citations( $article ) {
        $title    = sanitize_text_field( $article['headline'] ?? ( $article['title'] ?? '' ) );
        $summary  = sanitize_textarea_field( $article['summary'] ?? ( $article['brief'] ?? '' ) );
        $keywords = isset( $article['keywords'] ) && is_array( $article['keywords'] ) ? $article['keywords'] : [];

        if ( empty( $title ) ) {
            return new \WP_Error( 'framework_no_title', 'Article has no headline.' );
        }

        // ── Build expanded search queries ──────────────────────────────────
        // Include both traditional industry queries and academic/analyst queries
        // to encourage diverse source type coverage.
        // Build search query pool — combine the article headline with contextual variations.
        // If a summary is available, also generate content-targeted queries for better recall.
        $summary_brief = ! empty( $summary ) ? substr( $summary, 0, 120 ) : '';
        $queries = [
            $title . ' statistics data',
            $title . ' case study',
            $title . ' industry report',
            $title . ' academic research',
            $title . ' analyst report',
            $title . ' white paper',
            $title . ' market research',
        ];
        // Include a summary-targeted query for richer contextual results
        if ( $summary_brief ) {
            $queries[] = $summary_brief;
        }
        foreach ( array_slice( $keywords, 0, 3 ) as $kw ) {
            if ( is_string( $kw ) && $kw !== '' ) {
                $queries[] = $kw . ' trend';
                $queries[] = $kw . ' research study';
            }
        }

        // Use ResearchAgent's SearchProvider for web search
        $search_provider = null;
        if ( class_exists( '\KH\Editorial\Providers\SearchProvider' ) ) {
            $search_provider = new \KH\Editorial\Providers\SearchProvider();
        }

        $candidates = [];
        foreach ( $queries as $query ) {
            if ( $search_provider ) {
                $results = $search_provider->search( $query, 8 );
                if ( ! is_wp_error( $results ) && ! empty( $results['organic_results'] ) ) {
                    foreach ( $results['organic_results'] as $item ) {
                        $url = $item['link'] ?? ( $item['url'] ?? '' );
                        if ( $url === '' ) {
                            continue;
                        }
                        $candidates[ $url ] = [
                            'url'     => $url,
                            'title'   => $item['title'] ?? '',
                            'snippet' => $item['snippet'] ?? '',
                        ];
                    }
                }
            } else {
                // Fallback: simulated results when SearchProvider is unavailable
                $candidates[ 'https://example.com/research/' . sanitize_title( $query ) ] = [
                    'url'     => 'https://example.com/research/' . sanitize_title( $query ),
                    'title'   => 'Research: ' . $query,
                    'snippet' => 'Industry insights and analysis regarding ' . $query . '.',
                ];
            }
        }

        if ( empty( $candidates ) ) {
            return new \WP_Error( 'framework_no_candidates', 'No citation candidates found via web search.' );
        }

        // ── Classify source types ──────────────────────────────────────────
        $classified = [];
        foreach ( $candidates as $url => $item ) {
            $source_type = $this->classify_source_type( $url, $item['title'] ?? '', $item['snippet'] ?? '' );
            $classified[ $url ] = array_merge( $item, [
                'source_type' => $source_type,
            ] );
        }

        // ── Enrich with metadata and score ─────────────────────────────────
        $enriched = [];
        foreach ( $classified as $url => $item ) {
            $metadata = $this->enrich_citation_metadata( $url, $item );
            $authority_score = $this->score_citation_authority( $url, $item['source_type'], $metadata );
            $enriched[] = array_merge(
                [
                    'url'          => $url,
                    'title'        => $item['title'],
                    'snippet'      => $item['snippet'],
                    'source_type'  => $item['source_type'],
                    'score'        => $authority_score,
                ],
                $metadata
            );
        }

        // ── Sort by authority score descending ─────────────────────────────
        usort( $enriched, function ( $a, $b ) {
            return ( $b['score'] ?? 0 ) <=> ( $a['score'] ?? 0 );
        } );

        // ── Select top 6-8 with diversity enforcement ──────────────────────
        $selected = $this->select_diverse_citations( $enriched, 8 );

        return $selected;
    }

    /**
     * Classify the source type of a URL/title/snippet combination.
     *
     * @param string $url     The candidate URL.
     * @param string $title   The page title.
     * @param string $snippet The page snippet.
     * @return string One of: academic, analyst, industry, case_study, trade
     */
    private function classify_source_type( $url, $title, $snippet ) {
        $text = strtolower( $url . ' ' . $title . ' ' . $snippet );
        $host = strtolower( parse_url( $url, PHP_URL_HOST ) ?: '' );

        // Academic: .edu domains or scholarly indicators
        if ( preg_match( '/\.edu\b/', $host ) || preg_match( '/researchgate|academia|scholar|jstor|springer|sciencedirect|wiley|taylorfrancis|sagepub|ieee|acm\.org|cambridge\.org|oxford|harvard|mit\.edu|stanford\.edu/', $text ) ) {
            return 'academic';
        }
        // Analyst: known analyst organisations
        if ( preg_match( '/gartner|forrester|mckinsey|bain|deloitte|pwc|bcg|kpmg|idc|nielsen|ibm research|accenture/', $text ) ) {
            return 'analyst';
        }
        // Case study: explicit mention
        if ( preg_match( '/case study|customer story|success story|how .+ achieved/', $text ) ) {
            return 'case_study';
        }
        // Trade publications
        if ( preg_match( '/\.org\b|trade|association|conference|expo|webinar|magazine/', $text ) ) {
            return 'trade';
        }
        // Default: industry
        return 'industry';
    }

    /**
     * Enrich citation metadata by attempting URL metadata fetch.
     *
     * @param string $url  The candidate URL.
     * @param array  $item Basic item data.
     * @return array Enriched metadata including lead_author, organisation, etc.
     */
    private function enrich_citation_metadata( $url, $item ) {
        $metadata = [
            'lead_author'        => '',
            'additional_authors' => '',
            'organisation'       => parse_url( $url, PHP_URL_HOST ) ?: '',
            'publication_date'   => '',
            'publication'        => '',
        ];

        // Try CitationVerifier if available
        if ( class_exists( '\KH\Editorial\Services\CitationVerifier' ) ) {
            try {
                $verifier = new \KH\Editorial\Services\CitationVerifier();
                $result = $verifier->verify_citation( [
                    'url'         => $url,
                    'title'       => $item['title'] ?? '',
                    'source_type' => $item['source_type'] ?? 'industry',
                ] );
                if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
                    $metadata['lead_author']        = $result['lead_author'] ?? '';
                    $metadata['additional_authors'] = $result['additional_authors'] ?? '';
                    $metadata['organisation']       = $result['publisher'] ?? $result['organisation'] ?? $metadata['organisation'];
                    $metadata['publication_date']   = $result['publication_date'] ?? '';
                    $metadata['publication']        = $result['publication'] ?? $result['journal'] ?? '';
                }
            } catch ( \Exception $e ) {
                // Silently fall back to domain-based metadata
                error_log( '[PLANNER] CitationVerifier failed for URL ' . $url . ': ' . $e->getMessage() );
            }
        }

        // Domain-based organisation fallback
        if ( empty( $metadata['organisation'] ) ) {
            $host = parse_url( $url, PHP_URL_HOST ) ?: '';
            $metadata['organisation'] = $host;
        }

        return $metadata;
    }

    /**
     * Score citation authority based on source type, domain, recency, and APA.
     *
     * @param string $url         The candidate URL.
     * @param string $source_type Source type classification.
     * @param array  $metadata    Enriched citation metadata.
     * @return float Score 0-1.
     */
    private function score_citation_authority( $url, $source_type, $metadata ) {
        $score = 0.0;
        $host  = strtolower( parse_url( $url, PHP_URL_HOST ) ?: '' );

        // Source type authority
        switch ( $source_type ) {
            case 'academic':
                $score += 0.3;
                break;
            case 'analyst':
                $score += 0.25;
                break;
            case 'industry':
                $score += 0.18;
                break;
            case 'case_study':
                $score += 0.15;
                break;
            case 'trade':
                $score += 0.1;
                break;
        }

        // Domain authority
        if ( preg_match( '/\.gov$|\.edu$/', $host ) ) {
            $score += 0.4;
        } elseif ( preg_match( '/mckinsey|gartner|deloitte|pwc|forrester|bain|hbr|hbr\.org|fieldservicenews|bcg|kpmg|idc|springer|sciencedirect|jstor|ieee|acm\.org/', $host ) ) {
            $score += 0.35;
        } else {
            $score += 0.15;
        }

        // Recency bonus (+0.1 if within 2 years)
        if ( ! empty( $metadata['publication_date'] ) ) {
            $pub_date = strtotime( $metadata['publication_date'] );
            if ( $pub_date !== false && ( time() - $pub_date ) < 2 * YEAR_IN_SECONDS ) {
                $score += 0.1;
            }
        }

        // APA metadata availability bonus (+0.1 if author present)
        if ( ! empty( $metadata['lead_author'] ) ) {
            $score += 0.1;
        }

        return min( 1.0, $score );
    }

    /**
     * Select diverse citations from the enriched pool.
     *
     * Enforces: at least 1 academic, 1 analyst, 1 industry, 1 case study (if available),
     * max 2 citations per organisation, sorted by authority score.
     *
     * @param array $candidates Enriched citation arrays.
     * @param int   $max_count  Maximum number to return (default 8).
     * @return array Selected citations.
     */
    private function select_diverse_citations( array $candidates, int $max_count = 8 ): array {
        // Strategy: ensure diversity by picking top from each source type,
        // then fill remaining slots by authority score.
        //
        // Operate on a copy of the candidates list — never mutate the input array.

        $required_types = [ 'academic', 'analyst', 'industry', 'case_study' ];
        $selected = [];
        $org_counts = [];
        $remaining = $candidates; // copy — never destroys input

        // Phase 1: pick the top candidate from each required type (if available)
        foreach ( $required_types as $type ) {
            $found_idx = null;
            foreach ( $remaining as $idx => $cand ) {
                if ( ( $cand['source_type'] ?? '' ) !== $type ) {
                    continue;
                }
                $org = $cand['organisation'] ?? '';
                if ( isset( $org_counts[ $org ] ) && $org_counts[ $org ] >= 2 ) {
                    continue;
                }
                $found_idx = $idx;
                break;
            }
            if ( null !== $found_idx ) {
                $cand = $remaining[ $found_idx ];
                $org = $cand['organisation'] ?? '';
                $selected[] = $cand;
                $org_counts[ $org ] = ( $org_counts[ $org ] ?? 0 ) + 1;
                array_splice( $remaining, $found_idx, 1 );
            }
        }

        // Phase 2: fill remaining slots by authority score (respecting org limit)
        foreach ( $remaining as $cand ) {
            if ( count( $selected ) >= $max_count ) {
                break;
            }
            $org = $cand['organisation'] ?? '';
            if ( isset( $org_counts[ $org ] ) && $org_counts[ $org ] >= 2 ) {
                continue;
            }
            $selected[] = $cand;
            $org_counts[ $org ] = ( $org_counts[ $org ] ?? 0 ) + 1;
        }

        // Re-sort selected by score descending
        usort( $selected, function ( $a, $b ) {
            return ( $b['score'] ?? 0 ) <=> ( $a['score'] ?? 0 );
        } );

        // Trim to max_count
        $selected = array_slice( $selected, 0, $max_count );

        // Build the final citation array (standardised format)
        $citations = [];
        foreach ( $selected as $item ) {
            $host = parse_url( $item['url'], PHP_URL_HOST ) ?: '';
            $citations[] = [
                'url'               => $item['url'],
                'title'             => $item['title'],
                'snippet'           => $item['snippet'] ?? '',
                'lead_author'       => $item['lead_author'] ?? '',
                'additional_authors'=> $item['additional_authors'] ?? '',
                'organisation'      => $item['organisation'] ?? $host,
                'source'            => $host,
                'publication_date'  => $item['publication_date'] ?? '',
                'source_type'       => $item['source_type'] ?? 'industry',
            ];
        }

        return $citations;
    }

    /**
     * Score a candidate URL based on domain authority (legacy method — kept
     * for backward compatibility; new pipeline uses score_citation_authority).
     *
     * @param string $url The candidate URL.
     * @return float Score 0-1.
     */
    private function score_candidate_url( $url ) {
        $score = 0.0;
        $host  = parse_url( $url, PHP_URL_HOST );
        if ( $host ) {
            if ( preg_match( '/\.gov$|\.edu$/', $host ) ) {
                $score += 0.4;
            } elseif ( preg_match( '/mckinsey|gartner|deloitte|pwc|forrester|bain|hbr|hbr\.org|fieldservicenews/i', $host ) ) {
                $score += 0.35;
            } else {
                $score += 0.2;
            }
        }
        return min( 1.0, $score );
    }

    /**
     * Build the LLM prompt for framework generation.
     *
     * Returns a prompt that asks the LLM to produce the structured JSON
     * that the frontend framework preview modal expects.
     *
     * @param array  $article          Article data.
     * @param array  $citations        Citation data.
     * @param string $pillar           Pillar name (optional).
     * @param string $audience_context Audience context (optional).
     * @return string The prompt.
     */
    private function build_framework_prompt( $article, $citations, $pillar = '', $audience_context = '' ) {
        $title   = $article['headline'] ?? ( $article['title'] ?? '' );
        $summary = $article['summary'] ?? ( $article['brief'] ?? '' );
        $keywords = isset( $article['keywords'] ) && is_array( $article['keywords'] )
            ? array_slice( $article['keywords'], 0, 6 )
            : [];

        $article_payload = [
            'id'       => $article['id'] ?? '',
            'title'    => $title,
            'summary'  => $summary,
            'keywords' => $keywords,
        ];

        // Pass up to 8 citations now (was 5)
        $citation_payload = [];
        foreach ( array_slice( $citations, 0, 8 ) as $c ) {
            $citation_payload[] = [
                'title'            => $c['title'] ?? '',
                'url'              => $c['url'] ?? '',
                'source'           => $c['source'] ?? ( $c['organisation'] ?? '' ),
                'lead_author'      => $c['lead_author'] ?? '',
                'additional_authors' => $c['additional_authors'] ?? '',
                'organisation'     => $c['organisation'] ?? '',
                'publication_date' => $c['publication_date'] ?? '',
                'source_type'      => $c['source_type'] ?? '',
                'snippet'          => isset( $c['snippet'] ) && is_string( $c['snippet'] )
                    ? substr( $c['snippet'], 0, 180 )
                    : '',
            ];
        }

        $apa_examples = <<<APA_EXAMPLES
APA Citation Examples:
- Journal: Author, A. A., & Author, B. B. (2024). Title of article. Journal Name, 12(3), 45-67. https://doi.org/xxxx
- Report: Organisation Name. (2024). Title of report. https://url
- Web: Author, A. A. (2024, January 15). Title of page. Site Name. https://url
APA_EXAMPLES;

        $lines = [
            'Act as an Editorial Planning Assistant. Create a comprehensive structured editorial framework for the given article. Return ONLY valid JSON. Do NOT wrap the output in a "framework" key — the top-level keys must be exactly as shown below.',
            '',
            '=== OUTPUT SCHEMA (FLAT — all keys at top level) ===',
            '{',
            '  "article_idea": {',
            '    "title": "Original article title",',
            '    "summary": "Original article summary",',
            '    "keywords": ["original", "keywords"]',
            '  },',
            '  "title": "Refined article title",',
            '  "overview": "2-3 sentence overview of what this article should cover",',
            '  "context": "Why this topic matters now — industry context, timing, and relevance",',
            '  "application": {',
            '    "intended_reader": "Who should read this article (role, industry, seniority)",',
            '    "use_case": "How the reader will apply this knowledge in practice"',
            '  },',
            '  "observations": [',
            '    {',
            '      "headline": "Short headline (max 8 words)",',
            '      "detail": "2-4 sentences of analysis and insight",',
            '      "evidence": [',
            '        {',
            '          "citation_index": 0,',
            '          "passage_snippet": "Key quote or data point from the cited source (max 200 chars)",',
            '          "confidence": 0.85',
            '        }',
            '      ]',
            '    }',
            '  ],',
            '  "key_themes": ["Theme 1", "Theme 2", "Theme 3", "Theme 4"],',
            '  "writer_guidance": {',
            '    "tone": "Recommended writing tone for this piece",',
            '    "structure": "Suggested article structure outline",',
            '    "key_messages": ["Key message 1", "Key message 2"],',
            '    "target_audience_notes": "Specific angle or framing considerations",',
            '    "citation_usage": "How to incorporate citations into the narrative"',
            '  },',
            '  "scoring": {',
            '    "total_score": 0-100,',
            '    "quality_level": "standard | good | excellent",',
            '    "is_publishable": true',
            '  },',
            '  "citations": [',
            '    {',
            '      "apa": "Full APA 7th edition formatted citation string",',
            '      "url": "https://...",',
            '      "title": "Full title of the source",',
            '      "lead_author": "Primary author or empty if unknown",',
            '      "organisation": "Publisher, institution, or domain name",',
            '      "publication_date": "YYYY-MM-DD or YYYY or empty",',
            '      "passage_snippet": "Key passage or quote from this source (max 300 chars)",',
            '      "relevance": "1-2 sentence note on relevance to the article"',
            '    }',
            '  ]',
            '}',
            '',
            '=== INSTRUCTIONS ===',
            'Tone: concise, authoritative, and practical. Use complete sentences throughout.',
            'Observations: 4–6 items. Each observation MUST include an "evidence" array linking to one or more citations by their index in the citations array. Include passage_snippets from the cited source.',
            'Citations: 6–8 items. Provide APA 7th edition formatting in "apa". Use the provided citation metadata (author, organisation, date) to construct the APA string — do not invent data. If lead_author is empty, omit it and start with the organisation or title.',
            'writer_guidance: A full guidance section to help the writer craft the article.',
            'scoring: Self-assess the framework quality. scoring.quality_level must be exactly one of: "excellent" (score 75-100), "good" (score 50-74), or "standard" (score 0-49). scoring.is_publishable should be true only if total_score >= 60.',
            '',
            '=== APA FORMATTING RULES ===',
            $apa_examples,
        ];

        if ( $pillar ) {
            $lines[] = 'Pillar: ' . $pillar;
        }
        if ( $audience_context ) {
            $lines[] = 'Audience Context: ' . $audience_context;
        }

        $lines[] = 'Article: ' . wp_json_encode( $article_payload );
        $lines[] = 'Citations (use these to construct the 6-8 citation array, enriching with APA format): ' . wp_json_encode( $citation_payload );

        $prompt = implode( "\n", $lines );

        // Smart truncation: keep schema instructions intact, truncate citation snippets only
        // Increase limit to 14000 to accommodate expanded schema
        if ( strlen( $prompt ) > 14000 ) {
            // Find the citations payload section and truncate only the snippet fields
            $citations_pos = strrpos( $prompt, 'Citations (use these' );
            if ( $citations_pos !== false ) {
                $before_citations = substr( $prompt, 0, $citations_pos );
                $citations_json   = substr( $prompt, $citations_pos );
                // If the whole thing is too long, truncate the citation payload only
                if ( strlen( $citations_json ) > 2000 ) {
                    // Truncate snippet values only
                    $citations_json = preg_replace_callback(
                        '/("snippet":\s*")([^"]{100,})(")/',
                        function ( $m ) {
                            return $m[1] . substr( $m[2], 0, 100 ) . '...' . $m[3];
                        },
                        $citations_json
                    );
                }
                $prompt = $before_citations . $citations_json;
            }
            // Final safety: hard truncate at 16000 to prevent model overflow
            if ( strlen( $prompt ) > 16000 ) {
                $prompt = substr( $prompt, 0, 16000 );
            }
        }

        return $prompt;
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
            error_log( '[PLANNER] enqueue_job failed: LLM not configured' );
            return new \WP_Error( 'llm_unconfigured', 'LLM API key is not configured. Cannot enqueue job.' );
        }

        if ( ! class_exists( '\KH\Editorial\Services\AI\AIStorage' ) ) {
            error_log( '[PLANNER] enqueue_job failed: AIStorage class missing' );
            return new \WP_Error( 'infrastructure_missing', 'AI Storage service not found.' );
        }

        $route = \KH\Editorial\Core\LLMService::resolve_agent_model( $agent_key );
        error_log( '[PLANNER] resolve_agent_model(' . $agent_key . '): ' . wp_json_encode( $route ) );

        if ( empty( $route['model'] ) ) {
            error_log( '[PLANNER] enqueue_job failed: no model resolved for agent ' . $agent_key );
            return new \WP_Error( 'no_model', 'No model resolved for agent: ' . $agent_key );
        }

        $storage = new \KH\Editorial\Services\AI\AIStorage();

        // Check user budget before enqueuing to prevent wasted API calls
        $author_id = (int) get_post_field( 'post_author', $post_id );
        error_log( '[PLANNER] check_budget for user ' . $author_id );
        $budget = $storage->check_budget( $author_id );
        if ( ! $budget['has_budget'] ) {
            error_log( '[PLANNER] enqueue_job failed: budget exhausted for user ' . $author_id );
            return new \WP_Error(
                'budget_exhausted',
                sprintf(
                    'User budget exhausted. Limit: %d tokens, Used: %d tokens.',
                    $budget['token_limit'],
                    $budget['token_used']
                )
            );
        }

        $job_data = [
            'session_id'      => $post_id,
            'prompt'          => $prompt,
            'model'           => $route['model'],
            'idempotency_key' => $idempotency_key,
            'created_by'      => get_current_user_id()
        ];
        error_log( '[PLANNER] insert_job data: ' . wp_json_encode( $job_data ) );

        $job_id = $storage->insert_job( $job_data );

        if ( is_wp_error( $job_id ) ) {
            error_log( '[PLANNER] insert_job returned error: ' . $job_id->get_error_message() );
            return $job_id;
        }

        error_log( '[PLANNER] insert_job succeeded: ' . $job_id );

        // Always refresh the prompt on the job record before dispatching.
        // This ensures re-runs use the freshly-built prompt (idempotency returns
        // the existing job ID without updating it).
        $storage->update_job( $job_id, 'queued', [ 'prompt' => $prompt ] );

        update_post_meta( $post_id, 'kh_planner_status', $next_status );
        update_post_meta( $post_id, 'kh_planner_current_job', $job_id );

        // For dive_deeper jobs (agent_key='research'), the caller
        // (article_action) handles processing directly after closing the
        // HTTP connection (connection-close pattern), which works reliably
        // on all environments including local dev where WP-Cron doesn't fire.
        // For all other jobs (phases 1-4, final synopsis), the synchronous
        // do_action path is fine since those are smaller prompts.
        if ( $agent_key !== 'research' ) {
            do_action( 'kh_editorial_job_created', $job_id, 'planner' );
        }
        
        return [ 'session_id' => $post_id, 'job_id' => $job_id, 'status' => $next_status ];
    }

    /**
     * Dispatch a job asynchronously via non-blocking HTTP request.
     *
     * Fires an immediate request to the dispatch-job endpoint so the job
     * processes without waiting for WP-Cron page load triggers.
     *
     * @param string $job_id The job ID to dispatch.
     * @param string $type   The job type (e.g., 'planner').
     */
    private function async_dispatch_job( $job_id, $type ) {
        $url = rest_url( 'editorial/v1/planner/dispatch-job' );
        
        // Use reasonable timeout (5s) and blocking=true so the request completes
        // before returning. The caller must ensure the response is handled.
        wp_remote_post( $url, [
            'timeout'   => 5,
            'blocking'  => true,
            'body'      => wp_json_encode( [ 'job_id' => $job_id, 'type' => $type ] ),
            'headers'   => [ 
                'Content-Type'    => 'application/json',
                'X-Internal-Call' => 'true',
            ],
            'sslverify' => false,
        ] );
        
        error_log( '[PLANNER] Async dispatch triggered for job: ' . $job_id );
    }


    private function normalize_terms( $terms ) {
        if ( is_string( $terms ) ) {
            $terms = array_filter( array_map( 'trim', explode( ',', $terms ) ) );
        }
        return is_array( $terms ) ? $terms : [];
    }

    /**
     * Run Author Generation for a specific article.
     *
     * Delegates to the kh-editorial-author plugin's DraftAgent which handles
     * prompt construction, LLM calling, policy enforcement, and citation validation.
     * Runs synchronously using the connection-close pattern from the endpoint.
     *
     * @param int    $post_id          Session post ID.
     * @param string $article_id       Article ID from session meta.
     * @param string $author_profile   Author profile key ('balanced', 'authoritative', 'conversational', 'analytical').
     * @param string $word_count_range Word count range key: 'short' (800-1500), 'standard' (1500-3000), 'long' (3000-5000).
     * @return array|\WP_Error
     */
    public function run_author_generation( $post_id, $article_id, $author_profile = '', $word_count_range = 'short' ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'planner_session' ) {
            return new \WP_Error( 'invalid_session', 'Invalid planner session.' );
        }

        if ( ! $author_profile ) {
            $author_profile = 'balanced';
        }

        // Load session meta and find the target article
        $session_meta = $this->get_planner_meta_array( $post_id );
        $articles     = $session_meta['articles'] ?? [];
        $article      = null;
        $article_idx  = null;
        foreach ( $articles as $idx => $a ) {
            if ( $a['id'] === $article_id ) {
                $article     = &$articles[ $idx ];
                $article_idx = $idx;
                break;
            }
        }
        unset( $a );

        if ( ! $article ) {
            return new \WP_Error( 'article_not_found', 'Article not found in session meta.' );
        }

        // Resolve author profile to a DraftAgent persona
        $persona_map = [
            'balanced'         => 'journalist',
            'authoritative'    => 'analyst',
            'analytical'       => 'analyst',
            'conversational'   => 'veteran',
            'executive'        => 'veteran',
        ];
        $persona = $persona_map[ $author_profile ] ?? 'journalist';

        // Collect citations from the article
        $citations = $article['citations'] ?? [];

        // Merge deep-dive citations
        $dive_jobs = $article['dive_deeper_jobs'] ?? [];
        if ( ! empty( $dive_jobs ) ) {
            $dives = get_post_meta( $post_id, 'kh_planner_dives', true ) ?: [];
            foreach ( $dive_jobs as $dj ) {
                $dive_id = $dj['dive_id'] ?? '';
                if ( $dive_id && isset( $dives[ $dive_id ]['citations'] ) ) {
                    $citations = array_merge( $citations, $dives[ $dive_id ]['citations'] );
                }
            }
        }

        // Normalize citations to the format DraftAgent expects
        $normalized_citations = [];
        foreach ( $citations as $c ) {
            $normalized_citations[] = [
                'lead_author'     => $c['lead_author'] ?? '',
                'title'           => $c['title'] ?? '',
                'year'            => $c['year'] ?? ( $c['publication_date'] ?? '' ),
                'publication'     => $c['publication'] ?? ( $c['organisation'] ?? '' ),
                'organisation'    => $c['organisation'] ?? ( $c['source'] ?? '' ),
                'url'             => $c['url'] ?? '',
                'passage_snippet' => $c['passage_snippet'] ?? ( $c['snippet'] ?? '' ),
            ];
        }

        // Build the author policy from session meta (or defaults)
        $author_policy = get_post_meta( $post_id, 'kh_planner_author_policy', true ) ?: [];

        // Apply word count range from the request param
        $word_count_ranges = [
            'short'    => [ 'min_words' => 800,  'max_words' => 1500 ],
            'standard' => [ 'min_words' => 1500, 'max_words' => 3000 ],
            'long'     => [ 'min_words' => 3000, 'max_words' => 5000 ],
        ];
        $wc = $word_count_ranges[ $word_count_range ] ?? $word_count_ranges['short'];
        $author_policy['min_words'] = $wc['min_words'];
        $author_policy['max_words'] = $wc['max_words'];

        $author_policy = \KH\EditorialAuthor\Core\AuthorPolicy::sanitize( $author_policy );

        // Store word_count_range on the article meta so the UI can display it
        $article['author']['word_count_range'] = $word_count_range;

        // Use the framework output as planner_data context
        $framework_output = $article['framework']['output'] ?? [];

        // Build context for DraftAgent::execute()
        $context = [
            'author_policy' => $author_policy,
            'persona'       => $persona,
            'citations'     => $normalized_citations,
            'planner_data'  => $framework_output,
            'dossier'       => '',
        ];

        $instructions = sprintf(
            'Write a draft article based on the framework "%s" using the %s writing profile.',
            $framework_output['title'] ?? ( $article['headline'] ?? $article['title'] ?? '' ),
            $author_profile
        );

        // Instantiate the author plugin's DraftAgent
        if ( ! class_exists( '\KH\EditorialAuthor\Agents\DraftAgent' ) ) {
            return new \WP_Error( 'author_agent_missing', 'kh-editorial-author plugin is not active.' );
        }

        $intelligence = new \KH\EditorialAuthor\Integration\IntelligenceBridge();
        $draft_agent  = new \KH\EditorialAuthor\Agents\DraftAgent( $intelligence );

        // Mark as running in session meta before the potentially long LLM call
        if ( ! isset( $article['author'] ) ) {
            $article['author'] = [];
        }
        $article['author']['status']  = 'running';
        $article['author']['profile'] = $author_profile;
        $session_meta['articles'] = $articles;
        update_post_meta( $post_id, 'kh_planner_meta', wp_json_encode( $session_meta ) );

        // Execute synchronously — catch ALL errors (WP_Error, Exceptions, fatal errors) to avoid
        // leaving article.author.status stuck at "running" forever
        try {
            $result = $draft_agent->execute( $context, $instructions, (int) get_post_field( 'post_author', $post_id ) );

            if ( is_wp_error( $result ) ) {
                throw new \RuntimeException( $result->get_error_message() );
            }
        } catch ( \Throwable $e ) {
            // Update session meta with failure
            $article['author']['status']   = 'failed';
            $article['author']['error']    = $e->getMessage();
            $article['author']['completed_at'] = current_time( 'mysql' );
            $session_meta['articles'] = $articles;
            update_post_meta( $post_id, 'kh_planner_meta', wp_json_encode( $session_meta ) );
            return new \WP_Error( 'author_generation_failed', $e->getMessage() );
        }

        // Store result into article.author.output
        $article['author']['status']  = 'completed';
        $article['author']['output']  = $result;
        $article['author']['completed_at'] = current_time( 'mysql' );
        $session_meta['articles'] = $articles;
        update_post_meta( $post_id, 'kh_planner_meta', wp_json_encode( $session_meta ) );

        return [
            'session_id' => $post_id,
            'article_id' => $article_id,
            'status'     => 'completed',
            'result'     => $result,
        ];
    }

    /**
     * Load kh_planner_meta for a session and return it as an array.
     *
     * WordPress's get_post_meta() with $single=true can return an already-
     * unserialized array if the value was stored as a PHP-serialized array
     * rather than a JSON string. This helper handles both shapes safely.
     *
     * @param int $session_id
     * @return array
     */
    private function get_planner_meta_array( $session_id ) {
        $raw = get_post_meta( $session_id, 'kh_planner_meta', true );
        if ( is_array( $raw ) ) {
            return $raw;
        }
        if ( is_string( $raw ) && $raw !== '' ) {
            $decoded = json_decode( $raw, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                return $decoded;
            }
        }
        return [];
    }
}
