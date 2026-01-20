# Installing DOMPDF for PDF Export

If you're getting a 500 error when trying to export PDFs, DOMPDF might not be installed.

## Installation Steps

1. Navigate to the backend-laravel directory:
   ```bash
   cd backend-laravel
   ```

2. Install DOMPDF:
   ```bash
   composer require dompdf/dompdf
   ```

3. Run composer dump-autoload:
   ```bash
   composer dump-autoload
   ```

4. Clear Laravel cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

5. Restart your Laravel server if it's running.

## Verify Installation

You can verify DOMPDF is installed by checking:
```bash
composer show dompdf/dompdf
```

Or check if the vendor directory exists:
```bash
ls vendor/dompdf/dompdf
```

## Troubleshooting

If you still get errors after installation:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Make sure PHP version is 8.2 or higher
3. Ensure all composer dependencies are installed: `composer install`

