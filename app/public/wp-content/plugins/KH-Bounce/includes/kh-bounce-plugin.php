<?php
/**
 * Core plugin loader.
 */
class KH_Bounce_Plugin {

    /**
     * Cached settings array.
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Frontend controller.
     *
     * @var KH_Bounce_Frontend_Renderer
     */
    protected $frontend;

    /**
     * Admin controller.
     *
     * @var KH_Bounce_Admin_Settings
     */
    protected $admin;

    public function __construct() {
        add_action( 'init', array( $this, 'load_textdomain' ) );

        if ( is_admin() ) {
            $this->admin = new KH_Bounce_Admin_Settings( $this );
        }

        $this->frontend = new KH_Bounce_Frontend_Renderer( $this );

        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'kh-bounce', false, basename( dirname( KH_BOUNCE_PLUGIN_FILE ) ) . '/languages' );
    }

    public function get_settings() {
        $defaults = $this->get_default_settings( did_action( 'init' ) );

        $settings = get_option( 'kh_bounce_settings', array() );

        return wp_parse_args( $settings, $defaults );
    }

    public function save_settings( array $settings ) {
        $settings = wp_parse_args( $settings, $this->get_settings() );
        update_option( 'kh_bounce_settings', $settings );
        $this->settings = $settings;
    }

    public function refresh_settings() {
        $this->settings = $this->get_settings();
        return $this->settings;
    }

    public function setting( $key, $default = '' ) {
        if ( empty( $this->settings ) ) {
            $this->settings = $this->get_settings();
        }
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
    }

    public function register_rest_routes() {
        register_rest_route( 'kh-bounce/v1', '/event', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle_rest_event' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function handle_rest_event( WP_REST_Request $request ) {
        if ( 'rest' !== $this->setting( 'telemetry_mode', 'none' ) ) {
            return new WP_REST_Response( array( 'status' => 'skipped' ), 202 );
        }

        if ( ! $this->check_rate_limit() ) {
            return new WP_Error( 'rate_limited', __( 'Too many telemetry events. Please slow down.', 'kh-bounce' ), array( 'status' => 429 ) );
        }

        $event = $this->sanitize_telemetry_value( $request->get_param( 'event' ), 'event' );
        $template = $this->sanitize_telemetry_value( $request->get_param( 'template' ), 'template' );

        do_action( 'kh_bounce_telemetry', array(
            'event'    => $event,
            'template' => $template,
            'user'     => get_current_user_id(),
            'time'     => current_time( 'mysql' ),
        ) );

        return array( 'status' => 'ok' );
    }

    /**
     * Default settings with optional translations (only after init to avoid JIT warning).
     */
    protected function get_default_settings( $translate = false ) {
        $defaults = array(
            'status'          => 'on',
            'template'        => 'classic',
            'title'           => 'Wait! Before you go...',
            'text'            => 'Join our marketing insiders newsletter and get instant access to playbooks.',
            'cta_label'       => 'Get the Playbook',
            'cta_url'         => home_url( '/newsletter/' ),
            'dismiss_label'   => 'No thanks',
            'display_on_home' => '1',
            'show_on_mobile'  => '0',
            'test_mode'       => '0',
            'telemetry_mode'  => 'none',
        );

        if ( $translate ) {
            $defaults['title']         = __( 'Wait! Before you go...', 'kh-bounce' );
            $defaults['text']          = __( 'Join our marketing insiders newsletter and get instant access to playbooks.', 'kh-bounce' );
            $defaults['cta_label']     = __( 'Get the Playbook', 'kh-bounce' );
            $defaults['dismiss_label'] = __( 'No thanks', 'kh-bounce' );
        }

        return $defaults;
    }

    /**
     * Simple IP-based rate limiter for telemetry POSTs.
     */
    protected function check_rate_limit() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        $key = 'kh_bounce_rate_' . md5( $ip );
        $window = 60; // seconds
        $limit  = 30; // events per window

        $data = get_transient( $key );
        $now  = time();

        if ( empty( $data ) || ! isset( $data['start'] ) || ( $now - $data['start'] ) > $window ) {
            set_transient( $key, array( 'start' => $now, 'count' => 1 ), $window );
            return true;
        }

        if ( $data['count'] >= $limit ) {
            return false;
        }

        $data['count']++;
        set_transient( $key, $data, $window );
        return true;
    }

    /**
     * Strictly sanitize telemetry values to prevent PII leakage and injection.
     */
    protected function sanitize_telemetry_value( $value, $default = '' ) {
        $value = is_string( $value ) ? $value : '';
        $value = substr( $value, 0, 64 );
        if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
            return $default;
        }
        return $value;
    }
}
