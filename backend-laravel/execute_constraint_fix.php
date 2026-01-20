<?php
// Simple script to fix the constraint - outputs everything to console
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

print "Starting constraint fix...\n";

try {
    // Drop old constraint
    print "Step 1: Dropping old constraint...\n";
    DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
    print "✓ Old constraint dropped\n";
    
    // Add new constraint
    print "Step 2: Adding new constraint...\n";
    $sql = "ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled'))";
    DB::statement($sql);
    print "✓ New constraint added\n";
    
    // Verify
    print "Step 3: Verifying constraint...\n";
    $result = DB::select("SELECT check_clause FROM information_schema.check_constraints WHERE constraint_name = 'check_status' AND table_schema = 'public'");
    if (!empty($result)) {
        print "✓ Constraint verified: " . $result[0]->check_clause . "\n";
    }
    
    print "\n=== SUCCESS! Constraint has been fixed ===\n";
    print "You can now approve supply requests.\n";
    
} catch (\Exception $e) {
    print "\n=== ERROR ===\n";
    print "Message: " . $e->getMessage() . "\n";
    print "File: " . $e->getFile() . "\n";
    print "Line: " . $e->getLine() . "\n";
    print "\nPlease run the SQL directly in PostgreSQL:\n";
    print "ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status;\n";
    print "ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled'));\n";
    exit(1);
}

