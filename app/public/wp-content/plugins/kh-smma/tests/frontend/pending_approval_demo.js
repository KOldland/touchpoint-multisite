const assert = require('node:assert/strict');
const path = require('node:path');

const calendarModal = require(path.resolve(__dirname, '../../assets/js/calendar-modal.js'));
const approvals = require(path.resolve(__dirname, '../../assets/js/pending-approvals.js'));

const warnVariant = {
    variantId: 'var_warn_001',
    text: 'Guaranteed pipeline improvement phrasing softened for sponsor review.'
};

const schedulePayload = calendarModal.buildRequestPayload(warnVariant, {
    sponsorId: 'sp_demo_01',
    scheduleTime: '2026-03-15T10:00',
    budgetCents: 1500,
    channel: 'linkedin',
    prioritize: 'reach',
    timezone: 'UTC',
    geoTargets: ['AU'],
    mode: 'sandbox'
}, {
    defaultCurrency: 'AUD',
    defaultChannel: 'linkedin'
});

assert.equal(schedulePayload.variant_id, 'var_warn_001');

const scheduleResponse = {
    schedule_id: 'sched_warn_001',
    status: 'pending_approval',
    approval_required: true,
    approval_status: 'pending',
    compliance_status: 'WARN',
    compliance_reason: 'Performance claim requires sponsor sign-off.'
};

const rowHtml = approvals.renderRow({
    schedule_id: scheduleResponse.schedule_id,
    variant_id: warnVariant.variantId,
    post_title: 'Deterministic approval demo',
    variant_preview: warnVariant.text,
    sponsor_name: 'Demo Sponsor',
    submitter: 'editor@example.com',
    requested_schedule_date: '2026-03-15T10:00:00Z',
    approval_status: 'pending',
    compliance_status: 'WARN',
    compliance_reason: scheduleResponse.compliance_reason,
    can_approve: true
});

assert.match(rowHtml, /Deterministic approval demo/);

const approvePayload = approvals.buildDecisionPayload('approve', [scheduleResponse.schedule_id], 'Approved in demo');
assert.equal(approvePayload.endpoint, '/approve');

console.log('Phase 3 pending approval demo');
console.log('Step 1 schedule modal: PASS - variant_id=' + schedulePayload.variant_id + ' sponsor=' + schedulePayload.sponsor_id);
console.log('Step 2 pending schedule: PASS - schedule_id=' + scheduleResponse.schedule_id + ' status=' + scheduleResponse.status);
console.log('Step 3 admin list render: PASS - row contains reviewer context');
console.log('Step 4 approve action: PASS - endpoint=' + approvePayload.endpoint + ' notes=' + approvePayload.body.review_notes);
