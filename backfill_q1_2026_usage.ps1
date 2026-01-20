# Backfill Q1 2026 Usage Data Script
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Backfill Q1 2026 Usage Data" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Set-Location backend-laravel

if (Test-Path "backfill_q1_2026_usage.php") {
    php backfill_q1_2026_usage.php
} else {
    Write-Host "Error: backfill_q1_2026_usage.php not found!" -ForegroundColor Red
    Write-Host "Make sure you're in the project root directory." -ForegroundColor Yellow
    exit 1
}

Set-Location ..

Write-Host ""
Write-Host "Press any key to continue..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

