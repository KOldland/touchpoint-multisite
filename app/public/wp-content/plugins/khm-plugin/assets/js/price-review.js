(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory(null, root, root.KHMCheckoutUiHelpers || require('./checkout-ui-helpers.js'));
        return;
    }

    root.KHMPriceReview = factory(root.jQuery, root, root.KHMCheckoutUiHelpers || {});
}(typeof window !== 'undefined' ? window : globalThis, function ($, root, helpers) {
    'use strict';

    function collectItems(doc) {
        doc = doc || root.document;
        var rows = doc ? doc.querySelectorAll('[data-price-review-row]') : [];
        return Array.prototype.map.call(rows, function (row) {
            return {
                key: row.getAttribute('data-key') || '',
                label: row.getAttribute('data-label') || '',
                amount_cents: parseInt((row.querySelector('input') || {}).value, 10) || 0
            };
        });
    }

    function buildSavePayload(doc) {
        doc = doc || root.document;
        var reference = doc.querySelector('[name="reference_id"]');
        var currency = doc.querySelector('[name="currency"]');
        return (helpers.normalizePriceReviewPayload || function (payload) { return payload; })({
            reference_id: reference ? reference.value : 'default',
            currency: currency ? currency.value : 'AUD',
            items: collectItems(doc)
        });
    }

    function init() {
        if (!$ || !root.document) {
            return;
        }

        var button = root.document.getElementById('khm-price-review-save');
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            var payload = buildSavePayload(root.document);
            var status = root.document.getElementById('khm-price-review-status');
            button.disabled = true;
            if (status) {
                status.textContent = 'Saving overrides...';
            }

            fetch((root.khmPriceReview || {}).endpoint || '/wp-json/kh-membership/v1/price-override', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': (root.khmPriceReview || {}).nonce || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            }).then(function (result) {
                if (status) {
                    status.textContent = result.ok ? 'Overrides saved.' : ((result.body && result.body.message) || 'Unable to save overrides.');
                }
            }).catch(function () {
                if (status) {
                    status.textContent = 'Unable to save overrides.';
                }
            }).finally(function () {
                button.disabled = false;
            });
        });
    }

    if ($) {
        $(init);
    }

    return {
        collectItems: collectItems,
        buildSavePayload: buildSavePayload,
        init: init
    };
}));
