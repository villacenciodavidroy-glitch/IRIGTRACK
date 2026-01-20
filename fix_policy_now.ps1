Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
Write-Host "Execution policy set to RemoteSigned for CurrentUser" -ForegroundColor Green
Write-Host ""
Write-Host "Current execution policy:" -ForegroundColor Cyan
Get-ExecutionPolicy -List | Format-Table
Write-Host ""
Write-Host "You can now close and reopen PowerShell, then use:" -ForegroundColor Yellow
Write-Host "  .\ml_api_env\Scripts\Activate.ps1" -ForegroundColor White

