<?php

namespace App\Jobs;

use App\Models\Item;
use App\Models\Category;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class CalculateLifespanJob implements ShouldQueue
{
    use Queueable;

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
            Log::info('Starting lifespan calculation job...');
            
            // Get Python API URL from config or env
            $pythonApiUrl = env('PY_API_BASE_URL', 'http://127.0.0.1:5000');
            
            // Get all non-consumable items (exclude Supply category)
            $supplyCategory = Category::whereRaw('LOWER(category) = ?', ['supply'])->first();
            
            $itemsQuery = Item::with(['category', 'condition', 'condition_number', 'maintenance_records'])
                ->whereNull('deleted_at');
            
            if ($supplyCategory) {
                $itemsQuery->where('category_id', '!=', $supplyCategory->id);
            }
            
            $items = $itemsQuery->get();
            
            if ($items->count() === 0) {
                Log::info('No items found for lifespan calculation.');
                return;
            }
            
            Log::info("Found {$items->count()} items to calculate lifespan for.");
            
            // Helper function to extract numeric value from condition_number string
            $extractConditionNumber = function($conditionNumberStr) {
                if (!$conditionNumberStr) return 0;
                if (is_numeric($conditionNumberStr)) {
                    return (int) $conditionNumberStr;
                }
                // Extract number from string like "A1" -> 1, "3" -> 3
                preg_match('/\d+/', (string) $conditionNumberStr, $matches);
                return $matches ? (int) $matches[0] : 0;
            };
            
            // Build payload for Python API
            $payload = [
                'items' => []
            ];
            
            foreach ($items as $item) {
                // Calculate years in use
                $acquisitionDate = $item->date_acquired ? new \DateTime($item->date_acquired) : new \DateTime();
                $today = new \DateTime();
                $diff = $today->diff($acquisitionDate);
                $yearsInUse = $diff->y + ($diff->m / 12) + ($diff->d / 365.25);
                
                // Get maintenance count
                $maintenanceCount = $item->maintenance_count ?? $item->maintenance_records->count();
                
                // Get condition number (A1=1, A2=2, A3=3) or R
                $conditionNumber = 0;
                $conditionNumberStr = '';
                if ($item->condition_number && $item->condition_number->condition_number) {
                    $conditionNumberStr = strtoupper(trim($item->condition_number->condition_number));
                    // Extract A1 -> 1, A2 -> 2, A3 -> 3, or keep R as string
                    if (preg_match('/A(\d+)/', $conditionNumberStr, $matches)) {
                        $conditionNumber = (int) $matches[1];
                    } elseif ($conditionNumberStr === 'R') {
                        $conditionNumber = 'R'; // Keep R as string for disposal check
                    } else {
                        $conditionNumber = $extractConditionNumber($conditionNumberStr);
                    }
                }
                
                // Get condition_status from condition_number (Good, Less Reliable, Un-operational, Disposal)
                $conditionStatus = $item->condition_number->condition_status ?? '';
                
                // Get condition from condition table (Serviceable, Non-Serviceable, On Maintenance)
                $condition = $item->condition ? $item->condition->condition : '';
                
                // Get last maintenance reason
                $lastReason = $item->maintenance_reason ?? '';
                if (empty($lastReason) && $item->maintenance_records && $item->maintenance_records->count() > 0) {
                    $lastRecord = $item->maintenance_records->sortByDesc('maintenance_date')->first();
                    if ($lastRecord) {
                        $lastReason = $lastRecord->reason ?? $lastRecord->technician_notes ?? '';
                    }
                }
                
                $payload['items'][] = [
                    'item_id' => $item->id,
                    'category' => $item->category ? $item->category->category : 'Unknown',
                    'years_in_use' => max(0, $yearsInUse),
                    'maintenance_count' => $maintenanceCount,
                    'condition_number' => $conditionNumber,
                    'condition_status' => $conditionStatus,
                    'condition' => $condition,
                    'last_reason' => $lastReason
                ];
            }
            
            // Call Python API
            Log::info("Calling Python API at {$pythonApiUrl}/predict/items/lifespan");
            $response = Http::timeout(30)->post("{$pythonApiUrl}/predict/items/lifespan", $payload);
            
            if (!$response->successful()) {
                throw new Exception("Python API request failed: " . $response->body());
            }
            
            $result = $response->json();
            
            if (!isset($result['success']) || !$result['success']) {
                throw new Exception("Python API returned error: " . ($result['error'] ?? 'Unknown error'));
            }
            
            if (!isset($result['predictions']) || empty($result['predictions'])) {
                Log::warning('Python API returned no predictions.');
                return;
            }
            
            // Update items with predictions
            $updated = 0;
            $errors = [];
            
            foreach ($result['predictions'] as $prediction) {
                try {
                    $itemId = $prediction['item_id'] ?? null;
                    $remainingYears = $prediction['remaining_years'] ?? null;
                    $lifespanEstimate = $prediction['lifespan_estimate'] ?? null;
                    
                    if (!$itemId) {
                        $errors[] = 'Missing item_id in prediction';
                        continue;
                    }
                    
                    $item = Item::find($itemId);
                    if (!$item) {
                        $errors[] = "Item not found with ID: {$itemId}";
                        continue;
                    }
                    
                    $updateData = [];
                    if (isset($prediction['remaining_years'])) {
                        $updateData['remaining_years'] = (float) $remainingYears;
                    }
                    if (isset($prediction['lifespan_estimate'])) {
                        $updateData['lifespan_estimate'] = (float) $lifespanEstimate;
                    }
                    
                    if (!empty($updateData)) {
                        // Force refresh to ensure we have latest data
                        $item->refresh();
                        
                        // Update the item
                        $result = $item->update($updateData);
                        
                        // Verify the update was successful
                        $item->refresh();
                        $actualValue = $item->remaining_years;
                        
                        if ($actualValue != null && abs($actualValue - $updateData['remaining_years']) < 0.01) {
                            $updated++;
                            Log::info("✅ Updated item {$item->uuid} ({$item->unit}) with remaining_years: {$updateData['remaining_years']}");
                        } else {
                            $errors[] = "Item {$itemId} update verification failed. Expected: {$updateData['remaining_years']}, Got: {$actualValue}";
                            Log::warning("⚠️ Item {$item->uuid} update may have failed. Expected: {$updateData['remaining_years']}, Actual: {$actualValue}");
                        }
                    } else {
                        $errors[] = "Item {$itemId} has no update data";
                        Log::warning("No update data for item {$itemId}");
                    }
                } catch (Exception $e) {
                    $errors[] = "Error updating item {$itemId}: " . $e->getMessage();
                    Log::error("Error updating lifespan for item {$itemId}: " . $e->getMessage());
                }
            }
            
            Log::info("Lifespan calculation job completed. Updated {$updated} items. Errors: " . count($errors));
            
            if (!empty($errors)) {
                Log::warning("Some items failed to update: " . implode(', ', $errors));
            }
            
        } catch (Exception $e) {
            Log::error('Error in CalculateLifespanJob: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}

