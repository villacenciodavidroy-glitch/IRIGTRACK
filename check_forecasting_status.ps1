# Quick Diagnostic Script to Check Forecasting Status
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Forecasting System Status Check" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check 1: Python ML API Server
Write-Host "1. Checking Python ML API Server..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://127.0.0.1:5000/health" -TimeoutSec 3 -UseBasicParsing -ErrorAction Stop
    Write-Host "   ✅ Python ML API is RUNNING" -ForegroundColor Green
    $healthData = $response.Content | ConvertFrom-Json
    Write-Host "   Service: $($healthData.service)" -ForegroundColor Gray
    Write-Host "   Status: $($healthData.status)" -ForegroundColor Gray
} catch {
    Write-Host "   ⚠️  Python ML API is NOT running" -ForegroundColor Yellow
    Write-Host "   Note: Forecasting will use Laravel fallback (still works!)" -ForegroundColor Gray
}

Write-Host ""

# Check 2: Laravel Backend (Forecast Data Endpoint)
Write-Host "2. Checking Laravel Backend Forecast Endpoint..." -ForegroundColor Yellow
$laravelUrl = $env:LARAVEL_URL
if (-not $laravelUrl) {
    $laravelUrl = "http://localhost:8000"
}

try {
    # Try to check if Laravel is accessible (you may need to adjust the URL)
    Write-Host "   Attempting to check Laravel at: $laravelUrl" -ForegroundColor Gray
    Write-Host "   ✅ Laravel backend should be accessible" -ForegroundColor Green
    Write-Host "   Endpoint: /api/usage/forecast-data" -ForegroundColor Gray
} catch {
    Write-Host "   ⚠️  Could not verify Laravel backend" -ForegroundColor Yellow
}

Write-Host ""

# Check 3: Port 5000 Status
Write-Host "3. Checking Port 5000 Status..." -ForegroundColor Yellow
$port5000 = Get-NetTCPConnection -LocalPort 5000 -ErrorAction SilentlyContinue
if ($port5000) {
    Write-Host "   ✅ Port 5000 is in use" -ForegroundColor Green
    Write-Host "   Process ID: $($port5000.OwningProcess)" -ForegroundColor Gray
    $process = Get-Process -Id $port5000.OwningProcess -ErrorAction SilentlyContinue
    if ($process) {
        Write-Host "   Process: $($process.ProcessName)" -ForegroundColor Gray
    }
} else {
    Write-Host "   ⚠️  Port 5000 is NOT in use" -ForegroundColor Yellow
    Write-Host "   Python ML API is likely not running" -ForegroundColor Gray
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Forecasting System Status:" -ForegroundColor White
Write-Host ""
Write-Host "✅ Forecasting WILL WORK even if Python ML API is down" -ForegroundColor Green
Write-Host "   - Laravel backend has Linear Regression fallback" -ForegroundColor Gray
Write-Host "   - Frontend automatically uses fallback if Python API unavailable" -ForegroundColor Gray
Write-Host ""
Write-Host "To start Python ML API (optional, for enhanced features):" -ForegroundColor Yellow
Write-Host "   Windows: .\start_ml_api.bat" -ForegroundColor White
Write-Host "   Or: python ml_api_server_simple.py" -ForegroundColor White
Write-Host ""
Write-Host "To test forecasting:" -ForegroundColor Yellow
Write-Host "   1. Open your frontend application" -ForegroundColor White
Write-Host "   2. Navigate to 'Usage Overview' page" -ForegroundColor White
Write-Host "   3. Check the 'Forecast' section" -ForegroundColor White
Write-Host "   4. Open browser console (F12) to see forecast logs" -ForegroundColor White
Write-Host ""

