<?php

namespace KH\EditorialAuthor\Integration;

use WP_Error;

class IntelligenceBridge {

    /**
     * Check budget via kh-editorial-intelligence
     */
    public function check_budget($user_id) {
        // This should interface with the Intelligence plugin's budget/usage logic
        // For now, mirroring the legacy check but pointing to the future service
        if (class_exists('\KH\EditorialIntelligence\Core\LLMService')) {
            // Future: $intelligence = \KH\EditorialIntelligence\Core\LLMService::get_instance();
            // return $intelligence->check_user_budget($user_id);
        }
        
        return true; 
    }

    /**
     * Create a new AI job via Intelligence service
     */
    public function create_job($data) {
        if (class_exists('\KH\Editorial\Services\AI\AIStorage')) {
            $storage = new \KH\Editorial\Services\AI\AIStorage();
            return $storage->insert_job($data);
        }
        return new \WP_Error('intelligence_missing', 'KH Editorial Intelligence storage is not available.');
    }

    /**
     * Get job status/result
     */
    public function get_job($job_id) {
        if (class_exists('\KH\Editorial\Services\AI\AIStorage')) {
            $storage = new \KH\Editorial\Services\AI\AIStorage();
            return $storage->get_job($job_id);
        }
        return new \WP_Error('intelligence_missing', 'KH Editorial Intelligence storage is not available.');
    }

    /**
     * Call LLM via centralized Intelligence service (supports OpenAI and OpenRouter).
     */
    public function call_llm($system_prompt, $user_prompt, $options = []) {
        if (class_exists('\KH\Editorial\Core\LLMService')) {
            $agent   = $options['agent'] ?? 'draft';
            $persona = $options['persona'] ?? null;

            // Persona-aware routing for draft agent
            if ($agent === 'draft' && $persona && in_array($persona, \KH\Editorial\Core\LLMService::PERSONAS, true)) {
                $route = \KH\Editorial\Core\LLMService::resolve_persona_model($persona);
            } else {
                $route = \KH\Editorial\Core\LLMService::resolve_agent_model($agent);
            }

            $model = $route['model'];

            $messages = [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt],
            ];

            $args = [
                'provider'    => $route['provider'],
                'model'       => $model,
                'temperature' => $options['temperature'] ?? $route['temperature'] ?? 0.4,
                'max_tokens'  => $options['max_tokens'] ?? 2000,
            ];

            if (!empty($options['json_mode'])) {
                $args['response_format'] = ['type' => 'json_object'];
            }

            $result = \KH\Editorial\Core\LLMService::post_completion($messages, $args);

            if (is_wp_error($result)) {
                return $result;
            }

            return [
                'content' => $result['content'],
                'usage'   => $result['usage'] ?? null,
            ];
        }

        return new \WP_Error('intelligence_missing', 'KH Editorial Intelligence plugin is not active or incomplete.');
    }
}
