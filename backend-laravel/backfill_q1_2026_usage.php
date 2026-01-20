<?php

/**
 * Backfill Q1 2026 Usage Data from Fulfilled Supply Requests
 * 
 * This script creates usage records in supply_usages table based on
 * fulfilled supply requests that occurred in Q1 2026 (Jan-Mar 2026).
 * 
 * Usage:
 *   php backfill_q1_2026_usage.php
 *   Or: php artisan tinker < backfill_q1_2026_usage.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\SupplyRequest;
use App\Models\ItemUsage;
use App\Models\Item;

echo "========================================\n";
echo "Backfill Q1 2026 Usage Data\n";
echo "========================================\n\n";

// Q1 2026 date range
$q1Start = '2026-01-01 00:00:00';
$q1End = '2026-03-31 23:59:59';
$period = 'Q1 2026';

echo "📅 Period: {$period}\n";
echo "📆 Date Range: {$q1Start} to {$q1End}\n\n";

// Get all fulfilled supply requests in Q1 2026
$fulfilledRequests = SupplyRequest::where('status', 'fulfilled')
    ->whereBetween('fulfilled_at', [$q1Start, $q1End])
    ->get();

echo "📊 Found " . $fulfilledRequests->count() . " fulfilled supply requests in Q1 2026\n\n";

if ($fulfilledRequests->isEmpty()) {
    echo "⚠️  No fulfilled requests found for Q1 2026.\n";
    echo "💡 Make sure you have fulfilled some supply requests between Jan 1 - Mar 31, 2026.\n";
    exit(0);
}

$recordsCreated = 0;
$recordsUpdated = 0;
$errors = [];

foreach ($fulfilledRequests as $request) {
    try {
        // Get the item
        $item = $request->item();
        
        if (!$item) {
            $errors[] = "Request ID {$request->id}: Item not found (item_id: {$request->item_id})";
            continue;
        }
        
        // Get quantity fulfilled
        $quantity = $request->quantity ?? 0;
        
        if ($quantity <= 0) {
            $errors[] = "Request ID {$request->id}: Invalid quantity ({$quantity})";
            continue;
        }
        
        // Check if usage record already exists
        $existingUsage = ItemUsage::where('item_id', $item->id)
            ->where('period', $period)
            ->first();
        
        if ($existingUsage) {
            // Update existing record - add the quantity
            $existingUsage->usage += $quantity;
            $existingUsage->save();
            $recordsUpdated++;
            
            echo "  ✓ Updated: Item {$item->id} ({$item->unit}) - Added {$quantity} units (Total: {$existingUsage->usage})\n";
        } else {
            // Create new usage record
            // Try to get stock info from the item's current state
            $stockStart = $item->quantity + $quantity; // Approximate: current + fulfilled = what it was before
            $stockEnd = $item->quantity;
            
            $usage = ItemUsage::create([
                'item_id' => $item->id,
                'period' => $period,
                'usage' => $quantity,
                'stock_start' => $stockStart,
                'stock_end' => $stockEnd,
                'restocked' => false,
                'restock_qty' => 0,
            ]);
            
            $recordsCreated++;
            
            echo "  ✓ Created: Item {$item->id} ({$item->unit}) - {$quantity} units\n";
        }
    } catch (\Exception $e) {
        $errors[] = "Request ID {$request->id}: " . $e->getMessage();
        echo "  ✗ Error processing request ID {$request->id}: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "✅ Records Created: {$recordsCreated}\n";
echo "🔄 Records Updated: {$recordsUpdated}\n";
echo "❌ Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\n⚠️  Errors encountered:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
}

echo "\n";
echo "✅ Backfill complete!\n";
echo "💡 Usage data is now available for Q1 2026 in the Usage Overview.\n";
echo "\n";

