<?php
/**
 * Framework Brief Schema Validator
 */

if (!defined('ABSPATH')) {
    exit;
}

class Framework_Brief_Validator {

    /**
     * JSON Schema for framework brief
     */
    const SCHEMA = array(
        'type' => 'object',
        'required' => array('id', 'title', 'overview', 'context', 'application', 'observations', 'key_themes', 'citations', 'writer_guidance'),
        'properties' => array(
            'id' => array('type' => 'string'),
            'title' => array('type' => 'string'),
            'overview' => array('type' => 'string'),
            'context' => array('type' => 'string'),
            'application' => array(
                'type' => 'object',
                'properties' => array(
                    'audience' => array('type' => 'string'),
                    'use_cases' => array('type' => 'array', 'items' => array('type' => 'string')),
                ),
            ),
            'observations' => array(
                'type' => 'array',
                'minItems' => 3,
                'maxItems' => 5,
                'items' => array(
                    'type' => 'object',
                    'required' => array('id', 'text', 'evidence'),
                    'properties' => array(
                        'id' => array('type' => 'string'),
                        'text' => array('type' => 'string'),
                        'evidence' => array(
                            'type' => 'array',
                            'minItems' => 1,
                            'items' => array(
                                'type' => 'object',
                                'required' => array('citation_id', 'passage_snippet'),
                                'properties' => array(
                                    'citation_id' => array('type' => 'string'),
                                    'passage_snippet' => array('type' => 'string', 'minLength' => 20),
                                    'confidence' => array('type' => 'number', 'minimum' => 0, 'maximum' => 1),
                                ),
                            ),
                        ),
                        'memory_cue' => array('type' => 'string'),
                        'micro_transition' => array('type' => 'string'),
                    ),
                ),
            ),
            'key_themes' => array(
                'type' => 'array',
                'minItems' => 4,
                'maxItems' => 6,
                'items' => array('type' => 'string'),
            ),
            'citations' => array(
                'type' => 'array',
                'minItems' => 4,
                'maxItems' => 6,
                'items' => array(
                    'type' => 'object',
                    'required' => array('id', 'apa_string', 'url', 'year', 'lead_author', 'publication', 'organisation', 'type', 'relevance_note'),
                    'properties' => array(
                        'id' => array('type' => 'string'),
                        'apa_string' => array('type' => 'string'),
                        'url' => array('type' => 'string'),
                        'year' => array('type' => 'integer'),
                        'lead_author' => array('type' => 'string'),
                        'publication' => array('type' => 'string'),
                        'organisation' => array('type' => 'string'),
                        'type' => array('enum' => array('academic', 'analyst', 'industry', 'case_study')),
                        'relevance_note' => array('type' => 'string'),
                    ),
                ),
            ),
            'writer_guidance' => array(
                'type' => 'object',
                'required' => array('tone', 'voice_constraints', 'length_recommendation'),
                'properties' => array(
                    'tone' => array('type' => 'string'),
                    'voice_constraints' => array('type' => 'string'),
                    'length_recommendation' => array('enum' => array('short', 'medium', 'long')),
                    'length_weight' => array('type' => 'number'),
                ),
            ),
        ),
    );

    /**
     * Validate framework brief against schema
     */
    public function validate($brief) {
        // Basic structure validation
        if (!is_array($brief)) {
            return array('valid' => false, 'errors' => array('Brief must be an array'));
        }

        $errors = array();

        // Check required fields
        foreach (self::SCHEMA['required'] as $field) {
            if (!isset($brief[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }

        // Validate observations
        if (isset($brief['observations'])) {
            $obs_count = count($brief['observations']);
            if ($obs_count < 3 || $obs_count > 5) {
                $errors[] = "Observations must be between 3 and 5 items, got $obs_count";
            }

            foreach ($brief['observations'] as $i => $obs) {
                if (!isset($obs['evidence']) || empty($obs['evidence'])) {
                    $errors[] = "Observation $i missing evidence";
                } else {
                    foreach ($obs['evidence'] as $j => $evidence) {
                        if (empty($evidence['passage_snippet']) || strlen($evidence['passage_snippet']) < 20) {
                            $errors[] = "Observation $i evidence $j has insufficient passage snippet";
                        }
                        if (empty($evidence['citation_id'])) {
                            $errors[] = "Observation $i evidence $j missing citation_id";
                        }
                    }
                }
            }
        }

        // Validate citations
        if (isset($brief['citations'])) {
            $cit_count = count($brief['citations']);
            if ($cit_count < 3 || $cit_count > 6) {
                $errors[] = "Citations must be between 3 and 6 items, got $cit_count";
            }

            $types = array();
            foreach ($brief['citations'] as $citation) {
                if (isset($citation['type'])) {
                    $types[] = $citation['type'];
                }
            }

            // Check for required types
            $required_types = array('academic', 'analyst', 'industry', 'case_study');
            foreach ($required_types as $type) {
                if (!in_array($type, $types)) {
                    $errors[] = "Missing required citation type: $type";
                }
            }
        }

        // Validate key themes
        if (isset($brief['key_themes'])) {
            $theme_count = count($brief['key_themes']);
            if ($theme_count < 4 || $theme_count > 6) {
                $errors[] = "Key themes must be between 4 and 6 items, got $theme_count";
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Validate and retry with LLM if invalid
     */
    public function validate_with_retry($brief_json, $llm_client, $max_retries = 2) {
        $brief = json_decode($brief_json, true);
        $validation = $this->validate($brief);

        if ($validation['valid']) {
            return array('valid' => true, 'brief' => $brief);
        }

        // Retry with LLM
        for ($i = 0; $i < $max_retries; $i++) {
            $retry_prompt = "The following framework brief has validation errors:\n\n" .
                           implode("\n", $validation['errors']) . "\n\n" .
                           "Original brief:\n$brief_json\n\n" .
                           "Please fix these errors and return a valid framework brief JSON.";

            $messages = array(
                array('role' => 'system', 'content' => 'You are a JSON validator. Fix the provided framework brief to match the required schema.'),
                array('role' => 'user', 'content' => $retry_prompt),
            );

            $response = $llm_client->create_chat_completion($messages, 'gpt-4o-mini', array(), 'none');

            if (!is_wp_error($response)) {
                $new_brief_json = $response['choices'][0]['message']['content'];
                $new_brief = json_decode($new_brief_json, true);
                $new_validation = $this->validate($new_brief);

                if ($new_validation['valid']) {
                    return array('valid' => true, 'brief' => $new_brief, 'retries' => $i + 1);
                } else {
                    // Update validation so subsequent retries use the latest errors
                    $validation = $new_validation;
                }
            }
        }

        return array('valid' => false, 'errors' => $validation['errors'], 'brief' => $brief);
    }
}