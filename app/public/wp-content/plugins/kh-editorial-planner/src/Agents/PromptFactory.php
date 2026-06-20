<?php

namespace KH\Planner\Agents;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PromptFactory
 * 
 * Centralized location for all Editorial Planner prompts.
 * This keeps the logic (Orchestrator) and the strategy (Prompts) separate.
 */
class PromptFactory {

    /**
     * Build the prompt for Phase 1 (Discovery).
     *
     * @param string $topic            The main topic.
     * @param int    $focus_level       Focus level (0-100).
     * @param string $context_json      Research context JSON.
     * @param string $pillar            Optional editorial pillar name.
     * @param string $audience_context  Optional audience profile context.
     * @param array  $keywords          Optional array of keywords related to the pillar.
     * @return string
     */
    public static function get_phase1_prompt( $topic, $focus_level, $context_json, $pillar = '', $audience_context = '', $keywords = [] ) {
        $blocks = [
            'Act as a Research & Insights Lead conducting Phase 1 (Discovery) of a content-aligned B2B research workflow.',
        ];

        if ( $pillar ) {
            $blocks[] = "EDITORIAL PILLAR: {$pillar}. Focus research specifically on this pillar within the broader topic. Tailor trend identification, keyword selection, and content gap analysis to this pillar.";
        }
        if ( $audience_context ) {
            $blocks[] = "AUDIENCE PROFILE:\n{$audience_context}";
        }
        if ( ! empty( $keywords ) ) {
            $blocks[] = "KEYWORD CONTEXT: Focus research around these keywords: " . implode( ', ', $keywords ) . ".";
        }

        $blocks[] = "Topic: $topic";
        $blocks[] = "Focus Level: $focus_level (0-100)";
        $blocks[] = 'Research Inputs (JSON):';
        $blocks[] = $context_json;
        $blocks[] = 'CRITICAL: Use the internal_coverage data to identify content gaps. Prioritize trends and subtopics that have high external demand but LOW internal coverage (0-1 hits).';
        $blocks[] = 'Objective: Analyze inputs to extract distinct trends with insight points and citations.';
        $blocks[] = 'CRITICAL INSTRUCTION ON CITATIONS: The Research Inputs JSON above contains SERP results with real URLs and snippets from Google search. You MUST use these SERP results to populate your citations. For each trend, include at least 6-8 citations from the SERP data that are most relevant. Each citation MUST be a structured object with: title, url (the actual link from the SERP result), and source_type (one of: "industry", "news", "research", "academic", "vendor"). DO NOT fabricate citations. If the SERP results do not contain enough relevant citations for a trend, you may supplement with your training knowledge, but ONLY with real, verifiable sources — and set url to "" if you do not have a URL. Always prefer citations with real URLs from the SERP data. Aim for at least 6-8 citations per trend.';
        $blocks[] = 'SOURCE DIVERSITY: Across all trends, avoid reusing the same source URL repeatedly. Each trend should draw from a distinct set of sources to ensure broad coverage. If the same URL appears in multiple citation lists, replace it with a different source from the SERP data.';
        $blocks[] = 'ALL CANDIDATE SOURCES: Include a top-level field "all_candidate_sources" — an array of EVERY unique source URL from the SERP data, even those not used as primary citations. Each entry must have: url, title (from SERP snippet), and relevance_indicator (brief note on what this source covers). This preserves all research for later phases.';
        $blocks[] = 'Return ONLY valid JSON: {"executive_summary":"", "all_candidate_sources":[{"url":"","title":"","relevance_indicator":""}], "trends":[{"title":"", "insight_points":[], "why_it_matters":"", "citations":[{"title":"", "url":"", "source_type":""}]}], "candidate_keywords":[]}';

        return implode( "\n", $blocks );
    }

