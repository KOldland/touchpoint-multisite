<?php

namespace KH\EditorialAuthor\Agents;

/**
 * Class EnrichmentAgent
 * 
 * Handles post-processing of drafts: generating pull-quotes from numeric claims
 * and building the automated References section from verified citations.
 */
class EnrichmentAgent {

    /**
     * Execute enrichment process
     * 
     * @param string $draft_content The raw draft (HTML or Markdown)
     * @param array $citations List of verified citations
     * @return array Enriched blocks and metadata
     */
    public function execute($draft_content, $citations) {
        $warnings = [];
        $validation_errors = [];

        $blocks = $this->parse_draft_into_blocks($draft_content);
        if (empty($blocks)) {
            return [
                'blocks' => [],
                'pull_quotes' => [],
                'footnotes' => [],
                'warnings' => ['No draft blocks could be parsed.'],
                'validation_errors' => [],
            ];
        }

        $text = $this->blocks_to_text($blocks);
        $word_count = str_word_count($text);
        $target_quotes = max(1, (int) floor($word_count / 500));

        $pull_quotes = [];
        $output_blocks = [];

        foreach ($blocks as $block) {
            $output_blocks[] = $block;

            if (($block['type'] ?? '') !== 'paragraph') {
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

            $pull_quote_meta = [
                'source_author' => $citation['lead_author'] ?? '',
                'publication'   => $citation['publication'] ?? '',
                'organisation'  => $citation['organisation'] ?? '',
                'date'          => $citation['date'] ?? '',
                'citation_ref_id' => $ref_id,
            ];

            $pull_quotes[] = [
                'text'     => $quote_text,
                'metadata' => $pull_quote_meta,
            ];

            $output_blocks[] = [
                'type'    => 'pullquote',
                'content' => $quote_text,
                'cite'    => $this->format_pullquote_citation($citation),
                'meta'    => $pull_quote_meta,
            ];
        }

        if (empty($pull_quotes)) {
            $warnings[] = 'No eligible pull quotes were found based on numeric claims. Pull quotes skipped.';
        }

        // Build Footnotes
        $footnotes = $this->build_footnotes($citations);
        if (empty($footnotes)) {
            $warnings[] = 'No verified citations available to build footnotes.';
        } else {
            $output_blocks[] = ['type' => 'heading', 'level' => 2, 'content' => 'References'];
            $output_blocks[] = ['type' => 'list', 'ordered' => false, 'items' => $footnotes];
        }

        return [
            'blocks'            => $output_blocks,
            'pull_quotes'       => $pull_quotes,
            'footnotes'         => $footnotes,
            'warnings'          => $warnings,
            'validation_errors' => $validation_errors,
        ];
    }

    private function parse_draft_into_blocks($content) {
        $blocks = [];
        // Support for WP Block format if present
        if (strpos($content, '<!-- wp:') !== false) {
            $parsed = parse_blocks($content);
            foreach ($parsed as $block) {
                if ($block['blockName'] === 'core/heading') {
                    $blocks[] = [
                        'type' => 'heading',
                        'level' => $block['attrs']['level'] ?? 2,
                        'content' => wp_strip_all_tags(render_block($block)),
                    ];
                } elseif ($block['blockName'] === 'core/paragraph') {
                    $blocks[] = [
                        'type' => 'paragraph',
                        'content' => wp_strip_all_tags(render_block($block)),
                    ];
                }
            }
            if (!empty($blocks)) return $blocks;
        }

        // Fallback to newline parsing
        $text = wp_strip_all_tags($content);
        $paragraphs = preg_split('/\n\s*\n/', $text);
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!$paragraph) continue;
            $blocks[] = ['type' => 'paragraph', 'content' => $paragraph];
        }

        return $blocks;
    }

    private function blocks_to_text($blocks) {
        return implode("\n\n", array_column($blocks, 'content'));
    }

    private function extract_citation_markers($text) {
        preg_match_all('/\[(\d+)\]/', $text, $matches);
        return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
    }

    private function paragraph_has_numeric_claim($text) {
        return preg_match('/\b\d{1,3}(?:[\.,]\d+)?%?\b/', $text) === 1;
    }

    private function extract_sentence_with_number($paragraph) {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($paragraph));
        foreach ($sentences as $sentence) {
            if ($this->paragraph_has_numeric_claim($sentence)) return trim($sentence);
        }
        return null;
    }

    private function find_citation_by_ref($citations, $ref_id) {
        foreach ($citations as $index => $citation) {
            // Check both 1-based and ref_id based
            $actual_ref = $citation['ref_id'] ?? ($index + 1);
            if ((int)$actual_ref === (int)$ref_id) return $citation;
        }
        return null;
    }

    private function format_pullquote_citation($citation) {
        $parts = array_filter([
            $citation['lead_author'] ?? '',
            $citation['publication'] ?? ($citation['organisation'] ?? ''),
            $citation['year'] ?? ''
        ]);
        return implode(', ', $parts);
    }

    private function build_footnotes($citations) {
        $entries = [];
        foreach ($citations as $citation) {
            $author = !empty($citation['lead_author']) ? trim($citation['lead_author']) . '. ' : '';
            $year = !empty($citation['year']) ? '(' . trim($citation['year']) . '). ' : '';
            $title = !empty($citation['title']) ? trim($citation['title']) . '. ' : '';
            $pub = !empty($citation['publication']) ? trim($citation['publication']) . '. ' : (!empty($citation['organisation']) ? trim($citation['organisation']) . '. ' : '');
            $url = !empty($citation['url']) ? trim($citation['url']) : '';
            
            $entry = trim($author . $year . $title . $pub);
            if ($url) $entry .= ' ' . $url;
            
            if ($entry) $entries[] = $entry;
        }
        sort($entries, SORT_NATURAL | SORT_FLAG_CASE);
        return $entries;
    }
}
