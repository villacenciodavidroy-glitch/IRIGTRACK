<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$output = [];

try {
    $output[] = "=== Adding assigned columns to supply_requests table ===";
    $output[] = "";
    
    // Check if table exists
    if (!Schema::hasTable('supply_requests')) {
        $output[] = "ERROR: Table supply_requests does not exist!";
        file_put_contents(__DIR__ . '/add_columns_result.txt', implode("\n", $output));
        echo implode("\n", $output);
        exit(1);
    }
    
    $output[] = "✓ Table exists";
    
    // Check existing columns
    $columns = Schema::getColumnListing('supply_requests');
    $output[] = "Current columns: " . implode(', ', $columns);
    $output[] = "";
    
    // Add assigned_to_admin_id if it doesn't exist
    if (!in_array('assigned_to_admin_id', $columns)) {
        $output[] = "Adding assigned_to_admin_id column...";
        DB::statement("ALTER TABLE supply_requests ADD COLUMN assigned_to_admin_id BIGINT NULL");
        DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT fk_supply_requests_assigned_to_admin FOREIGN KEY (assigned_to_admin_id) REFERENCES users(id) ON DELETE SET NULL");
        $output[] = "✓ assigned_to_admin_id column added";
    } else {
        $output[] = "✓ assigned_to_admin_id column already exists";
    }
    
    // Add assigned_at if it doesn't exist
    if (!in_array('assigned_at', $columns)) {
        $output[] = "Adding assigned_at column...";
        DB::statement("ALTER TABLE supply_requests ADD COLUMN assigned_at TIMESTAMP NULL");
        $output[] = "✓ assigned_at column added";
    } else {
        $output[] = "✓ assigned_at column already exists";
    }
    
    // Add admin_accepted_at if it doesn't exist
    if (!in_array('admin_accepted_at', $columns)) {
        $output[] = "Adding admin_accepted_at column...";
        DB::statement("ALTER TABLE supply_requests ADD COLUMN admin_accepted_at TIMESTAMP NULL");
        $output[] = "✓ admin_accepted_at column added";
    } else {
        $output[] = "✓ admin_accepted_at column already exists";
    }
    
    $output[] = "";
    $output[] = "=== SUCCESS! All columns added ===";
    
} catch (\Exception $e) {
    $output[] = "";
    $output[] = "ERROR: " . $e->getMessage();
    $output[] = "File: " . $e->getFile();
    $output[] = "Line: " . $e->getLine();
}

file_put_contents(__DIR__ . '/add_columns_result.txt', implode("\n", $output));
echo implode("\n", $output);