    /**
     * Build the prompt for Phase 2 (Qualification).
     *
     * @param string $topic            The main topic.
     * @param string $phase1_summary   Phase 1 executive summary.
     * @param string $metrics_json     Keyword metrics JSON.
     * @param string $pillar           Optional editorial pillar name.
     * @param string $audience_context Optional audience profile context.
     * @return string
     */
    public static function get_phase2_prompt( $topic, $phase1_summary, $metrics_json, $pillar = '', $audience_context = '' ) {
        $blocks = [
            'Act as a Strategic Keyword Analyst.',
        ];

        if ( $pillar ) {
            $blocks[] = "EDITORIAL PILLAR: {$pillar}. Prioritise keywords that serve this pillar within the broader topic.";
        }
        if ( $audience_context ) {
            $blocks[] = "AUDIENCE PROFILE:\n{$audience_context}";
        }

        $blocks[] = "Topic: $topic";
        $blocks[] = 'Phase 1 Context:';
        $blocks[] = $phase1_summary;
        $blocks[] = 'Keyword Metrics (JSON):';
        $blocks[] = $metrics_json;
        $blocks[] = 'Objective: Prioritize these keywords based on strategic alignment and demand.';
        $blocks[] = 'Return ONLY valid JSON: {"ranked_keywords":[{"keyword":"", "priority_score":0.0, "reason":""}]}';

        return implode( "\n", $blocks );
    }

    /**
     * Build the prompt for Phase 3 (Deep Dive / Topic Framing).
     *
     * @param string $topic                The main topic.
     * @param string $phase1_summary       Phase 1 executive summary.
     * @param string $phase2_ranked_keywords Phase 2 ranked keywords JSON.
     * @param string $pillar               Optional editorial pillar name.
     * @param string $audience_context     Optional audience profile context.
     * @return string
     */
    public static function get_phase3_prompt( $topic, $phase1_summary, $phase2_ranked_keywords, $pillar = '', $audience_context = '' ) {
        $blocks = [
            'Act as a Research & Insights Lead conducting Phase 3 (Deep Dive) of a B2B research workflow.',
        ];

        if ( $pillar ) {
            $blocks[] = "EDITORIAL PILLAR: {$pillar}. Deep-dive into subtopics specifically within this pillar.";
        }
        if ( $audience_context ) {
            $blocks[] = "AUDIENCE PROFILE:\n{$audience_context}";
        }

        $blocks[] = "Topic: $topic";
        $blocks[] = 'Phase 1 Trends Summary:';
        $blocks[] = $phase1_summary;
        $blocks[] = 'Phase 2 Qualified Keywords:';
        $blocks[] = $phase2_ranked_keywords;
        $blocks[] = 'Objective: Drill into the most strategically important subtopics. Deliver editorially actionable insights for article development.';
        $blocks[] = 'For each subtopic, provide: Why it matters, Key findings, and suggested Keywords.';
        $blocks[] = 'CRITICAL INSTRUCTION ON CITATIONS: For each prioritized topic, include a "citations" array with real, verifiable sources. Each citation MUST be a structured object with: title, url (if known), source_type (industry/news/research/academic/vendor), and a relevance_note explaining why it supports this topic. If you do not have a real URL, set url to "" — do not fake URLs. Aim for at least 6-8 citations per topic from your training knowledge, prioritizing well-known industry reports, analyst publications, and authoritative sources.';
        $blocks[] = 'Return ONLY valid JSON: {"article_summary": "", "prioritized_topics": [{"topic": "", "why_now": "", "key_findings": [""], "keywords": [""], "citations":[{"title":"","url":"","source_type":"","relevance_note":""}]}]}';

        return implode( "\n", $blocks );
    }

