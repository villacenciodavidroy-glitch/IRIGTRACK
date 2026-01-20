# 🔧 Fix PDF Export - Install DOMPDF

## Quick Fix

**Run these commands in your terminal (PowerShell or Command Prompt):**

```bash
cd backend-laravel
composer require dompdf/dompdf
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

**Or use the provided script:**

**Windows (PowerShell):**
```powershell
cd backend-laravel
.\install_dompdf.ps1
```

**Windows (Command Prompt):**
```cmd
cd backend-laravel
install_dompdf.bat
```

## Verify Installation

After installation, verify DOMPDF is installed:

```bash
cd backend-laravel
composer show dompdf/dompdf
```

You should see package information. If you see "Package not found", the installation failed.

## Restart Server

**IMPORTANT:** After installing, restart your Laravel server:

1. Stop your current Laravel server (Ctrl+C)
2. Start it again: `php artisan serve`

## Test

Try exporting PDF again. It should work now!

## Troubleshooting

If you still get errors:

1. **Check PHP version:** DOMPDF requires PHP 7.4+
   ```bash
   php -v
   ```

2. **Check composer is working:**
   ```bash
   composer --version
   ```

3. **Check Laravel logs:**
   ```bash
   cd backend-laravel
   tail -n 50 storage/logs/laravel.log
   ```

4. **Manual installation check:**
   ```bash
   cd backend-laravel
   dir vendor\dompdf\dompdf
   ```
   If this folder doesn't exist, DOMPDF is not installed.

