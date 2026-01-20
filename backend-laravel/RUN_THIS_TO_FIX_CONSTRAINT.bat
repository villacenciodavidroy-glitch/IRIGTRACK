@echo off
echo Fixing supply_requests constraint...
cd /d "%~dp0"
php artisan supply-requests:fix-constraint
pause

