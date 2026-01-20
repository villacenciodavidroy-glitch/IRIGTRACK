Write-Host "Installing DOMPDF package..." -ForegroundColor Green
Set-Location backend-laravel
composer require dompdf/dompdf --no-interaction
Write-Host ""
Write-Host "Running composer dump-autoload..." -ForegroundColor Green
composer dump-autoload
Write-Host ""
Write-Host "Clearing Laravel cache..." -ForegroundColor Green
php artisan config:clear
php artisan cache:clear
php artisan route:clear
Write-Host ""
Write-Host "Installation complete! Please restart your Laravel server if it's running." -ForegroundColor Green

