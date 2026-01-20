<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

try {
    // Check if column exists
    $columnExists = DB::select("
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'items' 
        AND column_name = 'maintenance_reason'
    ");
    
    if (empty($columnExists)) {
        // Add the column
        DB::statement('ALTER TABLE items ADD COLUMN maintenance_reason TEXT NULL');
        echo "✓ Column 'maintenance_reason' added successfully to 'items' table.\n";
    } else {
        echo "✓ Column 'maintenance_reason' already exists in 'items' table.\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