    /**
     * Build the prompt for Phase 4 (Validation) with citation verification context.
     *
     * @param string $topic                The main topic.
     * @param string $phase3_topics        Phase 3 prioritized topics JSON.
     * @param string $verification_context Citation verification context JSON.
     * @param string $pillar               Optional editorial pillar name.
     * @param string $audience_context     Optional audience profile context.
     * @return string
     */
    public static function get_phase4_prompt( $topic, $phase3_topics, $verification_context = '', $pillar = '', $audience_context = '' ) {
        $parts = [
            'Act as a Fact-Checker and Editorial Validator.',
        ];

        if ( $pillar ) {
            $parts[] = "EDITORIAL PILLAR: {$pillar}. Validate topics against this pillar — discard any that do not serve the pillar strategy.";
        }
        if ( $audience_context ) {
            $parts[] = "AUDIENCE PROFILE:\n{$audience_context}";
        }

        $parts[] = "Topic: $topic";
        $parts[] = 'Proposed Article Topics (JSON):';
        $parts[] = $phase3_topics;

        if ( $verification_context ) {
            $decoded = json_decode( $verification_context, true );
            $count   = $decoded['verified_citations_count'] ?? 0;
            $unverified_count = $decoded['unverified_citations_count'] ?? 0;
            $parts[] = "Verified Citation Context: {$count} citations have been checked via CrossRef/OpenAlex/URL metadata.";
            $parts[] = "Unverified Citation Context: {$unverified_count} additional citations from Phase 1 trends were not verifiable (no URL to check). These are included as unverified_citations below — they may still be valid industry references.";
            $parts[] = 'Each verified citation includes: title, lead_author, publication, year, APA string, url, source_type, tier (tier1/tier2/tier3), authority_score (0.0–1.0), and confidence.';
            $parts[] = 'Each unverified citation includes: title, url, source_type, confidence (0.1), and note. These should be used with caution.';
            $parts[] = 'Citation Data (JSON):';
            $parts[] = $verification_context;
            $parts[] = 'INSTRUCTIONS: Use the tier and authority_score to weigh each citation. Tier1 (academic/analyst) sources should carry more weight than Tier3 (trade). Discard topics whose supporting citations are all Tier3/low-authority.';
            $parts[] = 'CRITICAL: For each validated topic, populate "supporting_citations" with the relevant citations from the Citation Data above. Prefer verified_citations over unverified_citations. Each citation in supporting_citations MUST include the full citation object with: title, apa (APA format string), url, source_type, tier, and authority_score (for verified) or confidence (for unverified). Assign citations to the topic they most strongly support. If no verified citation directly supports a topic, include the most relevant unverified_citations with "confidence": 0.1.';
        }

        $parts[] = 'CITATION COUNT: Each validated topic MUST have at least 4-6 supporting_citations. Do not limit to 1-2. Distribute the available citations across topics so each topic gets a meaningful set of supporting evidence.';
        $parts[] = 'Objective: Validate these topics against authoritative signals. Discard weak, overlapping, or redundant topics.';
        $parts[] = 'TOPIC COUNT: You MUST return between 3 and 6 validated_topics. If the proposed list is larger than 6, trim rigorously. If smaller than 3, this is fine — quality over quantity. Discard topics that overlap significantly with others, or that lack strong citation support (all Tier3/low-authority).';
        $parts[] = 'Return ONLY valid JSON: {"validation_summary": "", "validated_topics": [{"topic": "", "confidence_score": 0.0, "reason": "", "supporting_citations": [{"title": "", "apa": "", "url": "", "source_type": "", "tier": "", "authority_score": 0.0}]}], "discarded_topics": []}';

        return implode( "\n", $parts );
    }

