# Fix PowerShell Execution Policy Issue

## Problem
PowerShell is blocking script execution due to security policies. This prevents activating the Python virtual environment normally.

## Quick Solution (Recommended)
**Use the batch file instead:** Simply double-click `train_model.bat` - it bypasses PowerShell entirely!

## Alternative Solutions

### Option 1: Fix PowerShell Execution Policy (One-time setup)

Run PowerShell **as Administrator** and execute:

```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

This allows local scripts to run. Then you can use:
```powershell
.\backend-laravel\ml_api_env\Scripts\Activate.ps1
```

### Option 2: Use activate.bat (Command Prompt)

Instead of PowerShell, use Command Prompt:
```cmd
backend-laravel\ml_api_env\Scripts\activate.bat
python train_lifespan_model.py
```

### Option 3: Use Python Directly (No Activation Needed)

The `train_model.bat` file uses Python directly, so you don't need to activate:
```cmd
backend-laravel\ml_api_env\Scripts\python.exe train_lifespan_model.py
```

## Recommended Approach

**Just double-click `train_model.bat`** - it handles everything automatically and doesn't require PowerShell!

