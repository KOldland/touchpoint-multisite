const assert = require('node:assert/strict');
const path = require('node:path');

const approvals = require(path.resolve(__dirname, '../../assets/js/pending-approvals.js'));

const row = approvals.normalizeRow({
    schedule_id: 'sched_123',
    variant_id: 'var_123',
    post_title: 'March sponsor launch',
    variant_preview: 'Preview text for reviewer context.',
    sponsor_name: 'Acme Sponsor',
    submitter: 'editor@example.com',
    requested_schedule_date: '2026-03-11T09:00:00Z',
    approval_status: 'pending',
    compliance_status: 'WARN',
    compliance_reason: 'Performance claim requires sponsor sign-off.',
    can_approve: true
});

assert.equal(row.schedule_id, 'sched_123');
assert.equal(row.compliance_status, 'WARN');

const html = approvals.renderRow(row);
assert.match(html, /March sponsor launch/);
assert.match(html, /Performance claim requires sponsor sign-off/);
assert.match(html, /Approve/);

const bulkPayload = approvals.buildDecisionPayload('approve', ['sched_123', 'sched_124'], 'Bulk note');
assert.equal(bulkPayload.endpoint, '/approve');
assert.deepEqual(bulkPayload.body.schedule_ids, ['sched_123', 'sched_124']);
assert.equal(bulkPayload.body.review_notes, 'Bulk note');

assert.equal(
    approvals.formatHistoryTimestamp('2026-03-11T09:00:00Z'),
    '2026-03-11 09:00:00'
);

console.log('pending-approvals.test.js: PASS');