    /**
     * Build the prompt for the Final Synopsis Generation.
     *
     * @param string $topic                The main topic.
     * @param string $research_context     Full research context JSON.
     * @param string $policy_directives    Exclusion/channel directives.
     * @param string $pillar               Optional editorial pillar name.
     * @param string $audience_context     Optional audience profile context.
     * @return string
     */
    public static function get_final_synopsis_prompt( $topic, $research_context, $policy_directives = '', $pillar = '', $audience_context = '', $synopsis_count = 4 ) {
        $count = in_array( $synopsis_count, [ 1, 4, 8 ] ) ? $synopsis_count : 4;
        $blocks = [
            'Act as a Senior Editorial Planner for a high-authority B2B publication.',
        ];

        if ( $pillar ) {
            $blocks[] = "EDITORIAL PILLAR: {$pillar}. Generate synopses that serve this pillar specifically.";
        }
        if ( $audience_context ) {
            $blocks[] = "AUDIENCE PROFILE:\n{$audience_context}";
        }

        $blocks[] = "Topic: $topic";
        $blocks[] = $policy_directives;
        $blocks[] = 'Comprehensive Research Context (JSON):';
        $blocks[] = $research_context;
        $blocks[] = "Objective: Generate exactly {$count} distinct, high-impact article synopses.";
        $blocks[] = 'Each synopsis MUST include: Compelling Headline, Strategic Summary, 4-6 Key Points, and a set of Target Keywords.';
        $blocks[] = 'Phase 4 data above includes "validated_topics" with "supporting_citations". For each synopsis, include any relevant citations from Phase 4\'s supporting_citations, PLUS any citations referenced in the Phase 1 trends, that support the article topic.';
        $blocks[] = 'CITATION COUNT: Each synopsis MUST include at least 4-6 citations in its "citations" array. Do NOT limit to 1-2 citations. Draw from the full pool of citations available across all phases.';
        $blocks[] = 'CITATION DIVERSITY: Each synopsis should draw from a distinct set of citations. Avoid reusing the same 2-3 citations across all synopses. Distribute the available citations so each article has unique supporting evidence.';
        $blocks[] = 'CRITICAL: Each synopsis MUST include a "citations" array. Each citation in the array MUST have: title, apa (APA format string), url (if available), source_type, and tier.';
        $blocks[] = 'Return ONLY valid JSON: {"synopses": [{"headline": "", "summary": "", "key_points": [""], "keywords": [""], "priority_score": 0.0, "citations": [{"title": "", "apa": "", "url": "", "source_type": "", "tier": ""}]}]}';

        return implode( "\n", $blocks );
    }

    /**
     * Build the prompt for a "Dive Deeper" research action on a specific article.
     *
     * The $needed parameter is the number of fresh citations to request from the
     * LLM. This should always be 4 (the target per-article citation count), NOT
     * a delta from existing citations. The LLM is instructed to return EXACTLY
     * $needed citations.
     *
     * @param string $article_headline The article headline to research.
     * @param string $pillar           Optional editorial pillar name.
     * @param string $audience_context Optional audience profile context.
     * @param int    $needed           Number of citations to request (default 4).
     * @return string
     */
    public static function get_research_dive_prompt( $article_headline, $pillar = '', $audience_context = '', $needed = 4 ) {
        $needed = max( 1, (int) $needed );

        $parts = [
            'Act as a B2B Research Analyst finding citations for a specific article.',
        ];

        if ( $pillar ) {
            $parts[] = "EDITORIAL PILLAR: {$pillar}.";
        }
        if ( $audience_context ) {
            $parts[] = "AUDIENCE PROFILE:\n{$audience_context}";
        }

        $parts[] = "ARTICLE: {$article_headline}";
        $parts[] = "TARGET CITATIONS: You MUST return EXACTLY {$needed} distinct, real citations to supplement this article's existing references. NO FEWER than {$needed}.";
        $parts[] = 'RECENCY: Prioritize sources published within the last 24 months.';
        $parts[] = 'SOURCE MIX: Aim for a mix of industry reports, analyst publications, and news sources. At least 1 should be an analyst or research source.';
        $parts[] = 'CRITICAL INSTRUCTIONS:';
        $parts[] = '1. Each citation MUST have: title, source_type (industry/news/research/academic), url (real URL if available, or "" if unknown), publication_date (if known), relevance_note.';
        $parts[] = '2. You MUST return ALL ' . $needed . ' citations in the citations array. Do NOT return fewer.';
        $parts[] = '3. Prefer well-known industry reports, analyst publications, and authoritative sources with REAL URLs.';
        $parts[] = '4. If you cannot find ' . $needed . ' high-quality citations, return the best ' . $needed . ' you can find rather than fewer.';
        $parts[] = 'EXAMPLE STRUCTURE:';
        $parts[] = '{"article_headline": "Example Headline", "citations": [{"title": "Report Title", "source_type": "industry", "url": "https://example.com/report", "publication_date": "2025-03-15", "relevance_note": "This report shows market trends."}]}';
        $parts[] = 'Return ONLY valid JSON with EXACTLY ' . $needed . ' citations in the citations array.';

        return implode( "\n", $parts );
    }

