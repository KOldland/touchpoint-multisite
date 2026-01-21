<?php
/**
 * Framework Generator Workers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Framework_Generator_Workers {

    /**
     * Process Phase 1: Foundational Discovery
     */
    public function process_phase1($job_id) {
        $db = new Dual_GPT_DB_Handler();
        $job = $db->get_job($job_id);

        if (!$job || $job['status'] !== 'running') {
            return;
        }

        $input = json_decode($job['input_prompt'], true);
        $session_id = $job['session_id'];

        // Use LLM to generate search queries and fetch articles
        $llm_client = new Dual_GPT_OpenAI_Connector();

        $system_prompt = $db->get_preset('fg-framework-generator')['system_prompt'];
        $user_prompt = $this->build_phase1_prompt($input);

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt),
        );

        $response = $llm_client->create_chat_completion($messages, 'gpt-4o-mini', array(), 'none');

        if (is_wp_error($response)) {
            $db->update_job_status($job_id, 'failed', array('error_message' => $response->get_error_message()));
            return;
        }

        $data = json_decode($response['choices'][0]['message']['content'], true);
        $usage = $llm_client->get_usage($response);

        // Persist raw articles
        $this->persist_raw_articles($session_id, $data['articles'] ?? array());

        // Update job
        $db->update_job_status($job_id, 'completed', array(
            'response_json' => wp_json_encode($data),
            'usage_prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'usage_completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
            'cost_micro' => $llm_client->calculate_cost($job['model'], $response['usage']['prompt_tokens'] ?? 0, $response['usage']['completion_tokens'] ?? 0)['cost_micro'],
        ));

        // Update token usage
        $db->update_token_usage(get_current_user_id(), ($response['usage']['prompt_tokens'] ?? 0) + ($response['usage']['completion_tokens'] ?? 0));

        // Log audit
        $db->insert_audit_log($job_id, 'phase1_completed', array('article_count' => count($data['articles'] ?? array())));

        // Enqueue Phase 2
        $this->enqueue_phase2($session_id, $job_id);
    }

    /**
     * Build Phase 1 prompt
     */
    private function build_phase1_prompt($input) {
        $idea = $input['article_idea'];
        $focus = $input['focus'] ?? array();

        // Sanitize user inputs to prevent prompt injection
        $title = isset($idea['title']) ? sanitize_text_field($idea['title']) : '';
        $description = isset($idea['short_description']) ? sanitize_textarea_field($idea['short_description']) : '';
        $seed_keywords = array();
        if (!empty($idea['seed_keywords']) && is_array($idea['seed_keywords'])) {
            $seed_keywords = array_filter(array_map('sanitize_text_field', $idea['seed_keywords']));
        }

        $prompt = "Article Idea: {$title}\n";
        $prompt .= "Description: {$description}\n";
        $prompt .= "Keywords: " . implode(', ', $seed_keywords) . "\n\n";

        $broad = array();
        if (!empty($focus['broad']) && is_array($focus['broad'])) {
            $broad = array_filter(array_map('sanitize_text_field', $focus['broad']));
        }
        if (!empty($broad)) {
            $prompt .= "Broad Focus: " . implode(', ', $broad) . "\n";
        }

        $granular = array();
        if (!empty($focus['granular']) && is_array($focus['granular'])) {
            $granular = array_filter(array_map('sanitize_text_field', $focus['granular']));
        }
        if (!empty($granular)) {
            $prompt .= "Granular Focus: " . implode(', ', $granular) . "\n";
        }

        $preferred_sources = array();
        if (!empty($focus['preferred_sources']) && is_array($focus['preferred_sources'])) {
            $preferred_sources = array_filter(array_map('sanitize_text_field', $focus['preferred_sources']));
        }
        if (!empty($preferred_sources)) {
            $prompt .= "Preferred Sources: " . implode(', ', $preferred_sources) . "\n";
        }

        $exclusions = array();
        if (!empty($focus['exclusions']) && is_array($focus['exclusions'])) {
            $exclusions = array_filter(array_map('sanitize_text_field', $focus['exclusions']));
        }
        if (!empty($exclusions)) {
            $prompt .= "Exclusions: " . implode(', ', $exclusions) . "\n";
        }

        $prompt .= "\nFind 12-16 unique articles from diverse domains and source types. Return JSON with articles array containing: title, url, domain, author, date, source_type, snippet, extracted_claims[], keywords[].";

        return $prompt;
    }

    /**
     * Persist raw articles
     */
    private function persist_raw_articles($session_id, $articles) {
        global $wpdb;
        $table = $wpdb->prefix . 'fg_raw_articles';

        foreach ($articles as $article) {
            $result = $wpdb->insert($table, array(
                'id' => wp_generate_uuid4(),
                'session_id' => $session_id,
                'title' => $article['title'] ?? '',
                'url' => $article['url'] ?? '',
                'domain' => $article['domain'] ?? '',
                'author' => $article['author'] ?? '',
                'date' => $article['date'] ?? null,
                'source_type' => $article['source_type'] ?? '',
                'snippet' => $article['snippet'] ?? '',
                'extracted_claims' => wp_json_encode($article['extracted_claims'] ?? array()),
                'keywords' => wp_json_encode($article['keywords'] ?? array()),
            ));

            if ($result === false) {
                error_log("Failed to persist raw article: " . $wpdb->last_error);
            }
        }
    }

    /**
     * Enqueue Phase 2
     */
    private function enqueue_phase2($session_id, $phase1_job_id) {
        $db = new Dual_GPT_DB_Handler();

        $job_data = array(
            'session_id' => $session_id,
            'model' => 'gpt-4o-mini',
            'preset_id' => 'fg-framework-generator',
            'idempotency_key' => 'phase2-' . wp_generate_uuid4(),
        );

        $job_id = $db->insert_job($job_data);
        $db->insert_audit_log($job_id, 'queued', array('phase' => 'deep_dive_validation', 'parent_job' => $phase1_job_id));
    }

    /**
     * Process Phase 2: Deep Dive & Validation
     */
    public function process_phase2($job_id) {
        $db = new Dual_GPT_DB_Handler();
        $job = $db->get_job($job_id);

        if (!$job || $job['status'] !== 'running') {
            return;
        }

        $session_id = $job['session_id'];

        // Get raw articles
        global $wpdb;
        $raw_articles = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fg_raw_articles WHERE session_id = %s ORDER BY created_at DESC LIMIT 16",
                $session_id
            ),
            ARRAY_A
        );

        // Validate and create citations
        $citations = array();
        foreach ($raw_articles as $article) {
            $citation = $this->validate_citation($article);
            if ($citation) {
                $citations[] = $citation;
            }
        }

        // Select top 6-8 high-authority citations
        $selected_citations = $this->select_high_authority_citations($citations);

        // Persist validated citations
        foreach ($selected_citations as $citation) {
            $this->persist_validated_citation($session_id, $job_id, $citation);
        }

        // Update job status to waiting for human
        $db->update_job_status($job_id, 'waiting_for_human');

        // Log audit
        $db->insert_audit_log($job_id, 'phase2_completed', array('citation_count' => count($selected_citations)));
    }

    /**
     * Validate citation with external APIs
     */
    private function validate_citation($article) {
        // Fetch URL to get metadata
        $metadata = $this->fetch_url_metadata($article['url']);

        // Try CrossRef/OpenAlex for academic sources
        if ($this->is_academic_source($article['source_type'])) {
            $api_metadata = $this->fetch_academic_metadata($article['title'], $article['url']);
            $metadata = array_merge($metadata, $api_metadata);
        }

        return array(
            'title' => $metadata['title'] ?? $article['title'],
            'lead_author' => $metadata['lead_author'] ?? $article['author'],
            'publication' => $metadata['publication'] ?? '',
            'organisation' => $metadata['organisation'] ?? $article['domain'],
            'year' => (int) ($metadata['year'] ?? date('Y', strtotime($article['date']))),
            'url' => $article['url'],
            'apa_string' => $metadata['apa_string'] ?? 'details_unavailable',
            'apa_details_available' => !empty($metadata['apa_string']),
            'passage_snippet' => $article['snippet'],
            'type' => $article['source_type'],
            'tier' => $this->determine_tier($article['source_type']),
            'authority_score' => $this->calculate_authority_score($article, $metadata),
            'confidence' => 0.8,
            'sponsored' => false,
        );
    }

    /**
     * Fetch URL metadata
     * 
     * Attempts to retrieve basic metadata from the target URL using WordPress HTTP APIs.
     * Returns an associative array which may contain:
     *  - title
     *  - lead_author
     *  - organisation
     *  - publication
     *  - year
     *  - apa_string
     */
    private function fetch_url_metadata($url) {
        if (empty($url)) {
            return array();
        }

        // Validate URL to prevent SSRF attacks
        $parsed_url = parse_url($url);
        if (!$parsed_url || !isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
            return array();
        }

        // Only allow HTTP and HTTPS schemes
        if (!in_array(strtolower($parsed_url['scheme']), array('http', 'https'), true)) {
            return array();
        }

        // Prevent requests to private/local IP addresses
        $host = $parsed_url['host'];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return array();
            }
        }

        $response = wp_remote_get($url, array(
            'timeout' => 5,
            'redirection' => 3,
        ));

        if (is_wp_error($response)) {
            return array();
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || $body === '') {
            return array();
        }

        $metadata = array();

        // Use DOMDocument for safer HTML parsing
        $dom = new DOMDocument();
        // Suppress warnings from malformed HTML
        $previous_error_level = libxml_use_internal_errors(true);
        
        // Ensure proper UTF-8 encoding before parsing
        $body = mb_convert_encoding($body, 'HTML-ENTITIES', 'UTF-8');
        
        // Load HTML with UTF-8 encoding
        $dom->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Restore error handling
        libxml_clear_errors();
        libxml_use_internal_errors($previous_error_level);

        // Extract title
        $title_tags = $dom->getElementsByTagName('title');
        if ($title_tags->length > 0) {
            $metadata['title'] = trim($title_tags->item(0)->textContent);
        }

        // Extract meta tags
        $meta_tags = $dom->getElementsByTagName('meta');
        foreach ($meta_tags as $meta) {
            $name = $meta->getAttribute('name');
            $property = $meta->getAttribute('property');
            $content = $meta->getAttribute('content');

            if (empty($content)) {
                continue;
            }

            // Check for author information
            if (stripos($name, 'author') !== false || stripos($property, 'author') !== false) {
                $metadata['lead_author'] = trim($content);
            }
        }

        return $metadata;
    }

    /**
     * Fetch academic metadata from CrossRef/OpenAlex
     * 
     * NOTE: This is a placeholder implementation. Full implementation would query
     * CrossRef or OpenAlex APIs for academic citation metadata.
     */
    private function fetch_academic_metadata($title, $url) {
        // Placeholder: In production, this would query CrossRef or OpenAlex APIs
        // Example: https://api.crossref.org/works?query.title=...
        // Example: https://api.openalex.org/works?filter=title.search:...
        return array();
    }

    /**
     * Determine if source is academic
     */
    private function is_academic_source($source_type) {
        return in_array($source_type, array('academic', 'journal', 'conference'));
    }

    /**
     * Determine tier
     */
    private function determine_tier($source_type) {
        $tiers = array(
            'academic' => 'tier1',
            'analyst' => 'tier1',
            'industry' => 'tier2',
            'case_study' => 'tier2',
            'trade' => 'tier3',
        );
        return $tiers[$source_type] ?? 'tier3';
    }

    /**
     * Calculate authority score
     */
    private function calculate_authority_score($article, $metadata) {
        $score = 0.5; // Base score

        // Boost for academic sources
        if ($this->is_academic_source($article['source_type'])) {
            $score += 0.3;
        }

        // Boost for recent content
        if (!empty($article['date']) && strtotime($article['date']) > strtotime('-2 years')) {
            $score += 0.1;
        }

        // Boost for APA availability
        if (!empty($metadata['apa_string'])) {
            $score += 0.1;
        }

        return min($score, 1.0);
    }

    /**
     * Select high-authority citations
     */
    private function select_high_authority_citations($citations) {
        // Sort by authority score
        usort($citations, function($a, $b) {
            return $b['authority_score'] <=> $a['authority_score'];
        });

        // Ensure diversity requirements
        $selected = array();
        $types_needed = array('academic' => 1, 'analyst' => 1, 'industry' => 1, 'case_study' => 1);
        $org_counts = array();

        foreach ($citations as $citation) {
            $type = $citation['type'];
            $org = $citation['organisation'];

            // Check org limit
            if (($org_counts[$org] ?? 0) >= 2) {
                continue;
            }

            // Check type requirements
            if (isset($types_needed[$type]) && $types_needed[$type] > 0) {
                $types_needed[$type]--;
                $selected[] = $citation;
                $org_counts[$org] = ($org_counts[$org] ?? 0) + 1;
            } elseif (count($selected) < 8) {
                $selected[] = $citation;
                $org_counts[$org] = ($org_counts[$org] ?? 0) + 1;
            }

            if (count($selected) >= 8) {
                break;
            }
        }

        return $selected;
    }

    /**
     * Persist validated citation
     */
    private function persist_validated_citation($session_id, $job_id, $citation) {
        global $wpdb;
        $table = $wpdb->prefix . 'fg_validated_citations';

        $result = $wpdb->insert($table, array(
            'id' => wp_generate_uuid4(),
            'session_id' => $session_id,
            'job_id' => $job_id,
            'title' => $citation['title'],
            'lead_author' => $citation['lead_author'],
            'publication' => $citation['publication'],
            'organisation' => $citation['organisation'],
            'year' => $citation['year'],
            'url' => $citation['url'],
            'apa_string' => $citation['apa_string'],
            'apa_details_available' => $citation['apa_details_available'],
            'passage_snippet' => $citation['passage_snippet'],
            'type' => $citation['type'],
            'tier' => $citation['tier'],
            'authority_score' => $citation['authority_score'],
            'confidence' => $citation['confidence'],
            'sponsored' => $citation['sponsored'],
        ));

        if ($result === false) {
            error_log("Failed to persist validated citation: " . $wpdb->last_error);
        }
    }

    /**
     * Process Phase 3: Framework Synthesis
     */
    public function process_phase3($job_id) {
        $db = new Dual_GPT_DB_Handler();
        $job = $db->get_job($job_id);

        if (!$job || $job['status'] !== 'running') {
            return;
        }

        $session_id = $job['session_id'];

        // Get approved citations
        global $wpdb;
        $citations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fg_validated_citations WHERE session_id = %s AND approved = 1 ORDER BY authority_score DESC",
                $session_id
            ),
            ARRAY_A
        );

        // Get original input
        $phase1_job = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT input_prompt FROM {$wpdb->prefix}ai_jobs WHERE session_id = %s AND idempotency_key LIKE 'phase1-%' LIMIT 1",
                $session_id
            ),
            ARRAY_A
        );

        // Ensure we have a valid phase 1 job with an input_prompt before decoding
        if (!$phase1_job || !isset($phase1_job['input_prompt'])) {
            $db->update_job_status($job_id, 'failed', array('error_message' => 'Phase 1 job input_prompt not found for session.'));
            return;
        }

        $input = json_decode($phase1_job['input_prompt'], true);

        // Generate framework brief
        $brief = $this->generate_framework_brief($citations, $input);

        // Handle failed brief generation
        if ($brief === null) {
            $db->update_job_status($job_id, 'failed', array('error_message' => 'Failed to generate framework brief'));
            return;
        }

        // Validate schema with retry
        $llm_client = new Dual_GPT_OpenAI_Connector();
        $validator = new Framework_Brief_Validator();
        $validation = $validator->validate_with_retry(wp_json_encode($brief), $llm_client);

        if (!$validation['valid']) {
            $db->update_job_status($job_id, 'failed', array('error_message' => 'Schema validation failed after retries: ' . implode(', ', $validation['errors'])));
            return;
        }

        $brief = $validation['brief'];

        // Score the brief
        $scoring = $this->score_framework_brief($brief);

        // Persist brief
        $brief_id = $this->persist_framework_brief($session_id, $brief, $scoring);

        // Update job
        $db->update_job_status($job_id, 'completed', array(
            'response_json' => wp_json_encode($brief),
        ));

        // Log audit
        $db->insert_audit_log($job_id, 'phase3_completed', array('brief_id' => $brief_id));
    }

    /**
     * Generate framework brief using LLM
     */
    private function generate_framework_brief($citations, $input) {
        $llm_client = new Dual_GPT_OpenAI_Connector();
        $db = new Dual_GPT_DB_Handler();

        $preset = $db->get_preset('fg-framework-generator');
        $system_prompt = $preset['system_prompt'];
        $user_prompt = $this->build_phase3_prompt($citations, $input);

        $messages = array(
            array('role' => 'system', 'content' => $system_prompt),
            array('role' => 'user', 'content' => $user_prompt),
        );

        $response = $llm_client->create_chat_completion($messages, 'gpt-4o-mini', array(), 'none');

        if (is_wp_error($response)) {
            return null;
        }

        $content = $response['choices'][0]['message']['content'];
        $usage = $response['usage'];

        // Update job with usage
        $db->update_token_usage(get_current_user_id(), ($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0));

        return json_decode($content, true);
    }

    /**
     * Build Phase 3 prompt
     */
    private function build_phase3_prompt($citations, $input) {
        $prompt = "Approved Citations:\n";
        foreach ($citations as $citation) {
            // Sanitize citation data to prevent prompt injection
            $title = isset($citation['title']) ? sanitize_text_field($citation['title']) : '';
            $type = isset($citation['type']) ? sanitize_text_field($citation['type']) : '';
            // Use sanitize_textarea_field for snippets to preserve formatting
            $snippet = isset($citation['passage_snippet']) ? sanitize_textarea_field($citation['passage_snippet']) : '';
            $prompt .= "- {$title} ({$type}) - {$snippet}\n";
        }

        $article_title = isset($input['article_idea']['title']) ? sanitize_text_field($input['article_idea']['title']) : '';
        $article_description = isset($input['article_idea']['short_description']) ? sanitize_textarea_field($input['article_idea']['short_description']) : '';

        $prompt .= "\nArticle Idea: {$article_title}\n";
        $prompt .= "Description: {$article_description}\n";

        $prompt .= "\nGenerate the final Research Brief JSON with required sections: title, overview, context, application, observations, key_themes, citations, writer_guidance.";

        return $prompt;
    }

    /**
     * Score framework brief
     */
    private function score_framework_brief($brief) {
        // Simplified scoring
        $score = 0.7; // Base score

        // Boost for citations - ensure array exists
        $citations = (isset($brief['citations']) && is_array($brief['citations'])) ? $brief['citations'] : array();
        if (count($citations) >= 3) {
            $score += 0.1;
        }

        // Boost for observations with evidence - ensure array exists
        $observations = (isset($brief['observations']) && is_array($brief['observations'])) ? $brief['observations'] : array();
        $evidence_count = 0;
        foreach ($observations as $obs) {
            if (!empty($obs['evidence'])) {
                $evidence_count++;
            }
        }
        if ($evidence_count >= 3) {
            $score += 0.1;
        }

        return array(
            'total_score' => min($score, 1.0),
            'quality_level' => $score >= 0.9 ? 'excellent' : ($score >= 0.75 ? 'good' : 'fair'),
            'is_publishable' => $score >= 0.6,
        );
    }

    /**
     * Persist framework brief
     */
    private function persist_framework_brief($session_id, $brief, $scoring) {
        global $wpdb;
        $table = $wpdb->prefix . 'fg_briefs';

        $brief_id = wp_generate_uuid4();

        $result = $wpdb->insert($table, array(
            'id' => $brief_id,
            'session_id' => $session_id,
            'article_idea' => wp_json_encode($brief['article_idea'] ?? array()),
            'title' => $brief['title'],
            'overview' => $brief['overview'],
            'context' => $brief['context'],
            'application' => wp_json_encode($brief['application']),
            'observations' => wp_json_encode($brief['observations']),
            'key_themes' => wp_json_encode($brief['key_themes']),
            'citations' => wp_json_encode($brief['citations']),
            'writer_guidance' => wp_json_encode($brief['writer_guidance']),
            'metadata' => wp_json_encode(array()),
            'scoring' => wp_json_encode($scoring),
        ));

        if ($result === false) {
            error_log("Failed to persist framework brief: " . $wpdb->last_error);
            return new WP_Error('db_insert_error', 'Failed to persist framework brief', array('db_error' => $wpdb->last_error));
        }

        return $brief_id;
    }
}