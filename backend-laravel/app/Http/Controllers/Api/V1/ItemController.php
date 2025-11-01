<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BorrowTransaction;
use App\Models\Item;
use App\Http\Requests\V1\StoreItemRequest;
use App\Http\Requests\V1\UpdateItemRequest;
use App\Http\Resources\V1\ItemResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ItemCollection;
use App\Services\V1\ItemService;
use App\Services\V1\QrCodeService;
use App\Traits\LogsActivity;
use App\Jobs\CheckLowStockJob;
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
            // Get all non-deleted items with QR code relationship
            $items = Item::with('qrCode')->get();
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
            // Get all items with QR code relationship
            $items = Item::with('qrCode')->get();
            
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
        $item->load('qrCode');
        return new ItemResource($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateItemRequest $request, Item $item)
    {
        $itemService = new ItemService();
        
        // Update the item with validated data
        $item->update($request->validated());
        
        // Handle image upload if present
        $image = $request->file('image_path');
        if ($image) {
            $itemService->handleImageUpload($item, $image);
        }
        
        // Log item update
        $this->logItemActivity($request, 'Updated', $item->unit ?? $item->description, $item->uuid);
        
        // Refresh item to get latest data after update
        $item->refresh();
        
        // Execute job immediately to check for low stock (especially when quantity changes)
        try {
            $job = new CheckLowStockJob();
            $job->handle();
        } catch (\Exception $e) {
            \Log::error("CheckLowStockJob failed: " . $e->getMessage());
        }
        
        // Return the updated item
        return new ItemResource($item);
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
            $item = Item::where('uuid', $uuid)->firstOrFail();
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

    // Subtract borrowed quantity
    $item->quantity -= $request->quantity;
    $item->save();

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
}
