<?php

namespace KHM\Membership;

class LandingPageShortcode {
    public function __construct() {
        if ( function_exists( 'add_shortcode' ) ) {
            add_shortcode( 'khm_landing_page', [ $this, 'render_shortcode' ] );
        }
    }

    public function render_shortcode( $atts ) {
        $defaults = [
            'schedule_id' => '',
            'sponsor_id' => '',
        ];
        $atts = $this->shortcode_atts_safe( $defaults, is_array( $atts ) ? $atts : [] );

        $scheduleId = isset( $_GET['schedule_id'] )
            ? $this->sanitize_identifier( (string) $this->unslash( $_GET['schedule_id'] ) )
            : $this->sanitize_identifier( (string) $atts['schedule_id'] );

        $sessionId = isset( $_GET['session_id'] )
            ? $this->sanitize_identifier( (string) $this->unslash( $_GET['session_id'] ) )
            : '';

        $sponsorId = isset( $_GET['sponsor_id'] )
            ? $this->sanitize_identifier( (string) $this->unslash( $_GET['sponsor_id'] ) )
            : $this->sanitize_identifier( (string) $atts['sponsor_id'] );

        if ( '' !== $sessionId ) {
            $this->enqueue_script();
            $this->enqueue_style();

            $successData = [
                'session_id' => $sessionId,
                'landing_success_endpoint' => function_exists( 'rest_url' ) ? rest_url( 'kh-membership/v1/landing-success' ) : '/wp-json/kh-membership/v1/landing-success',
                'telemetry_endpoint' => function_exists( 'rest_url' ) ? rest_url( 'kh-membership/v1/landing-telemetry' ) : '/wp-json/kh-membership/v1/landing-telemetry',
                'support_contact' => get_option( 'admin_email', '' ),
            ];

            ob_start();
            $templatePath = dirname( __DIR__, 2 ) . '/templates/success.php';
            $this->include_template( $templatePath, $successData );
            return (string) ob_get_clean();
        }

        if ( '' === $scheduleId ) {
            return '<div class="khm-landing-page-error">Missing required schedule_id.</div>';
        }

        $schedule = $this->resolve_schedule_data( $scheduleId );
        $sponsor = $this->resolve_sponsor_data( $sponsorId );
        $sponsorName = ! empty( $sponsor['name'] ) ? (string) $sponsor['name'] : '';

        $utmSource = isset( $_GET['utm_source'] ) ? sanitize_text_field( (string) $this->unslash( $_GET['utm_source'] ) ) : '';
        $utmMedium = isset( $_GET['utm_medium'] ) ? sanitize_text_field( (string) $this->unslash( $_GET['utm_medium'] ) ) : '';
        $utmCampaign = isset( $_GET['utm_campaign'] ) ? sanitize_text_field( (string) $this->unslash( $_GET['utm_campaign'] ) ) : '';

        $this->emit_telemetry( 'landing.view', [
            'schedule_id' => $scheduleId,
            'sponsor_id' => $sponsorId,
            'utm_source' => $utmSource,
            'source' => 'landing',
        ] );

        $this->enqueue_script();
        $this->enqueue_style();

        $data = [
            'schedule' => $schedule,
            'sponsor' => $sponsor,
            'schedule_id' => $scheduleId,
            'sponsor_id' => $sponsorId,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'phase_at_click' => 'landing',
            'referred_by' => $sponsorName ?: $utmSource,
            'signup_init_endpoint' => function_exists( 'rest_url' ) ? rest_url( 'kh-membership/v1/signup-init' ) : '/wp-json/kh-membership/v1/signup-init',
        ];

        ob_start();
        $templatePath = dirname( __DIR__, 2 ) . '/templates/landing.php';
        $this->include_template( $templatePath, $data );
        return (string) ob_get_clean();
    }

