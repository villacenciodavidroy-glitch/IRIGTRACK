<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Verifying supply_requests table ===\n\n";

try {
    if (!Schema::hasTable('supply_requests')) {
        echo "❌ ERROR: Table 'supply_requests' does NOT exist!\n";
        exit(1);
    }
    
    echo "✓ Table 'supply_requests' exists!\n\n";
    
    // Get all columns
    $columns = Schema::getColumnListing('supply_requests');
    echo "Columns (" . count($columns) . "):\n";
    foreach ($columns as $col) {
        echo "  ✓ $col\n";
    }
    
    echo "\n=== Required Columns Check ===\n";
    
    $requiredColumns = [
        'id',
        'item_id',
        'quantity',
        'urgency_level',
        'status',
        'requested_by_user_id',
        'approved_by',
        'forwarded_to_admin_id',
        'admin_comments',
        'approved_at',
        'fulfilled_at',
        'created_at',
        'updated_at'
    ];
    
    $optionalColumns = [
        'request_number',
        'rejection_reason',
        'fulfillment_notes',
        'delivery_location',
        'expected_delivery_date',
        'cancellation_reason',
        'fulfilled_by',
        'rejected_at',
        'cancelled_at'
    ];
    
    $missingRequired = [];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            $missingRequired[] = $col;
            echo "  ❌ Missing required: $col\n";
        } else {
            echo "  ✓ Required: $col\n";
        }
    }
    
    echo "\n=== Optional Columns Check ===\n";
    foreach ($optionalColumns as $col) {
        if (in_array($col, $columns)) {
            echo "  ✓ Optional: $col\n";
        } else {
            echo "  ⚠ Missing optional: $col (can be added later)\n";
        }
    }
    
    // Check indexes
    echo "\n=== Checking Indexes ===\n";
    try {
        $indexes = DB::select("
            SELECT indexname 
            FROM pg_indexes 
            WHERE tablename = 'supply_requests'
        ");
        echo "Found " . count($indexes) . " indexes:\n";
        foreach ($indexes as $idx) {
            echo "  ✓ " . $idx->indexname . "\n";
        }
    } catch (\Exception $e) {
        echo "  ⚠ Could not check indexes: " . $e->getMessage() . "\n";
    }
    
    // Check foreign keys
    echo "\n=== Checking Foreign Keys ===\n";
    try {
        $fks = DB::select("
            SELECT conname, confrelid::regclass as foreign_table
            FROM pg_constraint 
            WHERE conrelid = 'supply_requests'::regclass 
            AND contype = 'f'
        ");
        echo "Found " . count($fks) . " foreign keys:\n";
        foreach ($fks as $fk) {
            echo "  ✓ " . $fk->conname . " -> " . $fk->foreign_table . "\n";
        }
    } catch (\Exception $e) {
        echo "  ⚠ Could not check foreign keys: " . $e->getMessage() . "\n";
    }
    
    // Test if we can query the table
    echo "\n=== Testing Table Access ===\n";
    try {
        $count = DB::table('supply_requests')->count();
        echo "✓ Can query table successfully!\n";
        echo "  Current records: $count\n";
    } catch (\Exception $e) {
        echo "❌ Cannot query table: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    if (empty($missingRequired)) {
        echo "\n✅ SUCCESS! Table is ready to use!\n";
        echo "All required columns are present.\n";
    } else {
        echo "\n⚠ WARNING: Missing required columns!\n";
        echo "Please run the migration to add missing columns.\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
