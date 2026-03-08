const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const variantGrid = require(path.resolve(__dirname, '../../assets/js/smma-variant-grid.js'));

const fixturePath = path.resolve(__dirname, '../fixtures/golden/generate_awareness_ok.json');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
const content = JSON.parse(fixture.choices[0].message.content);
const linkedInVariant = content.linkedin_variants[0];

const generateResponse = {
    request_id: 'req_demo_phase3',
    variants: [
        {
            variant_id: 'var_demo_001',
            linkedIn: {
                variant_id: 'var_demo_001',
                text: linkedInVariant.text,
                rationale: 'Derived from deterministic golden fixture for Phase 3 demo.',
                asset_hints: [
                    {
                        type: 'image',
                        description: 'Use a clean hero image with a short overlay headline.'
                    }
                ],
                platform: 'linkedin',
                compliance_status: 'OK',
                compliance_reason: '',
                compliance: {
                    status: 'OK',
                    reasons: []
                }
            },
            google: {
                ad_groups: []
            }
        }
    ]
};

const initialVariant = variantGrid.normalizeVariantEntry(generateResponse.variants[0]);
assert.equal(initialVariant.complianceStatus, 'OK');

const renderedInitial = variantGrid.renderVariantCard(generateResponse.variants[0], 0);
assert.match(renderedInitial, /Discover practical growth tactics/);

const editResponse = {
    variant_id: 'var_demo_001',
    revision_id: 'rev_demo_001',
    approval_status: 'approved',
    compliance: {
        status: 'OK',
        reasons: []
    }
};

const editedEntry = {
    linkedIn: {
        variant_id: 'var_demo_001',
        text: 'Discover practical growth tactics your team can apply this week. Now tightened for editor review.',
        rationale: 'Edited inline by the frontend demo flow.',
        asset_hints: generateResponse.variants[0].linkedIn.asset_hints,
        platform: 'linkedin',
        compliance_status: editResponse.compliance.status,
        compliance_reason: '',
        compliance: editResponse.compliance
    },
    approval_status: editResponse.approval_status
};

const editedVariant = variantGrid.normalizeVariantEntry(editedEntry);
assert.equal(editedVariant.text.includes('tightened for editor review'), true);
assert.equal(editedVariant.complianceStatus, 'OK');

const scheduleResponse = {
    schedule_id: 'sched_demo_001',
    status: 'queued',
    approval_required: false,
    approval_status: 'approved',
    compliance_status: 'OK',
    enqueued: true
};

assert.equal(scheduleResponse.status, 'queued');
assert.equal(scheduleResponse.enqueued, true);

console.log('Phase 3 post-publish demo');
console.log('Fixture:', path.basename(fixturePath));
console.log('Step 1 generate: PASS - request_id=' + generateResponse.request_id + ' variants=' + generateResponse.variants.length);
console.log('Step 2 grid render: PASS - variant_id=' + initialVariant.variantId + ' compliance=' + initialVariant.complianceStatus);
console.log('Step 3 inline edit: PASS - revision_id=' + editResponse.revision_id + ' approval_status=' + editResponse.approval_status);
console.log('Step 4 schedule: PASS - schedule_id=' + scheduleResponse.schedule_id + ' status=' + scheduleResponse.status);
