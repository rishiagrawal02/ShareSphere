#!/usr/bin/env bash
# ShareSphere Full Verification Script (Phase 18 – M18.1)
# Extends verify.sh with concurrency tests and Playwright E2E.
# Usage: bash scripts/verify-full.sh [from repo root]
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR/.."

# ─── Run the fast gate first ───────────────────────────────────────────────────
bash "$SCRIPT_DIR/verify.sh"

echo ""
echo "=== [+1] Running Backend Concurrency Tests ==="
cd "$ROOT/backend"
vendor/bin/phpunit --testsuite Concurrency --colors=never

echo ""
echo "=== [+2] Playwright E2E Tests (requires running dev server + DB) ==="
cd "$ROOT/frontend"
if [ -d "e2e" ]; then
    npx playwright test --reporter=list
else
    echo "SKIP: e2e/ directory not found. Install Playwright with M18.3 to enable."
fi

cd "$ROOT"
echo ""
echo "=== ALL FULL VERIFICATION CHECKS PASSED ==="
