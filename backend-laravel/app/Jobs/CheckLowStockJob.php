<?php

namespace App\Jobs;

use App\Models\Item;
use App\Models\Notification;
use App\Models\Category;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckLowStockJob implements ShouldQueue
{
    use Queueable;

    /**
     * The threshold for low stock (quantity below this will trigger a notification)
     */
    private const THRESHOLD = 50;
    
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // Set connection to sync to run immediately
        $this->connection = 'sync';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Find the "Supply" category only (case-insensitive)
            $supplyCategory = Category::whereRaw('LOWER(category) = ?', ['supply'])->first();

            if (!$supplyCategory) {
                Log::warning('Supply category not found. Skipping low stock check.');
                return;
            }

            // Get all supply items with quantity less than threshold
            $lowStockItems = Item::where('category_id', $supplyCategory->id)
                ->where('quantity', '<', self::THRESHOLD)
                ->whereNotNull('quantity')
                ->get();

            if ($lowStockItems->count() === 0) {
                return;
            }

            foreach ($lowStockItems as $item) {
                // Create the notification message in the specified format
                $message = "Low stock alert: {$item->unit} (only {$item->quantity} remaining)";
                
                // Check if a duplicate notification exists: same message AND same item_id for the same date
                // This prevents creating duplicate notifications with the same message for the same item on the same day
                $existingNotification = Notification::where('item_id', $item->id)
                    ->where('message', $message)
                    ->whereDate('created_at', today())
                    ->first();

                if ($existingNotification) {
                    continue;
                }

                try {
                    // Double-check before creating (race condition protection)
                    $doubleCheck = Notification::where('item_id', $item->id)
                        ->where('message', $message)
                        ->whereDate('created_at', today())
                        ->first();
                    if ($doubleCheck) {
                        continue;
                    }
                    
                    $notification = Notification::create([
                        'item_id' => $item->id,
                        'message' => $message,
                        'type' => 'low_stock',
                        'is_read' => false,
                        // user_id is null - goes to all admins
                    ]);

                    if ($notification && $notification->notification_id) {
                        Log::info("Created low stock notification for item: {$item->unit} (ID: {$item->id}, Quantity: {$item->quantity})");
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to create notification for item {$item->id}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Error in CheckLowStockJob: ' . $e->getMessage());
            throw $e;
        }
    }
}
