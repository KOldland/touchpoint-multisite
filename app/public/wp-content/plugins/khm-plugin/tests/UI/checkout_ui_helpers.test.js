const assert = require('node:assert/strict');
const path = require('node:path');

const helpers = require(path.resolve(__dirname, '../../assets/js/checkout-ui-helpers.js'));
const priceReview = require(path.resolve(__dirname, '../../assets/js/price-review.js'));

assert.equal(
    helpers.getPromoErrorMessage({ code: 'MBR_ERR_INVALID_PROMO', message: 'Invalid promotion code.' }, 'fallback'),
    'Invalid promotion code.'
);

const landingPayload = helpers.buildLandingPayload({
    schedule_id: 'sched_123',
    sponsor_id: 'sp_456',
    utm_source: 'linkedin',
    utm_medium: 'paid_social',
    utm_campaign: 'awareness',
    phase_at_click: 'landing',
    idempotency_key: '123e4567-e89b-12d3-a456-426614174000',
    consent: false,
    client_reference: 'join',
    promo_code: 'WELCOME10',
    profile_marketing_optin: true
});

assert.equal(landingPayload.consent, false);
assert.equal(landingPayload.utm_source, null);
assert.equal(landingPayload.promo_code, 'WELCOME10');
assert.equal(landingPayload.profile_marketing_optin, true);

const successContent = helpers.buildSuccessContent({ consent: false });
assert.equal(successContent.showAttribution, false);
assert.match(successContent.body, /Tracking details were not stored/);

const fakeDocument = {
    querySelector(selector) {
        if (selector === '[name="reference_id"]') {
            return { value: 'demo-price-review' };
        }
        if (selector === '[name="currency"]') {
            return { value: 'AUD' };
        }
        return null;
    },
    querySelectorAll() {
        return [
            {
                getAttribute(name) {
                    return name === 'data-key' ? 'creative_setup' : 'Creative setup';
                },
                querySelector() {
                    return { value: '4500' };
                }
            },
            {
                getAttribute(name) {
                    return name === 'data-key' ? 'campaign_management' : 'Campaign management';
                },
                querySelector() {
                    return { value: '8500' };
                }
            }
        ];
    }
};

const pricePayload = priceReview.buildSavePayload(fakeDocument);
assert.equal(pricePayload.reference_id, 'demo-price-review');
assert.equal(pricePayload.currency, 'AUD');
assert.equal(pricePayload.items.length, 2);
assert.equal(pricePayload.items[0].amount_cents, 4500);

console.log('checkout_ui_helpers.test.js: PASS');
