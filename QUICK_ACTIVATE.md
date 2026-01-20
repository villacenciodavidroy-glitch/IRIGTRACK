# Quick Activation Guide

## If you're getting execution policy errors, use one of these:

### Option 1: Use the batch file (EASIEST - Always works!)
```powershell
.\activate_venv.bat
```

### Option 2: Use bypass method (Works immediately)
```powershell
powershell -ExecutionPolicy Bypass -File .\ml_api_env\Scripts\Activate.ps1
```

### Option 3: Use the new activate script
```powershell
.\activate.ps1
```

### Option 4: Fix policy permanently (then close/reopen PowerShell)
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
```
Then close PowerShell, reopen it, and use:
```powershell
.\ml_api_env\Scripts\Activate.ps1
```

## RECOMMENDED: Just use `.\activate_venv.bat` - it always works!
























