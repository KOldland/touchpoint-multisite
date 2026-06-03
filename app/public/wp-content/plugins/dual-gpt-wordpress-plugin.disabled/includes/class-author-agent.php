<?php
/**
 * Author Agent API + Core Logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Author_Agent_API {

    /**
     * Register REST routes
     */
    public function register_routes() {
        register_rest_route('dual-gpt/v1', '/author/run', array(
            'methods' => 'POST',
            'callback' => array($this, 'run_author_agent'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'mode' => array(
                    'type' => 'string',
                    'required' => true,
                    'enum' => array('draft', 'abstract', 'enrichment'),
                ),
                'framework_brief_id' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'planner_session_id' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'draft_content' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'instructions' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'core_settings' => array(
                    'type' => 'object',
                    'required' => false,
                ),
                'author_policy' => array(
                    'type' => 'object',
                    'required' => false,
                ),
            ),
        ));
    }

    /**
     * Permission check
     */
    public function check_permissions() {
        return current_user_can('edit_posts');
    }

    /**
     * Run the Author Agent
     */
    public function run_author_agent($request) {
        $params = $request->get_params();

        $agent = new Dual_GPT_Author_Agent();
        $result = $agent->run($params, get_current_user_id());

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }
}

class Dual_GPT_Author_Agent {

    /**
     * Run Author Agent based on mode
     */
    public function run($payload, $user_id = null) {
        $mode = sanitize_text_field($payload['mode'] ?? $payload['author_mode'] ?? '');

        if (!in_array($mode, array('draft', 'abstract', 'enrichment'), true)) {
            return new WP_Error('invalid_mode', 'Author mode must be draft, abstract, or enrichment.', array('status' => 400));
        }

        $framework_brief_id = sanitize_text_field($payload['framework_brief_id'] ?? '');
        $planner_session_id = sanitize_text_field($payload['planner_session_id'] ?? '');
        $draft_content = $payload['draft_content'] ?? '';
        $instructions = sanitize_textarea_field($payload['instructions'] ?? '');
        $core_settings = $this->normalize_core_settings($payload['core_settings'] ?? array());
        $author_policy = $this->resolve_author_policy($payload, $planner_session_id);

        $warnings = array();
        $validation_errors = array();

        $framework_brief = null;
        $planner_brief = null;

        if ($mode === 'draft') {
            if (empty($framework_brief_id) || empty($planner_session_id)) {
                return new WP_Error('missing_briefs', 'Framework brief ID and planner session ID are required for draft mode.', array('status' => 400));
            }

            $framework_brief = $this->get_framework_brief($framework_brief_id);
            if (is_wp_error($framework_brief)) {
                return $framework_brief;
            }

            $planner_brief = $this->get_planner_brief($planner_session_id);
            if (is_wp_error($planner_brief)) {
                return $planner_brief;
            }
        } else {
            if (!empty($framework_brief_id)) {
                $framework_brief = $this->get_framework_brief($framework_brief_id);
                if (is_wp_error($framework_brief)) {
                    $warnings[] = $framework_brief->get_error_message();
                    $framework_brief = null;
                }
            }

            if (!empty($planner_session_id)) {
                $planner_brief = $this->get_planner_brief($planner_session_id);
                if (is_wp_error($planner_brief)) {
                    $warnings[] = $planner_brief->get_error_message();
                    $planner_brief = null;
                }
            }
        }

        $citations = $this->get_verified_citations($planner_session_id, $framework_brief);
        if (empty($citations)) {
            $warnings[] = 'No verified citations available. Author Agent will avoid introducing citations.';
        }

        if ($mode === 'draft') {
            $budget_check = $this->check_budget($user_id);
            if (is_wp_error($budget_check)) {
                return $budget_check;
            }

            $draft_result = $this->generate_draft($framework_brief, $planner_brief, $citations, $core_settings, $author_policy, $instructions, $user_id);
            if (is_wp_error($draft_result)) {
                return $draft_result;
            }

            $validation = $this->validate_citations_in_text($draft_result['text'] ?? '', $citations);
            $constraint_validation = $this->validate_draft_constraints($draft_result['blocks'] ?? array(), $core_settings, $author_policy);
            $warnings = array_merge($warnings, $validation['warnings']);
            $validation_errors = array_merge($validation_errors, $validation['errors']);
            $warnings = array_merge($warnings, $constraint_validation['warnings']);
            $validation_errors = array_merge($validation_errors, $constraint_validation['errors']);

            return array(
                'mode' => 'draft',
                'output' => $draft_result,
                'warnings' => $warnings,
                'validation_errors' => $validation_errors,
                'citations' => $citations,
                'author_policy' => $author_policy,
                'usage' => $draft_result['usage'] ?? null,
            );
        }

        if ($mode === 'abstract') {
            if (empty($draft_content)) {
                return new WP_Error('missing_draft', 'Draft content is required for abstract mode.', array('status' => 400));
            }

            $budget_check = $this->check_budget($user_id);
            if (is_wp_error($budget_check)) {
                return $budget_check;
            }

            $abstract_result = $this->generate_abstract($draft_content, $user_id);
            if (is_wp_error($abstract_result)) {
                return $abstract_result;
            }

            $abstract_validation = $this->validate_abstract($abstract_result['abstract'] ?? array());
            $warnings = array_merge($warnings, $abstract_validation['warnings']);
            $validation_errors = array_merge($validation_errors, $abstract_validation['errors']);

            return array(
                'mode' => 'abstract',
                'output' => $abstract_result,
                'warnings' => $warnings,
                'validation_errors' => $validation_errors,
                'citations' => $citations,
                'author_policy' => $author_policy,
                'usage' => $abstract_result['usage'] ?? null,
            );
        }

        if (empty($draft_content)) {
            return new WP_Error('missing_draft', 'Draft content is required for enrichment mode.', array('status' => 400));
        }

        $enrichment_result = $this->generate_enrichment($draft_content, $citations);
        $warnings = array_merge($warnings, $enrichment_result['warnings'] ?? array());
        $validation_errors = array_merge($validation_errors, $enrichment_result['validation_errors'] ?? array());

        return array(
            'mode' => 'enrichment',
            'output' => $enrichment_result,
            'warnings' => $warnings,
            'validation_errors' => $validation_errors,
            'citations' => $citations,
            'author_policy' => $author_policy,
        );
    }

