Write-Host "Fixing supply_requests constraint..." -ForegroundColor Green
Set-Location $PSScriptRoot
php artisan supply-requests:fix-constraint
Write-Host "Press any key to continue..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

