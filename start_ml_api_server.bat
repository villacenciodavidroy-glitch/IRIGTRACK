@echo off
echo ============================================
echo Starting Python ML API Server
echo ============================================
echo.

REM Check if Python is available
py --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    echo Please install Python 3.8+ from https://www.python.org/
    pause
    exit /b 1
)

REM Change to project directory
cd /d "%~dp0"

REM Check if requirements are installed (quick check)
echo Checking Python dependencies...
py -c "import flask, catboost" >nul 2>&1
if errorlevel 1 (
    echo.
    echo WARNING: Some dependencies may be missing.
    echo Installing requirements...
    py -m pip install -r requirements_ml_api.txt
    if errorlevel 1 (
        echo.
        echo ERROR: Failed to install dependencies
        pause
        exit /b 1
    )
)

echo.
echo Starting ML API Server...
echo Server will run on: http://127.0.0.1:5000
echo Press Ctrl+C to stop the server
echo.

REM Start the Flask server
py ml_api_server.py

pause

