const path = require('node:path');

const priceReview = require(path.resolve(__dirname, '../../assets/js/price-review.js'));

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
            },
            {
                getAttribute(name) {
                    return name === 'data-key' ? 'reporting' : 'Reporting';
                },
                querySelector() {
                    return { value: '2500' };
                }
            }
        ];
    }
};

const payload = priceReview.buildSavePayload(fakeDocument);
const total = payload.items.reduce((sum, item) => sum + item.amount_cents, 0);

console.log('Phase 3 price review demo');
console.log('Reference: ' + payload.reference_id);
console.log('Currency: ' + payload.currency);
console.log('Items: ' + payload.items.length);
console.log('Total cents: ' + total);
