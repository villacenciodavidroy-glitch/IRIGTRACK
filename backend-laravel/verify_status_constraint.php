<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    print "Checking supply_requests table structure...\n";
    
    // Check if table exists
    if (!Schema::hasTable('supply_requests')) {
        print "ERROR: Table supply_requests does not exist!\n";
        exit(1);
    }
    
    print "Table exists.\n";
    
    // Check constraint
    $constraints = DB::select("
        SELECT constraint_name, check_clause 
        FROM information_schema.check_constraints 
        WHERE constraint_name = 'check_status' 
        AND constraint_schema = 'public'
    ");
    
    if (empty($constraints)) {
        print "WARNING: check_status constraint not found. Creating it...\n";
        DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled'))");
        print "Constraint created.\n";
    } else {
        print "Current constraint: " . $constraints[0]->check_clause . "\n";
        
        // Check if it includes supply_approved
        if (strpos($constraints[0]->check_clause, 'supply_approved') === false) {
            print "Constraint does not include 'supply_approved'. Updating...\n";
            DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT check_status");
            DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled'))");
            print "Constraint updated.\n";
        } else {
            print "Constraint is correct and includes 'supply_approved'.\n";
        }
    }
    
    // Check columns
    $columns = Schema::getColumnListing('supply_requests');
    print "Columns: " . implode(', ', $columns) . "\n";
    
    if (!in_array('assigned_to_admin_id', $columns)) {
        print "WARNING: assigned_to_admin_id column not found. Adding columns...\n";
        Schema::table('supply_requests', function ($table) {
            $table->foreignId('assigned_to_admin_id')->nullable()->after('forwarded_to_admin_id')->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable()->after('assigned_to_admin_id');
            $table->timestamp('admin_accepted_at')->nullable()->after('assigned_at');
        });
        print "Columns added.\n";
    } else {
        print "All required columns exist.\n";
    }
    
    print "SUCCESS! Everything is configured correctly.\n";
    
} catch (\Exception $e) {
    print "ERROR: " . $e->getMessage() . "\n";
    print "File: " . $e->getFile() . "\n";
    print "Line: " . $e->getLine() . "\n";
    exit(1);
}

