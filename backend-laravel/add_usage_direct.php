<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Adding usage data directly...\n";

// Based on your database: items 69, 71, 72
// Item 69 has: Q1-Q4 2025 (needs Q1-Q4 2024)
// Item 71 has: Q4 2025 (needs Q1-Q4 2024 + Q1-Q3 2025)
// Item 72 has: Q4 2025 (needs Q1-Q4 2024 + Q1-Q3 2025)

$records = [];

// Item 69 - Add 2024 data (based on existing Q1-Q4 2025: 10, 12, 11, 166)
$records[] = ['item_id' => 69, 'period' => 'Q1 2024', 'usage' => 9, 'stock_start' => 120, 'stock_end' => 111, 'restocked' => false, 'restock_qty' => 0];
$records[] = ['item_id' => 69, 'period' => 'Q2 2024', 'usage' => 11, 'stock_start' => 111, 'stock_end' => 100, 'restocked' => false, 'restock_qty' => 0];
$records[] = ['item_id' => 69, 'period' => 'Q3 2024', 'usage' => 10, 'stock_start' => 100, 'stock_end' => 90, 'restocked' => false, 'restock_qty' => 0];
$records[] = ['item_id' => 69, 'period' => 'Q4 2024', 'usage' => 150, 'stock_start' => 90, 'stock_end' => 240, 'restocked' => true, 'restock_qty' => 300];

// Item 71 - Add 2024 + missing 2025 (based on Q4 2025: 93)
$records[] = ['item_id' => 71, 'period' => 'Q1 2024', 'usage' => 75, 'stock_start' => 150, 'stock_end' => 75, 'restocked' => false, 'restock_qty' => 0];
$records[] = ['item_id' => 71, 'period' => 'Q2 2024', 'usage' => 80, 'stock_start' => 75, 'stock_end' => 95, 'restocked' => true, 'restock_qty' => 100];
$records[] = ['item_id' => 71, 'period' => 'Q3 2024', 'usage' => 85, 'stock_start' => 95, 'stock_end' => 10, 'restocked' => false, 'restock_qty' => 0];
$records[] = ['item_id' => 71, 'period' => 'Q4 2024', 'usage' => 90, 'stock_start' => 10, 'stock_end' => 100, 'restocked' => true, 'restock_qty' => 180];
$records[] = ['item_id' => 71, 'period' => 'Q1 2025', 'usage' => 88, 'stock_start' => 100, 'stock_end' => 12, 'restocked' => false, 'restock_qty' => 0];
$records[] = ['item_id' => 71, 'period' => 'Q2 2025', 'usage' => 90, 'stock_start' => 12, 'stock_end' => 102, 'restocked' => true, 'restock_qty' => 180];
$records[] = ['item_id' => 71, 'period' => 'Q3 2025', 'usage' => 92, 'stock_start' => 102, 'stock_end' => 10, 'restocked' => false, 'restock_qty' => 0];

// Item 72 - Add 2024 + missing 2025 (based on Q4 2025: 252)
$records[] = ['item_id' => 72, 'period' => 'Q1 2024', 'usage' => 200, 'stock_start' => 300, 'stock_end' => 100, 'restocked' => false, 'restock_qty' => 0];
$records[] = ['item_id' => 72, 'period' => 'Q2 2024', 'usage' => 210, 'stock_start' => 100, 'stock_end' => 190, 'restocked' => true, 'restock_qty' => 300];
$records[] = ['item_id' => 72, 'period' => 'Q3 2024', 'usage' => 220, 'stock_start' => 190, 'stock_end' => 70, 'restocked' => false, 'restock_qty' => 0];
$records[] = ['item_id' => 72, 'period' => 'Q4 2024', 'usage' => 230, 'stock_start' => 70, 'stock_end' => 140, 'restocked' => true, 'restock_qty' => 300];
$records[] = ['item_id' => 72, 'period' => 'Q1 2025', 'usage' => 240, 'stock_start' => 140, 'stock_end' => 100, 'restocked' => true, 'restock_qty' => 200];
$records[] = ['item_id' => 72, 'period' => 'Q2 2025', 'usage' => 245, 'stock_start' => 100, 'stock_end' => 155, 'restocked' => true, 'restock_qty' => 300];
$records[] = ['item_id' => 72, 'period' => 'Q3 2025', 'usage' => 250, 'stock_start' => 155, 'stock_end' => 105, 'restocked' => true, 'restock_qty' => 200];

// Add timestamps
foreach ($records as &$record) {
    $record['created_at'] = now();
    $record['updated_at'] = now();
}

// Check which records already exist and skip them
$existing = DB::table('supply_usages')
    ->whereIn('item_id', [69, 71, 72])
    ->get()
    ->keyBy(function($item) {
        return $item->item_id . '_' . $item->period;
    });

$toInsert = [];
foreach ($records as $record) {
    $key = $record['item_id'] . '_' . $record['period'];
    if (!$existing->has($key)) {
        $toInsert[] = $record;
    }
}

if (empty($toInsert)) {
    echo "All records already exist.\n";
    exit(0);
}

echo "Inserting " . count($toInsert) . " records...\n";

try {
    DB::table('supply_usages')->insert($toInsert);
    echo "✅ Successfully inserted " . count($toInsert) . " records!\n";
    echo "\nSummary:\n";
    echo "- Item 69: Added Q1-Q4 2024\n";
    echo "- Item 71: Added Q1-Q4 2024 + Q1-Q3 2025\n";
    echo "- Item 72: Added Q1-Q4 2024 + Q1-Q3 2025\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
