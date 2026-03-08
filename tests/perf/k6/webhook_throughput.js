import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: Number(__ENV.K6_VUS || 50),
  duration: __ENV.K6_DURATION || '2m',
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<500'],
  },
};

const baseUrl = __ENV.K6_WEBHOOK_BASE_URL || 'https://staging.example.com';
const payload = JSON.stringify({ id: 'evt_k6_webhook_001', type: 'checkout.session.completed' });

export default function () {
  const res = http.post(`${baseUrl}/wp-json/kh-membership/v1/webhook/stripe`, payload, {
    headers: {
      'Content-Type': 'application/json',
      'Stripe-Signature': __ENV.K6_STRIPE_SIGNATURE || 'k6-fixture-signature',
    },
  });

  check(res, {
    'accepted or rejected cleanly': (r) => [200, 202, 400, 401, 429].includes(r.status),
  });

  sleep(1);
}