    /**
     * Build the LLM prompt for author generation (draft article creation).
     *
     * Constructs a prompt that asks the LLM to generate a full draft article
     * from the framework output using the specified author profile.
     *
     * @param string $title          Article title/headline.
     * @param array  $citations      Citation data.
     * @param array  $article        Full article data from session meta.
     * @param string $author_profile Author profile key (balanced, authoritative, etc.).
     * @return string The prompt.
     */
    public static function get_author_prompt( $title, $citations, $article, $author_profile = 'balanced' ) {
        $framework = $article['framework']['output'] ?? [];

        $tone_map = [
            'balanced'     => 'Neutral, factual, and professional — suitable for a general B2B audience.',
            'authoritative'=> 'Confident, data-driven, and definitive — positions the writer as a thought leader.',
            'conversational'=> 'Approachable, engaging, and relatable — uses plain language and examples.',
            'analytical'   => 'Deeply analytical with technical precision — uses data, models, and frameworks extensively.',
        ];
        $tone = $tone_map[ $author_profile ] ?? $tone_map['balanced'];

        $parts = [
            'Act as an expert B2B writer creating a draft article based on the provided editorial framework.',
            '',
            "Author Profile / Tone: {$tone}",
            '',
            '=== ARTICLE FRAMEWORK ===',
            'Title: ' . ( $framework['title'] ?? $title ),
            '',
            'Overview: ' . ( $framework['overview'] ?? '' ),
            '',
            'Context: ' . ( $framework['context'] ?? '' ),
            '',
            'Intended Reader: ' . ( $framework['application']['intended_reader'] ?? '' ),
            'Use Case: ' . ( $framework['application']['use_case'] ?? '' ),
            '',
            'Key Themes: ' . implode( ', ', $framework['key_themes'] ?? [] ),
            '',
            '=== WRITER GUIDANCE ===',
            'Tone: ' . ( $framework['writer_guidance']['tone'] ?? $tone ),
            'Structure: ' . ( $framework['writer_guidance']['structure'] ?? '' ),
            'Key Messages: ' . implode( '; ', $framework['writer_guidance']['key_messages'] ?? [] ),
            'Target Audience Notes: ' . ( $framework['writer_guidance']['target_audience_notes'] ?? '' ),
            'Citation Usage: ' . ( $framework['writer_guidance']['citation_usage'] ?? '' ),
            '',
            '=== OBSERVATIONS ===',
        ];

        foreach ( $framework['observations'] ?? [] as $obs ) {
            $parts[] = '- ' . ( $obs['headline'] ?? '' );
            $parts[] = '  ' . ( $obs['detail'] ?? '' );
            foreach ( $obs['evidence'] ?? [] as $ev ) {
                $ci = $ev['citation_index'] ?? null;
                if ( is_int( $ci ) && isset( $citations[ $ci ] ) ) {
                    $parts[] = '  [Source: ' . ( $citations[ $ci ]['title'] ?? '' ) . ']';
                }
            }
        }

        $parts[] = '';
        $parts[] = '=== SOURCE CITATIONS ===';
        foreach ( $citations as $i => $c ) {
            $parts[] = '[' . ( $i + 1 ) . '] ' . ( $c['title'] ?? '' ) . ' — ' . ( $c['url'] ?? '' ) . ( ! empty( $c['lead_author'] ) ? ' (' . $c['lead_author'] . ')' : '' );
        }

        $parts[] = '';
        $parts[] = '=== OUTPUT FORMAT ===';
        $parts[] = 'Return ONLY valid JSON with this structure:';
        $parts[] = '{';
        $parts[] = '  "draft": "Full article text with [1], [2] citation markers",';
        $parts[] = '  "word_count": approximate word count,';
        $parts[] = '  "format": "article"';
        $parts[] = '}';
        $parts[] = '';
        $parts[] = 'Use inline citation markers [1], [2] etc. corresponding to the Source Citations above.';
        $parts[] = 'The article should be 800-1200 words and follow the structure outlined in Writer Guidance.';

        return implode( "\n", $parts );
    }
}
