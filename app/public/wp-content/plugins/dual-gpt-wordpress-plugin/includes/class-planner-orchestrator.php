<?php
/**
 * Planner orchestrator for Dual-GPT Editorial Planner workflow
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Planner_Orchestrator {

    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Run the planner workflow for a session
     */
    public function run($session_id) {
        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);

        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $meta = $this->decode_meta($session['meta_json'] ?? null);
        $topic = $meta['topic'] ?? $session['title'] ?? '';
        $includes = $this->normalize_terms($meta['includes'] ?? array());
        $excludes = $this->normalize_terms($meta['excludes'] ?? array());

        $phases = isset($meta['phases']) && is_array($meta['phases']) ? $meta['phases'] : array();

        $focus_level = isset($meta['focus_level']) ? intval($meta['focus_level']) : 50;
        $research_policy = is_array($meta['research_policy'] ?? null) ? $meta['research_policy'] : array();
        $subgroup = sanitize_text_field((string) ($meta['subgroup'] ?? ($meta['subgroup_profile']['name'] ?? '')));
        $sponsor_context = array(
            'is_sponsored' => !empty($meta['is_sponsored']),
            'sponsor_name' => sanitize_text_field((string) ($meta['sponsor_name'] ?? '')),
            'sponsor_weighting' => max(0, min(5, intval($meta['sponsor_weighting'] ?? 2))),
            'target_sponsors' => is_array($meta['category_profile']['target_sponsors'] ?? null)
                ? $meta['category_profile']['target_sponsors']
                : array(),
        );

        $exclusions = $this->resolve_session_exclusions($meta);

        $phase1_data = $this->build_phase1_data($topic, $includes, $excludes, $research_policy, $subgroup, $sponsor_context, $exclusions);
        if (is_wp_error($phase1_data)) {
            return $phase1_data;
        }

        $meta['phase1'] = $phase1_data;

        // Encode and validate JSON size to prevent prompt length issues
        $context_json = wp_json_encode($phase1_data);
        $json_length = strlen($context_json);
        
        // If JSON is too large, aggressively truncate
        if ($json_length > 5000) {
            error_log('[PLANNER] Phase 1 context JSON is large (' . $json_length . ' chars), truncating...');
            
            // Further reduce SERP results
            if (isset($phase1_data['serp_snapshot']) && is_array($phase1_data['serp_snapshot'])) {
                foreach ($phase1_data['serp_snapshot'] as $query => $results) {
                    if (is_array($results)) {
                        $phase1_data['serp_snapshot'][$query] = array_slice($results, 0, 3);
                        foreach ($phase1_data['serp_snapshot'][$query] as $idx => $result) {
                            if (isset($result['snippet'])) {
                                $phase1_data['serp_snapshot'][$query][$idx]['snippet'] = $this->truncate_text($result['snippet'], 100);
                            }
                            if (isset($result['description'])) {
                                $phase1_data['serp_snapshot'][$query][$idx]['description'] = $this->truncate_text($result['description'], 100);
                            }
                        }
                    }
                }
            }
            
            // Limit keywords further
            if (isset($phase1_data['candidate_keywords']) && is_array($phase1_data['candidate_keywords'])) {
                $phase1_data['candidate_keywords'] = array_slice($phase1_data['candidate_keywords'], 0, 40);
            }

            // Keep internal coverage compact if present
            if (isset($phase1_data['internal_content_coverage']) && is_array($phase1_data['internal_content_coverage'])) {
                if (isset($phase1_data['internal_content_coverage']['terms']) && is_array($phase1_data['internal_content_coverage']['terms'])) {
                    $phase1_data['internal_content_coverage']['terms'] = array_slice($phase1_data['internal_content_coverage']['terms'], 0, 12);
                }
                if (isset($phase1_data['internal_content_coverage']['top_covered_terms']) && is_array($phase1_data['internal_content_coverage']['top_covered_terms'])) {
                    $phase1_data['internal_content_coverage']['top_covered_terms'] = array_slice($phase1_data['internal_content_coverage']['top_covered_terms'], 0, 6);
                }
                if (isset($phase1_data['internal_content_coverage']['priority_gaps']) && is_array($phase1_data['internal_content_coverage']['priority_gaps'])) {
                    $phase1_data['internal_content_coverage']['priority_gaps'] = array_slice($phase1_data['internal_content_coverage']['priority_gaps'], 0, 6);
                }
            }
            
            $context_json = wp_json_encode($phase1_data);
            $new_length = strlen($context_json);
            error_log('[PLANNER] After truncation: ' . $new_length . ' chars (saved ' . ($json_length - $new_length) . ' chars)');
        }

        $phase1_job_id = $this->enqueue_phase_job(
            $session_id,
            'phase1',
            $topic,
            $includes,
            $excludes,
            $context_json,
            $focus_level
        );
        if (is_wp_error($phase1_job_id)) {
            return $phase1_job_id;
        }

        $phases['phase1'] = array(
            'title' => 'Discovery',
            'job_id' => $phase1_job_id,
            'status' => 'queued',
        );

        $meta['topic'] = $topic;
        $meta['includes'] = $includes;
        $meta['excludes'] = $excludes;
        $meta['focus_level'] = $focus_level;
        $meta['phases'] = $phases;
        if ($subgroup !== '') {
            $meta['subgroup'] = $subgroup;
        }

        $db->update_session_meta($session_id, $meta);

        return array(
            'session_id' => $session_id,
            'meta' => $meta,
        );
    }

    /**
     * Generate framework for an article summary and attach to planner session
     */
    public function generate_framework($session_id, $article) {
        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);

        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        if ($session['created_by'] != get_current_user_id() && !current_user_can('manage_options')) {
            return new WP_Error('access_denied', 'You do not have permission to access this session', array('status' => 403));
        }

        $article_id = isset($article['id']) ? sanitize_text_field($article['id']) : '';
        if ($article_id === '') {
            return new WP_Error('missing_article_id', 'Article ID is required.', array('status' => 400));
        }

        return $this->run_framework_for_article($session_id, $article_id);
    }

    private function decode_meta($meta_json) {
        if (empty($meta_json)) {
            return array();
        }

        $decoded = json_decode($meta_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return array();
        }

        return $decoded;
    }

    private function normalize_terms($terms) {
        if (is_string($terms)) {
            $terms = array_filter(array_map('trim', explode(',', $terms)));
        }

        if (!is_array($terms)) {
            return array();
        }

        return array_values(array_filter(array_map('sanitize_text_field', $terms)));
    }

    private function focus_profile($focus_level) {
        $focus_level = max(0, min(100, intval($focus_level)));
        if ($focus_level >= 70) {
            return array(
                'trend_range' => '4–6',
                'topic_range' => '3–5',
            );
        }
        if ($focus_level >= 40) {
            return array(
                'trend_range' => '5–7',
                'topic_range' => '5–7',
            );
        }
        return array(
            'trend_range' => '7–9',
            'topic_range' => '7–10',
        );
    }

    public function enqueue_phase_job($session_id, $phase_key, $topic, $includes, $excludes, $context_summary, $focus_level = 50) {
        if ($phase_key === 'phase1') {
            $prompt = $this->build_phase1_prompt($topic, $includes, $excludes, $context_summary, $focus_level);
            $task = 'discovery';
        } elseif ($phase_key === 'phase2') {
            $prompt = $this->build_phase2_prompt($topic, $includes, $excludes, $context_summary, array(), $focus_level);
            $task = 'author';
        } else {
            $prompt = $this->build_phase3_prompt($topic, $includes, $excludes, $context_summary, array(), $focus_level);
            $task = 'verify';
        }

        return $this->run_job($session_id, 'planner-' . $phase_key . '-' . $session_id, $prompt, $task);
    }

    public function run_job($session_id, $idempotency_key, $prompt, $task = '') {
        $model_config = class_exists('Dual_GPT_Model_Config') ? new Dual_GPT_Model_Config() : null;
        $model = $model_config ? $model_config->get_model_for_task($task) : 'gpt-4o-mini';
        $fallback_map = $model_config ? $model_config->get_fallback_map() : array();
        $fallback_model = $fallback_map[$task] ?? 'gpt-4o-mini';

        if ($this->should_use_fallback_model($task, $model, $fallback_model)) {
            error_log('[PLANNER][MODEL] Auto-selected fallback model for task ' . $task . ': ' . $model . ' -> ' . $fallback_model);
            $model = $fallback_model;
        }

        $request = new WP_REST_Request('POST', '/dual-gpt/v1/jobs');
        $request->set_param('session_id', $session_id);
        $request->set_param('prompt', $prompt);
        $request->set_param('model', $model);
        $request->set_param('idempotency_key', $idempotency_key);

        $response = $this->plugin->create_job($request);
        if (is_wp_error($response)) {
            error_log('[PLANNER][MODEL] Primary job creation failed for task ' . $task . ' model ' . $model . ': ' . $response->get_error_message());
            if ($fallback_model && $fallback_model !== $model) {
                error_log('[PLANNER][MODEL] Fallback model for task ' . $task . ': ' . $model . ' -> ' . $fallback_model);
                $request->set_param('model', $fallback_model);
                $response = $this->plugin->create_job($request);
                if (is_wp_error($response)) {
                    error_log('[PLANNER][MODEL] Fallback job creation failed for task ' . $task . ' model ' . $fallback_model . ': ' . $response->get_error_message());
                    return $response;
                }
            } else {
                return $response;
            }
        }

        $data = $response instanceof WP_REST_Response ? $response->get_data() : $response;
        $job_id = $data['job_id'] ?? null;

        if (!$job_id) {
            return new WP_Error('job_creation_failed', 'Job ID not returned', array('status' => 500));
        }

        return $job_id;
    }

    private function should_use_fallback_model($task, $model, $fallback_model) {
        if ($task !== 'framework') {
            return false;
        }

        if ($fallback_model === '' || $fallback_model === $model) {
            return false;
        }

        global $wpdb;
        $jobs_table = $wpdb->prefix . 'ai_jobs';
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        $recent_rate_limited = intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$jobs_table}
                 WHERE created_by = %d
                   AND model = %s
                   AND idempotency_key LIKE %s
                   AND status = 'failed'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                   AND (error_message LIKE %s OR error_message LIKE %s)",
                $user_id,
                $model,
                'planner-fw-%',
                '%Rate limit%',
                '%rate limit%'
            )
        ));

        return $recent_rate_limited >= 1;
    }

    public function build_phase1_prompt($topic, $includes, $excludes, $context_summary = '', $focus_level = 50) {
        $focus_profile = $this->focus_profile($focus_level);
        $lines = array(
            'Act as a Research & Insights Lead conducting Phase 1 (Discovery) of a content-aligned B2B research workflow.',
            'Topic: ' . $topic,
        );
        if (!empty($includes)) {
            $lines[] = 'Focus inclusively on: ' . implode(', ', $includes);
        }
        if (!empty($excludes)) {
            $lines[] = 'Exclude or deprioritize: ' . implode(', ', $excludes);
        }
        if (!empty($context_summary)) {
            $lines[] = 'Research Inputs (JSON):';
            $lines[] = $context_summary;
            $lines[] = 'Use the workflow_directives object in the research inputs to enforce preferred journals/publications, provider priorities, subgroup focus, and sponsor weighting behavior.';
            $lines[] = 'Use internal_content_coverage to assess where existing site coverage is strong, weak, or stale (frequency + recency), and prioritize opportunities with external demand but weak/stale internal coverage.';
            // Inject channel-specific exclusion instructions from workflow_directives
            $directives = array();
            if (is_string($context_summary)) {
                $decoded = json_decode($context_summary, true);
                $directives = is_array($decoded['workflow_directives'] ?? null) ? $decoded['workflow_directives'] : array();
            }
            $channel = $directives['content_channel'] ?? 'house';
            $ex_names = array_filter((array) ($directives['excluded_entity_names'] ?? array()));
            $ex_types = array_filter((array) ($directives['excluded_entity_types'] ?? array()));
            $keep = trim((string) ($directives['keep_entity_name'] ?? ''));
            if ($channel === 'quote_club') {
                if (!empty($ex_names)) {
                    $lines[] = 'CITATION RULE (Quote Club – Summary): Do NOT cite or mention the following vendors as sources: ' . implode(', ', $ex_names) . '. Treat all content as vendor-agnostic.';
                } elseif (!empty($ex_types)) {
                    $kn = $keep !== '' ? ' Exception: ' . $keep . ' (submitting vendor) may be cited.' : '';
                    $lines[] = 'CITATION RULE (Quote Club – Framework): Exclude all ' . implode(', ', $ex_types) . ' vendors from citations except the submitting vendor.' . $kn;
                }
            } elseif ($channel === 'circle') {
                if (!empty($ex_types)) {
                    $kn = $keep !== '' ? ' Exception: ' . $keep . ' (the client) remains includable.' : '';
                    $lines[] = 'CITATION RULE (Circle – Ghost-written): Exclude all ' . implode(', ', $ex_types) . ' solution providers from research sources and citations.' . $kn;
                }
            }
        }
        $lines[] = 'Focus level: ' . intval($focus_level) . ' (0 = general, 100 = focused).';
        $lines[] = 'Analyze the supplied research inputs (SERP snapshots, keyword suggestions, trend notes, and internal coverage telemetry) to extract ' . $focus_profile['trend_range'] . ' distinct trends shaping this topic.';
        $lines[] = 'Enforce 36-month recency window: extract publication dates from citation metadata and reject any sources older than 36 months. Do not accept article titles as publication dates; verify against schema metadata (datePublished, article:published_time, etc.).';
        $lines[] = 'For each trend, include: 2–4 insight_points; a clear why_it_matters; 2–3 editorial_angles; and 2–4 citations sourced only from the supplied inputs.';
        $lines[] = 'Apply only to: Field Service, Spare Parts, B2B E-Commerce, B2B Pricing, Servitization, or Aftermarket Strategy. For e-commerce and pricing, restrict to B2B manufacturing.';
        $lines[] = 'Return at least 12 candidate_keywords derived from the supplied inputs. Do not leave candidate_keywords empty.';
        $lines[] = 'Return ONLY valid JSON. Do not include commentary, apologies, or process notes.';
        $lines[] = 'Schema:';
        $lines[] = '```json';
        $lines[] = '{';
        $lines[] = '  "executive_summary":"",';
        $lines[] = '  "trends":[{"title":"","insight_points":[""],"why_it_matters":"","editorial_angles":[""],"citations":[{"title":"","url":"","source":"","date":"","snippet":""}]}],';
        $lines[] = '  "trend_summary":[{"trend":"","repeated_in_research":"yes | no | mixed"}],';
        $lines[] = '  "candidate_keywords":[""],';
        $lines[] = '  "next_step_question":"Proceed to Research Phase 2?"';
        $lines[] = '}';
        $lines[] = '```';
        $lines[] = 'Do not fabricate sources or stats. If a citation is not in the inputs, omit it.';

        return implode("\n", $lines);
    }

    public function build_phase2_context($meta, $max_keywords = 10, $max_sources = 2) {
        $meta = is_array($meta) ? $meta : array();
        $phase1_payload = $meta['phases']['phase1']['payload'] ?? array();
        $phase1_summary = $phase1_payload['executive_summary'] ?? $meta['phase1']['summary'] ?? '';
        $phase1_summary = $this->truncate_text($phase1_summary, 500);
        $ranked = $meta['phase2']['ranked_keywords'] ?? array();
        $serp_context = $meta['phase2']['serp_context'] ?? array();

        $keywords = array();
        foreach (array_slice((array) $ranked, 0, $max_keywords) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $keyword = $item['keyword'] ?? '';
            if ($keyword === '') {
                continue;
            }
            $sources = array();
            if (!empty($item['serp_sources']) && is_array($item['serp_sources'])) {
                foreach (array_slice($item['serp_sources'], 0, $max_sources) as $source) {
                    $sources[] = array(
                        'title' => $source['title'] ?? '',
                        'url' => $source['url'] ?? '',
                        'domain' => $source['domain'] ?? '',
                    );
                }
            } elseif (!empty($serp_context[$keyword]['results'])) {
                foreach (array_slice($serp_context[$keyword]['results'], 0, $max_sources) as $result) {
                    $sources[] = array(
                        'title' => $result['title'] ?? '',
                        'url' => $result['url'] ?? '',
                        'domain' => isset($result['url']) ? parse_url($result['url'], PHP_URL_HOST) : '',
                    );
                }
            }

            $keywords[] = array(
                'keyword' => $keyword,
                'priority_score' => $item['priority_score'] ?? null,
                'search_volume' => $item['search_volume'] ?? null,
                'cpc' => $item['cpc'] ?? null,
                'competition' => $item['competition'] ?? null,
                'difficulty' => $item['difficulty'] ?? null,
                'serp_sources' => $sources,
            );
        }

        $dossier_context = $this->build_required_dossier_context($meta, 'phase3');

        return array(
            'phase1_summary' => $phase1_summary,
            'ranked_keywords' => $keywords,
            'dossier' => $dossier_context,
        );
    }

    public function build_phase3_context($meta, $max_topics = 8, $max_keywords = 12) {
        $meta = is_array($meta) ? $meta : array();
        $phase3_payload = $meta['phases']['phase3']['payload'] ?? array();

        $topics = array();
        foreach (array_slice((array) ($phase3_payload['prioritized_topics'] ?? array()), 0, $max_topics) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $topic_name = sanitize_text_field((string) ($row['topic'] ?? ''));
            if ($topic_name === '') {
                continue;
            }
            $topics[] = array(
                'topic' => $topic_name,
                'keywords' => array_values(array_slice((array) ($row['keywords'] ?? array()), 0, 6)),
                'why_now' => $this->truncate_text((string) ($row['why_now'] ?? ''), 180),
            );
        }

        $phase1_keywords = array_slice((array) ($meta['phase1']['candidate_keywords'] ?? array()), 0, $max_keywords);

        return array(
            'phase3_summary' => $this->truncate_text((string) ($meta['phases']['phase3']['summary'] ?? ''), 500),
            'prioritized_topics' => $topics,
            'phase1_candidate_keywords' => $phase1_keywords,
            'dossier' => $this->build_required_dossier_context($meta, 'phase4'),
        );
    }

    public function build_phase2_prompt($topic, $includes, $excludes, $phase1_summary, $phase2_context = array(), $focus_level = 50) {
        $focus_profile = $this->focus_profile($focus_level);
        $lines = array(
            'Act as a Research & Insights Lead conducting Phase 3 (Deep Dive) of a content-aligned B2B research workflow.',
            '',
            'Topic: ' . $topic,
            'Include: ' . (!empty($includes) ? implode(', ', $includes) : 'None'),
            'Exclude: ' . (!empty($excludes) ? implode(', ', $excludes) : 'None'),
            '',
            'Context:',
        );
        if (!empty($phase1_summary)) {
            $lines[] = $this->truncate_text($phase1_summary, 500);
        }
        $lines[] = '';
        if (!empty($phase2_context)) {
            $lines[] = 'Phase 2 Qualification Signals (JSON):';
            $lines[] = wp_json_encode($phase2_context);
            if (!empty($phase2_context['dossier'])) {
                $lines[] = 'Use the dossier.phase_snapshot object as the compact cross-phase memory; treat dossier.artifact_ref as the canonical persisted artifact location for this planner session.';
                $missing = (array) ($phase2_context['dossier']['completeness']['missing_fields'] ?? array());
                if (!empty($missing)) {
                    $lines[] = 'Dossier completeness warning: the following expected fields are missing: ' . implode(', ', $missing) . '. Treat those fields as unknown and do not infer absent data.';
                }
            }
        }
        $lines[] = '';
        $lines[] = 'Objective:';
        $lines[] = 'Focus level: ' . intval($focus_level) . ' (0 = general, 100 = focused). Target ' . $focus_profile['topic_range'] . ' prioritized topics.';
        $lines[] = 'Drill into the most strategically important or underexplored subtopics identified in Phase 1 and keyword-qualified in Phase 2.';
        $lines[] = 'Deliver editorially actionable insights designed specifically for article development—not long-form reports or white papers.';
        $lines[] = 'Focus on producing insights that can support a single article per topic, with clear narrative angles, recent data points, and sourceable quotes.';
        $lines[] = 'Keep each key_finding under 35 words. Limit to 3–5 citations per topic.';
        $lines[] = '';
        $lines[] = 'Return ONLY valid JSON. Do not include commentary.';
        $lines[] = 'Schema (JSON):';
        $lines[] = '```json';
        $lines[] = '{';
        $lines[] = '  "article_summary": "",';
        $lines[] = '  "prioritized_topics": [';
        $lines[] = '    {';
        $lines[] = '      "topic": "",';
        $lines[] = '      "why_now": "",';
        $lines[] = '      "key_findings": [""],';
        $lines[] = '      "citations": [';
        $lines[] = '        {';
        $lines[] = '          "title": "",';
        $lines[] = '          "url": "",';
        $lines[] = '          "source": "",';
        $lines[] = '          "date": "",';
        $lines[] = '          "snippet": ""';
        $lines[] = '        }';
        $lines[] = '      ],';
        $lines[] = '      "keywords": [""],';
        $lines[] = '      "content_opportunity": ""';
        $lines[] = '    }';
        $lines[] = '  ],';
        $lines[] = '  "next_step_question": "Proceed to Research Phase 4?"';
        $lines[] = '}';
        $lines[] = '```';

        return implode("\n", $lines);
    }

    public function build_phase3_prompt($topic, $includes, $excludes, $phase2_summary, $phase3_context = array(), $focus_level = 50) {
        $focus_profile = $this->focus_profile($focus_level);
        $lines = array(
            'Act as a Research & Insights Lead conducting Phase 4 (Validation) of a content-aligned B2B research workflow.',
            'Topic: ' . $topic,
        );
        if (!empty($includes)) {
            $lines[] = 'Include: ' . implode(', ', $includes);
        }
        if (!empty($excludes)) {
            $lines[] = 'Exclude: ' . implode(', ', $excludes);
        }
        if (!empty($phase2_summary)) {
            $lines[] = 'Context from Phase 3 Deep Dive:';
            $lines[] = $phase2_summary;
        }
        if (!empty($phase3_context)) {
            $lines[] = 'Phase 3 Validation Inputs (JSON):';
            $lines[] = wp_json_encode($phase3_context);
            if (!empty($phase3_context['dossier'])) {
                $lines[] = 'Use dossier.phase_snapshot as canonical memory of prior phases and preserve alignment with phase1/phase2 strategic signals.';
                $missing = (array) ($phase3_context['dossier']['completeness']['missing_fields'] ?? array());
                if (!empty($missing)) {
                    $lines[] = 'Dossier completeness warning: ' . implode(', ', $missing) . '. Do not fabricate missing evidence.';
                }
            }
        }
        $lines[] = 'Focus level: ' . intval($focus_level) . ' (0 = general, 100 = focused). Validate ' . $focus_profile['topic_range'] . ' topics and discard weak signals.';
        $lines[] = 'Your job is to validate, refine, or eliminate topics based on accuracy, authority, and editorial confidence.';
        $lines[] = 'Stress-test Phase 3 topics against authoritative, recent sources. Attach only citations that are safe to quote.';
        $lines[] = 'Where applicable, map back to keyword signals identified in Phase 2 to preserve strategic alignment.';
        $lines[] = 'Return ONLY valid JSON. Do not include commentary or apologies.';
        $lines[] = 'Schema: {';
        $lines[] = '"validation_summary":"",';
        $lines[] = '"validated_topics":[{"topic":"","validated_insights":[""],"trend_maturity":"emerging|growing|mainstream","relevance_score":0.0,"confidence_score":0.0,"keywords":[""],"citations":[{"title":"","url":"","source":"","date":"","snippet":""}]}],';
        $lines[] = '"discarded_topics":[{"topic":"","reason":"Outdated trend / insufficient supporting evidence / low editorial value"}],';
        $lines[] = '"next_step_question":"Ready to generate article synopses?"';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    public function build_synopsis_prompt($topic, $phase1_payload, $phase2_context, $phase3_payload, $phase4_payload, $plan, $existing_titles = array()) {
        $caps_levels = array(
            array(
                'phase1_trends' => 6,
                'phase1_citations' => 2,
                'phase1_insights' => 3,
                'phase1_angles' => 2,
                'phase1_summary' => 280,
                'phase1_why' => 220,
                'phase1_keywords' => 12,
                'phase1_citation_title' => 120,
                'phase2_keywords' => 10,
                'phase2_sources' => 2,
                'phase2_title' => 120,
                'phase2_summary' => 200,
                'phase3_topics' => 6,
                'phase3_citations' => 4,
                'phase3_findings' => 4,
                'phase3_summary' => 220,
                'phase3_why' => 200,
                'phase3_keywords' => 6,
                'phase3_content' => 180,
                'phase3_citation_title' => 120,
                'phase4_topics' => 6,
                'phase4_citations' => 5,
                'phase4_insights' => 4,
                'phase4_summary' => 220,
                'phase4_keywords' => 6,
                'phase4_citation_title' => 120,
                'phase4_discarded' => 5,
            ),
            array(
                'phase1_trends' => 5,
                'phase1_citations' => 1,
                'phase1_insights' => 2,
                'phase1_angles' => 1,
                'phase1_summary' => 240,
                'phase1_why' => 180,
                'phase1_keywords' => 10,
                'phase1_citation_title' => 110,
                'phase2_keywords' => 8,
                'phase2_sources' => 1,
                'phase2_title' => 110,
                'phase2_summary' => 180,
                'phase3_topics' => 5,
                'phase3_citations' => 1,
                'phase3_findings' => 3,
                'phase3_summary' => 200,
                'phase3_why' => 170,
                'phase3_keywords' => 5,
                'phase3_content' => 160,
                'phase3_citation_title' => 110,
                'phase4_topics' => 5,
                'phase4_citations' => 1,
                'phase4_insights' => 3,
                'phase4_summary' => 200,
                'phase4_keywords' => 5,
                'phase4_citation_title' => 110,
                'phase4_discarded' => 4,
            ),
            array(
                'phase1_trends' => 4,
                'phase1_citations' => 1,
                'phase1_insights' => 2,
                'phase1_angles' => 1,
                'phase1_summary' => 200,
                'phase1_why' => 160,
                'phase1_keywords' => 8,
                'phase1_citation_title' => 90,
                'phase2_keywords' => 6,
                'phase2_sources' => 1,
                'phase2_title' => 90,
                'phase2_summary' => 160,
                'phase3_topics' => 4,
                'phase3_citations' => 1,
                'phase3_findings' => 3,
                'phase3_summary' => 160,
                'phase3_why' => 140,
                'phase3_keywords' => 4,
                'phase3_content' => 140,
                'phase3_citation_title' => 90,
                'phase4_topics' => 4,
                'phase4_citations' => 1,
                'phase4_insights' => 3,
                'phase4_summary' => 160,
                'phase4_keywords' => 4,
                'phase4_citation_title' => 90,
                'phase4_discarded' => 3,
            ),
        );

        $plan = $this->compact_synopsis_plan($plan);
        $existing_titles = array_values(array_unique(array_filter($existing_titles)));
        if (count($existing_titles) > 20) {
            $existing_titles = array_slice($existing_titles, 0, 20);
        }
        // Keep synopsis prompts below create_job validation limit (10,000 chars) with safety margin.
        $limit = 9500;
        $last_prompt = '';
        foreach ($caps_levels as $index => $caps) {
            $payloads = $this->build_synopsis_payloads($phase1_payload, $phase2_context, $phase3_payload, $phase4_payload, $caps);
            $prompt = $this->assemble_synopsis_prompt(
                $topic,
                $payloads['phase1'],
                $payloads['phase2'],
                $payloads['phase3'],
                $payloads['phase4'],
                $plan,
                $existing_titles
            );
            $last_prompt = $prompt;
            $length = strlen($prompt);
            if ($length <= $limit) {
                if ($index > 0) {
                    error_log('[PLANNER][SYNOPSES] Prompt trimmed to level ' . ($index + 1) . ' (len=' . $length . ').');
                }
                if ($index === 0) {
                    error_log('[PLANNER][SYNOPSES] Prompt length ok at base level (len=' . $length . ').');
                }
                return $prompt;
            }
        }

        $drop_phase1 = $this->assemble_synopsis_prompt(
            $topic,
            array(),
            $this->compact_phase2_context($phase2_context, 4, 1, 80, 140),
            $this->compact_phase3_payload($phase3_payload, 3, 1, 2, 140, 120, 3, 120, 80),
            $this->compact_phase4_payload($phase4_payload, 3, 1, 2, 140, 3, 80, 2),
            $plan,
            $existing_titles
        );
        if (strlen($drop_phase1) <= $limit) {
            error_log('[PLANNER][SYNOPSES] Prompt trimmed by dropping Phase 1 (len=' . strlen($drop_phase1) . ').');
            return $drop_phase1;
        }

        $drop_phase2 = $this->assemble_synopsis_prompt(
            $topic,
            array(),
            array(),
            $this->compact_phase3_payload($phase3_payload, 3, 1, 2, 140, 120, 3, 120, 80),
            $this->compact_phase4_payload($phase4_payload, 3, 1, 2, 140, 3, 80, 2),
            $plan,
            $existing_titles
        );
        if (strlen($drop_phase2) <= $limit) {
            error_log('[PLANNER][SYNOPSES] Prompt trimmed by dropping Phase 1 and 2 (len=' . strlen($drop_phase2) . ').');
            return $drop_phase2;
        }

        $existing_titles_min = array_slice($existing_titles, 0, 10);
        $drop_phase3 = $this->assemble_synopsis_prompt(
            $topic,
            array(),
            array(),
            array(),
            $this->compact_phase4_payload($phase4_payload, 2, 1, 2, 120, 3, 70, 2),
            $plan,
            $existing_titles_min
        );
        if (strlen($drop_phase3) <= $limit) {
            error_log('[PLANNER][SYNOPSES] Prompt trimmed to Phase 4 only (len=' . strlen($drop_phase3) . ').');
            return $drop_phase3;
        }

        // Final emergency fallback: minimal prompt with compacted Phase 4 + plan only.
        $ultra_phase4 = $this->compact_phase4_payload($phase4_payload, 2, 1, 1, 90, 3, 70, 0);
        $ultra_prompt = implode("\n", array(
            'Generate article synopses as strict JSON only: {"synopses":[{"id":"","topic":"","headline":"","summary":"","key_points":[""],"keywords":[""],"citations":[{"title":"","url":"","source":"","date":""}],"recommended_word_count":"","topic_coverage_level":"Important and Not Covered | Saturated | Ever-Green","audience_segment":"","priority_score":0.0,"opening_hook":""}]}',
            'Minimum 4 citations per synopsis. Use only citations supplied below. Do not invent URLs.',
            'Phase 4 Validation Output (ultra-compact):',
            wp_json_encode($ultra_phase4),
            'Synopsis Plan (topic => count):',
            wp_json_encode($plan),
            'Existing headlines to avoid:',
            wp_json_encode(array_slice($existing_titles, 0, 5)),
        ));
        if (strlen($ultra_prompt) <= $limit) {
            error_log('[PLANNER][SYNOPSES] Prompt trimmed to ultra-compact fallback (len=' . strlen($ultra_prompt) . ').');
            return $ultra_prompt;
        }

        error_log('[PLANNER][SYNOPSES] Prompt still above limit after all trimming (len=' . strlen($ultra_prompt) . '). Returning truncated fallback.');
        return substr($ultra_prompt, 0, $limit);
    }

    private function assemble_synopsis_prompt($topic, $phase1_payload, $phase2_context, $phase3_payload, $phase4_payload, $plan, $existing_titles) {
        $lines = array(
            'Act as an Editorial Planning Assistant generating article synopses based on validated B2B research. Each synopsis must represent a single, clearly scoped article idea.',
            'Use the provided Phase 4 validated topics and citations, Phase 3 topic framing, keyword metrics from Phase 2, and trend context from Phase 1 to ensure alignment with audience demand and editorial value.',
            'Prioritize: fresh or under-covered angles, strategic keyword targeting, and data-backed article narratives.',
            'CITATION REQUIREMENT (hard rule): Every synopsis MUST include a minimum of 4 citations. Aim for 5-6 citations per synopsis where the phase payloads support it. Draw citations from Phase 4 first, then Phase 3, then Phase 1. Each citation must directly support a specific claim in the summary or key points — do not add citations as mere background references.',
            'Return ONLY valid JSON. Do not include commentary or apologies.',
            'If you cannot comply, return {"synopses": []} and nothing else.',
            'If you need additional detail, refer to the phase payloads provided below. Do not invent sources or fabricate URLs.',
            'Schema: {',
            '"synopses":[{"id":"","topic":"","headline":"","summary":"","key_points":[""],"keywords":[""],"citations":[{"title":"","url":"","source":"","date":"","snippet":"","quote":""}],"recommended_word_count":"","topic_coverage_level":"Important and Not Covered | Saturated | Ever-Green","audience_segment":"","priority_score":0.0,"opening_hook":""}]',
            '}',
            'Avoid overlapping with existing headlines:',
            wp_json_encode($existing_titles),
            'Phase 1 Discovery Payload (compressed):',
            wp_json_encode($phase1_payload),
            'Phase 2 Qualification Context (compressed):',
            wp_json_encode($phase2_context),
            'Phase 3 Deep Dive Payload (compressed):',
            wp_json_encode($phase3_payload),
            'Phase 4 Validation Output (compressed):',
            wp_json_encode($phase4_payload),
            'Synopsis Plan (topic => count):',
            wp_json_encode($plan),
            'Format requirements:',
            '1) Headline: SEO-friendly and compelling.',
            '2) Summary: concise and strategic, must reference the key evidence angle.',
            '3) Key Points: up to 5 bullets, each grounded in a cited source where possible.',
            '4) Keywords: 4-6 relevant terms.',
            '5) Citations: MINIMUM 4, target 5-6. Draw from Phase 4 validated topics first, supplement from Phase 3 and Phase 1. Each citation must have a non-empty snippet and a direct quote where available.',
            '6) Recommended Word Count: choose one of 850-1250, 1500-2500, 3000-5000.',
            '7) Topic Coverage Level: Important and Not Covered | Saturated | Ever-Green.',
            '8) Audience Segment: primary audience label when clear.',
            '9) Priority Score: 0.0-1.0 based on demand and content gap.',
            '10) Opening Hook: 1-2 sentence CTA or opening hook.',
            'FINAL CHECK: Before returning the JSON, verify that every synopsis in the array has at least 4 citations. If any synopsis has fewer than 4, add more from the phase payloads before returning.',
        );

        return implode("\n", $lines);
    }

    private function build_synopsis_payloads($phase1_payload, $phase2_context, $phase3_payload, $phase4_payload, $caps) {
        return array(
            'phase1' => $this->compact_phase1_payload(
                $phase1_payload,
                $caps['phase1_trends'],
                $caps['phase1_citations'],
                $caps['phase1_insights'],
                $caps['phase1_angles'],
                $caps['phase1_summary'],
                $caps['phase1_why'],
                $caps['phase1_keywords'],
                $caps['phase1_citation_title']
            ),
            'phase2' => $this->compact_phase2_context(
                $phase2_context,
                $caps['phase2_keywords'],
                $caps['phase2_sources'],
                $caps['phase2_title'],
                $caps['phase2_summary']
            ),
            'phase3' => $this->compact_phase3_payload(
                $phase3_payload,
                $caps['phase3_topics'],
                $caps['phase3_citations'],
                $caps['phase3_findings'],
                $caps['phase3_summary'],
                $caps['phase3_why'],
                $caps['phase3_keywords'],
                $caps['phase3_content'],
                $caps['phase3_citation_title']
            ),
            'phase4' => $this->compact_phase4_payload(
                $phase4_payload,
                $caps['phase4_topics'],
                $caps['phase4_citations'],
                $caps['phase4_insights'],
                $caps['phase4_summary'],
                $caps['phase4_keywords'],
                $caps['phase4_citation_title'],
                $caps['phase4_discarded']
            ),
        );
    }

    private function compact_phase1_payload($payload, $max_trends = 4, $max_citations = 1, $max_insights = 2, $max_angles = 1, $summary_limit = 200, $why_limit = 160, $keywords_limit = 8, $citation_title_limit = 90) {
        $payload = is_array($payload) ? $payload : array();
        $trends = isset($payload['trends']) && is_array($payload['trends']) ? array_slice($payload['trends'], 0, $max_trends) : array();
        $compact_trends = array();
        foreach ($trends as $trend) {
            if (!is_array($trend)) {
                continue;
            }
            $citations = isset($trend['citations']) && is_array($trend['citations']) ? array_slice($trend['citations'], 0, $max_citations) : array();
            $compact_trends[] = array(
                'title' => $trend['title'] ?? '',
                'insight_points' => array_slice($trend['insight_points'] ?? array(), 0, $max_insights),
                'why_it_matters' => $this->truncate_text($trend['why_it_matters'] ?? '', $why_limit),
                'editorial_angles' => array_slice($trend['editorial_angles'] ?? array(), 0, $max_angles),
                'citations' => $this->compact_citations($citations, $citation_title_limit),
            );
        }
        return array(
            'executive_summary' => $this->truncate_text($payload['executive_summary'] ?? '', $summary_limit),
            'trends' => $compact_trends,
            'trend_summary' => array_slice($payload['trend_summary'] ?? array(), 0, $max_trends),
            'candidate_keywords' => array_slice($payload['candidate_keywords'] ?? array(), 0, $keywords_limit),
        );
    }

    private function compact_phase2_context($context, $max_keywords = 6, $max_sources = 1, $title_limit = 90, $summary_limit = 160) {
        $context = is_array($context) ? $context : array();
        $ranked = isset($context['ranked_keywords']) && is_array($context['ranked_keywords'])
            ? array_slice($context['ranked_keywords'], 0, $max_keywords)
            : array();
        $keywords = array();
        foreach ($ranked as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sources = array();
            foreach (array_slice($item['serp_sources'] ?? array(), 0, $max_sources) as $source) {
                $sources[] = array(
                    'title' => $this->truncate_text($source['title'] ?? '', $title_limit),
                    'url' => $source['url'] ?? '',
                    'domain' => $source['domain'] ?? '',
                );
            }
            $keywords[] = array(
                'keyword' => $item['keyword'] ?? '',
                'priority_score' => $item['priority_score'] ?? null,
                'search_volume' => $item['search_volume'] ?? null,
                'cpc' => $item['cpc'] ?? null,
                'competition' => $item['competition'] ?? null,
                'difficulty' => $item['difficulty'] ?? null,
                'serp_sources' => $sources,
            );
        }
        return array(
            'phase1_summary' => $this->truncate_text($context['phase1_summary'] ?? '', $summary_limit),
            'ranked_keywords' => $keywords,
        );
    }

    private function compact_phase3_payload($payload, $max_topics = 4, $max_citations = 1, $max_findings = 3, $summary_limit = 160, $why_limit = 140, $keywords_limit = 4, $content_limit = 140, $citation_title_limit = 90) {
        $payload = is_array($payload) ? $payload : array();
        $topics = isset($payload['prioritized_topics']) && is_array($payload['prioritized_topics'])
            ? array_slice($payload['prioritized_topics'], 0, $max_topics)
            : array();
        $compact_topics = array();
        foreach ($topics as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $citations = isset($topic['citations']) && is_array($topic['citations']) ? array_slice($topic['citations'], 0, $max_citations) : array();
            $compact_topics[] = array(
                'topic' => $topic['topic'] ?? '',
                'why_now' => $this->truncate_text($topic['why_now'] ?? '', $why_limit),
                'key_findings' => array_slice($topic['key_findings'] ?? array(), 0, $max_findings),
                'citations' => $this->compact_citations($citations, $citation_title_limit),
                'keywords' => array_slice($topic['keywords'] ?? array(), 0, $keywords_limit),
                'content_opportunity' => $this->truncate_text($topic['content_opportunity'] ?? '', $content_limit),
            );
        }
        return array(
            'article_summary' => $this->truncate_text($payload['article_summary'] ?? '', $summary_limit),
            'prioritized_topics' => $compact_topics,
        );
    }

    private function compact_phase4_payload($payload, $max_topics = 4, $max_citations = 1, $max_insights = 3, $summary_limit = 160, $keywords_limit = 4, $citation_title_limit = 90, $discarded_limit = 3) {
        $payload = is_array($payload) ? $payload : array();
        $topics = isset($payload['validated_topics']) && is_array($payload['validated_topics'])
            ? array_slice($payload['validated_topics'], 0, $max_topics)
            : array();
        $compact_topics = array();
        foreach ($topics as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $citations = isset($topic['citations']) && is_array($topic['citations']) ? array_slice($topic['citations'], 0, $max_citations) : array();
            $compact_topics[] = array(
                'topic' => $topic['topic'] ?? '',
                'validated_insights' => array_slice($topic['validated_insights'] ?? array(), 0, $max_insights),
                'trend_maturity' => $topic['trend_maturity'] ?? '',
                'relevance_score' => $topic['relevance_score'] ?? null,
                'confidence_score' => $topic['confidence_score'] ?? null,
                'keywords' => array_slice($topic['keywords'] ?? array(), 0, $keywords_limit),
                'citations' => $this->compact_citations($citations, $citation_title_limit),
            );
        }
        return array(
            'validation_summary' => $this->truncate_text($payload['validation_summary'] ?? '', $summary_limit),
            'validated_topics' => $compact_topics,
            'discarded_topics' => array_slice($payload['discarded_topics'] ?? array(), 0, $discarded_limit),
        );
    }

    private function compact_synopsis_plan($plan) {
        if (!is_array($plan)) {
            return array();
        }
        return $plan;
    }

    private function normalize_candidate_keywords($keywords) {
        $normalized = array();
        foreach ($keywords as $keyword) {
            $keyword = sanitize_text_field($keyword);
            if ($keyword === '') {
                continue;
            }
            $keyword = preg_replace('/\\b(19|20)\\d{2}\\b/', '', $keyword);
            $keyword = preg_replace('/\\s+/', ' ', trim($keyword));
            if ($keyword === '') {
                continue;
            }
            $normalized[] = $keyword;
        }
        $unique = array_values(array_unique($normalized));
        return $unique;
    }

    private function compact_citations($citations, $title_limit = 90) {
        $compact = array();
        foreach ($citations as $citation) {
            if (!is_array($citation)) {
                continue;
            }
            $compact[] = array(
                'title' => $this->truncate_text($citation['title'] ?? '', $title_limit),
                'url' => $citation['url'] ?? '',
                'source' => $citation['source'] ?? '',
                'date' => $citation['date'] ?? '',
            );
        }
        return $compact;
    }

    private function truncate_text($text, $limit) {
        $text = trim((string) $text);
        if ($text === '' || $limit <= 0) {
            return $text;
        }
        if (strlen($text) <= $limit) {
            return $text;
        }
        return substr($text, 0, $limit - 1) . '…';
    }

    public function build_framework_prompt($title, $summary, $tags) {
        $lines = array(
            'Generate a framework brief based on this article summary.',
            'Title: ' . $title,
            'Summary: ' . $summary,
        );
        if (!empty($tags)) {
            $lines[] = 'Tags: ' . implode(', ', $tags);
        }
        $lines[] = 'Return the framework output as JSON or structured markdown as required by the Framework Generator preset.';

        return implode("\n", $lines);
    }

    public function run_phase1_5($session_id, $candidate_keywords, $max_keywords = 15) {
        $candidate_keywords = $this->normalize_candidate_keywords((array) $candidate_keywords);
        if (empty($candidate_keywords)) {
            return new WP_Error('keyword_list_empty', 'No candidate keywords available for Phase 1.5.');
        }

        $max_keywords = max(1, intval($max_keywords));
        $candidate_keywords = array_slice($candidate_keywords, 0, $max_keywords);

        $keyword_provider = new Dual_GPT_Keyword_Providers();
        $metrics = $keyword_provider->keyword_metrics($candidate_keywords);
        if (is_wp_error($metrics)) {
            if ($this->should_soft_fail_keyword_metrics($metrics)) {
                error_log('[PLANNER][PHASE2] Soft-failing keyword metrics provider: ' . $metrics->get_error_message());
                $metrics = array_map(function ($keyword) {
                    return array(
                        'keyword' => $keyword,
                        'search_volume' => 0,
                        'competition' => 'UNKNOWN',
                        'cpc' => 0,
                        'trend' => 'n/a',
                        'difficulty' => null,
                    );
                }, $candidate_keywords);
            } else {
                return $metrics;
            }
        }

        $difficulty_items = $keyword_provider->keyword_difficulty($candidate_keywords);
        if (is_wp_error($difficulty_items)) {
            $error_data = $difficulty_items->get_error_data();
            $status = is_array($error_data) ? ($error_data['status'] ?? null) : null;
            if ($status === 404) {
                error_log('[PLANNER][PHASE2] Keyword difficulty endpoint not available; continuing without difficulty.');
                $difficulty_items = array();
            } else {
                return $difficulty_items;
            }
        }

        $difficulty_map = array();
        foreach ($difficulty_items as $item) {
            if (!empty($item['keyword'])) {
                $difficulty_map[$item['keyword']] = $item['difficulty'] ?? null;
            }
        }

        foreach ($metrics as $index => $item) {
            $keyword = $item['keyword'] ?? '';
            if ($keyword !== '' && array_key_exists($keyword, $difficulty_map)) {
                $metrics[$index]['difficulty'] = $difficulty_map[$keyword];
            }
        }

        $research_tools = new Dual_GPT_Research_Tools();
        $serp_context = array();
        $serp_keywords = array_slice($candidate_keywords, 0, 3);
        foreach ($serp_keywords as $keyword) {
            $result = $research_tools->web_search($keyword, 5);
            $serp_context[$keyword] = $result;

            // If provider networking is degraded, avoid repeating expensive timeouts across all keywords.
            if (is_array($result) && !empty($result['provider_errors'])) {
                error_log('[PLANNER][PHASE2] SERP context degraded; stopping additional web_search calls after keyword: ' . $keyword);
                break;
            }
        }

        $ranked_keywords = $this->rank_phase2_keywords($metrics, $serp_context);
        $summary = $this->summarize_phase2($ranked_keywords);

        return array(
            'keyword_metrics' => $metrics,
            'serp_context' => $serp_context,
            'ranked_keywords' => $ranked_keywords,
            'summary' => $summary,
        );
    }

    private function should_soft_fail_keyword_metrics($error) {
        if (!is_wp_error($error)) {
            return false;
        }

        $code = (string) $error->get_error_code();
        $message = strtolower((string) $error->get_error_message());

        if (strpos($code, 'dataforseo_') === 0) {
            return true;
        }

        $needle_list = array(
            'curl error 35',
            'ssl_error_syscall',
            'ssl connect',
            'operation timed out',
            'could not resolve host',
            'failed to connect',
        );
        foreach ($needle_list as $needle) {
            if (strpos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function rank_phase2_keywords($metrics, $serp_context) {
        $metrics = is_array($metrics) ? $metrics : array();
        $max_volume = 0;
        $max_cpc = 0;
        foreach ($metrics as $item) {
            $volume = isset($item['search_volume']) ? floatval($item['search_volume']) : 0;
            $cpc = isset($item['cpc']) ? floatval($item['cpc']) : 0;
            $max_volume = max($max_volume, $volume);
            $max_cpc = max($max_cpc, $cpc);
        }

        $ranked = array();
        foreach ($metrics as $item) {
            $keyword = $item['keyword'] ?? '';
            if ($keyword === '') {
                continue;
            }
            $volume = isset($item['search_volume']) ? floatval($item['search_volume']) : 0;
            $cpc = isset($item['cpc']) ? floatval($item['cpc']) : 0;
            $competition = strtoupper((string) ($item['competition'] ?? ''));
            $competition_score = 0.0;
            if ($competition === 'HIGH') {
                $competition_score = 1.0;
            } elseif ($competition === 'MEDIUM') {
                $competition_score = 0.6;
            } elseif ($competition === 'LOW') {
                $competition_score = 0.3;
            }

            $volume_score = $max_volume > 0 ? ($volume / $max_volume) : 0;
            $cpc_score = $max_cpc > 0 ? ($cpc / $max_cpc) : 0;
            $priority_score = (0.6 * $volume_score) + (0.2 * $cpc_score) + (0.2 * $competition_score);

            $sources = array();
            $context = $serp_context[$keyword]['results'] ?? array();
            foreach (array_slice($context, 0, 3) as $result) {
                $sources[] = array(
                    'title' => $result['title'] ?? '',
                    'url' => $result['url'] ?? '',
                    'domain' => isset($result['url']) ? parse_url($result['url'], PHP_URL_HOST) : '',
                );
            }

            $ranked[] = array(
                'keyword' => $keyword,
                'search_volume' => $volume ?: null,
                'cpc' => $cpc ?: null,
                'competition' => $competition ?: null,
                'priority_score' => round($priority_score, 3),
                'serp_sources' => $sources,
            );
        }

        usort($ranked, function($a, $b) {
            return ($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0);
        });

        return $ranked;
    }

    private function summarize_phase2($ranked_keywords) {
        if (!is_array($ranked_keywords) || empty($ranked_keywords)) {
            return 'Qualification complete, but no keyword metrics were returned.';
        }

        $top = $ranked_keywords[0] ?? null;
        $top_volume = null;
        foreach ($ranked_keywords as $item) {
            if (!empty($item['search_volume'])) {
                $top_volume = $item;
                break;
            }
        }
        $top_cpc = null;
        foreach ($ranked_keywords as $item) {
            if (!empty($item['cpc']) && (!$top_cpc || $item['cpc'] > $top_cpc['cpc'])) {
                $top_cpc = $item;
            }
        }

        $parts = array();
        $parts[] = sprintf('Qualification complete: %d keywords enriched.', count($ranked_keywords));
        if ($top_volume) {
            $parts[] = sprintf('Top demand: %s (%s).', $top_volume['keyword'], $top_volume['search_volume']);
        }
        if ($top_cpc) {
            $parts[] = sprintf('Highest CPC: %s (%s).', $top_cpc['keyword'], $top_cpc['cpc']);
        }

        return implode(' ', $parts);
    }

    public function run_framework_for_article($session_id, $article_id, $force = false) {
        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($session_id);
        if (!$session) {
            return new WP_Error('session_not_found', 'Session not found', array('status' => 404));
        }

        $meta = $this->decode_meta($session['meta_json'] ?? null);
        $articles = $meta['articles'] ?? array();
        $article = null;
        foreach ($articles as $item) {
            if (($item['id'] ?? '') === $article_id) {
                $article = $item;
                break;
            }
        }

        if (!$article) {
            return new WP_Error('article_not_found', 'Article not found', array('status' => 404));
        }

        $citations = $this->collect_framework_citations($article);
        if (is_wp_error($citations)) {
            return $citations;
        }

        $prompt = $this->build_framework_agent_prompt($article, $citations);
        $idempotency = 'planner-fw-' . substr(md5($session_id), 0, 8) . '-' . substr(md5($article_id), 0, 8);
        if ($force) {
            $idempotency .= '-r' . time();
        }
        $job_id = $this->run_job($session_id, $idempotency, $prompt, 'framework');
        if (is_wp_error($job_id)) {
            $meta = $this->decode_meta($session['meta_json'] ?? null);
            $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
            foreach ($articles as $index => $item) {
                if (($item['id'] ?? '') !== $article_id) {
                    continue;
                }
                $articles[$index]['framework'] = array(
                    'status' => 'failed',
                    'error_message' => $job_id->get_error_message(),
                    'completed_at' => current_time('mysql'),
                );
                break;
            }
            $meta['articles'] = $articles;
            $db->update_session_meta($session_id, $meta);
            return $job_id;
        }

        $meta = $this->decode_meta($session['meta_json'] ?? null);
        $articles = isset($meta['articles']) && is_array($meta['articles']) ? $meta['articles'] : array();
        foreach ($articles as $index => $item) {
            if (($item['id'] ?? '') !== $article_id) {
                continue;
            }
            $articles[$index]['framework'] = array(
                'status' => 'running',
                'started_at' => current_time('mysql'),
                'job_id' => $job_id,
            );
            $articles[$index]['citations'] = $citations;
            $articles[$index]['citation_count'] = count($citations);
            break;
        }
        $meta['articles'] = $articles;
        $db->update_session_meta($session_id, $meta);

        return array(
            'job_id' => $job_id,
            'citations' => $citations,
        );
    }

    private function collect_framework_citations($article) {
        $title = sanitize_text_field($article['title'] ?? '');
        $keywords = isset($article['keywords']) ? (array) $article['keywords'] : array();
        $brief = sanitize_textarea_field($article['brief'] ?? '');

        $queries = array(
            $title . ' statistics',
            $title . ' case study',
            $title . ' industry report',
        );
        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }
            $queries[] = $keyword . ' trend report';
        }

        $research_tools = new Dual_GPT_Research_Tools();
        $candidates = array();
        foreach ($queries as $query) {
            $results = $research_tools->web_search($query, 8);
            if (is_wp_error($results)) {
                continue;
            }
            foreach ($results['results'] as $item) {
                $url = $item['url'] ?? '';
                if ($url === '') {
                    continue;
                }
                $candidates[$url] = $item;
            }
        }

        if (empty($candidates)) {
            return new WP_Error('framework_no_candidates', 'No citation candidates found.');
        }

        $scored = array();
        foreach ($candidates as $url => $item) {
            $scored[] = array(
                'url' => $url,
                'title' => $item['title'] ?? '',
                'snippet' => $item['snippet'] ?? '',
                'score' => $this->score_candidate_url($url, $item),
            );
        }

        usort($scored, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $top_k = array_slice($scored, 0, 5);
        $citations = array();
        foreach ($top_k as $item) {
            $page = $research_tools->fetch_url($item['url'], 'text', 6000);
            if (is_wp_error($page) || !empty($page['error'])) {
                continue;
            }
            $meta = $page['meta'] ?? array();
            $author = $meta['author'] ?? '';
            $authors = isset($meta['authors']) && is_array($meta['authors']) ? $meta['authors'] : array();
            if (empty($authors) && $author !== '') {
                $authors = array($author);
            }
            $lead_author = $authors[0] ?? '';
            $additional_authors = '';
            if (count($authors) > 1) {
                $additional_authors = implode(', ', array_slice($authors, 1));
            }
            $publication_date = $meta['published_date'] ?? '';
            $host = parse_url($item['url'], PHP_URL_HOST);
            $citations[] = array(
                'url' => $item['url'],
                'title' => $meta['title'] ?? $item['title'],
                'snippet' => $meta['description'] ?? $item['snippet'],
                'published_at' => $publication_date,
                'publication_date' => $publication_date,
                'lead_author' => $lead_author,
                'additional_authors' => $additional_authors,
                'organisation' => $host,
                'source' => $host,
            );
        }

        if (empty($citations)) {
            return new WP_Error('framework_no_citations', 'Failed to fetch citation content.');
        }

        return $citations;
    }

    private function score_candidate_url($url, $item) {
        $score = 0.0;
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            if (preg_match('/\\.gov$|\\.edu$/', $host)) {
                $score += 0.4;
            } elseif (preg_match('/mckinsey|gartner|deloitte|pwc|forrester|bain|hbr|hbr\\.org|fieldservicenews/i', $host)) {
                $score += 0.35;
            } else {
                $score += 0.2;
            }
        }

        if (!empty($item['date'])) {
            $score += 0.2;
        }

        if (!empty($item['snippet'])) {
            $score += 0.1;
        }

        return min(1, $score);
    }

    private function build_framework_agent_prompt($article, $citations) {
        $build_payload = function ($mode) use ($article, $citations) {
            $brief = $article['brief'] ?? ($article['summary'] ?? '');
            $brief_limit = $mode === 'tight' ? 320 : 640;
            if (is_string($brief)) {
                $brief = trim($brief);
                if (strlen($brief) > $brief_limit) {
                    $brief = substr($brief, 0, $brief_limit) . '…';
                }
            }
            $keywords = isset($article['keywords']) && is_array($article['keywords']) ? $article['keywords'] : array();
            $keywords = array_slice($keywords, 0, $mode === 'tight' ? 4 : 6);
            $article_payload = array(
                'id' => $article['id'] ?? '',
                'title' => $article['title'] ?? '',
                'brief' => $brief,
                'keywords' => $keywords,
                'recommended_word_count' => $article['recommended_word_count'] ?? '',
                'topic_coverage_level' => $article['topic_coverage_level'] ?? '',
            );

            $citation_payload = array();
            $max_citations = $mode === 'tight' ? 3 : 5;
            foreach (array_slice((array) $citations, 0, $max_citations) as $citation) {
                $item = array(
                    'title' => $citation['title'] ?? '',
                    'url' => $citation['url'] ?? '',
                    'source' => $citation['source'] ?? '',
                    'lead_author' => $citation['lead_author'] ?? '',
                    'additional_authors' => $citation['additional_authors'] ?? '',
                    'organisation' => $citation['organisation'] ?? ($citation['source'] ?? ''),
                    'publication_date' => $citation['publication_date'] ?? ($citation['published_at'] ?? ''),
                );
                if ($mode !== 'tight') {
                    $snippet = $citation['snippet'] ?? '';
                    if (is_string($snippet) && strlen($snippet) > 180) {
                        $snippet = substr($snippet, 0, 180) . '…';
                    }
                    $item['snippet'] = $snippet;
                }
                $citation_payload[] = $item;
            }

            return array($article_payload, $citation_payload);
        };

        $build_prompt = function ($article_payload, $citation_payload) {
            $lines = array(
                'Act as an Editorial Planning Assistant. Create a structured framework that is ready for human consumption.',
                'Return ONLY valid JSON.',
                'Schema:',
                '{',
                '"framework":{',
                '  "title":"",',
                '  "overview":"",',
                '  "context":"",',
                '  "application":{"intended_reader":"","use_case":""},',
                '  "observations":[{"headline":"","detail":""}],',
                '  "key_themes":[""]',
                '},',
                '"citations":[{"apa":"","url":"","relevance":"","lead_author":"","additional_authors":"","organisation":"","publication_date":""}]',
                '}',
                'Tone: concise, authoritative, and practical. Use complete sentences.',
                'Observations: 4–6 items, each with a headline and 2–4 sentences of detail.',
                'Citations: 3–5 items; provide APA style in "apa" and a 1–2 sentence relevance note. Keep lead_author/organisation/publication_date aligned with the provided citations (do not invent).',
                'Article:',
                wp_json_encode($article_payload),
                'Citations:',
                wp_json_encode($citation_payload),
            );

            return implode("\n", $lines);
        };

        list($article_payload, $citation_payload) = $build_payload('normal');
        $prompt = $build_prompt($article_payload, $citation_payload);
        if (strlen($prompt) <= 9000) {
            return $prompt;
        }

        list($article_payload, $citation_payload) = $build_payload('tight');
        $prompt = $build_prompt($article_payload, $citation_payload);
        if (strlen($prompt) <= 9000) {
            return $prompt;
        }

        $prompt = str_replace("\n", ' ', $prompt);
        if (strlen($prompt) > 9000) {
            $prompt = substr($prompt, 0, 9000);
        }

        return $prompt;
    }

    public function build_phase1_data($topic, $includes, $excludes, $research_policy = array(), $subgroup = '', $sponsor_context = array(), $exclusions = array()) {
        $research_tools = new Dual_GPT_Research_Tools();
        $keyword_provider = new Dual_GPT_Keyword_Providers();

        $exclusions = is_array($exclusions) ? $exclusions : array();
        $excluded_names = array_map('strtolower', array_values(array_filter((array) ($exclusions['excluded_names'] ?? array()))));
        $excluded_types = array_values(array_filter((array) ($exclusions['excluded_types'] ?? array())));
        $keep_name = strtolower(sanitize_text_field((string) ($exclusions['keep_name'] ?? '')));

        $research_policy = is_array($research_policy) ? $research_policy : array();
        $priority_domains = array_values(array_filter(array_map('sanitize_text_field', (array) ($research_policy['priority_domains'] ?? array()))));
        $preferred_sources = array_values(array_filter(array_map('sanitize_text_field', (array) ($research_policy['preferred_sources'] ?? array()))));
        $blocked_keywords = array_values(array_filter(array_map('strtolower', array_map('sanitize_text_field', (array) ($research_policy['blocked_keywords'] ?? array())))));
        $subgroup = sanitize_text_field((string) $subgroup);
        $sponsor_context = is_array($sponsor_context) ? $sponsor_context : array();
        $is_sponsored = !empty($sponsor_context['is_sponsored']);
        $sponsor_name = sanitize_text_field((string) ($sponsor_context['sponsor_name'] ?? ''));
        $sponsor_weighting = max(0, min(5, intval($sponsor_context['sponsor_weighting'] ?? 2)));

        $queries = array(
            $topic . ' trends',
            $topic . ' industry report',
            $topic . ' market outlook',
        );
        if ($subgroup !== '') {
            $queries[] = $topic . ' ' . $subgroup . ' trends';
            $queries[] = $subgroup . ' market outlook';
        }

        foreach (array_slice($preferred_sources, 0, 3) as $source) {
            $queries[] = $topic . ' ' . $source;
        }

        foreach (array_slice($priority_domains, 0, 3) as $domain) {
            $queries[] = $topic . ' site:' . $domain;
        }

        if ($is_sponsored && $sponsor_name !== '' && $sponsor_weighting >= 3) {
            $queries[] = $sponsor_name . ' ' . $topic;
            if ($subgroup !== '') {
                $queries[] = $sponsor_name . ' ' . $subgroup;
            }
        }

        $queries = array_values(array_unique(array_filter(array_map('trim', $queries))));
        // Limit additional queries from includes to prevent prompt bloat
        $limited_includes = array_slice($includes, 0, 2);
        foreach ($limited_includes as $include) {
            $queries[] = $include . ' trends';
        }

        $serp_snapshot = array();
        foreach ($queries as $query) {
            // Reduce from 8 to 5 results per query
            $results = $research_tools->web_search($query, 5);
            // Truncate snippets in SERP results to reduce size
            if (is_array($results)) {
                foreach ($results as $idx => $result) {
                    if (isset($result['snippet'])) {
                        $results[$idx]['snippet'] = $this->truncate_text($result['snippet'], 200);
                    }
                    if (isset($result['description'])) {
                        $results[$idx]['description'] = $this->truncate_text($result['description'], 200);
                    }
                }
            }
            $serp_snapshot[$query] = $results;
        }

        $keyword_candidates = array();
        $seed_terms = array_merge(array($topic), $limited_includes);
        foreach ($seed_terms as $seed) {
            // Reduce from 25 to 15 suggestions per seed
            $suggestions = $keyword_provider->keyword_suggestions($seed, 15);
            if (is_wp_error($suggestions)) {
                continue;
            }
            foreach ($suggestions as $item) {
                if (!empty($item['keyword'])) {
                    $candidate = sanitize_text_field((string) $item['keyword']);
                    $candidate_l = strtolower($candidate);
                    $blocked = false;
                    foreach ($blocked_keywords as $blocked_term) {
                        if ($blocked_term !== '' && strpos($candidate_l, $blocked_term) !== false) {
                            $blocked = true;
                            break;
                        }
                    }
                    if (!$blocked && $candidate !== '') {
                        $keyword_candidates[$candidate] = true;
                    }
                }
            }
        }

        // Limit total candidate keywords to 75 to prevent prompt bloat
        $candidate_keywords = array_slice(array_keys($keyword_candidates), 0, 75);

        $trend_summary = array();
        $trend_keywords = array_slice($candidate_keywords, 0, 5);
        if (!empty($trend_keywords)) {
            $trend_result = $keyword_provider->keyword_trends($trend_keywords);
            if (!is_wp_error($trend_result)) {
                $trend_summary = $trend_result;
            }
        }

        $internal_coverage = $this->build_internal_content_coverage($topic, $limited_includes, $subgroup, $candidate_keywords);

        return array(
            'serp_snapshot' => $serp_snapshot,
            'candidate_keywords' => $candidate_keywords,
            'trend_summary' => $trend_summary,
            'internal_content_coverage' => $internal_coverage,
            'workflow_directives' => array(
                'subgroup' => $subgroup,
                'preferred_sources' => array_values(array_slice($preferred_sources, 0, 12)),
                'priority_domains' => array_values(array_slice($priority_domains, 0, 12)),
                'blocked_keywords' => array_values(array_slice($blocked_keywords, 0, 20)),
                'sponsor_mode' => array(
                    'enabled' => $is_sponsored,
                    'sponsor_name' => $sponsor_name,
                    'weighting' => $sponsor_weighting,
                ),
                'content_channel' => sanitize_text_field((string) ($exclusions['channel'] ?? 'house')),
                'excluded_entity_names' => array_values($excluded_names),
                'excluded_entity_types' => array_values($excluded_types),
                'keep_entity_name' => $keep_name,
            ),
        );
    }

    /**
     * Resolve which entities/types should be excluded from research queries and prompts
     * based on the session's content_channel, quote_club_mode, submitting_vendor, circle_client.
     *
     * Returns: [channel, excluded_names[], excluded_types[], keep_name]
     * - excluded_names: individual entity names excluded outright (lowercased)
     * - excluded_types: entity type strings excluded (e.g. 'software', 'consultant')
     * - keep_name: one entity that is exempt from type-based exclusion (the submitter/client)
     */
    public function resolve_session_exclusions($meta) {
        $meta = is_array($meta) ? $meta : array();
        $channel = sanitize_text_field((string) ($meta['content_channel'] ?? 'house'));

        $sponsors = array_values(array_filter(
            is_array($meta['category_profile']['target_sponsors'] ?? null)
                ? $meta['category_profile']['target_sponsors']
                : array(),
            function ($e) { return is_array($e) && !empty($e['name']); }
        ));

        $excluded_names = array();
        $excluded_types = array();
        $keep_name = '';

        if ($channel === 'quote_club') {
            $mode = sanitize_text_field((string) ($meta['quote_club_mode'] ?? 'summary'));
            if ($mode === 'summary') {
                // Vendor-agnostic summaries: exclude ALL sponsors by name
                foreach ($sponsors as $entity) {
                    $excluded_names[] = strtolower((string) $entity['name']);
                }
            } elseif ($mode === 'framework') {
                // Submitting vendor stays; exclude all others of the same type
                $sv = is_array($meta['submitting_vendor'] ?? null) ? $meta['submitting_vendor'] : array();
                $sv_name = sanitize_text_field((string) ($sv['name'] ?? ''));
                $sv_type = strtolower(sanitize_text_field((string) ($sv['type'] ?? '')));
                $keep_name = strtolower($sv_name);
                if ($sv_type !== '') {
                    $excluded_types[] = $sv_type;
                }
            }
        } elseif ($channel === 'circle') {
            // Ghost-written: exclude same-type competitors/sponsors, keep client
            $cc = is_array($meta['circle_client'] ?? null) ? $meta['circle_client'] : array();
            $cc_name = sanitize_text_field((string) ($cc['name'] ?? ''));
            $cc_type = strtolower(sanitize_text_field((string) ($cc['type'] ?? '')));
            $keep_name = strtolower($cc_name);
            if ($cc_type !== '') {
                $excluded_types[] = $cc_type;
            }
        }

        return array(
            'channel' => $channel,
            'excluded_names' => array_values(array_unique($excluded_names)),
            'excluded_types' => array_values(array_unique($excluded_types)),
            'keep_name' => $keep_name,
        );
    }

    /**
     * Filter a typed entity list [{name, type}] through session exclusions.
     * Removes entities whose name is in excluded_names OR whose type is in excluded_types,
     * unless their name matches keep_name.
     */
    private function apply_entity_exclusions($entities, $exclusions) {
        $excluded_names = array_map('strtolower', (array) ($exclusions['excluded_names'] ?? array()));
        $excluded_types = array_map('strtolower', (array) ($exclusions['excluded_types'] ?? array()));
        $keep_name = strtolower((string) ($exclusions['keep_name'] ?? ''));

        $result = array();
        foreach ((array) $entities as $entity) {
            if (!is_array($entity) || empty($entity['name'])) {
                continue;
            }
            $name_l = strtolower($entity['name']);
            $type_l = strtolower((string) ($entity['type'] ?? ''));
            if ($keep_name !== '' && $name_l === $keep_name) {
                $result[] = $entity;
                continue;
            }
            if (in_array($name_l, $excluded_names, true)) {
                continue;
            }
            if ($type_l !== '' && in_array($type_l, $excluded_types, true)) {
                continue;
            }
            $result[] = $entity;
        }
        return $result;
    }

    private function build_internal_content_coverage($topic, $includes = array(), $subgroup = '', $candidate_keywords = array()) {
        $terms = $this->build_internal_coverage_terms($topic, $includes, $subgroup, $candidate_keywords);
        if (empty($terms)) {
            return array(
                'summary' => array(
                    'posts_analyzed' => 0,
                    'matching_posts' => 0,
                    'coverage_terms_considered' => 0,
                    'analysis_window_months' => 60,
                ),
                'terms' => array(),
                'top_covered_terms' => array(),
                'priority_gaps' => array(),
            );
        }

        $posts = get_posts(array(
            'post_type' => array('post', 'atomic_article'),
            'post_status' => 'publish',
            'posts_per_page' => 350,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => array(
                array(
                    'after' => gmdate('Y-m-d', strtotime('-60 months')),
                ),
            ),
            'suppress_filters' => false,
        ));

        $stats = array();
        foreach ($terms as $term) {
            $stats[$term] = array(
                'term' => $term,
                'hits' => 0,
                'latest_ts' => 0,
                'coverage_score' => 0,
                'coverage_level' => 'low',
                'days_since_last_mention' => null,
                'latest_post_date' => '',
            );
        }

        $matching_post_ids = array();
        foreach ((array) $posts as $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }

            $body = strtolower(
                wp_strip_all_tags(
                    (string) $post->post_title . ' ' .
                    (string) $post->post_excerpt . ' ' .
                    (string) $post->post_content
                )
            );

            $post_ts = strtotime((string) $post->post_date_gmt ?: (string) $post->post_date);
            foreach ($stats as $term => $term_stats) {
                if (strpos($body, strtolower($term)) === false) {
                    continue;
                }

                $stats[$term]['hits']++;
                $matching_post_ids[$post->ID] = true;
                if ($post_ts > $stats[$term]['latest_ts']) {
                    $stats[$term]['latest_ts'] = $post_ts;
                }
            }
        }

        $now_ts = current_time('timestamp', true);
        foreach ($stats as $term => $term_stats) {
            $hits = intval($term_stats['hits']);
            $latest_ts = intval($term_stats['latest_ts']);

            $days_since_last = null;
            if ($latest_ts > 0) {
                $days_since_last = max(0, intval(floor(($now_ts - $latest_ts) / DAY_IN_SECONDS)));
            }

            $volume_score = min(60, $hits * 15);
            $recency_score = 0;
            if ($days_since_last !== null) {
                if ($days_since_last <= 30) {
                    $recency_score = 40;
                } elseif ($days_since_last <= 90) {
                    $recency_score = 30;
                } elseif ($days_since_last <= 180) {
                    $recency_score = 20;
                } elseif ($days_since_last <= 365) {
                    $recency_score = 10;
                }
            }

            $coverage_score = max(0, min(100, $volume_score + $recency_score));
            $coverage_level = 'low';
            if ($coverage_score >= 70) {
                $coverage_level = 'high';
            } elseif ($coverage_score >= 35) {
                $coverage_level = 'medium';
            }

            $stats[$term]['coverage_score'] = $coverage_score;
            $stats[$term]['coverage_level'] = $coverage_level;
            $stats[$term]['days_since_last_mention'] = $days_since_last;
            $stats[$term]['latest_post_date'] = $latest_ts > 0 ? gmdate('Y-m-d', $latest_ts) : '';
            unset($stats[$term]['latest_ts']);
        }

        $term_rows = array_values($stats);
        usort($term_rows, function ($a, $b) {
            $score_cmp = intval($b['coverage_score']) <=> intval($a['coverage_score']);
            if ($score_cmp !== 0) {
                return $score_cmp;
            }
            return intval($b['hits']) <=> intval($a['hits']);
        });

        $top_covered = array_map(function ($row) {
            return array(
                'term' => (string) ($row['term'] ?? ''),
                'hits' => intval($row['hits'] ?? 0),
                'days_since_last_mention' => $row['days_since_last_mention'],
                'coverage_level' => (string) ($row['coverage_level'] ?? 'low'),
            );
        }, array_slice($term_rows, 0, 8));

        $gap_candidates = $term_rows;
        usort($gap_candidates, function ($a, $b) {
            $a_days = is_numeric($a['days_since_last_mention']) ? intval($a['days_since_last_mention']) : 99999;
            $b_days = is_numeric($b['days_since_last_mention']) ? intval($b['days_since_last_mention']) : 99999;

            $a_gap_score = intval($a['coverage_score']) - min(240, $a_days);
            $b_gap_score = intval($b['coverage_score']) - min(240, $b_days);
            return $a_gap_score <=> $b_gap_score;
        });

        $priority_gaps = array_values(array_filter(array_map(function ($row) {
            if (intval($row['hits'] ?? 0) === 0) {
                return (string) ($row['term'] ?? '');
            }
            $days = $row['days_since_last_mention'];
            if (is_numeric($days) && intval($days) > 180) {
                return (string) ($row['term'] ?? '');
            }
            if ((string) ($row['coverage_level'] ?? '') === 'low') {
                return (string) ($row['term'] ?? '');
            }
            return '';
        }, array_slice($gap_candidates, 0, 12))));
        $priority_gaps = array_values(array_slice(array_unique($priority_gaps), 0, 8));

        return array(
            'summary' => array(
                'posts_analyzed' => count((array) $posts),
                'matching_posts' => count($matching_post_ids),
                'coverage_terms_considered' => count($term_rows),
                'analysis_window_months' => 60,
            ),
            'terms' => array_values(array_slice($term_rows, 0, 24)),
            'top_covered_terms' => $top_covered,
            'priority_gaps' => $priority_gaps,
        );
    }

    private function build_internal_coverage_terms($topic, $includes = array(), $subgroup = '', $candidate_keywords = array()) {
        $raw_terms = array_merge(
            array((string) $topic),
            (array) $includes,
            $subgroup !== '' ? array($subgroup) : array(),
            array_slice((array) $candidate_keywords, 0, 20)
        );

        $terms = array();
        foreach ($raw_terms as $term) {
            $term = trim(sanitize_text_field((string) $term));
            if ($term === '' || strlen($term) < 3) {
                continue;
            }
            $terms[$term] = true;
        }

        return array_slice(array_keys($terms), 0, 30);
    }

    private function build_required_dossier_context($meta, $requested_phase = 'phase3') {
        $meta = is_array($meta) ? $meta : array();
        $dossier = is_array($meta['planner_dossier'] ?? null) ? $meta['planner_dossier'] : array();

        $phase_snapshot = $this->load_dossier_snapshot_from_artifact($dossier);
        if (empty($phase_snapshot)) {
            $phase_snapshot = is_array($dossier['phase_snapshot'] ?? null) ? $dossier['phase_snapshot'] : array();
        }

        $phase_snapshot = $this->hydrate_dossier_snapshot_from_meta($phase_snapshot, $meta);

        $required = $requested_phase === 'phase4'
            ? array(
                'phase1.summary',
                'phase2.ranked_keywords',
                'phase3.summary',
                'phase3.prioritized_topics',
            )
            : array(
                'phase1.summary',
                'phase1.candidate_keywords',
                'phase2.summary',
                'phase2.ranked_keywords',
            );

        $missing = array();
        foreach ($required as $path) {
            if (!$this->dossier_path_has_value($phase_snapshot, $path)) {
                $missing[] = $path;
            }
        }

        return array(
            'artifact_ref' => sanitize_text_field((string) ($dossier['artifact_ref'] ?? '')),
            'url' => esc_url_raw((string) ($dossier['url'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($dossier['updated_at'] ?? '')),
            'requested_phase' => sanitize_key((string) $requested_phase),
            'phase_snapshot' => $phase_snapshot,
            'completeness' => array(
                'required_fields' => $required,
                'missing_fields' => $missing,
                'is_complete' => empty($missing),
            ),
        );
    }

    private function load_dossier_snapshot_from_artifact($dossier) {
        $post_id = intval($dossier['post_id'] ?? 0);
        if ($post_id <= 0) {
            return array();
        }

        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) {
            return array();
        }

        $content = (string) $post->post_content;
        if ($content === '') {
            return array();
        }

        $matches = array();
        preg_match_all('/##\s+PHASE([1-4])\s+```json\s*(\{(?:.|\n|\r)*?\})\s*```/i', $content, $matches, PREG_SET_ORDER);
        if (empty($matches)) {
            return array();
        }

        $snapshot = array();
        foreach ($matches as $match) {
            $phase_num = intval($match[1] ?? 0);
            $json_blob = (string) ($match[2] ?? '');
            if ($phase_num < 1 || $phase_num > 4 || $json_blob === '') {
                continue;
            }

            $decoded = json_decode($json_blob, true);
            if (is_array($decoded)) {
                $snapshot['phase' . $phase_num] = $decoded;
            }
        }

        return $snapshot;
    }

    private function hydrate_dossier_snapshot_from_meta($snapshot, $meta) {
        $snapshot = is_array($snapshot) ? $snapshot : array();
        $meta = is_array($meta) ? $meta : array();
        $phases = is_array($meta['phases'] ?? null) ? $meta['phases'] : array();

        if (empty($snapshot['phase1'])) {
            $p1 = is_array($phases['phase1']['payload'] ?? null) ? $phases['phase1']['payload'] : array();
            $snapshot['phase1'] = array(
                'summary' => sanitize_text_field((string) ($phases['phase1']['summary'] ?? $p1['executive_summary'] ?? '')),
                'candidate_keywords' => array_slice((array) ($p1['candidate_keywords'] ?? $meta['phase1']['candidate_keywords'] ?? array()), 0, 16),
            );
        }

        if (empty($snapshot['phase2'])) {
            $p2 = is_array($phases['phase2']['payload'] ?? null) ? $phases['phase2']['payload'] : array();
            $snapshot['phase2'] = array(
                'summary' => sanitize_text_field((string) ($phases['phase2']['summary'] ?? $p2['summary'] ?? '')),
                'ranked_keywords' => array_slice((array) ($p2['ranked_keywords'] ?? array()), 0, 16),
            );
        }

        if (empty($snapshot['phase3']) && !empty($phases['phase3'])) {
            $p3 = is_array($phases['phase3']['payload'] ?? null) ? $phases['phase3']['payload'] : array();
            $snapshot['phase3'] = array(
                'summary' => sanitize_text_field((string) ($phases['phase3']['summary'] ?? $p3['article_summary'] ?? '')),
                'prioritized_topics' => array_slice((array) ($p3['prioritized_topics'] ?? array()), 0, 12),
            );
        }

        if (empty($snapshot['phase4']) && !empty($phases['phase4'])) {
            $p4 = is_array($phases['phase4']['payload'] ?? null) ? $phases['phase4']['payload'] : array();
            $snapshot['phase4'] = array(
                'summary' => sanitize_text_field((string) ($phases['phase4']['summary'] ?? $p4['validation_summary'] ?? '')),
                'validated_topics' => array_slice((array) ($p4['validated_topics'] ?? array()), 0, 12),
            );
        }

        return $snapshot;
    }

    private function dossier_path_has_value($snapshot, $path) {
        $cursor = $snapshot;
        foreach (explode('.', (string) $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }

        if (is_array($cursor)) {
            return !empty($cursor);
        }

        return trim((string) $cursor) !== '';
    }

    private function create_framework_session($planner_session_id, $article_title, $article_tags) {
        $request = new WP_REST_Request('POST', '/dual-gpt/v1/sessions');
        $request->set_param('role', 'research');
        $request->set_param('preset_id', 'fg-framework-generator');
        $request->set_param('title', 'Framework: ' . $article_title);
        $request->set_param('meta', array(
            'planner_session_id' => $planner_session_id,
            'article_title' => $article_title,
            'article_tags' => $article_tags,
        ));
        $request->set_param('idempotency_key', 'framework-session-' . md5($planner_session_id . $article_title));

        $response = $this->plugin->create_session($request);
        if (is_wp_error($response)) {
            return $response;
        }

        $data = $response instanceof WP_REST_Response ? $response->get_data() : $response;
        $session_id = $data['session_id'] ?? null;

        if (!$session_id) {
            return new WP_Error('session_creation_failed', 'Framework session ID not returned', array('status' => 500));
        }

        return $session_id;
    }
}
