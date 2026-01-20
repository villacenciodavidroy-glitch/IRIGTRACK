<?php

/**
 * Directly insert usage data for forecasting
 * This script will add 2024 data and missing 2025 quarters without prompts
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ItemUsage;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

echo "🚀 Directly inserting usage data for forecasting...\n\n";

// Get items with existing usage data (69, 71, 72)
$existingItemIds = ItemUsage::distinct()->pluck('item_id');
$items = Item::whereIn('id', $existingItemIds)->get();

if ($items->isEmpty()) {
    echo "❌ No items found with existing usage data\n";
    exit(1);
}

echo "✅ Found " . $items->count() . " item(s) to process\n\n";

$recordsToCreate = [];
$quarters2024 = ['Q1 2024', 'Q2 2024', 'Q3 2024', 'Q4 2024'];
$quarters2025 = ['Q1 2025', 'Q2 2025', 'Q3 2025', 'Q4 2025'];

foreach ($items as $item) {
    echo "📦 Processing: {$item->unit} (ID: {$item->id})\n";
    
    // Get existing usage data
    $existingUsages = ItemUsage::where('item_id', $item->id)->orderBy('period')->get();
    
    // Analyze patterns
    $avgUsage = $existingUsages->avg('usage') ?? 0;
    $maxUsage = $existingUsages->max('usage') ?? 0;
    $minUsage = $existingUsages->min('usage') ?? 0;
    $recentUsage = $existingUsages->last()->usage ?? $avgUsage;
    $baselineStock = $existingUsages->last()->stock_end ?? ($item->quantity ?? 100);
    
    // Process 2024 quarters
    foreach ($quarters2024 as $quarter) {
        $existing = ItemUsage::where('item_id', $item->id)->where('period', $quarter)->first();
        if ($existing) {
            echo "  ⚠️  {$quarter} already exists\n";
            continue;
        }
        
        // Generate usage based on patterns
        $usage = generateUsage($quarter, $avgUsage, $minUsage, $maxUsage, $recentUsage);
        
        // Calculate stock
        $prevQuarter = getPreviousQuarter($quarter);
        $prevRecord = ItemUsage::where('item_id', $item->id)->where('period', $prevQuarter)->first();
        $stockStart = $prevRecord ? $prevRecord->stock_end : ($baselineStock + rand(50, 150));
        $stockEnd = max(0, $stockStart - $usage);
        
        $restocked = false;
        $restockQty = 0;
        if ($stockEnd < ($stockStart * 0.3) || rand(0, 3) === 0) {
            $restocked = true;
            $restockQty = rand(50, max(100, (int)($usage * 1.5)));
            $stockEnd = $stockStart - $usage + $restockQty;
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
        
        echo "  ✓ {$quarter}: Usage={$usage}\n";
    }
    
    // Process missing 2025 quarters
    foreach ($quarters2025 as $quarter) {
        $existing = ItemUsage::where('item_id', $item->id)->where('period', $quarter)->first();
        if ($existing) {
            echo "  ⚠️  {$quarter} already exists\n";
            continue;
        }
        
        // Generate usage
        $usage = generateUsage($quarter, $avgUsage, $minUsage, $maxUsage, $recentUsage);
        
        // Calculate stock
        $prevQuarter = getPreviousQuarter($quarter);
        $prevRecord = ItemUsage::where('item_id', $item->id)->where('period', $prevQuarter)->first();
        $stockStart = $prevRecord ? $prevRecord->stock_end : ($baselineStock + rand(50, 150));
        $stockEnd = max(0, $stockStart - $usage);
        
        $restocked = false;
        $restockQty = 0;
        if ($stockEnd < ($stockStart * 0.3) || rand(0, 3) === 0) {
            $restocked = true;
            $restockQty = rand(50, max(100, (int)($usage * 1.5)));
            $stockEnd = $stockStart - $usage + $restockQty;
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
        
        echo "  ✓ {$quarter}: Usage={$usage}\n";
    }
    
    echo "\n";
}

if (empty($recordsToCreate)) {
    echo "✅ All records already exist. No new records to create.\n";
    exit(0);
}

echo "📊 Inserting " . count($recordsToCreate) . " record(s)...\n";

try {
    DB::table('supply_usages')->insert($recordsToCreate);
    echo "\n✅ Successfully inserted " . count($recordsToCreate) . " usage record(s)!\n";
    echo "💡 Data is now available for forecasting.\n";
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

function generateUsage($quarter, $avgUsage, $minUsage, $maxUsage, $recentUsage) {
    if (preg_match('/Q(\d)\s+(\d{4})/', $quarter, $matches)) {
        $quarterNum = (int)$matches[1];
    } else {
        return (int)round($avgUsage ?: rand(50, 100));
    }
    
    $baseUsage = $avgUsage > 0 ? $avgUsage : $recentUsage;
    
    $seasonalMultiplier = [
        1 => 0.85,
        2 => 0.95,
        3 => 1.05,
        4 => 1.15,
    ];
    
    $usage = $baseUsage * ($seasonalMultiplier[$quarterNum] ?? 1.0);
    $variation = rand(-20, 20) / 100;
    $usage = $usage * (1 + $variation);
    
    if ($minUsage > 0 && $maxUsage > 0) {
        $usage = max($minUsage * 0.7, min($maxUsage * 1.3, $usage));
    }
    
    return max(1, (int)round($usage));
}

function getPreviousQuarter($quarter) {
    if (preg_match('/Q(\d)\s+(\d{4})/', $quarter, $matches)) {
        $q = (int)$matches[1];
        $year = (int)$matches[2];
        
        if ($q == 1) {
            return "Q4 " . ($year - 1);
        } else {
            return "Q" . ($q - 1) . " $year";
        }
    }
    return null;
}
