const assert = require('node:assert/strict');
const path = require('node:path');

const variantGrid = require(path.resolve(__dirname, '../../assets/js/smma-variant-grid.js'));

const entry = {
    variant_id: 'variant-top-level',
    linkedIn: {
        variant_id: 'var_123',
        text: 'Discover practical growth tactics your team can apply this week.',
        rationale: 'Matches awareness content for a post-publish demo.',
        asset_hints: [
            { type: 'image', description: 'Product hero with headline overlay' }
        ],
        platform: 'linkedin',
        compliance_status: 'WARN',
        compliance_reason: 'Needs sponsor review',
        compliance: {
            status: 'WARN',
            reasons: ['Needs sponsor review']
        }
    },
    approval_status: 'pending_approval'
};

const normalized = variantGrid.normalizeVariantEntry(entry);

assert.equal(normalized.variantId, 'var_123');
assert.equal(normalized.platform, 'linkedin');
assert.equal(normalized.complianceStatus, 'WARN');
assert.equal(normalized.complianceReason, 'Needs sponsor review');
assert.equal(normalized.assetHints.length, 1);
assert.match(normalized.assetHints[0].description, /Product hero/);

const html = variantGrid.renderVariantCard(entry, 0);
assert.match(html, /khm-smma-inline-edit-btn/);
assert.match(html, /khm-smma-inline-schedule-btn/);
assert.match(html, /Needs sponsor review/);
assert.match(html, /Product hero with headline overlay/);

const fallbackHints = variantGrid.normalizeAssetHints({
    image_aspect: '1.91:1',
    alt_text: 'Suggested preview'
});

assert.equal(fallbackHints.length, 1);
assert.equal(fallbackHints[0].type, 'image');
assert.equal(fallbackHints[0].description, 'Suggested preview');

console.log('variant-grid.test.js: PASS');
