<?php
/**
 * Model configuration helper.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dual_GPT_Model_Config {
    public function get_default_map() {
        return array(
            'discovery' => 'gpt-4o-mini',
            'validation' => 'gpt-4o',
            'author' => 'gpt-4.1',
            'framework' => 'gpt-5.2',
            'verify' => 'gpt-5.2',
        );
    }

    public function get_fallback_map() {
        return array(
            'discovery' => 'gpt-4o-mini',
            'validation' => 'gpt-4o',
            'author' => 'gpt-4o',
            'framework' => 'gpt-4.1',
            'verify' => 'gpt-4.1',
        );
    }

    public function get_allowed_models() {
        return array(
            'gpt-5.2',
            'gpt-5',
            'gpt-4.1',
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
        );
    }

    public function get_task_models() {
        $defaults = $this->get_default_map();
        $saved = get_option('dual_gpt_task_models', array());
        if (!is_array($saved)) {
            $saved = array();
        }

        $models = array();
        foreach ($defaults as $task => $default_model) {
            $candidate = sanitize_text_field($saved[$task] ?? $default_model);
            $models[$task] = $this->normalize_model($candidate, $default_model);
        }

        return $models;
    }

    public function get_model_for_task($task) {
        $models = $this->get_task_models();
        if (isset($models[$task])) {
            return $models[$task];
        }

        $defaults = $this->get_default_map();
        return $defaults[$task] ?? 'gpt-4o-mini';
    }

    public function is_non_optimal($task, $model) {
        $defaults = $this->get_default_map();
        $model = sanitize_text_field($model);
        return isset($defaults[$task]) && $defaults[$task] !== $model;
    }

    public function normalize_model($model, $fallback = '') {
        $model = sanitize_text_field($model);
        $allowed = $this->get_allowed_models();
        if (in_array($model, $allowed, true)) {
            return $model;
        }

        if ($fallback && in_array($fallback, $allowed, true)) {
            return $fallback;
        }

        return 'gpt-4o-mini';
    }
}
