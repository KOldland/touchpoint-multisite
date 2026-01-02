<?php
namespace KHFormBuilder\Admin;

class FormAdmin
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
        add_action('add_meta_boxes', [$this, 'registerMetabox']);
        add_action('save_post_kh_form', [$this, 'saveForm'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('manage_kh_form_entry_posts_columns', [$this, 'entryColumns']);
        add_action('manage_kh_form_entry_posts_custom_column', [$this, 'renderEntryColumns'], 10, 2);
    }

    public function registerMetabox()
    {
        add_meta_box(
            'kh_form_builder_fields',
            __('Form Fields', 'kh-form-builder'),
            [$this, 'renderMetabox'],
            'kh_form',
            'normal',
            'high'
        );
    }

    public function renderMetabox($post)
    {
        wp_nonce_field('kh_form_fields', 'kh_form_fields_nonce');
        $fields = get_post_meta($post->ID, '_kh_form_fields', true);
        $fields = is_array($fields) ? $fields : [];
        ?>
        <div id="kh-form-builder" data-fields="<?php echo esc_attr(wp_json_encode($fields)); ?>">
            <p><?php esc_html_e('Add fields to this form. Drag to reorder.', 'kh-form-builder'); ?></p>
            <table class="kh-form-fields-table widefat">
                <thead>
                <tr>
                    <th><?php esc_html_e('Label', 'kh-form-builder'); ?></th>
                    <th><?php esc_html_e('Type', 'kh-form-builder'); ?></th>
                    <th><?php esc_html_e('Required', 'kh-form-builder'); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
            <button type="button" class="button" id="kh-form-add-field"><?php esc_html_e('Add Field', 'kh-form-builder'); ?></button>
        </div>
        <?php
    }

    public function saveForm($postId, $post)
    {
        if (! isset($_POST['kh_form_fields_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kh_form_fields_nonce'])), 'kh_form_fields')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $fields = isset($_POST['kh_form_fields']) ? json_decode(wp_unslash($_POST['kh_form_fields']), true) : [];
        if (! is_array($fields)) {
            $fields = [];
        }

        update_post_meta($postId, '_kh_form_fields', array_values($fields));
    }

    public function enqueueAssets($hook)
    {
        global $typenow;
        if ('kh_form' !== $typenow) {
            return;
        }

        wp_enqueue_style('kh-form-builder-admin', KH_FORM_BUILDER_URL . 'assets/css/admin.css', [], KH_FORM_BUILDER_VERSION);
        wp_enqueue_script('kh-form-builder-admin', KH_FORM_BUILDER_URL . 'assets/js/admin.js', ['jquery', 'jquery-ui-sortable', 'wp-i18n'], KH_FORM_BUILDER_VERSION, true);
        wp_localize_script('kh-form-builder-admin', 'khFormBuilder', [
            'fieldTypes' => [
                'text'     => __('Text', 'kh-form-builder'),
                'email'    => __('Email', 'kh-form-builder'),
                'textarea' => __('Textarea', 'kh-form-builder'),
                'select'   => __('Select', 'kh-form-builder'),
            ],
        ]);
    }

    public function entryColumns($columns)
    {
        $columns['kh_form'] = __('Form', 'kh-form-builder');
        $columns['kh_data'] = __('Data', 'kh-form-builder');
        return $columns;
    }

    public function renderEntryColumns($column, $postId)
    {
        if ('kh_form' === $column) {
            $formId = get_post_meta($postId, '_kh_form_id', true);
            echo $formId ? '<a href="' . esc_url(get_edit_post_link($formId)) . '">' . esc_html(get_the_title($formId)) . '</a>' : '&mdash;';
            return;
        }

        if ('kh_data' === $column) {
            $data = get_post_meta($postId, '_kh_form_data', true);
            if (! is_array($data) || empty($data)) {
                echo '&mdash;';
                return;
            }

            foreach ($data as $label => $value) {
                echo '<strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '<br>';
            }
        }
    }
}
