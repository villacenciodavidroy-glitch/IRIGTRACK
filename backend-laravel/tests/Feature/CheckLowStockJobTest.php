<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\CheckLowStockJob;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class CheckLowStockJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the job creates notifications for low stock items
     */
    public function test_job_creates_notifications_for_low_stock_items(): void
    {
        // Create supply category
        $supplyCategory = Category::create([
            'category' => 'Supply'
        ]);

        // Create other required models
        $location = Location::create(['location' => 'Test Location']);
        $user = User::create([
            'fullname' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'location_id' => $location->id,
        ]);

        // Create item with low stock (quantity < 50)
        $lowStockItem = Item::create([
            'unit' => 'Test Item Low Stock',
            'description' => 'Test Description',
            'pac' => 'TEST001',
            'unit_value' => 100,
            'quantity' => 30, // Below threshold of 50
            'date_acquired' => now(),
            'po_number' => 'PO123',
            'category_id' => $supplyCategory->id,
            'location_id' => $location->id,
            'user_id' => $user->id,
        ]);

        // Create item with adequate stock (quantity >= 50)
        $adequateStockItem = Item::create([
            'unit' => 'Test Item Adequate',
            'description' => 'Test Description',
            'pac' => 'TEST002',
            'unit_value' => 100,
            'quantity' => 100, // Above threshold of 50
            'date_acquired' => now(),
            'po_number' => 'PO124',
            'category_id' => $supplyCategory->id,
            'location_id' => $location->id,
            'user_id' => $user->id,
        ]);

        // Execute the job
        $job = new CheckLowStockJob();
        $job->handle();

        // Assert that notification was created for low stock item
        $this->assertDatabaseHas('notifications', [
            'item_id' => $lowStockItem->id,
        ]);

        // Assert that notification was NOT created for adequate stock item
        $this->assertDatabaseMissing('notifications', [
            'item_id' => $adequateStockItem->id,
        ]);

        // Verify notification message contains expected text
        $notification = Notification::where('item_id', $lowStockItem->id)->first();
        $this->assertStringContainsString('Low stock alert', $notification->message);
        $this->assertStringContainsString('30', $notification->message);
        $this->assertStringContainsString('Threshold: 50', $notification->message);
    }

    /**
     * Test that the job skips when supply category doesn't exist
     */
    public function test_job_skips_when_supply_category_not_found(): void
    {
        // Don't create supply category
        // Log should be called
        Log::shouldReceive('info')
            ->once()
            ->with('Supply category not found. Skipping low stock check.');

        $job = new CheckLowStockJob();
        $job->handle();

        // No notifications should be created
        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * Test that duplicate notifications are not created on the same day
     */
    public function test_job_prevents_duplicate_notifications_same_day(): void
    {
        // Create supply category
        $supplyCategory = Category::create([
            'category' => 'Supply'
        ]);

        $location = Location::create(['location' => 'Test Location']);
        $user = User::create([
            'fullname' => 'Test User',
            'username' => 'testuser3',
            'email' => 'test3@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'location_id' => $location->id,
        ]);

        // Create item with low stock
        $lowStockItem = Item::create([
            'unit' => 'Test Item Duplicate',
            'description' => 'Test Description',
            'pac' => 'TEST004',
            'unit_value' => 100,
            'quantity' => 30,
            'date_acquired' => now(),
            'po_number' => 'PO126',
            'category_id' => $supplyCategory->id,
            'location_id' => $location->id,
            'user_id' => $user->id,
        ]);

        // Run job first time
        $job = new CheckLowStockJob();
        $job->handle();

        // Verify one notification was created
        $this->assertDatabaseCount('notifications', 1);

        // Run job second time on same day
        $job = new CheckLowStockJob();
        $job->handle();

        // Should still have only one notification
        $this->assertDatabaseCount('notifications', 1);
    }

    /**
     * Test that the job works with "Consumables" category as well
     */
    public function test_job_works_with_consumables_category(): void
    {
        // Create Consumables category instead of Supply
        $consumablesCategory = Category::create([
            'category' => 'Consumables'
        ]);

        $location = Location::create(['location' => 'Test Location']);
        $user = User::create([
            'fullname' => 'Test User',
            'username' => 'testuser2',
            'email' => 'test2@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'location_id' => $location->id,
        ]);

        // Create item with low stock
        $lowStockItem = Item::create([
            'unit' => 'Test Consumable',
            'description' => 'Test Description',
            'pac' => 'TEST003',
            'unit_value' => 100,
            'quantity' => 25,
            'date_acquired' => now(),
            'po_number' => 'PO125',
            'category_id' => $consumablesCategory->id,
            'location_id' => $location->id,
            'user_id' => $user->id,
        ]);

        // Execute the job
        $job = new CheckLowStockJob();
        $job->handle();

        // Assert that notification was created
        $this->assertDatabaseHas('notifications', [
            'item_id' => $lowStockItem->id,
        ]);
    }

    /**
     * Test that items with null quantity are skipped
     */
    public function test_job_skips_items_with_null_quantity(): void
    {
        // Create supply category
        $supplyCategory = Category::create([
            'category' => 'Supply'
        ]);

        $location = Location::create(['location' => 'Test Location']);
        $user = User::create([
            'fullname' => 'Test User',
            'username' => 'testuser4',
            'email' => 'test4@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'location_id' => $location->id,
        ]);

        // Create item with null quantity
        $nullQuantityItem = Item::create([
            'unit' => 'Test Item Null',
            'description' => 'Test Description',
            'pac' => 'TEST005',
            'unit_value' => 100,
            'quantity' => null,
            'date_acquired' => now(),
            'po_number' => 'PO127',
            'category_id' => $supplyCategory->id,
            'location_id' => $location->id,
            'user_id' => $user->id,
        ]);

        // Execute the job
        $job = new CheckLowStockJob();
        $job->handle();

        // No notifications should be created
        $this->assertDatabaseCount('notifications', 0);
    }
}

