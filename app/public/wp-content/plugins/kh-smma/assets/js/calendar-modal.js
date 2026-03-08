(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory(root);
        return;
    }

    root.KHSmmaCalendarModal = factory(root);
}(typeof window !== 'undefined' ? window : globalThis, function (root) {
    'use strict';

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function normalizeTimezone(timezone) {
        return String(timezone || 'UTC');
    }

    function recommendedTime(index, nowProvider) {
        var now = typeof nowProvider === 'function' ? nowProvider() : new Date();
        var next = new Date(now.getTime() + ((index + 1) * 3600 * 1000));
        return next.toISOString().slice(0, 16);
    }

    function buildRows(variants, nowProvider) {
        return (variants || []).map(function (variant, index) {
            return {
                variantId: String(variant.variantId || variant.variant_id || ''),
                preview: String((variant.text || '').slice(0, 90)),
                recommended: recommendedTime(index, nowProvider)
            };
        });
    }

    function buildRequestPayload(variant, formValues, defaults) {
        defaults = defaults || {};
        formValues = formValues || {};

        return {
            variant_id: String(variant.variantId || variant.variant_id || ''),
            sponsor_id: String(formValues.sponsorId || ''),
            schedule_time: new Date(formValues.scheduleTime).toISOString(),
            boost_options: {
                budget_cents: parseInt(formValues.budgetCents, 10) || 0,
                currency: String(formValues.currency || defaults.defaultCurrency || 'AUD'),
                channels: [String(formValues.channel || defaults.defaultChannel || 'linkedin')],
                prioritize: String(formValues.prioritize || 'reach'),
                geo_overrides: formValues.geoTargets || []
            },
            approval_required: !!formValues.approvalRequired,
            metadata: {
                timezone: normalizeTimezone(formValues.timezone),
                trace_label: String(formValues.traceLabel || 'phase3-schedule')
            },
            mode: String(formValues.mode || 'sandbox')
        };
    }

    function summarizeResponses(responses) {
        var pending = 0;
        var queued = 0;
        var rejected = 0;

        (responses || []).forEach(function (response) {
            if (!response) {
                return;
            }
            if (String(response.status || '') === 'pending_approval') {
                pending += 1;
                return;
            }
            if (String(response.status || '') === 'queued') {
                queued += 1;
                return;
            }
            rejected += 1;
        });

        return {
            pendingApprovalCount: pending,
            queuedCount: queued,
            rejectedCount: rejected
        };
    }

    return {
        buildRows: buildRows,
        buildRequestPayload: buildRequestPayload,
        summarizeResponses: summarizeResponses,
        normalizeTimezone: normalizeTimezone,
        esc: esc
    };
}));
