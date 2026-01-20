# Script to verify all code was pushed to GitHub
Write-Host "=== Verifying GitHub Push ===" -ForegroundColor Cyan
Write-Host ""

# Check local commit
Write-Host "1. Checking local commit..." -ForegroundColor Yellow
$localCommit = git log --oneline -1
Write-Host "   Local commit: $localCommit" -ForegroundColor White

# Check remote commit
Write-Host ""
Write-Host "2. Checking remote commit..." -ForegroundColor Yellow
$remoteCommit = git ls-remote irigtrack HEAD
$remoteHash = ($remoteCommit -split '\s+')[0]
Write-Host "   Remote commit: $remoteHash" -ForegroundColor White

# Check if commits match
Write-Host ""
Write-Host "3. Comparing commits..." -ForegroundColor Yellow
$pushedCommit = git log --format="%H" -1 069dfad
if ($remoteHash -eq $pushedCommit) {
    Write-Host "   [OK] Commits match!" -ForegroundColor Green
} else {
    Write-Host "   [X] Commits don't match!" -ForegroundColor Red
}

# Count files in local commit
Write-Host ""
Write-Host "4. Counting files in pushed commit..." -ForegroundColor Yellow
$fileCount = (git ls-tree -r --name-only 069dfad | Measure-Object -Line).Lines
Write-Host "   Files in commit: $fileCount" -ForegroundColor White

# Check what was in the commit
Write-Host ""
Write-Host "5. Commit details:" -ForegroundColor Yellow
git show --stat 069dfad | Select-String "files changed"

# Check for uncommitted changes
Write-Host ""
Write-Host "6. Checking for uncommitted changes..." -ForegroundColor Yellow
$status = git status --porcelain
if ($status) {
    Write-Host "   [WARNING] You have uncommitted changes:" -ForegroundColor Yellow
    $status | ForEach-Object { Write-Host "      $_" -ForegroundColor White }
} else {
    Write-Host "   [OK] Working tree is clean" -ForegroundColor Green
}

# Check for unpushed commits
Write-Host ""
Write-Host "7. Checking for unpushed commits..." -ForegroundColor Yellow
$unpushed = git log irigtrack/main..HEAD --oneline
if ($unpushed) {
    Write-Host "   [WARNING] You have commits not pushed to irigtrack:" -ForegroundColor Yellow
    $unpushed | ForEach-Object { Write-Host "      $_" -ForegroundColor White }
} else {
    Write-Host "   [OK] All commits are pushed" -ForegroundColor Green
}

# Summary
Write-Host ""
Write-Host "=== Summary ===" -ForegroundColor Cyan
Write-Host "Repository URL: https://github.com/villacenciodavidroy-glitch/IRIGTRACK" -ForegroundColor White
Write-Host "Pushed commit: 069dfad (Initial commit: Push all code to IRIGTRACK repository)" -ForegroundColor White
Write-Host "Files pushed: 205 files, 41,657+ insertions" -ForegroundColor White
Write-Host ""
Write-Host "To manually verify, visit:" -ForegroundColor Yellow
Write-Host "https://github.com/villacenciodavidroy-glitch/IRIGTRACK" -ForegroundColor Cyan
