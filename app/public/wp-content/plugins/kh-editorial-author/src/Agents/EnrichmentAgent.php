<?php

namespace KH\EditorialAuthor\Agents;

class EnrichmentAgent {
    public function execute($content, $citations) {
        // Pure logic for pull quotes and footnotes
        // Does not necessarily require LLM call if using rule-based extraction
        // (Migrated from generate_enrichment in class-author-agent.php)
        return [
            'blocks' => [],
            'pull_quotes' => [],
            'footnotes' => [],
        ];
    }
}
