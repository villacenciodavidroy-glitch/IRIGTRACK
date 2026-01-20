<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

print "Adding assigned columns to supply_requests table...\n";

try {
    // Check if columns exist
    $columns = Schema::getColumnListing('supply_requests');
    print "Current columns: " . implode(', ', $columns) . "\n\n";
    
    // Add assigned_to_admin_id
    if (!in_array('assigned_to_admin_id', $columns)) {
        print "Adding assigned_to_admin_id...\n";
        DB::statement("ALTER TABLE supply_requests ADD COLUMN assigned_to_admin_id BIGINT NULL");
        print "✓ Column added\n";
        
        // Add foreign key
        print "Adding foreign key constraint...\n";
        try {
            DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT fk_supply_requests_assigned_to_admin FOREIGN KEY (assigned_to_admin_id) REFERENCES users(id) ON DELETE SET NULL");
            print "✓ Foreign key added\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
            print "✓ Foreign key already exists\n";
        }
    } else {
        print "✓ assigned_to_admin_id already exists\n";
    }
    
    // Add assigned_at
    if (!in_array('assigned_at', $columns)) {
        print "Adding assigned_at...\n";
        DB::statement("ALTER TABLE supply_requests ADD COLUMN assigned_at TIMESTAMP NULL");
        print "✓ Column added\n";
    } else {
        print "✓ assigned_at already exists\n";
    }
    
    // Add admin_accepted_at
    if (!in_array('admin_accepted_at', $columns)) {
        print "Adding admin_accepted_at...\n";
        DB::statement("ALTER TABLE supply_requests ADD COLUMN admin_accepted_at TIMESTAMP NULL");
        print "✓ Column added\n";
    } else {
        print "✓ admin_accepted_at already exists\n";
    }
    
    print "\n=== SUCCESS! All columns added ===\n";
    print "You can now assign requests to admins.\n";
    
} catch (\Exception $e) {
    print "\n=== ERROR ===\n";
    print "Message: " . $e->getMessage() . "\n";
    print "File: " . $e->getFile() . "\n";
    print "Line: " . $e->getLine() . "\n";
    print "\nPlease run the SQL directly in PostgreSQL:\n";
    print "See file: ADD_ASSIGNED_COLUMNS.sql\n";
    exit(1);
}
