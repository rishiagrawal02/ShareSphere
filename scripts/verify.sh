#!/usr/bin/env bash
set -e

echo "=== [1/4] Validating Backend Composer ==="
cd backend
composer validate --strict
cd ..

echo "=== [2/4] Running Backend PHPUnit Tests ==="
cd backend
vendor/bin/phpunit
cd ..

echo "=== [3/4] Running Frontend Tests ==="
cd frontend
npm run test:run
cd ..

echo "=== [4/4] Building Frontend Production Bundle ==="
cd frontend
npm run build
cd ..

echo "=== ALL VERIFICATION CHECKS PASSED (Phase 0 Baseline) ==="
