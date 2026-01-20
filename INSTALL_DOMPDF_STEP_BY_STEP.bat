@echo off
echo ========================================
echo Installing DOMPDF for PDF Export
echo ========================================
echo.

cd /d "%~dp0backend-laravel"

echo Step 1: Installing DOMPDF package...
echo.
composer require dompdf/dompdf --no-interaction
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERROR: Failed to install DOMPDF
    echo Please check if composer is installed and working.
    echo Run: composer --version
    pause
    exit /b 1
)

echo.
echo Step 2: Updating autoloader...
composer dump-autoload
if %ERRORLEVEL% NEQ 0 (
    echo WARNING: Autoloader update failed, but continuing...
)

echo.
echo Step 3: Clearing Laravel caches...
php artisan config:clear
php artisan cache:clear
php artisan route:clear

echo.
echo Step 4: Verifying installation...
if exist "vendor\dompdf\dompdf\src\Dompdf.php" (
    echo.
    echo ========================================
    echo SUCCESS: DOMPDF is installed!
    echo ========================================
    echo.
    echo Please RESTART your Laravel server:
    echo 1. Stop the server (Ctrl+C)
    echo 2. Start it again: php artisan serve
    echo.
) else (
    echo.
    echo ERROR: DOMPDF installation verification failed
    echo Please check vendor\dompdf folder
    echo.
)

pause

