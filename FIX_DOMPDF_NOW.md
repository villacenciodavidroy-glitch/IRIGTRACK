# 🚨 URGENT: Fix DOMPDF Installation

## The Problem
PDF export is failing because DOMPDF is not installed, even though it's listed in `composer.json`.

## ✅ THE FIX (Run These Commands Now):

**Open PowerShell or Command Prompt and run:**

```bash
cd backend-laravel
composer install
composer dump-autoload
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

## Verify Installation

After running the commands, verify DOMPDF is installed:

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

**IMPORTANT:** After installation, **restart your Laravel server**:

1. Stop the server (press Ctrl+C in the terminal running `php artisan serve`)
2. Start it again: `php artisan serve`

## Test PDF Export

Try exporting PDF again - it should work now!

## If Still Not Working

If you still get errors, check:

1. **Composer is working:**
   ```bash
   composer --version
   ```

2. **PHP version (needs 7.4+):**
   ```bash
   php -v
   ```

3. **Check if DOMPDF folder exists:**
   ```bash
   cd backend-laravel
   dir vendor\dompdf
   ```
   
   If this shows "directory not found", DOMPDF is not installed.

4. **Manual installation:**
   ```bash
   cd backend-laravel
   composer require dompdf/dompdf --no-interaction
   composer dump-autoload
   php artisan config:clear
   ```

