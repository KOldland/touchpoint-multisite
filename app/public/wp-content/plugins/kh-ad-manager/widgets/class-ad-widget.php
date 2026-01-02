<?php
defined('ABSPATH') || exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

/**
 * KH Elementor Ad Widget
 * A single, reusable widget that represents an ad slot location.
 * Does not determine what ad shows — that's handled by ACF logic per post.
 */
class KH_Elementor_Ad_Widget extends Widget_Base {

    public function get_name() {
        return 'kh_ad_widget';
    }

    public function get_title() {
        return __('KH Ad Slot', 'kh-ad-manager');
    }

    public function get_icon() {
        return 'eicon-ad';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        $slots = [
            'exit_overlay' => 'Exit Overlay',
            'footer'       => 'Footer',
            'header'       => 'Header',
            'popup'        => 'PopUp',
            'sidebar1'     => 'Sidebar 1',
            'sidebar2'     => 'Sidebar 2',
            'ticker'       => 'Ticker',
            'slide_in'     => 'Slide In',
        ];

        $this->start_controls_section('content', [
            'label' => __('Ad Slot Settings', 'kh-ad-manager'),
        ]);

        $this->add_control('ad_slot', [
            'label'   => __('Ad Slot', 'kh-ad-manager'),
            'type'    => Controls_Manager::SELECT,
            'options' => $slots,
            'default' => 'sidebar1',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $slot = $settings['ad_slot'] ?? '';

        if (!$slot) {
            echo '<!-- KH Ad Widget: no slot selected -->';
            return;
        }

        $post_id = get_the_ID();

        // Delegate to contextual rendering logic
        kh_ad_manager_render_ad_for_slot_in_context($slot, $post_id);
    }
}
