# ShareSphere – Phase-Wise Development Playbook

**Project:** ShareSphere – Smart Community Donation & NGO Matching Platform
**Source spec:** *ShareSphere Complete Project Description v1.0 (Oct 2026)*
**Stack (from spec):** React 18 + Vite + React Router · PHP 8.2+ REST API · PostgreSQL 15/16 + PostGIS 3.3+ · Leaflet/OpenStreetMap · SMTP email · private file storage
**How to use:** Work top to bottom. Never start a milestone until every milestone it depends on is marked DONE and tagged in Git.

---

## 0. Read This First

### 0.1 Missing / ambiguous information (and the default I used)

Nothing below blocks you from starting. Phase 0–8 run entirely on the defaults. **D-1 and D-2 must be confirmed before Phase 9**, **D-10 before Milestone 0.2**, and **D-4/D-13 before Phase 21**. If you change a default, update Milestone 1.1 (the contract) *first*, because every later milestone reads from it.

| ID | Question the spec leaves open | Default used in this playbook | Needed before |
|---|---|---|---|
| D-1 | Does a *pending* request reserve stock, or only an accepted one? Spec §6.8/§7.3 creates request + allocation atomically at request time, but also has donor accept/reject. | **Reserve at request time.** Creating a request atomically creates a `donation_requests` row (pending) + an `allocations` row (reserved) and decrements `donations.available_quantity`. Reject/cancel/expiry returns the stock. | Phase 9 |
| D-2 | What makes a handover *Completed*? (Spec §6.9: "if two-party confirmation is included".) | **Two-party:** donor enters OTP → `collected`; NGO then confirms receipt → `completed`. | Phase 10 |
| D-3 | Tailwind or Bootstrap? | **Tailwind CSS** (spec says pick one). | Phase 12 |
| D-4 | Where will the demo be hosted? | **Single Linux VPS** (Nginx + PHP-FPM + Postgres/PostGIS, Let's Encrypt). | Phase 21 |
| D-5 | Geocoding in scope? | **No.** Map-picker click + manual lat/lng/address text only. Geocoding stays optional/future (spec §6.7). | – |
| D-6 | Password reset in scope? | **No** (spec: "may be added"). Listed as known limitation. | – |
| D-7 | NGO ↔ user relationship | **1 NGO user account = 1 NGO organisation** (`ngos.user_id` UNIQUE). | Phase 1 |
| D-8 | How are admins created? | **Never via public registration.** Created by CLI `php backend/bin/create-admin.php`. | Phase 3 |
| D-9 | Location privacy rule | Public/NGO-facing coordinates are **snapped to a 0.005° grid (~550 m)**. Exact coordinates and address visible only to the owner, admins, and the NGO on an *accepted* request. | Phase 6 |
| D-10 | Is Docker allowed on your dev machine? | **Yes** – Docker Compose runs Postgres+PostGIS and Mailpit. If not, install PostgreSQL 16 + PostGIS locally and run Mailpit as a binary. | Milestone 0.2 |
| D-11 | Git host / repo name | **GitHub, repo `sharesphere`**, default branch `main`. | Milestone 0.1 |
| D-12 | Category compatibility rules | Table `category_compatibility(category_id, compatible_category_id, score_factor)` managed by admin; **exact = 100, compatible = `score_factor` (default 60)**. | Phase 8 |
| D-13 | Production SMTP provider | Any TLS SMTP provider (config only). | Phase 21 |
| D-14 | Enum values | Condition: `new, like_new, good, fair`. Urgency: `low, medium, high, critical`. | Phase 1 |
| D-15 | Media visibility | Donation images: owner, admin, any **verified+active NGO** while donation is `active/partially_allocated`. NGO documents: owning NGO + admin only. | Phase 5 |
| D-16 | OTP parameters (spec proposes 5 attempts) | 6 digits, **TTL 30 min**, **5 failed attempts → locked until reissue**, max **3 issues per pickup per 24 h**. OTP is emailed to the **NGO**; the **donor** types it in. | Phase 10 |
| D-17 | Pending-request lifetime | Auto-expire after **72 h** via cron-run CLI (`bin/expire-requests.php`). | Phase 9 |
| D-18 | Password hashing | `password_hash(..., PASSWORD_DEFAULT)` (bcrypt/argon2 depending on build) + `password_needs_rehash`. | Phase 3 |

### 0.2 Technology additions and why each is needed

The spec fixes the main stack. These are the *only* extra libraries/tools this playbook adds:

| Addition | Why it is needed |
|---|---|
| Docker Compose (`postgis/postgis:16-3.4`, `axllent/mailpit`) | Reproducible Postgres+PostGIS for every machine; Mailpit catches OTP/notification emails so you never email real people in dev (spec §16.1 "email sandbox"). |
| Composer packages: `vlucas/phpdotenv`, `monolog/monolog`, `phpmailer/phpmailer`, `phpunit/phpunit` | `.env` loading (spec §16.2 env-based config); structured logs with request IDs (spec §16.3); SMTP/TLS with correct MIME/escaping (hand-rolled SMTP is a security risk); unit/integration tests (spec §15). |
| **No PHP framework** | Spec §9.3 defines its own Router→Middleware→Controller→Service→Repository layering; a tiny custom router keeps that explicit and demonstrates the web-programming concepts the project is meant to show. |
| Plain SQL migrations + 60-line runner (`bin/migrate.php`) | Spec §12.1 requires migrations under version control; a framework-free runner avoids a heavy dependency and gives explicit `up`/`down` files for rollback. |
| `ext-gd` (PHP) | Re-encode uploaded images to strip EXIF (EXIF can contain GPS = donor home location, violating spec §17 location-privacy risk). |
| Frontend: `leaflet`, `react-leaflet@4`, `react-router-dom`, `tailwindcss`, `vitest`, `@testing-library/react`, `@playwright/test` | Map (spec §9.2), routing, styling, unit + E2E tests (spec §9.2/§15). |
| `k6` | Concurrency and load testing (spec §15.3/§15.6). |

### 0.3 Target repository layout (built up milestone by milestone)

```
sharesphere/
├─ backend/
│  ├─ public/index.php            # single entry point (front controller)
│  ├─ src/{Http,Middleware,Controllers,Services,Repositories,Adapters,Support}/
│  ├─ bin/                        # migrate.php, create-admin.php, expire-requests.php, seed-perf.php
│  ├─ tests/{Unit,Integration,Concurrency}/
│  ├─ storage/                    # PRIVATE uploads + logs (gitignored, outside public/)
│  ├─ composer.json  phpunit.xml
├─ frontend/                      # Vite + React app
├─ db/{migrations,seed}/
├─ docs/{api-contract.md,state-model.md,matching.md,security-checklist.md,test-report.md}
├─ docker-compose.yml  .env.example  .gitignore  Makefile  scripts/verify.sh  README.md
```

### 0.4 Global conventions (apply to EVERY milestone)

**Workflow cycle (spec §17):** PLAN → EXECUTE → VALIDATE → TEST → REVIEW → APPROVE → PROCEED. AI-generated code must be read, understood, traced to a requirement, and tested before you accept it.

**Branching.** One branch per phase, created from an up-to-date `main`:
```bash
git checkout main && git pull origin main
git checkout -b phase-<N>-<short-name>      # exact name is given in each phase
```
Each milestone = **one commit + one annotated tag** (`m<phase>.<n>`) pushed to the phase branch. At phase end you merge to `main` and tag `phase-<N>-complete`.

**Test IDs & status.** IDs look like `T-3.1-04`. The **Status** column starts as `☐` (not run) → you change it to `✅ Pass` / `❌ Fail`. A milestone is DONE only when every row is `✅`.

**Test-type legend:** **P** positive · **N** negative · **E** edge · **V** validation · **I** integration · **F** failure-handling · **S** security · **R** regression · **C** concurrency.

**Standard rollback procedures** (referenced by every milestone):

| Code | Situation | Commands |
|---|---|---|
| **R-1** (default, safe) | A pushed milestone is bad and others may have pulled it | `git log --oneline -5` → `git revert --no-edit <bad-commit-hash>` → `git push origin <branch>` |
| **R-2** (own phase branch only) | You want the branch to look exactly like the previous milestone | `git fetch --tags` → `git reset --hard m<prev>` → `git push --force-with-lease origin <branch>` |
| **R-3** (database) | The milestone added a migration | `php backend/bin/migrate.php down --steps=<n>` (from 1.2 onward) *before* switching code back; confirm with `php backend/bin/migrate.php status` |
| **R-4** (nuclear, dev DB only) | Dev DB corrupted | `docker compose down -v && docker compose up -d && php backend/bin/migrate.php up && psql … -f db/seed/dev_seed.sql` |

> Never use `git push --force` (use `--force-with-lease`), never rewrite `main`, never run R-4 against a shared or production database.

**Universal "do NOT commit" list** (enforced by `.gitignore` from Milestone 0.1; each milestone adds specifics): `.env`, `*.pem`, `*.key`, SMTP/DB passwords, `OTP_HMAC_KEY`, `APP_KEY`, `backend/vendor/`, `frontend/node_modules/`, `frontend/dist/`, `backend/storage/**` (uploads, logs, sessions), `coverage/`, `playwright-report/`, `.phpunit.cache/`, IDE folders, OS junk, DB dumps with real data.

**Pre-commit habit (every milestone):**
```bash
git status                       # review the file list line by line
git diff --staged | grep -iE "password|secret|api[_-]?key|BEGIN (RSA|PRIVATE)" || echo "no obvious secrets"
```

**Phase Completion Checkpoint template** (each phase ends with one):

| Field | Value |
|---|---|
| Completed milestones | |
| Tests passed (count / total) | |
| Known issues | |
| Git commit / hash | `git rev-parse --short HEAD` → |
| Overall phase verification | |
| **Go / No-Go** | Go only if: all milestone statuses `✅`, `scripts/verify.sh` green, no open Sev-1/Sev-2 defects, `main` merged and tagged. |

---

## Milestone Map

| Phase | Name | Milestones |
|---|---|---|
| 0 | Project Setup & Baseline | 0.1 – 0.4 |
| 1 | Contracts & Database | 1.1 – 1.4 |
| 2 | Backend Foundation | 2.1 – 2.2 |
| 3 | Authentication & Security Core | 3.1 – 3.4 |
| 4 | Notifications & Email Infrastructure | 4.1 – 4.2 |
| 5 | Secure Files, NGO Onboarding, Categories | 5.1 – 5.4 |
| 6 | Donations | 6.1 – 6.3 |
| 7 | NGO Requirements | 7.1 |
| 8 | Smart Matching Engine | 8.1 – 8.2 |
| 9 | Requests & Safe Allocation | 9.1 – 9.3 |
| 10 | Pickup, OTP & Completion | 10.1 – 10.3 |
| 11 | Admin Operations (backend) | 11.1 – 11.2 |
| 12 | Frontend Foundation, Public & Auth | 12.1 – 12.2 |
| 13 | Frontend – Donor | 13.1 – 13.2 |
| 14 | Frontend – NGO & Matching Map | 14.1 – 14.2 |
| 15 | Frontend – Pickup, OTP & Notifications | 15.1 |
| 16 | Frontend – Admin | 16.1 – 16.2 |
| 17 | Validation, Errors, Accessibility | 17.1 |
| 18 | Testing & Performance | 18.1 – 18.3 |
| 19 | Security Audit | 19.1 – 19.2 |
| 20 | Documentation | 20.1 |
| 21 | Deployment | 21.1 – 21.2 |
| 22 | Final Audit & Sign-off | 22.1 |

---

# PHASE 0 – Project Setup & Baseline
**Branch:** `phase-0-setup`

## Milestone 0.1 – Repository, Git Hygiene & Environment Template

**Depends on:** nothing.

**1. Milestone Name:** Repository, Git hygiene and environment template.

**2. Objective:** Create the empty monorepo with a correct `.gitignore`, `.env.example`, README stub and branching so that no secret or generated file can ever enter history.

**3. Tasks to Complete**
- [ ] Create GitHub repo `sharesphere` (private), clone locally.
- [ ] Create folders `backend/ frontend/ db/migrations db/seed docs scripts`.
- [ ] Write `.gitignore` (see below), `.gitattributes` (`* text=auto eol=lf`), `.editorconfig`.
- [ ] Write `.env.example` with **placeholder names only**.
- [ ] Write `README.md` stub (project name, "setup in progress").
- [ ] Add `LICENSE` or "All rights reserved" note (your choice).
- [ ] Create `main`, protect it on GitHub (require PR or at least disallow force-push).

**4. Files / Modules Affected:** `.gitignore`, `.gitattributes`, `.editorconfig`, `.env.example`, `README.md`, empty dirs with `.gitkeep`.

**5. Implementation Guidance**
`.gitignore` must contain at least:
```
.env
.env.*
!.env.example
backend/vendor/
backend/storage/*
!backend/storage/.gitkeep
frontend/node_modules/
frontend/dist/
coverage/
playwright-report/
test-results/
.phpunit.cache/
*.log
.idea/
.vscode/
.DS_Store
*.pem
*.key
*.sql.gz
```
`.env.example` (names only, no real values):
```
APP_ENV=development
APP_URL=http://localhost:5173
APP_KEY=change-me-32-bytes-hex
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=sharesphere_dev
DB_USER=sharesphere_app
DB_PASS=change-me
DB_OWNER_USER=sharesphere_owner
DB_OWNER_PASS=change-me
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USER=
MAIL_PASS=
MAIL_FROM=no-reply@sharesphere.local
OTP_HMAC_KEY=change-me-32-bytes-hex
STORAGE_PATH=./storage
CORS_ALLOWED_ORIGIN=http://localhost:5173
```
Why a protected `main`: every Go/No-Go decision later depends on `main` always being a stable checkpoint.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-0.1-01 (P) | Repo clones cleanly | Repo pushed | `git clone <url> /tmp/ss-check` | Folders from task 3 exist | ☐ |
| T-0.1-02 (S) | `.env` is ignored | `.gitignore` present | `echo X=1 > .env && git status --short` | `.env` is **not** listed | ☐ |
| T-0.1-03 (P) | `.env.example` is tracked | – | `git ls-files .env.example` | File listed | ☐ |
| T-0.1-04 (S) | Storage uploads ignored | – | `touch backend/storage/test.jpg && git status --short` | Not listed; `.gitkeep` still tracked | ☐ |
| T-0.1-05 (N) | No real secrets in example | – | `grep -iE "(pass\|key)=.{12,}" .env.example` | Only `change-me…` placeholders match | ☐ |
| T-0.1-06 (E) | Line endings normalised | `.gitattributes` present | `git ls-files --eol \| head` | `i/lf` for text files | ☐ |

**7. Verification Checklist**
- [ ] All six tests ✅.
- [ ] `git check-ignore -v .env backend/vendor frontend/node_modules` shows a matching rule for each.
- [ ] GitHub shows `main` protected.

**8. Milestone Completion Criteria:** DONE when no ignored path is trackable, `.env.example` lists every variable used by later milestones (extend it later, never put values), and the repo is pushed.

**9. Git Checkpoint**
```bash
git checkout -b phase-0-setup
git status
git add .
git commit -m "chore(repo): initialise monorepo, gitignore and env template (M0.1)"
git push origin phase-0-setup
git tag -a m0.1 -m "M0.1 repo baseline" && git push origin m0.1
```
*Do NOT commit:* a real `.env`, any credential file.

**10. Rollback / Recovery:** **R-1**. If a secret was committed even once: rotate it immediately (history rewrite is not enough), then remove from history with `git filter-repo`.

---

## Milestone 0.2 – Local Services: PostgreSQL + PostGIS + Mailpit

**Depends on:** M0.1. **Requires decision D-10.**

**1. Milestone Name:** Dockerised Postgres/PostGIS and Mailpit.

**2. Objective:** One command starts the database (with PostGIS, citext, dev + test databases, owner and least-privilege app roles) and an email sandbox.

**3. Tasks to Complete**
- [ ] Create `docker-compose.yml` with `db` (`postgis/postgis:16-3.4`, port 5432, named volume) and `mailpit` (ports 1025 SMTP / 8025 UI).
- [ ] Create `db/init/01-init.sql` mounted to `/docker-entrypoint-initdb.d/`: create roles `sharesphere_owner` (migrations) and `sharesphere_app` (runtime, no DDL), databases `sharesphere_dev` and `sharesphere_test`, and in **each** DB `CREATE EXTENSION postgis; CREATE EXTENSION citext;`, plus default privileges for the app role.
- [ ] Copy `.env.example` → `.env` and set local passwords.
- [ ] Document `docker compose up -d` in README.

**4. Files / Modules Affected:** `docker-compose.yml`, `db/init/01-init.sql`, `README.md`, local `.env` (not committed).

**5. Implementation Guidance**
Init script skeleton (passwords come from your `.env`; for the init file use `\set`/env substitution or a local-only password – never commit real ones; dev-only throwaway values are acceptable *only* if clearly labelled and not reused anywhere):
```sql
CREATE ROLE sharesphere_owner LOGIN PASSWORD :'owner_pw';
CREATE ROLE sharesphere_app   LOGIN PASSWORD :'app_pw' NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE DATABASE sharesphere_dev  OWNER sharesphere_owner;
CREATE DATABASE sharesphere_test OWNER sharesphere_owner;
\c sharesphere_dev
CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS citext;
ALTER DEFAULT PRIVILEGES FOR ROLE sharesphere_owner IN SCHEMA public
  GRANT SELECT,INSERT,UPDATE,DELETE ON TABLES TO sharesphere_app;
ALTER DEFAULT PRIVILEGES FOR ROLE sharesphere_owner IN SCHEMA public
  GRANT USAGE,SELECT ON SEQUENCES TO sharesphere_app;
-- repeat \c sharesphere_test block
```
Why two roles: spec §13.1 demands least-privilege DB credentials; the app role can never `DROP`/`ALTER`, and in M3.3 we also revoke `UPDATE/DELETE` on `audit_logs`.
Why PostGIS now: later milestones depend on `geography` columns and GiST indexes.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-0.2-01 (P) | Services start | Docker running | `docker compose up -d && docker compose ps` | `db` healthy, `mailpit` up | ☐ |
| T-0.2-02 (P) | PostGIS present | DB up | `docker compose exec db psql -U sharesphere_owner -d sharesphere_dev -c "SELECT postgis_version();"` | Version ≥ 3.3 | ☐ |
| T-0.2-03 (P) | citext present | DB up | `… -c "SELECT 'A'::citext = 'a'::citext;"` | `t` | ☐ |
| T-0.2-04 (S) | App role cannot do DDL | DB up | connect as `sharesphere_app`: `CREATE TABLE x(i int);` | `permission denied for schema public` | ☐ |
| T-0.2-05 (P) | Test DB exists | DB up | `\l` | `sharesphere_test` listed | ☐ |
| T-0.2-06 (P) | Mail sandbox works | Mailpit up | open http://localhost:8025 | UI loads | ☐ |
| T-0.2-07 (F) | Data survives restart | Volume defined | `docker compose restart db` then re-run T-0.2-02 | Still works | ☐ |
| T-0.2-08 (E) | Port conflict handled | Local PG already on 5432 | Change host port in compose to 5433 and `.env` | Works with `DB_PORT=5433` | ☐ |

**7. Verification Checklist**
- [ ] All tests ✅. [ ] `git status` shows no `.env`. [ ] A teammate (or fresh clone) can run `docker compose up -d` and pass T-0.2-02.

**8. Milestone Completion Criteria:** DONE when both databases exist with PostGIS+citext, the app role is DDL-less, and Mailpit receives mail (test with `swaks` or `php -r "mail(...)"` later in M4.1).

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "chore(env): docker compose for postgis and mailpit with least-privilege roles (M0.2)"
git push origin phase-0-setup
git tag -a m0.2 -m "M0.2 local services" && git push origin m0.2
```
*Do NOT commit:* `.env`, Docker volumes, any file containing real passwords.

**10. Rollback / Recovery:** **R-1**; then `docker compose down -v && docker compose up -d` (**R-4**, dev only) to rebuild from the previous init script.

---

## Milestone 0.3 – Backend Skeleton & Health Endpoint

**Depends on:** M0.2.

**1. Milestone Name:** PHP project skeleton with `/api/health`.

**2. Objective:** A running PHP front controller, Composer autoloading, `.env` loading, a PDO connection factory and a health endpoint proving DB connectivity.

**3. Tasks to Complete**
- [ ] Verify PHP: `php -v` (≥ 8.2) and `php -m | grep -E "pdo_pgsql|mbstring|fileinfo|gd|openssl|sodium"` (all present).
- [ ] `cd backend && composer init` (name `sharesphere/api`, PSR-4 `App\` → `src/`).
- [ ] `composer require vlucas/phpdotenv monolog/monolog` and `composer require --dev phpunit/phpunit`.
- [ ] Create `public/index.php`, `src/Support/Config.php` (reads env, fails fast on missing keys), `src/Support/Database.php` (PDO factory: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`).
- [ ] Temporary handler: `GET /api/health` → `{"success":true,"data":{"status":"ok","db":true,"time":"…UTC"}}`.
- [ ] `backend/storage/.gitkeep`.

**4. Files / Modules Affected:** `backend/composer.json`, `composer.lock`, `public/index.php`, `src/Support/{Config,Database}.php`.

**5. Implementation Guidance**
Run: `php -S 127.0.0.1:8000 -t backend/public backend/public/index.php` (router-script form so every path hits the front controller). Use `EMULATE_PREPARES=false` so prepared statements are real server-side (spec §13.3). Do not echo exception messages to clients (spec §11.1); for now return a generic 500. Keep `composer.lock` committed (reproducible installs).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-0.3-01 (P) | Health OK | DB up, server running | `curl -i http://127.0.0.1:8000/api/health` | `200`, JSON with `db:true` | ☐ |
| T-0.3-02 (F) | DB down | `docker compose stop db` | same curl | `503` generic JSON error, **no** DSN/stack trace in body | ☐ |
| T-0.3-03 (N) | Unknown route | – | `curl -i …/api/nope` | `404` JSON (not HTML) | ☐ |
| T-0.3-04 (N) | Wrong method | – | `curl -i -X POST …/api/health` | `405` + `Allow: GET` | ☐ |
| T-0.3-05 (F) | Missing env var | remove `DB_NAME` from `.env` | start server, call health | Clear startup error logged server-side, generic 500 to client | ☐ |
| T-0.3-06 (S) | Server identity not leaked | – | `curl -I …/api/health` | No `X-Powered-By` header (set `expose_php=0`/`header_remove`) | ☐ |

**7. Verification Checklist**
- [ ] `composer validate` passes. [ ] `composer dump-autoload` OK. [ ] T-0.3-02 body contains no password/host. [ ] `git status` shows `vendor/` ignored.

