<?php

namespace KHFormBuilder\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Elementor widget wrapper for [kh_form].
 */
class FormWidget extends Widget_Base
{
    public function get_name()
    {
        return 'kh_form_widget';
    }

    public function get_title()
    {
        return __('KH Form', 'kh-form-builder');
    }

    public function get_icon()
    {
        return 'eicon-form-horizontal';
    }

    public function get_categories()
    {
        return ['touchpoint'];
    }

    protected function register_controls()
    {
        $this->start_controls_section(
            'section_form',
            [
                'label' => __('Form', 'kh-form-builder'),
            ]
        );

        $this->add_control(
            'id',
            [
                'label' => __('Form', 'kh-form-builder'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_forms_options(),
                'description' => __('Select which form to render.', 'kh-form-builder'),
            ]
        );

        $this->end_controls_section();
    }

    protected function render()
    {
        $settings = $this->get_settings_for_display();
        $form_id  = absint($settings['id'] ?? 0);

        if (! $form_id) {
            echo '<div class="kh-form-widget-error">' . esc_html__('Select a form to render.', 'kh-form-builder') . '</div>';
            return;
        }

        echo do_shortcode('[kh_form id="' . esc_attr($form_id) . '"]');
    }

    /**
     * Build select options of available forms.
     *
     * @return array
     */
    private function get_forms_options(): array
    {
        $options = ['' => __('— Select —', 'kh-form-builder')];
        $forms = get_posts([
            'post_type'      => 'kh_form',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        foreach ($forms as $form) {
            $options[$form->ID] = $form->post_title ?: sprintf(__('Form #%d', 'kh-form-builder'), $form->ID);
        }

        return $options;
    }
}
