<?php
// Test if constraint was fixed
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Try to update a test record with supply_approved status
    // First, check current constraint
    $constraints = DB::select("
        SELECT check_clause 
        FROM information_schema.check_constraints 
        WHERE constraint_name = 'check_status' 
        AND constraint_schema = 'public'
    ");
    
    if (empty($constraints)) {
        print "ERROR: Constraint not found!\n";
        exit(1);
    }
    
    print "Current constraint: " . $constraints[0]->check_clause . "\n";
    
    // Check if supply_approved is in the constraint
    if (strpos($constraints[0]->check_clause, 'supply_approved') !== false) {
        print "SUCCESS: Constraint includes 'supply_approved'!\n";
        print "The constraint has been fixed. You can now approve requests.\n";
    } else {
        print "ERROR: Constraint does NOT include 'supply_approved'!\n";
        print "Please run: php artisan supply-requests:fix-constraint\n";
        print "Or run the SQL directly in PostgreSQL.\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    print "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

