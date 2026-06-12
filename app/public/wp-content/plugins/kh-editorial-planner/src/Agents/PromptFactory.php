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
     */
    public static function get_phase1_prompt( $topic, $focus_level, $context_json ) {
        return implode( "\n", [
            'Act as a Research & Insights Lead conducting Phase 1 (Discovery) of a content-aligned B2B research workflow.',
            "Topic: $topic",
            "Focus Level: $focus_level (0-100)",
            'Research Inputs (JSON):',
            $context_json,
            'CRITICAL: Use the internal_coverage data to identify content gaps. Prioritize trends and subtopics that have high external demand but LOW internal coverage (0-1 hits).',
            'Objective: Analyze inputs to extract distinct trends with insight points and citations.',
            'Return ONLY valid JSON: {"executive_summary":"", "trends":[{"title":"", "insight_points":[], "why_it_matters":"", "citations":[]}], "candidate_keywords":[]}'
        ] );
    }

    /**
     * Build the prompt for Phase 2 (Qualification).
     */
    public static function get_phase2_prompt( $topic, $phase1_summary, $metrics_json ) {
        return implode( "\n", [
            'Act as a Strategic Keyword Analyst.',
            "Topic: $topic",
            'Phase 1 Context:',
            $phase1_summary,
            'Keyword Metrics (JSON):',
            $metrics_json,
            'Objective: Prioritize these keywords based on strategic alignment and demand.',
            'Return ONLY valid JSON: {"ranked_keywords":[{"keyword":"", "priority_score":0.0, "reason":""}]}'
        ] );
    }

    /**
     * Build the prompt for Phase 3 (Deep Dive / Topic Framing).
     */
    public static function get_phase3_prompt( $topic, $phase1_summary, $phase2_ranked_keywords ) {
        return implode( "\n", [
            'Act as a Research & Insights Lead conducting Phase 3 (Deep Dive) of a B2B research workflow.',
            "Topic: $topic",
            'Phase 1 Trends Summary:',
            $phase1_summary,
            'Phase 2 Qualified Keywords:',
            $phase2_ranked_keywords,
            'Objective: Drill into the most strategically important subtopics. Deliver editorially actionable insights for article development.',
            'For each subtopic, provide: Why it matters, Key findings, and suggested Keywords.',
            'Return ONLY valid JSON: {"article_summary": "", "prioritized_topics": [{"topic": "", "why_now": "", "key_findings": [""], "keywords": [""]}]}'
        ] );
    }

    /**
     * Build the prompt for Phase 4 (Validation) with citation verification context.
     */
    public static function get_phase4_prompt( $topic, $phase3_topics, $verification_context = '' ) {
        $parts = [
            'Act as a Fact-Checker and Editorial Validator.',
            "Topic: $topic",
            'Proposed Article Topics (JSON):',
            $phase3_topics,
        ];

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
     */
    public static function get_final_synopsis_prompt( $topic, $research_context, $policy_directives = '' ) {
        return implode( "\n", [
            'Act as a Senior Editorial Planner for a high-authority B2B publication.',
            "Topic: $topic",
            $policy_directives,
            'Comprehensive Research Context (JSON):',
            $research_context,
            'Objective: Generate 4-6 distinct, high-impact article synopses.',
            'Each synopsis MUST include: Compelling Headline, Strategic Summary, 4-6 Key Points, and a set of Target Keywords.',
            'Return ONLY valid JSON: {"synopses": [{"headline": "", "summary": "", "key_points": [""], "keywords": [""], "priority_score": 0.0}]}'
        ] );
    }
}