    private function include_template( $template_path, $data = [] ) {
        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    private function shortcode_atts_safe( array $defaults, array $atts ): array {
        if ( function_exists( 'shortcode_atts' ) ) {
            return shortcode_atts( $defaults, $atts );
        }

        return array_merge( $defaults, $atts );
    }

    private function sanitize_identifier( string $value ): string {
        $value = preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
        return substr( (string) $value, 0, 128 );
    }

    private function resolve_schedule_data( string $scheduleId ): array {
        $numericId = $this->extract_numeric_id( $scheduleId );
        $title = '';
        $recommended = '';
        $boostCopy = '';

        if ( $numericId > 0 && function_exists( 'get_post' ) ) {
            $post = get_post( $numericId );
            if ( is_object( $post ) && isset( $post->post_title ) ) {
                $title = sanitize_text_field( (string) $post->post_title );
            }

            if ( function_exists( 'get_post_meta' ) ) {
                $recommended = sanitize_text_field( (string) get_post_meta( $numericId, 'recommended_post_time', true ) );
                $boostCopy = sanitize_text_field( (string) get_post_meta( $numericId, 'boost_copy', true ) );
            }
        }

        $schedule = [
            'id' => $scheduleId,
            'title' => $title,
            'recommended_post_time' => $recommended,
            'boost_copy' => $boostCopy,
        ];

        return apply_filters( 'khm_membership_landing_schedule_data', $schedule, $scheduleId );
    }

    private function resolve_sponsor_data( string $sponsorId ): array {
        if ( '' === $sponsorId ) {
            return [
                'id' => null,
                'name' => '',
                'logo_url' => '',
                'accent_color' => '',
                'blurb' => '',
            ];
        }

        $numericId = $this->extract_numeric_id( $sponsorId );
        $name = '';
        if ( $numericId > 0 ) {
            global $wpdb;
            $table = $wpdb->prefix . 'khm_sponsors';
            $row = $wpdb->get_row(
                $wpdb->prepare( "SELECT id, name FROM {$table} WHERE id = %d LIMIT 1", $numericId ),
                ARRAY_A
            );
            if ( is_array( $row ) ) {
                $name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
            }
        }

        $logoUrl = (string) get_option( 'khm_sponsor_logo_' . $sponsorId, '' );
        $accent = sanitize_text_field( (string) get_option( 'khm_sponsor_accent_' . $sponsorId, '' ) );
        $blurb = (string) get_option( 'khm_sponsor_blurb_' . $sponsorId, '' );

        $allowedBlurb = [
            'a' => [ 'href' => [], 'target' => [], 'rel' => [] ],
            'strong' => [],
            'em' => [],
            'p' => [],
            'br' => [],
            'span' => [ 'class' => [] ],
        ];

        $sponsor = [
            'id' => $sponsorId,
            'name' => $name,
            'logo_url' => function_exists( 'esc_url_raw' ) ? esc_url_raw( $logoUrl ) : filter_var( $logoUrl, FILTER_SANITIZE_URL ),
            'accent_color' => preg_match( '/^#[A-Fa-f0-9]{6}$/', $accent ) ? $accent : '',
            'blurb' => function_exists( 'wp_kses' ) ? wp_kses( $blurb, $allowedBlurb ) : strip_tags( $blurb, '<a><strong><em><p><br><span>' ),
        ];

        $sponsor = apply_filters( 'khm_membership_landing_sponsor_data', $sponsor, $sponsorId );
        if ( is_array( $sponsor ) ) {
            $sponsor['blurb'] = function_exists( 'wp_kses' )
                ? wp_kses( (string) ( $sponsor['blurb'] ?? '' ), $allowedBlurb )
                : strip_tags( (string) ( $sponsor['blurb'] ?? '' ), '<a><strong><em><p><br><span>' );
            $sponsor['accent_color'] = preg_match( '/^#[A-Fa-f0-9]{6}$/', (string) ( $sponsor['accent_color'] ?? '' ) )
                ? (string) $sponsor['accent_color']
                : '';
            $sponsor['name'] = sanitize_text_field( (string) ( $sponsor['name'] ?? '' ) );
        }

        return is_array( $sponsor ) ? $sponsor : [
            'id' => $sponsorId,
            'name' => '',
            'logo_url' => '',
            'accent_color' => '',
            'blurb' => '',
        ];
    }

    private function extract_numeric_id( string $value ): int {
        if ( preg_match( '/(\d+)/', $value, $matches ) ) {
            return absint( $matches[1] );
        }
        return 0;
    }

    private function enqueue_script(): void {
        if ( ! function_exists( 'wp_enqueue_script' ) ) {
            return;
        }

        $mainFile = dirname( __DIR__, 2 ) . '/khm-plugin.php';
        $helperUrl = plugin_dir_url( $mainFile ) . 'assets/js/checkout-ui-helpers.js';
        $scriptUrl = plugin_dir_url( $mainFile ) . 'assets/js/landing.js';
        wp_enqueue_script( 'khm-checkout-ui-helpers', $helperUrl, [], '1.0.0', true );
        wp_enqueue_script( 'khm-membership-landing', $scriptUrl, [ 'khm-checkout-ui-helpers' ], '1.0.0', true );
    }

    private function enqueue_style(): void {
        if ( ! function_exists( 'wp_enqueue_style' ) ) {
            return;
        }

        $mainFile = dirname( __DIR__, 2 ) . '/khm-plugin.php';
        $styleUrl = plugin_dir_url( $mainFile ) . 'assets/css/landing.css';
        wp_enqueue_style( 'khm-membership-landing', $styleUrl, [], '1.0.0' );
    }

    private function emit_telemetry( string $metric, array $context = [] ): void {
        do_action( 'khm_membership_landing_telemetry', $metric, $context );
        error_log( 'KHM landing ' . $metric . ' ' . wp_json_encode( $context ) );
    }

    private function unslash( $value ) {
        if ( function_exists( 'wp_unslash' ) ) {
            return wp_unslash( $value );
        }

        return is_string( $value ) ? stripslashes( $value ) : $value;
    }
}
