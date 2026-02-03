<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Portal Voucher Widget
 * Renders a voucher redemption form for gifted articles.
 */
class PortalVoucher_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_portal_voucher';
    }

    public function get_title() {
        return __( 'Portal Voucher', 'khm-membership' );
    }

    public function get_icon() {
        return 'eicon-ticket';
    }

    public function get_categories() {
        return [ 'touchpoint' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Voucher', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'heading',
            [
                'label' => __( 'Heading', 'khm-membership' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Redeem Gift Voucher', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'description',
            [
                'label' => __( 'Description', 'khm-membership' ),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __( 'Enter a voucher code to add a gifted article to your library.', 'khm-membership' ),
            ]
        );

        $this->add_control(
            'button_label',
            [
                'label' => __( 'Button Label', 'khm-membership' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Redeem Voucher', 'khm-membership' ),
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if ( ! is_user_logged_in() ) {
            echo '<p class="khm-portal-login-required">' . esc_html__( 'Please log in to redeem a voucher.', 'khm-membership' ) . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $this->enqueue_portal_styles();
        $this->enqueue_portal_scripts();

        $input_id = 'khm-voucher-code-' . esc_attr( $this->get_id() );
        ?>
        <div class="khm-portal-voucher">
            <div class="khm-dashboard-section khm-voucher-section">
                <h3 class="khm-subsection-title"><?php echo esc_html( $settings['heading'] ?? '' ); ?></h3>
                <p class="khm-section-desc"><?php echo esc_html( $settings['description'] ?? '' ); ?></p>
                <form class="khm-form khm-voucher-form" data-voucher-form>
                    <div class="khm-form-row">
                        <label for="<?php echo esc_attr( $input_id ); ?>"><?php esc_html_e( 'Voucher Code', 'khm-membership' ); ?></label>
                        <input type="text" id="<?php echo esc_attr( $input_id ); ?>" class="khm-voucher-code" placeholder="<?php esc_attr_e( 'Paste your voucher code', 'khm-membership' ); ?>" required>
                    </div>
                    <div class="khm-voucher-actions">
                        <button type="submit" class="khm-save-btn khm-voucher-btn">
                            <?php echo esc_html( $settings['button_label'] ?? '' ); ?>
                        </button>
                        <span class="khm-form-message" role="status" aria-live="polite"></span>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    private function enqueue_portal_styles() {
        $css_path = plugin_dir_path( dirname( dirname( __DIR__ ) ) ) . 'assets/css/portal-widgets.css';
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style(
                'khm-portal-widgets',
                plugin_dir_url( dirname( dirname( __DIR__ ) ) ) . 'assets/css/portal-widgets.css',
                [],
                filemtime( $css_path )
            );
        }
    }

    private function enqueue_portal_scripts() {
        $js_path = plugin_dir_path( dirname( dirname( __DIR__ ) ) ) . 'assets/js/portal-widgets.js';
        if ( file_exists( $js_path ) ) {
            wp_enqueue_script(
                'khm-portal-widgets',
                plugin_dir_url( dirname( dirname( __DIR__ ) ) ) . 'assets/js/portal-widgets.js',
                [ 'jquery' ],
                filemtime( $js_path ),
                true
            );

            wp_localize_script( 'khm-portal-widgets', 'khmPortalWidgets', [
                'restUrl' => esc_url_raw( rest_url( 'khm/v1/portal/' ) ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'shareNonce' => wp_create_nonce( 'khm_library_nonce' ),
                'strings' => [
                    'loading' => __( 'Loading...', 'khm-membership' ),
                    'error' => __( 'An error occurred. Please try again.', 'khm-membership' ),
                ],
            ] );
        }
    }
}
