<?php
$schedule = isset( $data['schedule'] ) && is_array( $data['schedule'] ) ? $data['schedule'] : [];
$sponsor = isset( $data['sponsor'] ) && is_array( $data['sponsor'] ) ? $data['sponsor'] : [];

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

$escUrl = static function ( $value ): string {
    if ( function_exists( 'esc_url' ) ) {
        return esc_url( (string) $value );
    }
    return htmlspecialchars( filter_var( (string) $value, FILTER_SANITIZE_URL ), ENT_QUOTES, 'UTF-8' );
};

$accentColor = isset( $sponsor['accent_color'] ) ? (string) $sponsor['accent_color'] : '';
if ( ! preg_match( '/^#[A-Fa-f0-9]{6}$/', $accentColor ) ) {
    $accentColor = '#2271b1';
}

$referredBy = isset( $data['referred_by'] ) ? (string) $data['referred_by'] : '';
$signupInitEndpoint = isset( $data['signup_init_endpoint'] ) ? (string) $data['signup_init_endpoint'] : '/wp-json/kh-membership/v1/signup-init';
$logoUrl = isset( $sponsor['logo_url'] ) ? (string) $sponsor['logo_url'] : '';
$logoAlt = ! empty( $sponsor['name'] ) ? (string) $sponsor['name'] . ' logo' : 'Sponsor logo';

$blurbRaw = isset( $sponsor['blurb'] ) ? (string) $sponsor['blurb'] : '';
$blurb = function_exists( 'wp_kses' )
    ? wp_kses( $blurbRaw, [
        'a' => [ 'href' => [], 'target' => [], 'rel' => [] ],
        'strong' => [],
        'em' => [],
        'p' => [],
        'br' => [],
        'span' => [ 'class' => [] ],
    ] )
    : strip_tags( $blurbRaw, '<a><strong><em><p><br><span>' );
?>

<div class="khm-landing-page" style="--khm-sponsor-accent: <?php echo $escAttr( $accentColor ); ?>; border:1px solid #ccd0d4;border-radius:8px;padding:24px;background:#fff;max-width:760px;">
    <h1 style="margin-top:0;"><?php echo $esc( $schedule['title'] ?? 'Join Membership' ); ?></h1>

    <?php if ( ! empty( $schedule['recommended_post_time'] ) ) : ?>
        <p><strong>Recommended post time:</strong> <?php echo $esc( $schedule['recommended_post_time'] ); ?></p>
    <?php endif; ?>

    <?php if ( ! empty( $schedule['boost_copy'] ) ) : ?>
        <p><?php echo $esc( $schedule['boost_copy'] ); ?></p>
    <?php endif; ?>

    <?php if ( $logoUrl ) : ?>
        <div style="margin:12px 0;">
            <img src="<?php echo $escUrl( $logoUrl ); ?>" alt="<?php echo $escAttr( $logoAlt ); ?>" style="max-width:160px;height:auto;">
        </div>
    <?php endif; ?>

    <?php if ( $blurb ) : ?>
        <div class="khm-landing-sponsor-blurb" style="margin:8px 0 14px;">
            <?php echo $blurb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    <?php endif; ?>

    <?php if ( $referredBy !== '' ) : ?>
        <p class="khm-landing-referral" style="margin:6px 0 14px;color:#50575e;">Referred by <?php echo $esc( $referredBy ); ?></p>
    <?php endif; ?>

    <form class="khm-landing-form" novalidate>
        <input type="hidden" name="schedule_id" value="<?php echo $escAttr( (string) ( $data['schedule_id'] ?? '' ) ); ?>">
        <input type="hidden" name="sponsor_id" value="<?php echo $escAttr( (string) ( $data['sponsor_id'] ?? '' ) ); ?>">
        <input type="hidden" name="utm_source" value="<?php echo $escAttr( (string) ( $data['utm_source'] ?? '' ) ); ?>">
        <input type="hidden" name="utm_medium" value="<?php echo $escAttr( (string) ( $data['utm_medium'] ?? '' ) ); ?>">
        <input type="hidden" name="utm_campaign" value="<?php echo $escAttr( (string) ( $data['utm_campaign'] ?? '' ) ); ?>">
        <input type="hidden" name="phase_at_click" value="<?php echo $escAttr( (string) ( $data['phase_at_click'] ?? 'landing' ) ); ?>">
        <input type="hidden" name="signup_init_endpoint" value="<?php echo $escAttr( $signupInitEndpoint ); ?>">

        <label for="khm-consent" style="display:flex;gap:8px;align-items:flex-start;margin:12px 0;">
            <input type="checkbox" id="khm-consent" name="consent" value="1" aria-label="Consent to attribution tracking">
            <span>I consent to attribution tracking for campaign measurement.</span>
        </label>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="khm-landing-cta" data-action="join" data-plan-id="" style="padding:10px 16px;border:0;border-radius:4px;background:var(--khm-sponsor-accent);color:#fff;cursor:pointer;">Join</button>
            <button type="button" class="khm-landing-cta" data-action="subscribe" data-plan-id="" style="padding:10px 16px;border:1px solid #8c8f94;border-radius:4px;background:#fff;cursor:pointer;">Subscribe</button>
            <button type="button" class="khm-landing-cta" data-action="claim_offer" data-plan-id="" style="padding:10px 16px;border:1px solid #8c8f94;border-radius:4px;background:#fff;cursor:pointer;">Claim Offer</button>
        </div>

        <p class="khm-landing-status" role="status" aria-live="polite" style="margin-top:12px;"></p>
        <p class="khm-landing-error" role="alert" aria-live="assertive" style="color:#b32d2e;margin-top:8px;"></p>
    </form>
</div>
