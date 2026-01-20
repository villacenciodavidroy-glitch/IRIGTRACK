<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$output = [];

try {
    $output[] = "=== Fixing supply_requests status constraint ===";
    $output[] = "";
    
    // Check if table exists
    if (!Schema::hasTable('supply_requests')) {
        $output[] = "ERROR: Table supply_requests does not exist!";
        file_put_contents(__DIR__ . '/constraint_fix_result.txt', implode("\n", $output));
        echo implode("\n", $output);
        exit(1);
    }
    
    $output[] = "✓ Table exists";
    
    // Drop old constraint
    $output[] = "Dropping old constraint...";
    DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
    $output[] = "✓ Old constraint dropped";
    
    // Add new constraint
    $output[] = "Adding new constraint with all statuses...";
    DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled'))");
    $output[] = "✓ New constraint added";
    
    // Verify constraint
    $constraints = DB::select("
        SELECT constraint_name, check_clause 
        FROM information_schema.check_constraints 
        WHERE constraint_name = 'check_status' 
        AND constraint_schema = 'public'
    ");
    
    if (!empty($constraints)) {
        $output[] = "";
        $output[] = "Constraint verified:";
        $output[] = $constraints[0]->check_clause;
    }
    
    // Check and add columns if needed
    $columns = Schema::getColumnListing('supply_requests');
    $output[] = "";
    $output[] = "Checking columns...";
    
    if (!in_array('assigned_to_admin_id', $columns)) {
        $output[] = "Adding missing columns...";
        Schema::table('supply_requests', function ($table) {
            $table->foreignId('assigned_to_admin_id')->nullable()->after('forwarded_to_admin_id')->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable()->after('assigned_to_admin_id');
            $table->timestamp('admin_accepted_at')->nullable()->after('assigned_at');
        });
        $output[] = "✓ Columns added";
    } else {
        $output[] = "✓ All columns exist";
    }
    
    $output[] = "";
    $output[] = "=== SUCCESS! Constraint fixed ===";
    
} catch (\Exception $e) {
    $output[] = "";
    $output[] = "ERROR: " . $e->getMessage();
    $output[] = "File: " . $e->getFile();
    $output[] = "Line: " . $e->getLine();
}

file_put_contents(__DIR__ . '/constraint_fix_result.txt', implode("\n", $output));
echo implode("\n", $output);

