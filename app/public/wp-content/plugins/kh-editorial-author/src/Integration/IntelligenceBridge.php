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
     * Call LLM via centralized Intelligence service
     */
    public function call_llm($system_prompt, $user_prompt, $options = []) {
        if (class_exists('\KH\Editorial\Core\LLMService')) {
            $api_key = \KH\Editorial\Core\LLMService::get_api_key();
            $model = $options['model'] ?? \KH\Editorial\Core\LLMService::get_model();
            
            if (!$api_key) {
                return new \WP_Error('missing_api_key', 'OpenAI API key not configured in Intelligence settings.');
            }

            $body = [
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.4,
                'max_tokens'  => $options['max_tokens'] ?? 2000,
            ];

            if (!empty($options['json_mode'])) {
                $body['response_format'] = ['type' => 'json_object'];
            }

            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($body),
                'timeout' => 60,
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($data['choices'][0]['message']['content'])) {
                return new \WP_Error('llm_empty_response', 'LLM returned an empty response.');
            }

            return [
                'content' => $data['choices'][0]['message']['content'],
                'usage'   => $data['usage'] ?? null,
            ];
        }

        return new \WP_Error('intelligence_missing', 'KH Editorial Intelligence plugin is not active or incomplete.');
    }
}
