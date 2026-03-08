(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
        return;
    }

    root.KHMCheckoutUiHelpers = factory();
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    function text(value) {
        if (value == null) {
            return '';
        }
        return String(value).trim();
    }

    function getPromoErrorMessage(payload, fallback) {
        if (!payload || typeof payload !== 'object') {
            return fallback || 'Invalid promotion code.';
        }

        if (payload.code === 'MBR_ERR_INVALID_PROMO') {
            return text(payload.message) || 'Invalid promotion code.';
        }

        if (payload.error && typeof payload.error === 'object') {
            if (payload.error.code === 'MBR_ERR_INVALID_PROMO') {
                return text(payload.error.message) || 'Invalid promotion code.';
            }
            if (payload.error.message) {
                return text(payload.error.message);
            }
        }

        if (payload.data && typeof payload.data === 'object') {
            if (payload.data.code === 'MBR_ERR_INVALID_PROMO') {
                return text(payload.data.message) || 'Invalid promotion code.';
            }
            if (payload.data.message) {
                return text(payload.data.message);
            }
        }

        if (payload.message) {
            return text(payload.message);
        }

        return fallback || 'Invalid promotion code.';
    }

    function buildLandingPayload(values) {
        values = values || {};
        var consent = !!values.consent;

        return {
            schedule_id: text(values.schedule_id),
            sponsor_id: text(values.sponsor_id) || null,
            utm_source: consent ? (text(values.utm_source) || null) : null,
            utm_medium: consent ? (text(values.utm_medium) || null) : null,
            utm_campaign: consent ? (text(values.utm_campaign) || null) : null,
            phase_at_click: consent ? (text(values.phase_at_click) || null) : null,
            idempotency_key: text(values.idempotency_key),
            consent: consent,
            client_reference: text(values.client_reference) || null,
            plan_id: text(values.plan_id) || null,
            promo_code: text(values.promo_code) || null,
            profile_marketing_optin: !!values.profile_marketing_optin
        };
    }

    function buildSuccessContent(payload) {
        payload = payload || {};
        if (!payload.consent) {
            return {
                headline: 'Membership confirmed',
                body: 'Your membership is confirmed. Tracking details were not stored because consent was not provided.',
                showAttribution: false
            };
        }

        return {
            headline: 'Membership confirmed',
            body: 'Your membership is confirmed and attribution details are available below.',
            showAttribution: true
        };
    }

    function normalizePriceReviewPayload(payload) {
        payload = payload || {};
        var items = Array.isArray(payload.items) ? payload.items : [];

        return {
            reference_id: text(payload.reference_id) || 'default',
            currency: text(payload.currency) || 'AUD',
            items: items.map(function (item) {
                return {
                    key: text(item.key),
                    label: text(item.label),
                    amount_cents: parseInt(item.amount_cents, 10) || 0
                };
            })
        };
    }

    return {
        text: text,
        getPromoErrorMessage: getPromoErrorMessage,
        buildLandingPayload: buildLandingPayload,
        buildSuccessContent: buildSuccessContent,
        normalizePriceReviewPayload: normalizePriceReviewPayload
    };
}));
