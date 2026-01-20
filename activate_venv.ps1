# PowerShell script to activate virtual environment (bypasses execution policy)
# Usage: powershell -ExecutionPolicy Bypass -File activate_venv.ps1

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "Activating Python Virtual Environment" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Change to script directory
Set-Location $PSScriptRoot

# Check if virtual environment exists
if (-not (Test-Path "ml_api_env\Scripts\Activate.ps1")) {
    Write-Host "ERROR: Virtual environment not found at ml_api_env\Scripts\Activate.ps1" -ForegroundColor Red
    Write-Host ""
    Read-Host "Press Enter to exit"
    exit 1
}

# Activate the virtual environment by bypassing execution policy
& powershell -ExecutionPolicy Bypass -File "ml_api_env\Scripts\Activate.ps1"

Write-Host ""
Write-Host "Virtual environment activated!" -ForegroundColor Green
Write-Host "You can now run Python commands." -ForegroundColor Green
Write-Host "Type 'deactivate' to exit the virtual environment." -ForegroundColor Yellow
Write-Host ""

