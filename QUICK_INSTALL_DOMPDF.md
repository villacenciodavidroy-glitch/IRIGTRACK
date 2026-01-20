# 🚀 Quick Fix: Install DOMPDF

## The Problem
PDF export is failing because DOMPDF library is not installed.

## The Solution

**Open PowerShell or Command Prompt and run these commands:**

```bash
cd backend-laravel
composer require dompdf/dompdf
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

## Verify Installation

After running the commands above, verify DOMPDF is installed:

```bash
cd backend-laravel
php check_dompdf.php
```

You should see:
```
✓ FOUND
✓ FOUND
SUCCESS: DOMPDF is installed and ready to use!
```

## Restart Server

**IMPORTANT:** After installation, restart your Laravel server:

1. Stop the server (press Ctrl+C)
2. Start it again: `php artisan serve`

## Test

Try exporting PDF again - it should work now!

## Still Having Issues?

If you still get errors:

1. Check if composer is working:
   ```bash
   composer --version
   ```

2. Check PHP version (needs 7.4+):
   ```bash
   php -v
   ```

3. Check Laravel logs:
   ```bash
   cd backend-laravel
   tail -n 50 storage/logs/laravel.log
   ```

