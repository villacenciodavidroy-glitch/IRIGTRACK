<?php

namespace App\Console\Commands;

use App\Jobs\CheckLowStockJob;
use App\Models\Notification;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Console\Command;

class TestLowStockJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:low-stock-job {--sync : Run job synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the CheckLowStockJob to verify low stock notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting CheckLowStockJob test...');
        $this->newLine();

        // Diagnostic checks
        $this->info('🔍 Running diagnostics...');
        
        // Check for Supply/Consumables category
        $supplyCategory = Category::where(function($query) {
            $query->whereRaw('LOWER(category) = ?', ['supply'])
                  ->orWhereRaw('LOWER(category) = ?', ['consumables']);
        })->first();

        // First, show ALL items with low quantity in entire database (regardless of category)
        $this->info("🔍 Checking ALL items in database with quantity < 50...");
        $allLowStockItems = Item::where('quantity', '<', 50)
            ->whereNotNull('quantity')
            ->with('category')
            ->get();
        
        $this->info("📦 Total items with quantity < 50 in ALL categories: {$allLowStockItems->count()}");
        
        if ($allLowStockItems->count() > 0) {
            $this->newLine();
            $this->info('All low stock items (all categories):');
            $this->table(
                ['ID', 'Unit', 'Quantity', 'Category', 'Category ID'],
                $allLowStockItems->map(function ($item) {
                    return [
                        $item->id,
                        $item->unit ?? 'N/A',
                        $item->quantity ?? 'N/A',
                        $item->category->category ?? 'N/A',
                        $item->category_id ?? 'N/A',
                    ];
                })->toArray()
            );
        }
        
        $this->newLine();
        
        if ($supplyCategory) {
            $this->info("✅ Found category: '{$supplyCategory->category}' (ID: {$supplyCategory->id})");
            
            // Check ALL items in this category first (for debugging)
            $allCategoryItems = Item::where('category_id', $supplyCategory->id)->get();
            $this->info("📊 Total items in this category: {$allCategoryItems->count()}");
            
            if ($allCategoryItems->count() > 0) {
                $this->info("Items in category (showing first 10):");
                $this->table(
                    ['ID', 'Unit', 'Quantity', 'Category ID'],
                    $allCategoryItems->take(10)->map(function ($item) {
                        return [
                            $item->id,
                            $item->unit ?? 'N/A',
                            $item->quantity ?? 'NULL',
                            $item->category_id,
                        ];
                    })->toArray()
                );
            }
            
            // Check for low stock items
            $lowStockItems = Item::where('category_id', $supplyCategory->id)
                ->where('quantity', '<', 50)
                ->whereNotNull('quantity')
                ->get();
            
            $this->info("📦 Found {$lowStockItems->count()} items with quantity < 50 in '{$supplyCategory->category}' category");
            
            // Also check items with NULL quantity
            $nullQuantityItems = Item::where('category_id', $supplyCategory->id)
                ->whereNull('quantity')
                ->count();
            if ($nullQuantityItems > 0) {
                $this->warn("⚠️  Note: {$nullQuantityItems} items have NULL quantity (skipped)");
            }
            
            // Check items with quantity >= 50 in this category
            $adequateStockItems = Item::where('category_id', $supplyCategory->id)
                ->where('quantity', '>=', 50)
                ->whereNotNull('quantity')
                ->count();
            if ($adequateStockItems > 0) {
                $this->info("ℹ️  {$adequateStockItems} items have adequate stock (>= 50) in this category");
            }
            
            if ($lowStockItems->count() > 0) {
                $this->newLine();
                $this->info('Low stock items:');
                $this->table(
                    ['ID', 'Unit', 'Quantity', 'Has Notification Today?'],
                    $lowStockItems->map(function ($item) {
                        $hasNotification = Notification::where('item_id', $item->id)
                            ->where('message', 'like', '%low stock%')
                            ->whereDate('created_at', today())
                            ->exists();
                        return [
                            $item->id,
                            $item->unit ?? 'N/A',
                            $item->quantity ?? 'N/A',
                            $hasNotification ? 'Yes ✅' : 'No ❌'
                        ];
                    })->toArray()
                );
        } else {
            $this->warn('⚠️  No items found with quantity < 50');
            
            // Offer to create test data
            if ($this->confirm('Would you like to create a test item with low stock?', false)) {
                $this->createTestItem($supplyCategory);
                // Re-check after creation
                $lowStockItems = Item::where('category_id', $supplyCategory->id)
                    ->where('quantity', '<', 50)
                    ->whereNotNull('quantity')
                    ->get();
                $this->info("📦 Now found {$lowStockItems->count()} items with quantity < 50");
            }
        }
        } else {
            $this->error("❌ Supply/Consumables category not found!");
            $this->info('Available categories:');
            $categories = Category::all();
            if ($categories->count() > 0) {
                $this->table(
                    ['ID', 'Category'],
                    $categories->map(fn($cat) => [$cat->id, $cat->category])->toArray()
                );
                $this->info('💡 Create a category with name "Supply" or "Consumables"');
            } else {
                $this->warn('No categories found in database.');
            }
            
            // Offer to create test category and item
            if ($this->confirm('Would you like to create a "Supply" category and test item?', false)) {
                $supplyCategory = Category::create(['category' => 'Supply']);
                $this->info("✅ Created category: '{$supplyCategory->category}' (ID: {$supplyCategory->id})");
                $this->createTestItem($supplyCategory);
            }
        }

