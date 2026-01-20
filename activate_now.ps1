# Quick activation script - bypasses execution policy
# Usage: Just run this file in PowerShell: .\activate_now.ps1

# Bypass execution policy for this session and activate
powershell -ExecutionPolicy Bypass -NoExit -Command "& 'C:\NIA_SystemProject\ml_api_env\Scripts\Activate.ps1'"

