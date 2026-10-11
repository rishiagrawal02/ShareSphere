#!/usr/bin/env bash
# ShareSphere Verification Script (Phase 18 – M18.1)
# Covers: lint → PHPUnit (unit+integration) → Vitest → frontend build
# Usage: bash scripts/verify.sh [from repo root]
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR/.."

echo "=== [1/5] Validating Backend Composer ==="
cd "$ROOT/backend"
composer validate --strict

echo ""
echo "=== [2/5] Running Backend Unit Tests ==="
vendor/bin/phpunit --testsuite Unit --colors=never

echo ""
echo "=== [3/5] Running Backend Integration Tests (incl. role-route matrix) ==="
vendor/bin/phpunit --testsuite Integration --colors=never

echo ""
echo "=== [4/5] Running Frontend Tests ==="
cd "$ROOT/frontend"
npm run test:run

echo ""
echo "=== [5/5] Building Frontend Production Bundle ==="
npm run build

cd "$ROOT"
echo ""
echo "=== ALL VERIFICATION CHECKS PASSED (Phase 18 Baseline) ==="