**8. Milestone Completion Criteria:** DONE when health reflects real DB state and failures never leak internals.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(api): php skeleton, config loader, pdo factory and health endpoint (M0.3)"
git push origin phase-0-setup
git tag -a m0.3 -m "M0.3 backend skeleton" && git push origin m0.3
```
*Do NOT commit:* `backend/vendor/`, `.env`, `backend/storage/*`.

**10. Rollback / Recovery:** **R-1**; after switching code back run `composer install` to match the old `composer.lock`.

---

## Milestone 0.4 – Frontend Skeleton, Dev Proxy & Test Baseline

**Depends on:** M0.3.

**1. Milestone Name:** Vite+React skeleton with `/api` proxy, Vitest/PHPUnit baselines and `scripts/verify.sh`.

**2. Objective:** React app that calls `/api/health` through the Vite proxy (same-origin ⇒ cookies work without CORS in dev), plus one passing test in each test framework and a single verification script reused by every later milestone.

**3. Tasks to Complete**
- [ ] `cd frontend && npm create vite@latest . -- --template react` (JavaScript or TypeScript – pick one; this playbook is language-neutral).
- [ ] `npm i react-router-dom` · `npm i -D tailwindcss @tailwindcss/vite vitest jsdom @testing-library/react @testing-library/jest-dom`.
- [ ] `vite.config.js`: `server.proxy['/api'] = { target: 'http://127.0.0.1:8000' }`.
- [ ] Landing placeholder showing "API: ok/down" from `/api/health`.
- [ ] PHPUnit: `backend/phpunit.xml`, `tests/Unit/SmokeTest.php`; Vitest: `src/App.test.jsx`.
- [ ] `scripts/verify.sh`: runs `composer validate`, `vendor/bin/phpunit`, `npm run lint`, `npm test -- --run`, `npm run build`.
- [ ] `Makefile` targets: `up`, `api`, `web`, `test`, `verify`.

**4. Files / Modules Affected:** `frontend/**`, `backend/phpunit.xml`, `backend/tests/Unit/SmokeTest.php`, `scripts/verify.sh`, `Makefile`.

**5. Implementation Guidance**
Why a proxy: spec §6.12/§13.2 use cookie sessions with `SameSite`; serving UI and API from one origin in dev avoids CORS+cookie complexity. In production Nginx plays the same role (M21.1). Add `frontend/.env.development` only with non-secret values (Vite exposes `VITE_*` to the browser – **never** put secrets there).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-0.4-01 (P) | UI shows API ok | API + DB + Vite running | open http://localhost:5173 | "API: ok" | ☐ |
| T-0.4-02 (F) | UI handles API down | stop PHP server | reload page | "API: down" message, no crash/blank screen | ☐ |
| T-0.4-03 (P) | PHPUnit smoke | – | `cd backend && vendor/bin/phpunit` | 1 test passes | ☐ |
| T-0.4-04 (P) | Vitest smoke | – | `cd frontend && npm test -- --run` | 1 test passes | ☐ |
| T-0.4-05 (P) | Production build | – | `npm run build` | `dist/` created, no errors | ☐ |
| T-0.4-06 (P) | One-shot verify | all above | `bash scripts/verify.sh` | Exit code 0 | ☐ |
| T-0.4-07 (S) | No secret in bundle | build done | `grep -ri "password\|secret" frontend/dist \| head` | No hits from your code | ☐ |

**7. Verification Checklist**
- [ ] `scripts/verify.sh` exits 0 from a clean clone (`git clone` → `composer install` → `npm ci` → verify).
- [ ] Browser devtools Network tab shows `/api/health` served via 5173 proxy.

**8. Milestone Completion Criteria:** DONE when verify.sh is green from a clean clone.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(web): vite react skeleton, api proxy, test baselines and verify script (M0.4)"
git push origin phase-0-setup
git tag -a m0.4 -m "M0.4 frontend skeleton" && git push origin m0.4
```
*Do NOT commit:* `node_modules/`, `dist/`, `.env*` except example.

**10. Rollback / Recovery:** **R-1**; then `npm ci` to restore the previous lockfile state.

---

## ✅ Phase 0 Completion Checkpoint
Use the template in §0.4. Extra checks: fresh-clone setup works in < 15 minutes following README. Then merge:
```bash
git checkout main && git pull origin main
git merge --no-ff phase-0-setup -m "merge: phase 0 setup"
git push origin main
git tag -a phase-0-complete -m "Phase 0 complete" && git push origin phase-0-complete
```
**Go to Phase 1 only if** all T-0.x tests ✅.

---
# PHASE 1 – Contracts & Database
**Branch:** `phase-1-database`

## Milestone 1.1 – API Contract, State Model & Enums

**Depends on:** Phase 0 complete. **Confirm D-7, D-14 now.**

**1. Milestone Name:** API contract, response format and state-machine documents.

**2. Objective:** Freeze the names every later milestone will reuse: routes, JSON envelope, error codes, status enums and legal transitions (spec Appendix A: "finalise before implementation").

**3. Tasks to Complete**
- [ ] `docs/api-contract.md`: every route in the table below with method, auth role, request fields, success/error codes.
- [ ] Define JSON envelope: success `{"success":true,"data":…,"meta":{…}}`; error `{"success":false,"error":{"code":"VALIDATION_FAILED","message":"…","fields":{"quantity":"must be ≥ 1"}},"request_id":"…"}`.
- [ ] `docs/state-model.md`: enums + transition tables (below).
- [ ] Map each HTTP status (200/201/400/401/403/404/405/409/413/415/422/429) to a stable `error.code`.
- [ ] Review against spec §11 and Appendix A; tick off any spec feature with no route.

**4. Files / Modules Affected:** `docs/api-contract.md`, `docs/state-model.md` (documentation only).

**5. Implementation Guidance**
Routes (deviations from spec §11 are marked ★):

| Group | Routes |
|---|---|
| Auth | `GET /api/auth/csrf`★, `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me` |
| Profile | `GET /api/profile`, `PATCH /api/profile` |
| Categories | `GET /api/categories` (public read) |
| Donations | `GET/POST /api/donations`, `GET/PATCH/DELETE /api/donations/{id}`, `POST /api/donations/{id}/images`, `DELETE /api/donations/{id}/images/{imageId}` |
| Requirements | `GET/POST /api/requirements`, `GET/PATCH/DELETE /api/requirements/{id}`, `POST /api/requirements/{id}/close` |
| Matching | `GET /api/matches?requirement_id=` (NGO), `GET /api/matches?donation_id=` (donor), `GET /api/map/donations`★ |
| Requests | `POST/GET /api/requests`, `GET /api/requests/{id}`, `POST /api/requests/{id}/{accept\|reject\|cancel}` |
| Allocation | `GET /api/allocations/{id}` |
| Pickups | `POST /api/pickups`, `GET /api/pickups`, `GET /api/pickups/{id}`, `PATCH /api/pickups/{id}`, `POST /api/pickups/{id}/{confirm\|cancel\|otp\|verify-otp\|confirm-receipt}` |
| Notifications | `GET /api/notifications`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all` |
| Media | `GET /api/media/donation-images/{id}`★, `GET /api/media/ngo-documents/{id}`★ |
| Admin | `GET /api/admin/ngos`, `GET /api/admin/ngos/{id}`, `POST /api/admin/ngos/{id}/verify`, `GET /api/admin/users`, `PATCH /api/admin/users/{id}/status`, `GET/POST/PATCH /api/admin/categories`, `POST /api/admin/donations/{id}/moderate`, `GET /api/admin/reports/summary`, `GET /api/admin/audit-logs` |

Additional routes introduced by later milestones (add each to the contract when you build it): `PATCH /api/profile/password` (M3.4), `POST/GET/DELETE /api/profile/ngo-documents` and `POST /api/profile/ngo-resubmit` (M5.2), `GET /api/donations/{id}/history` (M10.3), `GET /api/dashboard`, `GET /api/admin/reports/{summary|trends|categories}` (M11.2).

Canonical `error.code` values (use exactly these): `BAD_REQUEST`(400) · `UNAUTHENTICATED`, `SESSION_EXPIRED`, `INVALID_CREDENTIALS`(401) · `FORBIDDEN`, `CSRF_FAILED`, `ACCOUNT_SUSPENDED`, `NGO_NOT_VERIFIED`(403) · `NOT_FOUND`(404) · `METHOD_NOT_ALLOWED`(405) · `EMAIL_TAKEN`, `INVALID_TRANSITION`, `INSUFFICIENT_QUANTITY`, `CONFLICT`(409) · `PAYLOAD_TOO_LARGE`(413) · `UNSUPPORTED_MEDIA_TYPE`(415) · `VALIDATION_FAILED`, `OTP_INVALID`, `OTP_EXPIRED`(422) · `OTP_LOCKED`(423) · `RATE_LIMITED`, `OTP_REISSUE_LIMIT`(429) · `INTERNAL_ERROR`(500) · `SERVICE_UNAVAILABLE`(503).

State model (finalise exactly these strings – DB CHECK constraints, API, UI badges and tests all use them):

| Entity | States | Legal transitions |
|---|---|---|
| user.account_status | `active, suspended` | active⇄suspended (admin) |
| ngo.verification_status | `pending, verified, rejected, correction_requested, suspended` | pending→verified/rejected/correction_requested; correction_requested→pending (NGO resubmits); verified→suspended; suspended→verified |
| donation.status | `draft, active, partially_allocated, fully_allocated, completed, closed, removed` | draft→active; active↔partially_allocated↔fully_allocated (derived from quantities); fully_allocated→completed (all allocations completed); active/partially_allocated→closed (donor, only if no *open* allocations); any→removed (admin moderation) |
| requirement.status | `draft, active, partially_fulfilled, fulfilled, closed` | similar; fulfilled when `quantity_fulfilled = quantity_needed` |
| request.status | `pending, accepted, rejected, cancelled, expired` | pending→accepted/rejected/cancelled/expired only |
| allocation.status | `reserved, confirmed, collected, completed, cancelled` | reserved→confirmed (donor accepts)/cancelled; confirmed→collected (OTP ok)/cancelled; collected→completed (NGO confirms) |
| pickup.state | `proposed, scheduled, otp_issued, collected, completed, cancelled` | proposed→scheduled (other party confirms); scheduled→otp_issued; otp_issued→collected; collected→completed; reschedule before `collected` returns to `proposed` and invalidates any OTP |

**6. Test Cases** (documentation milestone – reviewed, not coded)

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-1.1-01 (V) | Every spec §6 feature maps to a route | docs written | Walk spec §6.1–6.12 | No feature without route/screen | ☐ |
| T-1.1-02 (V) | Every spec §19 acceptance item maps to ≥1 planned test | docs written | Cross-check with milestone tables | No orphan criterion | ☐ |
| T-1.1-03 (V) | Enum strings identical across docs | – | `grep -rn "partially_allocated" docs/` | Same spelling everywhere | ☐ |
| T-1.1-04 (V) | No illegal transition is documented as legal | – | Review transition table | Terminal states (`completed`, `cancelled`, `rejected`) have no outgoing edges | ☐ |
| T-1.1-05 (V) | Error code table complete | – | Check all 12 HTTP codes | Each has code+example | ☐ |

**7. Verification Checklist:** [ ] You (and a teammate/mentor) have read both docs end-to-end. [ ] D-1, D-2 written into the docs as chosen. [ ] Markdown renders on GitHub.

**8. Milestone Completion Criteria:** DONE when the contract is reviewed and no open "TBD" remains for routes or states used before Phase 9.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-1-database
git status
git add docs/
git commit -m "docs(contract): api contract, error codes and state model (M1.1)"
git push origin phase-1-database
git tag -a m1.1 -m "M1.1 contract" && git push origin m1.1
```
*Do NOT commit:* nothing sensitive expected; check no real emails/IPs in examples.

**10. Rollback / Recovery:** **R-1**. Docs-only: safe to revert any time. (If you later change an enum, change docs first, then migration, then code.)

---

## Milestone 1.2 – Migration Runner & Identity Schema

**Depends on:** M1.1, M0.2.

**1. Milestone Name:** Migration runner + `users`, `ngos`, `categories`, `category_compatibility`.

**2. Objective:** Version-controlled up/down migrations and the first tables with constraints.

**3. Tasks to Complete**
- [ ] `backend/bin/migrate.php` with commands `up`, `down --steps=N`, `status`; tracks applied files in `schema_migrations(version, applied_at)`; each migration runs in a transaction; connects as `DB_OWNER_USER`.
- [ ] `db/migrations/001_identity.up.sql` / `.down.sql`: tables
  - `users(id identity PK, name, email citext UNIQUE NOT NULL, password_hash, role CHECK IN ('donor','ngo','admin'), account_status CHECK, phone NULL, created_at, updated_at, last_login_at)`
  - `ngos(id, user_id UNIQUE FK→users, organization_name, registration_number, address_text, location geography(Point,4326), service_radius_km, verification_status CHECK, reviewed_by FK→users NULL, reviewed_at, review_note, created_at, updated_at)`
  - `categories(id, name UNIQUE, description, is_active)`; `category_compatibility(category_id, compatible_category_id, score_factor NUMERIC CHECK 0<…≤100, PK(both), CHECK category_id<>compatible_category_id)`
- [ ] GiST index on `ngos.location`.
- [ ] `updated_at` trigger function reused by later tables.
- [ ] `composer` script `migrate`, and Makefile target.

**4. Files / Modules Affected:** `backend/bin/migrate.php`, `db/migrations/001_*`, `Makefile`.

**5. Implementation Guidance**
Filename ordering `NNN_name.up.sql`/`.down.sql`; refuse to run if a numbered gap or edited-after-applied checksum is detected (store sha256 in `schema_migrations`). Always write the `down` script at the same time as `up`. Use `BIGINT GENERATED ALWAYS AS IDENTITY`. Use `TIMESTAMPTZ` everywhere and store UTC (spec §12.1). Admin role is allowed in the CHECK but no registration path creates it (D-8).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-1.2-01 (P) | Migrate up | Empty DB | `php backend/bin/migrate.php up` | 001 applied, `status` shows applied | ☐ |
| T-1.2-02 (P) | Idempotent | After up | run `up` again | "Nothing to migrate", exit 0 | ☐ |
| T-1.2-03 (P) | Down + up cycle | After up | `down --steps=1` then `up` | Tables dropped then recreated, no errors | ☐ |
| T-1.2-04 (V) | Duplicate email (case-insens.) | Tables exist | insert `A@x.com` then `a@x.com` | Unique violation | ☐ |
| T-1.2-05 (V) | Invalid role | – | insert role `'root'` | CHECK violation | ☐ |
| T-1.2-06 (V) | Self-compatible category | – | insert (1,1,60) | CHECK violation | ☐ |
| T-1.2-07 (F) | Failing migration is atomic | Add a deliberately bad 999 SQL (temp) | run `up` | Whole 999 rolled back, not recorded | ☐ |
| T-1.2-08 (S) | App role can DML, not DDL | – | as `sharesphere_app`: `SELECT` users OK; `DROP TABLE users` | Select ok; drop denied | ☐ |
| T-1.2-09 (E) | Spatial index used | – | `\d ngos` | `gist (location)` index exists | ☐ |
| T-1.2-10 (V) | Edited applied migration detected | After up | modify 001 file, run `up` | Runner aborts on checksum mismatch | ☐ |

**7. Verification Checklist:** [ ] Test DB also migrates (`DB_NAME=sharesphere_test`). [ ] `down` leaves no orphan objects (`\dt` empty except `schema_migrations`). [ ] `git status` clean of temp 999 file.

**8. Milestone Completion Criteria:** DONE when up/down/status are reliable on both DBs and constraints reject all invalid rows.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(db): migration runner and identity schema (M1.2)"
git push origin phase-1-database
git tag -a m1.2 -m "M1.2 identity schema" && git push origin m1.2
```
*Do NOT commit:* DB dumps, `.env`.

**10. Rollback / Recovery:** run **R-3** (`down --steps=1`) first, then **R-1**. If a bad migration already ran in a shared DB, write a *new* corrective migration rather than editing the applied one.

---

## Milestone 1.3 – Donations, Requirements & Spatial Schema

**Depends on:** M1.2.

**1. Milestone Name:** `donations`, `donation_images`, `ngo_requirements`, `ngo_documents` with PostGIS indexes.

**2. Objective:** Tables for listings and needs with quantity CHECK constraints and spatial indexes ready for matching.

**3. Tasks to Complete**
- [ ] `002_donations_requirements.up/down.sql`:
  - `donations(id, donor_id FK, category_id FK, title, description, condition CHECK, total_quantity CHECK >0, available_quantity CHECK (>=0 AND <=total_quantity), status CHECK, address_text, location geography(Point,4326) NOT NULL, location_public geography(Point,4326) NOT NULL (snapped), pickup_notes, expires_at NULL, created_at, updated_at)`
  - `donation_images(id, donation_id FK ON DELETE CASCADE, storage_name UNIQUE, original_mime, size_bytes, sha256, sort_order, created_at)`
  - `ngo_documents(id, ngo_id FK, storage_name UNIQUE, mime, size_bytes, sha256, doc_type, created_at)`
  - `ngo_requirements(id, ngo_id FK, category_id FK, title, description, quantity_needed CHECK >0, quantity_allocated CHECK (>=0 AND <=quantity_needed), quantity_fulfilled CHECK (>=0 AND <=quantity_allocated), urgency CHECK, min_condition NULL, location geography NOT NULL, radius_km CHECK 1–500, needed_by DATE NULL, status CHECK, created_at, updated_at)`
- [ ] Indexes: `GIST(donations.location_public)`, `GIST(ngo_requirements.location)`, `btree(donations.status, category_id)`, `btree(ngo_requirements.status, category_id)`.
- [ ] `updated_at` triggers.

**4. Files / Modules Affected:** `db/migrations/002_*`.

**5. Implementation Guidance**
Quantity strategy (documented per spec §12.1 "avoid duplicate derived balances unless clear consistency strategy"): `donations.available_quantity` and `ngo_requirements.quantity_allocated/quantity_fulfilled` are **authoritative counters changed only inside `AllocationService` transactions** (M9.x); CHECK constraints are the last line of defence against overselling. A reconcile query (`available + SUM(open allocations) + SUM(completed) = total`) is added as a test in M9.3. Matching uses `geography` + `ST_DWithin` (metres) so distance units are consistent (spec §8.4).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-1.3-01 (P) | Valid donation insert | seed donor + category | insert qty 20/20 | OK | ☐ |
| T-1.3-02 (V) | Zero/negative quantity | – | total_quantity 0 and −5 | CHECK violation both | ☐ |
| T-1.3-03 (V) | available > total | – | total 5, available 6 | CHECK violation | ☐ |
| T-1.3-04 (V) | Invalid status/condition | – | status `'foo'` | CHECK violation | ☐ |
| T-1.3-05 (V) | fulfilled > allocated | requirement row | set fulfilled 5, allocated 3 | CHECK violation | ☐ |
| T-1.3-06 (V) | Missing location | – | NULL location | NOT NULL violation | ☐ |
| T-1.3-07 (I) | Cascade images | donation + image | delete donation | Images rows removed | ☐ |
| T-1.3-08 (I) | FK integrity | – | donation with non-existent category | FK violation | ☐ |
| T-1.3-09 (P) | Spatial query uses index | 1,000 sample rows | `EXPLAIN SELECT … WHERE ST_DWithin(location_public, ST_MakePoint(73.85,18.52)::geography, 5000)` | Plan mentions the GiST index (Bitmap/Index Scan) | ☐ |
| T-1.3-10 (P) | Down/up cycle | – | `down --steps=1 && up` | Clean | ☐ |

**7. Verification Checklist:** [ ] Test DB migrated. [ ] `\d donations` matches spec §12 fields. [ ] Reviewer confirms every CHECK enum equals `docs/state-model.md`.

**8. Milestone Completion Criteria:** DONE when no invalid quantity/status/geometry can be stored and the spatial plan uses the index.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(db): donations, requirements, media tables and spatial indexes (M1.3)"
git push origin phase-1-database
git tag -a m1.3 -m "M1.3 donation schema" && git push origin m1.3
```
*Do NOT commit:* sample real-world addresses/phones.

**10. Rollback / Recovery:** **R-3** `down --steps=1`, then **R-1**.

---

## Milestone 1.4 – Workflow, Audit & Support Tables + Dev Seed

**Depends on:** M1.3.

**1. Milestone Name:** `donation_requests`, `allocations`, `pickups`, `notifications`, `email_outbox`, `audit_logs`, `session_logs`, `rate_limits` and seed data.

**2. Objective:** Finish the schema from spec §12 and provide repeatable dev data.

**3. Tasks to Complete**
- [ ] `003_workflow_support.up/down.sql`:
  - `donation_requests(id, donation_id, ngo_id, requirement_id, requested_quantity CHECK>0, status CHECK, created_at, reviewed_at, expires_at)`
  - `allocations(id, request_id UNIQUE, donation_id, requirement_id, allocated_quantity CHECK>0, status CHECK, created_at, updated_at)`
  - `pickups(id, allocation_id UNIQUE, proposed_by FK users, scheduled_at, location_details, state CHECK, otp_hmac NULL, otp_expires_at NULL, otp_attempts INT DEFAULT 0, otp_locked BOOL, otp_issue_count, last_otp_issued_at, collected_at, completed_at, created_at, updated_at)`
  - `notifications(id, user_id, type, title, body, ref_type, ref_id, read_at, created_at)`
  - `email_outbox(id, to_email, subject, body_text, body_html NULL, status CHECK IN ('pending','sent','failed'), attempts, next_attempt_at, last_error, created_at, sent_at)`
  - `audit_logs(id, actor_user_id NULL, action, target_type, target_id, result, metadata jsonb, ip inet NULL, request_id, created_at)`
  - `session_logs(id, user_id, event, ip, user_agent, created_at)`
  - `rate_limits(key text, window_start timestamptz, hits int, PRIMARY KEY(key, window_start))`
- [ ] `db/seed/dev_seed.sql`: 8 categories (Clothing, Blankets, Books, Stationery, Furniture, Kitchenware, Toys, Electronics-small), 3 compatibility pairs, **no users with real passwords** (users are created through the API/CLI).
- [ ] Indexes on every FK + `(user_id, read_at)` for notifications, `(status, next_attempt_at)` for outbox.

**4. Files / Modules Affected:** `db/migrations/003_*`, `db/seed/dev_seed.sql`.

**5. Implementation Guidance**
`email_outbox` implements the **transactional outbox**: business transactions insert an outbox row; a worker sends it afterwards. This is why an SMTP failure can never corrupt allocation (spec §6.10 "retry-safe"). OTP plaintext is **never** stored in `email_outbox` after sending – M10.2 will store OTP-mail body only in memory and enqueue an already-rendered message that is blanked after `sent` (documented decision there). `audit_logs.metadata` must never hold secrets.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-1.4-01 (P) | Migrate up/down | M1.3 done | `up` then `down --steps=1` then `up` | Clean | ☐ |
| T-1.4-02 (V) | One allocation per request | rows exist | 2nd allocation same request_id | Unique violation | ☐ |
| T-1.4-03 (V) | One pickup per allocation | – | duplicate | Unique violation | ☐ |
| T-1.4-04 (V) | Bad pickup state / outbox status | – | invalid strings | CHECK violation | ☐ |
| T-1.4-05 (P) | Seed loads | migrated | `psql … -f db/seed/dev_seed.sql` | 8 categories | ☐ |
| T-1.4-06 (E) | Seed re-runnable | seeded | run seed again | No duplicates (use `ON CONFLICT DO NOTHING`) | ☐ |
| T-1.4-07 (I) | FK from allocation → request | – | delete a request that has allocation | Blocked (RESTRICT) | ☐ |
| T-1.4-08 (R) | Earlier tables unaffected | – | re-run T-1.2/T-1.3 suites | Still ✅ | ☐ |

**7. Verification Checklist:** [ ] All 11+ spec tables exist (`\dt`). [ ] Migrations 001–003 apply to a brand-new DB in order and revert in reverse. [ ] Seed contains no personal data.

**8. Milestone Completion Criteria:** DONE when the full schema exists in dev+test DBs with up/down proven.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(db): workflow, audit, outbox tables and dev seed (M1.4)"
git push origin phase-1-database
git tag -a m1.4 -m "M1.4 full schema" && git push origin m1.4
```
*Do NOT commit:* DB dumps containing user rows; admin credentials.

**10. Rollback / Recovery:** **R-3** `down --steps=1`, then **R-1**.

---

## ✅ Phase 1 Completion Checkpoint
Fill the template in §0.4. Extra verification: drop & recreate dev DB (**R-4**), run all migrations + seed, confirm `docs/state-model.md` equals DB CHECK values (`grep` each enum). Merge to `main` and tag:
```bash
git checkout main && git pull origin main
git merge --no-ff phase-1-database -m "merge: phase 1 database"
git push origin main
git tag -a phase-1-complete -m "Phase 1 complete" && git push origin phase-1-complete
```

---

# PHASE 2 – Backend Foundation
**Branch:** `phase-2-foundation`

## Milestone 2.1 – Router, Response Envelope & Error Handling

**Depends on:** Phase 1 complete (M1.1 envelope).

**1. Milestone Name:** Front-controller router with method checks, JSON envelope and central error handler.

**2. Objective:** Spec §9.3 "Router / API Gateway": map URL+method → controller, return 404/405 consistently, and never leak internals.

**3. Tasks to Complete**
- [ ] `src/Http/Request.php` (method, path, query, parsed JSON body, headers, files, client IP), `Response.php` (status, JSON helper), `Router.php` (pattern `/api/donations/{id}` with `\d+` typed params, per-route middleware list, `Allow` header on 405).
- [ ] `src/Http/ErrorHandler.php`: converts `HttpException` subclasses (`BadRequest`, `Unauthorized`, `Forbidden`, `NotFound`, `MethodNotAllowed`, `Conflict`, `PayloadTooLarge`, `UnsupportedMediaType`, `ValidationFailed`, `TooManyRequests`) to the envelope; any other `Throwable` → logged + generic `500 INTERNAL_ERROR`.
- [ ] Reject invalid JSON (400) and non-JSON content-type on JSON routes (415); body size cap (e.g. 1 MB for JSON).
- [ ] Move `/api/health` into a `HealthController`.
- [ ] `routes.php` file holds the route table.

**4. Files / Modules Affected:** `src/Http/*`, `src/Controllers/HealthController.php`, `routes.php`, `public/index.php`, `tests/Unit/RouterTest.php`.

**5. Implementation Guidance**
Controllers parse request and return `Response`; no SQL, no business rules (spec §9.3). Set response headers globally: `Content-Type: application/json; charset=utf-8`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store` on API JSON. Set `display_errors=0` in non-dev. Typed route params prevent `/api/donations/abc` ever reaching a controller.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-2.1-01 (P) | Route match with param | route `/api/x/{id}` | unit-test `Router::dispatch` GET `/api/x/12` | handler gets `id=12` (int) | ☐ |
| T-2.1-02 (N) | 404 unknown path | server up | `curl -i /api/zzz` | 404 envelope, `error.code=NOT_FOUND` | ☐ |
| T-2.1-03 (N) | 405 wrong method | route GET only | `curl -i -X DELETE …/api/health` | 405 + `Allow: GET` | ☐ |
| T-2.1-04 (V) | Non-numeric id | route with `{id}` | GET `/api/x/abc` | 404 | ☐ |
| T-2.1-05 (N) | Malformed JSON | POST route | send `{bad` | 400 `BAD_REQUEST` | ☐ |
| T-2.1-06 (N) | Wrong content-type | POST route | `Content-Type: text/plain` | 415 | ☐ |
| T-2.1-07 (E) | Oversized body | – | send 2 MB JSON | 413 | ☐ |
| T-2.1-08 (F) | Uncaught exception | temp throwing route | call it | 500 generic, no stack/SQL; log has full detail | ☐ |
| T-2.1-09 (R) | Health still works | – | curl health | 200 (unchanged from M0.3) | ☐ |
| T-2.1-10 (S) | Security headers | – | `curl -I` | `nosniff` and `no-store` present | ☐ |

**7. Verification Checklist:** [ ] PHPUnit green. [ ] Remove the temporary throwing route. [ ] `php -l` on all files.

**8. Milestone Completion Criteria:** DONE when every error path returns the contract envelope and status codes from M1.1.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-2-foundation
git status
git add .
git commit -m "feat(api): router, response envelope and central error handling (M2.1)"
git push origin phase-2-foundation
git tag -a m2.1 -m "M2.1 router" && git push origin m2.1
```
*Do NOT commit:* log files from `backend/storage`.

**10. Rollback / Recovery:** **R-1**.

---

## Milestone 2.2 – Middleware Pipeline, Logging, Validator & DB Helpers

**Depends on:** M2.1.

**1. Milestone Name:** Middleware chain, request-ID logging, reusable validator, transaction helper.

**2. Objective:** Shared plumbing so later milestones only add business logic.

**3. Tasks to Complete**
- [ ] `src/Middleware/` interface + pipeline runner; `RequestIdMiddleware` (UUID per request, echoed as `X-Request-Id`, included in logs and error envelope).
- [ ] Monolog JSON logger → `storage/logs/app-YYYY-MM-DD.log`; a **redaction processor** removing keys `password, otp, token, csrf, authorization, cookie`.
- [ ] `src/Support/Validator.php`: rules `required, string, min, max, email, int, min_value, max_value, in:[…], date, lat, lng, regex`; returns field→message map → throws `ValidationFailed` (422).
- [ ] `src/Support/Db.php::transaction(callable)` (begin/commit/rollback, rethrow) and `forUpdate` query helpers.
- [ ] `AuditLogger` and `Clock` interfaces (implemented in M3.3; stub now so tests can fake time).

**4. Files / Modules Affected:** `src/Middleware/*`, `src/Support/{Validator,Db,LogRedactor}.php`, `tests/Unit/{ValidatorTest,LogRedactorTest,DbTransactionTest}.php`.

**5. Implementation Guidance**
Validation always runs server-side, trimming and bounding lengths (spec §13.3). Return **all** field errors at once (better UX, easier tests). `Clock` abstraction is needed so OTP expiry and rate-limit tests (M3.3, M10.2) don't `sleep()`.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-2.2-01 (P) | Request ID generated | server up | `curl -i /api/health` | `X-Request-Id` header, same ID in log line | ☐ |
| T-2.2-02 (S) | Redaction | – | log context `['password'=>'x','otp'=>'123456']` | Log shows `[REDACTED]` | ☐ |
| T-2.2-03 (V) | Validator multi-error | – | validate `{}` against required name+email | Two field errors | ☐ |
| T-2.2-04 (V) | Email/int/range rules | – | `"a@b"`, `"12x"`, qty −1 | Each rejected with message | ☐ |
| T-2.2-05 (E) | Boundary lengths | – | string length exactly max / max+1 | Accept / reject | ☐ |
| T-2.2-06 (E) | Unicode input | – | name `"अर्यन"` valid length (mb_strlen) | Accepted | ☐ |
| T-2.2-07 (P) | Transaction commits | test DB | insert in `transaction()` | Row persisted | ☐ |
| T-2.2-08 (F) | Transaction rolls back | test DB | throw inside callable | Row absent, exception rethrown | ☐ |
| T-2.2-09 (R) | Router tests | – | run M2.1 suite | ✅ | ☐ |

**7. Verification Checklist:** [ ] `scripts/verify.sh` green. [ ] Manually grep today's log for any `password` literal – none.

**8. Milestone Completion Criteria:** DONE when logs are structured+redacted and validation/transaction helpers are unit-tested.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(api): middleware pipeline, redacted logging, validator and tx helper (M2.2)"
git push origin phase-2-foundation
git tag -a m2.2 -m "M2.2 plumbing" && git push origin m2.2
```
*Do NOT commit:* `backend/storage/logs/*`.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 2 Completion Checkpoint
Template §0.4. Extra: `scripts/verify.sh` green; confirm no controller contains SQL (`grep -rn "PDO\|SELECT" backend/src/Controllers` → none).
```bash
git checkout main && git pull origin main
git merge --no-ff phase-2-foundation -m "merge: phase 2 foundation"
git push origin main
git tag -a phase-2-complete -m "Phase 2 complete" && git push origin phase-2-complete
```

---
# PHASE 3 – Authentication & Security Core
**Branch:** `phase-3-auth`

## Milestone 3.1 – Registration, Login, Logout & Sessions

**Depends on:** Phase 2 complete. **Confirm D-8, D-18.**

**1. Milestone Name:** Donor registration, login/logout, `/api/auth/me`, secure server-side sessions.

**2. Objective:** Spec §6.2 and §6.12: hashed passwords, PHP sessions with regenerated IDs, secure cookie attributes, session expiry. (NGO registration with documents comes in M5.2; this milestone registers `donor` only and creates the NGO *user* skeleton path later.)

**3. Tasks to Complete**
- [ ] `UserRepository`, `AuthService`, `AuthController`, `SessionManager`.
- [ ] `POST /api/auth/register` (name, email, password ≥ 10 chars, password_confirm, `accept_terms=true`, role ∈ {`donor`}; any other role incl. `admin` → 422).
- [ ] `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`.
- [ ] Session bootstrap: `session.use_strict_mode=1`, `use_only_cookies=1`, cookie name `ss_session`, `httponly`, `samesite=Lax`, `secure` when HTTPS (env-driven), idle timeout 30 min, absolute 8 h, `session_regenerate_id(true)` on login, full destroy + cookie expiry on logout.
- [ ] Generic login failure message (`INVALID_CREDENTIALS`, same for unknown email/wrong password); dummy `password_verify` against a fixed hash for unknown email to equalise timing.
- [ ] `password_hash(PASSWORD_DEFAULT)`; rehash on login if `password_needs_rehash`.
- [ ] Reject login for `suspended` users (403 `ACCOUNT_SUSPENDED`).
- [ ] Integration-test base class: uses `sharesphere_test`, truncates tables in `setUp`.
- [ ] `bin/create-admin.php` (prompts for password without echo; **never** takes it as a CLI arg).

**4. Files / Modules Affected:** `src/Services/AuthService.php`, `src/Repositories/UserRepository.php`, `src/Controllers/AuthController.php`, `src/Support/SessionManager.php`, `bin/create-admin.php`, `tests/Integration/AuthTest.php`, `tests/Integration/IntegrationTestCase.php`.

**5. Implementation Guidance**
Use file-based PHP sessions stored under `storage/sessions` (outside web root, gitignored). Session data holds only `user_id`, `role`, `created_at`, `last_seen`; **role and status are re-read from the DB on every authenticated request** (cheap primary-key lookup) so suspension/role changes take effect immediately (spec §13.2 "invalidate after suspension"). Never put password hashes or OTPs in the session.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-3.1-01 (P) | Register donor | clean DB | POST valid payload | 201; row has `password_hash` ≠ plaintext, starts with `$2y$`/`$argon2` | ☐ |
| T-3.1-02 (V) | Duplicate email | user exists | register same email in different case | 422/409 `EMAIL_TAKEN` | ☐ |
| T-3.1-03 (V) | Weak/short password; mismatch confirm | – | 5-char pw; mismatched confirm | 422 with field errors | ☐ |
| T-3.1-04 (S) | Role escalation | – | register with `"role":"admin"` | 422; no admin created | ☐ |
| T-3.1-05 (P) | Login success | user exists | POST login | 200; `Set-Cookie: ss_session` has `HttpOnly; SameSite=Lax` | ☐ |
| T-3.1-06 (S) | Session ID regenerated | pre-login cookie `A` | login | Cookie value after login ≠ `A` | ☐ |
| T-3.1-07 (N) | Wrong password / unknown email | – | both cases | Identical 401 body | ☐ |
| T-3.1-08 (P) | `/me` with session | logged in | GET me | Returns id, name, role (no hash) | ☐ |
| T-3.1-09 (N) | `/me` without session | – | GET me | 401 | ☐ |
| T-3.1-10 (P) | Logout invalidates | logged in | logout then GET me with old cookie | 401 | ☐ |
| T-3.1-11 (E) | Idle timeout | Fake `Clock` +31 min | GET me | 401 `SESSION_EXPIRED` | ☐ |
| T-3.1-12 (S) | Suspended user | set `account_status=suspended` | login; also existing session `/me` | 403 on login; existing session → 401 | ☐ |
| T-3.1-13 (S) | SQLi in email | – | login email `' OR 1=1 --` | 401/422, no error leak | ☐ |
| T-3.1-14 (S) | No password in logs/response | – | grep log + response bodies | None | ☐ |
| T-3.1-15 (E) | Email normalisation | – | `" User@X.com "` | trimmed/lowercased match on login | ☐ |

**7. Verification Checklist:** [ ] Inspect cookie flags in browser devtools. [ ] `SELECT password_hash FROM users` shows hashes only. [ ] All PHPUnit tests ✅. [ ] Admin created through CLI and can log in.

**8. Milestone Completion Criteria:** DONE when spec §19 items 1–2 are demonstrably true (Secure flag verified later in HTTPS deployment, M21.2).

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-3-auth
git status
git add .
git commit -m "feat(auth): registration, login, logout and secure sessions (M3.1)"
git push origin phase-3-auth
git tag -a m3.1 -m "M3.1 auth core" && git push origin m3.1
```
*Do NOT commit:* `storage/sessions/*`, admin credentials, cookies copied from the browser.

**10. Rollback / Recovery:** **R-1**; sessions are file-based so also clear `storage/sessions/*` locally.

---

## Milestone 3.2 – CSRF Protection, RBAC & Ownership Guards

**Depends on:** M3.1.

**1. Milestone Name:** CSRF middleware, role middleware, ownership policy helper.

**2. Objective:** Server-side authorization for *every* protected operation (spec §5, §13.1–13.3); CSRF for cookie-authenticated writes.

**3. Tasks to Complete**
- [ ] `GET /api/auth/csrf` → creates anonymous session if needed, returns `{token}` (random_bytes(32) hex, stored in session).
- [ ] `CsrfMiddleware`: required on POST/PUT/PATCH/DELETE; header `X-CSRF-Token`; `hash_equals`; also validate `Origin` (or `Referer`) host equals `APP_URL` host; rotate token on login.
- [ ] `AuthMiddleware` (401 if no valid session), `RoleMiddleware('admin')`, `VerifiedNgoMiddleware` (ngo role + `verification_status='verified'` + user active), `Policy::owns($resource)` helpers returning 403, or 404 when existence should not be revealed.
- [ ] Narrow CORS: allow only `CORS_ALLOWED_ORIGIN`, `Access-Control-Allow-Credentials: true`, preflight handled; no `*`.
- [ ] Apply middleware in `routes.php` (a route table without middleware must be a review red flag).

**4. Files / Modules Affected:** `src/Middleware/{Auth,Role,VerifiedNgo,Csrf,Cors}Middleware.php`, `src/Support/Policy.php`, `routes.php`, `tests/Integration/AuthorizationTest.php`.

**5. Implementation Guidance**
Why a CSRF token and not only SameSite=Lax: SameSite is defence-in-depth, but older browsers and same-site subdomain attacks exist; spec §6.12 requires CSRF protection explicitly. Login/register are state-changing and need the token too (login-CSRF). Add a test-only route group `/api/_test/*` **only when `APP_ENV=testing`** to exercise role guards, and make a test asserting those routes 404 in production mode.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-3.2-01 (P) | Token flow | – | GET csrf → POST login with header | 200 | ☐ |
| T-3.2-02 (S) | Missing token | session | POST logout without header | 403 `CSRF_FAILED` | ☐ |
| T-3.2-03 (S) | Wrong token | – | tampered header | 403 | ☐ |
| T-3.2-04 (S) | Foreign Origin | valid token | `Origin: https://evil.example` | 403 | ☐ |
| T-3.2-05 (P) | GET needs no token | – | GET `/api/auth/me` | 200 | ☐ |
| T-3.2-06 (S) | Admin route as donor | donor logged in | call test admin route | 403 | ☐ |
| T-3.2-07 (S) | Admin route anonymous | – | call | 401 | ☐ |
| T-3.2-08 (S) | Pending NGO blocked | NGO user with `pending` | call verified-only route | 403 `NGO_NOT_VERIFIED` | ☐ |
| T-3.2-09 (S) | IDOR | donor A, resource owned by B | PATCH B's resource | 403/404 | ☐ |
| T-3.2-10 (S) | CORS | – | preflight from allowed vs. other origin | Allowed origin echoed; other gets no ACAO header | ☐ |
| T-3.2-11 (R) | Auth tests | – | rerun M3.1 suite (adapted for CSRF) | ✅ | ☐ |
| T-3.2-12 (S) | Test routes hidden | `APP_ENV=production` | call `/api/_test/ping` | 404 | ☐ |

**7. Verification Checklist:** [ ] Every route in `routes.php` has an explicit middleware list reviewed line-by-line. [ ] Matrix test (role × route) generated and passing.

**8. Milestone Completion Criteria:** DONE when a role/route matrix test proves no route is reachable by an unintended role and CSRF blocks forged writes.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(security): csrf, rbac middleware, ownership policy and narrow cors (M3.2)"
git push origin phase-3-auth
git tag -a m3.2 -m "M3.2 authz" && git push origin m3.2
```
*Do NOT commit:* any `APP_ENV=testing` override in a committed `.env`.

**10. Rollback / Recovery:** **R-1**. If the frontend (later) breaks because of CSRF, fix the client — never disable the middleware.

---

## Milestone 3.3 – Rate Limiting & Audit Logging Service

**Depends on:** M3.2.

**1. Milestone Name:** DB-backed rate limiter and audit/session logging.

**2. Objective:** Spec §13.3 (rate limits on login/OTP) and §13.7 (audit of security-relevant actions) as reusable services.

**3. Tasks to Complete**
- [ ] `RateLimiter` using `rate_limits` (key = `login:<ip>:<email-hash>`, window 15 min, 10 hits ⇒ 429 with `Retry-After`). Atomic upsert `INSERT … ON CONFLICT DO UPDATE SET hits=hits+1`.
- [ ] `RateLimitMiddleware(name, max, windowSeconds)`; apply to login, register, and (later) OTP verify.
- [ ] `AuditLogger::log(action, targetType, targetId, result, metadata)` fills actor, IP, request_id; metadata passes through the redactor.
- [ ] Log: `auth.login.success`, `auth.login.failure`, `auth.logout`, `auth.register`, `rate_limit.blocked`; mirror login/logout into `session_logs`.
- [ ] Migration `004_audit_hardening`: `REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM sharesphere_app;` + trigger raising an exception on UPDATE/DELETE for any role except owner.
- [ ] Cleanup CLI `bin/purge-rate-limits.php` (rows older than 1 day).

**4. Files / Modules Affected:** `src/Services/{RateLimiter,AuditLogger}.php`, `src/Middleware/RateLimitMiddleware.php`, `db/migrations/004_*`, `bin/purge-rate-limits.php`.

**5. Implementation Guidance**
Key by IP **and** account identifier so one attacker can't lock out everyone and one IP can't try unlimited accounts. Behind a reverse proxy, trust `X-Forwarded-For` only from the configured proxy IP (decide before M21). Audit rows are append-only by DB permission, which is the practical "tamper-resistant" measure the spec asks for.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-3.3-01 (P) | Under limit | – | 5 bad logins | All 401 | ☐ |
| T-3.3-02 (S) | Over limit | – | 11 bad logins | 11th → 429 with `Retry-After` | ☐ |
| T-3.3-03 (E) | Window reset | Fake clock +16 min | login again | Allowed | ☐ |
| T-3.3-04 (E) | Different account same IP | limit hit for email A | login as B | Not blocked by A's counter (key includes identifier) — but IP-wide ceiling still applies | ☐ |
| T-3.3-05 (C) | Parallel increments | – | 20 parallel requests | `hits` = 20 exactly (atomic upsert) | ☐ |
| T-3.3-06 (P) | Audit row on login | – | successful login | Row with actor, ip, request_id | ☐ |
| T-3.3-07 (S) | No secrets in audit | – | inspect metadata of failed login | No password, no full email only hash/mask | ☐ |
| T-3.3-08 (S) | Append-only | app role | `UPDATE audit_logs …`, `DELETE …` | Permission denied | ☐ |
| T-3.3-09 (S) | Trigger blocks owner updates | owner role | `UPDATE audit_logs` | Exception raised | ☐ |
| T-3.3-10 (F) | Audit failure policy | audit table temporarily unwritable | login | Login still succeeds; failure logged to file (document that **security-critical** transitions in later phases write audit **inside** the transaction instead) | ☐ |

**7. Verification Checklist:** [ ] Migration 004 up/down tested. [ ] Rate-limit tests use fake clock, no `sleep`. [ ] All earlier suites ✅.

**8. Milestone Completion Criteria:** DONE when brute-force login is throttled and audit rows exist and are immutable to the app role.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(security): rate limiter, audit logger and append-only audit table (M3.3)"
git push origin phase-3-auth
git tag -a m3.3 -m "M3.3 ratelimit+audit" && git push origin m3.3
```
*Do NOT commit:* exported audit data containing IPs/emails.

**10. Rollback / Recovery:** **R-3** `down --steps=1` (restores audit privileges) then **R-1**.

---

## Milestone 3.4 – Profile Management

**Depends on:** M3.2, M3.3.

**1. Milestone Name:** `GET/PATCH /api/profile`.

**2. Objective:** Users view/edit their own contact and location fields (spec §6.2); password change with current-password check.

**3. Tasks to Complete**
- [ ] `ProfileController/Service`: donor fields (name, phone, default location lat/lng + address text), NGO fields placeholder (filled in M5.2).
- [ ] `PATCH /api/profile/password` ★ (current_password, new_password): on success regenerate session ID, audit `auth.password.changed`.
- [ ] Whitelist updatable fields (mass-assignment protection): `email`, `role`, `account_status` are **not** updatable here.
- [ ] Validate lat∈[−90,90], lng∈[−180,180], phone pattern.

**4. Files / Modules Affected:** `src/Controllers/ProfileController.php`, `src/Services/ProfileService.php`, `routes.php`, tests.

**5. Implementation Guidance:** Email change is intentionally out of scope (would need verification flow, D-6). Return only the caller's own record — there is no `/profile/{id}` route, which removes an IDOR class by design.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-3.4-01 (P) | View own profile | logged in | GET | Own fields only | ☐ |
| T-3.4-02 (P) | Update name/phone | – | PATCH valid | 200; persisted | ☐ |
| T-3.4-03 (S) | Mass assignment | – | PATCH `{"role":"admin","account_status":"active"}` | Ignored/422; role unchanged | ☐ |
| T-3.4-04 (V) | Invalid lat/lng/phone | – | lat 123 | 422 | ☐ |
| T-3.4-05 (N) | Anonymous | – | GET | 401 | ☐ |
| T-3.4-06 (P) | Change password | – | correct current pw | 200; old pw no longer works; new works | ☐ |
| T-3.4-07 (N) | Wrong current password | – | PATCH | 403/422; audit failure row | ☐ |
| T-3.4-08 (S) | XSS payload in name | – | `<script>alert(1)</script>` | Stored as text; API returns JSON-escaped (UI escaping tested in M12) | ☐ |
| T-3.4-09 (R) | Auth + authz suites | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Manual curl run through the happy path. [ ] No response contains `password_hash`.

**8. Milestone Completion Criteria:** DONE when users can only read/change whitelisted own fields.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(profile): own-profile read/update and password change (M3.4)"
git push origin phase-3-auth
git tag -a m3.4 -m "M3.4 profile" && git push origin m3.4
```
*Do NOT commit:* test users' real data.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 3 Completion Checkpoint
Template §0.4. Extra verification: spec §19 items 1, 2, 18 (roles/ownership) and 20 (error codes 401/403/405/429) each have a ✅ test. Run `bash scripts/verify.sh`.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-3-auth -m "merge: phase 3 auth"
git push origin main
git tag -a phase-3-complete -m "Phase 3 complete" && git push origin phase-3-complete
```

---

# PHASE 4 – Notifications & Email Infrastructure
**Branch:** `phase-4-notifications`
*(Built early so every later workflow milestone can simply "emit a notification".)*

## Milestone 4.1 – Email Adapter & Transactional Outbox

**Depends on:** Phase 3 complete.

**1. Milestone Name:** PHPMailer SMTP adapter, outbox enqueue and sender worker with retries.

**2. Objective:** Spec §6.10: retry-safe email that can never corrupt a business transaction.

**3. Tasks to Complete**
- [ ] `composer require phpmailer/phpmailer`.
- [ ] `MailerInterface` + `SmtpMailer` (TLS/STARTTLS when `MAIL_PORT≠1025`), `FakeMailer` for tests.
- [ ] `Outbox::enqueue(to, subject, text, html?)` — **called inside the caller's DB transaction**.
- [ ] `bin/send-outbox.php`: selects `pending` rows `FOR UPDATE SKIP LOCKED`, sends, marks `sent`; on failure `attempts++`, exponential backoff `next_attempt_at`, `failed` after 5 attempts; supports `--loop` for dev.
- [ ] Subjects/bodies built from templates in `src/Support/EmailTemplates.php` (plain text; escape any user data in HTML parts).
- [ ] Never include passwords; OTP mail handling decision made in M10.2.

**4. Files / Modules Affected:** `src/Adapters/Mail/*`, `src/Services/Outbox.php`, `bin/send-outbox.php`, tests.

**5. Implementation Guidance:** `SKIP LOCKED` lets two workers run without double-sending. Header-injection defence: reject CR/LF in subject/recipient (PHPMailer validates, but add a unit test). Sending is **outside** request handling so SMTP latency never slows API calls.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-4.1-01 (P) | Enqueue + send | Mailpit up | enqueue, run worker | Mail visible in Mailpit; row `sent` | ☐ |
| T-4.1-02 (F) | SMTP down | Mailpit stopped | run worker | Row stays `pending`, `attempts=1`, `next_attempt_at` in future, `last_error` set | ☐ |
| T-4.1-03 (F) | Max retries | fake mailer always failing | run 5× (fake clock) | Row `failed` | ☐ |
| T-4.1-04 (I) | Rollback drops mail | begin tx, enqueue, throw | – | No outbox row exists | ☐ |
| T-4.1-05 (C) | Two workers | 50 pending | run 2 workers | Each mail sent exactly once | ☐ |
| T-4.1-06 (S) | Header injection | recipient `a@b.com\r\nBcc:x@y.com` | enqueue | Rejected (422/exception) | ☐ |
| T-4.1-07 (E) | Non-ASCII subject/body | – | Marathi text | Delivered, UTF-8 correct | ☐ |
| T-4.1-08 (S) | Secrets not logged | – | grep logs after failure | No SMTP password | ☐ |

**7. Verification Checklist:** [ ] Mailpit shows correct sender/subject. [ ] Worker exits non-zero on config error.

**8. Milestone Completion Criteria:** DONE when failures are retried without duplicates and enqueue is transaction-safe.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-4-notifications
git status
git add .
git commit -m "feat(notify): smtp adapter and transactional email outbox with retries (M4.1)"
git push origin phase-4-notifications
git tag -a m4.1 -m "M4.1 outbox" && git push origin m4.1
```
*Do NOT commit:* SMTP credentials in any file.

**10. Rollback / Recovery:** **R-1**. Pending outbox rows in dev DB: `TRUNCATE email_outbox;` (dev only).

---

## Milestone 4.2 – In-App Notifications API & Event Helper

**Depends on:** M4.1.

**1. Milestone Name:** `NotificationService` with in-app + email fan-out and read APIs.

**2. Objective:** One call `Notifier::notify($userId, $type, $title, $body, $ref, $emailToo)` writes an in-app row and (optionally) an outbox row inside the current transaction; users can list/mark read.

**3. Tasks to Complete**
- [ ] `Notifier` service, notification type constants (`ngo.verified`, `ngo.rejected`, `request.created`, `request.accepted`, `request.rejected`, `pickup.proposed`, `pickup.scheduled`, `otp.issued`, `handover.completed`, …).
- [ ] `GET /api/notifications?unread=1&page=`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`.
- [ ] Pagination helper (`page`, `per_page` ≤ 50) reused later.
- [ ] Body templates: minimal data only (no addresses, no OTPs, no phone numbers) per spec §6.10.

**4. Files / Modules Affected:** `src/Services/Notifier.php`, `src/Controllers/NotificationController.php`, `src/Support/Paginator.php`.

**5. Implementation Guidance:** Notifications are fetched by `user_id = session user` only — no ID in the list route, and `read` checks ownership (404 for others' IDs to avoid enumeration).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-4.2-01 (P) | Notify creates row + outbox | user with email | `notify(..., emailToo=true)` | 1 notification, 1 outbox row | ☐ |
| T-4.2-02 (P) | List own unread | 3 notifs (1 read) | GET `?unread=1` | 2 returned, newest first | ☐ |
| T-4.2-03 (S) | Read another user's notification | notif of user B | A POSTs read | 404; row unchanged | ☐ |
| T-4.2-04 (P) | Read-all | – | POST | All own `read_at` set; others untouched | ☐ |
| T-4.2-05 (E) | Pagination bounds | 120 rows | `per_page=1000`, `page=0`, `page=999` | Clamped to 50 / page 1 / empty list (200) | ☐ |
| T-4.2-06 (I) | Transaction rollback | – | notify inside failed tx | Neither row exists | ☐ |
| T-4.2-07 (N) | Anonymous | – | GET | 401 | ☐ |
| T-4.2-08 (S) | Body hygiene | – | unit test templates | Templates contain no `{otp}`, `{phone}`, `{address}` placeholders | ☐ |

**7. Verification Checklist:** [ ] Role matrix test updated. [ ] M4.1 suite ✅.

**8. Milestone Completion Criteria:** DONE when any later service can emit a notification in one line and users can only see their own.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(notify): in-app notifications api and notifier service (M4.2)"
git push origin phase-4-notifications
git tag -a m4.2 -m "M4.2 notifications" && git push origin m4.2
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 4 Completion Checkpoint
Template §0.4. Extra: demonstrate end-to-end: PHPUnit-created notification → Mailpit email. Merge:
```bash
git checkout main && git pull origin main
git merge --no-ff phase-4-notifications -m "merge: phase 4 notifications"
git push origin main
git tag -a phase-4-complete -m "Phase 4 complete" && git push origin phase-4-complete
```

---
# PHASE 5 – Secure Files, NGO Onboarding & Categories
**Branch:** `phase-5-ngo-files`

## Milestone 5.1 – Secure Upload Service & Media Gateway Core

**Depends on:** Phase 4 complete (audit, auth); **D-15** confirmed.

**1. Milestone Name:** Reusable secure file storage service and authorization-aware media streaming.

**2. Objective:** Spec §13.5: validated uploads stored outside the public root with random names and served only through an authorized endpoint — built once, used for NGO documents (M5.2) and donation images (M6.2).

**3. Tasks to Complete**
- [ ] `FileValidator`: extension allow-list, **MIME via `finfo`**, **magic-byte check**, max size (images 5 MB, documents 8 MB), for images `getimagesize` + max dimensions 6000×6000.
- [ ] `FileStorage`: writes to `STORAGE_PATH/private/{images|documents}/<2-char-shard>/<32-hex-random>.<ext>` with mode `0640`; never uses client filename for the path; computes sha256.
- [ ] `ImageSanitizer` (GD): re-encode JPEG/PNG/WebP to strip EXIF/metadata and any appended payload.
- [ ] Allowed: images `image/jpeg, image/png, image/webp`; documents `application/pdf` + the image types.
- [ ] `MediaController::stream($kind,$id)`: runs a `MediaPolicy` (per kind) → 404 if not allowed; sends `Content-Type` from DB, `X-Content-Type-Options: nosniff`, `Cache-Control: private, max-age=0`, `Content-Disposition` (inline for images, attachment for PDFs); streams with `readfile`.
- [ ] Reject: double extensions (`a.php.jpg` is stored as `.jpg` but content must verify), null bytes, `../` in names, zero-byte files, polyglots that fail re-encode.
- [ ] Ensure `storage/` is not under `public/` and web server config denies it (documented for M21).

**4. Files / Modules Affected:** `src/Services/{FileValidator,FileStorage,ImageSanitizer}.php`, `src/Controllers/MediaController.php`, `src/Support/MediaPolicy*.php`, `tests/Unit/FileValidatorTest.php`, `tests/fixtures/` (tiny known-good/bad sample files — **small synthetic files only**).

**5. Implementation Guidance:** Errors map to spec codes: oversize → 413, wrong type → 415, corrupt/invalid → 422. Delete the temp file on every failure path. Use `move_uploaded_file()` for real uploads (it verifies the file came from an HTTP upload). Keep validation separate from storage so unit tests don't touch disk.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-5.1-01 (P) | Valid JPEG | fixture | validate+store | Stored under random name; sha256 recorded | ☐ |
| T-5.1-02 (S) | PHP file renamed `.jpg` | fixture with `<?php` | upload | 415/422; nothing stored | ☐ |
| T-5.1-03 (S) | MIME spoof | text file with `Content-Type: image/png` | upload | Rejected (magic bytes) | ☐ |
| T-5.1-04 (E) | Oversize | 6 MB image | upload | 413 | ☐ |
| T-5.1-05 (E) | Zero-byte / truncated image | fixtures | upload | 422 | ☐ |
| T-5.1-06 (S) | Path traversal name | filename `../../x.jpg` | upload | Stored under random name; no write outside storage | ☐ |
| T-5.1-07 (S) | EXIF stripped | JPEG with GPS EXIF | store, re-read | No EXIF/GPS tags present | ☐ |
| T-5.1-08 (S) | Direct URL access | stored file | request `http://localhost:8000/storage/...` | 404 (not web-served) | ☐ |
| T-5.1-09 (S) | Media unauthenticated | stored file id | GET media without session | 401 | ☐ |
| T-5.1-10 (S) | Media unauthorized | file owned by A | B requests | 404 | ☐ |
| T-5.1-11 (P) | Headers on stream | authorised user | GET | `nosniff`, correct `Content-Type`, `private` cache | ☐ |
| T-5.1-12 (F) | File missing on disk | DB row, file deleted | GET | 404/410, error logged, no path leaked | ☐ |
| T-5.1-13 (E) | Huge dimensions (decompression bomb) | 20000×20000 PNG | upload | Rejected before decoding fully | ☐ |

**7. Verification Checklist:** [ ] Fixtures contain no real personal images. [ ] `ls -l backend/storage/private` shows no web-reachable path. [ ] `git status` doesn't list stored uploads.

**8. Milestone Completion Criteria:** DONE when every malicious fixture is rejected and stored files are reachable only through the policy-checked endpoint.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-5-ngo-files
git status
git add .
git commit -m "feat(media): secure upload validation, private storage and media gateway (M5.1)"
git push origin phase-5-ngo-files
git tag -a m5.1 -m "M5.1 secure uploads" && git push origin m5.1
```
*Do NOT commit:* `backend/storage/private/**`, any malicious sample that is a real executable (keep fixtures inert text/bytes).

**10. Rollback / Recovery:** **R-1**; delete locally generated files under `storage/private` (dev only).

---

## Milestone 5.2 – NGO Registration & Verification Documents

**Depends on:** M5.1, M4.2.

**1. Milestone Name:** NGO sign-up with organisation details and document upload; profile extensions.

**2. Objective:** Spec §6.2/§7.2: NGO account stays `pending` until an admin acts; pending NGOs can't perform verified-only actions.

**3. Tasks to Complete**
- [ ] Extend `POST /api/auth/register` to accept `role=ngo` with `multipart/form-data`: organization_name, registration_number, address_text, lat/lng, service_radius_km, contact phone, 1–3 documents. Creates `users` + `ngos(status=pending)` in **one transaction**; if file storage fails after DB work, roll back and delete stored files.
- [ ] `POST /api/profile/ngo-documents` and `GET` list (own); `DELETE` own document only while status ∈ {pending, correction_requested}.
- [ ] `PATCH /api/profile` extended for NGO fields; changing org name/registration number after verification sets status back to `pending` (re-verification rule — document it).
- [ ] `GET /api/media/ngo-documents/{id}` policy: owning NGO user or admin only.
- [ ] Notify admins (all active admins) with `ngo.application.submitted` (in-app only).
- [ ] Audit `ngo.registered`.

**4. Files / Modules Affected:** `AuthService`, `NgoRepository`, `NgoService`, `ProfileController`, `MediaPolicyNgoDocument`, tests.

**5. Implementation Guidance:** Registration with files is the one multi-step operation spanning DB+disk; implement a compensating cleanup (`try { … } catch { storage->deleteMany($written); throw; }`). Public registration must still reject `role=admin`.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-5.2-01 (P) | NGO registers with 1 PDF | CSRF ok | POST multipart | 201; `ngos.verification_status='pending'`; doc stored | ☐ |
| T-5.2-02 (V) | Missing org name / no document | – | submit | 422 field errors | ☐ |
| T-5.2-03 (S) | Invalid doc type (`.exe`) | – | submit | 415; **no user/ngo rows created** | ☐ |
| T-5.2-04 (F) | Disk write failure mid-way | simulate unwritable storage | submit | 500 generic; no partial user row; no orphan files | ☐ |
| T-5.2-05 (S) | Another user fetches doc | doc of NGO A | NGO B / donor GET media | 404 | ☐ |
| T-5.2-06 (P) | Admin fetches doc | admin session | GET media | 200 attachment | ☐ |
| T-5.2-07 (S) | Pending NGO hits verified-only route | – | call `VerifiedNgoMiddleware` route | 403 `NGO_NOT_VERIFIED` | ☐ |
| T-5.2-08 (E) | Doc limit | 4 docs | submit | 422 (max 3) | ☐ |
| T-5.2-09 (P) | Admin notified | active admin | register NGO | admin has unread notification | ☐ |
| T-5.2-10 (R) | Donor registration unchanged | – | run M3.1 suite | ✅ | ☐ |
| T-5.2-11 (S) | Re-verification trigger | verified NGO | change registration number | status → `pending` | ☐ |

**7. Verification Checklist:** [ ] Manual curl multipart test. [ ] DB shows one `users` + one `ngos` row per NGO. [ ] No uploaded files tracked by Git.

**8. Milestone Completion Criteria:** DONE when spec §19 item 7 passes (NGO can submit evidence; pending NGO can't do verified-only actions).

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(ngo): ngo registration, verification documents and document media policy (M5.2)"
git push origin phase-5-ngo-files
git tag -a m5.2 -m "M5.2 ngo onboarding" && git push origin m5.2
```
*Do NOT commit:* uploaded documents, sample registration certificates.

**10. Rollback / Recovery:** **R-1**; no migration here, so R-3 not needed.

---

## Milestone 5.3 – Admin NGO Verification Workflow

**Depends on:** M5.2, M3.3.

**1. Milestone Name:** Verification queue, detail, approve / reject / request-correction.

**2. Objective:** Spec §6.11/§7.5: admin decisions recorded with actor/timestamp, reflected immediately in NGO permissions, NGO notified.

**3. Tasks to Complete**
- [ ] `GET /api/admin/ngos?status=&q=&page=`; `GET /api/admin/ngos/{id}` (details + document list with media URLs).
- [ ] `POST /api/admin/ngos/{id}/verify` body `{decision: approve|reject|request_correction|suspend|reinstate, note}`; `note` required for reject/correction/suspend.
- [ ] Enforce legal transitions from `docs/state-model.md`; illegal → 409 `INVALID_TRANSITION`.
- [ ] Inside one transaction: update `ngos` (status, reviewed_by, reviewed_at, review_note), `Notifier` (in-app + email to NGO), `audit_logs` row (`ngo.verification.decision`).
- [ ] NGO `GET /api/profile` shows status + note so NGO can correct and resubmit (`POST /api/profile/ngo-resubmit` → `pending`).

**4. Files / Modules Affected:** `AdminNgoController`, `NgoVerificationService`, routes (all `admin` role + CSRF), tests.

**5. Implementation Guidance:** Decision logic lives in the service; controller just validates. Because middleware reads status from DB each request, an approved NGO gains rights on its **next** request without re-login (and a suspended NGO loses them at once).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-5.3-01 (P) | Approve pending NGO | pending NGO | POST approve | status `verified`, reviewer+time set, NGO notified (outbox row) | ☐ |
| T-5.3-02 (P) | Reject with note | pending | POST reject note | `rejected`; note visible to NGO | ☐ |
| T-5.3-03 (V) | Reject without note | – | POST reject | 422 | ☐ |
| T-5.3-04 (N) | Illegal transition | `rejected` NGO | POST approve | 409 `INVALID_TRANSITION` | ☐ |
| T-5.3-05 (S) | Non-admin | donor / NGO session | POST verify | 403 | ☐ |
| T-5.3-06 (S) | NGO tries to verify itself | NGO session | POST verify own id | 403 | ☐ |
| T-5.3-07 (I) | Permission change is immediate | NGO session open | admin approves → NGO calls verified-only route | 200 (no relogin) | ☐ |
| T-5.3-08 (I) | Suspend removes access | verified NGO | admin suspends | verified-only route → 403 | ☐ |
| T-5.3-09 (P) | Audit trail | – | any decision | `audit_logs` row with actor/target/result | ☐ |
| T-5.3-10 (F) | Email fails | SMTP down | approve | Decision still committed; outbox row pending | ☐ |
| T-5.3-11 (C) | Two admins decide simultaneously | pending NGO | parallel approve + reject | Exactly one wins; other gets 409 (use `SELECT … FOR UPDATE`) | ☐ |
| T-5.3-12 (E) | Filter/search/pagination | 25 NGOs | `?status=pending&page=2` | Correct slice, bounded size | ☐ |

**7. Verification Checklist:** [ ] Spec §19 item 8 ✅. [ ] Mailpit shows decision email without private data.

**8. Milestone Completion Criteria:** DONE when approval/rejection immediately changes permissions and every decision is audited.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(admin): ngo verification queue and decision workflow with audit (M5.3)"
git push origin phase-5-ngo-files
git tag -a m5.3 -m "M5.3 ngo verification" && git push origin m5.3
```
*Do NOT commit:* exported NGO documents.

**10. Rollback / Recovery:** **R-1**. If bad data got in dev DB: set status via SQL in dev only.

---

## Milestone 5.4 – Category & Compatibility Management

**Depends on:** M5.3 (admin guard), M1.4 seed.

**1. Milestone Name:** Public category list and admin category/compatibility CRUD.

**2. Objective:** Spec §6.11 "manage item categories": controlled taxonomy that donations/requirements reference; compatibility mapping for matching (D-12).

**3. Tasks to Complete**
- [ ] `GET /api/categories` (public; only `is_active`; includes compatible ids for admins only).
- [ ] `GET/POST /api/admin/categories`, `PATCH /api/admin/categories/{id}` (rename, description, `is_active`), `PUT /api/admin/categories/{id}/compatibility` (list of `{compatible_category_id, score_factor}`; symmetrical rows created both ways or treated symmetrically in queries — decide and document).
- [ ] Deactivation rule: cannot deactivate while *active* donations/requirements use it → 409 with counts (or allow with warning — choose and document).
- [ ] Unique name (case-insensitive) → 409.
- [ ] Audit `category.created/updated/deactivated`.

**4. Files / Modules Affected:** `CategoryRepository/Service`, `CategoryController`, `AdminCategoryController`, tests.

**5. Implementation Guidance:** Never hard-delete categories (historical records reference them) — deactivate only. Add `docs/matching.md` stub recording the compatibility semantics now, completed in Phase 8.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-5.4-01 (P) | Public list | seed | GET categories (no auth) | Active only | ☐ |
| T-5.4-02 (P) | Admin creates category | admin | POST | 201; audit row | ☐ |
| T-5.4-03 (V) | Duplicate name (case-insens.) | exists | POST `clothing` vs `Clothing` | 409/422 | ☐ |
| T-5.4-04 (V) | Empty / 200-char name | – | POST | 422 | ☐ |
| T-5.4-05 (S) | Non-admin write | donor | POST/PATCH | 403 | ☐ |
| T-5.4-06 (N) | Deactivate in-use category | active donation | PATCH `is_active=false` | Per decision: 409 with counts | ☐ |
| T-5.4-07 (P) | Set compatibility | two cats | PUT list | Rows saved; reread equals | ☐ |
| T-5.4-08 (V) | Self-compatibility / factor 150 | – | PUT | 422 | ☐ |
| T-5.4-09 (E) | Deactivated category hidden from public list but old data intact | – | GET list; GET old donation | Not listed; donation still readable | ☐ |

**7. Verification Checklist:** [ ] Seed categories visible via curl. [ ] Compatibility semantics written in `docs/matching.md`.

**8. Milestone Completion Criteria:** DONE when admin can manage categories safely and the public list is correct.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(admin): category and compatibility management (M5.4)"
git push origin phase-5-ngo-files
git tag -a m5.4 -m "M5.4 categories" && git push origin m5.4
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 5 Completion Checkpoint
Template §0.4. Extra: demo run — NGO registers → admin approves → NGO passes verified-only guard; hostile upload fixtures rejected.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-5-ngo-files -m "merge: phase 5 ngo and files"
git push origin main
git tag -a phase-5-complete -m "Phase 5 complete" && git push origin phase-5-complete
```

---

# PHASE 6 – Donations
**Branch:** `phase-6-donations`

## Milestone 6.1 – Donation Create / Read / Update / Deactivate

**Depends on:** Phase 5 complete (categories, auth).

**1. Milestone Name:** Donation CRUD with server validation and location privacy.

**2. Objective:** Spec §6.4: donors publish and manage listings; invalid/negative/implausible quantities are rejected; precise location protected (D-9).

**3. Tasks to Complete**
- [ ] `DonationRepository/Service/Controller`.
- [ ] `POST /api/donations`: title (3–120), category_id (active), description (≤2000), condition enum, total_quantity (1–10 000; also the initial `available_quantity`), lat/lng, address_text, pickup_notes, optional `expires_at` (future), `status` ∈ {`draft`,`active`} default `active`.
- [ ] On write: compute `location` (exact) and `location_public` (snapped to 0.005° grid) in SQL/PHP, single helper `LocationPrivacy::snap()`.
- [ ] `GET /api/donations?scope=mine&status=&category_id=&page=` (donor: own only; filters/pagination); `GET /api/donations/{id}` (owner: full; verified NGO / admin: public view without exact coords/address for `active|partially_allocated` only; others 404).
- [ ] `PATCH /api/donations/{id}`: owner only; **cannot reduce `total_quantity` below (total − available)** i.e. below already-allocated amount; cannot edit `category_id` once any allocation exists; changing `total_quantity` adjusts `available_quantity` by the delta inside a locked transaction.
- [ ] `DELETE /api/donations/{id}`: deactivate/archive → `closed`; blocked (409) while *open* allocations (`reserved|confirmed|collected`) exist.
- [ ] Audit create/update/close.

**4. Files / Modules Affected:** `src/Repositories/DonationRepository.php`, `src/Services/DonationService.php`, `src/Controllers/DonationController.php`, `src/Support/LocationPrivacy.php`, tests.

**5. Implementation Guidance:** Use serializer functions: `DonationResource::forOwner()` vs `::forPublic()` — the public one must **not even load** exact location fields. `FOR UPDATE` on the donation row during quantity edits. Pagination via M4.2 helper.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-6.1-01 (P) | Create valid donation | donor | POST 20 winter clothes | 201; `available=total=20`; status `active` | ☐ |
| T-6.1-02 (V) | Missing required fields | – | POST `{}` | 422 listing all missing fields | ☐ |
| T-6.1-03 (V) | Quantity 0, −3, 10001, `"abc"`, 2.5 | – | POST each | All 422 | ☐ |
| T-6.1-04 (V) | Inactive/unknown category | – | POST | 422 | ☐ |
| T-6.1-05 (V) | Lat 95 / lng 200 / expires in past | – | POST | 422 | ☐ |
| T-6.1-06 (S) | NGO/anonymous creates donation | NGO session | POST | 403 / 401 | ☐ |
| T-6.1-07 (S) | Mass assignment | – | POST with `available_quantity:999`, `status:'completed'`, `donor_id:2` | Ignored or 422; donor_id from session | ☐ |
| T-6.1-08 (P) | Owner list with filters | 15 donations | `?status=active&page=2` | Only own, correct slice | ☐ |
| T-6.1-09 (S) | Other donor GET/PATCH/DELETE | donation of A | B | 404 (GET), 403/404 (writes) | ☐ |
| T-6.1-10 (S) | Public view hides exact location | verified NGO | GET `{id}` | Coords equal snapped values; no `address_text` | ☐ |
| T-6.1-11 (E) | Reduce total below allocated | 10 allocated of 20 | PATCH total 8 | 409/422 | ☐ |
| T-6.1-12 (P) | Edit quantity up | 20 total, 12 available | PATCH total 30 | available 22 | ☐ |
| T-6.1-13 (N) | Close with open allocation | reserved allocation | DELETE | 409 | ☐ |
| T-6.1-14 (P) | Close without allocations | – | DELETE | status `closed`; still readable by owner | ☐ |
| T-6.1-15 (S) | XSS in title/description | `<img onerror=…>` | POST | Stored verbatim as text, returned JSON-escaped | ☐ |
| T-6.1-16 (S) | SQLi in filters | – | `?category_id=1 OR 1=1` | 422; no SQL error | ☐ |

**7. Verification Checklist:** [ ] Spec §19 items 3–4 ✅ (images in M6.2). [ ] Query log shows parameterised statements only. [ ] Pagination bounded.

**8. Milestone Completion Criteria:** DONE when donation lifecycle rules above hold and no public response contains exact coordinates.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-6-donations
git status
git add .
git commit -m "feat(donations): donation crud with validation and location privacy (M6.1)"
git push origin phase-6-donations
git tag -a m6.1 -m "M6.1 donation crud" && git push origin m6.1
```
*Do NOT commit:* seed files with real addresses.

**10. Rollback / Recovery:** **R-1** (no migration).

---

## Milestone 6.2 – Donation Image Upload & Access

**Depends on:** M6.1, M5.1.

**1. Milestone Name:** Upload, list, delete donation images with authorised access.

**2. Objective:** Spec §6.4 + §13.5: ≤ 5 images per donation, validated, EXIF-stripped, served only to permitted users (D-15).

**3. Tasks to Complete**
- [ ] `POST /api/donations/{id}/images` (multipart, field `images[]`, 1–5 files per request, total ≤ 5 per donation); `DELETE /api/donations/{id}/images/{imageId}`; first image becomes thumbnail (`sort_order`).
- [ ] Include `images: [{id, url:"/api/media/donation-images/{id}"}]` in donation responses.
- [ ] `MediaPolicy` for `donation-images`: owner, admin, or **verified+active NGO** when donation is `active|partially_allocated|fully_allocated`(with own allocation); otherwise 404.
- [ ] Orphan cleanup on donation removal; compensating delete if DB insert fails after file write.

**4. Files / Modules Affected:** `DonationImageService/Repository`, `DonationImageController`, `MediaPolicyDonationImage`, tests.

**5. Implementation Guidance:** Process each file independently but commit all-or-nothing: validate all first, then store, then insert rows; clean up on failure. Do not trust `getimagesize` alone.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-6.2-01 (P) | Upload 2 valid images | owner donor | POST multipart | 201; 2 rows; files exist | ☐ |
| T-6.2-02 (S) | Fake image (text) | – | upload | 415/422; no rows/files | ☐ |
| T-6.2-03 (E) | 6th image | already 5 | upload | 422 | ☐ |
| T-6.2-04 (E) | Oversized file | 6 MB | upload | 413 | ☐ |
| T-6.2-05 (S) | Non-owner uploads | donation of A | B POST | 403/404 | ☐ |
| T-6.2-06 (P) | Verified NGO views image | active donation | GET media | 200 | ☐ |
| T-6.2-07 (S) | Pending NGO views image | – | GET | 404 | ☐ |
| T-6.2-08 (S) | Anonymous views | – | GET | 401 | ☐ |
| T-6.2-09 (S) | Image of `draft`/`closed` donation by NGO | – | GET | 404 | ☐ |
| T-6.2-10 (P) | Delete own image | – | DELETE | Row + file removed | ☐ |
| T-6.2-11 (F) | Mixed batch (1 good, 1 bad) | – | upload | Entire request rejected; no partial state | ☐ |
| T-6.2-12 (R) | M6.1 + M5.1 suites | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Spec §19 items 5–6 ✅. [ ] Stored file has no EXIF (`exiftool` if available). [ ] Disk file count equals DB row count.

**8. Milestone Completion Criteria:** DONE when only permitted users can fetch images and bad uploads leave no residue.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(donations): secure donation image upload and access policy (M6.2)"
git push origin phase-6-donations
git tag -a m6.2 -m "M6.2 donation images" && git push origin m6.2
```
*Do NOT commit:* uploaded images, `backend/storage/**`.

**10. Rollback / Recovery:** **R-1**; clear `storage/private/images` in dev if needed.

---

## Milestone 6.3 – Moderation Hooks, Expiry & Status Derivation

**Depends on:** M6.2, M5.3.

**1. Milestone Name:** Admin moderation of listings, expiry job and derived donation status.

**2. Objective:** Spec §6.11 moderation + §8.3 expiry rules: admin can remove a listing; expired listings stop matching; a single function derives `active / partially_allocated / fully_allocated` from quantities (used in Phase 9).

**3. Tasks to Complete**
- [ ] `POST /api/admin/donations/{id}/moderate` `{action: remove|restore, reason}` → `removed` / back to previous status; notify donor; audit.
- [ ] `DonationStatus::derive(total, available, hasOpenAllocations)` pure function with unit tests (to be used by `AllocationService`).
- [ ] `bin/expire-donations.php`: sets `closed` for `expires_at < now()` with no open allocations; idempotent.
- [ ] Removal with open allocations: blocks (409) unless `force=true` → cancels allocations, returns requirement quantity (full behaviour verified in M9.2; here just guard + TODO test marked pending).

**4. Files / Modules Affected:** `AdminDonationController`, `DonationStatus.php`, `bin/expire-donations.php`, tests.

**5. Implementation Guidance:** Keep `derive()` pure so the concurrency-critical code in Phase 9 has a trivially testable dependency.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-6.3-01 (P) | Admin removes listing | active donation | POST remove + reason | status `removed`; donor notified; audit row | ☐ |
| T-6.3-02 (S) | Non-admin moderate | donor | POST | 403 | ☐ |
| T-6.3-03 (V) | Missing reason | – | POST | 422 | ☐ |
| T-6.3-04 (P) | Removed hidden from NGO | – | NGO GET/ list | 404 / absent | ☐ |
| T-6.3-05 (P) | Restore | removed | POST restore | back to active | ☐ |
| T-6.3-06 (P) | derive(): matrix | unit | (20,20)→active; (20,8)→partially_allocated; (20,0)→fully_allocated | Correct | ☐ |
| T-6.3-07 (E) | derive(): invalid inputs | unit | available>total | Throws | ☐ |
| T-6.3-08 (P) | Expiry job | donation expired yesterday | run job | `closed` | ☐ |
| T-6.3-09 (E) | Expiry job idempotent / skips open allocations | – | run twice; with allocation | No change second run; allocation untouched | ☐ |

**7. Verification Checklist:** [ ] Cron line documented in README (not yet installed). [ ] All Phase 6 tests ✅.

**8. Milestone Completion Criteria:** DONE when moderation and expiry work and status derivation is unit-proven.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(donations): moderation, expiry job and status derivation (M6.3)"
git push origin phase-6-donations
git tag -a m6.3 -m "M6.3 moderation" && git push origin m6.3
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 6 Completion Checkpoint
Template §0.4. Extra: spec §19 items 3–6 ✅. Run a privacy review: dump a verified-NGO response of a donation and confirm no exact coordinates/address.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-6-donations -m "merge: phase 6 donations"
git push origin main
git tag -a phase-6-complete -m "Phase 6 complete" && git push origin phase-6-complete
```

---

# PHASE 7 – NGO Requirements
**Branch:** `phase-7-requirements`

## Milestone 7.1 – Requirement CRUD, Close & Progress Tracking

**Depends on:** Phase 6 complete, M5.3 (verified NGOs).

**1. Milestone Name:** Verified-NGO requirement management.

**2. Objective:** Spec §6.6: NGOs publish needs (category, quantity, urgency, location/radius, needed-by), edit/close them, and see quantity progress.

**3. Tasks to Complete**
- [ ] `POST /api/requirements` (title, category_id, description, quantity_needed 1–100 000, urgency enum, min_condition optional, lat/lng or default NGO location, radius_km 1–500, needed_by ≥ today optional, `status` draft|active).
- [ ] `GET /api/requirements?status=&page=` (own NGO), `GET /api/requirements/{id}` (owner NGO or admin; includes `quantity_allocated`, `quantity_fulfilled`, `outstanding = needed − allocated`).
- [ ] `PATCH /api/requirements/{id}`: can't set `quantity_needed` below `quantity_allocated`; can't change category once allocations exist.
- [ ] `POST /api/requirements/{id}/close`; `DELETE` = same as close when allocations exist, hard-delete only for `draft` with zero history.
- [ ] Only `VerifiedNgoMiddleware` routes; suspended NGO's active requirements are excluded from matching later (flag check in M8.1).
- [ ] Audit create/update/close.

**4. Files / Modules Affected:** `RequirementRepository/Service/Controller`, tests.

**5. Implementation Guidance:** Expose `outstanding` computed, not stored. `quantity_allocated` / `quantity_fulfilled` are **never writable** through this API (only `AllocationService` in Phase 9/10).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-7.1-01 (P) | Verified NGO creates requirement | verified NGO | POST 12 winter clothes, urgency high | 201; outstanding 12 | ☐ |
| T-7.1-02 (S) | Pending/rejected NGO | pending NGO | POST | 403 `NGO_NOT_VERIFIED` | ☐ |
| T-7.1-03 (S) | Donor creates | donor | POST | 403 | ☐ |
| T-7.1-04 (V) | Bad quantity/urgency/radius/date | – | POST each | 422 | ☐ |
| T-7.1-05 (S) | Mass assignment of counters | – | POST `quantity_allocated:5` | Ignored/422 | ☐ |
| T-7.1-06 (S) | Another NGO accesses | requirement of NGO A | NGO B GET/PATCH | 404/403 | ☐ |
| T-7.1-07 (E) | Lower needed below allocated | allocated 5 (set via DB) | PATCH needed 3 | 409/422 | ☐ |
| T-7.1-08 (P) | Close requirement | active | POST close | `closed`; excluded from active lists | ☐ |
| T-7.1-09 (N) | Edit closed requirement | closed | PATCH | 409 | ☐ |
| T-7.1-10 (P) | Pagination/filter | 25 reqs | `?status=active&page=2` | Correct | ☐ |
| T-7.1-11 (I) | NGO suspended after create | active requirement | admin suspends NGO | NGO can't PATCH (403); requirement flagged excluded (checked M8.1) | ☐ |
| T-7.1-12 (R) | Previous suites | – | `verify.sh` | ✅ | ☐ |

**7. Verification Checklist:** [ ] Spec §19 item 9 ✅. [ ] Counters unmodifiable via API.

**8. Milestone Completion Criteria:** DONE when verified NGOs fully manage requirements and counters are server-controlled.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-7-requirements
git status
git add .
git commit -m "feat(requirements): ngo requirement crud, close and progress fields (M7.1)"
git push origin phase-7-requirements
git tag -a m7.1 -m "M7.1 requirements" && git push origin m7.1
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 7 Completion Checkpoint
Template §0.4.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-7-requirements -m "merge: phase 7 requirements"
git push origin main
git tag -a phase-7-complete -m "Phase 7 complete" && git push origin phase-7-complete
```

---
# PHASE 8 – Smart Matching Engine
**Branch:** `phase-8-matching`

## Milestone 8.1 – Eligibility Filter & Spatial Candidate Query

**Depends on:** Phase 7 complete, **D-12**.

**1. Milestone Name:** Eligibility rules and PostGIS candidate retrieval.

**2. Objective:** Spec §8.3–8.4: before any ranking, produce only *eligible* (requirement, donation) pairs using `ST_DWithin` on a GiST index and consistent metre units.

**3. Tasks to Complete**
- [ ] `MatchRepository::candidatesForRequirement($reqId, $limit)` — SQL joins `ngo_requirements r` × `donations d` with:
  - `d.status IN ('active','partially_allocated')`, `d.available_quantity > 0`, `(d.expires_at IS NULL OR d.expires_at > now())`
  - `r.status IN ('active','partially_fulfilled')`, `r.quantity_needed − r.quantity_allocated > 0`
  - NGO `verification_status='verified'` and user `active`; donor `active`
  - category identical **or** present in `category_compatibility` (both directions per M5.4 decision)
  - condition ≥ `r.min_condition` using an ordered rank (`new>like_new>good>fair`)
  - `ST_DWithin(d.location_public, r.location, r.radius_km*1000)`
  - exclude donations already having a pending/open request from this NGO for this requirement
- [ ] Mirror query `candidatesForDonation($donationId)` (donor view: matching NGO requirements).
- [ ] Return raw distance in metres (`ST_Distance`).
- [ ] `docs/matching.md`: eligibility table (spec §8.3 one row each).

**4. Files / Modules Affected:** `src/Repositories/MatchRepository.php`, `docs/matching.md`, `tests/Integration/MatchEligibilityTest.php`.

**5. Implementation Guidance:** Use the *public (snapped)* location for matching/distance so ranking can't leak precise coordinates via distance probing. Run `EXPLAIN` now and keep the plan in `docs/matching.md`; formal benchmark is M18.2. Do **not** compute scores in SQL yet — scoring is a pure PHP function (M8.2) so it's unit-testable.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-8.1-01 (P) | Same category within radius | 1 req, 1 donation 2 km away | query | 1 candidate, distance ≈ 2000 m | ☐ |
| T-8.1-02 (N) | Outside radius | donation 80 km, radius 50 | query | Excluded | ☐ |
| T-8.1-03 (E) | Exactly at radius boundary | distance = radius | query | Included (≤) — document | ☐ |
| T-8.1-04 (N) | Incompatible category | cat A vs B, no mapping | query | Excluded | ☐ |
| T-8.1-05 (P) | Compatible category | mapping A↔B | query | Included, flagged `category_match='compatible'` | ☐ |
| T-8.1-06 (N) | Zero available / fully allocated | available 0 | query | Excluded | ☐ |
| T-8.1-07 (N) | Requirement satisfied | allocated = needed | query | Excluded | ☐ |
| T-8.1-08 (N) | Inactive states | donation `closed`/`removed`/`draft`; req `closed` | query | Excluded | ☐ |
| T-8.1-09 (S) | Unverified/suspended NGO | status pending/suspended | query | Excluded | ☐ |
| T-8.1-10 (N) | Condition too low | req min `good`, donation `fair` | query | Excluded | ☐ |
| T-8.1-11 (N) | Expired donation | `expires_at` past | query | Excluded | ☐ |
| T-8.1-12 (P) | Reverse query (donor side) | – | `candidatesForDonation` | Same pairs mirrored | ☐ |
| T-8.1-13 (S) | Precise location not used | donation exact vs snapped differ | inspect SQL/plan | Only `location_public` referenced | ☐ |
| T-8.1-14 (P) | Index used | 10k generated donations | `EXPLAIN` | GiST scan on `location_public` | ☐ |

**7. Verification Checklist:** [ ] Fixture builder creates donor/NGO/requirement/donation in 1 line for tests (reused later). [ ] SQL reviewed for parameter binding (no string-concatenated radius).

**8. Milestone Completion Criteria:** DONE when each eligibility rule (spec §8.3) has a failing-then-passing test and the index is used.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-8-matching
git status
git add .
git commit -m "feat(matching): eligibility rules and postgis candidate queries (M8.1)"
git push origin phase-8-matching
git tag -a m8.1 -m "M8.1 eligibility" && git push origin m8.1
```
*Do NOT commit:* generated 10k-row SQL dumps.

**10. Rollback / Recovery:** **R-1** (no migration unless you added an index — then **R-3**).

---

## Milestone 8.2 – Scoring, Explanations & Matches API (+ Map Data)

**Depends on:** M8.1.

**1. Milestone Name:** Weighted score, explanation reasons, `/api/matches`, `/api/map/donations`.

**2. Objective:** Spec §8.2/§8.5: `MatchScore = 0.35·S_item + 0.30·S_dist + 0.20·S_urg + 0.15·S_qty`, each 0–100, with human-readable reasons — and endpoints for list and map views.

**3. Tasks to Complete**
- [ ] `MatchScorer` pure class, weights from config constants (documented as design choices, not "optimal"):
  - `S_item` = 100 exact category; `score_factor` (default 60) compatible.
  - `S_dist` = `100 × (1 − d/R)` clamped [0,100] (d, R in metres).
  - `S_urg` = base {low 25, medium 50, high 75, critical 100} + deadline boost `25 × (1 − days_left/14)` when `0 ≤ days_left ≤ 14` (cap 100).
  - `S_qty` = `100 × min(available, outstanding) / outstanding`.
- [ ] Deterministic tie-break: score DESC, distance ASC, donation id ASC.
- [ ] `ExplanationBuilder`: e.g. "Same category", "4.2 km away (within your 25 km radius)", "High urgency", "Can fulfil 8 of 12 requested units"; never promises suitability.
- [ ] `GET /api/matches?requirement_id=` (owner verified NGO): paged ranked results with `score`, `score_band` (High ≥ 75 / Medium 50–74 / Low < 50), `components`, `reasons`, public donation summary + thumbnail URL.
- [ ] `GET /api/matches?donation_id=` (donor owner): ranked NGO requirements with NGO org name, distance, reasons (no NGO documents).
- [ ] `GET /api/map/donations?requirement_id=` and `?bbox=` : GeoJSON-like list `{id,title,lat,lng(public),category,qty}` capped at 200 markers.
- [ ] Complete `docs/matching.md` (formula, assumptions, "weights are proposed, not proven").

**4. Files / Modules Affected:** `src/Services/{MatchScorer,ExplanationBuilder,MatchService}.php`, `MatchController`, `MapController`, `tests/Unit/MatchScorerTest.php`, integration tests.

**5. Implementation Guidance:** All ranking behaviour is in the pure scorer; the repository only filters. Avoid claiming accuracy percentages (spec §8.6, Appendix C). Add a hand-built "golden" fixture set of ≥ 10 pairs with expected order — this is the evaluation approach the spec recommends. For AI/ML-style validation: assert score ∈ [0,100], components never NaN, ranking stable across runs.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-8.2-01 (P) | Weights sum | unit | sum constants | Exactly 1.00 | ☐ |
| T-8.2-02 (P) | Perfect match | exact cat, d=0, critical, full qty | score | 100 | ☐ |
| T-8.2-03 (E) | Distance edges | d=0, d=R, d>R | `S_dist` | 100, 0, 0 (clamped; >R excluded earlier) | ☐ |
| T-8.2-04 (E) | Quantity fit | avail 8/outst 12; avail 50/outst 12; avail 1/outst 100 | `S_qty` | 66.7, 100, 1 | ☐ |
| T-8.2-05 (E) | Urgency boost | high + 3 days left; high + no date; low + 20 days | `S_urg` | boosted ≤100; 75; 25 | ☐ |
| T-8.2-06 (P) | Ranking order | golden set (≥10) | rank | Matches expected order | ☐ |
| T-8.2-07 (E) | Tie-break deterministic | equal scores | rank twice | Same order both times | ☐ |
| T-8.2-08 (V) | Output sanity | random fuzz 1000 inputs | scorer | All in [0,100], no NaN/INF | ☐ |
| T-8.2-09 (P) | Explanations present & accurate | – | API | Reasons mention actual numbers; "8 of 12" correct | ☐ |
| T-8.2-10 (S) | Another NGO's requirement | NGO B asks `requirement_id` of A | GET | 404 | ☐ |
| T-8.2-11 (S) | Pending NGO | – | GET | 403 | ☐ |
| T-8.2-12 (S) | No exact location / address in payload | – | inspect JSON | Only snapped coords; no `address_text` | ☐ |
| T-8.2-13 (P) | Donor-side matches | donor's donation | GET `?donation_id=` | Ranked requirements, no private NGO data | ☐ |
| T-8.2-14 (E) | Empty result | no candidates | GET | 200, `data: []`, helpful `meta.hint` | ☐ |
| T-8.2-15 (E) | Pagination + map cap | 500 candidates | GET list & map | Page size ≤ 50; map ≤ 200 | ☐ |
| T-8.2-16 (V) | Missing/invalid params | – | no ids / both ids / non-numeric | 422 | ☐ |
| T-8.2-17 (R) | M8.1 suite | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Hand-calculate two scores on paper and compare. [ ] `docs/matching.md` states weights = design choices. [ ] Spec §19 items 10–11 backend half ✅.

**8. Milestone Completion Criteria:** DONE when ranking is deterministic, bounded, explainable, role-protected and privacy-preserving.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(matching): weighted scorer, explanations, matches and map endpoints (M8.2)"
git push origin phase-8-matching
git tag -a m8.2 -m "M8.2 matching api" && git push origin m8.2
```
*Do NOT commit:* benchmark dumps.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 8 Completion Checkpoint
Template §0.4. Extra: print the golden-set ranking table into `docs/matching.md`; confirm reviewer agrees with it.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-8-matching -m "merge: phase 8 matching"
git push origin main
git tag -a phase-8-complete -m "Phase 8 complete" && git push origin phase-8-complete
```

---

# PHASE 9 – Requests & Safe Allocation
**Branch:** `phase-9-allocation`
> **Before starting: confirm D-1 (reserve at request time) and D-17 (72 h expiry).** This is the highest-risk phase; nothing here may be pushed with a failing concurrency test.

## Milestone 9.1 – Create Request with Atomic Reservation

**Depends on:** Phase 8 complete.

**1. Milestone Name:** `POST /api/requests` with transactional allocation.

**2. Objective:** Spec §6.8/§7.3: verify, lock, re-check, reserve, record — atomically; losing racers get HTTP 409.

**3. Tasks to Complete**
- [ ] `AllocationService::createRequest(ngoId, donationId, requirementId, quantity)`:
  ```
  BEGIN
  SELECT … FROM donations         WHERE id=:d FOR UPDATE      -- lock order: donation first
  SELECT … FROM ngo_requirements  WHERE id=:r FOR UPDATE      -- then requirement (always this order)
  checks: roles/ownership, NGO verified+active, donation status ∈ (active, partially_allocated) & not expired,
          requirement active & owned by NGO, category eligible (reuse M8.1 predicate),
          0 < qty ≤ donation.available_quantity  AND  qty ≤ requirement.quantity_needed − quantity_allocated
  UPDATE donations SET available_quantity = available_quantity − :q, status = derive(…) WHERE id=:d AND available_quantity >= :q   -- rowCount must be 1
  UPDATE ngo_requirements SET quantity_allocated = quantity_allocated + :q, status = … WHERE id=:r AND quantity_allocated + :q <= quantity_needed
  INSERT donation_requests(status='pending', expires_at = now()+72h), allocations(status='reserved')
  Notifier → donor (request.created); AuditLogger inside tx
  COMMIT
  ```
- [ ] Failure mapping: insufficient stock/outstanding → 409 `INSUFFICIENT_QUANTITY` with current `available`; ineligible state → 409/422; not verified → 403.
- [ ] Idempotency guard: header `Idempotency-Key` stored with request (unique per NGO) so a double-click/retry returns the original result instead of reserving twice. *(Why: spec §10.1 "prevent duplicate submissions"; client disabling buttons isn't enough.)*
- [ ] Duplicate rule: one *pending* request per (NGO, donation, requirement) → 409.
- [ ] Migration `005_request_idempotency` (adds `idempotency_key` + unique index `(ngo_id, idempotency_key)`).
- [ ] `GET /api/requests` (NGO: own; donor: received for own donations; admin: all) with filters; `GET /api/requests/{id}`.

**4. Files / Modules Affected:** `src/Services/AllocationService.php`, `RequestRepository`, `AllocationRepository`, `RequestController`, `db/migrations/005_*`, tests.

**5. Implementation Guidance:** Transaction isolation `READ COMMITTED` + explicit row locks is sufficient and simpler than `SERIALIZABLE` retries. Fixed lock order prevents deadlocks; still catch SQLSTATE `40P01` and retry once. Never compute remaining stock in PHP and write it back — use the conditional `UPDATE … WHERE available_quantity >= :q` as the authority. Emails go through the outbox in the same transaction (so a rollback removes them).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-9.1-01 (P) | Request 8 of 20 | verified NGO, req needs 12 | POST qty 8 | 201; donation available 12, status `partially_allocated`; requirement allocated 8; allocation `reserved` | ☐ |
| T-9.1-02 (P) | Second request 5 (different NGO) | after 01 | POST | Success; available 7 (partial allocation across NGOs) | ☐ |
| T-9.1-03 (N) | Exceeds available | available 7 | POST 8 | 409 `INSUFFICIENT_QUANTITY`; no row changes | ☐ |
| T-9.1-04 (N) | Exceeds outstanding need | outstanding 4 | POST 5 | 409/422 | ☐ |
| T-9.1-05 (V) | qty 0, −1, `"x"`, 1.5 | – | POST | 422 | ☐ |
| T-9.1-06 (S) | Pending/suspended NGO | – | POST | 403 | ☐ |
| T-9.1-07 (S) | Requirement of another NGO | – | POST with foreign requirement_id | 403/404 | ☐ |
| T-9.1-08 (S) | Donor/anonymous | – | POST | 403/401 | ☐ |
| T-9.1-09 (N) | Closed/removed/expired donation | – | POST | 409 | ☐ |
| T-9.1-10 (E) | Take exact remainder | available 7 | POST 7 | OK; status `fully_allocated`; available 0 | ☐ |
| T-9.1-11 (E) | Idempotent retry | same `Idempotency-Key` twice | POST ×2 | Same response; stock reserved once | ☐ |
| T-9.1-12 (N) | Duplicate pending request | – | same triple | 409 | ☐ |
| T-9.1-13 (F) | DB error mid-transaction | inject failure after first UPDATE | POST | Full rollback: counters unchanged, no request/allocation/outbox rows | ☐ |
| T-9.1-14 (I) | Donor sees request | – | donor GET `/requests` | Listed with NGO name, qty | ☐ |
| T-9.1-15 (S) | Visibility | NGO B GET `/requests/{A's}` | – | 404 | ☐ |
| T-9.1-16 (I) | Audit + notification | – | after success | Audit row `request.created`; donor notification | ☐ |

**7. Verification Checklist:** [ ] Reconcile query: `available + SUM(open+completed allocations) = total` for every donation. [ ] DB CHECK constraints never fired in happy-path logs. [ ] Spec §19 item 12 ✅ (concurrency in M9.3).

**8. Milestone Completion Criteria:** DONE when single-threaded rules pass and rollback leaves zero residue.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-9-allocation
git status
git add .
git commit -m "feat(allocation): atomic request creation with row locks and idempotency (M9.1)"
git push origin phase-9-allocation
git tag -a m9.1 -m "M9.1 request creation" && git push origin m9.1
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-3** `down --steps=1` (migration 005) then **R-1**. If bad allocation data exists in dev: **R-4** (dev only). *Never* hand-edit counters in a shared DB without a reconcile script.

---

## Milestone 9.2 – Accept, Reject, Cancel & Expire

**Depends on:** M9.1.

**1. Milestone Name:** Request lifecycle transitions with quantity return.

**2. Objective:** Donor accepts/rejects; NGO cancels; system expires after 72 h — each transition is locked, validated against the state model, and releases or confirms stock correctly.

**3. Tasks to Complete**
- [ ] `POST /api/requests/{id}/accept` (donor owner): request `pending→accepted`, allocation `reserved→confirmed`; notify NGO; audit.
- [ ] `POST /api/requests/{id}/reject` (donor owner, reason optional): `pending→rejected`, allocation `cancelled`, **return qty**: `donations.available_quantity += q` (status re-derived), `requirement.quantity_allocated −= q`.
- [ ] `POST /api/requests/{id}/cancel` (NGO owner): allowed while `pending` or `accepted` with pickup not yet `collected`; same return-stock logic; cancels any linked pickup (M10 hook).
- [ ] `bin/expire-requests.php`: expires `pending` where `expires_at < now()` (same return logic); idempotent; notifies both parties.
- [ ] Illegal transitions → 409 `INVALID_TRANSITION`; double accept → 409 (or idempotent 200 — choose and document).
- [ ] Locks acquired in the same order (donation → requirement → request) to avoid deadlocks.
- [ ] Admin removal-with-force (M6.3) now cancels allocations through this same service.

**4. Files / Modules Affected:** `AllocationService`, `RequestController`, `bin/expire-requests.php`, `AdminDonationController` (force path), tests.

**5. Implementation Guidance:** Implement one private `releaseReservation(allocationId, newStatus)` used by reject/cancel/expire/force-remove — a single place that returns stock keeps the arithmetic provably consistent.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-9.2-01 (P) | Donor accepts | pending 8 | POST accept | request `accepted`; allocation `confirmed`; NGO notified | ☐ |
| T-9.2-02 (P) | Donor rejects | pending 8 | POST reject | request `rejected`; donation available +8 and status re-derived; requirement allocated −8 | ☐ |
| T-9.2-03 (S) | Other donor accepts | request on A's donation | B POST | 403/404 | ☐ |
| T-9.2-04 (S) | NGO tries accept | – | POST accept | 403 | ☐ |
| T-9.2-05 (N) | Accept after reject | rejected | POST accept | 409 `INVALID_TRANSITION` | ☐ |
| T-9.2-06 (E) | Double accept | accepted | POST accept again | Per decision (409 or idempotent); counters unchanged | ☐ |
| T-9.2-07 (P) | NGO cancels pending | – | POST cancel | stock returned | ☐ |
| T-9.2-08 (P) | NGO cancels accepted (pre-pickup) | – | POST cancel | stock returned; donor notified | ☐ |
| T-9.2-09 (N) | Cancel after collected | allocation `collected` | POST cancel | 409 | ☐ |
| T-9.2-10 (P) | Expiry job | request 73 h old (fake clock) | run | `expired`; stock returned | ☐ |
| T-9.2-11 (E) | Expiry idempotent | run twice | – | No double return | ☐ |
| T-9.2-12 (I) | Returned stock re-requestable | after reject | another NGO requests | Success | ☐ |
| T-9.2-13 (C) | Reject vs cancel race | pending | parallel reject + cancel | Exactly one succeeds; stock returned once | ☐ |
| T-9.2-14 (I) | Reconcile invariant | after all above | run reconcile query | Holds for every donation & requirement | ☐ |
| T-9.2-15 (R) | M9.1 suite | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Reconcile query added to `tests/Support/Invariants.php` and called in `tearDown` of all allocation tests. [ ] Audit rows for each transition.

**8. Milestone Completion Criteria:** DONE when every transition obeys the state model and the invariant holds after each test.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(allocation): accept, reject, cancel and expiry with stock release (M9.2)"
git push origin phase-9-allocation
git tag -a m9.2 -m "M9.2 request lifecycle" && git push origin m9.2
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-1**.

---

## Milestone 9.3 – Concurrency & Overselling Proof

**Depends on:** M9.2.

**1. Milestone Name:** Concurrency test suite and load proof for allocation.

**2. Objective:** Spec §15.3/§19 item 13: prove two or more simultaneous requests can never over-allocate.

**3. Tasks to Complete**
- [ ] `tests/Concurrency/OversellTest.php` using real parallel processes (`proc_open` workers or `pcntl_fork`) — each worker opens its **own** DB connection and session.
- [ ] Scenario A: donation available 10; 5 NGOs each request 4 simultaneously ⇒ at most 2 succeed (8), others 409; available = 2.
- [ ] Scenario B: two NGOs request exactly the last 3 ⇒ exactly one 201, one 409.
- [ ] Scenario C: one NGO requirement outstanding 5, two donations, parallel requests of 5 each ⇒ only one succeeds (requirement over-fulfilment prevented).
- [ ] Scenario D: accept/reject/cancel storm on same request.
- [ ] k6 script `tests/load/allocation.js` firing 50 VUs at one donation (available 100, each asks 3) — assert `sum(successes×3) ≤ 100` via DB query afterward.
- [ ] Run each scenario ≥ 20 repetitions to expose flakiness.
- [ ] Invariants checked after each run.

**4. Files / Modules Affected:** `tests/Concurrency/*`, `tests/load/allocation.js`, `tests/Support/Invariants.php`, README (how to run).

**5. Implementation Guidance:** Concurrency tests must **commit** real data (no wrapping transaction) and truncate afterwards; run against `sharesphere_test`. If any run oversells, fix the service (usually a missing lock or a read-then-write), never loosen the test. Record environment (CPU, PG version, N, repetitions) for `docs/test-report.md`.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-9.3-01 (C) | Scenario A ×20 | stock 10 | run | Never >2 successes; available ≥ 0 | ☐ |
| T-9.3-02 (C) | Scenario B ×20 | stock 3 | run | Exactly 1×201 and 1×409 each time | ☐ |
| T-9.3-03 (C) | Scenario C ×20 | outstanding 5 | run | Requirement `allocated ≤ needed` always | ☐ |
| T-9.3-04 (C) | Scenario D ×20 | pending request | run | No double stock return | ☐ |
| T-9.3-05 (C) | k6 50 VUs | stock 100 | `k6 run tests/load/allocation.js` | Allocated total ≤ 100; no 5xx | ☐ |
| T-9.3-06 (F) | Deadlock injection | opposite lock order in a test-only path | run | Detected as 40P01 and retried/returned 409, no corruption (and confirm production path uses fixed order) | ☐ |
| T-9.3-07 (I) | Invariant | after every run | reconcile | Holds | ☐ |
| T-9.3-08 (V) | Losing request response | – | inspect | 409 with `error.code=INSUFFICIENT_QUANTITY` and fresh `available` | ☐ |

**7. Verification Checklist:** [ ] 20/20 clean runs for each scenario. [ ] Results table pasted into `docs/test-report.md`. [ ] Spec §19 item 13 ✅.

**8. Milestone Completion Criteria:** DONE only if zero oversell across all repetitions.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "test(allocation): concurrency and load proof against overselling (M9.3)"
git push origin phase-9-allocation
git tag -a m9.3 -m "M9.3 concurrency proof" && git push origin m9.3
```
*Do NOT commit:* k6 raw output files with large data.

**10. Rollback / Recovery:** **R-1**. If this milestone *finds* a bug: revert only the service fix commit, not the test.

---

## ✅ Phase 9 Completion Checkpoint
Template §0.4. **No-Go automatically** if any concurrency test fails or the invariant query reports a mismatch.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-9-allocation -m "merge: phase 9 allocation"
git push origin main
git tag -a phase-9-complete -m "Phase 9 complete" && git push origin phase-9-complete
```

---
# PHASE 10 – Pickup, OTP & Completion
**Branch:** `phase-10-pickup-otp`
> **Before starting: confirm D-2 (two-party completion) and D-16 (OTP parameters).**

## Milestone 10.1 – Pickup Proposal, Confirmation & Rescheduling

**Depends on:** Phase 9 complete.

**1. Milestone Name:** Pickup scheduling workflow (`proposed → scheduled`).

**2. Objective:** Spec §6.9: after a request is accepted, donor and NGO agree a date/time and handover details; one pickup per allocation.

**3. Tasks to Complete**
- [ ] `POST /api/pickups` (NGO or donor party of an **accepted/confirmed** allocation): `{allocation_id, scheduled_at (UTC ISO, ≥ now+1h, ≤ now+60d), location_details (≤500 chars), contact_note}` → state `proposed`, `proposed_by` set; notify the other party.
- [ ] `POST /api/pickups/{id}/confirm` — only the **other** party; `proposed→scheduled`.
- [ ] `PATCH /api/pickups/{id}` (either party) reschedule: allowed while state ∈ {proposed, scheduled, otp_issued}; resets to `proposed`, **invalidates any OTP** (clears `otp_hmac`, counters), notifies other party.
- [ ] `POST /api/pickups/{id}/cancel` (either party, pre-`collected`): state `cancelled` (allocation stays `confirmed` so a new pickup can be proposed — one *active* pickup per allocation; enforce with partial unique index in migration `006_pickups_active_unique`).
- [ ] `GET /api/pickups` (role-scoped, filters `state`, `from`, `to`), `GET /api/pickups/{id}`.
- [ ] Exact pickup location/address from the donation is revealed to the NGO **only once the pickup is `scheduled`** (D-9); before that only `location_public`.
- [ ] Store/compare times in UTC; API returns ISO-8601 with `Z`; UI localises (Asia/Kolkata).
- [ ] Audit `pickup.proposed/confirmed/rescheduled/cancelled`.

**4. Files / Modules Affected:** `PickupRepository/Service/Controller`, `db/migrations/006_*`, `PickupResource` (privacy-aware serializer), tests.

**5. Implementation Guidance:** The confirming party must differ from `proposed_by` (prevents one side "agreeing" with itself). Use `SELECT … FOR UPDATE` on the pickup row for every state change. Keep all pickup transitions in a `PickupStateMachine` class with a transition table taken from `docs/state-model.md`.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-10.1-01 (P) | NGO proposes pickup | confirmed allocation | POST | 201 `proposed`; donor notified | ☐ |
| T-10.1-02 (P) | Donor confirms | proposed by NGO | POST confirm | `scheduled`; NGO now sees exact address | ☐ |
| T-10.1-03 (N) | Proposer confirms own proposal | – | POST confirm | 403/409 | ☐ |
| T-10.1-04 (N) | Allocation not confirmed (pending/rejected) | – | POST | 409 | ☐ |
| T-10.1-05 (V) | Past date / >60 d / bad ISO | – | POST | 422 | ☐ |
| T-10.1-06 (S) | Third party | unrelated donor/NGO | POST/GET | 403/404 | ☐ |
| T-10.1-07 (N) | Second active pickup | one exists | POST | 409 (unique partial index) | ☐ |
| T-10.1-08 (P) | Reschedule resets | state `otp_issued` | PATCH new time | state `proposed`; OTP cleared | ☐ |
| T-10.1-09 (N) | Reschedule after collected | `collected` | PATCH | 409 | ☐ |
| T-10.1-10 (P) | Cancel then re-propose | scheduled | cancel then POST | New pickup created | ☐ |
| T-10.1-11 (S) | Exact address hidden before scheduled | `proposed` | NGO GET | No `address_text`/exact coords | ☐ |
| T-10.1-12 (E) | DST/timezone | `2026-10-12T04:30:00Z` | POST/GET | Stored & returned identical UTC | ☐ |
| T-10.1-13 (C) | Parallel confirm + reschedule | proposed | race | Consistent final state; no stale OTP | ☐ |
| T-10.1-14 (R) | M9 suites | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Migration 006 up/down proven. [ ] Spec §19 item 14 ✅.

**8. Milestone Completion Criteria:** DONE when pickups follow the state table exactly and address privacy holds.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-10-pickup-otp
git status
git add .
git commit -m "feat(pickup): pickup proposal, confirmation, reschedule and cancel (M10.1)"
git push origin phase-10-pickup-otp
git tag -a m10.1 -m "M10.1 pickup scheduling" && git push origin m10.1
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-3** `down --steps=1` then **R-1**.

---

## Milestone 10.2 – OTP Issue, Email Delivery & Verification

**Depends on:** M10.1, M4.1, M3.3 (rate limiter).

**1. Milestone Name:** Secure OTP lifecycle: issue → email → verify with attempt limits.

**2. Objective:** Spec §6.9/§13.6: CSPRNG OTP, HMAC-stored, short TTL, attempt cap, timing-safe compare, reissue rules, no secret leakage.

**3. Tasks to Complete**
- [ ] `OtpService::issue(pickupId, actor)`: pickup must be `scheduled` or `otp_issued` and `now ≥ scheduled_at − 24h`; generates `str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT)`; stores `otp_hmac = hash_hmac('sha256', pickupId.'|'.otp, OTP_HMAC_KEY)`, `otp_expires_at = now+30min`, `otp_attempts=0`, `otp_locked=false`, `otp_issue_count++`; max 3 issues/24 h (429 `OTP_REISSUE_LIMIT`); previous OTP invalid immediately; state → `otp_issued`.
- [ ] `POST /api/pickups/{id}/otp` — **NGO** only; the plaintext OTP is emailed to the **NGO user** and is **never** returned in the API response (D-16).
- [ ] Mail handling: enqueue the OTP email into `email_outbox`; after status `sent`, worker overwrites `body_text/body_html` with `[redacted]` (OTP not retained at rest) — unit test for this.
- [ ] `POST /api/pickups/{id}/verify-otp` — **donor** only: body `{otp}` must match `^\d{6}$`; checks not locked, not expired; `hash_equals(stored, computed)`; wrong ⇒ `otp_attempts++`, at 5 ⇒ `otp_locked=true` (423/`OTP_LOCKED`) until reissue; right ⇒ `state=collected`, allocation `collected`, `collected_at=now`, OTP fields cleared; audit.
- [ ] Rate-limit middleware on verify (per pickup + per IP) *in addition* to the attempt cap.
- [ ] Audit: `otp.issued`, `otp.verify.failed`, `otp.locked`, `otp.verify.success`, `otp.expired` — **no OTP value in any log, audit, URL, or notification**; notification text says only "A pickup code was emailed to you."
- [ ] Redactor test: grep logs after test run for a known OTP value → 0 hits.

**4. Files / Modules Affected:** `OtpService`, `PickupController` additions, `OutboxWorker` (redaction), `RateLimitMiddleware` usage, tests.

**5. Implementation Guidance:** HMAC key is `OTP_HMAC_KEY` from env (never committed; separate from `APP_KEY`). 10⁶ combinations × 5 attempts × 30 min TTL × rate limit keeps brute-force probability ≈ 5×10⁻⁶ per issued OTP (state this reasoning in the security doc rather than claiming it is "unbreakable"). Use the injected `Clock` so expiry tests don't sleep.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-10.2-01 (P) | NGO issues OTP | scheduled pickup within 24 h | POST otp | 200 (no OTP in body); Mailpit has 6-digit code; state `otp_issued` | ☐ |
| T-10.2-02 (S) | DB stores no plaintext | – | `SELECT otp_hmac` | 64-hex string; plaintext absent everywhere | ☐ |
| T-10.2-03 (S) | Donor tries to issue | – | POST otp as donor | 403 | ☐ |
| T-10.2-04 (N) | Issue too early | scheduled in 3 days | POST | 409/422 | ☐ |
| T-10.2-05 (P) | Valid OTP | issued | donor POST verify | 200; pickup+allocation `collected`; OTP cleared | ☐ |
| T-10.2-06 (N) | Wrong OTP | – | verify wrong | 422 `OTP_INVALID`; attempts=1 | ☐ |
| T-10.2-07 (S) | 5 wrong attempts | – | 5× wrong then correct | 5th ⇒ `OTP_LOCKED`; correct OTP now still rejected | ☐ |
| T-10.2-08 (P) | Reissue unlocks | locked | NGO reissues; verify new | Success; old OTP rejected | ☐ |
| T-10.2-09 (E) | Expired OTP | fake clock +31 min | verify correct | 422 `OTP_EXPIRED` | ☐ |
| T-10.2-10 (E) | Reissue limit | 3 issues | 4th | 429 `OTP_REISSUE_LIMIT` | ☐ |
| T-10.2-11 (V) | Format | `"12345"`, `"abcdef"`, `"1234567"`, `null` | verify | 422 each (no attempt counted for malformed? decide & document) | ☐ |
| T-10.2-12 (S) | Wrong party verifies | NGO calls verify | – | 403 | ☐ |
| T-10.2-13 (S) | Third-party donor | other donor | verify | 404 | ☐ |
| T-10.2-14 (S) | Replay after success | collected | verify same OTP | 409 | ☐ |
| T-10.2-15 (S) | No leakage | after suite | grep app log, audit metadata, outbox after send, notifications | Known OTP appears nowhere | ☐ |
| T-10.2-16 (S) | Timing-safe compare | code review | – | Uses `hash_equals` only | ☐ |
| T-10.2-17 (C) | Parallel verify with correct OTP ×5 | – | race | Exactly one success; no double state change | ☐ |
| T-10.2-18 (F) | SMTP down at issue | stop Mailpit | POST otp | Issued, outbox pending, API returns 200 with `email_status:"queued"`; resend works later | ☐ |
| T-10.2-19 (S) | Rate limit | – | 30 verify calls/min from one IP | 429 | ☐ |

**7. Verification Checklist:** [ ] Spec §19 items 15–16 ✅. [ ] `OTP_HMAC_KEY` present only in `.env`. [ ] Manual end-to-end with Mailpit.

**8. Milestone Completion Criteria:** DONE when every OTP security property in spec §13.6 has a passing test.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(otp): secure otp issue, email delivery and verification with lockout (M10.2)"
git push origin phase-10-pickup-otp
git tag -a m10.2 -m "M10.2 otp" && git push origin m10.2
```
*Do NOT commit:* `OTP_HMAC_KEY`, Mailpit exports containing codes, log files.

**10. Rollback / Recovery:** **R-1**. If an OTP value was ever logged: purge logs, rotate `OTP_HMAC_KEY`, and invalidate outstanding OTPs (`UPDATE pickups SET otp_hmac=NULL … ` in dev; in prod via reviewed script).

---

## Milestone 10.3 – Receipt Confirmation & Completion Accounting

**Depends on:** M10.2.

**1. Milestone Name:** `confirm-receipt` → `completed`, requirement/donation progress.

**2. Objective:** Spec §6.9/Appendix A: finalise handover (D-2); update requirement `quantity_fulfilled`, requirement and donation statuses; notify both; audit.

**3. Tasks to Complete**
- [ ] `POST /api/pickups/{id}/confirm-receipt` (NGO only; state `collected` → `completed`).
- [ ] In one transaction (locks: donation → requirement → pickup): allocation `completed`; `ngo_requirements.quantity_fulfilled += q` (status `partially_fulfilled`/`fulfilled`); donation → `completed` when `available_quantity=0` and no open allocations remain; notify both (`handover.completed`).
- [ ] An OTP success alone must **not** mark `completed` (Appendix A warning).
- [ ] Guard: NGO may not confirm if state ≠ `collected` (409).
- [ ] Optional dispute stub: `completed` is terminal; document "dispute handling = out of scope".
- [ ] Status history: add `GET /api/donations/{id}/history` (owner) listing audit events — satisfies spec §6.4 "status history".
- [ ] Extend `Invariants`: `fulfilled ≤ allocated ≤ needed`; `available + open + completed = total`.

**4. Files / Modules Affected:** `PickupService`, `AllocationService::complete()`, `DonationHistoryController`, tests.

**5. Implementation Guidance:** Treat `complete()` as the mirror of `createRequest()`: lock, validate state, update counters with guarded SQL, derive statuses with the pure functions, insert audit/notifications in-transaction.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-10.3-01 (P) | Confirm receipt | `collected` | NGO POST | pickup & allocation `completed`; requirement fulfilled +q | ☐ |
| T-10.3-02 (N) | Confirm before OTP | `scheduled` | NGO POST | 409 | ☐ |
| T-10.3-03 (S) | Donor confirms receipt | – | donor POST | 403 | ☐ |
| T-10.3-04 (S) | Other NGO confirms | – | POST | 404 | ☐ |
| T-10.3-05 (N) | Double confirm | completed | POST | 409; counters unchanged | ☐ |
| T-10.3-06 (P) | Requirement becomes fulfilled | needed 12, two completions 8+4 | – | status `fulfilled`; no further matches | ☐ |
| T-10.3-07 (P) | Donation completed | all stock completed | – | donation `completed` | ☐ |
| T-10.3-08 (E) | Donation with remaining stock | available 5 left | after completion | stays `partially_allocated`/`active` | ☐ |
| T-10.3-09 (P) | Notifications + audit | – | after confirm | both parties notified; audit `handover.completed` | ☐ |
| T-10.3-10 (P) | History endpoint | – | owner GET history | Ordered events incl. request, pickup, OTP verified (no OTP), completion | ☐ |
| T-10.3-11 (S) | History access | other donor | GET | 404 | ☐ |
| T-10.3-12 (I) | Full backend demo scenario (spec App. B) | seed script | run script/test | 20 items → NGO req 12 → request 8 → second request 4 → pickups → OTP → completion; dashboards numbers consistent | ☐ |
| T-10.3-13 (I) | Invariants | after all | run | Hold | ☐ |
| T-10.3-14 (R) | Phase 9 + 10.1/10.2 suites | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Scripted demo (`tests/Integration/DemoScenarioTest.php`) passes. [ ] Spec §19 items 14–17 ✅.

**8. Milestone Completion Criteria:** DONE when the full spec Appendix B scenario passes at API level.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(pickup): receipt confirmation, completion accounting and history (M10.3)"
git push origin phase-10-pickup-otp
git tag -a m10.3 -m "M10.3 completion" && git push origin m10.3
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 10 Completion Checkpoint
Template §0.4. Extra: OTP leakage grep = 0 hits; Appendix B scenario ✅; this completes **all core backend workflows** (spec §17 phase 6).
```bash
git checkout main && git pull origin main
git merge --no-ff phase-10-pickup-otp -m "merge: phase 10 pickup and otp"
git push origin main
git tag -a phase-10-complete -m "Phase 10 complete" && git push origin phase-10-complete
```

---

# PHASE 11 – Admin Operations (Backend)
**Branch:** `phase-11-admin`

## Milestone 11.1 – User Management & Suspension

**Depends on:** Phase 10 complete.

**1. Milestone Name:** Admin user list/detail and suspend/reinstate.

**2. Objective:** Spec §6.11: review and suspend accounts under a documented policy, with immediate session effect.

**3. Tasks to Complete**
- [ ] `GET /api/admin/users?role=&status=&q=&page=` (no password hashes; email partially visible is fine for admin).
- [ ] `PATCH /api/admin/users/{id}/status` `{status: active|suspended, reason}`; reason required for suspend; admin cannot suspend self or the last active admin (409).
- [ ] Suspend effects: all future requests return 401/403 (middleware reads DB); active listings of a suspended donor hidden from matching; open allocations are **not** silently destroyed — they are listed for admin follow-up (documented policy) or cancelled via the M9.2 service if `cascade=true`.
- [ ] Notify user by email; audit `user.suspended/reinstated`.
- [ ] `docs/admin-policy.md`: when to suspend, effects, appeal path (text).

**4. Files / Modules Affected:** `AdminUserController`, `UserAdminService`, `docs/admin-policy.md`, tests.

**5. Implementation Guidance:** Never allow role editing here (kept out of scope to avoid privilege-escalation surface; changing roles requires DB access by the owner).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-11.1-01 (P) | List & filter | 30 users | `?role=donor&q=ar` | Filtered page | ☐ |
| T-11.1-02 (P) | Suspend user | active donor logged in elsewhere | PATCH suspended+reason | Donor's next request → 401; audit + email | ☐ |
| T-11.1-03 (V) | Suspend without reason | – | PATCH | 422 | ☐ |
| T-11.1-04 (N) | Admin suspends self | – | PATCH own id | 409 | ☐ |
| T-11.1-05 (N) | Suspend last admin | one admin | PATCH | 409 | ☐ |
| T-11.1-06 (S) | Non-admin | donor | GET/PATCH | 403 | ☐ |
| T-11.1-07 (S) | No hashes in payload | – | inspect list | No `password_hash` | ☐ |
| T-11.1-08 (P) | Reinstate | suspended | PATCH active | Can log in | ☐ |
| T-11.1-09 (I) | Suspended donor's listings excluded from matching | active listing | run M8.1 query | Excluded | ☐ |
| T-11.1-10 (C) | Suspend during in-flight request | parallel | – | Consistent; no 500 | ☐ |

**7. Verification Checklist:** [ ] Admin policy doc reviewed. [ ] Role matrix test updated.

**8. Milestone Completion Criteria:** DONE when suspension takes effect on the very next request and is audited.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-11-admin
git status
git add .
git commit -m "feat(admin): user management with suspension policy (M11.1)"
git push origin phase-11-admin
git tag -a m11.1 -m "M11.1 user admin" && git push origin m11.1
```
*Do NOT commit:* real user exports.

**10. Rollback / Recovery:** **R-1**.

---

## Milestone 11.2 – Reports, Analytics & Audit Log Viewer

**Depends on:** M11.1.

**1. Milestone Name:** Admin summary metrics, trend data, audit log search.

**2. Objective:** Spec §6.11 reports (counts, donations over time, category distribution, request outcomes, completion rate) and audit review.

**3. Tasks to Complete**
- [ ] `GET /api/admin/reports/summary?from=&to=` → totals: users by role, NGOs by status, donations by status, requests by status, completed handovers, completion rate = `completed / accepted` (define precisely in docs).
- [ ] `GET /api/admin/reports/trends?metric=donations|completions&interval=week&from&to` (SQL `date_trunc` in UTC).
- [ ] `GET /api/admin/reports/categories` distribution.
- [ ] `GET /api/admin/audit-logs?actor=&action=&target_type=&from=&to=&page=` (read-only; metadata displayed already-redacted).
- [ ] Role-scoped summary endpoints for dashboards: `GET /api/dashboard` returning donor cards (total donations, requested/accepted, scheduled pickups, completed) or NGO cards (active requirements, matched donations count, pending requests, scheduled pickups, completed) — spec §6.3/§6.5.
- [ ] Optional CSV export `?format=csv` — only if time permits; CSV-injection-safe (prefix `=,+,-,@` with `'`).

**4. Files / Modules Affected:** `ReportService/Repository`, `AdminReportController`, `DashboardController`, tests.

**5. Implementation Guidance:** Aggregate queries run read-only and bounded by date range (max 366 days). Add indexes only if `EXPLAIN` shows need. Numbers must equal hand-computed values on a fixture set — dashboards that disagree with raw tables destroy trust.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-11.2-01 (P) | Summary correct | fixture with known counts | GET | Matches hand-computed numbers | ☐ |
| T-11.2-02 (E) | Empty DB / empty range | – | GET | Zeros, no division-by-zero (completion rate null/0) | ☐ |
| T-11.2-03 (V) | Bad dates / range >366 d | – | GET | 422 | ☐ |
| T-11.2-04 (S) | Non-admin | donor/NGO | GET admin reports | 403 | ☐ |
| T-11.2-05 (P) | Audit filter | many rows | `?action=ngo.verification.decision` | Only those | ☐ |
| T-11.2-06 (S) | Audit has no secrets | after OTP/login tests | scan JSON | No password/OTP/token | ☐ |
| T-11.2-07 (P) | Donor dashboard | donor with data | GET dashboard | Counts equal list endpoints | ☐ |
| T-11.2-08 (P) | NGO dashboard | NGO with data | GET | Counts equal list endpoints | ☐ |
| T-11.2-09 (S) | Dashboard isolation | two donors | each GET | Own data only | ☐ |
| T-11.2-10 (E) | Timezone boundaries | rows at 23:30 UTC | trends | Bucketed by UTC consistently (documented) | ☐ |
| T-11.2-11 (S) | CSV injection (if built) | title `=cmd\|…` | export | Cell prefixed with `'` | ☐ |

**7. Verification Checklist:** [ ] Spot-check three numbers with raw SQL. [ ] `docs/api-contract.md` updated with final shapes.

**8. Milestone Completion Criteria:** DONE when every dashboard number reconciles with SQL ground truth.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(admin): reports, dashboards and audit log viewer endpoints (M11.2)"
git push origin phase-11-admin
git tag -a m11.2 -m "M11.2 reports" && git push origin m11.2
```
*Do NOT commit:* exported audit/CSV data.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 11 Completion Checkpoint
Template §0.4. Extra: **Backend feature-complete gate** — walk spec §6 and tick every backend-capable item; list gaps as known issues. Freeze `docs/api-contract.md` (changes now need a version note).
```bash
git checkout main && git pull origin main
git merge --no-ff phase-11-admin -m "merge: phase 11 admin"
git push origin main
git tag -a phase-11-complete -m "Phase 11 complete (backend complete)" && git push origin phase-11-complete
```

---
# PHASE 12 – Frontend Foundation, Public Site & Authentication
**Branch:** `phase-12-frontend-foundation`
> The API contract is frozen (Phase 11). The UI never decides authorization — it only reflects what the server allows (spec §5). **Confirm D-3 (Tailwind).**

## Milestone 12.1 – API Client, Auth Context, Route Guards & UI Kit

**Depends on:** Phase 11 complete.

**1. Milestone Name:** Frontend infrastructure: API client with CSRF, auth state, guarded routes, shared components.

**2. Objective:** One tested layer every screen uses: consistent errors, CSRF, loading/empty/error states, role-aware navigation.

**3. Tasks to Complete**
- [ ] `src/api/client.js` (native `fetch`, `credentials:'include'`): fetches CSRF once (`GET /api/auth/csrf`), sends `X-CSRF-Token` on non-GET, refetches token + retries **once** on `CSRF_FAILED`; normalises the error envelope into `ApiError{status, code, message, fields}`; global handler: 401 ⇒ clear auth + redirect to `/login?next=`.
- [ ] `AuthContext` (`user`, `loading`, `login`, `logout`, `refresh`) loading `/api/auth/me` on boot.
- [ ] Router: `/`, `/login`, `/signup`, `/donor/*`, `/ngo/*`, `/admin/*`, `*` (404 page); `<RoleRoute roles={[…]}>` plus `<VerifiedNgoRoute>` that shows the Pending/Rejected status page.
- [ ] Shared components: `AppShell` (side nav per role, responsive drawer), `PageHeader`, `StatusBadge` (**icon + text, never colour alone**), `LoadingState`, `EmptyState`, `ErrorState` (shows `request_id`), `FormField` (label, hint, error with `aria-describedby`), `Toast`, `ConfirmDialog` (focus trap), `Pagination`, `useSubmitOnce()` hook (disables duplicate submit; generates `Idempotency-Key` where needed).
- [ ] Tailwind config with a green/neutral token palette; dark text on light backgrounds passing contrast.
- [ ] **Never** render user text via `dangerouslySetInnerHTML`; ESLint rule `react/no-danger: error`.

**4. Files / Modules Affected:** `frontend/src/{api,auth,components,layouts,routes}/**`, `eslint config`, tests.

**5. Implementation Guidance:** Keep all server-state fetching in small hooks (`useApi(path)`) — no global state library is needed at this scale (avoid adding Redux/React-Query without a clear need). Route guards are *UX only*; every API call is still authorised server-side.

**6. Test Cases** (Vitest + React Testing Library; API mocked with `fetch` stubs)

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-12.1-01 (P) | CSRF header attached | mock | POST via client | Header present on POST, absent on GET | ☐ |
| T-12.1-02 (F) | CSRF refresh retry | first POST returns `CSRF_FAILED` | POST | Token refetched, retried once, succeeds | ☐ |
| T-12.1-03 (P) | Error normalisation | 422 with fields | call | `ApiError.fields` populated | ☐ |
| T-12.1-04 (P) | 401 redirect | mock 401 | any call | Auth cleared; navigates to `/login?next=…` | ☐ |
| T-12.1-05 (S) | Role guard | user=donor | render `/admin` | Redirect / "Not permitted" page (no admin UI flashes) | ☐ |
| T-12.1-06 (P) | Pending NGO view | user=ngo pending | open `/ngo/requirements/new` | Pending-verification page | ☐ |
| T-12.1-07 (E) | Auth loading state | `/me` slow | render | Spinner, no premature redirect | ☐ |
| T-12.1-08 (P) | StatusBadge a11y | – | render each status | Text label + icon present; passes jest-axe | ☐ |
| T-12.1-09 (E) | Double-submit prevention | click submit twice | – | One request | ☐ |
| T-12.1-10 (S) | XSS escaping | name=`<img src=x onerror=alert(1)>` | render in header | Appears as text; no element created | ☐ |
| T-12.1-11 (P) | Build & lint | – | `npm run lint && npm run build` | 0 errors | ☐ |

**7. Verification Checklist:** [ ] Manual: login → refresh browser → still logged in; logout → protected route redirects. [ ] Keyboard-only navigation of shell works.

**8. Milestone Completion Criteria:** DONE when all shared pieces are unit-tested and a stub page for each role renders under correct guard.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-12-frontend-foundation
git status
git add .
git commit -m "feat(web): api client, auth context, route guards and ui kit (M12.1)"
git push origin phase-12-frontend-foundation
git tag -a m12.1 -m "M12.1 frontend foundation" && git push origin m12.1
```
*Do NOT commit:* `node_modules`, `dist`, any `VITE_` variable holding a secret.

**10. Rollback / Recovery:** **R-1** then `npm ci`.

---

## Milestone 12.2 – Public Pages, Sign-up, Login & Profile Screens

**Depends on:** M12.1.

**1. Milestone Name:** Landing/About/How-it-works/Impact/Contact, Login, Sign-up (donor & NGO), Profile/Settings.

**2. Objective:** Spec screens 1–3, 19 and §6.1: responsive public site and working auth UI.

**3. Tasks to Complete**
- [ ] Public pages with nav and CTAs; **Impact** page shows only real aggregates (or "coming soon") — no invented statistics (Appendix C).
- [ ] Login form: field errors, generic failure message, `next` redirect, role-based landing (`/donor`, `/ngo`, `/admin`).
- [ ] Sign-up: role toggle (Donor | NGO), password + confirm + strength hint, terms checkbox; NGO section with organisation fields, **map picker for location** (Leaflet; manual lat/lng inputs as fallback — D-5), document upload (type/size pre-check mirrors server limits, server still authoritative).
- [ ] Profile/settings: edit name/phone/location; change password; NGO status banner with admin note and "Resubmit" action.
- [ ] Map component `LocationPicker` (react-leaflet, OSM tiles with attribution, click-to-set marker, keyboard-accessible lat/lng inputs, graceful message if tiles fail).
- [ ] Contact page: static info only (no form posting to nowhere); if a form exists it must be wired and tested.

**4. Files / Modules Affected:** `frontend/src/pages/{public,auth,profile}/**`, `components/LocationPicker.jsx`.

**5. Implementation Guidance:** `npm i leaflet react-leaflet@4`; import Leaflet CSS once; set default marker icon paths explicitly (Vite bundling issue). Respect OSM tile usage policy (attribution, no bulk prefetch). Client validation is for convenience only.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-12.2-01 (P) | Donor sign-up E2E (manual+RTL) | API up | fill & submit | Redirect to donor dashboard (or login) | ☐ |
| T-12.2-02 (V) | Field errors shown | server 422 | submit bad data | Messages beside each field; inputs preserved except passwords | ☐ |
| T-12.2-03 (P) | NGO sign-up with PDF | API up | submit | "Pending verification" page | ☐ |
| T-12.2-04 (V) | Client file pre-check | 20 MB file | select | Inline error before upload | ☐ |
| T-12.2-05 (N) | Login failure | wrong pw | submit | Generic message; no field hint which part wrong | ☐ |
| T-12.2-06 (P) | Redirect by role | 3 roles | login each | Correct landing | ☐ |
| T-12.2-07 (F) | Map tiles blocked | offline tiles | open picker | Message + manual lat/lng still works | ☐ |
| T-12.2-08 (P) | Map click sets coordinates | – | click map | Lat/lng inputs update; marker moves | ☐ |
| T-12.2-09 (A11y) | Forms accessible | – | tab through; run axe | Labels, focus order, no violations | ☐ |
| T-12.2-10 (P) | Responsive | – | 360 px / 768 px / 1280 px | No horizontal scroll; nav collapses | ☐ |
| T-12.2-11 (P) | Profile update + password change | logged in | submit | Success toast; relogin with new pw works | ☐ |
| T-12.2-12 (S) | Passwords not persisted | – | inspect localStorage/sessionStorage | Nothing sensitive stored | ☐ |
| T-12.2-13 (F) | API down on submit | stop API | submit | Friendly error with retry; no crash | ☐ |
| T-12.2-14 (R) | M12.1 suite | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Lighthouse accessibility ≥ 90 on landing + login (record actual score). [ ] Spec §19 items 1–2, 7 verified through the UI.

**8. Milestone Completion Criteria:** DONE when a visitor can sign up as donor/NGO, log in, and edit the profile via the UI.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(web): public pages, signup, login, profile and location picker (M12.2)"
git push origin phase-12-frontend-foundation
git tag -a m12.2 -m "M12.2 public+auth ui" && git push origin m12.2
```
*Do NOT commit:* uploaded sample documents with personal data.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 12 Completion Checkpoint
Template §0.4.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-12-frontend-foundation -m "merge: phase 12 frontend foundation"
git push origin main
git tag -a phase-12-complete -m "Phase 12 complete" && git push origin phase-12-complete
```

---

# PHASE 13 – Frontend: Donor
**Branch:** `phase-13-donor-ui`

## Milestone 13.1 – Donor Dashboard, Post Donation & My Donations

**Depends on:** Phase 12 complete.

**1. Milestone Name:** Donor screens 4, 5, 6.

**2. Objective:** Donors can create and manage listings with images and map location, and see summary cards from `/api/dashboard`.

**3. Tasks to Complete**
- [ ] Dashboard: summary cards (total donations, requested/accepted, scheduled pickups, completed), recent donations list with thumbnails, quick links.
- [ ] Post Donation form: all fields with live counters, quantity stepper (min 1), condition select, `LocationPicker`, multi-image upload with previews, per-file validation, upload progress, "Save as draft" vs "Publish".
- [ ] Two-step submit: `POST /api/donations` → `POST /…/images`; if image step fails, keep the donation and show "Retry uploading images" (no lost form data).
- [ ] My Donations: table/cards with thumbnail (`<img src="/api/media/donation-images/ID">` works with the session cookie), remaining qty, status badges, filters (status, category), pagination, Edit / Close actions with `ConfirmDialog`; edit form respects server rules (disabled fields + explanatory text when allocations exist).
- [ ] Donation detail page with status history timeline (`/history`).

**4. Files / Modules Affected:** `frontend/src/pages/donor/{Dashboard,PostDonation,MyDonations,DonationDetail}.jsx`, `hooks/useDonations.js`, tests.

**5. Implementation Guidance:** Show server `409`/`422` messages verbatim in friendly wrappers. Do not optimistically update quantities — refetch after any mutation so UI always mirrors backend state (spec §6.3 "status labels consistent with backend state").

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-13.1-01 (P) | Publish donation with 2 images | donor | fill & publish | Appears in My Donations with thumbnail | ☐ |
| T-13.1-02 (V) | Qty −1 / 0 / empty | – | submit | Client error; if bypassed, server 422 shown | ☐ |
| T-13.1-03 (F) | Image step fails | mock 415 | submit | Donation exists; retry button; message names the file | ☐ |
| T-13.1-04 (E) | 6 images | – | select 6 | Blocked at 5 with message | ☐ |
| T-13.1-05 (P) | Filter/pagination | 25 donations | filter status + page 2 | Matches API | ☐ |
| T-13.1-06 (N) | Close with open allocation | allocation exists | click Close | Error explaining why (409) | ☐ |
| T-13.1-07 (P) | Edit locked fields | allocation exists | open edit | Category disabled with explanation | ☐ |
| T-13.1-08 (P) | Dashboard cards | data | open | Numbers equal list counts | ☐ |
| T-13.1-09 (E) | Empty state | new donor | open dashboard/list | EmptyState with "Post your first donation" CTA | ☐ |
| T-13.1-10 (S) | XSS in titles | title with HTML | view lists | Rendered as text | ☐ |
| T-13.1-11 (A11y) | Keyboard + screen reader | – | tab through form; axe | No violations; errors announced (`aria-live`) | ☐ |
| T-13.1-12 (P) | Mobile layout | 360 px | use all screens | Usable, no overflow | ☐ |
| T-13.1-13 (S) | Other donor's URL | `/donor/donations/ID-of-other` | open | "Not found" page | ☐ |

**7. Verification Checklist:** [ ] Manual run of spec Appendix B step 1 (20 winter items). [ ] Network tab shows no exact-location leakage to non-owners (N/A here, owner view only).

**8. Milestone Completion Criteria:** DONE when a donor can run the entire listing lifecycle through the UI.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-13-donor-ui
git status
git add .
git commit -m "feat(web): donor dashboard, post donation and my donations (M13.1)"
git push origin phase-13-donor-ui
git tag -a m13.1 -m "M13.1 donor listings ui" && git push origin m13.1
```
*Do NOT commit:* test images.

**10. Rollback / Recovery:** **R-1**.

---

## Milestone 13.2 – Requests Received & Matched NGOs (Donor)

**Depends on:** M13.1.

**1. Milestone Name:** Donor screens 7 and 8.

**2. Objective:** Donors see incoming requests and accept/reject; see ranked NGO requirements that fit a listing with a map.

**3. Tasks to Complete**
- [ ] Requests Received: list (pending first) with NGO name, requested qty, requirement urgency, expiry countdown; Accept / Reject (reject asks for optional reason) via `ConfirmDialog`; handle `409 INVALID_TRANSITION` by refreshing and explaining.
- [ ] Matched NGOs view per donation: ranked cards with `score_band`, reasons list, distance; `MatchMap` (Leaflet) with list/map toggle; markers use only public coordinates from API.
- [ ] Link accepted requests to "Schedule pickup" (screen in Phase 15).

**4. Files / Modules Affected:** `pages/donor/{RequestsReceived,MatchedNgos}.jsx`, `components/{MatchCard,MatchMap}.jsx`.

**5. Implementation Guidance:** Score shown as a **band + reasons**, with a footnote "ranking is guidance, not a guarantee" (spec §8.5). Debounce nothing that mutates data.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-13.2-01 (P) | Accept request | pending request | click Accept→confirm | Status → accepted; list refreshes; qty unchanged | ☐ |
| T-13.2-02 (P) | Reject request | pending | Reject | Removed from pending; donation remaining qty increases in My Donations | ☐ |
| T-13.2-03 (F) | Request already cancelled | NGO cancelled meanwhile | click Accept | 409 handled: toast + refreshed list | ☐ |
| T-13.2-04 (P) | Match list/map toggle | matches exist | toggle | Same items in both views; marker popup shows allowed fields only | ☐ |
| T-13.2-05 (E) | No matches | none | open | EmptyState suggesting edit category/location | ☐ |
| T-13.2-06 (S) | No NGO private data | – | inspect network JSON & DOM | No documents, no NGO phone/address | ☐ |
| T-13.2-07 (A11y) | Map alternative | keyboard user | tab | List view fully usable without map | ☐ |
| T-13.2-08 (P) | Expiry display | request 70 h old | open | "Expires in ~2 h" | ☐ |
| T-13.2-09 (R) | M13.1 suite | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Two-browser manual test (donor + NGO) for accept path (full NGO side arrives in Phase 14).

**8. Milestone Completion Criteria:** DONE when donors can process requests and view matches without UI/back-end state disagreement.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(web): donor requests received and matched ngos with map (M13.2)"
git push origin phase-13-donor-ui
git tag -a m13.2 -m "M13.2 donor requests ui" && git push origin m13.2
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 13 Completion Checkpoint
Template §0.4.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-13-donor-ui -m "merge: phase 13 donor ui"
git push origin main
git tag -a phase-13-complete -m "Phase 13 complete" && git push origin phase-13-complete
```

---

# PHASE 14 – Frontend: NGO & Matching Map
**Branch:** `phase-14-ngo-ui`

## Milestone 14.1 – NGO Dashboard & Requirements Screens

**Depends on:** Phase 13 complete.

**1. Milestone Name:** NGO screens 9 and 10 (+ My Requirements).

**2. Objective:** Verified NGOs create/manage requirements and see progress; unverified NGOs see a clear status page.

**3. Tasks to Complete**
- [ ] NGO dashboard cards (active requirements, matched donations, pending requests, scheduled pickups, completed) and verification-status banner (Pending / Verified / Rejected / Suspended / Correction requested + admin note).
- [ ] Post/Edit Requirement form (category, quantity, urgency, min condition, radius slider 1–500 km, `LocationPicker` with radius circle, needed-by date).
- [ ] My Requirements list: progress bar `fulfilled / allocated / needed` with text values (not colour only), status badges, Close action, "View matches" link.
- [ ] Disabled-with-reason behaviour for editing quantity below allocated.

**4. Files / Modules Affected:** `pages/ngo/{Dashboard,PostRequirement,MyRequirements}.jsx`.

**5. Implementation Guidance:** The radius circle visualises the server's `ST_DWithin` input; the server remains authoritative.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-14.1-01 (P) | Create requirement | verified NGO | submit 12 winter clothes, high | Listed; outstanding 12 | ☐ |
| T-14.1-02 (S) | Pending NGO | pending | open create page | Blocked page with explanation | ☐ |
| T-14.1-03 (V) | Invalid inputs | – | qty 0, radius 900, past date | Field errors | ☐ |
| T-14.1-04 (P) | Progress display | allocated 8/12 | open list | "8 of 12 allocated, 0 fulfilled" text + bar | ☐ |
| T-14.1-05 (P) | Close requirement | active | Close→confirm | Status closed | ☐ |
| T-14.1-06 (N) | Edit below allocated | allocated 8 | set needed 5 | Blocked with message | ☐ |
| T-14.1-07 (P) | Status banner variants | each status | render | Correct copy+icon | ☐ |
| T-14.1-08 (A11y) | Radius slider accessible | – | keyboard | Value announced, arrow keys work | ☐ |
| T-14.1-09 (R) | Phase 12/13 suites | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Appendix B step 3 via UI.

**8. Milestone Completion Criteria:** DONE when verified NGOs manage requirements end-to-end through UI.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-14-ngo-ui
git status
git add .
git commit -m "feat(web): ngo dashboard and requirement management screens (M14.1)"
git push origin phase-14-ngo-ui
git tag -a m14.1 -m "M14.1 ngo requirements ui" && git push origin m14.1
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-1**.

---

## Milestone 14.2 – Matched Donations (List + Map) & Request Flow

**Depends on:** M14.1.

**1. Milestone Name:** NGO screen 11: ranked matches, map, and request-quantity flow with conflict handling.

**2. Objective:** Spec §6.7/§6.8 UI: NGO reviews ranked donations (images, qty, condition, distance, reasons), requests a quantity, and sees the outcome — including 409 when stock changes.

**3. Tasks to Complete**
- [ ] Matched Donations page per requirement: ranked cards (score band, reasons, thumbnail, available qty, condition, public distance), list/map toggle via `MatchMap`, radius circle, pagination.
- [ ] "Request" drawer: quantity input bounded by `min(available, outstanding)`, shows remaining need, sends `Idempotency-Key` (generated once per drawer open), disabled while pending.
- [ ] `409 INSUFFICIENT_QUANTITY`: show "Someone else reserved part of this stock — only N left", refresh card, keep drawer open with new max.
- [ ] "My requests" list: statuses (pending/accepted/rejected/cancelled/expired), Cancel action, expiry hint, link to pickup scheduling.
- [ ] Image display via media endpoint; image failure shows placeholder.

**4. Files / Modules Affected:** `pages/ngo/{MatchedDonations,MyRequests}.jsx`, `components/RequestDrawer.jsx`.

**5. Implementation Guidance:** Never compute availability client-side as truth. Show `updated_at` freshness; refetch on window focus for this page only.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-14.2-01 (P) | Request 8 of 20 | match exists | open drawer, 8, submit | Success toast; card shows available 12; request listed pending | ☐ |
| T-14.2-02 (V) | Qty > max | – | enter 25 | Input capped / error before submit | ☐ |
| T-14.2-03 (F) | Conflict | another NGO takes stock first | submit | 409 message with new max; no duplicate | ☐ |
| T-14.2-04 (E) | Double-click Submit | – | click ×2 quickly | One request; same Idempotency-Key | ☐ |
| T-14.2-05 (P) | Second NGO partial allocation | first NGO took 8 | second requests 4 | Success; remaining updates | ☐ |
| T-14.2-06 (P) | Cancel pending request | pending | Cancel | Status cancelled; stock back on card after refresh | ☐ |
| T-14.2-07 (P) | Map markers | matches | toggle map | Markers = list items; popup shows title/qty/distance only | ☐ |
| T-14.2-08 (S) | Exact location not exposed | before scheduled pickup | inspect DOM/network | Only public coords | ☐ |
| T-14.2-09 (E) | No matches | none | open | EmptyState with tips (widen radius/category) | ☐ |
| T-14.2-10 (F) | Image 404 | missing file | view | Placeholder, page works | ☐ |
| T-14.2-11 (P) | Appendix B steps 4–6 | seed | run through UI with 2 NGOs | Explanation + remaining stock correct | ☐ |
| T-14.2-12 (R) | Previous suites | – | `verify.sh` | ✅ | ☐ |

**7. Verification Checklist:** [ ] Two real browsers racing the last units (manual check complementing M9.3). [ ] Spec §19 items 10–12 ✅ via UI.

**8. Milestone Completion Criteria:** DONE when the NGO can discover, request, cancel and recover from conflicts using only the UI.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(web): matched donations list/map and request flow with conflict handling (M14.2)"
git push origin phase-14-ngo-ui
git tag -a m14.2 -m "M14.2 ngo matching ui" && git push origin m14.2
```
*Do NOT commit:* nothing special.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 14 Completion Checkpoint
Template §0.4.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-14-ngo-ui -m "merge: phase 14 ngo ui"
git push origin main
git tag -a phase-14-complete -m "Phase 14 complete" && git push origin phase-14-complete
```

---

# PHASE 15 – Frontend: Pickup, OTP & Notifications
**Branch:** `phase-15-pickup-ui`

## Milestone 15.1 – Schedule Pickup, OTP Screens & Notification Centre

**Depends on:** Phase 14 complete.

**1. Milestone Name:** Screens 12, 13 and the notification bell/centre.

**2. Objective:** Both parties complete the handover through the UI exactly per the backend state machine.

**3. Tasks to Complete**
- [ ] Schedule Pickup page: propose date/time (local time zone → UTC), instructions; shows other party's proposal with Confirm / Counter-propose; reveals exact address only when pickup `scheduled`.
- [ ] Pickup detail with state stepper (`proposed → scheduled → otp_issued → collected → completed`) and text status.
- [ ] NGO: "Send pickup code" button (enabled within 24 h window; shows reissue count & cooldown); message "Code emailed to you — share it with the donor at handover". **Never display or log the OTP in the UI.**
- [ ] Donor: OTP entry (6 numeric boxes, `inputmode="numeric"`, `autocomplete="one-time-code"`), attempts-remaining message from server, locked/expired states with guidance, success state.
- [ ] NGO: "Confirm receipt" after `collected`.
- [ ] Notification bell (polling every 60 s only while tab visible), dropdown of recent items, `/notifications` page with mark-read / read-all and links to related screens.
- [ ] Scheduled Pickups lists on both dashboards.

**4. Files / Modules Affected:** `pages/{donor,ngo}/Pickup*.jsx`, `components/{OtpInput,PickupStepper,NotificationBell}.jsx`.

**5. Implementation Guidance:** Do not put the OTP in URLs, query strings, localStorage or console logs; clear the input on unmount; disable paste logging. Use `Intl.DateTimeFormat('en-IN',{timeZone:'Asia/Kolkata'})` for display.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-15.1-01 (P) | Propose → confirm | accepted allocation | NGO proposes; donor confirms | Stepper shows `scheduled` for both | ☐ |
| T-15.1-02 (V) | Past time | – | choose yesterday | Blocked client + server 422 shown | ☐ |
| T-15.1-03 (P) | Reschedule resets | otp_issued | change time | Stepper back to `proposed`; OTP notice says previous code invalid | ☐ |
| T-15.1-04 (P) | Issue OTP (Mailpit) | within 24 h | click send | Toast "emailed"; Mailpit has code; UI never shows code | ☐ |
| T-15.1-05 (P) | Donor enters valid OTP | issued | type 6 digits | Success; state `collected` | ☐ |
| T-15.1-06 (N) | Wrong OTP | – | wrong code | Error + attempts left (e.g., 4) | ☐ |
| T-15.1-07 (S) | Lockout UX | 5 wrong | – | Locked message; input disabled; instructs NGO to reissue | ☐ |
| T-15.1-08 (E) | Expired OTP | fake/shortened TTL in staging | enter | Expired message | ☐ |
| T-15.1-09 (V) | Non-numeric / paste with spaces | – | paste `12 34 56` | Normalised to digits, or rejected clearly | ☐ |
| T-15.1-10 (P) | Confirm receipt | collected | NGO confirms | `completed`; dashboards update | ☐ |
| T-15.1-11 (P) | Notification bell | events fired | wait/poll | Unread count correct; click navigates; mark-read persists | ☐ |
| T-15.1-12 (S) | OTP not stored client-side | after flow | inspect storage, URL, history, console | Absent | ☐ |
| T-15.1-13 (P) | Appendix B steps 7–9 | seed | complete full journey via UI | Counts/progress consistent | ☐ |
| T-15.1-14 (A11y) | OTP input a11y | – | keyboard + screen reader | Group labelled; errors announced | ☐ |
| T-15.1-15 (F) | Poll failure | API 500 | bell | Silent retry with backoff; no error spam | ☐ |

**7. Verification Checklist:** [ ] Manual two-browser handover with real Mailpit email. [ ] Spec §19 items 14–16 verified through UI.

**8. Milestone Completion Criteria:** DONE when the handover can be completed purely in the UI and OTP never appears client-side.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-15-pickup-ui
git status
git add .
git commit -m "feat(web): pickup scheduling, otp verification and notification centre (M15.1)"
git push origin phase-15-pickup-ui
git tag -a m15.1 -m "M15.1 pickup ui" && git push origin m15.1
```
*Do NOT commit:* screenshots containing OTP codes.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 15 Completion Checkpoint
Template §0.4.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-15-pickup-ui -m "merge: phase 15 pickup ui"
git push origin main
git tag -a phase-15-complete -m "Phase 15 complete" && git push origin phase-15-complete
```

---

# PHASE 16 – Frontend: Administrator
**Branch:** `phase-16-admin-ui`

## Milestone 16.1 – NGO Verification Queue, Users & Categories

**Depends on:** Phase 15 complete.

**1. Milestone Name:** Admin screens 15, 16, 17.

**2. Objective:** Admins verify NGOs (view documents safely), manage users and categories.

**3. Tasks to Complete**
- [ ] Manage NGOs: queue with status tabs, search, detail view with org data and documents opened through `/api/media/ngo-documents/{id}` in a new tab (`rel="noopener noreferrer"`), decision panel (approve / reject / request correction / suspend / reinstate; note required where server requires).
- [ ] Manage Users: search/filter, suspend/reinstate with reason dialog, self-suspend button hidden (server still enforces).
- [ ] Manage Categories: create/edit/activate-deactivate, compatibility editor (multi-select + factor), conflict messages surfaced.
- [ ] Admin moderation action on a donation (remove/restore with reason) accessible from a donation lookup by id.

**4. Files / Modules Affected:** `pages/admin/{Ngos,NgoDetail,Users,Categories}.jsx`.

**5. Implementation Guidance:** Treat admin UI as high-risk for XSS (it renders other users' text and filenames) — render everything as text, never create object URLs from untrusted HTML.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-16.1-01 (P) | Approve NGO | pending NGO | open → approve | Status verified; NGO can use requirement screens on next load | ☐ |
| T-16.1-02 (V) | Reject without note | – | submit | Blocked | ☐ |
| T-16.1-03 (S) | Non-admin opens `/admin` | donor | – | Not permitted page; API calls 403 | ☐ |
| T-16.1-04 (S) | Stored XSS in org name/doc name | `<script>` | view in queue | Escaped text | ☐ |
| T-16.1-05 (P) | Open document | PDF | click | Opens via authorised endpoint; direct file path 404 | ☐ |
| T-16.1-06 (P) | Suspend user + reason | – | submit | User blocked on next action | ☐ |
| T-16.1-07 (F) | 409 on category deactivate | in use | click | Message with usage counts | ☐ |
| T-16.1-08 (P) | Category compatibility edit | – | save | Matching reflects (verify via NGO matches) | ☐ |
| T-16.1-09 (P) | Moderate listing | active donation | remove w/ reason | Disappears from NGO lists | ☐ |
| T-16.1-10 (R) | Previous suites | – | rerun | ✅ | ☐ |

**7. Verification Checklist:** [ ] Spec §19 item 8 ✅ via UI. [ ] Keyboard-operable tables.

**8. Milestone Completion Criteria:** DONE when admins complete verification and moderation tasks through UI.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-16-admin-ui
git status
git add .
git commit -m "feat(web): admin ngo verification, users and categories screens (M16.1)"
git push origin phase-16-admin-ui
git tag -a m16.1 -m "M16.1 admin ops ui" && git push origin m16.1
```
*Do NOT commit:* downloaded NGO documents.

**10. Rollback / Recovery:** **R-1**.

---

## Milestone 16.2 – Admin Dashboard, Reports & Audit Log Screens

**Depends on:** M16.1.

**1. Milestone Name:** Admin screens 14 and 18 plus audit viewer.

**2. Objective:** Visual summaries that equal backend ground truth.

**3. Tasks to Complete**
- [ ] Admin dashboard: count cards, trend line (donations/completions per week), category distribution bar, recent activity list.
- [ ] Reports page: date-range picker (≤ 366 d), request outcomes, completion rate with its definition tooltip.
- [ ] Charts: lightweight library (`recharts`) — justification: accessible SVG charts with little code; provide a **data table alternative** for every chart.
- [ ] Audit log viewer: filters, pagination, expandable metadata (already redacted by server).

**4. Files / Modules Affected:** `pages/admin/{Dashboard,Reports,AuditLogs}.jsx`.

**5. Implementation Guidance:** Charts get text summaries and non-colour cues (patterns/labels). Empty ranges show "No data for this period", never a broken chart.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-16.2-01 (P) | Numbers reconcile | fixture | compare UI to SQL | Equal | ☐ |
| T-16.2-02 (E) | Empty range | – | choose empty period | Empty state | ☐ |
| T-16.2-03 (V) | Range >366 d | – | choose | Inline error | ☐ |
| T-16.2-04 (A11y) | Chart alternatives | – | toggle "table view" | Same data, accessible table | ☐ |
| T-16.2-05 (P) | Audit filtering | many rows | filter by action | Correct rows | ☐ |
| T-16.2-06 (S) | No secrets in audit UI | – | scan | None | ☐ |
| T-16.2-07 (P) | Responsive | 360 px | open | Charts resize; tables scroll inside container | ☐ |

**7. Verification Checklist:** [ ] Screenshot set saved for the final report (no real data). [ ] Appendix B step 10 ✅.

**8. Milestone Completion Criteria:** DONE when admin analytics are correct, accessible and responsive.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "feat(web): admin dashboard, reports and audit log screens (M16.2)"
git push origin phase-16-admin-ui
git tag -a m16.2 -m "M16.2 admin analytics ui" && git push origin m16.2
```
*Do NOT commit:* exported reports.

**10. Rollback / Recovery:** **R-1** then `npm ci`.

---

## ✅ Phase 16 Completion Checkpoint
Template §0.4. Extra: **All 20 screens from spec §10 exist** — tick the list in the checkpoint table.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-16-admin-ui -m "merge: phase 16 admin ui"
git push origin main
git tag -a phase-16-complete -m "Phase 16 complete" && git push origin phase-16-complete
```

---

# PHASE 17 – Validation, Error Handling & Accessibility Pass
**Branch:** `phase-17-polish`

## Milestone 17.1 – Error/Empty/Loading Standardisation, Accessibility & Responsive Audit

**Depends on:** Phase 16 complete.

**1. Milestone Name:** Cross-cutting UX/validation consistency (spec §17 phase 8; §10.1; §14).

**2. Objective:** Every screen handles loading/empty/error/permission states identically, every API error code maps to friendly copy, and WCAG 2.1 AA practices are verified.

**3. Tasks to Complete**
- [ ] `errorMessages.js` mapping `error.code` → user text (VALIDATION_FAILED, UNAUTHENTICATED, FORBIDDEN, NOT_FOUND, METHOD_NOT_ALLOWED, INSUFFICIENT_QUANTITY, INVALID_TRANSITION, OTP_*, RATE_LIMITED, PAYLOAD_TOO_LARGE, UNSUPPORTED_MEDIA_TYPE, INTERNAL_ERROR) — all pages use it.
- [ ] Backend: grep every controller for hand-rolled error JSON; replace by exceptions; add test enumerating routes and asserting the envelope on 401/403/404/405.
- [ ] Add `jest-axe` checks for each page component; fix violations.
- [ ] Manual audit: keyboard-only walkthrough of 6 key journeys; contrast check; zoom 200%; screen-reader smoke test (NVDA/VoiceOver) of login, post donation, OTP.
- [ ] Responsive audit at 360, 768, 1024, 1440.
- [ ] Add `<noscript>` message and error boundary page with request ID.
- [ ] Confirm Leaflet popups/markers keyboard reachable or have list equivalent.

**4. Files / Modules Affected:** `frontend/src/lib/errorMessages.js`, many page components (small edits), `backend/tests/Integration/ErrorEnvelopeTest.php`.

**5. Implementation Guidance:** This is a *regression-heavy* milestone — change little, test lots. Do not alter API codes (contract is frozen).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-17.1-01 (P) | Envelope on every route | route list | automated test hitting each route with bad method/unauth | All JSON envelope, correct code | ☐ |
| T-17.1-02 (P) | Code→message coverage | unit | iterate all codes in contract | Each has friendly text | ☐ |
| T-17.1-03 (A11y) | axe on all pages | – | jest-axe | 0 serious/critical violations | ☐ |
| T-17.1-04 (A11y) | Keyboard walkthrough | – | 6 journeys | Completable without mouse; visible focus | ☐ |
| T-17.1-05 (A11y) | Colour not sole cue | – | grayscale screenshot | Statuses distinguishable | ☐ |
| T-17.1-06 (P) | Responsive matrix | 4 widths × key pages | – | No overflow/clipping | ☐ |
| T-17.1-07 (F) | Error boundary | throw in component | – | Friendly page with request ID, no stack | ☐ |
| T-17.1-08 (F) | Offline | disable network | use app | Clear offline error; no infinite spinners | ☐ |
| T-17.1-09 (R) | Full suites | – | `verify.sh` | ✅ | ☐ |

**7. Verification Checklist:** [ ] Lighthouse a11y ≥ 90 on 5 key pages (record actual). [ ] Defect list triaged.

**8. Milestone Completion Criteria:** DONE when no serious a11y violation remains and error handling is uniform.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-17-polish
git status
git add .
git commit -m "fix(ux): standardise errors, accessibility and responsive behaviour (M17.1)"
git push origin phase-17-polish
git tag -a m17.1 -m "M17.1 polish" && git push origin m17.1
```
*Do NOT commit:* Lighthouse JSON with local paths (store summary in docs instead).

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 17 Completion Checkpoint
Template §0.4.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-17-polish -m "merge: phase 17 polish"
git push origin main
git tag -a phase-17-complete -m "Phase 17 complete" && git push origin phase-17-complete
```

---
# PHASE 18 – Testing & Performance
**Branch:** `phase-18-testing`

## Milestone 18.1 – Test-Gap Closure, Role Matrix & Regression Gate

**Depends on:** Phase 17 complete.

**1. Milestone Name:** Complete the automated suite and make it a mandatory gate.

**2. Objective:** Spec §15.1–15.2: every unit/integration area listed in the spec has automated coverage; one command proves nothing regressed.

**3. Tasks to Complete**
- [ ] Generate coverage: `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text` (install Xdebug or PCOV locally) and `npm test -- --run --coverage`; list files < 70 % line coverage that contain business rules (Services, Policies) and add tests (coverage % is a *guide*, not the goal).
- [ ] **Role × route matrix test**: auto-iterates `routes.php`, calls each as anonymous/donor/ngo-pending/ngo-verified/admin/suspended, compares with an expected table in `tests/Support/expected_access.php` (a new route without an entry fails the build).
- [ ] **Status-code matrix**: 401, 403, 404, 405, 409, 413, 415, 422, 429 each triggered at least once through the real HTTP stack.
- [ ] Add `scripts/verify.sh` stages: lint → PHPUnit (unit+integration) → Vitest → build; separate `scripts/verify-full.sh` adds concurrency + Playwright.
- [ ] Optional: GitHub Actions workflow `.github/workflows/ci.yml` running Postgres+PostGIS service and `verify.sh` on every push (secrets: none required).
- [ ] Triage and fix failures; record in `docs/test-report.md` (environment, versions, counts).

**4. Files / Modules Affected:** `backend/tests/**`, `frontend/**/*.test.jsx`, `scripts/*.sh`, `.github/workflows/ci.yml`, `docs/test-report.md`.

**5. Implementation Guidance:** Flaky tests are defects: fix or delete with justification — never retry-until-green. Keep fixtures small and builders reusable.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-18.1-01 (R) | Full unit+integration run | clean test DB | `bash scripts/verify.sh` | Exit 0 | ☐ |
| T-18.1-02 (S) | Role matrix complete | – | run matrix test | Every route/role pair matches expectation | ☐ |
| T-18.1-03 (N) | Unregistered route detection | add dummy route without expectation | run | Build fails (then remove dummy) | ☐ |
| T-18.1-04 (P) | Status-code coverage | – | run | All 9 error codes observed | ☐ |
| T-18.1-05 (R) | Repeatability | – | run suite 3× | Same results; no order dependence (`--order-by=random`) | ☐ |
| T-18.1-06 (P) | Clean-clone run | fresh clone | follow README → verify | ✅ | ☐ |
| T-18.1-07 (P) | CI green (if set up) | push | Actions run | ✅ | ☐ |

**7. Verification Checklist:** [ ] Coverage numbers recorded honestly. [ ] No `skip`/`markTestSkipped` without a linked known-issue.

**8. Milestone Completion Criteria:** DONE when `verify.sh` is green on a clean clone and the matrix test guards authorization.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-18-testing
git status
git add .
git commit -m "test: coverage gaps, role matrix and regression gate (M18.1)"
git push origin phase-18-testing
git tag -a m18.1 -m "M18.1 regression gate" && git push origin m18.1
```
*Do NOT commit:* `coverage/` output.

**10. Rollback / Recovery:** **R-1**.

---

## Milestone 18.2 – Performance Measurement (API, Matching @100k, Frontend)

**Depends on:** M18.1.

**1. Milestone Name:** Reproducible benchmarks against spec §14 targets.

**2. Objective:** Measure — not assume — matching latency (target ≤ 250 ms @100,000 donations/50 km), API p95 (≤ 300 ms), and frontend interactivity (≤ 3 s on 4G profile). Report honestly (Appendix C).

**3. Tasks to Complete**
- [ ] `bin/seed-perf.php`: inserts 100 000 donations (random points inside a ~120 km box around Pune, mixed categories/statuses/quantities), 500 NGOs/requirements, via set-based SQL (`generate_series`); `ANALYZE` after.
- [ ] Matching benchmark: run candidate query for 50 different requirements × 20 repetitions; record median, p95, max; run `EXPLAIN (ANALYZE, BUFFERS)`; compare **with and without** the GiST index (`DROP INDEX` in a scratch DB copy).
- [ ] If target missed: tune in this order — verify index use, `VACUUM ANALYZE`, add partial index `WHERE status IN ('active','partially_allocated')`, pre-filter by bounding box/`&&`, LIMIT early; re-measure. Do **not** change eligibility semantics for speed.
- [ ] k6 script `tests/load/api_reads.js`: 20 VUs, 60 s, mix of `GET /api/donations`, `/api/matches`, `/api/notifications`; thresholds `http_req_duration{p(95)}<300`, `http_req_failed<0.01`.
- [ ] Lighthouse (CLI or Chrome DevTools) on landing, login, matched donations with throttling "Slow 4G/Fast 4G" desktop+mobile profiles; record TTI/LCP/TBT.
- [ ] Record environment (CPU, RAM, PG/PHP versions, dataset, repetitions) in `docs/test-report.md`.

**4. Files / Modules Affected:** `bin/seed-perf.php`, `tests/load/*.js`, `docs/performance.md`, possibly `db/migrations/007_matching_indexes`.

**5. Implementation Guidance:** Benchmark on the *same kind of machine* you will demo on; PHP built-in server is single-threaded — use PHP-FPM + Nginx (or `PHP_CLI_SERVER_WORKERS=4`) for load tests, and say so in the report.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-18.2-01 (P) | Seed 100k | empty perf DB | run seed script | `SELECT count(*)` = 100000; seed < 5 min | ☐ |
| T-18.2-02 (P) | Matching latency | seeded + ANALYZE | benchmark | Record median/p95; **target** p95 ≤ 250 ms | ☐ |
| T-18.2-03 (P) | Index effect | – | with vs without GiST | Documented speed-up factor | ☐ |
| T-18.2-04 (P) | EXPLAIN shows index scan | – | EXPLAIN ANALYZE | `Index Scan`/`Bitmap` on GiST | ☐ |
| T-18.2-05 (P) | API read p95 | seeded, FPM | k6 | p95 ≤ 300 ms, errors < 1 % | ☐ |
| T-18.2-06 (P) | Frontend TTI | prod build served by Nginx or `vite preview` | Lighthouse (4G) | TTI ≤ 3 s target; record actual | ☐ |
| T-18.2-07 (F) | Target missed | – | – | Result recorded as **not met** with analysis, never hidden | ☐ |
| T-18.2-08 (R) | Correctness after tuning | – | rerun M8 suites | ✅ identical rankings on golden set | ☐ |
| T-18.2-09 (E) | Dense area (10k within radius) | cluster seed | benchmark | Pagination keeps response ≤ limit; latency recorded | ☐ |

**7. Verification Checklist:** [ ] Another person can reproduce numbers from `docs/performance.md` commands. [ ] Perf DB isolated from dev data.

**8. Milestone Completion Criteria:** DONE when numbers (met or not met) are recorded with environment and reproducible steps.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "perf: benchmarks for matching, api and frontend with recorded results (M18.2)"
git push origin phase-18-testing
git tag -a m18.2 -m "M18.2 performance" && git push origin m18.2
```
*Do NOT commit:* the 100k-row dump, raw k6 JSON, Lighthouse HTML containing local info.

**10. Rollback / Recovery:** **R-3** (if index migration added) then **R-1**; drop the perf database separately.

---

## Milestone 18.3 – End-to-End Browser Tests (Playwright)

**Depends on:** M18.2.

**1. Milestone Name:** Automated E2E journeys for all roles.

**2. Objective:** Spec §15.4: prove complete user journeys in a real browser against real API+DB+Mailpit.

**3. Tasks to Complete**
- [ ] `cd frontend && npm i -D @playwright/test && npx playwright install chromium firefox`.
- [ ] `e2e/global-setup.ts`: reset test DB (`migrate down all/up` or truncate), seed categories, create admin via CLI helper using a **test-only** password from env.
- [ ] Specs: (1) public → signup/login/logout; (2) donor lists item with image; (3) NGO registers → admin approves → NGO posts requirement; (4) match → request 8 → second NGO requests 4 (partial allocation); (5) donor accepts; pickup proposal/confirm; OTP read from **Mailpit API** (`http://localhost:8025/api/v1/messages`) → donor verifies → NGO confirms receipt; (6) wrong/expired OTP paths; (7) admin moderation & category toggle; (8) responsive smoke at mobile viewport; (9) API-error UI (route intercept returning 500/409).
- [ ] Videos/traces on failure only; reports not committed.

**4. Files / Modules Affected:** `frontend/e2e/**`, `playwright.config.ts`, `package.json` scripts.

**5. Implementation Guidance:** Use role-specific browser contexts to hold sessions; wait on UI state (`expect(...).toBeVisible()`), never fixed `sleep`. E2E uses its own DB/ports to avoid destroying dev data.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-18.3-01 (P) | Spec Appendix B full run | fresh DB | run `e2e/appendix-b.spec` | All steps pass | ☐ |
| T-18.3-02 (N) | Wrong OTP then correct | – | spec | Error shown; then success | ☐ |
| T-18.3-03 (F) | Expired OTP path | short TTL env | spec | Expired message; reissue works | ☐ |
| T-18.3-04 (C) | Two NGOs race last units | 2 contexts | spec | One succeeds, other sees conflict UI | ☐ |
| T-18.3-05 (S) | Cross-role URL access | – | donor opens `/admin`, NGO opens other NGO's requirement | Blocked / not found | ☐ |
| T-18.3-06 (P) | Cross-browser | chromium+firefox | run all | ✅ both | ☐ |
| T-18.3-07 (P) | Mobile viewport | 390×844 | smoke spec | ✅ | ☐ |
| T-18.3-08 (R) | Re-run ×3 | – | repeat | No flakes | ☐ |

**7. Verification Checklist:** [ ] `npx playwright show-report` reviewed. [ ] Traces only for failures.

**8. Milestone Completion Criteria:** DONE when the full journey is green on two browsers three times in a row.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "test(e2e): playwright journeys for all roles incl. otp handover (M18.3)"
git push origin phase-18-testing
git tag -a m18.3 -m "M18.3 e2e" && git push origin m18.3
```
*Do NOT commit:* `playwright-report/`, `test-results/`, traces/videos, test admin password.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 18 Completion Checkpoint
Template §0.4. Extra: `docs/test-report.md` has results for unit, integration, concurrency, E2E, performance (met/not met). Spec §19 item 19 ✅.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-18-testing -m "merge: phase 18 testing"
git push origin main
git tag -a phase-18-complete -m "Phase 18 complete" && git push origin phase-18-complete
```

---

# PHASE 19 – Security Audit
**Branch:** `phase-19-security`

## Milestone 19.1 – Security Review Against Spec §13 & §15.5

**Depends on:** Phase 18 complete.

**1. Milestone Name:** Structured security audit with evidence.

**2. Objective:** Find (not assume away) weaknesses in access control, files, sessions, OTP and secrets; document in `docs/security-checklist.md`.

**3. Tasks to Complete**
- [ ] **Static review:** grep for `->query(` / string-concatenated SQL, `$_GET/$_POST` direct use, `eval`, `unserialize`, `shell_exec`, `dangerouslySetInnerHTML`, `innerHTML`; `composer audit`; `npm audit --omit=dev`.
- [ ] **Secrets scan:** `git log -p | grep -iE "password|secret|key"` and optionally `gitleaks detect` on full history.
- [ ] **Authorization (IDOR) sweep:** for every `{id}` route, test with another user's ID (automated via the role matrix + a dedicated `IdorTest`).
- [ ] **Session/cookie review** on a staging HTTPS instance (or locally behind a TLS proxy): flags `Secure; HttpOnly; SameSite`; fixation test; logout invalidation; suspension invalidation.
- [ ] **CSRF** manual forged-form test from a local HTML page on another origin.
- [ ] **XSS**: payload corpus in every text field (title, description, org name, notes, filenames, pickup details) → verify escaped in UI + admin screens + emails (HTML email parts escape).
- [ ] **SQLi** attempts with `sqlmap` against a *local test instance only* (never against third-party systems).
- [ ] **File upload** attacks: polyglot, SVG, zip bomb, path traversal, MIME spoof, direct URL.
- [ ] **OTP**: brute-force script respects 5-attempt lockout and rate limit; expiry; replay; log leakage grep.
- [ ] **Headers/CORS**: `securityheaders`-style check (CSP, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options`/`frame-ancestors`).
- [ ] **OWASP ZAP baseline scan** (`zap-baseline.py -t http://localhost:…`) on the local instance; triage alerts.
- [ ] **Privacy check:** donor exact location/address visible only to owner/admin/NGO-after-scheduled; EXIF stripped; emails contain minimal data.
- [ ] Record each finding: ID, severity (Sev-1 critical … Sev-4 low), evidence, fix owner, status.

**4. Files / Modules Affected:** `docs/security-checklist.md`, `backend/tests/Security/*`, config fixes as needed.

**5. Implementation Guidance:** Only test systems you own. Treat any Sev-1/Sev-2 as blocking. Keep ZAP/sqlmap output out of Git (may contain tokens).

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-19.1-01 (S) | SQL injection corpus | local instance | sqlmap on login, filters, ids | No injection found | ☐ |
| T-19.1-02 (S) | XSS corpus in all inputs | – | submit + view as every role | No script execution anywhere | ☐ |
| T-19.1-03 (S) | IDOR sweep | two users per role | access each other's resources | 403/404 always | ☐ |
| T-19.1-04 (S) | CSRF forged form | logged-in victim | cross-origin POST | Blocked 403 | ☐ |
| T-19.1-05 (S) | Session fixation | known pre-login SID | login | New SID issued; old invalid | ☐ |
| T-19.1-06 (S) | Cookie flags (HTTPS) | TLS proxy | inspect | Secure+HttpOnly+SameSite | ☐ |
| T-19.1-07 (S) | Upload attack set | – | run corpus | All rejected, nothing stored | ☐ |
| T-19.1-08 (S) | Direct media/doc URL | – | guess storage paths | 404 | ☐ |
| T-19.1-09 (S) | OTP brute force | issued OTP | script 1000 guesses | Locked at 5; 429s; success probability negligible | ☐ |
| T-19.1-10 (S) | Secret scanning | repo | gitleaks/grep history | 0 findings (or rotated) | ☐ |
| T-19.1-11 (S) | Dependency audit | – | composer/npm audit | No high/critical unpatched (or documented) | ☐ |
| T-19.1-12 (S) | ZAP baseline | – | run | No high-risk alerts unresolved | ☐ |
| T-19.1-13 (S) | Headers | prod-like | curl -I | CSP and other headers present | ☐ |
| T-19.1-14 (S) | Error leakage | induce errors | review responses | No stack/SQL/paths | ☐ |
| T-19.1-15 (S) | Privileged action authz | – | admin endpoints as each role | Only admin passes | ☐ |
| T-19.1-16 (S) | Suspended-user token reuse | suspended during session | request | 401 | ☐ |

**7. Verification Checklist:** [ ] Findings table complete. [ ] Each Sev-1/2 has a fix plan (executed in M19.2).

**8. Milestone Completion Criteria:** DONE when every checklist item has been executed and evidence recorded (pass or finding).

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-19-security
git status
git add docs/ backend/tests/Security
git commit -m "docs(security): audit checklist, findings and security tests (M19.1)"
git push origin phase-19-security
git tag -a m19.1 -m "M19.1 security audit" && git push origin m19.1
```
*Do NOT commit:* scanner raw reports, exploit payload dumps containing tokens, any discovered credential.

**10. Rollback / Recovery:** **R-1** (docs/tests only).

---

## Milestone 19.2 – Remediation & Re-test

**Depends on:** M19.1.

**1. Milestone Name:** Fix findings and prove closure.

**2. Objective:** Close all Sev-1/Sev-2 (and agreed Sev-3) findings, adding a regression test per fix.

**3. Tasks to Complete**
- [ ] One commit per finding, message `fix(security): <ID> …`; each with a failing-then-passing regression test.
- [ ] Re-run the whole M19.1 test table; update statuses.
- [ ] Rotate any secret that was ever exposed; update `.env.example` if new variables appear.
- [ ] Document accepted residual risks (with reason) in `docs/security-checklist.md`.

**4. Files / Modules Affected:** as dictated by findings; `docs/security-checklist.md`; tests.

**5. Implementation Guidance:** Fix root causes (e.g., central policy) rather than patching single routes. Re-run `verify-full.sh` after each fix to catch regressions.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-19.2-01 (R) | Each fixed finding has regression test | findings list | run tests | Pass; fail on revert of fix | ☐ |
| T-19.2-02 (S) | Re-run M19.1 table | – | repeat | All ✅ or documented residual risk | ☐ |
| T-19.2-03 (R) | Full suite | – | `verify-full.sh` | ✅ | ☐ |
| T-19.2-04 (S) | Secrets re-scan | – | gitleaks | 0 | ☐ |

**7. Verification Checklist:** [ ] No open Sev-1/Sev-2. [ ] Reviewer sign-off noted.

**8. Milestone Completion Criteria:** DONE when zero open Sev-1/Sev-2 findings remain.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "fix(security): remediate audit findings with regression tests (M19.2)"
git push origin phase-19-security
git tag -a m19.2 -m "M19.2 security fixes" && git push origin m19.2
```
*Do NOT commit:* anything with real credentials.

**10. Rollback / Recovery:** **R-1** per commit (granular reverts are why we commit per finding).

---

## ✅ Phase 19 Completion Checkpoint
Template §0.4. **No-Go** if any Sev-1/Sev-2 is open.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-19-security -m "merge: phase 19 security"
git push origin main
git tag -a phase-19-complete -m "Phase 19 complete" && git push origin phase-19-complete
```

---

# PHASE 20 – Documentation
**Branch:** `phase-20-docs`

## Milestone 20.1 – Documentation Pack

**Depends on:** Phase 19 complete.

**1. Milestone Name:** Setup, API, database, user, test and limitation documents.

**2. Objective:** Spec §17 phase 12 and §19 item 20: a stranger can start the app from the docs on a clean machine.

**3. Tasks to Complete**
- [ ] `README.md`: prerequisites, 10-step quick start (`docker compose up -d` → `cp .env.example .env` → `composer install` → `migrate` → seed → create admin → `npm ci` → run API/web/worker), troubleshooting table.
- [ ] `docs/api-contract.md` final (examples from real responses).
- [ ] `docs/database.md`: ER diagram (Mermaid), table purposes, constraint list, quantity-consistency strategy.
- [ ] `docs/architecture.md`: layered diagram, request flow, allocation transaction sequence diagram.
- [ ] `docs/user-guide.md`: per role with screenshots (synthetic data).
- [ ] `docs/test-report.md`, `docs/performance.md`, `docs/security-checklist.md` (from earlier).
- [ ] `docs/known-limitations.md`: password reset absent (D-6), no geocoding (D-5), dispute handling absent, real-time chat out of scope, etc.; **claims table** honouring Appendix C (what is verified vs not).
- [ ] `docs/operations.md`: cron lines, backup/restore, credential rotation, log locations.
- [ ] Clean-machine rehearsal: new VM/container or teammate follows README exactly; fix every gap.

**4. Files / Modules Affected:** `README.md`, `docs/**`.

**5. Implementation Guidance:** Docs must describe what *exists*. Mark anything unbuilt as "Future work". Never include real emails/passwords in screenshots.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-20.1-01 (P) | Clean-machine setup | fresh environment | follow README only | App + tests run; no undocumented step needed | ☐ |
| T-20.1-02 (V) | Command accuracy | – | execute every command block in docs | All succeed | ☐ |
| T-20.1-03 (V) | API doc vs reality | – | script compares documented routes to `routes.php` | No mismatch | ☐ |
| T-20.1-04 (V) | Links/diagrams render | GitHub | open docs | No broken links; Mermaid renders | ☐ |
| T-20.1-05 (S) | No secrets/real data in docs | – | grep | None | ☐ |
| T-20.1-06 (V) | Claims audit | spec App. C | review known-limitations | No unverified claim | ☐ |

**7. Verification Checklist:** [ ] Spec §19 item 20 ✅. [ ] Teammate sign-off.

**8. Milestone Completion Criteria:** DONE when a third party reproduces the setup unaided.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-20-docs
git status
git add README.md docs/
git commit -m "docs: setup, api, database, user, test and limitations documentation (M20.1)"
git push origin phase-20-docs
git tag -a m20.1 -m "M20.1 docs" && git push origin m20.1
```
*Do NOT commit:* screenshots showing real data or tokens.

**10. Rollback / Recovery:** **R-1**.

---

## ✅ Phase 20 Completion Checkpoint
Template §0.4.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-20-docs -m "merge: phase 20 docs"
git push origin main
git tag -a phase-20-complete -m "Phase 20 complete" && git push origin phase-20-complete
```

---

# PHASE 21 – Deployment
**Branch:** `phase-21-deploy`
> **Confirm D-4 (hosting) and D-13 (SMTP provider) before starting.** Examples assume Ubuntu + Nginx + PHP-FPM.

## Milestone 21.1 – Production Configuration, Hardening & Backup Scripts

**Depends on:** Phase 20 complete.

**1. Milestone Name:** Deployable configuration as code (no secrets).

**2. Objective:** Spec §16: HTTPS, secure cookies, private storage, least-privilege DB, CORS, backups — captured in committed templates and scripts.

**3. Tasks to Complete**
- [ ] `deploy/nginx.conf.example`: serve `frontend/dist` at `/`, SPA fallback `try_files $uri /index.html`; `location /api/ { fastcgi to php-fpm → backend/public/index.php }`; **deny** `/storage`, dotfiles, `.env`; `client_max_body_size 30m`; redirect 80→443; HSTS; headers: `Content-Security-Policy: default-src 'self'; img-src 'self' data: https://*.tile.openstreetmap.org; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(self)`.
- [ ] `deploy/php-prod.ini`: `display_errors=0`, `log_errors=1`, `expose_php=0`, `session.cookie_secure=1`, `session.cookie_httponly=1`, `session.cookie_samesite=Lax`, `session.use_strict_mode=1`, `upload_max_filesize=10M`, `post_max_size=30M`, `opcache.enable=1`.
- [ ] `deploy/.env.production.example` (names only) — `APP_ENV=production`, production `APP_URL`, `CORS_ALLOWED_ORIGIN`, trusted proxy IP setting.
- [ ] `deploy/db-setup.sql`: create prod owner + app roles with **new strong passwords supplied at runtime**, enable PostGIS/citext.
- [ ] `deploy/backup.sh` (`pg_dump -Fc` + `rsync`/tar of `storage/private`, retention 14 days, encrypted if stored off-box) and `deploy/restore.md`.
- [ ] Cron templates: `send-outbox` (every minute or systemd service with `--loop`), `expire-requests` (hourly), `expire-donations` (daily), `purge-rate-limits` (daily), `backup.sh` (daily).
- [ ] `deploy/deploy.sh`: `git fetch --tags && git checkout <tag>` → `composer install --no-dev --optimize-autoloader` → `npm ci && npm run build` → `php backend/bin/migrate.php up` → reload php-fpm. Refuses to run with a dirty tree or without a tag.
- [ ] Log rotation config for `storage/logs`.

**4. Files / Modules Affected:** `deploy/**`, docs update.

**5. Implementation Guidance:** CSP: Leaflet needs OSM tile images and inline styles; verify the app works under the CSP in staging and tighten rather than loosening. Deploy **tags**, not branches, so rollback = redeploy the previous tag.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-21.1-01 (V) | Nginx config valid | server | `nginx -t -c …` | OK | ☐ |
| T-21.1-02 (V) | PHP ini applied | php-fpm | `php-fpm -i \| grep -E "display_errors\|expose_php"` | As specified | ☐ |
| T-21.1-03 (S) | Secret files not committed | repo | `git ls-files \| grep -E "\.env$"` | Only `.example` files | ☐ |
| T-21.1-04 (V) | deploy.sh guards | dirty tree | run | Refuses | ☐ |
| T-21.1-05 (P) | Backup+restore drill | staging | run backup, restore to scratch DB | Row counts equal; files restored | ☐ |
| T-21.1-06 (S) | Least-privilege DB | prod-like | app user `CREATE TABLE` | Denied | ☐ |
| T-21.1-07 (S) | Storage denied via web | staging | curl `/storage/…` and `/.env` | 403/404 | ☐ |

**7. Verification Checklist:** [ ] No secret literal in `deploy/`. [ ] Restore steps executed once, timed.

**8. Milestone Completion Criteria:** DONE when configs are reviewed and backup/restore has been proven on a scratch DB.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-21-deploy
git status
git add deploy/ docs/
git commit -m "chore(deploy): nginx, php, db, backup and deploy script templates (M21.1)"
git push origin phase-21-deploy
git tag -a m21.1 -m "M21.1 deploy config" && git push origin m21.1
```
*Do NOT commit:* real `.env`, TLS keys/certs, backup archives, server IPs/credentials.

**10. Rollback / Recovery:** **R-1** (configs only).

---

## Milestone 21.2 – Staging/Production Deployment & Smoke Tests

**Depends on:** M21.1.

**1. Milestone Name:** Deploy a tagged release and run smoke tests.

**2. Objective:** Spec §16.4: HTTPS demo/production with verified login, listing, matching, allocation and OTP flow; repeatable steps.

**3. Tasks to Complete**
- [ ] Provision server; install Nginx, PHP 8.2-FPM + extensions, PostgreSQL 16 + PostGIS; create non-root deploy user; firewall allow 22/80/443 only; key-based SSH.
- [ ] DNS + TLS: `certbot --nginx -d <domain>`; confirm auto-renew (`certbot renew --dry-run`).
- [ ] Create `.env` **on the server** (chmod 600, owned by deploy user) with new secrets (`openssl rand -hex 32` for `APP_KEY`, `OTP_HMAC_KEY`); real SMTP credentials (D-13).
- [ ] Tag release `v1.0.0-rc1` locally, push tag, run `deploy/deploy.sh v1.0.0-rc1` on server; create admin via CLI.
- [ ] Install cron/systemd units; run `send-outbox` and confirm a real email (to your own mailbox).
- [ ] Smoke script `deploy/smoke.sh` (curl-based) + manual E2E of Appendix B on the live URL.
- [ ] Verify cookie flags in browser devtools (Secure now testable), HSTS, CSP console errors = 0.
- [ ] Record the deployed commit/tag and date in `docs/operations.md`.

**4. Files / Modules Affected:** server only + `docs/operations.md`, `deploy/smoke.sh`.

**5. Implementation Guidance:** Don't claim "deployed" in any report until this milestone passes (Appendix C). Take a DB backup immediately before every deploy.

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-21.2-01 (P) | HTTPS + redirect | live | `curl -I http://domain` | 301 → https | ☐ |
| T-21.2-02 (S) | Cookie flags | live | login, inspect | `Secure; HttpOnly; SameSite=Lax` | ☐ |
| T-21.2-03 (P) | Smoke: register/login/logout | live | `smoke.sh` | ✅ | ☐ |
| T-21.2-04 (P) | Smoke: list → NGO verify → match → request → accept → OTP | live | manual Appendix B | ✅ ; OTP email arrives via real SMTP | ☐ |
| T-21.2-05 (S) | Config exposure | live | curl `/.env`, `/backend/`, `/storage/` | 403/404 | ☐ |
| T-21.2-06 (S) | Headers | live | `curl -I` | CSP, HSTS, nosniff present | ☐ |
| T-21.2-07 (F) | Email provider failure | block SMTP temporarily | trigger notification | Outbox retries; no 500s | ☐ |
| T-21.2-08 (P) | Backup then redeploy previous tag | live | deploy rc0 → rc1 → back to rc0 | Works; DB intact (migrations forward-only safe — verify!) | ☐ |
| T-21.2-09 (P) | Cron jobs run | live | check logs after an hour | Expire jobs executed | ☐ |
| T-21.2-10 (S) | Port scan | live | `nmap <host>` | Only 22/80/443 | ☐ |

**7. Verification Checklist:** [ ] Rollback rehearsed (below). [ ] Monitoring: disk usage and log errors checked.

**8. Milestone Completion Criteria:** DONE when the release candidate passes smoke + Appendix B on the live HTTPS URL and rollback has been rehearsed.

**9. Git Checkpoint**
```bash
git status
git add .
git commit -m "chore(release): deployment notes and smoke script for v1.0.0-rc1 (M21.2)"
git push origin phase-21-deploy
git tag -a v1.0.0-rc1 -m "Release candidate 1" && git push origin v1.0.0-rc1
git tag -a m21.2 -m "M21.2 deployed" && git push origin m21.2
```
*Do NOT commit:* server `.env`, certificates, SSH keys, IPs.

**10. Rollback / Recovery:** On the server: `deploy/backup.sh` first, then `deploy/deploy.sh <previous-tag>`. If a migration was applied: `php backend/bin/migrate.php down --steps=<n>` **before** switching code, or restore the pre-deploy DB backup. Repo side: **R-1**.

---

## ✅ Phase 21 Completion Checkpoint
Template §0.4. Extra: public URL recorded; rehearsed rollback time noted.
```bash
git checkout main && git pull origin main
git merge --no-ff phase-21-deploy -m "merge: phase 21 deploy"
git push origin main
git tag -a phase-21-complete -m "Phase 21 complete" && git push origin phase-21-complete
```

---

# PHASE 22 – Final Audit & Sign-off
**Branch:** `phase-22-release`

## Milestone 22.1 – Acceptance Traceability & Release

**Depends on:** Phase 21 complete.

**1. Milestone Name:** Compare implementation with scope/acceptance criteria; release `v1.0.0`.

**2. Objective:** Spec §17 phase 14 and §19: every acceptance item Pass/Fail with evidence; honest limitations list; tagged release.

**3. Tasks to Complete**
- [ ] Fill the **Acceptance Traceability Table** (below) with evidence links (test IDs, screenshots, report sections).
- [ ] Re-run `verify-full.sh` on the release commit; run the live smoke + Appendix B once more.
- [ ] Update `docs/known-limitations.md` with any failed/partial items.
- [ ] Complete the **Final Release Checklist** (end of this playbook).
- [ ] Bump version in `composer.json`/`package.json`; create `CHANGELOG.md`.
- [ ] Tag `v1.0.0`, create GitHub Release notes (attach docs, not secrets).

**4. Files / Modules Affected:** `docs/acceptance.md`, `CHANGELOG.md`, versions.

**5. Implementation Guidance:** A criterion is **Pass only if reproducible** (spec §19). Anything partial is reported as partial.

**Acceptance Traceability Table (spec §19)**

| # | Acceptance criterion | Verified by (test IDs) | Result |
|---|---|---|---|
| 1 | Donor register/login/logout; no plaintext password | T-3.1-01/05/10, T-12.2-01 | ☐ |
| 2 | Sessions created/invalidated; secure cookie attrs in HTTPS | T-3.1-05/06/10/11, T-21.2-02 | ☐ |
| 3 | Donor create/view/update/deactivate listing | T-6.1-01/12/14, T-13.1-01 | ☐ |
| 4 | Forms reject missing/invalid/negative quantities server-side | T-6.1-02/03 | ☐ |
| 5 | Allowed image upload OK; invalid/oversized rejected | T-6.2-01/02/04, T-5.1-* | ☐ |
| 6 | Private media inaccessible to unauthorised users | T-5.1-09/10, T-6.2-07/08, T-19.1-08 | ☐ |
| 7 | NGO submits profile/evidence; pending can't do verified-only actions | T-5.2-01/07, T-14.1-02 | ☐ |
| 8 | Admin approve/reject reflected in permissions | T-5.3-01/07/08, T-16.1-01 | ☐ |
| 9 | Verified NGO create/update/close requirement | T-7.1-01/08, T-14.1-01/05 | ☐ |
| 10 | Matching obeys eligibility; ranks by documented components | T-8.1-*, T-8.2-* | ☐ |
| 11 | Map shows permitted locations; manual fallback | T-8.2-12, T-12.2-07/08, T-14.2-07/08 | ☐ |
| 12 | NGO request qty; request & allocation state tracked | T-9.1-*, T-9.2-*, T-14.2-01 | ☐ |
| 13 | Concurrent requests never over-allocate; loser fails safely | T-9.3-01…08, T-18.3-04 | ☐ |
| 14 | Pickup scheduled for accepted allocation | T-10.1-01/02, T-15.1-01 | ☐ |
| 15 | Valid OTP verifies; wrong/expired rejected | T-10.2-05/06/09, T-15.1-05/06/08 | ☐ |
| 16 | OTP attempts limited; OTP never logged | T-10.2-07/15, T-19.1-09 | ☐ |
| 17 | Audit records for state transitions/admin actions | T-3.3-06, T-5.3-09, T-9.1-16, T-10.3-09 | ☐ |
| 18 | Role & ownership checks prevent cross-user modification | T-3.2-09, T-18.1-02, T-19.1-03 | ☐ |
| 19 | API returns correct error codes (401/403/404/409/422/405…) | T-18.1-04, T-17.1-01 | ☐ |
| 20 | Test report records actual performance | T-18.2-02/05/06, `docs/test-report.md` | ☐ |
| 21 | App starts from documented setup on a clean machine | T-20.1-01 | ☐ |

**6. Test Cases**

| Test Case ID | Test Scenario | Preconditions | Steps | Expected Result | Status |
|---|---|---|---|---|---|
| T-22.1-01 (R) | Full regression at release commit | release candidate | `verify-full.sh` | ✅ | ☐ |
| T-22.1-02 (P) | Live Appendix B | deployed rc | manual run | ✅ | ☐ |
| T-22.1-03 (V) | Every §19 row has evidence | table filled | review | No blank evidence | ☐ |
| T-22.1-04 (V) | Claims audit | docs/report | compare to App. C | No overstated claim | ☐ |
| T-22.1-05 (S) | Repo hygiene scan | repo | gitleaks; `git ls-files` review | Clean | ☐ |
| T-22.1-06 (P) | Fresh clone of `v1.0.0` builds | tag | clone → README → run | ✅ | ☐ |

**7. Verification Checklist:** [ ] Mentor/reviewer demo completed. [ ] Known limitations published.

**8. Milestone Completion Criteria:** DONE when all 21 criteria are Pass (or consciously documented as exceptions) and the Final Release Checklist is fully ticked.

**9. Git Checkpoint**
```bash
git checkout main && git pull origin main && git checkout -b phase-22-release
git status
git add .
git commit -m "chore(release): acceptance traceability, changelog and v1.0.0 (M22.1)"
git push origin phase-22-release
git checkout main && git merge --no-ff phase-22-release -m "merge: release v1.0.0" && git push origin main
git tag -a v1.0.0 -m "ShareSphere v1.0.0" && git push origin v1.0.0
```
*Do NOT commit:* anything listed in the universal do-not-commit list.

**10. Rollback / Recovery:** Do not delete a published tag. Fix forward with `v1.0.1`; or redeploy `v1.0.0-rc1`/previous tag (see M21.2) and `git revert` the offending commit (**R-1**).

---

## ✅ Phase 22 Completion Checkpoint (Final)

| Field | Value |
|---|---|
| Completed milestones | All 0.1 – 22.1 |
| Tests passed | |
| Known issues | (copy from `docs/known-limitations.md`) |
| Git commit / hash | |
| Overall verification | Acceptance table fully filled; live smoke ✅ |
| **Go / No-Go (release)** | **Go** only if Final Release Checklist is 100 % ticked |

---

# FINAL RELEASE CHECKLIST

### Functionality
- [ ] Donor: register, login, logout, profile, create/edit/close donation, upload images, see requests, accept/reject, schedule pickup, enter OTP.
- [ ] NGO: register with documents, see status, create/edit/close requirements, view ranked matches (list + map), request quantity, cancel, schedule pickup, receive OTP email, confirm receipt.
- [ ] Admin: verify/reject/correct/suspend NGOs, manage users, categories + compatibility, moderate listings, dashboards, reports, audit logs.
- [ ] Partial allocation works both ways (one donation → many NGOs; one requirement → many donations).
- [ ] Notifications (in-app + email) fire for all events in spec §6.10.
- [ ] All 20 screens in spec §10 exist and are reachable by the right roles.
- [ ] Out-of-scope items (payments, native apps, chat, logistics) are **not** presented as features.

### Testing
- [ ] `scripts/verify.sh` and `scripts/verify-full.sh` green on a clean clone.
- [ ] Unit, integration, concurrency (20/20 clean runs), E2E (2 browsers) all pass.
- [ ] Role × route matrix and status-code matrix pass.
- [ ] Invariant (`available + open + completed = total`; `fulfilled ≤ allocated ≤ needed`) holds after every suite.
- [ ] `docs/test-report.md` contains environment, counts, and honest results.

### Security
- [ ] Passwords hashed; sessions regenerate/destroy correctly; cookie flags verified on HTTPS.
- [ ] CSRF enforced on all state-changing routes; CORS limited to the app origin.
- [ ] Prepared statements everywhere; no unescaped HTML rendering; XSS corpus clean.
- [ ] Uploads validated (ext + MIME + magic bytes + size + dimensions), EXIF stripped, stored privately with random names, served only via authorised endpoints.
- [ ] OTP: CSPRNG, HMAC-stored, TTL, 5-attempt lock, reissue limit, `hash_equals`, never logged/returned/stored in plaintext after send.
- [ ] Rate limiting active on login/register/OTP; audit log append-only.
- [ ] Exact donor location/address hidden until permitted; secrets absent from repo and history (gitleaks clean).
- [ ] No open Sev-1/Sev-2 findings; residual risks documented.

### Performance
- [ ] Matching latency measured @100k rows with EXPLAIN evidence (target ≤ 250 ms p95 — met or documented as not met).
- [ ] API read p95 ≤ 300 ms under defined k6 load (or documented).
- [ ] Frontend interactivity measured on 4G profile (target ≤ 3 s, or documented).
- [ ] No claim in any document exceeds measured results.

### Deployment
- [ ] HTTPS with valid certificate and HSTS; HTTP redirects.
- [ ] `APP_ENV=production`, `display_errors=0`, secrets only in server `.env` (chmod 600).
- [ ] Least-privilege DB user; PostGIS/citext enabled; migrations applied from tag.
- [ ] Private storage outside web root; `/storage`, `/.env`, `/backend` not web-reachable.
- [ ] SMTP/TLS verified with a real delivered email; outbox worker + cron jobs running.
- [ ] Backup and restore rehearsed; rollback to previous tag rehearsed.
- [ ] Live smoke + Appendix B pass on the deployed URL.

### Documentation
- [ ] README quick start verified on a clean machine.
- [ ] API contract, database, architecture, user guide, operations, security, performance, test report, known-limitations all present and accurate.
- [ ] Acceptance traceability table complete; CHANGELOG written.

### Git Repository Cleanliness
- [ ] `git status` clean; no untracked build/storage/log files.
- [ ] `git ls-files | grep -E "\.env$|\.pem$|\.key$|node_modules|vendor/|storage/private"` returns nothing.
- [ ] `.env.example` lists every variable used and contains no real values.
- [ ] Every milestone has a tag `m<phase>.<n>`; every phase has `phase-<N>-complete`; release tagged `v1.0.0`.
- [ ] `main` contains only merged, passing phases; no leftover debug code/TODO without an issue.
- [ ] Repository access reviewed (collaborators, deploy keys) and any temporary credentials revoked.

---

## Appendix – Quick Command Reference

```bash
# start everything (dev)
docker compose up -d
cd backend && composer install && php bin/migrate.php up && psql … -f ../db/seed/dev_seed.sql
php -S 127.0.0.1:8000 -t public public/index.php        # API
php bin/send-outbox.php --loop                           # email worker (separate terminal)
cd ../frontend && npm ci && npm run dev                  # UI on :5173; Mailpit UI on :8025

# verify
bash scripts/verify.sh            # fast gate used before every commit
bash scripts/verify-full.sh       # + concurrency + E2E, before every phase merge

# git checkpoint (every milestone)
git status && git add . && git commit -m "<type>(<scope>): <what> (M<x.y>)" \
  && git push origin <phase-branch> && git tag -a m<x.y> -m "M<x.y>" && git push origin m<x.y>
```
