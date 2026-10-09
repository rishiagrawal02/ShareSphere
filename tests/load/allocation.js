import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 50,
  duration: '10s',
  thresholds: {
    http_req_failed: ['rate<0.80'], // Expected 409s when stock is exhausted
    http_req_duration: ['p(95)<500'],
  },
};

const BASE_URL = __ENV.APP_URL || 'http://localhost:8000';

export default function () {
  // In load test, 50 VUs concurrently request 3 units each from a target donation
  const payload = JSON.stringify({
    donation_id: __ENV.TARGET_DONATION_ID || 1,
    requested_quantity: 3,
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': __ENV.CSRF_TOKEN || 'test-token',
    },
  };

  const res = http.post(`${BASE_URL}/api/requests`, payload, params);

  // Valid response is either 201 Created or 409 Conflict (stock exhausted)
  check(res, {
    'status is 201 or 409': (r) => r.status === 201 || r.status === 409,
    'never returns 500 error': (r) => r.status !== 500,
  });

  sleep(0.1);
}
