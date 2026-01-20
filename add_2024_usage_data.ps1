Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Add 2024 Usage Data for Supply Items" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Set-Location backend-laravel

Write-Host "Running artisan command to add 2024 usage data..." -ForegroundColor Yellow
Write-Host ""

php artisan usage:add-forecast-data --auto-detect --fill-missing

Write-Host ""
Write-Host "Done!" -ForegroundColor Green
Read-Host "Press Enter to continue"
