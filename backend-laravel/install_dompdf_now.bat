@echo off
echo Installing DOMPDF...
cd /d "%~dp0"
composer require dompdf/dompdf --no-interaction
if %ERRORLEVEL% EQU 0 (
    echo.
    echo DOMPDF installed successfully!
    echo.
    echo Running composer dump-autoload...
    composer dump-autoload
    echo.
    echo Clearing Laravel caches...
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    echo.
    echo Done! Please restart your Laravel server.
) else (
    echo.
    echo ERROR: Failed to install DOMPDF
    echo Please check the error messages above.
    pause
)

