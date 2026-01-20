<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Checking supply_requests table ===\n\n";

try {
    // Check if table exists
    $exists = Schema::hasTable('supply_requests');
    echo "Table exists: " . ($exists ? "YES" : "NO") . "\n\n";
    
    if ($exists) {
        // Get column listing
        $columns = Schema::getColumnListing('supply_requests');
        echo "Columns (" . count($columns) . "):\n";
        foreach ($columns as $column) {
            echo "  - $column\n";
        }
        echo "\n";
        
        // Get row count
        $count = DB::table('supply_requests')->count();
        echo "Row count: $count\n";
    } else {
        echo "Table does not exist. Attempting to create...\n\n";
        
        // Try to run migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2025_12_06_000000_create_supply_requests_table.php',
            '--force' => true
        ]);
        
        echo Artisan::output();
        
        // Check again
        $exists = Schema::hasTable('supply_requests');
        echo "\nAfter migration - Table exists: " . ($exists ? "YES" : "NO") . "\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
