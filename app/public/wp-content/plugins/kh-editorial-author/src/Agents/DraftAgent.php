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

        $options = [
            'max_tokens' => 3000,
            'json_mode'  => true,
        ];

        if ($persona && in_array($persona, \KH\Editorial\Core\LLMService::PERSONAS, true)) {
            $options['agent'] = 'draft';
            $options['persona'] = $persona;
        }

        $response = $this->intelligence->call_llm($system_prompt, $user_prompt, $options);

        if (is_wp_error($response)) {
            return $response;
        }

        // Strip markdown code fences (```json ... ```) that the LLM may wrap around JSON
        $content = $response['content'];
        $content = preg_replace('/^```(?:json)?\s*\n?/i', '', $content);
        $content = preg_replace('/\n?```\s*$/', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['blocks'])) {
            return new \WP_Error('invalid_draft_output', 'Draft output is not valid JSON with blocks.');
        }

        $text = $this->blocks_to_text($data['blocks']);
        $validation = $this->validator->validate($text, $data['blocks'], $policy);

        return [
            'mode'              => 'draft',
            'blocks'            => $data['blocks'],
            'text'              => $text,
            'word_count'        => str_word_count($text),
            'citations'         => $citations,
            'warnings'          => $validation['warnings'],
            'validation_errors' => $validation['errors'],
            'author_policy'     => $policy,
            'usage'             => $response['usage'] ?? null,
        ];
    }

    private function blocks_to_text($blocks) {
        $parts = [];
        foreach ($blocks as $block) {
            if (!empty($block['content'])) {
                $parts[] = $block['content'];
            }
        }
        return implode("\n\n", $parts);
    }
}
