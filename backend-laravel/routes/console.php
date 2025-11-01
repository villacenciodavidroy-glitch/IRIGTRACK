<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CheckLowStockJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the low stock check job to run every 30 seconds (for testing)
// Change back to ->dailyAt('06:00') for production
Schedule::job(new CheckLowStockJob)->everyThirtySeconds();
