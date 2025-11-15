<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BorrowTransaction;
use App\Models\BorrowRequest;
use App\Models\Item;
use App\Models\MaintenanceRecord;
use App\Models\Condition;
use App\Models\DeletedItem;
use App\Models\User;
use App\Models\Location;
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
use App\Exports\LifeCyclesDataExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Events\ItemBorrowed;
use App\Events\BorrowRequestCreated;
use App\Events\ItemUpdated;
use App\Models\Notification;

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

        $itemService = new ItemService();@
        $newItem = Item::create($request->validated());

        $image = $request->file('image');

        if($image) {
            $itemService->handleImageUpload($newItem, $image);
        } 

        $itemWithQrCode = $qrCodeService->generateQrCode($newItem);

        // Refresh item to get latest data with relationships
        $newItem->refresh();
        $newItem->load(['category', 'location', 'condition']);
        
        // Log item creation with detailed information
        $itemName = $newItem->unit ?? $newItem->description;
        $categoryName = $newItem->category ? $newItem->category->name : 'N/A';
        $locationName = $newItem->location ? $newItem->location->name : 'N/A';
        $description = "Added item '{$itemName}' (Quantity: {$newItem->quantity}, Category: {$categoryName}, Location: {$locationName})";
        $this->logActivity($request, 'Added Item', $description);

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
        $maintenanceReason = $request->input('maintenance_reason'); // Enum value (Overheat, Wear, etc.)
        $technicianNotes = $request->input('technician_notes'); // Detailed notes
        
        // Get validated data and remove maintenance_reason before updating items table
        // (maintenance_reason will be saved to maintenance_records.reason instead)
        $validatedData = $request->validated();
        unset($validatedData['maintenance_reason']); // Remove from items update
        unset($validatedData['technician_notes']); // Remove from items update (not in items table)
        
        // Update the item with validated data (without maintenance_reason)
        $item->update($validatedData);
        
        // Handle image upload if present
        $image = $request->file('image_path');
        if ($image) {
            $itemService->handleImageUpload($item, $image);
        }
        
        // Refresh item to get latest data after update
        $item->refresh();
        
        // Handle maintenance records for "On Maintenance" condition
        // maintenance_reason -> items.maintenance_reason (already saved via validated data)
        // technician_notes -> maintenance_records.technician_notes
        // maintenance_reason enum -> maintenance_records.reason
        $onMaintenanceCondition = Condition::where('condition', 'On Maintenance')
            ->orWhere('condition', 'Under Maintenance')
            ->first();
        
        // Check if item is on maintenance (current condition or newly set)
        $isOnMaintenance = $onMaintenanceCondition && $item->condition_id == $onMaintenanceCondition->id;
        
        if ($isOnMaintenance && $maintenanceReason && $technicianNotes) {
            try {
                // Use maintenance_reason as the enum value directly (already validated)
                $reasonEnum = $maintenanceReason ?: 'Other';
                
                // Check if condition changed to "On Maintenance" (new maintenance record)
                if ($request->has('condition_id') && $oldConditionId != $item->condition_id) {
                    // Create new maintenance record when condition changes to "On Maintenance"
                    MaintenanceRecord::create([
                        'item_id' => $item->id,
                        'maintenance_date' => now()->toDateString(),
                        'reason' => $reasonEnum,
                        'condition_before_id' => $oldConditionId,
                        'condition_after_id' => $item->condition_id,
                        'technician_notes' => $technicianNotes
                    ]);
                    
                    \Log::info("Maintenance record created for item {$item->id} with reason: {$reasonEnum} and technician notes");
                } elseif ($request->has('maintenance_reason') || $request->has('technician_notes')) {
                    // Update the latest maintenance record if item is already on maintenance
                    $latestMaintenanceRecord = MaintenanceRecord::where('item_id', $item->id)
                        ->latest('maintenance_date')
                        ->first();
                    
                    if ($latestMaintenanceRecord) {
                        $latestMaintenanceRecord->update([
                            'technician_notes' => $technicianNotes,
                            'reason' => $reasonEnum
                        ]);
                        
                        \Log::info("Updated maintenance record {$latestMaintenanceRecord->id} for item {$item->id} with reason: {$reasonEnum}");
                    } else {
                        // No existing record, create one
                        MaintenanceRecord::create([
                            'item_id' => $item->id,
                            'maintenance_date' => now()->toDateString(),
                            'reason' => $reasonEnum,
                            'condition_before_id' => $item->condition_id,
                            'condition_after_id' => $item->condition_id,
                            'technician_notes' => $technicianNotes
                        ]);
                        
                        \Log::info("Created new maintenance record for item {$item->id} (already on maintenance) with reason: {$reasonEnum}");
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed to create/update maintenance record: " . $e->getMessage());
                // Don't fail the request if maintenance record creation fails
            }
        }
        
        // Refresh item to get latest data with relationships
        $item->load(['category', 'location', 'condition']);
        
        // Log item update with detailed information
        $itemName = $item->unit ?? $item->description;
        $updateDetails = [];
        
        // Check what was updated
        if ($request->has('quantity') && $oldQuantity != $item->quantity) {
            $updateDetails[] = "Quantity: {$oldQuantity} → {$item->quantity}";
        }
        if ($request->has('condition_id') && $oldConditionId != $item->condition_id) {
            $oldCondition = $oldConditionId ? Condition::find($oldConditionId)?->condition : 'N/A';
            $newCondition = $item->condition ? $item->condition->condition : 'N/A';
            $updateDetails[] = "Condition: {$oldCondition} → {$newCondition}";
        }
        if ($request->has('unit')) {
            $updateDetails[] = "Unit updated";
        }
        if ($request->has('description')) {
            $updateDetails[] = "Description updated";
        }
        if ($request->has('location_id')) {
            $locationName = $item->location ? $item->location->name : 'N/A';
            $updateDetails[] = "Location: {$locationName}";
        }
        
        $updateInfo = !empty($updateDetails) ? ' (' . implode(', ', $updateDetails) . ')' : '';
        $description = "Edited item '{$itemName}'{$updateInfo}";
        
        $this->logActivity($request, 'Edited Item', $description);
        
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
                        // Only update columns that exist in the database
                        // Filter out any columns that don't exist in the fillable array or schema
                        $fillableColumns = $item->getFillable();
                        $updateData = array_intersect_key($updateData, array_flip($fillableColumns));
                        
                        if (!empty($updateData)) {
                            // Update the item
                            $item->update($updateData);
                            
                            // Log the update for debugging
                            $logData = [];
                            if (isset($updateData['remaining_years'])) {
                                $logData[] = "remaining_years: {$updateData['remaining_years']}";
                            }
                            if (isset($updateData['lifespan_estimate'])) {
                                $logData[] = "lifespan_estimate: {$updateData['lifespan_estimate']}";
                            }
                            \Log::info("Updated item {$uuid} ({$item->unit}) with " . implode(', ', $logData));
                            
                            $updated++;
                        } else {
                            \Log::warning("No valid columns to update for item {$uuid}. Update data filtered out.");
                        }
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
            
            // Get the current user who is deleting the item
            $userId = null;
            if ($request->user()) {
                $userId = $request->user()->id;
            } else {
                // If no authenticated user, try to use the item's owner (user_id)
                if ($item->user_id) {
                    // Verify the user exists
                    $user = User::find($item->user_id);
                    if ($user) {
                        $userId = $item->user_id;
                    }
                }
                
                // If still no user, find the first available user
                if (!$userId) {
                    $firstUser = User::first();
                    if ($firstUser) {
                        $userId = $firstUser->id;
                    }
                }
            }
            
            // If we still don't have a valid user_id, throw an error
            if (!$userId) {
                throw new \Exception('Cannot delete item: No valid user found. Please ensure at least one user exists in the system.');
            }
            
            // Soft delete the item
            $item->delete();
            
            // Create record in deleted_items table
            DeletedItem::create([
                'item_id' => $item->id,
                'reason_for_deletion' => $deletionReason,
                'user_id' => $userId
            ]);
            
            // Load relationships before deletion
            $item->load(['category', 'location', 'condition']);
            
            // Log item deletion with detailed information
            $itemName = $item->unit ?? $item->description;
            $categoryName = $item->category ? $item->category->name : 'N/A';
            $locationName = $item->location ? $item->location->name : 'N/A';
            $description = "Deleted item '{$itemName}' (Quantity: {$item->quantity}, Category: {$categoryName}, Location: {$locationName}, Reason: {$deletionReason})";
            
            // Log activity directly if user is authenticated, otherwise create log manually
            if ($request->user()) {
                $this->logActivity($request, 'Deleted Item', $description);
            } else {
                // Create activity log directly when no authenticated user
                try {
                    $activityLog = \App\Models\ActivityLog::create([
                        'user_id' => $userId,
                        'action' => 'Deleted Item',
                        'description' => $description,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                    
                    // Broadcast event for real-time updates
                    try {
                        event(new \App\Events\ActivityLogCreated($activityLog));
                    } catch (\Exception $e) {
                        \Log::warning("Failed to broadcast ActivityLogCreated event: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to log delete activity: ' . $e->getMessage());
                }
            }
            
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
            
            // Load maintenance_records for technician notes
            $item->load('maintenance_records');
            
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
            // Get all soft-deleted items with their relationships and deleted_items records
            $deletedItems = Item::onlyTrashed()
                ->with(['location', 'condition', 'condition_number', 'category', 'user', 'deletedItemRecord'])
                ->orderBy('deleted_at', 'desc')
                ->get();
            
            // Manually attach reason_for_deletion to each item from deletedItemRecord
            $deletedItems->each(function ($item) {
                if ($item->deletedItemRecord) {
                    $item->reason_for_deletion = $item->deletedItemRecord->reason_for_deletion;
                    $item->deleted_by_user_id = $item->deletedItemRecord->user_id;
                }
            });
            
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
            
            // Delete the record from deleted_items table
            DeletedItem::where('item_id', $item->id)->delete();
            
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
            
            // Delete the record from deleted_items table
            DeletedItem::where('item_id', $item->id)->delete();
            
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
    $item = null;
    $oldQuantity = 0;
    $newQuantity = 0;
    
    try {
        // Note: Authentication is optional for mobile apps scanning QR codes
        // If user is authenticated, use their info; otherwise proceed without user context
        // Sanctum will automatically authenticate if Bearer token is present in Authorization header
        
        // Manually authenticate user if Bearer token is present
        // Since this is a public route, we need to manually authenticate
        $user = null;
        $bearerToken = $request->bearerToken();
        
        if ($bearerToken) {
            try {
                // Find the token in the database
                $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
                if ($tokenModel && $tokenModel->tokenable) {
                    $user = $tokenModel->tokenable; // Get the user model
                    
                    // Verify token hasn't expired (if expiration is set)
                    if (!$tokenModel->expires_at || $tokenModel->expires_at->isFuture()) {
                        \Log::info('Borrow: Authenticated user via token - ' . $user->fullname . ' (ID: ' . $user->id . ', Role: ' . ($user->role ?? 'N/A') . ')');
                    } else {
                        \Log::warning('Borrow: Token expired for user ID: ' . $tokenModel->tokenable_id);
                        $user = null;
                    }
                } else {
                    \Log::warning('Borrow: Invalid token - token not found in database');
                }
            } catch (\Exception $e) {
                \Log::warning('Borrow: Failed to authenticate token - ' . $e->getMessage());
            }
        }
        
        // Try standard Sanctum method as fallback (won't work on public routes without middleware)
        if (!$user) {
            try {
                $user = $request->user();
                if ($user) {
                    \Log::info('Borrow: Authenticated user via request->user() - ' . $user->fullname . ' (ID: ' . $user->id . ')');
                }
            } catch (\Exception $e) {
                // Ignore - this is expected on public routes
            }
        }
        
        if (!$user) {
            $tokenInfo = $bearerToken ? 'Yes (Length: ' . strlen($bearerToken) . ' chars, Prefix: ' . substr($bearerToken, 0, 20) . '...)' : 'No';
            \Log::warning('Borrow: No authenticated user found. Token present: ' . $tokenInfo);
            \Log::warning('Borrow: Request headers - Authorization: ' . ($request->header('Authorization') ? 'Present' : 'Missing'));
            \Log::warning('Borrow: All headers - ' . json_encode($request->headers->all()));
        }
        
        // Validate input
        $validated = $request->validate([
        'quantity' => 'required|integer|min:1',
        'borrowed_by' => 'required|string',
    ]);

        // Find item
        $item = Item::where('uuid', $uuid)->firstOrFail();

        // Check quantity
        if ($item->quantity < $validated['quantity']) {
            return response()->json([
                'message' => 'Not enough quantity available.',
                'status' => 'error'
            ], 400);
    }

        // Store old quantity for tracking
    $oldQuantity = $item->quantity;

        // Update quantity - THIS IS THE CRITICAL OPERATION
        $item->quantity -= $validated['quantity'];
        
        // Attempt to save with error handling
        try {
            $item->save();
        } catch (\Exception $e) {
            \Log::error("Failed to save item quantity update: " . $e->getMessage());
            \Log::error("Item ID: {$item->id}, UUID: {$item->uuid}, Old Qty: {$oldQuantity}, Attempted Qty: " . ($oldQuantity - $validated['quantity']));
            return response()->json([
                'message' => 'Failed to update quantity: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
        
        $newQuantity = $item->quantity;

        // Prepare success response FIRST
        $responseData = [
            'message' => 'Item borrowed successfully.',
            'status' => 'success',
            'remaining_quantity' => $newQuantity,
            'item' => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'quantity' => $newQuantity,
            ]
        ];

        // Refresh and load relationships for potential use in logging/broadcasting
        try {
            $item->refresh();
            $item->load(['category', 'location', 'condition', 'condition_number', 'qrCode', 'user']);
        } catch (\Exception $e) {
            \Log::warning("Failed to refresh item relationships: " . $e->getMessage());
        }

        // All non-critical operations below - they won't affect the response
        
        // Track usage (non-critical)
        try {
            $this->trackItemUsage($item, $oldQuantity, $newQuantity, $validated['quantity']);
        } catch (\Exception $e) {
            \Log::warning("Failed to track item usage: " . $e->getMessage());
        }

        // Log borrow transaction (non-critical)
        $borrow = null;
        try {
    $borrow = BorrowTransaction::create([
        'item_id' => $item->id,
                'quantity' => $validated['quantity'],
                'borrowed_by' => $validated['borrowed_by'],
        'status' => 'borrowed',
    ]);
            $responseData['borrow'] = $borrow;
        } catch (\Exception $e) {
            \Log::warning("Failed to create borrow transaction: " . $e->getMessage());
        }

        // Log activity (non-critical)
        try {
            // Load item relationships for detailed logging
            $item->load(['category', 'location', 'condition']);
            
            // Get personnel from item's location (if available)
            $personnel = null;
            if ($item->location && $item->location->personnel) {
                $personnel = $item->location->personnel;
            } elseif ($item->user && $item->user->fullname) {
                // Fallback to item's assigned user
                $personnel = $item->user->fullname;
            }
            
            // Get item details for enhanced description
            $itemName = $item->unit ?? $item->description;
            $categoryName = $item->category ? $item->category->name : 'N/A';
            $locationName = $item->location ? $item->location->name : 'N/A';
            
            // Create detailed description
            $description = "Borrowed item '{$itemName}' (Quantity: {$validated['quantity']}, Category: {$categoryName}, Location: {$locationName}, Remaining: {$newQuantity})";
            if ($personnel) {
                $description .= " by {$personnel}";
            }
            
            // Use logActivity directly with detailed description
            // If user is authenticated, use their ID; otherwise try to find by personnel name
            $logUser = $user;
            if (!$logUser && $personnel) {
                $logUser = \App\Models\User::where(function($query) use ($personnel) {
                    $query->where('fullname', $personnel)
                        ->orWhere('username', $personnel)
                        ->orWhere('email', $personnel);
                })->first();
            }
            
            // Create activity log directly
            if ($logUser) {
                try {
                    $activityLog = \App\Models\ActivityLog::create([
                        'user_id' => $logUser->id,
                        'action' => 'Borrowed Item',
                        'description' => $description,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                    
                    // Broadcast event for real-time updates
                    try {
                        event(new \App\Events\ActivityLogCreated($activityLog));
                    } catch (\Exception $e) {
                        \Log::warning("Failed to broadcast ActivityLogCreated event: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to log borrow activity: ' . $e->getMessage());
                }
            } else {
                // Fallback to logBorrowActivity if no user found
                $this->logBorrowActivity(
                    $request,
                    'Borrowed Item',
                    $itemName,
                    $validated['quantity'],
                    $personnel,
                    $user
                );
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to log borrow activity: " . $e->getMessage());
        }

        // Broadcast event for real-time updates (non-critical)
        try {
            \Log::info("Broadcasting ItemBorrowed event for item: {$item->id}, UUID: {$item->uuid}, New Quantity: {$newQuantity}");
            event(new ItemBorrowed($item, $validated['quantity'], $validated['borrowed_by']));
            \Log::info("ItemBorrowed event broadcasted successfully");
        } catch (\Exception $e) {
            \Log::error("Failed to broadcast ItemBorrowed event: " . $e->getMessage());
            // Continue - broadcasting failure shouldn't fail the request
        }

        // Check low stock job (non-critical)
    try {
        $job = new CheckLowStockJob();
        $job->handle();
    } catch (\Exception $e) {
            \Log::warning("CheckLowStockJob failed: " . $e->getMessage());
    }

        // Return success response - quantity was updated successfully
        return response()->json($responseData, 200);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Re-throw validation exceptions for proper 422 response
    return response()->json([
            'message' => 'Validation failed.',
            'errors' => $e->errors(),
            'status' => 'error'
        ], 422);
    } catch (\Exception $e) {
        \Log::error("Error in borrowItem: " . $e->getMessage());
        \Log::error("Exception trace: " . $e->getTraceAsString());
        
        // If quantity was already updated, still return success
        if ($item && $newQuantity > 0) {
            \Log::warning("Exception occurred but quantity was already updated. Returning success.");
            return response()->json([
                'message' => 'Item borrowed successfully. (Some operations may have failed)',
                'status' => 'success',
                'remaining_quantity' => $newQuantity,
    ], 200);
        }
        
        // Only return error if quantity wasn't updated
        return response()->json([
            'message' => 'Failed to borrow item: ' . $e->getMessage(),
            'status' => 'error'
        ], 500);
    }
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
     * Export lifecycle data to Excel
     */
    public function exportLifeCyclesData(Request $request)
    {
        try {
            $itemsParam = $request->input('items'); // Optional: JSON string of items from frontend

            $fileName = 'Life_Cycles_Data_' . date('Y-m-d_His') . '.xlsx';
            
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
                return Excel::download(new LifeCyclesDataExport($items), $fileName);
            } else {
                // Export all items with lifespan data
                return Excel::download(new LifeCyclesDataExport(null), $fileName);
            }
        } catch (\Exception $e) {
            \Log::error('Error exporting lifecycle data: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to export lifecycle data: ' . $e->getMessage(),
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
            
            // Load relationships
            $item->load(['category', 'location']);
            
            // Log the scan activity with detailed information
            $itemName = $item->unit ?? $item->description;
            $categoryName = $item->category ? $item->category->name : 'N/A';
            $locationName = $item->location ? $item->location->name : 'N/A';
            $description = "Scanned item '{$itemName}' (Category: {$categoryName}, Location: {$locationName}, Quantity Available: {$item->quantity})";
            
            $this->logActivity($request, 'Scanned Item', $description);
            
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

    /**
     * Create a borrow request
     */
    public function createBorrowRequest(Request $request, $itemId)
    {
        try {
            \Log::info('createBorrowRequest called with itemId: ' . $itemId);
            \Log::info('Request data: ' . json_encode($request->all()));
            
            $validated = $request->validate([
                'quantity' => 'required|integer|min:1',
                'location' => 'required|string|max:255',
                'borrowed_by' => 'required|string|max:255',
                'send_to' => 'required|string|max:255', // Location name (e.g., "Finance", "PIO") of the admin who should receive this request
                'requested_by_user_id' => 'nullable|integer|exists:users,id', // Optional: user ID from mobile app
                'user_id' => 'nullable|integer|exists:users,id', // Alternative field name
            ]);

            // Get authenticated user (who scanned/created the request)
            // Since the route requires auth:sanctum middleware, the user is automatically authenticated
            // when the mobile app sends the Bearer token in the Authorization header
            $user = $request->user();
            
            // Log user information for debugging
            if ($user) {
                \Log::info('BorrowRequest: Authenticated user - ' . ($user->fullname ?? 'N/A') . ' (ID: ' . $user->id . ', Email: ' . ($user->email ?? 'N/A') . ')');
            } else {
                // This should not happen since route requires auth:sanctum, but log if it does
                \Log::error('BorrowRequest: CRITICAL - No authenticated user found even though route requires auth:sanctum middleware!');
                \Log::error('BorrowRequest: This means the mobile app did not send a valid Bearer token.');
                \Log::error('BorrowRequest: The request should have been rejected with 401 Unauthorized before reaching this point.');
                
                // Return error since we need to know who sent the request
                return response()->json([
                    'message' => 'Authentication required. Please send a valid Bearer token in the Authorization header.',
                    'error' => 'No authenticated user found',
                ], 401);
            }

            // Find item by UUID or ID
            // Check if it's a UUID format (contains hyphens) or numeric ID
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $itemId)) {
                // It's a UUID - search by UUID only
                $item = Item::where('uuid', $itemId)->first();
            } else {
                // It's numeric - search by ID only
                $item = Item::where('id', $itemId)->first();
            }
            \Log::info('Item lookup result: ' . ($item ? 'Found (ID: ' . $item->id . ', UUID: ' . ($item->uuid ?? 'N/A') . ')' : 'Not found'));
            
            if (!$item) {
                return response()->json([
                    'message' => 'Item not found',
                    'searched_item_id' => $itemId,
                ], 404);
            }

            // Check if quantity is available
            $currentQuantity = intval($item->quantity ?? 0);
            if ($validated['quantity'] > $currentQuantity) {
                return response()->json([
                    'message' => 'Requested quantity exceeds available stock',
                    'available' => $currentQuantity,
                ], 400);
            }

            // Look up the location by name to get its ID
            \Log::info("Looking up location by name: '{$validated['send_to']}'");
            $sendToLocation = Location::where('location', $validated['send_to'])->first();
            if (!$sendToLocation) {
                \Log::warning("Location not found: '{$validated['send_to']}'");
                return response()->json([
                    'message' => 'Location not found',
                    'error' => "The location '{$validated['send_to']}' does not exist. Please check the location name.",
                    'send_to' => $validated['send_to'],
                ], 404);
            }
            \Log::info("Location found: ID={$sendToLocation->id}, Name='{$sendToLocation->location}'");

            // Create borrow request with the user who scanned/created it
            // Log user information for debugging
            if ($user) {
                \Log::info("Creating borrow request with authenticated user: {$user->fullname} (ID: {$user->id}, Email: {$user->email})");
            } else {
                \Log::warning("Creating borrow request WITHOUT authenticated user - requested_by_user_id will be NULL");
            }
            
            try {
                $borrowRequest = BorrowRequest::create([
                    'item_id' => $item->uuid ?? $item->id,
                    'quantity' => $validated['quantity'],
                    'location' => $validated['location'],
                    'borrowed_by' => $validated['borrowed_by'],
                    'send_to' => $sendToLocation->id, // Store location ID (looked up from location name)
                    'requested_by_user_id' => $user ? $user->id : null, // User who scanned/created the request
                    'status' => 'pending',
                ]);
                
                \Log::info("Borrow request created successfully: ID={$borrowRequest->id}, send_to={$borrowRequest->send_to}, requested_by_user_id={$borrowRequest->requested_by_user_id}, borrowed_by={$borrowRequest->borrowed_by}");
            } catch (\Exception $e) {
                \Log::error("Failed to create borrow request: " . $e->getMessage());
                \Log::error("Exception trace: " . $e->getTraceAsString());
                return response()->json([
                    'message' => 'Error creating borrow request',
                    'error' => $e->getMessage(),
                ], 500);
            }

            // Create notification for the borrow request
            try {
                // Get the requester's name for the message
                $requesterName = 'Someone';
                if ($user) {
                    $requesterName = $user->fullname ?? $user->username ?? $user->email ?? 'Someone';
                } elseif ($borrowRequest->requested_by_user_id) {
                    $requestedByUser = \App\Models\User::find($borrowRequest->requested_by_user_id);
                    if ($requestedByUser) {
                        $requesterName = $requestedByUser->fullname ?? $requestedByUser->username ?? $requestedByUser->email ?? 'Someone';
                    }
                }
                
                $notification = Notification::create([
                    'item_id' => $item->id,
                    'borrow_request_id' => $borrowRequest->id,
                    'message' => "{$requesterName} Sent a Request",
                    'type' => 'borrow_request',
                    'is_read' => false,
                ]);

                // Broadcast the notification event
                event(new BorrowRequestCreated($borrowRequest, $notification));
            } catch (\Exception $e) {
                \Log::warning("Failed to create notification for borrow request: " . $e->getMessage());
                // Continue even if notification fails
            }

            return response()->json([
                'message' => 'Borrow request created successfully',
                'data' => $borrowRequest,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating borrow request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all borrow requests for an item
     */
    public function getBorrowRequests($itemId)
    {
        try {
            // Find item by UUID or ID
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $itemId)) {
                $item = Item::where('uuid', $itemId)->first();
            } else {
                $item = Item::where('id', $itemId)->first();
            }
            if (!$item) {
                return response()->json(['message' => 'Item not found'], 404);
            }

            $itemIdentifier = $item->uuid ?? $item->id;
            $requests = BorrowRequest::where('item_id', $itemIdentifier)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => $requests,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching borrow requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a borrow request (for admin use)
     */
    public function approveBorrowRequest(Request $request, $itemId, $requestId)
    {
        try {
            // Find item by UUID or ID
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $itemId)) {
                $item = Item::where('uuid', $itemId)->first();
            } else {
                $item = Item::where('id', $itemId)->first();
            }
            if (!$item) {
                return response()->json(['message' => 'Item not found'], 404);
            }

            $itemIdentifier = $item->uuid ?? $item->id;
            $borrowRequest = BorrowRequest::where('id', $requestId)
                ->where('item_id', $itemIdentifier)
                ->first();

            if (!$borrowRequest) {
                return response()->json(['message' => 'Borrow request not found'], 404);
            }

            // Check authorization: Only the admin assigned to this request (send_to) can approve it
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Authentication required'], 401);
            }

            $adminLocationId = $user->location_id;
            if (!$adminLocationId || $borrowRequest->send_to != $adminLocationId) {
                return response()->json([
                    'message' => 'Unauthorized: You are not assigned to this borrow request',
                    'error' => 'Only the admin assigned to this request can approve it',
                ], 403);
            }

            // Check if already processed
            if ($borrowRequest->status !== 'pending') {
                return response()->json([
                    'message' => 'Borrow request has already been processed',
                    'current_status' => $borrowRequest->status,
                ], 400);
            }

            // Check if quantity is still available
            $currentQuantity = intval($item->quantity ?? 0);
            if ($borrowRequest->quantity > $currentQuantity) {
                return response()->json([
                    'message' => 'Insufficient quantity available',
                    'available' => $currentQuantity,
                    'requested' => $borrowRequest->quantity,
                ], 400);
            }

            // Use database transaction to ensure atomicity
            DB::beginTransaction();
            
            try {
                // Approve the request and deduct quantity immediately
                $borrowRequest->status = 'approved';
                $borrowRequest->approved_at = now();
                $borrowRequest->approved_by = $request->user()?->id ?? null;
                $borrowRequest->save();

                \Log::info("Borrow request approved: ID={$borrowRequest->id}, Item ID={$item->id}, Current Qty={$currentQuantity}, Requested Qty={$borrowRequest->quantity}");

                // Deduct quantity from item
                $newQuantity = $currentQuantity - $borrowRequest->quantity;
                
                // Use update to ensure it's saved
                $updated = $item->update(['quantity' => $newQuantity]);
                
                if (!$updated) {
                    throw new \Exception('Failed to update item quantity');
                }
                
                \Log::info("Item quantity updated: Item ID={$item->id}, Old Qty={$currentQuantity}, New Qty={$newQuantity}");
                
                // Verify the quantity was actually saved
                $item->refresh();
                $savedQuantity = intval($item->quantity ?? 0);
                
                if ($savedQuantity !== $newQuantity) {
                    \Log::error("Quantity mismatch! Expected: {$newQuantity}, Saved: {$savedQuantity}");
                    throw new \Exception("Quantity was not saved correctly. Expected: {$newQuantity}, Got: {$savedQuantity}");
                }
                
                \Log::info("Verified quantity saved correctly: {$savedQuantity}");

                // Create borrow transaction record
                $borrowTransaction = BorrowTransaction::create([
                    'item_id' => $item->id,
                    'quantity' => $borrowRequest->quantity,
                    'borrowed_by' => $borrowRequest->borrowed_by,
                    'location' => $borrowRequest->location,
                    'borrowed_at' => now(),
                ]);
                
                \Log::info("Borrow transaction created: ID={$borrowTransaction->id}");
                
                // Get approver user to get their role and name
                $approver = $request->user();
                $approverRole = $approver ? (strtolower($approver->role ?? 'user')) : 'user';
                
                // Get approver's full name (prioritize fullname, then username, then email)
                $approverName = 'N/A';
                if ($approver) {
                    $approverName = $approver->fullname ?? $approver->username ?? $approver->email ?? 'N/A';
                }
                
                // Normalize and format role for database (uppercase to match frontend display)
                if (in_array($approverRole, ['admin', 'super_admin', 'superadmin'])) {
                    $approverRole = 'ADMIN';
                } else {
                    $approverRole = 'USER';
                }
                
                // Refresh borrow request to get the latest status
                $borrowRequest->refresh();
                
                // Format status for database (capitalized to match frontend display)
                $status = strtolower($borrowRequest->status ?? 'approved');
                $formattedStatus = ucfirst($status); // "approved" -> "Approved", "rejected" -> "Rejected"
                
                // Get the user who scanned/created the borrow request (the person who requested it)
                $requestedByUser = null;
                $requestedByName = 'N/A';
                
                // First, try to get from requested_by_user_id
                if ($borrowRequest->requested_by_user_id) {
                    try {
                        $requestedByUser = \App\Models\User::find($borrowRequest->requested_by_user_id);
                        if ($requestedByUser) {
                            $requestedByName = $requestedByUser->fullname ?? $requestedByUser->username ?? $requestedByUser->email ?? 'N/A';
                            \Log::info("Found requested_by user: {$requestedByName} (ID: {$borrowRequest->requested_by_user_id})");
                        } else {
                            \Log::warning("requested_by_user_id {$borrowRequest->requested_by_user_id} not found in users table");
                        }
                    } catch (\Exception $e) {
                        \Log::error("Error fetching requested_by user: " . $e->getMessage());
                    }
                } else {
                    \Log::warning("Borrow request ID {$borrowRequest->id} has no requested_by_user_id set");
                }
                
                // Log the requested_by value before creating transaction
                \Log::info("Creating transaction with requested_by: {$requestedByName}");
                
                // Create transaction record in transactions table with formatted values matching frontend display
                // Use unit (item name) first, then description as fallback to match notifications
                // Store approver's full name directly in approved_by column to match database with system display
                $transaction = \App\Models\Transaction::create([
                    'approved_by' => $approverName, // Store approver's full name directly (not ID)
                    'borrower_name' => $borrowRequest->borrowed_by,
                    'requested_by' => $requestedByName, // Account of who scanned/created the request
                    'location' => $borrowRequest->location,
                    'item_name' => $item->unit ?? $item->description ?? 'N/A',
                    'quantity' => $borrowRequest->quantity,
                    'transaction_time' => $borrowRequest->approved_at ?? now(),
                    'role' => $approverRole, // Store as "ADMIN" or "USER" to match frontend
                    'status' => $formattedStatus, // Store as "Approved" or "Rejected" to match frontend
                ]);
                
                \Log::info("Transaction record created: ID={$transaction->id}, requested_by={$transaction->requested_by}, borrower_name={$transaction->borrower_name}");
                
                // Commit transaction
                DB::commit();
                
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error("Error in borrow request approval transaction: " . $e->getMessage());
                throw $e;
            }
            
            // Load relationships after transaction
            $item->load(['category', 'location', 'condition', 'condition_number', 'qrCode', 'user']);

            // Log activity for approving borrow request
            try {
                $approver = $request->user();
                if ($approver) {
                    $itemName = $item->unit ?? $item->description;
                    $categoryName = $item->category ? $item->category->name : 'N/A';
                    $locationName = $item->location ? $item->location->name : 'N/A';
                    $borrowerName = $borrowRequest->borrowed_by;
                    $approverName = $approver->fullname ?? $approver->username ?? $approver->email ?? 'N/A';
                    
                    $description = "Approved borrow request for item '{$itemName}' (Quantity: {$borrowRequest->quantity}, Borrower: {$borrowerName}, Category: {$categoryName}, Location: {$locationName}, Remaining: {$item->quantity})";
                    
                    $this->logActivity($request, 'Approved Borrow Request', $description);
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to log approve borrow request activity: " . $e->getMessage());
            }

            // Prepare success response first
            $responseData = [
                'message' => 'Borrow request approved successfully',
                'data' => $borrowRequest,
                'item' => [
                    'id' => $item->id,
                    'uuid' => $item->uuid,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                ],
            ];

            // Broadcast ItemUpdated event for real-time frontend updates (non-blocking)
            try {
                event(new ItemUpdated($item));
                \Log::info("ItemUpdated event broadcasted for item: {$item->id}");
            } catch (\Exception $e) {
                \Log::warning("Failed to broadcast ItemUpdated event: " . $e->getMessage());
                // Don't fail the request if broadcasting fails
            }

            // Also broadcast ItemBorrowed event (for consistency with direct borrows) (non-blocking)
            try {
                event(new ItemBorrowed($item, $borrowRequest->quantity, $borrowRequest->borrowed_by));
                \Log::info("ItemBorrowed event broadcasted for item: {$item->id}");
            } catch (\Exception $e) {
                \Log::warning("Failed to broadcast ItemBorrowed event: " . $e->getMessage());
                // Don't fail the request if broadcasting fails
            }

            // Return success response (even if broadcasting failed)
            return response()->json($responseData, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error approving borrow request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a borrow request (for admin use)
     */
    public function rejectBorrowRequest(Request $request, $itemId, $requestId)
    {
        try {
            // Find item by UUID or ID
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $itemId)) {
                $item = Item::where('uuid', $itemId)->first();
            } else {
                $item = Item::where('id', $itemId)->first();
            }
            if (!$item) {
                return response()->json(['message' => 'Item not found'], 404);
            }

            $itemIdentifier = $item->uuid ?? $item->id;
            $borrowRequest = BorrowRequest::where('id', $requestId)
                ->where('item_id', $itemIdentifier)
                ->first();

            if (!$borrowRequest) {
                return response()->json(['message' => 'Borrow request not found'], 404);
            }

            // Check authorization: Only the admin assigned to this request (send_to) can reject it
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Authentication required'], 401);
            }

            $adminLocationId = $user->location_id;
            if (!$adminLocationId || $borrowRequest->send_to != $adminLocationId) {
                return response()->json([
                    'message' => 'Unauthorized: You are not assigned to this borrow request',
                    'error' => 'Only the admin assigned to this request can reject it',
                ], 403);
            }

            // Check if already processed
            if ($borrowRequest->status !== 'pending') {
                return response()->json([
                    'message' => 'Borrow request has already been processed',
                    'current_status' => $borrowRequest->status,
                ], 400);
            }

            // Reject the request
            $borrowRequest->status = 'rejected';
            $borrowRequest->approved_at = now();
            $borrowRequest->approved_by = $request->user()?->id ?? null;
            $borrowRequest->save();

            // Get rejector user to get their role and name
            $rejector = $request->user();
            $rejectorRole = $rejector ? (strtolower($rejector->role ?? 'user')) : 'user';
            
            // Get rejector's full name (prioritize fullname, then username, then email)
            $rejectorName = 'N/A';
            if ($rejector) {
                $rejectorName = $rejector->fullname ?? $rejector->username ?? $rejector->email ?? 'N/A';
            }
            
            // Normalize and format role for database (uppercase to match frontend display)
            if (in_array($rejectorRole, ['admin', 'super_admin', 'superadmin'])) {
                $rejectorRole = 'ADMIN';
            } else {
                $rejectorRole = 'USER';
            }
            
            // Get item details for transaction record
            $item = null;
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $borrowRequest->item_id)) {
                $item = Item::where('uuid', $borrowRequest->item_id)->first();
            } else {
                $item = Item::where('id', $borrowRequest->item_id)->first();
            }
            
            // Refresh borrow request to get the latest status
            $borrowRequest->refresh();
            
            // Format status for database (capitalized to match frontend display)
            $status = strtolower($borrowRequest->status ?? 'rejected');
            $formattedStatus = ucfirst($status); // "approved" -> "Approved", "rejected" -> "Rejected"
            
            // Get the user who scanned/created the borrow request (the person who requested it)
            $requestedByUser = null;
            $requestedByName = 'N/A';
            
            // First, try to get from requested_by_user_id
            if ($borrowRequest->requested_by_user_id) {
                try {
                    $requestedByUser = \App\Models\User::find($borrowRequest->requested_by_user_id);
                    if ($requestedByUser) {
                        $requestedByName = $requestedByUser->fullname ?? $requestedByUser->username ?? $requestedByUser->email ?? 'N/A';
                        \Log::info("Found requested_by user: {$requestedByName} (ID: {$borrowRequest->requested_by_user_id})");
                    } else {
                        \Log::warning("requested_by_user_id {$borrowRequest->requested_by_user_id} not found in users table");
                    }
                } catch (\Exception $e) {
                    \Log::error("Error fetching requested_by user: " . $e->getMessage());
                }
            } else {
                \Log::warning("Borrow request ID {$borrowRequest->id} has no requested_by_user_id set");
            }
            
            // Log the requested_by value before creating transaction
            \Log::info("Creating transaction with requested_by: {$requestedByName}");
            
            // Create transaction record in transactions table with formatted values matching frontend display
            // Use unit (item name) first, then description as fallback to match notifications
            // Store rejector's full name directly in approved_by column to match database with system display
            $transaction = \App\Models\Transaction::create([
                'approved_by' => $rejectorName, // Store rejector's full name directly (not ID)
                'borrower_name' => $borrowRequest->borrowed_by,
                'requested_by' => $requestedByName, // Account of who scanned/created the request
                'location' => $borrowRequest->location,
                'item_name' => $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A',
                'quantity' => $borrowRequest->quantity,
                'transaction_time' => $borrowRequest->approved_at ?? now(),
                'role' => $rejectorRole, // Store as "ADMIN" or "USER" to match frontend
                'status' => $formattedStatus, // Store as "Approved" or "Rejected" to match frontend
            ]);
            
            \Log::info("Transaction record created: ID={$transaction->id}, requested_by={$transaction->requested_by}, borrower_name={$transaction->borrower_name}");

            // Log activity for rejecting borrow request
            try {
                if ($rejector && $item) {
                    $item->load(['category', 'location']);
                    $itemName = $item->unit ?? $item->description;
                    $categoryName = $item->category ? $item->category->name : 'N/A';
                    $locationName = $item->location ? $item->location->name : 'N/A';
                    $borrowerName = $borrowRequest->borrowed_by;
                    
                    $description = "Rejected borrow request for item '{$itemName}' (Quantity: {$borrowRequest->quantity}, Borrower: {$borrowerName}, Category: {$categoryName}, Location: {$locationName})";
                    
                    $this->logActivity($request, 'Rejected Borrow Request', $description);
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to log reject borrow request activity: " . $e->getMessage());
            }

            return response()->json([
                'message' => 'Borrow request rejected',
                'data' => $borrowRequest,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error rejecting borrow request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all borrow requests (admin only - filtered by send_to)
     * Only shows requests assigned to the current admin's location
     */
    public function getAllBorrowRequests(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'message' => 'Authentication required',
                    'error' => 'No authenticated user found',
                ], 401);
            }
            
            // Get the admin's location_id
            $adminLocationId = $user->location_id;
            
            if (!$adminLocationId) {
                return response()->json([
                    'message' => 'Admin location not found',
                    'error' => 'User does not have a location assigned',
                ], 400);
            }
            
            $status = $request->query('status'); // Optional filter by status
            
            // Only show requests assigned to this admin's location
            $query = BorrowRequest::where('send_to', $adminLocationId);
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $requests = $query->orderBy('created_at', 'desc')->get();

            // Get item details for each request
            $requestsWithItems = $requests->map(function ($borrowRequest) {
                // Check if item_id is UUID or numeric ID
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $borrowRequest->item_id)) {
                    $item = Item::where('uuid', $borrowRequest->item_id)->first();
                } else {
                    $item = Item::where('id', $borrowRequest->item_id)->first();
                }
                
                // Get approver information
                $approver = null;
                if ($borrowRequest->approved_by) {
                    $approverUser = \App\Models\User::find($borrowRequest->approved_by);
                    if ($approverUser) {
                        $approver = [
                            'id' => $approverUser->id,
                            'name' => $approverUser->name ?? $approverUser->email ?? 'N/A',
                            'email' => $approverUser->email ?? 'N/A',
                        ];
                    }
                }
                
                return [
                    'id' => $borrowRequest->id,
                    'item_id' => $borrowRequest->item_id,
                    'item_name' => $item ? ($item->description ?? $item->name ?? 'N/A') : 'N/A',
                    'item_pac' => $item ? ($item->pac ?? 'N/A') : 'N/A',
                    'item_quantity' => $item ? ($item->quantity ?? 0) : 0,
                    'quantity' => $borrowRequest->quantity,
                    'location' => $borrowRequest->location,
                    'borrowed_by' => $borrowRequest->borrowed_by,
                    'send_to' => $borrowRequest->send_to, // Location ID this request is assigned to
                    'status' => $borrowRequest->status,
                    'approved_at' => $borrowRequest->approved_at ? $borrowRequest->approved_at->format('Y-m-d H:i:s') : null,
                    'approved_by' => $borrowRequest->approved_by,
                    'approver' => $approver,
                    'created_at' => $borrowRequest->created_at ? $borrowRequest->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $borrowRequest->updated_at ? $borrowRequest->updated_at->format('Y-m-d H:i:s') : null,
                ];
            });

            return response()->json([
                'data' => $requestsWithItems,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching borrow requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
 * Get all transactions from the transactions table
 * This endpoint is accessible to ALL users including admins
 * NO authentication or admin role check required
 */
public function getAllTransactions(Request $request)
{
    // IMPORTANT: This endpoint MUST be accessible to ALL users including admins
    // NO authentication or admin role check should exist here
    
    try {
        // Check if transactions table exists
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('transactions')) {
                return response()->json([
                    'message' => 'Transactions table does not exist',
                    'data' => [],
                ], 200);
            }
        } catch (\Exception $e) {
            \Log::warning('Could not check if transactions table exists: ' . $e->getMessage());
        }

        // Get all transactions from the transactions table
        // Order by transaction_time descending (most recent first)
        $transactions = \Illuminate\Support\Facades\DB::table('transactions')
            ->orderBy('transaction_time', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Format the transactions data
        $formattedTransactions = $transactions->map(function ($transaction) {
            // Helper function to format dates
            $formatDate = function($dateValue) {
                if (!$dateValue) return null;
                try {
                    if (is_string($dateValue)) {
                        return date('Y-m-d H:i:s', strtotime($dateValue));
                    } elseif ($dateValue instanceof \DateTime) {
                        return $dateValue->format('Y-m-d H:i:s');
                    } else {
                        return (string)$dateValue;
                    }
                } catch (\Exception $e) {
                    return (string)$dateValue;
                }
            };
            
            return [
                'id' => $transaction->id ?? null,
                'approved_by' => $transaction->approved_by ?? null,
                'approved_by_name' => $transaction->approved_by_name ?? $transaction->approved_by ?? 'N/A',
                'borrower_name' => $transaction->borrower_name ?? 'N/A',
                'location' => $transaction->location ?? 'N/A',
                'item_name' => $transaction->item_name ?? 'N/A',
                'quantity' => $transaction->quantity ?? 0,
                'transaction_time' => $formatDate($transaction->transaction_time ?? null),
                'role' => $transaction->role ?? 'ADMIN',
                'status' => $transaction->status ?? 'Approved',
                'created_at' => $formatDate($transaction->created_at ?? null),
                'updated_at' => $formatDate($transaction->updated_at ?? null),
            ];
        });

        return response()->json([
            'data' => $formattedTransactions,
        ], 200);
    } catch (\PDOException $e) {
        \Log::error('PDO error fetching transactions: ' . $e->getMessage());
        return response()->json([
            'message' => 'Database connection error',
            'error' => config('app.debug') ? $e->getMessage() : 'Unable to connect to database. Please check database configuration.',
        ], 500);
    } catch (\Illuminate\Database\QueryException $e) {
        \Log::error('Database query error fetching transactions: ' . $e->getMessage());
        return response()->json([
            'message' => 'Database query error',
            'error' => config('app.debug') ? $e->getMessage() : 'Database query failed',
        ], 500);
    } catch (\Exception $e) {
        \Log::error('Error fetching transactions: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error fetching transactions',
            'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
        ], 500);
    }
    
}

}
