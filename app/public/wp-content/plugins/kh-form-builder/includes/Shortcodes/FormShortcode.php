<?php
namespace KHFormBuilder\Shortcodes;

class FormShortcode
{
    public static function register()
    {
        add_shortcode('kh_form', [__CLASS__, 'render']);
    }

    public static function render($atts)
    {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'kh_form');

        $formId = absint($atts['id']);
        if (! $formId) {
            return '';
        }

        $post = get_post($formId);
        if (! $post || 'kh_form' !== $post->post_type) {
            return '';
        }

        $fields = get_post_meta($formId, '_kh_form_fields', true);
        $fields = is_array($fields) ? $fields : [];

        wp_enqueue_style('kh-form-builder-frontend', KH_FORM_BUILDER_URL . 'assets/css/frontend.css', [], KH_FORM_BUILDER_VERSION);
        wp_enqueue_script('kh-form-builder-frontend', KH_FORM_BUILDER_URL . 'assets/js/frontend.js', ['jquery'], KH_FORM_BUILDER_VERSION, true);
        wp_localize_script('kh-form-builder-frontend', 'khFormFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'error'   => __('Something went wrong. Please try again.', 'kh-form-builder'),
        ]);

        ob_start();
        ?>
        <form class="kh-form" method="post" data-form-id="<?php echo esc_attr($formId); ?>">
            <?php foreach ($fields as $index => $field) :
                $type = isset($field['type']) ? $field['type'] : 'text';
                $name = self::resolveFieldName($field, $index);
                $fieldId = 'kh_form_' . $formId . '_' . $name;
                ?>
                <div class="kh-form-field">
                    <label for="<?php echo esc_attr($fieldId); ?>"><?php echo esc_html($field['label']); ?><?php echo ! empty($field['required']) ? ' *' : ''; ?></label>
                    <?php echo self::renderFieldInput($field, $fieldId, $name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endforeach; ?>
            <?php wp_nonce_field('kh_form_submit_' . $formId); ?>
            <input type="hidden" name="kh_form_id" value="<?php echo esc_attr($formId); ?>" />
            <button type="submit" class="kh-form-submit"><?php esc_html_e('Send', 'kh-form-builder'); ?></button>
            <div class="kh-form-response" style="display:none;"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    private static function renderFieldInput($field, $fieldId, $name)
    {
        $type     = isset($field['type']) ? $field['type'] : 'text';
        $required = ! empty($field['required']) ? 'required' : '';

        switch ($type) {
            case 'textarea':
                return '<textarea id="' . esc_attr($fieldId) . '" name="fields[' . esc_attr($name) . ']" ' . $required . '></textarea>';
            case 'email':
                return '<input type="email" id="' . esc_attr($fieldId) . '" name="fields[' . esc_attr($name) . ']" ' . $required . '>';
            case 'select':
                return '<select id="' . esc_attr($fieldId) . '" name="fields[' . esc_attr($name) . ']" ' . $required . '></select>';
            default:
                return '<input type="text" id="' . esc_attr($fieldId) . '" name="fields[' . esc_attr($name) . ']" ' . $required . '>';
        }
    }

    /**
     * Resolve a stable field name to avoid collisions when labels repeat.
     */
    private static function resolveFieldName($field, $index)
    {
        if (!empty($field['name'])) {
            return sanitize_key($field['name']);
        }

        $base = sanitize_key($field['label']);
        if (!$base) {
            $base = 'field_' . $index;
        }

        // Append index to reduce collision risk if labels repeat.
        return $base . '_' . intval($index);
    }
}
