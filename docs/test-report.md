# ShareSphere Test & Performance Report (Phase 18)

**Generated:** 2026-10-11  
**Target Environment:** Local Windows Dev & Linux CI (GitHub Actions)  
**Playbook Phase:** Phase 18 – Testing & Performance  

---

## 1. Environment & Runtime Specifications

| Component | Version / Details | Notes |
|---|---|---|
| **Operating System** | Windows 11 Enterprise (64-bit) / Ubuntu 22.04 LTS (CI) | Dual-platform compatibility verified |
| **PHP Runtime** | PHP 8.2.12 (CLI) | Strict types enabled, PDO pgsql, openssl, sodium |
| **PHPUnit** | 10.5.66 | Configured via `phpunit.xml` with separate suites |
| **Database** | PostgreSQL 16.4 with PostGIS 3.4 | Dedicated test database `sharesphere_test` (port 5433) |
| **Node.js** | v22.19.0 | npm v10.x |
| **Frontend Test Runner** | Vitest v2.1.9 | jsdom environment, React Testing Library |
| **Frontend Bundler** | Vite v6.4.4 | Production builds verified |

---

## 2. Milestone 18.1 – Test-Gap Closure, Role Matrix & Regression Gate

### 2.1 Automated Test Suites Summary

| Test Suite | Type | Tests | Assertions / Status | Duration |
|---|---|---|---|---|
| **PHPUnit Unit Suite** | Unit (isolated) | 64 tests | 425 assertions | ~0.8s |
| **PHPUnit Integration Suite** | Integration (real DB + HTTP stack) | 169 tests | 27,222 assertions | ~2m 45s |
| **Total Backend PHPUnit** | Unit + Integration | **233 tests** | **27,647 assertions (100% Pass)** | ~2m 51s |
| **Frontend Vitest Suite** | Components, Pages, Flows, A11y | **114 tests** (8 files) | **114 passed (100% Pass)** | ~9.9s |
| **Frontend Bundle Build** | Production Build (`vite build`) | – | **Zero errors, 4 assets generated** | ~9.9s |

---

### 2.2 Role × Route Access Matrix (`RoleRouteMatrixTest.php`)

- **Objective:** T-18.1-02 (S) and T-18.1-03 (N). Auto-iterates all registered endpoints in `src/routes.php` and tests access across the 6 core user personas:
  1. `anonymous` (Unauthenticated visitor)
  2. `donor` (Active authenticated donor)
  3. `ngo-pending` (NGO pending admin verification)
  4. `ngo-verified` (Fully approved NGO)
  5. `admin` (System administrator)
  6. `suspended` (Suspended user account)
- **Matrix Contract:** Defined in `tests/Support/expected_access.php`.
- **Results:**
  - Registered Routes Tested: 38 endpoints (6 HTTP methods: GET, POST, PUT, PATCH, DELETE, OPTIONS).
  - Assertions Run: 836 assertions.
  - Unregistered route detection verified: Adding a route without a corresponding policy entry immediately fails the build.
  - Result: **PASS (100%)**.

---

### 2.3 Status-Code Matrix (`StatusCodeMatrixTest.php`)

- **Objective:** T-18.1-04 (P). Proves all required standard error status codes are triggered through the real HTTP stack with standardized JSON envelopes (`success: false`, `error: {code, message}`, `request_id`).

| HTTP Status | Error Code | Test Trigger | Result |
|---|---|---|---|
| **401 Unauthorized** | `UNAUTHENTICATED` | Unauthenticated request to `/api/auth/me` | Pass |
| **403 Forbidden** | `INSUFFICIENT_ROLE` | Authenticated donor accessing `/api/admin/categories` | Pass |
| **404 Not Found** | `NOT_FOUND` | Dispatching unmapped route `/api/nonexistent-route-xyz-404` | Pass |
| **405 Method Not Allowed** | `METHOD_NOT_ALLOWED` | POST request to GET-only `/api/health` | Pass |
| **409 Conflict** | `EMAIL_TAKEN` | Duplicate user registration on `/api/auth/register` | Pass |
| **413 Payload Too Large** | `PAYLOAD_TOO_LARGE` | Request payload exceeding 1 MB limit | Pass |
| **415 Unsupported Media Type** | `UNSUPPORTED_MEDIA_TYPE` | Content-type other than application/json or form-data | Pass |
| **422 Unprocessable Entity** | `VALIDATION_FAILED` | Validation failure on `/api/auth/register` (short name, password mismatch) | Pass |
| **429 Too Many Requests** | `RATE_LIMITED` | Rate limit threshold exceeded (`Retry-After` header present) | Pass |

- Total Assertions: 84 assertions.
- Result: **PASS (100%)**.

---

### 2.4 Verification Scripts & CI Pipeline

- `scripts/verify.sh` & `scripts/verify.ps1`:
  1. `[1/5]` Composer package validation (`composer validate --strict`)
  2. `[2/5]` Backend Unit Tests (`vendor/bin/phpunit --testsuite Unit`)
  3. `[3/5]` Backend Integration Tests (`vendor/bin/phpunit --testsuite Integration`)
  4. `[4/5]` Frontend Tests (`npm run test:run`)
  5. `[5/5]` Frontend Production Build (`npm run build`)
- `scripts/verify-full.sh`:
  - Extends standard verification with concurrency testsuite and E2E runner.
- `.github/workflows/ci.yml`:
  - Multi-job GitHub Actions workflow with PostgreSQL + PostGIS service container.
  - Runs PHPUnit (Unit + Integration) and Vitest + Vite build on every push and PR.

---

## 3. Milestone 18.2 – Performance Measurement

*(To be populated during Milestone 18.2 execution)*

- Pune ~120km Geolocation Matching Latency Benchmark (100k records)
- Index Analysis (GiST vs Sequential)
- API Read Latency (k6 Load Test)
- Frontend Interactivity / Lighthouse Metrics

---

## 4. Milestone 18.3 – End-to-End Browser Tests

*(To be populated during Milestone 18.3 execution)*

- Playwright E2E User Journeys
- Cross-Browser & Mobile Viewport Smoke Tests
