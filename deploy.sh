#!/bin/bash

cd /var/www/nia-system
git pull origin main

cd frontend-vue
npm install
npm run build

cd ../backend-laravel
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo supervisorctl restart laravel-app

echo "Deployment complete!"