    /**
     * Normalize core settings
     */
    private function normalize_core_settings($settings) {
        $defaults = array(
            'industry_focus' => get_option('dual_gpt_core_industry_focus', 'General'),
            'audience_tier' => get_option('dual_gpt_core_audience_tier', 'General'),
            'risk_tolerance' => get_option('dual_gpt_core_risk_tolerance', 'Moderate'),
            'brand_profile' => get_option('dual_gpt_core_brand_profile', 'Brand A (FSI)'),
        );

        if (!is_array($settings)) {
            return $defaults;
        }

        return array(
            'industry_focus' => sanitize_text_field($settings['industry_focus'] ?? $defaults['industry_focus']),
            'audience_tier' => sanitize_text_field($settings['audience_tier'] ?? $defaults['audience_tier']),
            'risk_tolerance' => sanitize_text_field($settings['risk_tolerance'] ?? $defaults['risk_tolerance']),
            'brand_profile' => sanitize_text_field($settings['brand_profile'] ?? $defaults['brand_profile']),
        );
    }

    private function default_author_policy() {
        return array(
            'reporter_voice_required' => true,
            'disallow_first_person' => true,
            'disallow_em_dash' => true,
            'disallow_rhetorical_binaries' => true,
            'disallow_listicle_framing' => true,
            'disallow_tidy_conclusion' => true,
            'min_words' => 1200,
            'max_words' => 2600,
            'banned_phrases' => array(),
        );
    }

    private function sanitize_author_policy($policy) {
        $defaults = $this->default_author_policy();
        $policy = is_array($policy) ? $policy : array();

        $banned_phrases = $policy['banned_phrases'] ?? $defaults['banned_phrases'];
        if (is_string($banned_phrases)) {
            $banned_phrases = array_filter(array_map('trim', explode(',', $banned_phrases)));
        }
        if (!is_array($banned_phrases)) {
            $banned_phrases = array();
        }
        $banned_phrases = array_values(array_unique(array_filter(array_map(function ($phrase) {
            return strtolower(trim((string) $phrase));
        }, $banned_phrases))));

        $min_words = max(300, intval($policy['min_words'] ?? $defaults['min_words']));
        $max_words = max($min_words, intval($policy['max_words'] ?? $defaults['max_words']));

        return array(
            'reporter_voice_required' => (bool) ($policy['reporter_voice_required'] ?? $defaults['reporter_voice_required']),
            'disallow_first_person' => (bool) ($policy['disallow_first_person'] ?? $defaults['disallow_first_person']),
            'disallow_em_dash' => (bool) ($policy['disallow_em_dash'] ?? $defaults['disallow_em_dash']),
            'disallow_rhetorical_binaries' => (bool) ($policy['disallow_rhetorical_binaries'] ?? $defaults['disallow_rhetorical_binaries']),
            'disallow_listicle_framing' => (bool) ($policy['disallow_listicle_framing'] ?? $defaults['disallow_listicle_framing']),
            'disallow_tidy_conclusion' => (bool) ($policy['disallow_tidy_conclusion'] ?? $defaults['disallow_tidy_conclusion']),
            'min_words' => $min_words,
            'max_words' => $max_words,
            'banned_phrases' => $banned_phrases,
        );
    }

    private function resolve_author_policy($payload, $planner_session_id) {
        if (!empty($payload['author_policy']) && is_array($payload['author_policy'])) {
            return $this->sanitize_author_policy($payload['author_policy']);
        }

        $session_policy = $this->load_author_policy_from_session($planner_session_id);
        return $this->sanitize_author_policy($session_policy);
    }

    private function load_author_policy_from_session($planner_session_id) {
        if (empty($planner_session_id)) {
            return array();
        }

        $db = new Dual_GPT_DB_Handler();
        $session = $db->get_session($planner_session_id);
        if (!$session || empty($session['meta_json'])) {
            return array();
        }

        $meta = json_decode($session['meta_json'], true);
        if (!is_array($meta)) {
            return array();
        }

        if (!empty($meta['author_policy']) && is_array($meta['author_policy'])) {
            return $meta['author_policy'];
        }

        if (!empty($meta['persona_policy']['author']) && is_array($meta['persona_policy']['author'])) {
            return $meta['persona_policy']['author'];
        }

        return array();
    }

