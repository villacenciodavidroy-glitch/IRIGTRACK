@echo off
echo ========================================
echo Add 2024 Usage Data for Supply Items
echo ========================================
echo.

cd backend-laravel

echo Running artisan command to add 2024 usage data...
echo.

php artisan usage:add-forecast-data --auto-detect --fill-missing --force

echo.
echo Done!
pause
