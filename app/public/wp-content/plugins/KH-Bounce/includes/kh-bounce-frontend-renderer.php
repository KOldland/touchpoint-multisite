<?php
/**
 * Frontend renderer for the modal.
 */
class KH_Bounce_Frontend_Renderer {

    /** @var KH_Bounce_Plugin */
    protected $plugin;

    public function __construct( KH_Bounce_Plugin $plugin ) {
        $this->plugin = $plugin;

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_modal' ) );
    }

    public function enqueue_assets() {
        if ( ! $this->should_render() ) {
            return;
        }

        wp_enqueue_style( 'kh-bounce-frontend', KH_BOUNCE_URL . 'assets/css/frontend.css', array(), KH_BOUNCE_VERSION );
        wp_enqueue_script( 'kh-bounce-frontend', KH_BOUNCE_URL . 'assets/js/frontend.js', array( 'jquery' ), KH_BOUNCE_VERSION, true );

        wp_localize_script( 'kh-bounce-frontend', 'khBounceSettings', array(
            'template'       => $this->plugin->setting( 'template' ),
            'telemetryMode'  => $this->plugin->setting( 'telemetry_mode', 'none' ),
            'restEndpoint'   => rest_url( 'kh-bounce/v1/event' ),
            'restNonce'      => wp_create_nonce( 'wp_rest' ),
            'impressionArgs' => array(
                'template' => $this->plugin->setting( 'template' ),
                'status'   => $this->plugin->setting( 'status' ),
            ),
            'forceShow'      => $this->should_force_show(),
            'testMode'       => ( '1' === $this->plugin->setting( 'test_mode', '0' ) ),
        ) );
    }

    protected function should_render() {
        if ( 'on' !== $this->plugin->setting( 'status' ) && ! $this->should_force_show() ) {
            return false;
        }

        if ( '1' === $this->plugin->setting( 'display_on_home' ) && ! is_front_page() && ! $this->should_force_show() ) {
            return false;
        }

        if ( $this->is_mobile_blocked() ) {
            return false;
        }

        return true;
    }

    public function render_modal() {
        if ( ! $this->should_render() ) {
            return;
        }

        $template = $this->plugin->setting( 'template', 'classic' );
        $settings = array(
            'title'         => $this->plugin->setting( 'title' ),
            'text'          => $this->plugin->setting( 'text' ),
            'cta_label'     => $this->plugin->setting( 'cta_label' ),
            'cta_url'       => $this->plugin->setting( 'cta_url' ),
            'dismiss_label' => $this->plugin->setting( 'dismiss_label', __( 'Dismiss', 'kh-bounce' ) ),
            'title_id'      => 'kh-bounce-modal-title',
            'text_id'       => 'kh-bounce-modal-desc',
        );
        ?>
        <div class="kh-bounce-modal underlay" data-template="<?php echo esc_attr( $template ); ?>" aria-hidden="true">
            <div class="kh-bounce-modal-flex kh-bounce-modal-flex-activated">
                <div class="kh-bounce-modal-sub template-<?php echo esc_attr( $template ); ?>" role="dialog" aria-modal="true" aria-labelledby="kh-bounce-modal-title" aria-describedby="kh-bounce-modal-desc">
                    <button type="button" class="kh-bounce-close" aria-label="<?php esc_attr_e( 'Close modal', 'kh-bounce' ); ?>">&times;</button>
                    <?php echo KH_Bounce_Templates::render( $template, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        </div>
        <?php
    }
    protected function should_force_show() {
        if ( '1' !== $this->plugin->setting( 'test_mode', '0' ) ) {
            return false;
        }

        if ( isset( $_GET['kh-bounce-test'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return true;
        }

        return is_user_logged_in() && current_user_can( 'manage_options' );
    }

    protected function is_mobile_blocked() {
        if ( '1' === $this->plugin->setting( 'show_on_mobile', '0' ) ) {
            return false;
        }

        if ( $this->should_force_show() ) {
            return false;
        }

        return function_exists( 'wp_is_mobile' ) && wp_is_mobile();
    }
}
