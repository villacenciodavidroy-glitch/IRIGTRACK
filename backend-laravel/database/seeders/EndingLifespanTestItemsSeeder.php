<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Category;
use App\Models\Condition;
use App\Models\ConditionNumber;
use App\Models\Location;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class EndingLifespanTestItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates test items that will be predicted as "Ending Lifespan Soon" by the CatBoost model
     */
    public function run(): void
    {
        // Get required relationships
        $desktopCategory = Category::where('category', 'Desktop')->first();
        $ictCategory = Category::where('category', 'ICT')->first();
        
        if (!$desktopCategory) {
            $desktopCategory = Category::create(['category' => 'Desktop']);
        }
        if (!$ictCategory) {
            $ictCategory = Category::create(['category' => 'ICT']);
        }
        
        $serviceableCondition = Condition::where('condition', 'Serviceable')->first();
        $onMaintenanceCondition = Condition::where('condition', 'On Maintenance')->first();
        
        $conditionA4 = ConditionNumber::where('condition_number', 'A4')->first();
        $conditionA5 = ConditionNumber::where('condition_number', 'A5')->first();
        $conditionA3 = ConditionNumber::where('condition_number', 'A3')->first();
        
        // Use A4 if A5 doesn't exist, or A3 if A4 doesn't exist
        $highConditionNumber = $conditionA5 ?? $conditionA4 ?? $conditionA3 ?? ConditionNumber::first();
        $mediumConditionNumber = $conditionA4 ?? $conditionA3 ?? ConditionNumber::first();
        
        $location = Location::first();
        if (!$location) {
            $location = Location::create(['location' => 'ICT']);
        }
        
        $user = User::first();
        
        // Maintenance reasons that trigger penalties
        $maintenanceReasons = [
            'Overheat',
            'Wear',
            'Electrical fault',
            'Component failure',
            'Wet damage',
            'Physical damage'
        ];
        
        // Create 8 test items that will be predicted as ending soon
        $testItems = [
            [
                'unit' => 'Old Desktop Computer - Office 1',
                'description' => 'Dell OptiPlex 7050 - High usage, multiple repairs',
                'category_id' => $desktopCategory->id,
                'date_acquired' => now()->subYears(7)->subMonths(6)->format('Y-m-d'), // 7.5 years ago
                'maintenance_count' => 3,
                'condition_number_id' => $mediumConditionNumber->id ?? null,
                'maintenance_reason' => 'Overheat and wear',
                'maintenance_records' => [
                    ['reason' => 'Overheat', 'months_ago' => 3],
                    ['reason' => 'Wear', 'months_ago' => 8],
                    ['reason' => 'Electrical', 'months_ago' => 15],
                ]
            ],
            [
                'unit' => 'Aging Laptop - Engineering',
                'description' => 'HP EliteBook 840 - Frequent overheating issues',
                'category_id' => $ictCategory->id,
                'date_acquired' => now()->subYears(7)->subMonths(11)->format('Y-m-d'), // ~8 years ago
                'maintenance_count' => 4,
                'condition_number_id' => $highConditionNumber->id ?? null,
                'maintenance_reason' => 'Overheat',
                'maintenance_records' => [
                    ['reason' => 'Overheat', 'months_ago' => 1],
                    ['reason' => 'Overheat', 'months_ago' => 4],
                    ['reason' => 'Wear', 'months_ago' => 10],
                    ['reason' => 'Electrical', 'months_ago' => 18],
                ]
            ],
            [
                'unit' => 'Printer - Admin Unit',
                'description' => 'Canon PIXMA - High print volume, worn parts',
                'category_id' => $ictCategory->id,
                'date_acquired' => now()->subYears(8)->subDays(30)->format('Y-m-d'), // ~8 years ago
                'maintenance_count' => 2,
                'condition_number_id' => $mediumConditionNumber->id ?? null,
                'maintenance_reason' => 'Wear',
                'maintenance_records' => [
                    ['reason' => 'Wear', 'months_ago' => 2],
                    ['reason' => 'Wear', 'months_ago' => 12],
                ]
            ],
            [
                'unit' => 'Server Rack Unit 1',
                'description' => 'Dell PowerEdge R730 - Multiple component failures',
                'category_id' => $ictCategory->id,
                'date_acquired' => now()->subYears(7)->subMonths(3)->format('Y-m-d'), // 7.25 years ago
                'maintenance_count' => 5,
                'condition_number_id' => $highConditionNumber->id ?? null,
                'maintenance_reason' => 'Electrical fault',
                'maintenance_records' => [
                    ['reason' => 'Electrical', 'months_ago' => 1],
                    ['reason' => 'Electrical', 'months_ago' => 3],
                    ['reason' => 'Component failure', 'months_ago' => 6],
                    ['reason' => 'Overheat', 'months_ago' => 12],
                    ['reason' => 'Wear', 'months_ago' => 20],
                ]
            ],
            [
                'unit' => 'Projector - Conference Room',
                'description' => 'Epson EX9240 - Overheating and lamp issues',
                'category_id' => $ictCategory->id,
                'date_acquired' => now()->subYears(7)->subDays(180)->format('Y-m-d'), // ~7.5 years ago
                'maintenance_count' => 3,
                'condition_number_id' => $mediumConditionNumber->id ?? null,
                'maintenance_reason' => 'Overheat',
                'maintenance_records' => [
                    ['reason' => 'Overheat', 'months_ago' => 2],
                    ['reason' => 'Component failure', 'months_ago' => 7],
                    ['reason' => 'Wear', 'months_ago' => 14],
                ]
            ],
            [
                'unit' => 'Network Switch - Main Office',
                'description' => 'Cisco Catalyst 2960 - Electrical issues',
                'category_id' => $ictCategory->id,
                'date_acquired' => now()->subYears(8)->subMonths(1)->format('Y-m-d'), // ~8 years ago
                'maintenance_count' => 2,
                'condition_number_id' => $mediumConditionNumber->id ?? null,
                'maintenance_reason' => 'Electrical',
                'maintenance_records' => [
                    ['reason' => 'Electrical', 'months_ago' => 3],
                    ['reason' => 'Electrical', 'months_ago' => 11],
                ]
            ],
            [
                'unit' => 'Workstation - Design Team',
                'description' => 'HP Z240 - High workload, frequent repairs',
                'category_id' => $desktopCategory->id,
                'date_acquired' => now()->subYears(7)->subMonths(9)->format('Y-m-d'), // ~7.75 years ago
                'maintenance_count' => 4,
                'condition_number_id' => $highConditionNumber->id ?? null,
                'maintenance_reason' => 'Wear and tear',
                'maintenance_records' => [
                    ['reason' => 'Wear', 'months_ago' => 1],
                    ['reason' => 'Overheat', 'months_ago' => 5],
                    ['reason' => 'Wear', 'months_ago' => 9],
                    ['reason' => 'Component failure', 'months_ago' => 16],
                ]
            ],
            [
                'unit' => 'Monitor Display - Reception',
                'description' => 'Samsung 24" LED - Physical damage and wear',
                'category_id' => $ictCategory->id,
                'date_acquired' => now()->subYears(7)->subDays(45)->format('Y-m-d'), // ~7.1 years ago
                'maintenance_count' => 3,
                'condition_number_id' => $mediumConditionNumber->id ?? null,
                'maintenance_reason' => 'Physical damage',
                'maintenance_records' => [
                    ['reason' => 'Physical damage', 'months_ago' => 4],
                    ['reason' => 'Wear', 'months_ago' => 10],
                    ['reason' => 'Component failure', 'months_ago' => 17],
                ]
            ],
        ];
        
        foreach ($testItems as $itemData) {
            // Create the item
            $itemDataForCreation = [
                'unit' => $itemData['unit'],
                'description' => $itemData['description'],
                'category_id' => $itemData['category_id'],
                'location_id' => $location->id,
                'condition_id' => $serviceableCondition->id,
                'condition_number_id' => $itemData['condition_number_id'],
                'date_acquired' => $itemData['date_acquired'],
                'maintenance_count' => $itemData['maintenance_count'],
                'pac' => 'PAC-' . strtoupper(Str::random(6)),
                'po_number' => 'PO-' . strtoupper(Str::random(8)),
                'unit_value' => rand(15000, 50000),
                'quantity' => 1,
                'user_id' => $user->id ?? null,
            ];
            
            // Add maintenance_reason if column exists
            if (Schema::hasColumn('items', 'maintenance_reason')) {
                $itemDataForCreation['maintenance_reason'] = $itemData['maintenance_reason'];
            }
            
            $item = Item::create($itemDataForCreation);
            
            // Create maintenance records for this item
            if (isset($itemData['maintenance_records'])) {
                foreach ($itemData['maintenance_records'] as $record) {
                    MaintenanceRecord::create([
                        'item_id' => $item->id,
                        'maintenance_date' => now()->subMonths($record['months_ago'])->format('Y-m-d'),
                        'reason' => ucfirst(strtolower($record['reason'])), // Normalize to enum format
                        'condition_before_id' => $serviceableCondition->id,
                        'condition_after_id' => $onMaintenanceCondition->id ?? $serviceableCondition->id,
                        'technician_notes' => $record['reason'] . ' issue - repaired and tested',
                    ]);
                }
            }
            
            echo "Created item: {$item->unit} (ID: {$item->id})\n";
            echo "  - Date acquired: {$itemData['date_acquired']}\n";
            echo "  - Maintenance count: {$itemData['maintenance_count']}\n";
            echo "  - Maintenance records: " . count($itemData['maintenance_records'] ?? []) . "\n";
        }
        
        echo "\n✅ Created " . count($testItems) . " test items that should be predicted as 'Ending Lifespan Soon'\n";
        echo "📊 These items have:\n";
        echo "   - High years in use (7-8 years)\n";
        echo "   - Multiple maintenance records (2-5 repairs)\n";
        echo "   - High condition numbers (A4-A5)\n";
        echo "   - Maintenance reasons that trigger penalties\n";
        echo "\n🔄 Run the lifespan prediction to see these items in the 'Ending Lifespan Soon' section!\n";
    }
}
