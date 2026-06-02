<?php

namespace KH\EditorialAuthor\Agents;

use KH\EditorialAuthor\Core\AuthorPolicy;

/**
 * Class PromptFactory
 * 
 * Centralizes the prompt engineering logic for the Author Suite.
 */
class PromptFactory {

    public static function build_draft_system_prompt($policy) {
        $policy = AuthorPolicy::sanitize($policy);
        $em_dash_guidance = AuthorPolicy::get_em_dash_guidance($policy['brand_profile']);

        $lines = [
            'You are the Author Agent. You execute an approved editorial plan and framework without adding new strategy, SEO, or distribution logic.',
            'You must not introduce new citations, entities, or claims beyond provided materials.',
            'Do not modify the topic scope or angle.',
            'Persona: ' . ($policy['reporter_voice_required'] ? 'Experienced Analyst / Senior Journalist.' : 'Professional B2B analyst writer.'),
            'Industry focus: ' . $policy['industry_focus'],
            'Audience tier: ' . $policy['audience_tier'],
            'Risk tolerance: ' . $policy['risk_tolerance'],
            'Brand profile: ' . $policy['brand_profile'],
            $policy['disallow_em_dash'] ? 'No em dashes (—) or double hyphens (--).' : $em_dash_guidance,
            $policy['disallow_tidy_conclusion'] ? 'No tidy conclusions. No omniscient voice. Allow tonal variation and friction.' : 'Avoid definitive resolution unless source-backed.',
            $policy['disallow_first_person'] ? 'Do not use first-person pronouns (I, we, our, us).' : 'Prefer third-person perspective.',
            'Output must be JSON only (no markdown or commentary).',
        ];

        if (!empty($policy['banned_phrases'])) {
            $lines[] = 'Do not use these banned phrases: ' . implode(', ', $policy['banned_phrases']) . '.';
        }

        // Add Enrichment Metadata if present in policy
        if (!empty($policy['enrichment'])) {
            $e = $policy['enrichment'];
            
            $fields = [
                'target_personas'     => 'Target audience',
                'target_sponsors'     => 'Consider vendor ecosystem',
                'key_competitors'     => 'Contextualise against competitors',
                'trade_associations'  => 'Reference industry bodies',
                'academic_journals'   => 'Preferred academic sources',
                'acronyms'            => 'Use and define terminology',
                'cultural_lexicon'    => 'Key industry concepts',
                'key_speakers'        => 'Reference thought leaders'
            ];

            foreach ($fields as $key => $label) {
                if (empty($e[$key])) continue;
                
                $values = $e[$key];
                $list = [];

                if (is_array($values)) {
                    foreach ($values as $item) {
                        if (is_array($item) && isset($item['name'])) {
                            $list[] = $item['name'];
                        } elseif (is_string($item)) {
                            $list[] = $item;
                        }
                    }
                } elseif (is_string($values)) {
                    $list[] = $values;
                }

                if (!empty($list)) {
                    $lines[] = $label . ': ' . implode(', ', $list) . '.';
                }
            }
        }

        return implode("\n", $lines);
    }

    public static function build_draft_user_prompt($context, $instructions, $policy) {
        $policy = AuthorPolicy::sanitize($policy);
        $prompt = [];

        $prompt[] = 'Editorial Planner Output (read-only):';
        $prompt[] = wp_json_encode($context['planner_data'] ?? [], JSON_PRETTY_PRINT);
        $prompt[] = '';
        
        $prompt[] = 'Research Dossier:';
        $prompt[] = $context['dossier'] ?? 'No dossier provided.';
        $prompt[] = '';

        $prompt[] = 'Verified Citations (use numeric markers [1], [2], etc. Do not invent new sources):';
        $citations = $context['citations'] ?? [];
        if (!empty($citations)) {
            foreach ($citations as $index => $citation) {
                $ref_id = $index + 1;
                $prompt[] = sprintf(
                    '[%d] %s - %s (%s). %s. URL: %s. Snippet: %s',
                    $ref_id,
                    $citation['lead_author'] ?? 'Unknown Author',
                    $citation['title'] ?? 'No Title',
                    $citation['year'] ?? 'n.d.',
                    $citation['publication'] ?? ($citation['organisation'] ?? 'Unknown Source'),
                    $citation['url'] ?? 'No URL',
                    $citation['passage_snippet'] ?? 'No Snippet'
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
        
        if ($policy['disallow_em_dash']) {
            $prompt[] = '- No em dashes (—) or double hyphens (--).';
        } else {
            $prompt[] = '- Em-dash usage per brand profile: ' . AuthorPolicy::get_em_dash_guidance($policy['brand_profile']);
        }

        $prompt[] = '- Every paragraph: at least one sentence >20 words and one sentence <8 words.';
        $prompt[] = '- At least one contradiction or self-correction per 500 words.';
        $prompt[] = '- Markers: ' . implode(', ', AuthorPolicy::get_contradiction_markers()) . '.';
        $prompt[] = '- Paragraphs broken by thought, not template.';
        $prompt[] = '- Preserve ambiguity, temporal drift, unresolved tension.';
        $prompt[] = '- Observational, reported, investigative stance.';
        $prompt[] = '- Do not use generic section headings: "Overview", "Conclusion", or "Summary".';
        
        if ($policy['disallow_tidy_conclusion']) {
            $prompt[] = '- No tidy conclusions or definitive resolution.';
        }
        if ($policy['disallow_first_person']) {
            $prompt[] = '- No first-person pronouns (I, we, our, us).';
        }

        $prompt[] = sprintf('- Target word count range: %d-%d words.', $policy['min_words'], $policy['max_words']);
        
        $prompt[] = '';
        $prompt[] = 'Output JSON schema:';
        $prompt[] = '{"blocks":[{"type":"heading","level":2,"content":""},{"type":"paragraph","content":""}],"citations_used":[1,2],"word_count":1234}';

        return implode("\n", $prompt);
    }

    public static function build_abstract_system_prompt() {
        return implode("\n", [
            'You are the Author Agent (Phase 2). This is extractive and analytical, not creative.',
            'No em dashes. No rhetorical binaries. No listicle framing.',
            'No additional insight beyond the provided draft.',
            'Use formal business/academic language only.',
            'Output JSON only, following the schema exactly.',
        ]);
    }

    public static function build_abstract_user_prompt($draft_content) {
        $prompt = [];
        $prompt[] = 'Draft Article:';
        $prompt[] = wp_strip_all_tags($draft_content);
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
}
