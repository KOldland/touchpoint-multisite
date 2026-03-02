<?php
$schedule_id = isset( $data['schedule_id'] ) ? absint( $data['schedule_id'] ) : 0;
$sponsor_id  = isset( $data['sponsor_id'] ) ? absint( $data['sponsor_id'] ) : 0;
$user_phase  = isset( $data['user_phase'] ) ? sanitize_text_field( (string) $data['user_phase'] ) : 'default';
?>

<div class="khm-landing-page" style="border:1px solid #ccd0d4;border-radius:8px;padding:24px;background:#fff;max-width:760px;">
    <h1><?php esc_html_e( 'Join Membership', 'khm-membership' ); ?></h1>
    <p><?php esc_html_e( 'Start your membership and continue to secure checkout.', 'khm-membership' ); ?></p>

    <div class="khm-landing-meta" style="margin:12px 0 20px;color:#50575e;">
        <?php if ( $schedule_id > 0 ) : ?>
            <div><?php printf( esc_html__( 'Schedule: #%d', 'khm-membership' ), $schedule_id ); ?></div>
        <?php endif; ?>
        <?php if ( $sponsor_id > 0 ) : ?>
            <div><?php printf( esc_html__( 'Sponsor: #%d', 'khm-membership' ), $sponsor_id ); ?></div>
        <?php endif; ?>
        <div><?php printf( esc_html__( 'Phase: %s', 'khm-membership' ), esc_html( $user_phase ) ); ?></div>
    </div>

    <form id="khm-landing-signup-form" novalidate>
        <label for="khm-landing-email"><?php esc_html_e( 'Email address', 'khm-membership' ); ?></label>
        <input type="email" id="khm-landing-email" name="email" autocomplete="email" required style="display:block;width:100%;max-width:420px;padding:10px;margin:6px 0 6px;border:1px solid #8c8f94;border-radius:4px;">
        <div id="khm-landing-email-error" role="alert" style="display:none;color:#b32d2e;margin-bottom:8px;"></div>

        <label style="display:flex;gap:8px;align-items:flex-start;margin:12px 0;">
            <input type="checkbox" id="khm-landing-consent" name="consent" value="1" required>
            <span><?php esc_html_e( 'I agree to share campaign attribution data for signup analytics and support.', 'khm-membership' ); ?></span>
        </label>
        <div id="khm-landing-consent-error" role="alert" style="display:none;color:#b32d2e;margin-bottom:8px;"></div>

        <button type="submit" id="khm-landing-submit" style="padding:10px 16px;border:0;border-radius:4px;background:#2271b1;color:#fff;cursor:pointer;">
            <?php esc_html_e( 'Continue to Checkout', 'khm-membership' ); ?>
        </button>
    </form>

    <div id="khm-landing-response" role="status" aria-live="polite" style="margin-top:14px;"></div>

    <div id="khm-landing-success" style="display:none;margin-top:18px;padding:14px;border:1px solid #72aee6;background:#f0f6fc;border-radius:6px;">
        <h2 style="margin:0 0 10px;"><?php esc_html_e( 'You are almost done', 'khm-membership' ); ?></h2>
        <p id="khm-landing-success-copy" style="margin:0 0 10px;"><?php esc_html_e( 'We are redirecting you to secure checkout.', 'khm-membership' ); ?></p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="<?php echo esc_url( home_url( '/account/' ) ); ?>" class="button"><?php esc_html_e( 'Go to Account', 'khm-membership' ); ?></a>
            <a href="<?php echo esc_url( home_url( '/support/' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Contact Support', 'khm-membership' ); ?></a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('khm-landing-signup-form');
    if (!form) {
        return;
    }

    const emailInput = document.getElementById('khm-landing-email');
    const consentInput = document.getElementById('khm-landing-consent');
    const emailError = document.getElementById('khm-landing-email-error');
    const consentError = document.getElementById('khm-landing-consent-error');
    const responseEl = document.getElementById('khm-landing-response');
    const successEl = document.getElementById('khm-landing-success');
    const submitEl = document.getElementById('khm-landing-submit');

    const ERROR_MAP = {
        MBR_ERR_100: 'Please enter a valid email address.',
        MBR_ERR_101: 'Please choose a membership plan.',
        MBR_ERR_105: 'This email is already linked to another account.',
        MBR_ERR_107: 'You already have an active membership.',
        MBR_ERR_200: 'Checkout is not configured yet. Please contact support.',
        MBR_ERR_201: 'Membership pricing is unavailable right now.',
        MBR_ERR_203: 'Payment setup failed. Please retry.',
    };

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function clearErrors() {
        emailError.style.display = 'none';
        emailError.textContent = '';
        consentError.style.display = 'none';
        consentError.textContent = '';
        responseEl.textContent = '';
    }

    function setError(el, message) {
        el.textContent = message;
        el.style.display = 'block';
    }

    function setBusy(isBusy) {
        submitEl.disabled = isBusy;
        submitEl.textContent = isBusy ? 'Processing...' : 'Continue to Checkout';
    }

    function mapFriendlyError(payload) {
        if (!payload || typeof payload !== 'object') {
            return 'Something went wrong. Please try again.';
        }
        if (payload.code && ERROR_MAP[payload.code]) {
            return ERROR_MAP[payload.code];
        }
        if (payload.message) {
            return String(payload.message).replace(/_/g, ' ');
        }
        if (payload.error) {
            return String(payload.error);
        }
        return 'Something went wrong. Please try again.';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearErrors();

        const email = emailInput.value.trim();
        const consent = !!consentInput.checked;

        if (!email || !emailInput.checkValidity()) {
            setError(emailError, 'Please enter a valid email address.');
            emailInput.focus();
            return;
        }

        if (!consent) {
            setError(consentError, 'Please confirm consent to continue.');
            consentInput.focus();
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const payload = {
            email: email,
            plan_id: 1,
            schedule_id: <?php echo wp_json_encode( $schedule_id ); ?>,
            sponsor_id: <?php echo wp_json_encode( $sponsor_id ); ?>,
            utm_source: params.get('utm_source') || '',
            utm_medium: params.get('utm_medium') || '',
            utm_campaign: params.get('utm_campaign') || '',
            phase_at_click: <?php echo wp_json_encode( $user_phase ); ?>,
            idempotency_key: uuid(),
            consent: consent
        };

        setBusy(true);

        fetch('/wp-json/kh-membership/v1/signup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.redirect_url) {
                    successEl.style.display = 'block';
                    responseEl.textContent = 'Redirecting to checkout...';
                    setTimeout(function () {
                        window.location.href = data.redirect_url;
                    }, 800);
                    return;
                }

                if (data && data.success) {
                    successEl.style.display = 'block';
                    responseEl.textContent = 'Signup completed.';
                    return;
                }

                const supportCode = data && data.support_code ? ' Support Code: ' + data.support_code : '';
                responseEl.textContent = mapFriendlyError(data) + supportCode;
            })
            .catch(function () {
                responseEl.textContent = 'Network issue while contacting checkout. Please retry.';
            })
            .finally(function () {
                setBusy(false);
            });
    });
});
</script>
