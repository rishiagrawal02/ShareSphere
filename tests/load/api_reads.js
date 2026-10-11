import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * ShareSphere API Reads Load Test (Phase 18 Milestone 18.2)
 *
 * Exercises concurrent read load:
 *   - 20 Concurrent Virtual Users (VUs)
 *   - 60 seconds duration
 *   - Balanced mix of GET /api/donations, /api/matches, /api/notifications
 *
 * Performance Targets (Spec §14):
 *   - http_req_duration{p(95)} < 300 ms
 *   - http_req_failed < 0.01 (< 1% error rate)
 */

export const options = {
  vus: 20,
  duration: '60s',
  thresholds: {
    'http_req_duration': ['p(95)<300'], // p95 response time must be under 300ms
    'http_req_failed': ['rate<0.01'],    // Error rate must be under 1%
  },
};

const BASE_URL = __ENV.APP_URL || 'http://127.0.0.1:8000';
const TEST_EMAIL = __ENV.PERF_USER_EMAIL || 'perf-donor-1@sharesphere.test';
const TEST_PASSWORD = __ENV.PERF_USER_PASSWORD || 'PerfPass123!';
const TARGET_DONATION_ID = __ENV.TARGET_DONATION_ID || '5';

export function setup() {
  // Obtain CSRF token
  const csrfRes = http.get(`${BASE_URL}/api/auth/csrf-token`);
  let csrfToken = '';
  if (csrfRes.status === 200) {
    try {
      const csrfBody = JSON.parse(csrfRes.body);
      csrfToken = csrfBody.csrf_token || '';
    } catch (e) {
      // fallback
    }
  }

  // Authenticate user to obtain session cookie
  const loginPayload = JSON.stringify({
    email: TEST_EMAIL,
    password: TEST_PASSWORD,
  });

  const loginRes = http.post(`${BASE_URL}/api/auth/login`, loginPayload, {
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
    },
  });

  return {
    cookies: loginRes.cookies,
    csrfToken: csrfToken,
  };
}

export default function (data) {
  const params = {
    headers: {
      'Accept': 'application/json',
      'X-CSRF-Token': data.csrfToken,
    },
    cookies: data.cookies,
  };

  // Weighted distribution: 45% /api/donations, 35% /api/matches, 20% /api/notifications
  const rand = Math.random();

  if (rand < 0.45) {
    const res = http.get(`${BASE_URL}/api/donations?limit=20&page=1`, params);
    check(res, {
      'donations status is 200': (r) => r.status === 200,
      'donations latency < 300ms': (r) => r.timings.duration < 300,
    });
  } else if (rand < 0.80) {
    const res = http.get(`${BASE_URL}/api/matches?donation_id=${TARGET_DONATION_ID}&limit=20`, params);
    check(res, {
      'matches status is 200': (r) => r.status === 200,
      'matches latency < 300ms': (r) => r.timings.duration < 300,
    });
  } else {
    const res = http.get(`${BASE_URL}/api/notifications`, params);
    check(res, {
      'notifications status is 200': (r) => r.status === 200,
      'notifications latency < 300ms': (r) => r.timings.duration < 300,
    });
  }

  sleep(0.05 + Math.random() * 0.1); // Pacing between requests (50-150ms)
}
