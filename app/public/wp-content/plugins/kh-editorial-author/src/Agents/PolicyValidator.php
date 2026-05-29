<?php

namespace KH\EditorialAuthor\Agents;

use KH\EditorialAuthor\Core\AuthorPolicy;

/**
 * Class PolicyValidator
 * 
 * Ported validation logic to ensure drafts meet editorial standards.
 */
class PolicyValidator {

    public function validate($text, $blocks, $policy) {
        $warnings = [];
        $errors = [];
        $policy = AuthorPolicy::sanitize($policy);

        $word_count = str_word_count($text);

        // Word count checks
        if ($word_count > 0 && $word_count < $policy['min_words']) {
            $warnings[] = sprintf('Draft is below policy minimum word count (%d < %d).', $word_count, $policy['min_words']);
        }
        if ($word_count > $policy['max_words']) {
            $warnings[] = sprintf('Draft exceeds policy maximum word count (%d > %d).', $word_count, $policy['max_words']);
        }

        // Em-dash checks
        $em_dash_count = preg_match_all('/\x{2014}|--/u', $text);
        if ($policy['disallow_em_dash']) {
            if ($em_dash_count > 0) {
                $warnings[] = sprintf('Em dash usage violates policy (%d detected).', $em_dash_count);
            }
        } else {
            $em_dash_limit = AuthorPolicy::get_em_dash_limit($policy['brand_profile'], $word_count);
            if ($em_dash_limit > 0 && $em_dash_count > $em_dash_limit) {
                $warnings[] = sprintf('Em dash usage exceeds guidance (%d used, max %d for this length).', $em_dash_count, $em_dash_limit);
            }
        }

        // Perspective checks
        if ($policy['disallow_first_person'] && $this->contains_first_person($text)) {
            $warnings[] = 'First-person perspective detected but disallowed by policy.';
        }

        // Banned phrases
        $banned_hits = $this->find_banned_phrase_hits($text, $policy['banned_phrases']);
        foreach ($banned_hits as $hit) {
            $warnings[] = 'Banned phrase detected: ' . $hit;
        }

        // Paragraph constraints (sentence length variation)
        $violations = $this->check_sentence_length_variation($blocks);
        if ($violations > 0) {
            $warnings[] = sprintf('Paragraph sentence-length constraint failed in %d paragraph(s).', $violations);
        }

        // Contradiction density
        $required_contradictions = (int) ceil($word_count / 500);
        $contradiction_count = $this->count_contradictions($text);
        if ($required_contradictions > 0 && $contradiction_count < $required_contradictions) {
            $warnings[] = sprintf('Contradiction/self-correction density is low (%d found, expected %d).', $contradiction_count, $required_contradictions);
        }

        // Rhetorical binaries & listicle framing
        if ($policy['disallow_rhetorical_binaries'] && $this->contains_rhetorical_binary($text)) {
            $warnings[] = 'Rhetorical binary detected ("not X but Y").';
        }
        if ($policy['disallow_listicle_framing'] && $this->contains_listicle_framing($text)) {
            $warnings[] = 'Listicle-style framing detected.';
        }

        // Headings & conclusions
        $generic_heading_hits = $this->find_disallowed_generic_headings($blocks);
        if (!empty($generic_heading_hits)) {
            $warnings[] = 'Generic section headings detected: ' . implode(', ', $generic_heading_hits) . '.';
        }
        if ($policy['disallow_tidy_conclusion'] && $this->has_tidy_conclusion($text, $blocks)) {
            $warnings[] = 'Draft may include a tidy conclusion (avoid definitive wrap-ups).';
        }

        // Citation parity checks
        $citations = $policy['citations'] ?? [];
        $citation_validation = $this->validate_citations_in_text($text, $citations);
        $warnings = array_merge($warnings, $citation_validation['warnings']);
        $errors = array_merge($errors, $citation_validation['errors']);

        // Footnote readiness
        $footnote_warnings = $this->validate_footnotes($citations);
        $warnings = array_merge($warnings, $footnote_warnings);

        return [
            'warnings' => $warnings,
            'errors'   => $errors,
        ];
    }

    /**
     * Validate citation details for footnote readiness
     */
    public function validate_footnotes($citations) {
        $warnings = [];
        $missing_apa = 0;
        $missing_url = 0;

        foreach ($citations as $citation) {
            $apa = $citation['apa_string'] ?? ($citation['apa'] ?? '');
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

    public function validate_citations_in_text($text, $citations) {
        $warnings = [];
        $errors = [];

        preg_match_all('/\[(\d+)\]/', $text, $matches);
        $markers = array_map('intval', $matches[1] ?? []);
        $markers = array_values(array_unique($markers));
        
        $max_ref = count($citations);

        if (empty($markers) && !empty($citations)) {
            $warnings[] = 'No citation markers found in draft output despite verified sources being available.';
        }

        foreach ($markers as $marker) {
            if ($marker < 1 || $marker > $max_ref) {
                $errors[] = 'Citation marker [' . $marker . '] does not match any verified citation.';
            }
        }

        return [
            'warnings' => $warnings,
            'errors'   => $errors,
        ];
    }

    private function contains_first_person($text) {
        return preg_match('/\b(i|we|our|ours|us|my|mine)\b/i', (string) $text) === 1;
    }

    private function find_banned_phrase_hits($text, $banned_phrases) {
        if (empty($banned_phrases)) return [];
        $hits = [];
        $haystack = strtolower((string) $text);
        foreach ($banned_phrases as $phrase) {
            if (strpos($haystack, strtolower($phrase)) !== false) $hits[] = $phrase;
        }
        return array_values(array_unique($hits));
    }

    private function check_sentence_length_variation($blocks) {
        $violations = 0;
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') !== 'paragraph') continue;
            $paragraph = $block['content'] ?? '';
            $sentences = preg_split('/(?<=[.!?])\s+/', trim($paragraph));
            $has_long = false;
            $has_short = false;
            foreach ($sentences as $sentence) {
                $word_count = str_word_count($sentence);
                if ($word_count > 20) $has_long = true;
                if ($word_count > 0 && $word_count < 8) $has_short = true;
            }
            if (!$has_long || !$has_short) $violations++;
        }
        return $violations;
    }

    private function count_contradictions($text) {
        $markers = AuthorPolicy::get_contradiction_markers();
        $count = 0;
        $lower = strtolower($text);
        foreach ($markers as $marker) {
            $count += substr_count($lower, $marker);
        }
        return $count;
    }

    private function contains_rhetorical_binary($text) {
        return preg_match('/\bnot\b[^.]{0,80}\bbut\b/i', $text) === 1;
    }

    private function contains_listicle_framing($text) {
        return preg_match('/\b(top|best)\s+\d+\b|\b\d+\s+(ways|reasons|tips|steps)\b/i', $text) === 1;
    }

    private function find_disallowed_generic_headings($blocks) {
        $disallowed = ['overview', 'conclusion', 'summary'];
        $hits = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') !== 'heading') continue;
            $content = strtolower(trim($block['content'] ?? ''));
            $token = preg_replace('/[^a-z]/', '', $content);
            if (in_array($token, $disallowed)) $hits[] = $block['content'];
        }
        return array_unique($hits);
    }

    private function has_tidy_conclusion($text, $blocks) {
        $last_heading = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'heading') $last_heading = $block['content'];
        }
        if ($last_heading && preg_match('/\b(overview|conclusion|summary|final thoughts)\b/i', $last_heading)) return true;
        return preg_match('/\bin conclusion\b|\bto conclude\b/i', $text) === 1;
    }
}
