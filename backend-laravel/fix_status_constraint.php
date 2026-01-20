<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    print "Dropping old check_status constraint...\n";
    DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
    
    print "Adding new check_status constraint with all statuses...\n";
    DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled'))");
    
    print "SUCCESS! Constraint updated.\n";
    
    // Verify
    $result = DB::select("SELECT constraint_name, check_clause FROM information_schema.check_constraints WHERE constraint_name = 'check_status' AND table_name = 'supply_requests'");
    if (!empty($result)) {
        print "Constraint verified: " . $result[0]->check_clause . "\n";
    }
    
} catch (\Exception $e) {
    print "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

