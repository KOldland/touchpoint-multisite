import http from 'k6/http';
import { check, group } from 'k6';

export const options = {
  vus: 1,
  iterations: 1,
};

const baseUrl = __ENV.CANARY_BASE_URL || 'https://staging.example.com';

export default function () {
  group('landing-success', () => {
    const res = http.get(`${baseUrl}/wp-json/kh-membership/v1/landing-success?session_id=cs_test_canary`);
    check(res, { 'landing success reachable': (r) => [200, 404].includes(r.status) });
  });

  group('smma generate route', () => {
    const res = http.post(`${baseUrl}/wp-json/kh-smma/v1/generate`, JSON.stringify({ post_id: 1, blocks_summary: 'canary smoke', num_variants: 1 }), {
      headers: { 'Content-Type': 'application/json' },
    });
    check(res, { 'generate route responds deterministically': (r) => [200, 401, 403].includes(r.status) });
  });
}