        $this->newLine();
        $this->info('🚀 Running job...');
        $notificationCountBefore = Notification::count();

        if ($this->option('sync')) {
            // Run job synchronously
            $job = new CheckLowStockJob();
            $job->handle();
        } else {
            // Dispatch to queue
            CheckLowStockJob::dispatch();
            $this->warn('Job dispatched. Make sure queue worker is running: php artisan queue:work');
        }

        $this->newLine();
        $this->info('Job execution completed!');
        $this->newLine();

        $notificationCountAfter = Notification::count();
        $notificationsCreated = $notificationCountAfter - $notificationCountBefore;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Notifications Before', $notificationCountBefore],
                ['Notifications After', $notificationCountAfter],
                ['Notifications Created', $notificationsCreated],
            ]
        );

        if ($notificationsCreated > 0) {
            $this->newLine();
            $this->info('Latest notifications:');
            $latestNotifications = Notification::latest()->take(5)->get();
            
            $this->table(
                ['ID', 'Item ID', 'Message', 'Created At'],
                $latestNotifications->map(function ($notification) {
                    return [
                        $notification->notification_id,
                        $notification->item_id,
                        substr($notification->message, 0, 50) . '...',
                        $notification->created_at->format('Y-m-d H:i:s'),
                    ];
                })->toArray()
            );
        } else {
            $this->warn('No new notifications were created.');
            $this->info('Possible reasons:');
            $this->line('  - Supply/Consumables category not found');
            $this->line('  - No items with quantity < 50');
            $this->line('  - Notifications already exist for today');
        }

        return Command::SUCCESS;
    }

    /**
     * Create a test item with low stock for testing purposes
     */
    private function createTestItem(Category $category): void
    {
        try {
            // Get or create a location
            $location = Location::first();
            if (!$location) {
                $location = Location::create(['location' => 'Test Location']);
                $this->info("✅ Created test location: '{$location->location}'");
            }

            // Get or create a user
            $user = User::first();
            if (!$user) {
                $user = User::create([
                    'fullname' => 'Test User',
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                    'password' => bcrypt('password'),
                    'role' => 'user',
                    'location_id' => $location->id,
                ]);
                $this->info("✅ Created test user: '{$user->fullname}'");
            }

            // Create test item with low stock (quantity = 25, below threshold of 50)
            $item = Item::create([
                'unit' => 'Test Low Stock Item',
                'description' => 'Test item created for low stock notification testing',
                'pac' => 'TEST-LOW-' . time(),
                'unit_value' => 100.00,
                'quantity' => 25, // Below threshold of 50
                'date_acquired' => now(),
                'po_number' => 'PO-TEST-' . time(),
                'category_id' => $category->id,
                'location_id' => $location->id,
                'user_id' => $user->id,
            ]);

            $this->info("✅ Created test item: '{$item->unit}' with quantity {$item->quantity}");
        } catch (\Exception $e) {
            $this->error("❌ Failed to create test item: " . $e->getMessage());
        }
    }
}
