Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Installing DOMPDF for PDF Export" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Set-Location -Path "$PSScriptRoot\backend-laravel"

Write-Host "Step 1: Installing DOMPDF package..." -ForegroundColor Yellow
Write-Host ""
composer require dompdf/dompdf --no-interaction
if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "ERROR: Failed to install DOMPDF" -ForegroundColor Red
    Write-Host "Please check if composer is installed and working." -ForegroundColor Red
    Write-Host "Run: composer --version" -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "Step 2: Updating autoloader..." -ForegroundColor Yellow
composer dump-autoload
if ($LASTEXITCODE -ne 0) {
    Write-Host "WARNING: Autoloader update failed, but continuing..." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Step 3: Clearing Laravel caches..." -ForegroundColor Yellow
php artisan config:clear
php artisan cache:clear
php artisan route:clear

Write-Host ""
Write-Host "Step 4: Verifying installation..." -ForegroundColor Yellow
if (Test-Path "vendor\dompdf\dompdf\src\Dompdf.php") {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "SUCCESS: DOMPDF is installed!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Please RESTART your Laravel server:" -ForegroundColor Yellow
    Write-Host "1. Stop the server (Ctrl+C)" -ForegroundColor Yellow
    Write-Host "2. Start it again: php artisan serve" -ForegroundColor Yellow
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "ERROR: DOMPDF installation verification failed" -ForegroundColor Red
    Write-Host "Please check vendor\dompdf folder" -ForegroundColor Red
    Write-Host ""
}

Read-Host "Press Enter to exit"

