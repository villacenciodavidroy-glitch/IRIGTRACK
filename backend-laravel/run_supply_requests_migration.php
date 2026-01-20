<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "Creating supply_requests table...\n";
    
    // Check if table already exists
    if (Schema::hasTable('supply_requests')) {
        echo "Table 'supply_requests' already exists. Skipping creation.\n";
        exit(0);
    }
    
    // Read and execute SQL file
    $sqlFile = __DIR__ . '/create_supply_requests_table.sql';
    if (!file_exists($sqlFile)) {
        echo "SQL file not found: $sqlFile\n";
        exit(1);
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    DB::beginTransaction();
    
    try {
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                // Skip DO blocks for now, handle them separately
                if (stripos($statement, 'DO $$') !== false) {
                    DB::unprepared($statement);
                } else {
                    DB::statement($statement);
                }
            }
        }
        
        DB::commit();
        echo "✓ Table 'supply_requests' created successfully!\n";
        
        // Verify table exists
        if (Schema::hasTable('supply_requests')) {
            $columns = Schema::getColumnListing('supply_requests');
            echo "✓ Table verified. Columns: " . count($columns) . "\n";
        }
        
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
