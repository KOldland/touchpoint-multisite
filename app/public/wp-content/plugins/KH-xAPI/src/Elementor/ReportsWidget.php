<?php
namespace KH\XAPI\Elementor;

use Elementor\Widget_Base;

/**
 * Elementor widget wrapper for [kh_xapi_reports].
 */
class ReportsWidget extends Widget_Base {

    public function get_name() {
        return 'kh_xapi_reports_widget';
    }

    public function get_title() {
        return __( 'KH xAPI Reports', 'kh-xapi' );
    }

    public function get_icon() {
        return 'eicon-table';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        // No controls needed; renders user-facing reports form.
    }

    protected function render() {
        echo do_shortcode( '[kh_xapi_reports]' );
    }
}
