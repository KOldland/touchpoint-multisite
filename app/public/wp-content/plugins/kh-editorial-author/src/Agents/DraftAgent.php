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
        
        $system_prompt = PromptFactory::build_draft_system_prompt($policy);
        $user_prompt = PromptFactory::build_draft_user_prompt($context, $instructions, $policy);

        $response = $this->intelligence->call_llm($system_prompt, $user_prompt, [
            'temperature' => 0.4,
            'max_tokens'  => 3000,
            'json_mode'   => true,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode($response['content'], true);
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
