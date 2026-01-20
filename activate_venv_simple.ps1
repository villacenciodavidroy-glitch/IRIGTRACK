# Simple activation script - works even with restricted execution policy
# Just run: .\activate_venv_simple.ps1

# Set execution policy for current process only (no admin needed)
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process -Force

# Activate the virtual environment
& "$PSScriptRoot\ml_api_env\Scripts\Activate.ps1"

Write-Host ""
Write-Host "Virtual environment activated!" -ForegroundColor Green
Write-Host "You can now run Python commands." -ForegroundColor Green

