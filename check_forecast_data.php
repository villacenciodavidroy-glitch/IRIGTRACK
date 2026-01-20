<?php

require __DIR__ . '/backend-laravel/vendor/autoload.php';

$app = require_once __DIR__ . '/backend-laravel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ItemUsage;

echo "========================================\n";
echo "Forecast Data Analysis\n";
echo "========================================\n\n";

$items = [
    69 => 'Flash Drive',
    71 => 'Bondpaper A4(size)',
    72 => 'Ballpens'
];

foreach ($items as $id => $name) {
    $usages = ItemUsage::where('item_id', $id)
        ->orderBy('period')
        ->get(['period', 'usage']);
    
    echo "Item {$id} ({$name}):\n";
    echo "  Data Points: " . $usages->count() . "\n";
    
    if ($usages->count() > 0) {
        echo "  Historical Data:\n";
        foreach ($usages as $u) {
            echo "    {$u->period}: {$u->usage} units\n";
        }
        
        // Calculate average
        $avg = $usages->avg('usage');
        echo "  Average Usage: " . round($avg, 2) . " units/quarter\n";
        
        // Check if data is sufficient for forecasting
        if ($usages->count() < 2) {
            echo "  ⚠️  WARNING: Less than 2 data points - forecast will use average (30% confidence)\n";
        } elseif ($usages->count() < 4) {
            echo "  ⚠️  WARNING: Less than 4 data points - forecast may have low confidence\n";
        } else {
            echo "  ✅ Sufficient data for forecasting\n";
        }
    } else {
        echo "  ❌ No usage data found\n";
    }
    
    echo "\n";
}

echo "========================================\n";
echo "Analysis\n";
echo "========================================\n";
echo "30% confidence means:\n";
echo "- R-squared value is very low (< 0.3)\n";
echo "- Linear regression doesn't fit the data well\n";
echo "- Possible reasons:\n";
echo "  1. Not enough historical data (need 4+ quarters)\n";
echo "  2. Data is too variable/inconsistent\n";
echo "  3. Data doesn't follow a linear trend\n";
echo "\n";

