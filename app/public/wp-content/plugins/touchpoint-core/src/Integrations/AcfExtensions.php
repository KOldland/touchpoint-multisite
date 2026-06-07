<?php
namespace TouchpointCore\Integrations;

class AcfExtensions {
    public function register() {
        add_filter('acf/settings/remove_wp_meta_box', '__return_false');
        add_filter('acf/load_field/name=main_category', [$this, 'load_main_category_choices']);
    }

    public function load_main_category_choices($field) {
        $categories = get_categories(['hide_empty' => false]);
        $choices = [];
        foreach ($categories as $cat) {
            $choices[$cat->term_id] = $cat->name;
        }
        $field['choices'] = $choices;
        return $field;
    }
}
