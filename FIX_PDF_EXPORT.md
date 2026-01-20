# Fix PDF Export 500 Error

## Problem
Getting 500 Internal Server Error when trying to export PDF for maintenance records.

## Root Cause
DOMPDF library is not installed in the backend Laravel application.

## Solution

### Step 1: Install DOMPDF
Open PowerShell or Command Prompt and run:

```bash
cd backend-laravel
composer require dompdf/dompdf
```

### Step 2: Verify Installation
Check if DOMPDF is installed:

```bash
composer show dompdf/dompdf
```

### Step 3: Clear Cache
Clear Laravel cache:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Step 4: Restart Server
Restart your Laravel development server if it's running.

### Step 5: Test
Try exporting PDF again. The error message should now show the actual issue if DOMPDF is still not working.

## Alternative: Check Laravel Logs

If the error persists, check the Laravel logs:

```bash
cd backend-laravel
tail -n 50 storage/logs/laravel.log
```

Look for error messages related to DOMPDF.

## Verification

After installation, you should see DOMPDF in:
- `backend-laravel/vendor/dompdf/dompdf/`
- `backend-laravel/composer.json` (should have `"dompdf/dompdf": "^2.0"`)

## Note

The frontend has been updated to show the actual error message from the backend, so you'll now see a more helpful error message if DOMPDF is not installed.

