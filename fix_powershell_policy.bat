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

if errorlevel 1 (
    echo.
    echo ERROR: Failed to set execution policy.
    echo Try running this script as Administrator (Right-click -^> Run as Administrator)
    echo.
    pause
    exit /b 1
)

echo.
echo Done! You can now use PowerShell activation scripts.
echo Try: ml_api_env\Scripts\Activate.ps1
echo Or use: activate_venv.bat (no PowerShell needed)
echo.
pause

