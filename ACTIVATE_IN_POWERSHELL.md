# How to Activate Virtual Environment in PowerShell (Current Session)

## Quick Fix for Your Current PowerShell Window

Since you're already in PowerShell and seeing the error, use one of these methods:

### ✅ Method 1: Bypass Execution Policy (Run in your current PowerShell)

**Copy and paste this command:**
```powershell
powershell -ExecutionPolicy Bypass -NoExit -Command "& 'C:\NIA_SystemProject\ml_api_env\Scripts\Activate.ps1'"
```

This will:
- Bypass the execution policy
- Activate your virtual environment
- Keep the window open

---

### ✅ Method 2: Use the Helper Script

**Run this in your current PowerShell:**
```powershell
.\activate_now.ps1
```

---

### ✅ Method 3: Fix Policy Then Restart PowerShell

**Step 1:** Run this in your current PowerShell (may need Admin):
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser -Force
```

**Step 2:** Close PowerShell completely and open a new one

**Step 3:** Then run:
```powershell
.\ml_api_env\Scripts\Activate.ps1
```

---

### ✅ Method 4: Use Batch File (Easiest - No PowerShell Issues!)

**Just open Command Prompt (cmd.exe) and run:**
```cmd
activate_venv.bat
```

Or double-click `activate_venv.bat` in Windows Explorer.

---

## Recommended for Right Now

**In your current PowerShell window, run:**
```powershell
powershell -ExecutionPolicy Bypass -NoExit -Command "& 'C:\NIA_SystemProject\ml_api_env\Scripts\Activate.ps1'"
```

This will work immediately without closing PowerShell!

