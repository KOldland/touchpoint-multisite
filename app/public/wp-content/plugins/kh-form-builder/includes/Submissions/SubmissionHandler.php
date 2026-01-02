<?php
namespace KHFormBuilder\Submissions;

class SubmissionHandler
{
    private static $instance;

    public static function getInstance()
    {
        if (! self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_ajax_kh_form_submit', [$this, 'handle']);
        add_action('wp_ajax_nopriv_kh_form_submit', [$this, 'handle']);
    }

    public function handle()
    {
        $formId = isset($_POST['kh_form_id']) ? absint($_POST['kh_form_id']) : 0;
        if (! $formId || ! wp_verify_nonce($_POST['_wpnonce'] ?? '', 'kh_form_submit_' . $formId)) {
            wp_send_json_error(__('Invalid request.', 'kh-form-builder'));
        }

        $fields = get_post_meta($formId, '_kh_form_fields', true);
        $fields = is_array($fields) ? $fields : [];

        $submitted = isset($_POST['fields']) ? (array) $_POST['fields'] : [];
        $clean     = [];

        foreach ($fields as $index => $field) {
            $label = sanitize_text_field($field['label']);
            $key   = $this->resolveFieldName($field, $index);
            $value = isset($submitted[$key]) ? wp_strip_all_tags($submitted[$key]) : '';

            if (! empty($field['required']) && '' === $value) {
                wp_send_json_error(sprintf(__('"%s" is required.', 'kh-form-builder'), $label));
            }

            $clean[$label] = $value;
        }

        $entryId = wp_insert_post([
            'post_type'   => 'kh_form_entry',
            'post_title'  => get_the_title($formId) . ' - ' . current_time('mysql'),
            'post_status' => 'publish',
            'meta_input'  => [
                '_kh_form_data' => $clean,
                '_kh_form_id'   => $formId,
            ],
        ]);

        wp_send_json_success([
            'message' => __('Thank you! We received your submission.', 'kh-form-builder'),
            'entry_id'=> $entryId,
        ]);
    }

    /**
     * Resolve a stable field key (mirrors FormShortcode logic).
     */
    private function resolveFieldName($field, $index)
    {
        if (!empty($field['name'])) {
            return sanitize_key($field['name']);
        }

        $base = sanitize_key($field['label']);
        if (! $base) {
            $base = 'field_' . $index;
        }

        return $base . '_' . intval($index);
    }
}
