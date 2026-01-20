@echo off
REM Activate Python Virtual Environment (Bypasses PowerShell execution policy)
echo ============================================
echo Activating Python Virtual Environment
echo ============================================
echo.

REM Change to project root directory
cd /d "%~dp0"

REM Check if virtual environment exists
if not exist "ml_api_env\Scripts\activate.bat" (
    echo ERROR: Virtual environment not found at ml_api_env\Scripts\activate.bat
    echo.
    pause
    exit /b 1
)

REM Activate using the batch file (no PowerShell needed)
call ml_api_env\Scripts\activate.bat

echo.
echo Virtual environment activated!
echo You can now run Python commands.
echo Type 'deactivate' to exit the virtual environment.
echo.

REM Keep the command prompt open with the activated environment
cmd /k

