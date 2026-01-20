<?php

/**
 * Script to add 2024 usage data for supply items
 * 
 * Usage: php add_2024_usage_data.php
 * 
 * This script will add usage records for Q1-Q4 2024 for:
 * - Ballpens
 * - Bondpaper A4(size)
 * - Flash Drive
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ItemUsage;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

echo "🔍 Finding supply items...\n";

// Default items from the image
$itemNames = ['Ballpens', 'Bondpaper A4(size)', 'Flash Drive'];

// Find items by name (unit field)
$items = Item::whereIn('unit', $itemNames)->get();

if ($items->isEmpty()) {
    echo "❌ No items found with names: " . implode(', ', $itemNames) . "\n";
    echo "💡 Available supply items:\n";
    Item::whereHas('category', function($q) {
        $q->where('category', 'like', '%supply%');
    })->get()->each(function($item) {
        echo "  - {$item->unit} (ID: {$item->id})\n";
    });
    exit(1);
}

echo "✅ Found " . $items->count() . " item(s)\n\n";

$quarters = ['Q1 2024', 'Q2 2024', 'Q3 2024', 'Q4 2024'];
$recordsToCreate = [];

foreach ($items as $item) {
    echo "📦 Processing: {$item->unit} (ID: {$item->id})\n";
    
    // Get current quantity as baseline
    $currentQuantity = $item->quantity ?? 0;
    
    // Generate sample usage data for each quarter
    // Modify these values based on actual usage patterns
    $quarterlyUsage = [
        'Q1 2024' => rand(50, 100),   // Sample: 50-100 units per quarter
        'Q2 2024' => rand(60, 110),
        'Q3 2024' => rand(55, 105),
        'Q4 2024' => rand(70, 120),
    ];
    
    foreach ($quarters as $quarter) {
        $usage = $quarterlyUsage[$quarter];
        
        // Calculate stock values (simplified - adjust based on actual data)
        $stockStart = $currentQuantity + rand(50, 200); // Starting stock
        $stockEnd = max(0, $stockStart - $usage); // Ending stock after usage
        
        // Randomly decide if restocked in this quarter
        $restocked = rand(0, 1) === 1;
        $restockQty = $restocked ? rand(50, 150) : 0;
        
        if ($restocked) {
            $stockEnd = $stockStart - $usage + $restockQty;
        }
        
        // Check if record already exists
        $existing = ItemUsage::where('item_id', $item->id)
            ->where('period', $quarter)
            ->first();
        
        if ($existing) {
            echo "  ⚠️  Record already exists for {$quarter}: Usage={$existing->usage}\n";
            continue;
        }
        
        $recordsToCreate[] = [
            'item_id' => $item->id,
            'period' => $quarter,
            'usage' => $usage,
            'stock_start' => $stockStart,
            'stock_end' => $stockEnd,
            'restocked' => $restocked,
            'restock_qty' => $restockQty,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        echo "  ✓ {$quarter}: Usage={$usage}, Stock Start={$stockStart}, Stock End={$stockEnd}" . 
             ($restocked ? ", Restocked={$restockQty}" : "") . "\n";
    }
    echo "\n";
}

if (empty($recordsToCreate)) {
    echo "✅ All records already exist. No new records to create.\n";
    exit(0);
}

echo "📊 Summary: " . count($recordsToCreate) . " record(s) to create\n\n";

echo "⚠️  About to create " . count($recordsToCreate) . " usage record(s). Continue? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) !== 'yes') {
    echo "Cancelled.\n";
    exit(0);
}
fclose($handle);

// Bulk insert
try {
    DB::table('supply_usages')->insert($recordsToCreate);
    echo "\n✅ Successfully created " . count($recordsToCreate) . " usage record(s)!\n";
    echo "💡 You can now view this data in the Usage Overview page and it will be used for forecasting.\n";
} catch (\Exception $e) {
    echo "\n❌ Error creating records: " . $e->getMessage() . "\n";
    exit(1);
}
