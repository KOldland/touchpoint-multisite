<?php

namespace KH\EditorialAuthor\Core;

/**
 * Class AuthorPolicy
 * 
 * Manages the editorial standards and writing constraints for the Author Suite.
 * Ported and modularized from legacy Dual_GPT_Author_Agent.
 */
class AuthorPolicy {

    /**
     * Default policy settings
     */
    public static function get_defaults() {
        return [
            'reporter_voice_required'      => true,
            'disallow_first_person'        => true,
            'disallow_em_dash'             => true,
            'disallow_rhetorical_binaries' => true,
            'disallow_listicle_framing'    => true,
            'disallow_tidy_conclusion'     => true,
            'min_words'                    => 1200,
            'max_words'                    => 2600,
            'banned_phrases'               => [],
            'industry_focus'               => 'General',
            'audience_tier'                => 'General',
            'risk_tolerance'               => 'Moderate',
            'brand_profile'                => 'Brand A (FSI)',
        ];
    }

    /**
     * Contradiction markers for validation and prompt building
     */
    public static function get_contradiction_markers() {
        return [
            'however', 'but', 'yet', 'although', 'though', 'still', 'nevertheless', 
            'on the other hand', 'that said', 'to be fair', 'on second thought', 
            'i might be wrong', 'i should'
        ];
    }

    /**
     * Sanitize and merge policy with defaults
     */
    public static function sanitize($policy) {
        $defaults = self::get_defaults();
        $policy = is_array($policy) ? $policy : [];

        $banned_phrases = $policy['banned_phrases'] ?? $defaults['banned_phrases'];
        if (is_string($banned_phrases)) {
            $banned_phrases = array_filter(array_map('trim', explode(',', $banned_phrases)));
        }
        if (!is_array($banned_phrases)) {
            $banned_phrases = [];
        }
        $banned_phrases = array_values(array_unique(array_filter(array_map(function ($phrase) {
            return strtolower(trim((string) $phrase));
        }, $banned_phrases))));

        $min_words = max(300, intval($policy['min_words'] ?? $defaults['min_words']));
        $max_words = max($min_words, intval($policy['max_words'] ?? $defaults['max_words']));

        return [
            'reporter_voice_required'      => (bool) ($policy['reporter_voice_required'] ?? $defaults['reporter_voice_required']),
            'disallow_first_person'        => (bool) ($policy['disallow_first_person'] ?? $defaults['disallow_first_person']),
            'disallow_em_dash'             => (bool) ($policy['disallow_em_dash'] ?? $defaults['disallow_em_dash']),
            'disallow_rhetorical_binaries' => (bool) ($policy['disallow_rhetorical_binaries'] ?? $defaults['disallow_rhetorical_binaries']),
            'disallow_listicle_framing'    => (bool) ($policy['disallow_listicle_framing'] ?? $defaults['disallow_listicle_framing']),
            'disallow_tidy_conclusion'     => (bool) ($policy['disallow_tidy_conclusion'] ?? $defaults['disallow_tidy_conclusion']),
            'min_words'                    => $min_words,
            'max_words'                    => $max_words,
            'banned_phrases'               => $banned_phrases,
            'industry_focus'               => sanitize_text_field($policy['industry_focus'] ?? $defaults['industry_focus']),
            'audience_tier'                => sanitize_text_field($policy['audience_tier'] ?? $defaults['audience_tier']),
            'risk_tolerance'               => sanitize_text_field($policy['risk_tolerance'] ?? $defaults['risk_tolerance']),
            'brand_profile'                => sanitize_text_field($policy['brand_profile'] ?? $defaults['brand_profile']),
            'enrichment'                   => $policy['enrichment'] ?? [],
            'citations'                    => $policy['citations'] ?? [],
        ];
    }

    /**
     * Get em-dash guidance based on brand profile
     */
    public static function get_em_dash_guidance($brand_profile) {
        if (stripos($brand_profile, 'brand b') !== false) {
            return 'Em-dash usage: max 1 per 300+ words (Brand B).';
        }
        return 'Em-dash usage: max 1 per 1500 words (Brand A).';
    }

    /**
     * Get em-dash limit for validation
     */
    public static function get_em_dash_limit($brand_profile, $word_count) {
        if ($word_count <= 0) return 0;
        $limit = (stripos($brand_profile, 'brand b') !== false) ? 300 : 1500;
        return (int) ceil($word_count / $limit);
    }
}
