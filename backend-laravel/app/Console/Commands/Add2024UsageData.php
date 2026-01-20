<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ItemUsage;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class AddForecastUsageData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:add-forecast-data 
                            {--item-names=* : Specific item names to add data for}
                            {--item-ids=* : Specific item IDs to add data for (e.g., 69 71 72)}
                            {--auto-detect : Automatically detect items with existing usage data}
                            {--year=2024 : Year to add data for (default: 2024)}
                            {--fill-missing : Also fill missing quarters in current year}
                            {--force : Skip confirmation and insert directly}
                            {--dry-run : Show what would be created without actually creating records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add usage data for forecasting (2024 and missing 2025 quarters)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Finding items to process...');
        
        $itemIds = $this->option('item-ids');
        $itemNames = $this->option('item-names');
        $autoDetect = $this->option('auto-detect');
        $targetYear = $this->option('year');
        $fillMissing = $this->option('fill-missing');
        
        $items = collect();
        
        // Auto-detect items with existing usage data
        if ($autoDetect || (empty($itemIds) && empty($itemNames))) {
            $this->info('🔍 Auto-detecting items with existing usage data...');
            $existingItemIds = ItemUsage::distinct()->pluck('item_id');
            if ($existingItemIds->isNotEmpty()) {
                $items = Item::whereIn('id', $existingItemIds)->get();
                $this->info("✅ Found " . $items->count() . " item(s) with existing usage data");
            }
        }
        
        // Find by item IDs
        if (!empty($itemIds)) {
            // Handle comma-separated IDs or array
            $ids = [];
            foreach ($itemIds as $id) {
                if (is_string($id) && strpos($id, ',') !== false) {
                    $ids = array_merge($ids, array_map('trim', explode(',', $id)));
                } else {
                    $ids[] = $id;
                }
            }
            $ids = array_filter(array_map('intval', $ids));
            if (!empty($ids)) {
                $itemsByIds = Item::whereIn('id', $ids)->get();
                $items = $items->merge($itemsByIds)->unique('id');
            }
        }
        
        // Find by item names
        if (!empty($itemNames)) {
            $itemsByName = Item::whereIn('unit', $itemNames)->get();
            $items = $items->merge($itemsByName)->unique('id');
        }
        
        if ($items->isEmpty()) {
            $this->error('❌ No items found');
            $this->info('💡 Usage:');
            $this->line('  --auto-detect : Find items with existing usage data');
            $this->line('  --item-ids=69,71,72 : Specify item IDs');
            $this->line('  --item-names="Ballpens" : Specify item names');
            return 1;
        }
        
        $this->info("✅ Processing " . $items->count() . " item(s)");
        
        $recordsToCreate = [];
        
        // Get quarters to add
        $quarters2024 = ['Q1 2024', 'Q2 2024', 'Q3 2024', 'Q4 2024'];
        $quarters2025 = ['Q1 2025', 'Q2 2025', 'Q3 2025', 'Q4 2025'];
        $quartersToAdd = [];
        
        if ($targetYear == 2024) {
            $quartersToAdd = array_merge($quartersToAdd, $quarters2024);
        }
        
        if ($fillMissing || $targetYear == 2025) {
            $quartersToAdd = array_merge($quartersToAdd, $quarters2025);
        }
        
        foreach ($items as $item) {
            $this->info("\n📦 Processing: {$item->unit} (ID: {$item->id})");
            
            // Get existing usage data to analyze patterns
            $existingUsages = ItemUsage::where('item_id', $item->id)
                ->orderBy('period')
                ->get();
            
            // Analyze existing patterns
            $avgUsage = $existingUsages->avg('usage') ?? 0;
            $maxUsage = $existingUsages->max('usage') ?? 0;
            $minUsage = $existingUsages->min('usage') ?? 0;
            $recentUsage = $existingUsages->last()->usage ?? $avgUsage;
            
            // Get current quantity
            $currentQuantity = $item->quantity ?? 0;
            
            // Estimate baseline stock (use recent stock_end or current quantity)
            $baselineStock = $existingUsages->last()->stock_end ?? $currentQuantity;
            
            $this->line("  📊 Existing data: Avg={$avgUsage}, Recent={$recentUsage}, Baseline Stock={$baselineStock}");
            
            foreach ($quartersToAdd as $quarter) {
                // Check if record already exists
                $existing = ItemUsage::where('item_id', $item->id)
                    ->where('period', $quarter)
                    ->first();
                
                if ($existing) {
                    $this->warn("  ⚠️  Record already exists for {$quarter}: Usage={$existing->usage}");
                    continue;
                }
                
                // Generate realistic usage based on existing patterns
                $usage = $this->generateRealisticUsage($quarter, $avgUsage, $minUsage, $maxUsage, $recentUsage);
                
                // Calculate stock values based on previous quarter's end stock
                $prevQuarter = $this->getPreviousQuarter($quarter);
                $prevRecord = ItemUsage::where('item_id', $item->id)
                    ->where('period', $prevQuarter)
                    ->first();
                
                $stockStart = $prevRecord ? $prevRecord->stock_end : ($baselineStock + rand(50, 150));
                $stockEnd = max(0, $stockStart - $usage);
                
                // Decide if restocked (more likely if stock is low)
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
                
                $this->line("  ✓ {$quarter}: Usage={$usage}, Stock Start={$stockStart}, Stock End={$stockEnd}" . 
                           ($restocked ? ", Restocked={$restockQty}" : ""));
            }
        }
        
        if (empty($recordsToCreate)) {
            $this->info("\n✅ All records already exist. No new records to create.");
            return 0;
        }
        
        $this->info("\n📊 Summary: " . count($recordsToCreate) . " record(s) to create");
        
        if ($this->option('dry-run')) {
            $this->warn("\n🔍 DRY RUN MODE - No records will be created");
            $this->table(
                ['Item ID', 'Period', 'Usage', 'Stock Start', 'Stock End', 'Restocked'],
                array_map(function($r) use ($items) {
                    $item = $items->firstWhere('id', $r['item_id']);
                    return [
                        $r['item_id'],
                        $item ? $item->unit : 'N/A',
                        $r['period'],
                        $r['usage'],
                        $r['stock_start'],
                        $r['stock_end'],
                        $r['restocked'] ? 'Yes (' . $r['restock_qty'] . ')' : 'No',
                    ];
                }, $recordsToCreate)
            );
            return 0;
        }
        
        // Skip confirmation if --force flag is set
        if (!$this->option('force') && !$this->confirm('Do you want to create these usage records?', true)) {
            $this->info('Cancelled.');
            return 0;
        }
        
        // Bulk insert
        try {
            DB::table('supply_usages')->insert($recordsToCreate);
            $this->info("\n✅ Successfully created " . count($recordsToCreate) . " usage record(s)!");
            return 0;
        } catch (\Exception $e) {
            $this->error("\n❌ Error creating records: " . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Generate realistic usage based on existing patterns
     */
    private function generateRealisticUsage($quarter, $avgUsage, $minUsage, $maxUsage, $recentUsage)
    {
        // Extract quarter number and year
        if (preg_match('/Q(\d)\s+(\d{4})/', $quarter, $matches)) {
            $quarterNum = (int)$matches[1];
            $year = (int)$matches[2];
        } else {
            return (int)round($avgUsage ?: rand(50, 100));
        }
        
        // Base usage on average, with some variation
        $baseUsage = $avgUsage > 0 ? $avgUsage : $recentUsage;
        
        // Add seasonal variation (Q4 often higher, Q1 often lower)
        $seasonalMultiplier = [
            1 => 0.85, // Q1 - typically lower
            2 => 0.95, // Q2
            3 => 1.05, // Q3
            4 => 1.15, // Q4 - typically higher
        ];
        
        $usage = $baseUsage * ($seasonalMultiplier[$quarterNum] ?? 1.0);
        
        // Add some random variation (±20%)
        $variation = rand(-20, 20) / 100;
        $usage = $usage * (1 + $variation);
        
        // Ensure within reasonable bounds
        if ($minUsage > 0 && $maxUsage > 0) {
            $usage = max($minUsage * 0.7, min($maxUsage * 1.3, $usage));
        }
        
        return max(1, (int)round($usage));
    }
    
    /**
     * Get previous quarter
     */
    private function getPreviousQuarter($quarter)
    {
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
}
