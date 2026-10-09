$ErrorActionPreference = "Stop"

Write-Host "=== [1/4] Validating Backend Composer ===" -ForegroundColor Cyan
Set-Location "$PSScriptRoot/../backend"
& "C:\xampp\php\php.exe" "C:\xampp\php\composer.phar" validate --strict

Write-Host "`n=== [2/4] Running Backend PHPUnit Tests ===" -ForegroundColor Cyan
& "C:\xampp\php\php.exe" vendor/bin/phpunit

Write-Host "`n=== [3/4] Running Frontend Tests ===" -ForegroundColor Cyan
Set-Location "$PSScriptRoot/../frontend"
npm run test:run

Write-Host "`n=== [4/4] Building Frontend Production Bundle ===" -ForegroundColor Cyan
npm run build

Set-Location "$PSScriptRoot/.."
Write-Host "`n=== ALL VERIFICATION CHECKS PASSED ===" -ForegroundColor Green
