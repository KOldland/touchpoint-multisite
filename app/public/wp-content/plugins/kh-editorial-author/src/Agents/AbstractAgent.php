<?php

namespace KH\EditorialAuthor\Agents;

/**
 * Class AbstractAgent
 * 
 * Handles extractive metadata generation (summaries, key points, meta-descriptions)
 * based on the completed draft.
 */
class AbstractAgent {
    private $intelligence;

    public function __construct($intelligence) {
        $this->intelligence = $intelligence;
    }

    public function execute($content, $user_id) {
        $system_prompt = PromptFactory::build_abstract_system_prompt();
        $user_prompt = PromptFactory::build_abstract_user_prompt($content);

        $response = $this->intelligence->call_llm($system_prompt, $user_prompt, [
            'temperature' => 0.2,
            'max_tokens'  => 1200,
            'json_mode'   => true,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode($response['content'], true);
        if (!is_array($data) || empty($data['overview'])) {
            return new \WP_Error('invalid_abstract_output', 'Abstract output is not valid JSON.');
        }

        // Validate abstract constraints
        $validation = $this->validate_abstract($data);

        return [
            'mode'              => 'abstract',
            'output'            => $data,
            'warnings'          => $validation['warnings'],
            'validation_errors' => $validation['errors'],
            'usage'             => $response['usage'] ?? null,
        ];
    }

    /**
     * Ported validation logic for abstract constraints
     */
    private function validate_abstract($abstract) {
        $warnings = [];
        $errors = [];

        $required = ['overview', 'key_points', 'context', 'application', 'keywords', 'editorial_summary', 'meta_summary'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $abstract)) {
                $errors[] = 'Missing field: ' . $key;
            }
        }

        // Sentence counts
        $overview_sentences = $this->count_sentences($abstract['overview'] ?? '');
        if ($overview_sentences < 2 || $overview_sentences > 3) {
            $warnings[] = 'Overview should be 2-3 sentences.';
        }

        $context_sentences = $this->count_sentences($abstract['context'] ?? '');
        if ($context_sentences > 3) {
            $warnings[] = 'Context should be 3 sentences or fewer.';
        }

        $key_points_count = is_array($abstract['key_points'] ?? null) ? count($abstract['key_points']) : 0;
        if ($key_points_count < 3 || $key_points_count > 6) {
            $warnings[] = 'Key Points should have 3-6 bullets.';
        }

        $editorial_word_count = str_word_count($abstract['editorial_summary'] ?? '');
        if ($editorial_word_count > 0 && ($editorial_word_count < 100 || $editorial_word_count > 200)) {
            $warnings[] = 'Editorial Summary should be 100-200 words.';
        }

        $meta_summary = $abstract['meta_summary'] ?? '';
        if (!empty($meta_summary) && strlen($meta_summary) > 160) {
            $warnings[] = 'Meta Summary should be 160 characters or fewer.';
        }

        return [
            'warnings' => $warnings,
            'errors'   => $errors,
        ];
    }

    private function count_sentences($text) {
        if (!is_string($text) || trim($text) === '') return 0;
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($text));
        return count(array_filter(array_map('trim', $sentences)));
    }
}
