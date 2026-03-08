const assert = require('node:assert/strict');
const path = require('node:path');

const modal = require(path.resolve(__dirname, '../../assets/js/calendar-modal.js'));

const rows = modal.buildRows([
    { variantId: 'var_1', text: 'First variant body' },
    { variantId: 'var_2', text: 'Second variant body' }
], function () {
    return new Date('2026-03-08T09:00:00Z');
});

assert.equal(rows.length, 2);
assert.equal(rows[0].variantId, 'var_1');
assert.equal(rows[0].recommended, '2026-03-08T10:00');

const payload = modal.buildRequestPayload(
    { variantId: 'var_warn_01' },
    {
        sponsorId: 'sp_123',
        scheduleTime: '2026-03-10T10:00',
        budgetCents: 2500,
        channel: 'linkedin',
        prioritize: 'reach',
        timezone: 'Australia/Sydney',
        geoTargets: ['AU', 'US'],
        mode: 'sandbox'
    },
    {
        defaultCurrency: 'AUD',
        defaultChannel: 'linkedin'
    }
);

assert.equal(payload.variant_id, 'var_warn_01');
assert.equal(payload.sponsor_id, 'sp_123');
assert.equal(payload.boost_options.budget_cents, 2500);
assert.deepEqual(payload.boost_options.geo_overrides, ['AU', 'US']);
assert.equal(payload.metadata.timezone, 'Australia/Sydney');

const summary = modal.summarizeResponses([
    { status: 'pending_approval' },
    { status: 'queued' },
    { status: 'queued' }
]);

assert.deepEqual(summary, {
    pendingApprovalCount: 1,
    queuedCount: 2,
    rejectedCount: 0
});

console.log('calendar-modal.test.js: PASS');
