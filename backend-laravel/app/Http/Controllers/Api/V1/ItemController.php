<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BorrowTransaction;
use App\Models\Item;
use App\Models\MaintenanceRecord;
use App\Models\Condition;
use App\Http\Requests\V1\StoreItemRequest;
use App\Http\Requests\V1\UpdateItemRequest;
use App\Http\Resources\V1\ItemResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ItemCollection;
use App\Services\V1\ItemService;
use App\Services\V1\QrCodeService;
use App\Traits\LogsActivity;
use App\Jobs\CheckLowStockJob;
use App\Models\ItemUsage;
use App\Exports\MonitoringAssetsExport;
use App\Exports\ServiceableItemsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    use LogsActivity;
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Get all non-deleted items with all necessary relationships
            $items = Item::with([
                'qrCode',
                'category',
                'location',
                'condition',
                'condition_number',
                'user'
            ])->get();
            return new ItemCollection($items);
        } catch (\Exception $e) {
            \Log::error('Error fetching items: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch items: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
    
    /**
     * Get active (non-deleted) items
     */
    public function getActiveItems()
    {
        try {
            // Get all items with all necessary relationships
            $items = Item::with([
                'qrCode',
                'category',
                'location',
                'condition',
                'condition_number',
                'user'
            ])->get();
            
            return response()->json([
                'message' => 'Active items retrieved successfully',
                'status' => 'success',
                'data' => ItemResource::collection($items)
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error retrieving active items: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve active items: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreItemRequest $request)
    {
        
        $qrCodeService = new QrCodeService();

        $itemService = new ItemService();

        $newItem = Item::create($request->validated());

        $image = $request->file('image');

        if($image) {
            $itemService->handleImageUpload($newItem, $image);
        } 

        $itemWithQrCode = $qrCodeService->generateQrCode($newItem);

        // Log item creation
        $this->logItemActivity($request, 'Created', $newItem->unit ?? $newItem->description, $newItem->uuid);

        // Refresh item to get latest data
        $newItem->refresh();

        // Execute job immediately to check for low stock (especially for supply items)
        try {
            $job = new CheckLowStockJob();
            $job->handle();
        } catch (\Exception $e) {
            \Log::error("CheckLowStockJob failed: " . $e->getMessage());
        }

        return new ItemResource($itemWithQrCode);

        // Validate the incoming request
        // $validated = $request->validated();

        // // Save the item (example)
        // $item = Item::create($validated);

        // // Dispatch the QR code generation job
        // GenerateQRCodeJob::dispatch($item);

        // // Return the item as a resource
        // return new ItemResource($item);

    }

    /**
     * Display the specified resource.
     */
    public function show(Item $item)
    {
        $item->load([
            'qrCode',
            'category',
            'location',
            'condition',
            'condition_number',
            'user'
        ]);
        return new ItemResource($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateItemRequest $request, Item $item)
    {
        $itemService = new ItemService();
        
        // Store old quantity BEFORE updating (for usage tracking)
        $oldQuantity = $item->quantity;
        
        // Store old condition BEFORE updating (for maintenance record)
        $oldConditionId = $item->condition_id;
        $maintenanceReason = $request->input('maintenance_reason');
        
        // Update the item with validated data
        $item->update($request->validated());
        
        // Handle image upload if present
        $image = $request->file('image_path');
        if ($image) {
            $itemService->handleImageUpload($item, $image);
        }
        
        // Refresh item to get latest data after update
        $item->refresh();
        
        // Create maintenance record if condition changed to "On Maintenance"
        if ($request->has('condition_id') && $oldConditionId != $item->condition_id) {
            $onMaintenanceCondition = Condition::where('condition', 'On Maintenance')
                ->orWhere('condition', 'Under Maintenance')
                ->first();
            
            if ($onMaintenanceCondition && $item->condition_id == $onMaintenanceCondition->id && $maintenanceReason) {
                try {
                    // Find reason enum value or default to 'Other'
                    $reasonEnum = 'Other'; // Default reason
                    $reasonText = strtolower($maintenanceReason);
                    
                    if (strpos($reasonText, 'wet') !== false) {
                        $reasonEnum = 'Wet';
                    } elseif (strpos($reasonText, 'overheat') !== false || strpos($reasonText, 'over heat') !== false) {
                        $reasonEnum = 'Overheat';
                    } elseif (strpos($reasonText, 'wear') !== false) {
                        $reasonEnum = 'Wear';
                    } elseif (strpos($reasonText, 'electrical') !== false) {
                        $reasonEnum = 'Electrical';
                    }
                    
                    MaintenanceRecord::create([
                        'item_id' => $item->id,
                        'maintenance_date' => now()->toDateString(),
                        'reason' => $reasonEnum,
                        'condition_before_id' => $oldConditionId,
                        'condition_after_id' => $item->condition_id,
                        'technician_notes' => $maintenanceReason
                    ]);
                    
                    \Log::info("Maintenance record created for item {$item->id} with reason: {$maintenanceReason}");
                } catch (\Exception $e) {
                    \Log::error("Failed to create maintenance record: " . $e->getMessage());
                    // Don't fail the request if maintenance record creation fails
                }
            }
        }
        
        // Log item update
        $this->logItemActivity($request, 'Updated', $item->unit ?? $item->description, $item->uuid);
        
        // Track usage/restock if quantity changed
        if ($request->has('quantity')) {
            $newQuantity = $request->input('quantity');
            
            if ($newQuantity > $oldQuantity) {
                // Quantity increased - this is a restock
                $restockQty = $newQuantity - $oldQuantity;
                $this->trackItemRestock($item, $oldQuantity, $newQuantity, $restockQty);
            } elseif ($newQuantity < $oldQuantity) {
                // Quantity decreased - this is usage
                $usedQty = $oldQuantity - $newQuantity;
                $this->trackItemUsage($item, $oldQuantity, $newQuantity, $usedQty);
            }
        }
        
        // Execute job immediately to check for low stock (especially when quantity changes)
        try {
            $job = new CheckLowStockJob();
            $job->handle();
        } catch (\Exception $e) {
            \Log::error("CheckLowStockJob failed: " . $e->getMessage());
        }
        
        // Reload item with all relationships before returning
        $item->refresh();
        $item->load([
            'qrCode',
            'category',
            'location',
            'condition',
            'condition_number',
            'user'
        ]);
        
        // Return the updated item
        return new ItemResource($item);
    }

    /**
     * Batch update items with lifespan predictions
     * 
     * Expected request format:
     * {
     *     "predictions": [
     *         {
     *             "uuid": "item-uuid",
     *             "remaining_years": 5.2,
     *             "lifespan_estimate": 8.0
     *         }
     *     ]
     * }
     */
    public function updateLifespanPredictions(Request $request)
    {
        try {
            $predictions = $request->input('predictions', []);
            
            if (empty($predictions)) {
                return response()->json([
                    'message' => 'No predictions provided',
                    'status' => 'error'
                ], 400);
            }
            
            $updated = 0;
            $errors = [];
            
            foreach ($predictions as $prediction) {
                try {
                    $uuid = $prediction['uuid'] ?? null;
                    $remainingYears = $prediction['remaining_years'] ?? null;
                    $lifespanEstimate = $prediction['lifespan_estimate'] ?? null;
                    
                    if (!$uuid) {
                        $errors[] = 'Missing UUID in prediction';
                        continue;
                    }
                    
                    $item = Item::where('uuid', $uuid)->first();
                    
                    if (!$item) {
                        $errors[] = "Item not found with UUID: {$uuid}";
                        continue;
                    }
                    
                    // Build update data - always update if value is provided (even if 0)
                    $updateData = [];
                    if (isset($prediction['remaining_years'])) {
                        $updateData['remaining_years'] = (float) $remainingYears;
                    }
                    if (isset($prediction['lifespan_estimate'])) {
                        $updateData['lifespan_estimate'] = (float) $lifespanEstimate;
                    }
                    
                    if (!empty($updateData)) {
                        // Update the item
                        $item->update($updateData);
                        
                        // Log the update for debugging
                        \Log::info("Updated item {$uuid} ({$item->unit}) with remaining_years: {$updateData['remaining_years']}, lifespan_estimate: {$updateData['lifespan_estimate']}");
                        
                        $updated++;
                    } else {
                        \Log::warning("No update data provided for item {$uuid}");
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error updating item {$uuid}: " . $e->getMessage();
                    \Log::error("Error updating lifespan prediction for item {$uuid}: " . $e->getMessage(), [
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            return response()->json([
                'message' => "Successfully updated {$updated} items",
                'status' => 'success',
                'updated_count' => $updated,
                'total_predictions' => count($predictions),
                'errors' => $errors
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error batch updating lifespan predictions: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update lifespan predictions: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($identifier, Request $request)
    {
        try {
            // First try to find by UUID
            $item = Item::where('uuid', $identifier)->first();
            
            // If not found by UUID, try by ID
            if (!$item) {
                // Check if the identifier is numeric (likely an ID)
                if (is_numeric($identifier)) {
                    $item = Item::find($identifier);
                }
                
                // If still not found, throw an exception
                if (!$item) {
                    throw new \Exception("Item not found with identifier: {$identifier}");
                }
            }
            
            // Get deletion reason from request
            $deletionReason = $request->input('deletion_reason', 'No reason provided');
            
            // Soft delete the item with reason
            $item->update([
                'deletion_reason' => $deletionReason
            ]);
            $item->delete();
            
            // Log item deletion
            $this->logItemActivity($request, 'Deleted', $item->unit ?? $item->description, $item->uuid);
            
            return response()->json([
                'message' => 'Item deleted successfully',
                'status' => 'success',
                'deleted_item' => new ItemResource($item)
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error deleting item: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete item: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
    
    /**
     * Check if an item exists by UUID
     */
    public function checkItem($uuid)
    {
        try {
            $item = Item::with([
                'qrCode',
                'category',
                'location',
                'condition',
                'condition_number',
                'user'
            ])->where('uuid', $uuid)->firstOrFail();
            return response()->json([
                'message' => 'Item found',
                'status' => 'success',
                'item' => new ItemResource($item)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Item not found: ' . $e->getMessage(),
                'status' => 'error'
            ], 404);
        }
    }
    
    /**
     * Get all deleted items
     */
    public function getDeletedItems()
    {
        try {
            // Get all soft-deleted items with their relationships
            $deletedItems = Item::onlyTrashed()
                ->with(['location', 'condition', 'condition_number', 'category', 'user'])
                ->orderBy('deleted_at', 'desc')
                ->get();
            
            return response()->json([
                'message' => 'Deleted items retrieved successfully',
                'status' => 'success',
                'data' => ItemResource::collection($deletedItems)
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error retrieving deleted items: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve deleted items: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
    
    /**
     * Restore a deleted item
     */
    public function restoreItem($uuid, Request $request)
    {
        try {
            // Check if the item exists in the trashed items
            $item = Item::onlyTrashed()->where('uuid', $uuid)->first();
            
            if (!$item) {
                return response()->json([
                    'message' => 'Item not found in deleted items. It may have been permanently deleted.',
                    'status' => 'error'
                ], 404);
            }
            
            // Restore the item
            $item->restore();
            
            // Clear the deletion reason
            $item->update(['deletion_reason' => null]);
            
            // Log item restoration
            $this->logItemActivity($request, 'Restored', $item->unit ?? $item->description, $item->uuid);
            
            return response()->json([
                'message' => 'Item restored successfully. It will appear in the inventory.',
                'status' => 'success',
                'item' => new ItemResource($item)
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error restoring item: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to restore item: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
    
    /**
     * Permanently delete an item
     */
    public function forceDelete($uuid)
    {
        try {
            // Find the soft-deleted item
            $item = Item::onlyTrashed()->where('uuid', $uuid)->first();
            
            if (!$item) {
                return response()->json([
                    'message' => 'Item not found in deleted items.',
                    'status' => 'error'
                ], 404);
            }
            
            // Delete associated QR code if exists
            if ($item->qrCode) {
                $item->qrCode->delete();
            }
            
            // Delete associated image if exists
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            
            // Permanently delete the item
            $item->forceDelete();
            
            return response()->json([
                'message' => 'Item permanently deleted successfully',
                'status' => 'success'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error permanently deleting item: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to permanently delete item: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
    public function borrowItem(Request $request, $uuid)
{
    $item = Item::where('uuid', $uuid)->first();

    if (!$item) {
        return response()->json(['message' => 'Item not found.'], 404);
    }

    $request->validate([
        'quantity' => 'required|integer|min:1',
        'borrowed_by' => 'required|string',
    ]);

    if ($item->quantity < $request->quantity) {
        return response()->json(['message' => 'Not enough quantity available.'], 400);
    }

    // Store old quantity for usage tracking
    $oldQuantity = $item->quantity;

    // Subtract borrowed quantity
    $item->quantity -= $request->quantity;
    $item->save();

    // Track usage automatically
    $this->trackItemUsage($item, $oldQuantity, $item->quantity, $request->quantity);

    // Log the borrow transaction
    $borrow = BorrowTransaction::create([
        'item_id' => $item->id,
        'quantity' => $request->quantity,
        'borrowed_by' => $request->borrowed_by,
        'status' => 'borrowed',
    ]);

    // Log the borrow activity with detailed information
    $this->logBorrowActivity(
        $request,
        'Borrowed',
        $item->unit ?? $item->description,
        $request->quantity,
        $request->borrowed_by
    );

    // Execute job immediately to check for low stock after borrowing
    try {
        $job = new CheckLowStockJob();
        $job->handle();
    } catch (\Exception $e) {
        \Log::error("CheckLowStockJob failed: " . $e->getMessage());
    }

    return response()->json([
        'message' => 'Item borrowed successfully.',
        'remaining_quantity' => $item->quantity,
        'borrow' => $borrow,
    ], 200);
}

    /**
     * Export monitoring assets to Excel
     */
    public function exportMonitoringAssets(Request $request)
    {
        try {
            $category = $request->input('category');
            $location = $request->input('location');
            $itemsParam = $request->input('items'); // Optional: JSON string of items from frontend

            $fileName = 'Monitoring_Assets_' . date('Y-m-d_His') . '.xlsx';
            
            // Decode items if provided as JSON string
            $items = null;
            if ($itemsParam) {
                $decodedItems = is_string($itemsParam) ? json_decode($itemsParam, true) : $itemsParam;
                if (is_array($decodedItems) && count($decodedItems) > 0) {
                    $items = $decodedItems;
                }
            }
            
            if ($items) {
                // Export filtered items from frontend
                return Excel::download(new MonitoringAssetsExport($items, $category, $location), $fileName);
            } else {
                // Export all items based on filters
                return Excel::download(new MonitoringAssetsExport(null, $category, $location), $fileName);
            }
        } catch (\Exception $e) {
            \Log::error('Error exporting monitoring assets: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to export monitoring assets: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
}

    /**
     * Export serviceable items to Excel
     */
    public function exportServiceableItems(Request $request)
    {
        try {
            $itemsParam = $request->input('items'); // Optional: JSON string of items from frontend

            $fileName = 'Serviceable_Items_' . date('Y-m-d_His') . '.xlsx';
            
            // Decode items if provided as JSON string
            $items = null;
            if ($itemsParam) {
                $decodedItems = is_string($itemsParam) ? json_decode($itemsParam, true) : $itemsParam;
                if (is_array($decodedItems) && count($decodedItems) > 0) {
                    $items = $decodedItems;
                }
            }
            
            if ($items) {
                // Export filtered items from frontend
                return Excel::download(new ServiceableItemsExport($items), $fileName);
            } else {
                // Export all items
                return Excel::download(new ServiceableItemsExport(null), $fileName);
            }
        } catch (\Exception $e) {
            \Log::error('Error exporting serviceable items: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to export serviceable items: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Validate and update QR code for an item
     */
    public function validateQRCode(Request $request, $uuid)
    {
        try {
            // Find the item by UUID with QR code relationship
            $item = Item::with('qrCode')->where('uuid', $uuid)->first();
            
            if (!$item) {
                return response()->json([
                    'message' => 'Item not found.',
                    'status' => 'error'
                ], 404);
            }
            
            // Generate and save new QR code (version will auto-increment)
            $qrCodeService = new QrCodeService();
            $newQrCode = $qrCodeService->validateAndUpdateQrCode($item);
            
            // Refresh the item to get updated QR code with relationship
            $item->refresh();
            $item->load('qrCode');
            
            // Log the activity
            $this->logItemActivity($request, 'QR Code Validated', $item->description, $item->uuid);
            
            return response()->json([
                'message' => 'QR code validated and updated successfully',
                'status' => 'success',
                'data' => new ItemResource($item),
                'qr_code_data' => [
                    'id' => $newQrCode->id,
                    'version' => $newQrCode->version,
                    'is_active' => $newQrCode->is_active,
                    'image_path' => asset('storage/' . $newQrCode->image_path)
                ]
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error validating QR code: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to validate QR code: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Track item usage when quantity decreases
     *
     * @param Item $item
     * @param int $oldQuantity
     * @param int $newQuantity
     * @param int $usedQty
     * @return void
     */
    private function trackItemUsage(Item $item, int $oldQuantity, int $newQuantity, int $usedQty): void
    {
        try {
            // Get current period (auto-calculated by ItemUsage model)
            $period = ItemUsage::getCurrentPeriod();    

            // Find or create usage record for this period
            $usage = ItemUsage::firstOrCreate(
                [
                    'item_id' => $item->id,
                    'period' => $period,
                ],
                [
                    'stock_start' => $oldQuantity,
                    'usage' => 0,
                    'stock_end' => $newQuantity,
                ]
            );

            // Update usage record
            $usage->usage += $usedQty;
            $usage->stock_end = $newQuantity;
            
            // Set stock_start if it's null (first usage in this period)
            if ($usage->stock_start === null) {
                $usage->stock_start = $oldQuantity;
            }
            
            $usage->save();

            \Log::info("Tracked usage for item {$item->id} in {$period}: {$usedQty} units used");
        } catch (\Exception $e) {
            \Log::error("Failed to track item usage: " . $e->getMessage());
            // Don't fail the request if tracking fails
        }
    }

    /**
     * Track item restock when quantity increases
     *
     * @param Item $item
     * @param int $oldQuantity
     * @param int $newQuantity
     * @param int $restockQty
     * @return void
     */
    private function trackItemRestock(Item $item, int $oldQuantity, int $newQuantity, int $restockQty): void
    {
        try {
            // Get current period (auto-calculated by ItemUsage model)
            $period = ItemUsage::getCurrentPeriod();

            // Find or create usage record for this period
            $usage = ItemUsage::firstOrCreate(
                [
                    'item_id' => $item->id,
                    'period' => $period,
                ],
                [
                    'stock_start' => $oldQuantity,
                    'usage' => 0,
                    'stock_end' => $newQuantity,
                    'restocked' => false,
                    'restock_qty' => 0,
                ]
            );

            // Update restock information
            $usage->restocked = true;
            $usage->restock_qty += $restockQty;
            $usage->stock_end = $newQuantity;
            
            // Set stock_start if it's null (first restock in this period)
            if ($usage->stock_start === null) {
                $usage->stock_start = $oldQuantity;
            }
            
            $usage->save();

            \Log::info("Tracked restock for item {$item->id} in {$period}: {$restockQty} units restocked");
        } catch (\Exception $e) {
            \Log::error("Failed to track item restock: " . $e->getMessage());
            // Don't fail the request if tracking fails
        }
    }
}
