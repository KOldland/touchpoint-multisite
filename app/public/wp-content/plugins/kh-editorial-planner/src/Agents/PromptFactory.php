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
     * @return string
     */
    public static function get_phase1_prompt( $topic, $focus_level, $context_json, $pillar = '', $audience_context = '' ) {
        $blocks = [
            'Act as a Research & Insights Lead conducting Phase 1 (Discovery) of a content-aligned B2B research workflow.',
        ];

        if ( $pillar ) {
            $blocks[] = "EDITORIAL PILLAR: {$pillar}. Focus research specifically on this pillar within the broader topic. Tailor trend identification, keyword selection, and content gap analysis to this pillar.";
        }
        if ( $audience_context ) {
            $blocks[] = "AUDIENCE PROFILE:\n{$audience_context}";
        }

        $blocks[] = "Topic: $topic";
        $blocks[] = "Focus Level: $focus_level (0-100)";
        $blocks[] = 'Research Inputs (JSON):';
        $blocks[] = $context_json;
        $blocks[] = 'CRITICAL: Use the internal_coverage data to identify content gaps. Prioritize trends and subtopics that have high external demand but LOW internal coverage (0-1 hits).';
        $blocks[] = 'Objective: Analyze inputs to extract distinct trends with insight points and citations.';
        $blocks[] = 'Return ONLY valid JSON: {"executive_summary":"", "trends":[{"title":"", "insight_points":[], "why_it_matters":"", "citations":[]}], "candidate_keywords":[]}';

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
        $blocks[] = 'Return ONLY valid JSON: {"article_summary": "", "prioritized_topics": [{"topic": "", "why_now": "", "key_findings": [""], "keywords": [""]}]}';

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
            $parts[] = "Verified Citation Context: {$count} citations have been checked via CrossRef/OpenAlex/URL metadata.";
            $parts[] = 'Each verified citation includes: title, lead_author, publication, year, APA string, source_type, tier (tier1/tier2/tier3), authority_score (0.0–1.0), and confidence.';
            $parts[] = 'Citation Data (JSON):';
            $parts[] = $verification_context;
            $parts[] = 'INSTRUCTIONS: Use the tier and authority_score to weigh each citation. Tier1 (academic/analyst) sources should carry more weight than Tier3 (trade). Discard topics whose supporting citations are all Tier3/low-authority.';
        }

        $parts[] = 'Objective: Validate these topics against authoritative signals. Discard weak or redundant topics.';
        $parts[] = 'Return ONLY valid JSON: {"validation_summary": "", "validated_topics": [{"topic": "", "confidence_score": 0.0, "reason": "", "supporting_citations": []}], "discarded_topics": []}';

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
    public static function get_final_synopsis_prompt( $topic, $research_context, $policy_directives = '', $pillar = '', $audience_context = '' ) {
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
        $blocks[] = 'Objective: Generate 4-6 distinct, high-impact article synopses.';
        $blocks[] = 'Each synopsis MUST include: Compelling Headline, Strategic Summary, 4-6 Key Points, and a set of Target Keywords.';
        $blocks[] = 'Return ONLY valid JSON: {"synopses": [{"headline": "", "summary": "", "key_points": [""], "keywords": [""], "priority_score": 0.0}]}';

        return implode( "\n", $blocks );
    }
}
