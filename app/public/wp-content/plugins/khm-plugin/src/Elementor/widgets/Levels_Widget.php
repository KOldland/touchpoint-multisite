<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use KHM\Services\LevelRepository;

/**
 * KHM Levels Widget
 * Simple list of membership levels for marketing/selection.
 */
class Levels_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_levels_widget';
    }

    public function get_title() {
        return __( 'KHM Levels', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-settings';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Levels', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'show_prices',
            [
                'label' => __( 'Show Prices', 'khm-membership' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __( 'Yes', 'khm-membership' ),
                'label_off' => __( 'No', 'khm-membership' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __( 'Columns', 'khm-membership' ),
                'type' => Controls_Manager::SELECT,
                'default' => '1',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $show_prices = ( $settings['show_prices'] ?? '' ) === 'yes';
        $columns     = max( 1, min( 3, (int) ( $settings['columns'] ?? 1 ) ) );

        $levels = [];
        try {
            $levels = ( new LevelRepository() )->all();
        } catch ( \Throwable $e ) {
            $levels = [];
        }

        if ( empty( $levels ) ) {
            echo '<p>' . esc_html__( 'No membership levels found.', 'khm-membership' ) . '</p>';
            return;
        }

        $col_class = 'khm-levels-cols-' . $columns;
        echo '<div class="khm-levels ' . esc_attr( $col_class ) . '">';
        foreach ( $levels as $level ) {
            echo '<div class="khm-level-card">';
            echo '<h3>' . esc_html( $level->name ) . '</h3>';
            if ( $show_prices ) {
                $price = isset( $level->billing_amount ) ? (float) $level->billing_amount : 0.0;
                $currency = get_option( 'khm_currency', 'USD' );
                echo '<p class="khm-level-price">' . esc_html( $currency . ' ' . number_format_i18n( $price, 2 ) ) . '</p>';
            }
            if ( ! empty( $level->description ) ) {
                echo '<p>' . esc_html( wp_trim_words( $level->description, 20 ) ) . '</p>';
            }
            $checkout_url = add_query_arg(
                [ 'level' => (int) $level->id ],
                site_url( '/checkout' )
            );
            echo '<p><a class="button" href="' . esc_url( $checkout_url ) . '">' . esc_html__( 'Select', 'khm-membership' ) . '</a></p>';
            echo '</div>';
        }
        echo '</div>';
    }
}
