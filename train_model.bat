@echo off
REM Training Script Helper - Uses direct Python path (bypasses PowerShell execution policy)
echo ============================================
echo CatBoost Model Training
echo ============================================
echo.

REM Change to project root directory
cd /d "%~dp0"

REM Use Python directly from virtual environment (bypasses activation)
set PYTHON_PATH=%~dp0backend-laravel\ml_api_env\Scripts\python.exe

echo Checking Python at: %PYTHON_PATH%
if not exist "%PYTHON_PATH%" (
    echo.
    echo ERROR: Python not found at:
    echo   %PYTHON_PATH%
    echo.
    echo Please check your virtual environment location.
    pause
    exit /b 1
)

echo.
echo Running CatBoost model training...
echo.

"%PYTHON_PATH%" train_lifespan_model.py

if errorlevel 1 (
    echo.
    echo ERROR: Training failed. Check the error messages above.
    pause
    exit /b 1
)

echo.
echo ============================================
echo Training completed!
echo ============================================
echo.
echo If model was created, check for: catboost_lifespan_model.cbm
echo Copy it to the same directory as ml_api_server.py
echo.
pause

