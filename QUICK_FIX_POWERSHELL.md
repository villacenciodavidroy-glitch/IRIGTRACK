# Quick Fix for PowerShell Execution Policy Error

## Immediate Solution

You're seeing this error because PowerShell's execution policy is blocking script execution. Here are **3 quick ways to fix it**:

### ✅ Solution 1: Use Batch File (Easiest - No Fix Needed!)

**Just double-click:** `activate_venv.bat`

This bypasses PowerShell entirely and activates your virtual environment without any policy changes!

---

### ✅ Solution 2: Fix PowerShell Policy (One-Time Setup)

**Run this command in PowerShell** (Right-click PowerShell → Run as Administrator):

```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
```

**Or use the helper script:**
- Double-click `fix_powershell_policy.bat` (may need to run as Administrator)

After this, you can use:
```powershell
.\ml_api_env\Scripts\Activate.ps1
```

---

### ✅ Solution 3: Bypass for Single Command (No Admin Needed)

**Run this in PowerShell** (no admin rights required):

```powershell
powershell -ExecutionPolicy Bypass -File ml_api_env\Scripts\Activate.ps1
```

**Or use the helper script:**
```powershell
powershell -ExecutionPolicy Bypass -File activate_venv.ps1
```

---

## Recommended: Use activate_venv.bat

The **easiest solution** is to simply use `activate_venv.bat` - it doesn't require PowerShell at all and works immediately!

Just double-click the file or run:
```cmd
activate_venv.bat
```