    /**
     * Fetch Framework Builder brief
     */
    private function get_framework_brief($brief_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fg_briefs';

        $brief = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $brief_id),
            ARRAY_A
        );

        if (!$brief) {
            return new WP_Error('framework_brief_not_found', 'Framework brief not found.', array('status' => 404));
        }

        $json_fields = array('application', 'observations', 'key_themes', 'citations', 'writer_guidance', 'article_idea');
        foreach ($json_fields as $field) {
            if (!empty($brief[$field]) && is_string($brief[$field])) {
                $brief[$field] = json_decode($brief[$field], true);
            }
        }

        return $brief;
    }

    /**
     * Fetch Editorial Planner brief
     */
    private function get_planner_brief($session_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ep_briefs';

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_exists) {
            return new WP_Error('planner_unavailable', 'Editorial Planner tables not found.', array('status' => 404));
        }

        $brief = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %s", $session_id),
            ARRAY_A
        );

        if (!$brief) {
            return new WP_Error('planner_brief_not_found', 'Editorial Planner brief not found.', array('status' => 404));
        }

        $json_fields = array('application', 'observations', 'key_themes', 'citations');
        foreach ($json_fields as $field) {
            if (!empty($brief[$field]) && is_string($brief[$field])) {
                $brief[$field] = json_decode($brief[$field], true);
            }
        }

        return $brief;
    }

    /**
     * Pull verified citations from planner + framework sources
     */
    private function get_verified_citations($planner_session_id, $framework_brief) {
        $citations = array();
        $seen_urls = array();

        if (!empty($planner_session_id)) {
            $citations = array_merge($citations, $this->get_planner_citations($planner_session_id));
        }

        if (!empty($framework_brief)) {
            $citations = array_merge($citations, $this->get_framework_citations($framework_brief));
        }

        $filtered = array();
        foreach ($citations as $citation) {
            $url = strtolower(trim($citation['url'] ?? ''));
            if ($url && isset($seen_urls[$url])) {
                continue;
            }
            if ($url) {
                $seen_urls[$url] = true;
            }
            $filtered[] = $citation;
        }

        foreach ($filtered as $index => &$citation) {
            $citation['ref_id'] = $index + 1;
        }

        return $filtered;
    }

    /**
     * Get citations from Editorial Planner
     */
    private function get_planner_citations($session_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ep_citations';

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_exists) {
            return array();
        }

        $citations = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %s AND approved = 1", $session_id),
            ARRAY_A
        );

        return array_map(array($this, 'normalize_citation_row'), $citations);
    }

    /**
     * Get citations from Framework Builder (FG)
     */
    private function get_framework_citations($framework_brief) {
        $session_id = $framework_brief['session_id'] ?? '';
        $citations = array();

        if (!empty($framework_brief['citations']) && is_array($framework_brief['citations'])) {
            foreach ($framework_brief['citations'] as $citation) {
                $citations[] = $this->normalize_citation_row($citation);
            }
        }

        if (!empty($session_id)) {
            global $wpdb;
            $table = $wpdb->prefix . 'fg_validated_citations';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($table_exists) {
                $has_approved = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'approved'));
                if (!empty($has_approved)) {
                    $rows = $wpdb->get_results(
                        $wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %s AND approved = 1", $session_id),
                        ARRAY_A
                    );
                } else {
                    $rows = $wpdb->get_results(
                        $wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %s", $session_id),
                        ARRAY_A
                    );
                }

                foreach ($rows as $row) {
                    $citations[] = $this->normalize_citation_row($row);
                }
            }
        }

        return $citations;
    }

    /**
     * Normalize citation row
     */
    private function normalize_citation_row($citation) {
        if (!is_array($citation)) {
            return array();
        }

        $year = $citation['year'] ?? $citation['publication_year'] ?? '';
        $date = $citation['date'] ?? $citation['publication_date'] ?? $year;

        return array(
            'citation_id' => $citation['id'] ?? $citation['citation_id'] ?? null,
            'title' => $citation['title'] ?? '',
            'lead_author' => $citation['lead_author'] ?? $citation['author'] ?? '',
            'publication' => $citation['publication'] ?? $citation['source'] ?? '',
            'organisation' => $citation['organisation'] ?? $citation['domain'] ?? '',
            'year' => $year,
            'date' => $date,
            'url' => $citation['url'] ?? '',
            'apa_string' => $citation['apa_string'] ?? $citation['apa'] ?? '',
            'passage_snippet' => $citation['passage_snippet'] ?? $citation['snippet'] ?? '',
            'type' => $citation['type'] ?? '',
        );
    }

    /**
     * Generate draft content
     */
    private function generate_draft($framework_brief, $planner_brief, $citations, $core_settings, $author_policy, $instructions, $user_id) {
        $llm_client = new \Dual_GPT\Dual_GPT_LLM_Client();
        if (!$llm_client->has_api_key()) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured.', array('status' => 500));
        }

        $model_config = class_exists('Dual_GPT_Model_Config') ? new Dual_GPT_Model_Config() : null;
        $model = $model_config ? $model_config->get_model_for_task('author') : $llm_client->get_model_name();

        $system_prompt = $this->build_draft_system_prompt($core_settings, $author_policy);
        $user_prompt = $this->build_draft_user_prompt($framework_brief, $planner_brief, $citations, $instructions, $core_settings, $author_policy);

        $response = $llm_client->call($system_prompt, $user_prompt, array(
            'temperature' => 0.4,
            'max_tokens' => 3000,
            'model' => $model,
            'json_mode' => true,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $content = $llm_client->extract_content($response);
        $data = json_decode($content, true);

        if (!is_array($data) || empty($data['blocks'])) {
            return new WP_Error('invalid_draft_output', 'Draft output is not valid JSON with blocks.', array('status' => 500));
        }

        $data['blocks'] = $this->normalize_draft_blocks($data['blocks']);

        $usage = $llm_client->get_usage($response);
        $cost = $llm_client->estimate_cost($usage, $model);
        $this->update_budget_usage($user_id, $usage);

        $text = $this->blocks_to_text($data['blocks']);

        return array(
            'blocks' => $data['blocks'],
            'text' => $text,
            'word_count' => str_word_count($text),
            'usage' => $usage,
            'model_used' => $model,
            'cost_estimate' => $cost,
        );
    }

    /**
     * Generate abstract and metadata
     */
    private function generate_abstract($draft_content, $user_id) {
        $llm_client = new \Dual_GPT\Dual_GPT_LLM_Client();
        if (!$llm_client->has_api_key()) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured.', array('status' => 500));
        }

        $model_config = class_exists('Dual_GPT_Model_Config') ? new Dual_GPT_Model_Config() : null;
        $model = $model_config ? $model_config->get_model_for_task('author') : $llm_client->get_model_name();

        $system_prompt = $this->build_abstract_system_prompt();
        $user_prompt = $this->build_abstract_user_prompt($draft_content);

        $response = $llm_client->call($system_prompt, $user_prompt, array(
            'temperature' => 0.2,
            'max_tokens' => 1200,
            'model' => $model,
            'json_mode' => true,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $content = $llm_client->extract_content($response);
        $data = json_decode($content, true);

        if (!is_array($data) || empty($data['overview'])) {
            return new WP_Error('invalid_abstract_output', 'Abstract output is not valid JSON.', array('status' => 500));
        }

        $usage = $llm_client->get_usage($response);
        $cost = $llm_client->estimate_cost($usage, $model);
        $this->update_budget_usage($user_id, $usage);

        return array(
            'abstract' => $data,
            'usage' => $usage,
            'model_used' => $model,
            'cost_estimate' => $cost,
        );
    }

    /**
     * Generate pull quotes and footnotes
     */
    private function generate_enrichment($draft_content, $citations) {
        $warnings = array();
        $validation_errors = array();

        $blocks = $this->parse_draft_into_blocks($draft_content);
        if (empty($blocks)) {
            return array(
                'blocks' => array(),
                'pull_quotes' => array(),
                'footnotes' => array(),
                'warnings' => array('No draft blocks could be parsed.'),
                'validation_errors' => array(),
            );
        }

        $word_count = str_word_count($this->blocks_to_text($blocks));
        $target_quotes = max(1, (int) floor($word_count / 500));

        $pull_quotes = array();
        $output_blocks = array();

        foreach ($blocks as $block) {
            $output_blocks[] = $block;

            if ($block['type'] !== 'paragraph') {
                continue;
            }

            if (count($pull_quotes) >= $target_quotes) {
                continue;
            }

            $paragraph = $block['content'] ?? '';
            $citation_refs = $this->extract_citation_markers($paragraph);
            if (empty($citation_refs)) {
                continue;
            }

            if (!$this->paragraph_has_numeric_claim($paragraph)) {
                continue;
            }

            $quote_text = $this->extract_sentence_with_number($paragraph);
            if (!$quote_text) {
                continue;
            }

            $ref_id = $citation_refs[0];
            $citation = $this->find_citation_by_ref($citations, $ref_id);
            if (!$citation) {
                continue;
            }

            $pull_quote_meta = array(
                'source_author' => $citation['lead_author'],
                'publication' => $citation['publication'],
                'organisation' => $citation['organisation'],
                'date' => $citation['date'],
                'citation_ref_id' => $citation['ref_id'],
            );

            $pull_quotes[] = array(
                'text' => $quote_text,
                'metadata' => $pull_quote_meta,
            );

            $output_blocks[] = array(
                'type' => 'pullquote',
                'content' => $quote_text,
                'cite' => $this->format_pullquote_citation($citation),
                'meta' => $pull_quote_meta,
            );
        }

        if (empty($pull_quotes)) {
            $warnings[] = 'No eligible pull quotes were found. Pull quotes skipped.';
        }

        $footnotes = $this->build_footnotes($citations);
        if (empty($footnotes)) {
            $warnings[] = 'No verified citations available to build footnotes.';
        } else {
            $output_blocks[] = array('type' => 'heading', 'level' => 2, 'content' => 'References');
            $output_blocks[] = array('type' => 'list', 'ordered' => false, 'items' => $footnotes);
        }
        $warnings = array_merge($warnings, $this->validate_footnotes($citations));

        return array(
            'blocks' => $output_blocks,
            'pull_quotes' => $pull_quotes,
            'footnotes' => $footnotes,
            'warnings' => $warnings,
            'validation_errors' => $validation_errors,
        );
    }

    /**
     * Build draft system prompt
     */
    private function build_draft_system_prompt($core_settings, $author_policy) {
        $brand_profile = $core_settings['brand_profile'] ?? 'Brand A (FSI)';
        $em_dash_guidance = $this->get_em_dash_guidance($brand_profile);
        $author_policy = $this->sanitize_author_policy($author_policy);

        $lines = array(
            'You are the Author Agent. You execute an approved editorial plan and framework without adding new strategy, SEO, or distribution logic.',
            'You must not introduce new citations, entities, or claims beyond provided materials.',
            'Do not modify the topic scope or angle.',
            'Persona: ' . ($author_policy['reporter_voice_required'] ? 'Experienced Analyst / Senior Journalist.' : 'Professional B2B analyst writer.'),
            'Industry focus: ' . $core_settings['industry_focus'],
            'Audience tier: ' . $core_settings['audience_tier'],
            'Risk tolerance: ' . $core_settings['risk_tolerance'],
            'Brand profile: ' . $brand_profile,
            $author_policy['disallow_em_dash'] ? 'No em dashes (—) or double hyphens (--).' : $em_dash_guidance,
            $author_policy['disallow_tidy_conclusion'] ? 'No tidy conclusions. No omniscient voice. Allow tonal variation and friction.' : 'Avoid definitive resolution unless source-backed.',
            $author_policy['disallow_first_person'] ? 'Do not use first-person pronouns (I, we, our, us).' : 'Prefer third-person perspective.',
            'Output must be JSON only (no markdown or commentary).',
        );

        if (!empty($author_policy['banned_phrases'])) {
            $lines[] = 'Do not use these banned phrases: ' . implode(', ', $author_policy['banned_phrases']) . '.';
        }

        return implode("\n", $lines);
    }

    /**
     * Build draft user prompt
     */
    private function build_draft_user_prompt($framework_brief, $planner_brief, $citations, $instructions, $core_settings, $author_policy) {
        $author_policy = $this->sanitize_author_policy($author_policy);
        $prompt = array();

        $prompt[] = 'Editorial Planner Output (read-only):';
        $prompt[] = wp_json_encode($planner_brief, JSON_PRETTY_PRINT);
        $prompt[] = '';
        $prompt[] = 'Framework Builder Output (read-only):';
        $prompt[] = wp_json_encode($framework_brief, JSON_PRETTY_PRINT);
        $prompt[] = '';

        $prompt[] = 'Verified Citations (use numeric markers [1], [2], etc. and do not invent new sources):';
        if (!empty($citations)) {
            foreach ($citations as $citation) {
                $prompt[] = sprintf(
                    '[%d] %s - %s (%s). %s. URL: %s. Snippet: %s',
                    $citation['ref_id'],
                    $citation['lead_author'] ?: 'Unknown Author',
                    $citation['title'],
                    $citation['year'] ?: 'n.d.',
                    $citation['publication'] ?: $citation['organisation'],
                    $citation['url'],
                    $citation['passage_snippet']
                );
            }
        } else {
            $prompt[] = 'No verified citations available. Do not add citations.';
        }

        if (!empty($instructions)) {
            $prompt[] = '';
            $prompt[] = 'Additional Instructions:';
            $prompt[] = $instructions;
        }

        $prompt[] = '';
        $prompt[] = 'Writing Constraints (mandatory):';
        $prompt[] = '- No rhetorical binaries ("not X but Y").';
        $prompt[] = '- No uniform logic arcs.';
        $prompt[] = '- No listicle framing.';
        $prompt[] = '- No punchline one-liners.';
        $prompt[] = '- No over-smoothed transitions.';
        if ($author_policy['disallow_em_dash']) {
            $prompt[] = '- No em dashes (—) or double hyphens (--).';
        } else {
            $prompt[] = '- Em-dash usage per brand profile: ' . $this->get_em_dash_guidance($core_settings['brand_profile'] ?? 'Brand A (FSI)');
        }
        $prompt[] = '- Every paragraph: at least one sentence >20 words and one sentence <8 words.';
        $prompt[] = '- At least one contradiction or self-correction per 500 words.';
        $prompt[] = '- Paragraphs broken by thought, not template.';
        $prompt[] = '- Preserve ambiguity, temporal drift, unresolved tension.';
        $prompt[] = '- Observational, reported, investigative stance.';
        $prompt[] = '- Do not use generic section headings: "Overview", "Conclusion", or "Summary".';
        if ($author_policy['disallow_tidy_conclusion']) {
            $prompt[] = '- No tidy conclusions or definitive resolution.';
        }
        if ($author_policy['disallow_first_person']) {
            $prompt[] = '- No first-person pronouns (I, we, our, us).';
        }
        $prompt[] = '- No fabricated data, names, or quotes.';
        $prompt[] = '- No inferred academic claims.';
        $prompt[] = '- All claims must be attributable or framed with humility.';
        $prompt[] = '- Do not add SEO keywords or optimize copy.';
        $prompt[] = sprintf('- Target word count range: %d-%d words.', intval($author_policy['min_words']), intval($author_policy['max_words']));
        if (!empty($author_policy['banned_phrases'])) {
            $prompt[] = '- Banned phrases: ' . implode(', ', $author_policy['banned_phrases']) . '.';
        }

        $prompt[] = '';
        $prompt[] = 'Output JSON schema:';
        $prompt[] = '{"blocks":[{"type":"heading","level":2,"content":""},{"type":"paragraph","content":""}],"citations_used":[1,2],"word_count":1234}';
        $prompt[] = 'Use headings and paragraphs only. Insert citation markers inline like [1].';

        return implode("\n", $prompt);
    }

    /**
     * Build abstract system prompt
     */
    private function build_abstract_system_prompt() {
        return implode("\n", array(
            'You are the Author Agent (Phase 2). This is extractive and analytical, not creative.',
            'No em dashes. No rhetorical binaries. No listicle framing.',
            'No additional insight beyond the provided draft.',
            'Use formal business/academic language only.',
            'Output JSON only, following the schema exactly.',
        ));
    }

    /**
     * Build abstract user prompt
     */
    private function build_abstract_user_prompt($draft_content) {
        $prompt = array();
        $prompt[] = 'Draft Article:';
        $prompt[] = $this->strip_tags_preserve_whitespace($draft_content);
        $prompt[] = '';
        $prompt[] = 'Abstract Constraints:';
        $prompt[] = '- Overview: 2-3 sentences.';
        $prompt[] = '- Key Points: 3-6 bullets.';
        $prompt[] = '- Context: 3 sentences or fewer.';
        $prompt[] = '- Application: 3 sentences or fewer.';
        $prompt[] = '- Editorial Summary: 100-200 words.';
        $prompt[] = '- Meta Summary: 160 characters or fewer.';
        $prompt[] = 'Output JSON schema:';
        $prompt[] = '{"overview":"","key_points":[""],"context":"","application":"","keywords":[""],"editorial_summary":"","meta_summary":""}';

        return implode("\n", $prompt);
    }

    /**
     * Validate abstract output
     */
    private function validate_abstract($abstract) {
        $warnings = array();
        $errors = array();

        $required = array('overview', 'key_points', 'context', 'application', 'keywords', 'editorial_summary', 'meta_summary');
        foreach ($required as $key) {
            if (!array_key_exists($key, $abstract)) {
                $errors[] = 'Missing field: ' . $key;
            }
        }

        $text_blob = wp_json_encode($abstract);
        if (preg_match('/\x{2014}/u', $text_blob)) {
            $warnings[] = 'Abstract output includes em dash characters.';
        }
        if ($this->contains_rhetorical_binary($text_blob)) {
            $warnings[] = 'Abstract output includes a rhetorical binary ("not X but Y").';
        }
        if ($this->contains_listicle_framing($text_blob)) {
            $warnings[] = 'Abstract output may include listicle framing.';
        }

        $overview_sentences = $this->count_sentences($abstract['overview'] ?? '');
        if ($overview_sentences < 2 || $overview_sentences > 3) {
            $warnings[] = 'Overview should be 2-3 sentences.';
        }
        $context_sentences = $this->count_sentences($abstract['context'] ?? '');
        if ($context_sentences > 3) {
            $warnings[] = 'Context should be 3 sentences or fewer.';
        }
        $application_sentences = $this->count_sentences($abstract['application'] ?? '');
        if ($application_sentences > 3) {
            $warnings[] = 'Application should be 3 sentences or fewer.';
        }
        $key_points_count = is_array($abstract['key_points'] ?? null) ? count($abstract['key_points']) : 0;
        if ($key_points_count < 3 || $key_points_count > 6) {
            $warnings[] = 'Key Points should have 3-6 bullets.';
        }
        $editorial_word_count = str_word_count($abstract['editorial_summary'] ?? '');
        if ($editorial_word_count > 0 && ($editorial_word_count < 100 || $editorial_word_count > 200)) {
            $warnings[] = 'Editorial Summary should be 100-200 words.';
        }
        $meta_summary = $abstract['meta_summary'] ?? '';
        if (!empty($meta_summary) && strlen($meta_summary) > 160) {
            $warnings[] = 'Meta Summary should be 160 characters or fewer.';
        }

        return array(
            'warnings' => $warnings,
            'errors' => $errors,
        );
    }

    /**
     * Validate draft against core constraints
     */
    private function validate_draft_constraints($blocks, $core_settings, $author_policy = array()) {
        $warnings = array();
        $errors = array();
        $author_policy = $this->sanitize_author_policy($author_policy);

        if (empty($blocks) || !is_array($blocks)) {
            return array('warnings' => $warnings, 'errors' => $errors);
        }

        $text = $this->blocks_to_text($blocks);
        $word_count = str_word_count($text);

        if ($word_count > 0 && $word_count < intval($author_policy['min_words'])) {
            $warnings[] = sprintf('Draft is below policy minimum word count (%d < %d).', $word_count, intval($author_policy['min_words']));
        }
        if ($word_count > intval($author_policy['max_words'])) {
            $warnings[] = sprintf('Draft exceeds policy maximum word count (%d > %d).', $word_count, intval($author_policy['max_words']));
        }

        $em_dash_count = preg_match_all('/\x{2014}|--/u', $text);
        if ($author_policy['disallow_em_dash']) {
            if ($em_dash_count > 0) {
                $warnings[] = sprintf('Em dash usage violates policy (%d detected).', $em_dash_count);
            }
        } else {
            $em_dash_limit = $this->get_em_dash_limit($core_settings['brand_profile'] ?? 'Brand A (FSI)', $word_count);
            if ($em_dash_limit > 0 && $em_dash_count > $em_dash_limit) {
                $warnings[] = sprintf('Em dash usage exceeds guidance (%d used, max %d for this length).', $em_dash_count, $em_dash_limit);
            }
        }

        if ($author_policy['disallow_first_person'] && $this->contains_first_person($text)) {
            $warnings[] = 'First-person perspective detected but disallowed by policy.';
        }

        $banned_hits = $this->find_banned_phrase_hits($text, $author_policy['banned_phrases']);
        foreach ($banned_hits as $hit) {
            $warnings[] = 'Banned phrase detected: ' . $hit;
        }

        $paragraphs = $this->extract_paragraphs_from_blocks($blocks);
        $violations = 0;
        foreach ($paragraphs as $paragraph) {
            $sentence_lengths = $this->get_sentence_word_counts($paragraph);
            if (!$this->has_sentence_length_between($sentence_lengths, 21, 999) || !$this->has_sentence_length_between($sentence_lengths, 1, 7)) {
                $violations++;
            }
        }
        if ($violations > 0) {
            $warnings[] = sprintf('Paragraph sentence-length constraint failed in %d paragraph(s).', $violations);
        }

        $required_contradictions = $this->get_required_contradictions($word_count);
        $contradiction_count = $this->count_contradictions($text);
        if ($required_contradictions > 0 && $contradiction_count < $required_contradictions) {
            $warnings[] = sprintf('Contradiction/self-correction density is low (%d found, expected %d).', $contradiction_count, $required_contradictions);
        }

        if ($author_policy['disallow_rhetorical_binaries'] && $this->contains_rhetorical_binary($text)) {
            $warnings[] = 'Rhetorical binary detected ("not X but Y").';
        }
        if ($author_policy['disallow_listicle_framing'] && $this->contains_listicle_framing($text)) {
            $warnings[] = 'Listicle-style framing detected.';
        }
        $generic_heading_hits = $this->find_disallowed_generic_headings($blocks);
        if (!empty($generic_heading_hits)) {
            $warnings[] = 'Generic section headings detected: ' . implode(', ', $generic_heading_hits) . '.';
        }
        if ($author_policy['disallow_tidy_conclusion'] && $this->has_tidy_conclusion($blocks)) {
            $warnings[] = 'Draft may include a tidy conclusion (avoid definitive wrap-ups).';
        }

        return array(
            'warnings' => $warnings,
            'errors' => $errors,
        );
    }

    /**
     * Validate citations in draft
     */
    private function validate_citations_in_text($text, $citations) {
        $warnings = array();
        $errors = array();

        $markers = $this->extract_citation_markers($text);
        $max_ref = count($citations);

        if (empty($markers) && !empty($citations)) {
            $warnings[] = 'No citation markers found in draft output.';
        }

        foreach ($markers as $marker) {
            if ($marker < 1 || $marker > $max_ref) {
                $errors[] = 'Citation marker [' . $marker . '] does not match any verified citation.';
            }
        }

        return array(
            'warnings' => $warnings,
            'errors' => $errors,
        );
    }

    /**
     * Convert blocks to text for validation
     */
    private function blocks_to_text($blocks) {
        $parts = array();
        foreach ($blocks as $block) {
            if (!empty($block['content'])) {
                $parts[] = $block['content'];
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Parse draft content into blocks
     */
    private function parse_draft_into_blocks($draft_content) {
        $blocks = array();

        if (strpos($draft_content, '<!-- wp:') !== false) {
            $parsed = parse_blocks($draft_content);
            foreach ($parsed as $block) {
                if ($block['blockName'] === 'core/heading') {
                    $blocks[] = array(
                        'type' => 'heading',
                        'level' => $block['attrs']['level'] ?? 2,
                        'content' => wp_strip_all_tags(render_block($block)),
                    );
                } elseif ($block['blockName'] === 'core/paragraph') {
                    $blocks[] = array(
                        'type' => 'paragraph',
                        'content' => wp_strip_all_tags(render_block($block)),
                    );
                } else {
                    $text = wp_strip_all_tags(render_block($block));
                    if (!empty($text)) {
                        $blocks[] = array(
                            'type' => 'paragraph',
                            'content' => $text,
                        );
                    }
                }
            }

            return $blocks;
        }

        $text = $this->strip_tags_preserve_whitespace($draft_content);
        $paragraphs = preg_split('/\n\s*\n/', $text);
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!$paragraph) {
                continue;
            }
            $blocks[] = array(
                'type' => 'paragraph',
                'content' => $paragraph,
            );
        }

        return $blocks;
    }

    /**
     * Build footnotes list from citations
     */
    private function build_footnotes($citations) {
        $entries = array();

        foreach ($citations as $citation) {
            $date_label = $this->format_citation_date_for_display($citation['date'] ?? ($citation['year'] ?? ''));
            if ($date_label === '' && !empty($citation['year'])) {
                $date_label = (string) $citation['year'];
            }

            $entry_parts = array();
            if (!empty($citation['lead_author'])) {
                $entry_parts[] = trim($citation['lead_author']) . '.';
            }
            if ($date_label !== '') {
                $entry_parts[] = '(' . $date_label . ').';
            }
            if (!empty($citation['title'])) {
                $entry_parts[] = trim($citation['title']) . '.';
            }

            $source = trim((string) ($citation['publication'] ?? ''));
            if ($source === '') {
                $source = trim((string) ($citation['organisation'] ?? ''));
            }
            if ($source !== '') {
                $entry_parts[] = $source . '.';
            }

            $entry = trim(implode(' ', $entry_parts));
            if (!empty($citation['url'])) {
                $entry .= ' ' . $citation['url'];
            }

            if (!empty($entry)) {
                $entries[] = $entry;
            }
        }

        sort($entries, SORT_NATURAL | SORT_FLAG_CASE);

        return $entries;
    }

    /**
     * Validate footnote readiness
     */
    private function validate_footnotes($citations) {
        $warnings = array();
        $missing_apa = 0;
        $missing_url = 0;

        foreach ($citations as $citation) {
            $apa = $citation['apa_string'] ?? '';
            if (empty($apa) || $apa === 'details_unavailable') {
                $missing_apa++;
            }
            if (empty($citation['url'])) {
                $missing_url++;
            }
        }

        if ($missing_apa > 0) {
            $warnings[] = sprintf('APA details missing for %d citation(s).', $missing_apa);
        }
        if ($missing_url > 0) {
            $warnings[] = sprintf('Source URL missing for %d citation(s).', $missing_url);
        }

        return $warnings;
    }

    /**
     * Find citation by reference id
     */
    private function find_citation_by_ref($citations, $ref_id) {
        foreach ($citations as $citation) {
            if ((int) $citation['ref_id'] === (int) $ref_id) {
                return $citation;
            }
        }
        return null;
    }

    /**
     * Format pullquote citation
     */
    private function format_pullquote_citation($citation) {
        $parts = array_filter(array(
            $citation['lead_author'],
            $citation['publication'],
            $this->format_citation_date_for_display($citation['date'] ?? ($citation['year'] ?? '')),
        ));

        return implode(', ', $parts);
    }

    private function get_disallowed_heading_tokens() {
        return array('overview', 'conclusion', 'summary');
    }

    private function normalize_heading_token($value) {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z\s]/', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function is_disallowed_generic_heading($heading) {
        $token = $this->normalize_heading_token($heading);
        if ($token === '') {
            return false;
        }

        return in_array($token, $this->get_disallowed_heading_tokens(), true);
    }

    private function find_disallowed_generic_headings($blocks) {
        $hits = array();
        foreach ((array) $blocks as $block) {
            if (($block['type'] ?? '') !== 'heading') {
                continue;
            }

            $content = trim((string) ($block['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            if ($this->is_disallowed_generic_heading($content)) {
                $hits[] = $content;
            }
        }

        return array_values(array_unique($hits));
    }

    private function normalize_draft_blocks($blocks) {
        $normalized = array();

        foreach ((array) $blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $content = trim((string) ($block['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            if ($type === 'heading' && $this->is_disallowed_generic_heading($content)) {
                continue;
            }

            $normalized[] = $block;
        }

        return $normalized;
    }

    private function format_citation_date_for_display($raw_date) {
        $raw = trim((string) $raw_date);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}$/', $raw) === 1) {
            $year = substr($raw, 0, 4);
            $month = substr($raw, 5, 2);
            return sprintf('%02d/%04d', (int) $month, (int) $year);
        }

        if (preg_match('/^\d{4}$/', $raw) === 1) {
            return $raw;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return '';
        }

        $has_day = preg_match('/\d{4}-\d{2}-\d{2}/', $raw) === 1
            || preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/', $raw) === 1
            || preg_match('/\b\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}\b/', $raw) === 1;

        if (!$has_day) {
            return gmdate('m/Y', $timestamp);
        }

        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $is_us_locale = stripos((string) $locale, 'en_US') === 0;
        return gmdate($is_us_locale ? 'm/d/Y' : 'd/m/Y', $timestamp);
    }

    /**
     * Extract numeric citation markers
     */
    private function extract_citation_markers($text) {
        preg_match_all('/\[(\d+)\]/', $text, $matches);
        $markers = array_map('intval', $matches[1] ?? array());
        return array_values(array_unique($markers));
    }

    /**
     * Check paragraph for numeric claim
     */
    private function paragraph_has_numeric_claim($paragraph) {
        return preg_match('/\b\d{1,3}(?:[\.,]\d+)?%?\b/', $paragraph) === 1;
    }

    /**
     * Extract sentence containing a number
     */
    private function extract_sentence_with_number($paragraph) {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($paragraph));
        foreach ($sentences as $sentence) {
            if ($this->paragraph_has_numeric_claim($sentence)) {
                return trim($sentence);
            }
        }

        return null;
    }

    /**
     * Remove HTML while preserving whitespace
     */
    private function strip_tags_preserve_whitespace($content) {
        $content = preg_replace('/<\s*br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<\/p>/i', "\n\n", $content);
        $content = wp_strip_all_tags($content);

        return trim($content);
    }

    /**
     * Em dash guidance text
     */
    private function get_em_dash_guidance($brand_profile) {
        if (stripos($brand_profile, 'brand b') !== false) {
            return 'Em-dash usage: max 1 per 300+ words (Brand B).';
        }

        return 'Em-dash usage: max 1 per 1500 words (Brand A).';
    }

    /**
     * Em dash limit count based on brand and length
     */
    private function get_em_dash_limit($brand_profile, $word_count) {
        if ($word_count <= 0) {
            return 0;
        }

        $limit = (stripos($brand_profile, 'brand b') !== false) ? 300 : 1500;
        return (int) ceil($word_count / $limit);
    }

    /**
     * Extract paragraph text from blocks
     */
    private function extract_paragraphs_from_blocks($blocks) {
        $paragraphs = array();
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'paragraph' && !empty($block['content'])) {
                $paragraphs[] = $block['content'];
            }
        }

        return $paragraphs;
    }

    /**
     * Count sentences in text
     */
    private function count_sentences($text) {
        if (!is_string($text) || trim($text) === '') {
            return 0;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', trim($text));
        $sentences = array_filter(array_map('trim', $sentences));
        return count($sentences);
    }

    /**
     * Sentence word counts for a paragraph
     */
    private function get_sentence_word_counts($paragraph) {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($paragraph));
        $counts = array();
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }
            $counts[] = str_word_count($sentence);
        }

        return $counts;
    }

    /**
     * Check for sentence length range
     */
    private function has_sentence_length_between($lengths, $min, $max) {
        foreach ($lengths as $length) {
            if ($length >= $min && $length <= $max) {
                return true;
            }
        }
        return false;
    }

    /**
     * Required contradiction count per word count
     */
    private function get_required_contradictions($word_count) {
        if ($word_count <= 0) {
            return 0;
        }

        return (int) ceil($word_count / 500);
    }

    /**
     * Count contradiction/self-correction markers
     */
    private function count_contradictions($text) {
        $markers = array(
            'however',
            'but',
            'yet',
            'although',
            'though',
            'still',
            'nevertheless',
            'on the other hand',
            'that said',
            'to be fair',
            'on second thought',
            'i might be wrong',
            'i should',
        );

        $count = 0;
        $lower = strtolower($text);
        foreach ($markers as $marker) {
            $count += substr_count($lower, $marker);
        }

        return $count;
    }

    /**
     * Detect rhetorical binaries
     */
    private function contains_rhetorical_binary($text) {
        return preg_match('/\bnot\b[^.]{0,80}\bbut\b/i', $text) === 1;
    }

    /**
     * Detect listicle framing
     */
    private function contains_listicle_framing($text) {
        return preg_match('/\b(top|best)\s+\d+\b|\b\d+\s+(ways|reasons|tips|steps)\b/i', $text) === 1;
    }

    private function contains_first_person($text) {
        return preg_match('/\b(i|we|our|ours|us|my|mine)\b/i', (string) $text) === 1;
    }

    private function find_banned_phrase_hits($text, $banned_phrases) {
        if (!is_array($banned_phrases) || empty($banned_phrases)) {
            return array();
        }

        $hits = array();
        $haystack = strtolower((string) $text);
        foreach ($banned_phrases as $phrase) {
            $phrase = strtolower(trim((string) $phrase));
            if ($phrase === '') {
                continue;
            }
            if (strpos($haystack, $phrase) !== false) {
                $hits[] = $phrase;
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * Detect tidy conclusion markers
     */
    private function has_tidy_conclusion($blocks) {
        $last_heading = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'heading' && !empty($block['content'])) {
                $last_heading = $block['content'];
            }
        }

        if ($last_heading && preg_match('/\b(overview|conclusion|summary|final thoughts)\b/i', $last_heading)) {
            return true;
        }

        $text = $this->blocks_to_text($blocks);
        return preg_match('/\bin conclusion\b|\bto conclude\b/i', $text) === 1;
    }

    /**
     * Update user budget usage
     */
    private function update_budget_usage($user_id, $usage) {
        if (empty($user_id)) {
            return;
        }

        $prompt_tokens = $usage['prompt_tokens'] ?? 0;
        $completion_tokens = $usage['completion_tokens'] ?? 0;
        $total = $prompt_tokens + $completion_tokens;

        if ($total <= 0) {
            return;
        }

        $db = new Dual_GPT_DB_Handler();
        $db->update_token_usage((int) $user_id, $total);
    }

    /**
     * Check budget before LLM usage
     */
    private function check_budget($user_id) {
        if (empty($user_id)) {
            return true;
        }

        $db = new Dual_GPT_DB_Handler();
        $budget = $db->check_user_budget((int) $user_id);
        if (!empty($budget['token_limit']) && !empty($budget['token_used']) && $budget['token_used'] >= $budget['token_limit']) {
            return new WP_Error('budget_exceeded', 'Token budget exceeded.', array('status' => 429));
        }

        return true;
    }
}
