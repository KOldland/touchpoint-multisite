<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use KHM\Services\LevelRepository;

/**
 * KHM Checkout Widget
 * Wraps the [khm_checkout] shortcode.
 */
class Checkout_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_checkout_widget';
    }

    public function get_title() {
        return __( 'KHM Checkout', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-cart-medium';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Checkout', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'level_id',
            [
                'label' => __( 'Membership Level', 'khm-membership' ),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_levels_options(),
                'default' => '',
                'description' => __( 'Select the level for checkout. Leave blank to require selection.', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'show_levels',
            [
                'label' => __( 'Show Level Selector', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-membership' ),
                'label_off' => __( 'No', 'khm-membership' ),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $level_id = absint( $settings['level_id'] ?? 0 );
        $show_levels = ( $settings['show_levels'] ?? '' ) === 'yes';

        $atts = [];
        if ( $level_id ) {
            $atts[] = 'level_id="' . esc_attr( $level_id ) . '"';
        }
        if ( $show_levels ) {
            $atts[] = 'show_levels="true"';
        }

        echo do_shortcode( '[khm_checkout ' . implode( ' ', $atts ) . ']' );
    }

    /**
     * Build options for level select.
     *
     * @return array
     */
    private function get_levels_options(): array {
        $options = [ '' => __( '— Select —', 'khm-membership' ) ];
        try {
            $levels = ( new LevelRepository() )->all();
            foreach ( $levels as $level ) {
                $options[ (string) $level->id ] = $level->name;
            }
        } catch ( \Throwable $e ) {
            // Fail silently; keep minimal options to avoid breaking editor.
        }
        return $options;
    }
}
