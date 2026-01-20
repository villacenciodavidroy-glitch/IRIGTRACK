<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$output = [];

try {
    $exists = Schema::hasTable('supply_request_messages');
    $output[] = "Table exists: " . ($exists ? "YES" : "NO");
    
    if ($exists) {
        $columns = Schema::getColumnListing('supply_request_messages');
        $output[] = "Columns (" . count($columns) . "):";
        foreach ($columns as $col) {
            $output[] = "  - $col";
        }
        
        $count = DB::table('supply_request_messages')->count();
        $output[] = "Row count: $count";
    } else {
        $output[] = "Creating table...";
        require __DIR__ . '/create_messages_table_now.php';
        
        $exists = Schema::hasTable('supply_request_messages');
        $output[] = "After creation - Table exists: " . ($exists ? "YES" : "NO");
    }
    
} catch (\Exception $e) {
    $output[] = "ERROR: " . $e->getMessage();
    $output[] = "File: " . $e->getFile();
    $output[] = "Line: " . $e->getLine();
}

file_put_contents(__DIR__ . '/messages_table_status.txt', implode("\n", $output));
echo implode("\n", $output);
