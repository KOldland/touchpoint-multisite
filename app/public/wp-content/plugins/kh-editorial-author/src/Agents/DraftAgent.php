<?php

namespace KH\EditorialAuthor\Agents;

use KH\EditorialAuthor\Core\AuthorPolicy;

class DraftAgent {
    private $intelligence;
    private $validator;

    public function __construct($intelligence) {
        $this->intelligence = $intelligence;
        $this->validator = new PolicyValidator();
    }

    public function execute($context, $instructions, $user_id) {
        $policy = AuthorPolicy::sanitize($context['author_policy'] ?? []);
        $persona = $context['persona'] ?? null;

        // Include verified citations in the policy so the validator can
        // check that all [1], [2] markers in the draft match actual sources.
        $citations = $context['citations'] ?? [];
        $policy['citations'] = $citations;

        $system_prompt = PromptFactory::build_draft_system_prompt($policy, $persona);
        $user_prompt = PromptFactory::build_draft_user_prompt($context, $instructions, $policy);

        // Legacy behavior: request raw markdown output (no JSON mode)
        $options = [
            'max_tokens' => 3000,
        ];

        if ($persona && in_array($persona, \KH\Editorial\Core\LLMService::PERSONAS, true)) {
            $options['agent'] = 'draft';
            $options['persona'] = $persona;
        }

        $response = $this->intelligence->call_llm($system_prompt, $user_prompt, $options);

        if (is_wp_error($response)) {
            return $response;
        }

        $content = $response['content'] ?? '';
        $text = $content;
        
        if (empty($text)) {
            return new \WP_Error('invalid_draft_output', 'Draft output is empty.');
        }

        // Parse the raw markdown text from the LLM into structured blocks for the UI
        $blocks = $this->markdown_to_blocks($text);

        // Enrich: generate references block list and append to blocks/text if needed
        $blocks = $this->generate_enrichment($blocks, $citations);
        
        // Ensure the plain text representation stays completely in sync with the final blocks
        $text = $this->blocks_to_text($blocks);

        $validation = $this->validator->validate($text, [], $policy);

        return [
            'mode'              => 'draft',
            'blocks'            => $blocks, // No longer empty! The frontend will now display the draft.
            'text'              => $text,
            'word_count'        => str_word_count($text),
            'citations'         => $citations,
            'warnings'          => $validation['warnings'],
            'validation_errors' => $validation['errors'],
            'author_policy'     => $policy,
            'usage'             => $response['usage'] ?? null,
        ];
    }

    /**
     * Parse raw markdown text into structured layout blocks.
     * Splits by double newlines and detects standard Markdown headings.
     *
     * @param string $text Raw markdown content.
     * @return array Structured blocks.
     */
    private function markdown_to_blocks($text) {
        $blocks = [];
        $lines = explode("\n\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Check for H3
            if (strpos($line, '###') === 0) {
                $blocks[] = [
                    'type'    => 'heading',
                    'level'   => 3,
                    'content' => trim(substr($line, 3)),
                ];
            // Check for H2
            } elseif (strpos($line, '##') === 0) {
                $blocks[] = [
                    'type'    => 'heading',
                    'level'   => 2,
                    'content' => trim(substr($line, 2)),
                ];
            // Check for H1 (Fallback safety)
            } elseif (strpos($line, '#') === 0) {
                $blocks[] = [
                    'type'    => 'heading',
                    'level'   => 1,
                    'content' => trim(substr($line, 1)),
                ];
            // Default to paragraph
            } else {
                $blocks[] = [
                    'type'    => 'paragraph',
                    'content' => $line,
                ];
            }
        }

        return $this->normalize_draft_blocks($blocks);
    }

    /**
     * Normalize draft blocks from LLM JSON output.
     * Ensures every block has type, level (for headings), and content.
     */
    private function normalize_draft_blocks($blocks) {
        if (!is_array($blocks)) {
            return [];
        }

        $normalized = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = $block['type'] ?? 'paragraph';
            $content = $block['content'] ?? '';

            if ($type === 'heading') {
                $normalized[] = [
                    'type'    => 'heading',
                    'level'   => (int) ($block['level'] ?? 2),
                    'content' => trim($content),
                ];
            } else {
                // Keep structure but safely sanitize spacing variations
                $normalized[] = [
                    'type'    => 'paragraph',
                    'content' => trim(preg_replace('/\s+/', ' ', $content)),
                ];
            }
        }

        return $normalized;
    }

    /**
     * Append a References section to the blocks using framework citation data.
     *
     * This mirrors the legacy generate_enrichment() logic:
     * - Finds citation markers [1], [2] etc. in the draft text.
     * - Appends a "References" heading at the end.
     * - Lists each citation with its formatted details.
     *
     * No LLM call — this is purely server-side assembly from existing data.
     *
     * @param array $blocks    The parsed draft blocks.
     * @param array $citations The framework citation array.
     * @return array Blocks with References section appended.
     */
    private function generate_enrichment($blocks, $citations) {
        if (empty($citations)) {
            return $blocks;
        }

        // Collect which citation refs are actually used in the draft text.
        $text = $this->blocks_to_text($blocks);
        $used_refs = [];
        if (preg_match_all('/\[(\d+)\]/', $text, $matches)) {
            $used_refs = array_unique(array_map('intval', $matches[1]));
        }

        if (empty($used_refs)) {
            return $blocks;
        }

        // Build the References section.
        $references[] = [
            'type'    => 'heading',
            'level'   => 2,
            'content' => 'References',
        ];

        $ref_lines = [];
        foreach ($used_refs as $ref_id) {
            $index = $ref_id - 1;
            if (!isset($citations[$index])) {
                continue;
            }
            $c = $citations[$index];

            // Format consistent with legacy: [N] APA reference
            $apa = $c['apa'] ?? '';
            if (empty($apa)) {
                // Fallback: build a simple reference from available fields.
                $author = $c['lead_author'] ?? 'Unknown Author';
                $title  = $c['title'] ?? 'No Title';
                $year   = $c['year'] ?? 'n.d.';
                $pub    = $c['publication'] ?? ($c['organisation'] ?? '');
                $url    = $c['url'] ?? '';
                $apa    = trim("{$author} ({$year}). {$title}. {$pub}.");
                if ($url) {
                    $apa .= " {$url}";
                }
            }

            $ref_lines[] = "[{$ref_id}] {$apa}";
        }

        if (!empty($ref_lines)) {
            $references[] = [
                'type'    => 'paragraph',
                'content' => implode("\n", $ref_lines),
            ];
            $blocks = array_merge($blocks, $references);
        }

        return $blocks;
    }

    /**
     * Convert structured blocks back into plain text for validation and display.
     *
     * Headings are prefixed with ## or ### markers. Paragraphs are separated
     * by double newlines. This produces clean, readable markdown suitable for
     * the "Export Draft" and preview views.
     *
     * @param array $blocks
     * @return string
     */
    private function blocks_to_text($blocks) {
        $parts = [];
        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'paragraph';
            $content = $block['content'] ?? '';

            if ($type === 'heading') {
                $level = (int) ($block['level'] ?? 2);
                $prefix = str_repeat('#', max(1, min(3, $level)));
                $parts[] = "{$prefix} {$content}";
            } else {
                $parts[] = $content;
            }
        }
        return implode("\n\n", $parts);
    }
}