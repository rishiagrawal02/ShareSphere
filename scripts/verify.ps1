$ErrorActionPreference = "Stop"

Write-Host "=== [1/5] Validating Backend Composer ===" -ForegroundColor Cyan
Set-Location "$PSScriptRoot/../backend"
& "C:\xampp\php\php.exe" "C:\xampp\php\composer.phar" validate --strict

Write-Host "`n=== [2/5] Running Backend Unit Tests ===" -ForegroundColor Cyan
& "C:\xampp\php\php.exe" vendor/bin/phpunit --testsuite Unit --colors=never

Write-Host "`n=== [3/5] Running Backend Integration Tests (incl. role-route matrix) ===" -ForegroundColor Cyan
& "C:\xampp\php\php.exe" vendor/bin/phpunit --testsuite Integration --colors=never

Write-Host "`n=== [4/5] Running Frontend Tests ===" -ForegroundColor Cyan
Set-Location "$PSScriptRoot/../frontend"
npm run test:run

Write-Host "`n=== [5/5] Building Frontend Production Bundle ===" -ForegroundColor Cyan
npm run build

Set-Location "$PSScriptRoot/.."
Write-Host "`n=== ALL VERIFICATION CHECKS PASSED (Phase 18 Baseline) ===" -ForegroundColor Green
