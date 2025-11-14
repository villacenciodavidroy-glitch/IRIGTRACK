@echo off
REM Fix PowerShell Execution Policy (Run as Administrator)
echo ============================================
echo PowerShell Execution Policy Fix
echo ============================================
echo.
echo This will set PowerShell to allow local scripts to run.
echo You may need to run this as Administrator.
echo.
pause

powershell -Command "Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force"

echo.
echo Done! You can now use PowerShell activation scripts.
echo Try: backend-laravel\ml_api_env\Scripts\Activate.ps1
echo.
pause

