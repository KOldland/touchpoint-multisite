<?php
$esc = static function ( $value ): string {
    if ( function_exists( 'esc_html' ) ) {
        return esc_html( (string) $value );
    }

    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
};

$escAttr = static function ( $value ): string {
    if ( function_exists( 'esc_attr' ) ) {
        return esc_attr( (string) $value );
    }

    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
};

$sessionId = isset( $data['session_id'] ) ? (string) $data['session_id'] : '';
$endpoint = isset( $data['landing_success_endpoint'] ) ? (string) $data['landing_success_endpoint'] : '/wp-json/kh-membership/v1/landing-success';
$telemetryEndpoint = isset( $data['telemetry_endpoint'] ) ? (string) $data['telemetry_endpoint'] : '/wp-json/kh-membership/v1/landing-telemetry';
$support = isset( $data['support_contact'] ) ? (string) $data['support_contact'] : '';
?>

<section class="khm-success-page" data-session-id="<?php echo $escAttr( $sessionId ); ?>" data-success-endpoint="<?php echo $escAttr( $endpoint ); ?>" data-telemetry-endpoint="<?php echo $escAttr( $telemetryEndpoint ); ?>" data-support-contact="<?php echo $escAttr( $support ); ?>">
    <header class="khm-success-header">
        <h1 id="khm-success-title"><?php echo $esc( 'Membership confirmation' ); ?></h1>
        <p class="khm-success-subtitle"><?php echo $esc( 'We are finalizing your membership details.' ); ?></p>
    </header>

    <div class="khm-success-live" aria-live="polite"></div>

    <div class="khm-success-content" role="region" aria-labelledby="khm-success-title">
        <p><?php echo $esc( 'This page will update automatically when your status is available.' ); ?></p>
    </div>

    <div class="khm-success-actions" role="navigation" aria-label="Success actions"></div>

    <div class="khm-success-print">
        <button type="button" class="khm-success-print-btn" data-cta-action="external" data-cta-name="Print this page"><?php echo $esc( 'Print / Save as PDF' ); ?></button>
    </div>

    <p class="khm-success-fallback" hidden>
        <?php echo $esc( 'We could not load your full confirmation details. Please contact support:' ); ?>
        <strong><?php echo $esc( $support ); ?></strong>
        <span class="khm-support-code"></span>
    </p>
</section>
