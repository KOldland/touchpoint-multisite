<?php
namespace KH\XAPI\Shortcodes;

class ReportsShortcode {
    public function hooks(): void {
        add_shortcode( 'kh_xapi_reports', [ $this, 'render' ] );
    }

    public function render(): string {
        if ( ! current_user_can( 'read' ) ) {
            return '';
        }

        ob_start();
        $form = KH_XAPI_PATH . '/addons/reports/form.php';
        if ( file_exists( $form ) ) {
            include $form;
        }

        return ob_get_clean() ?: '';
    }
}
