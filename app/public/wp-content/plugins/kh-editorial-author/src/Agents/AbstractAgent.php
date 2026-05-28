<?php

namespace KH\EditorialAuthor\Agents;

class AbstractAgent {
    private $intelligence;

    public function __construct($intelligence) {
        $this->intelligence = $intelligence;
    }

    public function execute($content, $user_id) {
        $system_prompt = $this->build_system_prompt();
        $user_prompt = $this->build_user_prompt($content);

        return $this->intelligence->call_llm($system_prompt, $user_prompt, [
            'temperature' => 0.2,
            'max_tokens' => 1200,
            'json_mode' => true,
        ]);
    }

    private function build_system_prompt() {
        return "You are the Author Agent (Phase 2: Abstracting)...";
    }

    private function build_user_prompt($content) {
        return "Summarize the following content...";
    }
}
