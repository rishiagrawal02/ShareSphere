/**
 * Node.js Native Runner for api_reads.js load test (Phase 18 Milestone 18.2)
 *
 * Runs 20 concurrent VUs over 20 seconds against PHP worker pool.
 * Measures latency percentiles (min, avg, p50, p95, p99, max) and error rate.
 */

const http = require('http');

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const DURATION_SEC = 20;
const CONCURRENCY = 20;

const agent = new http.Agent({
  keepAlive: true,
  maxSockets: 50,
});

async function request(path, options = {}) {
  const url = new URL(path, BASE_URL);
  const start = performance.now();

  return new Promise((resolve) => {
    const req = http.request(
      url,
      {
        agent,
        method: options.method || 'GET',
        headers: options.headers || {},
      },
      (res) => {
        let body = '';
        res.on('data', (chunk) => (body += chunk));
        res.on('end', () => {
          const duration = performance.now() - start;
          const cookies = res.headers['set-cookie'] || [];
          resolve({
            status: res.statusCode,
            duration,
            body,
            cookies,
          });
        });
      }
    );

    req.on('error', (err) => {
      const duration = performance.now() - start;
      resolve({ status: 500, duration, error: err.message });
    });

    if (options.body) {
      req.write(options.body);
    }
    req.end();
  });
}

async function loginUser() {
  // 1. Get CSRF token
  const csrfRes = await request('/api/auth/csrf-token');
  let csrfToken = '';
  let cookieHeader = '';

  if (csrfRes.status === 200) {
    try {
      const data = JSON.parse(csrfRes.body);
      csrfToken = data.csrf_token || data.data?.csrf_token || '';
      if (csrfRes.cookies && csrfRes.cookies.length > 0) {
        cookieHeader = csrfRes.cookies.map((c) => c.split(';')[0]).join('; ');
      }
    } catch (e) {}
  }

  // 2. Perform Login
  const loginBody = JSON.stringify({
    email: 'perf-donor-1@sharesphere.test',
    password: 'PerfPass123!',
  });

  const loginRes = await request('/api/auth/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
      'Cookie': cookieHeader,
    },
    body: loginBody,
  });

  let sessionCookie = cookieHeader;
  if (loginRes.cookies && loginRes.cookies.length > 0) {
    sessionCookie = loginRes.cookies.map((c) => c.split(';')[0]).join('; ');
  }

  return { csrfToken, sessionCookie };
}

async function runWorker(workerId, auth, deadline, results) {
  const endpoints = [
    '/api/donations?limit=20',
    '/api/matches?donation_id=5&limit=20',
    '/api/notifications',
    '/api/health',
  ];

  while (Date.now() < deadline) {
    const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];
    const res = await request(endpoint, {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-Token': auth.csrfToken,
        'Cookie': auth.sessionCookie,
      },
    });

    results.push({
      endpoint,
      status: res.status,
      duration: res.duration,
      success: res.status >= 200 && res.status < 400,
    });

    // Pacing delay (10-30ms)
    await new Promise((r) => setTimeout(r, 10 + Math.random() * 20));
  }
}

async function main() {
  console.log('=== ShareSphere API Reads Concurrent Benchmark (Spec §14 / M18.2) ===');
  console.log(`Target: ${BASE_URL}`);
  console.log(`Concurrency: ${CONCURRENCY} VUs`);
  console.log(`Duration: ${DURATION_SEC} seconds\n`);

  console.log('Authenticating test user...');
  const auth = await loginUser();
  console.log('Authenticated successfully.\n');

  console.log(`Starting ${CONCURRENCY} concurrent workers...`);
  const deadline = Date.now() + DURATION_SEC * 1000;
  const results = [];

  const workers = [];
  for (let i = 0; i < CONCURRENCY; i++) {
    workers.push(runWorker(i, auth, deadline, results));
  }

  await Promise.all(workers);

  // Compute metrics
  const total = results.length;
  const successful = results.filter((r) => r.success).length;
  const failed = total - successful;
  const errorRate = (failed / total) * 100;

  const durations = results.map((r) => r.duration).sort((a, b) => a - b);
  const min = durations[0];
  const max = durations[total - 1];
  const avg = durations.reduce((a, b) => a + b, 0) / total;
  const p50 = durations[Math.floor(total * 0.5)];
  const p90 = durations[Math.floor(total * 0.9)];
  const p95 = durations[Math.floor(total * 0.95)];
  const p99 = durations[Math.floor(total * 0.99)];
  const rps = (total / DURATION_SEC).toFixed(1);

  console.log('\n--- Load Test Results ---');
  console.log(`Total Requests:    ${total}`);
  console.log(`Throughput:        ${rps} req/sec`);
  console.log(`Successful:        ${successful} (${(100 - errorRate).toFixed(2)}%)`);
  console.log(`Failed Requests:   ${failed} (${errorRate.toFixed(2)}%)`);
  console.log(`Min Latency:       ${min.toFixed(2)} ms`);
  console.log(`Average Latency:   ${avg.toFixed(2)} ms`);
  console.log(`Median (p50):      ${p50.toFixed(2)} ms`);
  console.log(`p90 Latency:       ${p90.toFixed(2)} ms`);
  console.log(`p95 Latency:       ${p95.toFixed(2)} ms (Target: < 300 ms)`);
  console.log(`p99 Latency:       ${p99.toFixed(2)} ms`);
  console.log(`Max Latency:       ${max.toFixed(2)} ms\n`);

  const p95TargetMet = p95 < 300;
  const errorTargetMet = errorRate < 1.0;

  console.log(`p95 Target (< 300 ms): ${p95TargetMet ? 'PASSED' : 'FAILED'}`);
  console.log(`Error Rate (< 1.0%):   ${errorTargetMet ? 'PASSED' : 'FAILED'}`);

  const fs = require('fs');
  const summary = {
    totalRequests: total,
    successfulRequests: successful,
    failedRequests: failed,
    throughputRps: Number(rps),
    errorRatePercent: Number(errorRate.toFixed(2)),
    minMs: Number(min.toFixed(2)),
    avgMs: Number(avg.toFixed(2)),
    p50Ms: Number(p50.toFixed(2)),
    p90Ms: Number(p90.toFixed(2)),
    p95Ms: Number(p95.toFixed(2)),
    p99Ms: Number(p99.toFixed(2)),
    maxMs: Number(max.toFixed(2)),
    p95TargetMet,
    errorTargetMet,
  };

  fs.writeFileSync('docs/perf-api-reads-results.json', JSON.stringify(summary, null, 2));
  console.log('Saved results to docs/perf-api-reads-results.json');
}

main().catch(console.error);
