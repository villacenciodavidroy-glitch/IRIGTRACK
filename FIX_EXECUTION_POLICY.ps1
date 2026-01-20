# Fix PowerShell Execution Policy
# Run this script to fix execution policy

Write-Host "============================================" -ForegroundColor Cyan
Write-Host "Fixing PowerShell Execution Policy" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Try to set for CurrentUser first (doesn't need admin)
Write-Host "Attempting to set execution policy for CurrentUser..." -ForegroundColor Yellow

$errorOccurred = $false

try {
    Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force -ErrorAction Stop
    Write-Host "Successfully set execution policy to RemoteSigned for CurrentUser" -ForegroundColor Green
    Write-Host ""
    Write-Host "You can now use:" -ForegroundColor Green
    Write-Host "  .\ml_api_env\Scripts\Activate.ps1" -ForegroundColor White
    Write-Host ""
    Write-Host "Or close and reopen PowerShell for the change to take effect." -ForegroundColor Yellow
} catch {
    $errorOccurred = $true
    Write-Host "Failed to set for CurrentUser. Trying Process scope..." -ForegroundColor Red
    
    try {
        Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope Process -Force -ErrorAction Stop
        Write-Host "Set execution policy for current session only" -ForegroundColor Green
        Write-Host ""
        Write-Host "Note: This only works for this PowerShell session." -ForegroundColor Yellow
        Write-Host "To make it permanent, run PowerShell as Administrator and run:" -ForegroundColor Yellow
        Write-Host "  Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force" -ForegroundColor White
        $errorOccurred = $false
    } catch {
        Write-Host "Could not set execution policy." -ForegroundColor Red
        Write-Host ""
        Write-Host "SOLUTION: Use the batch file instead:" -ForegroundColor Cyan
        Write-Host "  .\activate_venv.bat" -ForegroundColor White
        Write-Host ""
        Write-Host "Or use bypass method:" -ForegroundColor Cyan
        Write-Host "  powershell -ExecutionPolicy Bypass -File .\ml_api_env\Scripts\Activate.ps1" -ForegroundColor White
    }
}

Write-Host ""
Read-Host "Press Enter to exit"

