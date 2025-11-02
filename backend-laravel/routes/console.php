<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CheckLowStockJob;
use App\Jobs\CalculateLifespanJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the low stock check job to run every 30 seconds (for testing)
// Change back to ->dailyAt('06:00') for production
Schedule::job(new CheckLowStockJob)->everyThirtySeconds();

// Schedule lifespan calculation job to run every 14 days
// This will calculate and update remaining_years for all items
// Using cron expression: every 14 days (runs on 1st and 15th of each month)
Schedule::job(new CalculateLifespanJob)->cron('0 2 1,15 * *');
