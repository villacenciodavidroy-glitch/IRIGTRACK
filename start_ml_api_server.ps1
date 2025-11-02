# PowerShell script to start the ML API Server
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "Starting Python ML API Server" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Check if Python is available
try {
    $pythonVersion = py --version 2>&1
    Write-Host "✓ Python found: $pythonVersion" -ForegroundColor Green
} catch {
    Write-Host "✗ ERROR: Python is not installed or not in PATH" -ForegroundColor Red
    Write-Host "Please install Python 3.8+ from https://www.python.org/" -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

# Change to script directory
Set-Location $PSScriptRoot

# Check if requirements are installed
Write-Host "Checking Python dependencies..." -ForegroundColor Yellow
try {
    py -c "import flask, catboost" 2>&1 | Out-Null
    Write-Host "✓ Dependencies are installed" -ForegroundColor Green
} catch {
    Write-Host "⚠ Some dependencies may be missing. Installing..." -ForegroundColor Yellow
    py -m pip install -r requirements_ml_api.txt
    if ($LASTEXITCODE -ne 0) {
        Write-Host "✗ ERROR: Failed to install dependencies" -ForegroundColor Red
        Read-Host "Press Enter to exit"
        exit 1
    }
}

Write-Host ""
Write-Host "Starting ML API Server..." -ForegroundColor Green
Write-Host "Server will run on: http://127.0.0.1:5000" -ForegroundColor Cyan
Write-Host "Press Ctrl+C to stop the server" -ForegroundColor Yellow
Write-Host ""

# Start the Flask server
py ml_api_server.py

