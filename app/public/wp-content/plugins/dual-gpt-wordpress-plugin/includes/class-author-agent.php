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

            $draft_result = $this->generate_draft($framework_brief, $planner_brief, $citations, $core_settings, $instructions, $user_id);
            if (is_wp_error($draft_result)) {
                return $draft_result;
            }

            $validation = $this->validate_citations_in_text($draft_result['text'] ?? '', $citations);
            $warnings = array_merge($warnings, $validation['warnings']);
            $validation_errors = array_merge($validation_errors, $validation['errors']);

            return array(
                'mode' => 'draft',
                'output' => $draft_result,
                'warnings' => $warnings,
                'validation_errors' => $validation_errors,
                'citations' => $citations,
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
    private function generate_draft($framework_brief, $planner_brief, $citations, $core_settings, $instructions, $user_id) {
        $llm_client = new \Dual_GPT\Dual_GPT_LLM_Client();
        if (!$llm_client->has_api_key()) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured.', array('status' => 500));
        }

        $system_prompt = $this->build_draft_system_prompt($core_settings);
        $user_prompt = $this->build_draft_user_prompt($framework_brief, $planner_brief, $citations, $instructions);

        $response = $llm_client->call($system_prompt, $user_prompt, array(
            'temperature' => 0.4,
            'max_tokens' => 3000,
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

        $usage = $llm_client->get_usage($response);
        $this->update_budget_usage($user_id, $usage);

        $text = $this->blocks_to_text($data['blocks']);

        return array(
            'blocks' => $data['blocks'],
            'text' => $text,
            'word_count' => str_word_count($text),
            'usage' => $usage,
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

        $system_prompt = $this->build_abstract_system_prompt();
        $user_prompt = $this->build_abstract_user_prompt($draft_content);

        $response = $llm_client->call($system_prompt, $user_prompt, array(
            'temperature' => 0.2,
            'max_tokens' => 1200,
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
        $this->update_budget_usage($user_id, $usage);

        return array(
            'abstract' => $data,
            'usage' => $usage,
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
    private function build_draft_system_prompt($core_settings) {
        $brand_profile = $core_settings['brand_profile'] ?? 'Brand A (FSI)';

        return implode("\n", array(
            'You are the Author Agent. You execute an approved editorial plan and framework without adding new strategy, SEO, or distribution logic.',
            'You must not introduce new citations, entities, or claims beyond provided materials.',
            'Persona: Experienced Analyst / Senior Journalist.',
            'Industry focus: ' . $core_settings['industry_focus'],
            'Audience tier: ' . $core_settings['audience_tier'],
            'Risk tolerance: ' . $core_settings['risk_tolerance'],
            'Brand profile: ' . $brand_profile,
            'Output must be JSON only (no markdown or commentary).',
        ));
    }

    /**
     * Build draft user prompt
     */
    private function build_draft_user_prompt($framework_brief, $planner_brief, $citations, $instructions) {
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
                    '[%d] %s — %s (%s). %s. URL: %s. Snippet: %s',
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
        $prompt[] = '- Em-dash usage per brand profile.';
        $prompt[] = '- Every paragraph: at least one sentence >20 words and one sentence <8 words.';
        $prompt[] = '- At least one contradiction or self-correction per 500 words.';
        $prompt[] = '- Preserve ambiguity, temporal drift, unresolved tension.';
        $prompt[] = '- Observational, reported, investigative stance.';
        $prompt[] = '- No fabricated data, names, or quotes.';
        $prompt[] = '- All claims must be attributable or framed with humility.';

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
        if (strpos($text_blob, '—') !== false) {
            $warnings[] = 'Abstract output includes em dash characters.';
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
            $apa = $citation['apa_string'];
            if (empty($apa) || $apa === 'details_unavailable') {
                $apa = trim(($citation['lead_author'] ? $citation['lead_author'] . '. ' : '') . ($citation['year'] ? '(' . $citation['year'] . '). ' : '') . $citation['title'] . '.');
            }

            $entry = trim($apa);
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
            $citation['year'],
        ));

        return implode(', ', $parts);
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