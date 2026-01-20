@echo off
echo Installing DOMPDF package...
cd backend-laravel
composer require dompdf/dompdf --no-interaction
echo.
echo Running composer dump-autoload...
composer dump-autoload
echo.
echo Clearing Laravel cache...
php artisan config:clear
php artisan cache:clear
php artisan route:clear
echo.
echo Installation complete!
echo Please restart your Laravel server if it's running.
pause

