# Fix PowerShell Execution Policy Issue

## Problem
PowerShell is blocking script execution due to security policies. This prevents activating the Python virtual environment normally.

## Quick Solution (Recommended - No PowerShell Needed!)

**Use the activation helper:** Simply double-click `activate_venv.bat` - it bypasses PowerShell entirely and activates your virtual environment!

Or use the PowerShell bypass script:
```powershell
powershell -ExecutionPolicy Bypass -File activate_venv.ps1
```

## Alternative Solutions

### Option 1: Fix PowerShell Execution Policy (One-time setup)

**Easiest way:** Double-click `fix_powershell_policy.bat` (may need to run as Administrator)

Or run PowerShell **as Administrator** and execute:
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
```

This allows local scripts to run. Then you can use:
```powershell
.\ml_api_env\Scripts\Activate.ps1
```

### Option 2: Use activate.bat (Command Prompt)

Instead of PowerShell, use Command Prompt:
```cmd
ml_api_env\Scripts\activate.bat
```

### Option 3: Bypass Execution Policy for Single Command

Run this in PowerShell (no admin needed):
```powershell
powershell -ExecutionPolicy Bypass -File ml_api_env\Scripts\Activate.ps1
```

### Option 4: Use Python Directly (No Activation Needed)

You can use Python directly without activating:
```cmd
ml_api_env\Scripts\python.exe your_script.py
```

## Recommended Approach

**Just double-click `activate_venv.bat`** - it handles everything automatically and doesn't require PowerShell or admin rights!

