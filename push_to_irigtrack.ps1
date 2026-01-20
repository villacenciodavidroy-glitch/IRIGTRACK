# Script to push code to IRIGTRACK repository
# Usage: .\push_to_irigtrack.ps1 -Token "YOUR_GITHUB_TOKEN"

param(
    [Parameter(Mandatory=$true)]
    [string]$Token
)

Write-Host "Updating remote URL with token..." -ForegroundColor Yellow

# Update the remote URL with the token
$username = "villacenciodavidroy-glitch"
$remoteUrl = "https://${username}:${Token}@github.com/villacenciodavidroy-glitch/IRIGTRACK.git"

git remote set-url irigtrack $remoteUrl

Write-Host "Remote URL updated successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "Pushing to IRIGTRACK repository..." -ForegroundColor Yellow

# Push to the repository
git push irigtrack main

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✓ Successfully pushed all code to IRIGTRACK repository!" -ForegroundColor Green
    Write-Host "Repository URL: https://github.com/villacenciodavidroy-glitch/IRIGTRACK" -ForegroundColor Cyan
} else {
    Write-Host ""
    Write-Host "✗ Push failed. Please check the error message above." -ForegroundColor Red
}
