<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MemorandumReceipt;
use App\Models\User;
use App\Models\Item;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemorandumReceiptController extends Controller
{
    use LogsActivity;

    /**
     * Get pending items for a user (for clearance)
     */
    public function getPendingItems($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $userFullname = trim($user->fullname ?? '');
            
            // BASIS: Pending items based on CURRENT personnel assignment
            // Priority: If item has location_id, it belongs to the personnel of that location ONLY
            // Only if item has NO location_id, then it can belong to user_id
            
            // Initialize collections
            $pendingItems = [];
            $directItems = [];
            
            // Get pending items assigned to locations where this user is CURRENTLY the personnel
            // Use a safer approach: get items first, then filter
            try {
                $pendingForPersonnel = MemorandumReceipt::where('issued_to_type', 'PERSONNEL')
                ->where('status', 'ISSUED')
                    ->whereHas('item', function($query) {
                        $query->whereNotNull('location_id');
                    })
                ->with(['item.category', 'item.location', 'item.condition'])
                ->get();
                
                // Filter in PHP to avoid query issues
                foreach ($pendingForPersonnel as $mr) {
                    try {
                        if ($mr->item && 
                            $mr->item->location && 
                            trim($mr->item->location->personnel ?? '') === trim($userFullname)) {
                            $pendingItems[] = $mr;
                        }
                    } catch (\Exception $e) {
                        \Log::error("Error processing MR {$mr->id}: " . $e->getMessage());
                        continue;
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Error fetching pendingForPersonnel: " . $e->getMessage());
                \Log::error("Stack trace: " . $e->getTraceAsString());
            }
            
            // Get pending items directly assigned to user (ONLY if item has NO location_id)
            try {
                $pendingForUser = MemorandumReceipt::where('issued_to_user_id', $userId)
                    ->where('issued_to_type', 'USER')
                    ->where('status', 'ISSUED')
                    ->whereHas('item', function($query) use ($userId) {
                        $query->where('user_id', $userId)
                              ->whereNull('location_id'); // Only items NOT assigned to any location
                    })
                    ->with(['item.category', 'item.location', 'item.condition'])
                    ->get();
                
                // Add to pending items array
                foreach ($pendingForUser as $mr) {
                    $pendingItems[] = $mr;
                }
            } catch (\Exception $e) {
                \Log::error("Error fetching pendingForUser: " . $e->getMessage());
                \Log::error("Stack trace: " . $e->getTraceAsString());
            }
            
            // Also get items directly assigned to user but WITHOUT MR records (items assigned before MR system)
            // These also need clearance
            try {
                $directItemsForUser = \App\Models\Item::where('user_id', $userId)
                    ->whereNull('location_id') // Only items directly assigned to user, not via location
                    ->with(['category', 'location', 'condition'])
                    ->get();
                
                foreach ($directItemsForUser as $item) {
                    $directItems[] = $item;
                }
            } catch (\Exception $e) {
                \Log::error("Error fetching directItemsForUser: " . $e->getMessage());
            }
            
            // Also get items assigned to locations where this user is currently the personnel (without MR)
            // Only get items where the location actually exists and matches
            try {
                $directItemsForPersonnel = \App\Models\Item::whereNotNull('location_id')
                    ->whereHas('location', function($query) use ($userFullname) {
                        $query->where('personnel', $userFullname); // Only locations where this user is current personnel
                    })
                    ->with(['category', 'location', 'condition'])
                    ->get();
                
                foreach ($directItemsForPersonnel as $item) {
                    // Filter out items where location relationship failed to load
                    if ($item->location !== null) {
                        $directItems[] = $item;
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Error fetching directItemsForPersonnel: " . $e->getMessage());
            }
            
            // Get item IDs that already have MR records
            $mrItemIds = [];
            foreach ($pendingItems as $mr) {
                try {
                    $itemId = null;
                    if (is_object($mr) && isset($mr->item_id)) {
                        $itemId = $mr->item_id;
                    } elseif (is_array($mr) && isset($mr['item_id'])) {
                        $itemId = $mr['item_id'];
                    }
                    if ($itemId) {
                        $mrItemIds[] = $itemId;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            $mrItemIds = array_unique($mrItemIds);
            
            // Create MR-like records for items that don't have MR records
            foreach ($directItems as $item) {
                try {
                    if (!in_array($item->id, $mrItemIds)) {
                        // Create a virtual MR record for display (pending items without MR)
                        $itemResource = new \App\Http\Resources\V1\ItemResource($item);
                        $itemArray = $itemResource->toArray(request());
                        
                        $pendingItems[] = (object)[
                            'id' => null, // No MR ID - indicates item needs formalization
                            'item_id' => $item->id,
                            'item' => $itemArray,
                            'issued_to_user_id' => $userId,
                            'issued_to_code' => $user->user_code ?? 'N/A',
                            'issued_to_type' => 'USER',
                            'issued_by_user_code' => 'SYSTEM',
                            'issued_at' => $item->created_at ?? now(),
                            'status' => 'ISSUED',
                            'returned_at' => null,
                            'remarks' => 'Item assigned before MR system was implemented. This item needs to be formalized or cleared.'
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::error("Error creating virtual MR for item {$item->id}: " . $e->getMessage());
                    continue;
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_values($pendingItems), // Re-index array
                'user' => [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'user_code' => $user->user_code,
                    'status' => $user->status,
                    'pending_count' => count($pendingItems)
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error("Error in getPendingItems: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending items for a location/personnel (for clearance)
     */
    public function getPendingItemsForPersonnel($locationId)
    {
        try {
            $location = \App\Models\Location::findOrFail($locationId);
            
            // Initialize arrays
            $pendingItems = [];
            $directItems = [];
            
            // Get pending items with MR records
            try {
                $mrItems = MemorandumReceipt::where('issued_to_type', 'PERSONNEL')
                ->where('status', 'ISSUED')
                    ->whereHas('item', function($query) use ($locationId) {
                        $query->where('location_id', $locationId);
                    })
                ->with(['item.category', 'item.location', 'item.condition'])
                ->get();
                
                foreach ($mrItems as $mr) {
                    $pendingItems[] = $mr;
                }
            } catch (\Exception $e) {
                \Log::error("Error fetching MR items for personnel: " . $e->getMessage());
            }
            
            // Also get items assigned to this location but WITHOUT MR records
            try {
                $items = \App\Models\Item::where('location_id', $locationId)
                    ->with(['category', 'location', 'condition'])
                    ->get();
                
                foreach ($items as $item) {
                    $directItems[] = $item;
                }
            } catch (\Exception $e) {
                \Log::error("Error fetching direct items for personnel: " . $e->getMessage());
            }
            
            // Get item IDs that already have MR records
            $mrItemIds = [];
            foreach ($pendingItems as $mr) {
                try {
                    $itemId = null;
                    if (is_object($mr) && isset($mr->item_id)) {
                        $itemId = $mr->item_id;
                    } elseif (is_array($mr) && isset($mr['item_id'])) {
                        $itemId = $mr['item_id'];
                    }
                    if ($itemId) {
                        $mrItemIds[] = $itemId;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            $mrItemIds = array_unique($mrItemIds);
            
            // Create MR-like records for items that don't have MR records
            foreach ($directItems as $item) {
                try {
                    if (!in_array($item->id, $mrItemIds)) {
                        // Create a virtual MR record for display (pending items without MR)
                        $itemResource = new \App\Http\Resources\V1\ItemResource($item);
                        $itemArray = $itemResource->toArray(request());
                        
                        $pendingItems[] = (object)[
                            'id' => null, // No MR ID - indicates item needs formalization
                            'item_id' => $item->id,
                            'item' => $itemArray,
                            'issued_to_user_id' => null,
                            'issued_to_code' => $location->personnel_code ?? 'N/A',
                            'issued_to_type' => 'PERSONNEL',
                            'issued_by_user_code' => 'SYSTEM',
                            'issued_at' => $item->created_at ?? now(),
                            'status' => 'ISSUED',
                            'returned_at' => null,
                            'remarks' => 'Item assigned before MR system was implemented. This item needs to be formalized or cleared.'
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::error("Error creating virtual MR for item {$item->id}: " . $e->getMessage());
                    continue;
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_values($pendingItems), // Re-index array
                'personnel' => [
                    'id' => $location->id,
                    'name' => $location->personnel,
                    'personnel_code' => $location->personnel_code,
                    'location' => $location->location,
                    'pending_count' => count($pendingItems)
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error("Error in getPendingItemsForPersonnel: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all issued items for a user (both ISSUED and RETURNED)
     */
    public function getAllIssuedItemsForUser($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $userFullname = trim($user->fullname ?? '');
            
            // BASIS: Issued items based on CURRENT personnel assignment
            // Priority: If item has location_id, it belongs to the personnel of that location ONLY
            // Only if item has NO location_id, then it can belong to user_id
            
            // Get items from MR records assigned to locations where this user is/was the personnel
            // Include ISSUED, RETURNED, LOST, and DAMAGED MRs
            // For LOST/DAMAGED items, we need to check the MR's issued_to_location_id directly
            // because the item's location_id may have been cleared
            $locationIdsForUser = \App\Models\Location::where('personnel', $userFullname)
                ->pluck('id')
                ->toArray();
            
            $mrItemsForPersonnel = MemorandumReceipt::where('issued_to_type', 'PERSONNEL')
                ->where(function($query) use ($userFullname, $locationIdsForUser) {
                    // Include ISSUED items that are still assigned to locations with this personnel
                    $query->where(function($q) use ($userFullname) {
                        $q->where('status', 'ISSUED')
                          ->whereHas('item.location', function($locQuery) use ($userFullname) {
                              $locQuery->where('personnel', $userFullname);
                          })
                          ->whereHas('item', function($itemQuery) {
                              $itemQuery->whereNotNull('location_id');
                          });
                    })
                    // Include RETURNED, LOST, and DAMAGED items for locations where this user was/is personnel
                    // Use issued_to_location_id directly (not item.location) because item assignment may be cleared
                    ->orWhere(function($q) use ($locationIdsForUser) {
                        $q->whereIn('status', ['RETURNED', 'LOST', 'DAMAGED'])
                          ->whereIn('issued_to_location_id', $locationIdsForUser);
                    });
                })
                ->with(['item.category', 'item.location', 'item.condition'])
                ->get();
            
            // Get items from MR records assigned directly to user
            // Include ISSUED, RETURNED, LOST, and DAMAGED MRs
            $mrItemsForUser = MemorandumReceipt::where('issued_to_user_id', $userId)
                ->where('issued_to_type', 'USER')
                ->where(function($query) use ($userId) {
                    // Include ISSUED items that are still assigned
                    $query->where(function($q) use ($userId) {
                        $q->where('status', 'ISSUED')
                          ->whereHas('item', function($itemQuery) use ($userId) {
                              $itemQuery->where('user_id', $userId)
                                        ->whereNull('location_id');
                          });
                    })
                    // Include RETURNED, LOST, and DAMAGED items (regardless of current assignment - for audit trail)
                    ->orWhereIn('status', ['RETURNED', 'LOST', 'DAMAGED']);
                })
                ->with(['item.category', 'item.location', 'item.condition'])
                ->get();
            
            // Combine both sets
            $mrItems = $mrItemsForUser->merge($mrItemsForPersonnel)->sortByDesc('issued_at')->values();
            
            // Get items assigned to locations where this user is currently the personnel
            $directItemsForPersonnel = \App\Models\Item::whereNotNull('location_id')
                ->whereHas('location', function($query) use ($userFullname) {
                    $query->where('personnel', $userFullname);
                })
                ->with(['category', 'location', 'condition'])
                ->get();
            
            // Get items directly assigned to user (ONLY if item has NO location_id)
            $directItems = \App\Models\Item::where('user_id', $userId)
                ->whereNull('location_id') // Only items NOT assigned to any location
                ->with(['category', 'location', 'condition'])
                ->get();
            
            // Combine direct items
            $directItems = $directItems->merge($directItemsForPersonnel);
            
            // Create MR-like records for items that don't have MR records
            $allItems = $mrItems->toArray();
            $mrItemIds = $mrItems->pluck('item_id')->toArray();
            
            foreach ($directItems as $item) {
                if (!in_array($item->id, $mrItemIds)) {
                    // Create a virtual MR record for display
                    // Use ItemResource to properly format the item with all relationships
                    $itemResource = new \App\Http\Resources\V1\ItemResource($item);
                    $allItems[] = [
                        'id' => null, // No MR ID
                        'item_id' => $item->id,
                        'item' => $itemResource->toArray(request()),
                        'issued_to_user_id' => $userId,
                        'issued_to_code' => $user->user_code ?? 'N/A',
                        'issued_to_type' => 'USER',
                        'issued_by_user_code' => 'SYSTEM',
                        'issued_at' => $item->created_at ?? now(),
                        'status' => 'ISSUED', // Assume issued if no MR record
                        'returned_at' => null,
                        'remarks' => 'Item assigned before MR system was implemented. This item was directly assigned to the personnel and does not have a Memorandum Receipt record.'
                    ];
                }
            }
            
            // Sort by issued_at
            usort($allItems, function($a, $b) {
                $dateA = is_array($a) ? ($a['issued_at'] ?? '1970-01-01') : ($a->issued_at ?? '1970-01-01');
                $dateB = is_array($b) ? ($b['issued_at'] ?? '1970-01-01') : ($b->issued_at ?? '1970-01-01');
                return strtotime($dateB) - strtotime($dateA);
            });

            return response()->json([
                'success' => true,
                'data' => $allItems,
                'user' => [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'user_code' => $user->user_code,
                    'status' => $user->status,
                    'total_count' => count($allItems),
                    'issued_count' => collect($allItems)->where('status', 'ISSUED')->count(),
                    'returned_count' => collect($allItems)->where('status', 'RETURNED')->count(),
                    'lost_count' => collect($allItems)->where('status', 'LOST')->count(),
                    'damaged_count' => collect($allItems)->where('status', 'DAMAGED')->count()
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error("Error in getAllIssuedItemsForUser: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch issued items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all issued items for the authenticated user (both ISSUED and RETURNED)
     * Users can only view their own issued items
     */
    public function getMyIssuedItems(Request $request)
    {
        try {
            $authenticatedUser = $request->user();
            
            if (!$authenticatedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            $userId = $authenticatedUser->id;
            $user = User::findOrFail($userId);
            
            // BASIS: Issued items are based on CURRENT personnel assignment
            // Priority: If item has location_id, it belongs to the personnel of that location ONLY
            // Only if item has NO location_id, then it can belong to user_id
            
            $userFullname = trim($user->fullname ?? '');
            
            // Get items from MR records assigned to locations where this user is CURRENTLY the personnel
            // Include ISSUED, RETURNED, LOST, and DAMAGED items
            $mrItemsForPersonnel = MemorandumReceipt::where('issued_to_type', 'PERSONNEL')
                ->where(function($query) use ($userFullname) {
                    // Include ISSUED items that are still assigned to locations with this personnel
                    $query->where(function($q) use ($userFullname) {
                        $q->where('status', 'ISSUED')
                          ->whereHas('item.location', function($locQuery) use ($userFullname) {
                              $locQuery->where('personnel', $userFullname);
                          })
                          ->whereHas('item', function($itemQuery) {
                              $itemQuery->whereNotNull('location_id');
                          });
                    })
                    // Include RETURNED, LOST, and DAMAGED items for locations where this user was/is personnel (for audit trail)
                    ->orWhere(function($q) use ($userFullname) {
                        $q->whereIn('status', ['RETURNED', 'LOST', 'DAMAGED'])
                          ->whereHas('item.location', function($locQuery) use ($userFullname) {
                              $locQuery->where('personnel', $userFullname);
                          });
                    });
                })
                ->with(['item.category', 'item.location', 'item.condition'])
                ->get();
            
            // Get items from MR records assigned directly to user (ONLY if item has NO location_id)
            // Include ISSUED, RETURNED, LOST, and DAMAGED items
            $mrItemsForUser = MemorandumReceipt::where('issued_to_user_id', $userId)
                ->where('issued_to_type', 'USER')
                ->where(function($query) use ($userId) {
                    // Include ISSUED items that are still assigned
                    $query->where(function($q) use ($userId) {
                        $q->where('status', 'ISSUED')
                          ->whereHas('item', function($itemQuery) use ($userId) {
                              $itemQuery->where('user_id', $userId)
                                        ->whereNull('location_id');
                          });
                    })
                    // Include RETURNED, LOST, and DAMAGED items (regardless of current assignment - for audit trail)
                    ->orWhereIn('status', ['RETURNED', 'LOST', 'DAMAGED']);
                })
                ->with(['item.category', 'item.location', 'item.condition'])
                ->get();
            
            // Combine both sets
            $mrItems = $mrItemsForUser->merge($mrItemsForPersonnel)->sortByDesc('issued_at')->values();
            
            // Get items assigned to locations where this user is currently the personnel
            $directItemsForPersonnel = \App\Models\Item::whereNotNull('location_id')
                ->whereHas('location', function($query) use ($userFullname) {
                    $query->where('personnel', $userFullname); // Only locations where this user is current personnel
                })
                ->with(['category', 'location', 'condition'])
                ->get();
            
            // Get items directly assigned to user (ONLY if item has NO location_id)
            // If item has location_id, it belongs to personnel, not user
            $directItems = \App\Models\Item::where('user_id', $userId)
                ->whereNull('location_id') // Only items NOT assigned to any location
                ->with(['category', 'location', 'condition'])
                ->get();
            
            // Combine direct items
            $directItems = $directItems->merge($directItemsForPersonnel);
            
            // Create MR-like records for items that don't have MR records
            // Convert to array and ensure remarks field is included
            $allItems = $mrItems->map(function($mr) {
                $array = $mr->toArray();
                // Explicitly include remarks field - ensure it's preserved as-is (could be JSON string or null)
                $array['remarks'] = $mr->remarks;
                // Also ensure all other important fields are included
                $array['id'] = $mr->id;
                $array['status'] = $mr->status;
                $array['returned_at'] = $mr->returned_at;
                $array['issued_at'] = $mr->issued_at;
                $array['issued_by_user_code'] = $mr->issued_by_user_code;
                return $array;
            })->toArray();
            $mrItemIds = $mrItems->pluck('item_id')->toArray();
            
            foreach ($directItems as $item) {
                if (!in_array($item->id, $mrItemIds)) {
                    // Create a virtual MR record for display
                    // Use ItemResource to properly format the item with all relationships
                    $itemResource = new \App\Http\Resources\V1\ItemResource($item);
                    $allItems[] = [
                        'id' => null, // No MR ID
                        'item_id' => $item->id,
                        'item' => $itemResource->toArray(request()),
                        'issued_to_user_id' => $userId,
                        'issued_to_code' => $user->user_code ?? 'N/A',
                        'issued_to_type' => 'USER',
                        'issued_by_user_code' => 'SYSTEM',
                        'issued_at' => $item->created_at ?? now(),
                        'status' => 'ISSUED', // Assume issued if no MR record
                        'returned_at' => null,
                        'remarks' => 'Item assigned before MR system was implemented. This item was directly assigned to the personnel and does not have a Memorandum Receipt record.'
                    ];
                }
            }
            
            // Sort by issued_at
            usort($allItems, function($a, $b) {
                $dateA = is_array($a) ? ($a['issued_at'] ?? '1970-01-01') : ($a->issued_at ?? '1970-01-01');
                $dateB = is_array($b) ? ($b['issued_at'] ?? '1970-01-01') : ($b->issued_at ?? '1970-01-01');
                return strtotime($dateB) - strtotime($dateA);
            });

            return response()->json([
                'success' => true,
                'data' => $allItems,
                'user' => [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'user_code' => $user->user_code,
                    'status' => $user->status,
                    'total_count' => count($allItems),
                    'issued_count' => collect($allItems)->where('status', 'ISSUED')->count(),
                    'returned_count' => collect($allItems)->where('status', 'RETURNED')->count(),
                    'lost_count' => collect($allItems)->where('status', 'LOST')->count(),
                    'damaged_count' => collect($allItems)->where('status', 'DAMAGED')->count()
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error("Error in getMyIssuedItems: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch issued items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all issued items for a location/personnel (both ISSUED, RETURNED, LOST, and DAMAGED)
     */
    public function getAllIssuedItemsForPersonnel($locationId)
    {
        try {
            $location = \App\Models\Location::findOrFail($locationId);
            
            // Get items from MR records - Include ALL statuses (ISSUED, RETURNED, LOST, DAMAGED)
            // This ensures we get LOST/DAMAGED items even if item assignment was cleared
            $mrItems = MemorandumReceipt::where('issued_to_location_id', $locationId)
                ->where('issued_to_type', 'PERSONNEL')
                ->with(['item.category', 'item.location', 'item.condition'])
                ->orderBy('issued_at', 'desc')
                ->get();
            
            // Also check items directly assigned to this location (for items assigned before MR system)
            $directItems = \App\Models\Item::where('location_id', $locationId)
                ->with(['category', 'location', 'condition'])
                ->get();
            
            // Create MR-like records for items that don't have MR records
            $allItems = $mrItems->toArray();
            $mrItemIds = $mrItems->pluck('item_id')->toArray();
            
            foreach ($directItems as $item) {
                if (!in_array($item->id, $mrItemIds)) {
                    // Create a virtual MR record for display
                    // Use ItemResource to properly format the item with all relationships
                    $itemResource = new \App\Http\Resources\V1\ItemResource($item);
                    $allItems[] = [
                        'id' => null, // No MR ID
                        'item_id' => $item->id,
                        'item' => $itemResource->toArray(request()),
                        'issued_to_location_id' => $locationId,
                        'issued_to_code' => $location->personnel_code ?? 'N/A',
                        'issued_to_type' => 'PERSONNEL',
                        'issued_by_user_code' => 'SYSTEM',
                        'issued_at' => $item->created_at ?? now(),
                        'status' => 'ISSUED', // Assume issued if no MR record
                        'returned_at' => null,
                        'remarks' => 'Item assigned before MR system was implemented. This item was directly assigned to the personnel and does not have a Memorandum Receipt record.'
                    ];
                }
            }
            
            // Sort by issued_at
            usort($allItems, function($a, $b) {
                $dateA = is_array($a) ? ($a['issued_at'] ?? '1970-01-01') : ($a->issued_at ?? '1970-01-01');
                $dateB = is_array($b) ? ($b['issued_at'] ?? '1970-01-01') : ($b->issued_at ?? '1970-01-01');
                return strtotime($dateB) - strtotime($dateA);
            });

            return response()->json([
                'success' => true,
                'data' => $allItems,
                'personnel' => [
                    'id' => $location->id,
                    'name' => $location->personnel,
                    'personnel_code' => $location->personnel_code,
                    'location' => $location->location,
                    'total_count' => count($allItems),
                    'issued_count' => collect($allItems)->where('status', 'ISSUED')->count(),
                    'returned_count' => collect($allItems)->where('status', 'RETURNED')->count(),
                    'lost_count' => collect($allItems)->where('status', 'LOST')->count(),
                    'damaged_count' => collect($allItems)->where('status', 'DAMAGED')->count()
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error("Error in getAllIssuedItemsForPersonnel: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch issued items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return an item (mark MR as returned)
     */
    public function returnItem(Request $request, $mrId)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:1000'
        ]);

        $mr = MemorandumReceipt::findOrFail($mrId);
        $admin = $request->user();

        if ($mr->status !== 'ISSUED') {
            return response()->json([
                'success' => false,
                'message' => 'This item is already processed.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Mark MR as returned
            $mr->markAsReturned($admin->id, $request->input('remarks'));

            // Update item to make it available (clear all assignments when returned)
            $item = $mr->item;
            if ($item) {
                // Always clear both assignments when item is returned to make it available for reissue
                    $item->user_id = null;
                    $item->location_id = null;
                $item->save();
            }

            // Log activity
            $fromCode = $mr->issued_to_code ?? 'N/A';
            $this->logActivity($request, 'Item Returned', 
                "Returned item '{$item->unit}' (MR #{$mr->id}) from {$fromCode}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item returned successfully.',
                'data' => $mr->fresh(['item', 'processedByUser'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to return item: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to return item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reassign item to another user or personnel
     */
    public function reassignItem(Request $request, $mrId)
    {
        $request->validate([
            'new_user_id' => 'nullable|exists:users,id',
            'new_location_id' => 'nullable|exists:locations,id',
            'remarks' => 'nullable|string|max:1000'
        ]);

        $mr = MemorandumReceipt::findOrFail($mrId);
        $admin = $request->user();

        if ($mr->status !== 'ISSUED') {
            return response()->json([
                'success' => false,
                'message' => 'This item is already processed.'
            ], 422);
        }

        $newUserId = $request->input('new_user_id');
        $newLocationId = $request->input('new_location_id');
        
        if (!$newUserId && !$newLocationId) {
            return response()->json([
                'success' => false,
                'message' => 'Either new_user_id or new_location_id must be provided.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $item = $mr->item;
            $newCode = null;
            $newType = null;
            $newId = null;
            
            if ($newUserId) {
                $newUser = User::findOrFail($newUserId);
                if ($newUser->status !== 'ACTIVE') {
                    throw new \Exception('Cannot reassign to inactive or resigned user.');
                }
                $newCode = $newUser->user_code;
                $newType = 'USER';
                $newId = $newUser->id;
                
                // Update item assignment
                if ($item) {
                    $item->user_id = $newUser->id;
                    $item->location_id = null; // Clear location if reassigning to user
                    $item->save();
                }
            } else {
                $newLocation = \App\Models\Location::findOrFail($newLocationId);
                if (!$newLocation->personnel) {
                    throw new \Exception('Selected location has no personnel assigned.');
                }
                
                // Generate personnel_code if not exists
                if (!$newLocation->personnel_code) {
                    $newLocation->personnel_code = \App\Models\Location::generatePersonnelCode($newLocation->location);
                    $newLocation->save();
                }
                
                $newCode = $newLocation->personnel_code;
                $newType = 'PERSONNEL';
                $newId = $newLocation->id;
                
                // Update item assignment
                if ($item) {
                    $item->location_id = $newLocation->id;
                    $item->user_id = null; // Clear user if reassigning to personnel
                    $item->save();
                }
            }

            // Reassign MR
            $mr->reassignTo($newId, $newCode, $newType, $admin->id, $request->input('remarks'));

            // Create new MR for the new assignee
            MemorandumReceipt::create([
                'item_id' => $item->id,
                'issued_to_user_id' => $newType === 'USER' ? $newId : null,
                'issued_to_location_id' => $newType === 'PERSONNEL' ? $newId : null,
                'issued_to_code' => $newCode,
                'issued_to_type' => $newType,
                'issued_by_user_code' => $admin->user_code ?? 'N/A',
                'issued_at' => now(),
                'status' => 'ISSUED'
            ]);

            // Log activity
            $fromCode = $mr->issued_to_code ?? 'N/A';
            $this->logActivity($request, 'Item Reassigned', 
                "Reassigned item '{$item->unit}' (MR #{$mr->id}) from {$fromCode} to {$newCode}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item reassigned successfully.',
                'data' => $mr->fresh(['item', 'reassignedToUser', 'reassignedToLocation', 'processedByUser'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to reassign item: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reassign item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark item as lost or damaged with enhanced accountability tracking
     */
    public function markAsLostOrDamaged(Request $request, $mrId)
    {
        $request->validate([
            'status' => 'required|in:LOST,DAMAGED',
            'remarks' => 'required|string|max:1000',
            'reported_by' => 'nullable|string|max:255',
            'incident_date' => 'nullable|date',
            'estimated_value_loss' => 'nullable|numeric|min:0',
            'investigation_required' => 'nullable|boolean'
        ]);

        $mr = MemorandumReceipt::findOrFail($mrId);
        $admin = $request->user();

        if ($mr->status !== 'ISSUED') {
            return response()->json([
                'success' => false,
                'message' => 'This item is already processed.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $item = $mr->item;
            $status = $request->input('status'); // LOST or DAMAGED
            
            // Create detailed accountability record
            $remarksData = [
                'type' => $status,
                'reported_by' => $request->input('reported_by', $mr->issued_to_code),
                'incident_date' => $request->input('incident_date', now()->toDateString()),
                'description' => $request->input('remarks'),
                'estimated_value_loss' => $request->input('estimated_value_loss'),
                'investigation_required' => $request->input('investigation_required', false),
                'processed_by' => $admin->user_code ?? 'SYSTEM',
                'processed_at' => now()->toDateTimeString(),
                'item_details' => [
                    'serial_number' => $item->serial_number ?? 'N/A',
                    'model' => $item->model ?? 'N/A',
                    'unit_value' => $item->unit_value ?? 0
                ]
            ];

            // Update MR with detailed status
            $mr->status = $status;
            $mr->returned_at = now();
            $mr->processed_by_user_id = $admin->id;
            $mr->remarks = json_encode($remarksData);
            $mr->save();

            // Update item assignment
            if ($item) {
                if ($mr->issued_to_type === 'USER' && $item->user_id == $mr->issued_to_user_id) {
                $item->user_id = null;
                } elseif ($mr->issued_to_type === 'PERSONNEL' && $item->location_id == $mr->issued_to_location_id) {
                    $item->location_id = null;
                }
                $item->save();
            }

            // Log activity with detailed information
            $itemInfo = "Item '{$item->unit}'";
            if ($item->serial_number) {
                $itemInfo .= " (Serial: {$item->serial_number}";
                if ($item->model) {
                    $itemInfo .= ", Model: {$item->model}";
                }
                $itemInfo .= ")";
            }
            
            $this->logActivity($request, "Item Marked as {$status}", 
                "{$itemInfo} MR #{$mr->id} marked as {$status}. " .
                "Issued to: {$mr->issued_to_code}, " .
                "Reported by: {$remarksData['reported_by']}, " .
                "Description: {$request->input('remarks')}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Item marked as {$status} successfully.",
                'data' => $mr->fresh(['item', 'processedByUser'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to mark item as lost/damaged: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * User-facing endpoint to report lost or damaged items
     * Status changes immediately and admin receives notification
     */
    public function reportLostOrDamaged(Request $request, $mrId)
    {
        $request->validate([
            'status' => 'required|in:LOST,DAMAGED',
            'remarks' => 'required|string|max:1000',
            'incident_date' => 'nullable|date',
            'estimated_value_loss' => 'nullable|numeric|min:0',
        ]);

        $mr = MemorandumReceipt::with(['item.location'])->findOrFail($mrId);
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Validate that the user can only report items issued to them
        $canReport = false;
        
        if ($mr->issued_to_type === 'USER' && $mr->issued_to_user_id == $user->id) {
            $canReport = true;
        } elseif ($mr->issued_to_type === 'PERSONNEL') {
            // Check if user is the personnel for this location
            $userFullname = trim($user->fullname ?? '');
            if ($mr->item && $mr->item->location && trim($mr->item->location->personnel ?? '') === $userFullname) {
                $canReport = true;
            }
        }

        if (!$canReport) {
            return response()->json([
                'success' => false,
                'message' => 'You can only report items issued to you.'
            ], 403);
        }

        if ($mr->status !== 'ISSUED') {
            return response()->json([
                'success' => false,
                'message' => 'This item is already processed.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $item = $mr->item;
            $status = $request->input('status'); // LOST or DAMAGED
            
            // Check if this item was previously lost/damaged and recovered
            $previousIncidents = [];
            $previousRemarks = null;
            if ($mr->remarks) {
                try {
                    $previousRemarks = is_string($mr->remarks) ? json_decode($mr->remarks, true) : $mr->remarks;
                    // If this was a recovered item, preserve the recovery history
                    if (isset($previousRemarks['recovered']) && $previousRemarks['recovered'] === true) {
                        $previousIncidents[] = [
                            'incident_number' => 1,
                            'original_status' => $previousRemarks['original_status'] ?? 'UNKNOWN',
                            'original_remarks' => $previousRemarks['original_remarks'] ?? [],
                            'recovery_info' => [
                                'recovery_notes' => $previousRemarks['recovery_notes'] ?? null,
                                'recovered_by' => $previousRemarks['recovered_by'] ?? null,
                                'recovery_date' => $previousRemarks['recovery_date'] ?? null,
                                'processed_by' => $previousRemarks['processed_by'] ?? null
                            ]
                        ];
                    }
                } catch (\Exception $e) {
                    // If parsing fails, continue without previous history
                }
            }
            
            // Create detailed accountability record with incident history
            $remarksData = [
                'type' => $status,
                'incident_number' => count($previousIncidents) + 1,
                'reported_by' => $user->fullname ?? $user->user_code ?? 'N/A',
                'reported_by_user_id' => $user->id,
                'reported_by_user_code' => $user->user_code ?? 'N/A',
                'incident_date' => $request->input('incident_date', now()->toDateString()),
                'description' => $request->input('remarks'),
                'estimated_value_loss' => $request->input('estimated_value_loss'),
                'reported_at' => now()->toDateTimeString(),
                'item_details' => [
                    'serial_number' => $item->serial_number ?? 'N/A',
                    'model' => $item->model ?? 'N/A',
                    'unit_value' => $item->unit_value ?? 0
                ]
            ];
            
            // Preserve previous incidents if any
            if (count($previousIncidents) > 0) {
                $remarksData['previous_incidents'] = $previousIncidents;
                $remarksData['is_repeat_incident'] = true;
            } else {
                $remarksData['is_repeat_incident'] = false;
            }

            // Update MR with detailed status
            $mr->status = $status;
            $mr->returned_at = now();
            $mr->processed_by_user_id = null; // Not processed by admin yet
            $mr->remarks = json_encode($remarksData);
            $mr->save();

            // IMPORTANT: Keep item assigned to the issued personnel even when lost
            // This allows QR code scanning to show who the item belongs to
            // The item remains the responsibility of the issued personnel
            // DO NOT clear user_id or location_id - item should stay assigned
            // This way, if someone finds the item, they can return it to the rightful owner

            // Create notification for admins
            $reporterName = $user->fullname ?? $user->user_code ?? 'Unknown User';
            $itemName = $item ? ($item->unit ?? 'Unknown Item') : 'Unknown Item';
            $isRepeat = isset($remarksData['is_repeat_incident']) && $remarksData['is_repeat_incident'] === true;
            $incidentNumber = $remarksData['incident_number'] ?? 1;
            
            if ($isRepeat) {
                $notificationMessage = "⚠️ REPEAT INCIDENT #{$incidentNumber}: Item '{$itemName}' has been reported as {$status} again by {$reporterName}. This item was previously lost/damaged and recovered. Description: {$request->input('remarks')}";
            } else {
                $notificationMessage = "Item '{$itemName}' has been reported as {$status} by {$reporterName}. Description: {$request->input('remarks')}";
            }
            
            // Only create notification if item exists
            if ($item && $item->id) {
                try {
                    $notification = \App\Models\Notification::create([
                        'item_id' => $item->id,
                        'message' => $notificationMessage,
                        'type' => 'item_lost_damaged_report',
                        'is_read' => false,
                    ]);

                    // Log successful notification creation
                    \Log::info("Created lost/damaged report notification: ID {$notification->notification_id}, Item: {$itemName}, Reporter: {$reporterName}");

                    // Broadcast notification to admins
                    if ($notification && $notification->notification_id) {
                        $notification->refresh();
                        $notification->load(['item']);
                        event(new \App\Events\NotificationCreated($notification));
                        \Log::info("Broadcasted lost/damaged report notification event");
                    }
                } catch (\Exception $notificationError) {
                    // Log notification error with full details
                    \Log::error("Failed to create notification for lost/damaged report: " . $notificationError->getMessage());
                    \Log::error("Notification error trace: " . $notificationError->getTraceAsString());
                    // Don't fail the entire operation - the report was still saved
                }
            } else {
                \Log::warning("Cannot create notification: Item not found. Item ID: " . ($item->id ?? 'null') . ", MR Item ID: " . ($mr->item_id ?? 'null'));
            }

            // Log activity
            $itemInfo = $item ? "Item '{$item->unit}'" : "Item (ID: {$mr->item_id})";
            if ($item && $item->serial_number) {
                $itemInfo .= " (Serial: {$item->serial_number}";
                if ($item->model) {
                    $itemInfo .= ", Model: {$item->model}";
                }
                $itemInfo .= ")";
            }
            
            $this->logActivity($request, "Item Reported as {$status}", 
                "{$itemInfo} MR #{$mr->id} reported as {$status} by user {$reporterName}. " .
                "Description: {$request->input('remarks')}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Item reported as {$status} successfully. Admin has been notified.",
                'data' => $mr->fresh(['item'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to report item as lost/damaged: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to report item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recover/restore a LOST or DAMAGED item (when found or repaired)
     */
    public function recoverItem(Request $request, $mrId)
    {
        $request->validate([
            'recovery_notes' => 'required|string|max:1000',
            'recovered_by' => 'nullable|string|max:255',
            'recovery_date' => 'nullable|date'
        ]);

        $mr = MemorandumReceipt::findOrFail($mrId);
        $admin = $request->user();

        if (!in_array($mr->status, ['LOST', 'DAMAGED'])) {
            return response()->json([
                'success' => false,
                'message' => 'This item is not marked as LOST or DAMAGED.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $item = $mr->item;
            if (!$item) {
                throw new \Exception('Item not found for this memorandum receipt.');
            }

            // Parse existing remarks to preserve original LOST/DAMAGED information
            $originalRemarks = [];
            if ($mr->remarks) {
                try {
                    $originalRemarks = is_string($mr->remarks) ? json_decode($mr->remarks, true) : $mr->remarks;
                } catch (\Exception $e) {
                    $originalRemarks = ['description' => $mr->remarks];
                }
            }

            // Create updated remarks with recovery information
            $recoveryData = [
                'original_status' => $mr->status,
                'original_remarks' => $originalRemarks,
                'recovered' => true,
                'recovery_notes' => $request->input('recovery_notes'),
                'recovered_by' => $request->input('recovered_by', $admin->user_code ?? 'SYSTEM'),
                'recovery_date' => $request->input('recovery_date', now()->toDateString()),
                'recovered_at' => now()->toDateTimeString(),
                'processed_by' => $admin->user_code ?? 'SYSTEM'
            ];

            // Update MR status back to ISSUED (restore to original personnel)
            $mr->status = 'ISSUED';
            $mr->returned_at = null; // Clear return date since item is back to issued
            $mr->processed_by_user_id = $admin->id;
            $mr->remarks = json_encode($recoveryData);
            $mr->save();

            // Restore item assignment back to original personnel/user
            if ($item) {
                if ($mr->issued_to_type === 'USER' && $mr->issued_to_user_id) {
                    // Restore to original user
                    $item->user_id = $mr->issued_to_user_id;
                    $item->location_id = null;
                } elseif ($mr->issued_to_type === 'PERSONNEL' && $mr->issued_to_location_id) {
                    // Restore to original location/personnel
                    $item->location_id = $mr->issued_to_location_id;
                    $item->user_id = null;
                }
                $item->save();
            }

            // Get information about who reported it and who it's issued to
            $reportedByName = 'Unknown';
            if (isset($originalRemarks['reported_by'])) {
                $reportedByName = $originalRemarks['reported_by'];
            }
            
            $issuedToName = 'Unknown';
            if ($mr->issued_to_type === 'USER' && $mr->issued_to_user_id) {
                $issuedToUser = \App\Models\User::find($mr->issued_to_user_id);
                $issuedToName = $issuedToUser ? ($issuedToUser->fullname ?? $issuedToUser->user_code ?? 'Unknown') : 'Unknown';
            } elseif ($mr->issued_to_type === 'PERSONNEL' && $mr->issued_to_location_id) {
                $location = \App\Models\Location::find($mr->issued_to_location_id);
                $issuedToName = $location ? ($location->personnel ?? $location->location ?? 'Unknown') : 'Unknown';
            }
            
            $itemName = $item->unit ?? 'Unknown Item';
            $recoveredByName = $request->input('recovered_by', $admin->user_code ?? 'Admin');
            $originalStatus = $recoveryData['original_status'] ?? 'LOST';
            $statusText = $originalStatus === 'LOST' ? 'lost' : 'damaged';
            
            // Create notification that item has been recovered and returned to owner
            // This notification will be visible to admins, the owner, and anyone who reported it
            if ($item && $item->id) {
                try {
                    $notificationMessage = "✅ Item '{$itemName}' that was reported as {$statusText} has been recovered and returned to {$issuedToName}. Recovery notes: {$request->input('recovery_notes')}. Recovered by: {$recoveredByName}";
                    
                    $notification = \App\Models\Notification::create([
                        'item_id' => $item->id,
                        'message' => $notificationMessage,
                        'type' => 'item_recovered',
                        'is_read' => false,
                    ]);
                    
                    if ($notification && $notification->notification_id) {
                        $notification->refresh();
                        $notification->load(['item']);
                        event(new \App\Events\NotificationCreated($notification));
                        \Log::info("Created recovery notification: Item '{$itemName}' returned to {$issuedToName}");
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to create recovery notification: " . $e->getMessage());
                    \Log::error("Notification error trace: " . $e->getTraceAsString());
                }
            }

            // Log activity
            $statusText = $originalStatus === 'LOST' ? 'Lost' : 'Damaged';
            $this->logActivity($request, "Item Recovered", 
                "Recovered {$statusText} item '{$item->unit}' (MR #{$mr->id}). " .
                "Recovery notes: {$request->input('recovery_notes')}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Item recovered successfully. It has been restored to the original personnel.",
                'data' => $mr->fresh(['item', 'processedByUser'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to recover item: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to recover item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Return lost item to owner (for mobile app - when item is scanned and found)
     * This allows any authenticated user to return a lost item when they scan it
     */
    public function returnLostItem(Request $request)
    {
        $request->validate([
            'item_uuid' => 'required|string|exists:items,uuid',
            'return_notes' => 'nullable|string|max:1000',
            'found_by' => 'nullable|string|max:255'
        ]);

        $user = $request->user();
        
        DB::beginTransaction();
        try {
            // Find the item by UUID
            $item = Item::where('uuid', $request->input('item_uuid'))->firstOrFail();
            
            // Find the latest MR record for this item with LOST status
            $mr = MemorandumReceipt::where('item_id', $item->id)
                ->where('status', 'LOST')
                ->orderBy('issued_at', 'desc')
                ->first();
            
            if (!$mr) {
                // Check if item has any LOST status in any MR
                $hasLostStatus = MemorandumReceipt::where('item_id', $item->id)
                    ->where('status', 'LOST')
                    ->exists();
                
                if (!$hasLostStatus) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This item is not marked as LOST.'
                    ], 422);
                }
                
                // If there's a lost MR but not the latest, get the latest one anyway
                $mr = MemorandumReceipt::where('item_id', $item->id)
                    ->orderBy('issued_at', 'desc')
                    ->first();
            }
            
            if (!$mr || $mr->status !== 'LOST') {
                return response()->json([
                    'success' => false,
                    'message' => 'This item is not currently marked as LOST. Current status: ' . ($mr->status ?? 'N/A')
                ], 422);
            }
            
            // Parse existing remarks to preserve original LOST information
            $originalRemarks = [];
            if ($mr->remarks) {
                try {
                    $originalRemarks = is_string($mr->remarks) ? json_decode($mr->remarks, true) : $mr->remarks;
                } catch (\Exception $e) {
                    $originalRemarks = ['description' => $mr->remarks];
                }
            }
            
            // Mark the old LOST MR as RETURNED (close it)
            $mr->status = 'RETURNED';
            $mr->returned_at = now();
            $mr->processed_by_user_id = $user->id;
            $mr->save();
            
            // Create recovery information for the new MR
            $recoveryData = [
                'original_status' => 'LOST',
                'original_remarks' => $originalRemarks,
                'recovered' => true,
                'recovery_notes' => $request->input('return_notes', 'Item found and returned to owner'),
                'recovered_by' => $request->input('found_by', $user->user_code ?? $user->fullname ?? 'Unknown'),
                'recovery_date' => now()->toDateString(),
                'recovered_at' => now()->toDateTimeString(),
                'processed_by' => $user->user_code ?? 'SYSTEM',
                'processed_by_user_id' => $user->id
            ];
            
            // Restore item assignment back to original owner
            if ($mr->issued_to_type === 'USER' && $mr->issued_to_user_id) {
                // Restore to original user
                $item->user_id = $mr->issued_to_user_id;
                $item->location_id = null;
            } elseif ($mr->issued_to_type === 'PERSONNEL' && $mr->issued_to_location_id) {
                // Restore to original location/personnel
                $item->location_id = $mr->issued_to_location_id;
                $item->user_id = null;
            } else {
                // If no original assignment, clear it
                $item->user_id = null;
                $item->location_id = null;
            }
            $item->save();
            
            // Create NEW MR with ISSUED status for the original owner (reissuing the item)
            // Use a slightly later timestamp to ensure it's the latest
            $newIssuedAt = now()->addSecond(); // Add 1 second to ensure it's newer than the RETURNED MR
            $newMR = MemorandumReceipt::create([
                'item_id' => $item->id,
                'issued_to_user_id' => $mr->issued_to_type === 'USER' ? $mr->issued_to_user_id : null,
                'issued_to_location_id' => $mr->issued_to_type === 'PERSONNEL' ? $mr->issued_to_location_id : null,
                'issued_to_code' => $mr->issued_to_code,
                'issued_to_type' => $mr->issued_to_type,
                'issued_by_user_code' => $user->user_code ?? 'N/A',
                'issued_at' => $newIssuedAt,
                'status' => 'ISSUED',
                'remarks' => json_encode($recoveryData)
            ]);
            
            // Get information about the original owner
            $originalOwnerName = 'Unknown';
            if ($mr->issued_to_type === 'USER' && $mr->issued_to_user_id) {
                $issuedToUser = User::find($mr->issued_to_user_id);
                $originalOwnerName = $issuedToUser ? ($issuedToUser->fullname ?? $issuedToUser->user_code ?? 'Unknown') : 'Unknown';
            } elseif ($mr->issued_to_type === 'PERSONNEL' && $mr->issued_to_location_id) {
                $location = \App\Models\Location::find($mr->issued_to_location_id);
                $originalOwnerName = $location ? ($location->personnel ?? $location->location ?? 'Unknown') : 'Unknown';
            }
            
            $itemName = $item->unit ?? $item->description ?? 'Unknown Item';
            $foundByName = $request->input('found_by', $user->fullname ?? $user->user_code ?? 'Unknown');
            
            // Create notification that lost item has been found and returned
            if ($item && $item->id) {
                try {
                    $notificationMessage = "✅ Lost item '{$itemName}' has been found and returned. Found by: {$foundByName}. Return notes: {$request->input('return_notes', 'Item found and returned to owner')}";
                    
                    $notification = \App\Models\Notification::create([
                        'item_id' => $item->id,
                        'message' => $notificationMessage,
                        'type' => 'item_lost_returned',
                        'is_read' => false,
                    ]);
                    
                    if ($notification && $notification->notification_id) {
                        $notification->refresh();
                        $notification->load(['item']);
                        event(new \App\Events\NotificationCreated($notification));
                        \Log::info("Created lost item return notification: Item '{$itemName}' returned by {$foundByName}");
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to create lost item return notification: " . $e->getMessage());
                }
            }
            
            // Log activity
            $this->logActivity($request, 'Lost Item Recovered and Reissued', 
                "Recovered lost item '{$itemName}' (Old MR #{$mr->id}, New MR #{$newMR->id}). " .
                "Recovered by: {$foundByName}. Reissued to: {$originalOwnerName}");
            
            DB::commit();
            
            // Refresh item and reload relationships to get the NEW MR as latest
            $item->refresh();
            $item->load([
                'category',
                'location',
                'condition',
                'condition_number',
                'user',
                'latestMemorandumReceipt.issuedToUser',
                'latestMemorandumReceipt.issuedToLocation'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Lost item recovered and reissued to original owner successfully.',
                'data' => [
                    'item' => new \App\Http\Resources\V1\ItemResource($item),
                    'memorandum_receipt' => $newMR->fresh(['item', 'issuedToUser', 'issuedToLocation']),
                    'recovery_info' => $recoveryData,
                    'original_owner' => $originalOwnerName
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to return lost item: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to return lost item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get MR audit trail by user code
     */
    public function getAuditTrailByUserCode(Request $request, $userCode)
    {
        $mrs = MemorandumReceipt::where('issued_to_code', $userCode)
            ->orWhere('reassigned_to_code', $userCode)
            ->with(['item', 'issuedToUser', 'issuedToLocation', 'reassignedToUser', 'reassignedToLocation', 'processedByUser'])
            ->orderBy('issued_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'user_code' => $userCode,
            'data' => $mrs
        ]);
    }

    /**
     * Get returned items available for reissue (items with RETURNED status and no current assignment)
     */
    public function getReturnedItemsAvailableForReissue(Request $request)
    {
        try {
            // Get all RETURNED MRs, ordered by most recent return first
            $allReturnedMRs = MemorandumReceipt::where('status', 'RETURNED')
                ->with(['item.category', 'item.location', 'item.condition', 'item.user', 'issuedToUser', 'issuedToLocation'])
                ->orderBy('returned_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();
            
            \Log::info("Found {$allReturnedMRs->count()} total RETURNED MRs");
            
            // Group by item_id to get the most recent return for each item
            $latestReturnByItem = $allReturnedMRs->groupBy('item_id')->map(function($mrs) {
                // Get the most recent return (highest returned_at or highest ID if returned_at is null)
                return $mrs->sortByDesc(function($mr) {
                    return $mr->returned_at ? $mr->returned_at->timestamp : 0;
                })->first();
            });
            
            // Map all returned MRs
            $returnedMRs = $latestReturnByItem->map(function($mr) {
                    $item = $mr->item;
                    if (!$item) {
                        \Log::warning("MR #{$mr->id} has no item");
                        return null;
                    }
                    
                    $isAvailable = is_null($item->user_id) && is_null($item->location_id);
                    
                    // If item still has assignment but MR is RETURNED, clear it automatically
                    if (!$isAvailable && $item) {
                        try {
                            $item->user_id = null;
                            $item->location_id = null;
                            $item->save();
                            $isAvailable = true;
                            \Log::info("Auto-cleared assignment for returned item ID: {$item->id}");
                        } catch (\Exception $e) {
                            \Log::warning("Failed to auto-clear assignment for item {$item->id}: " . $e->getMessage());
                        }
                    }
                    
                    // Check if this item has been reissued AFTER this return
                    // Only exclude if there's a newer ISSUED MR created after THIS return date
                    $hasBeenReissued = false;
                    if ($mr->returned_at) {
                        $newerIssued = MemorandumReceipt::where('item_id', $item->id)
                            ->where('status', 'ISSUED')
                            ->where('id', '!=', $mr->id)
                            ->where('issued_at', '>', $mr->returned_at)
                            ->exists();
                        
                        if ($newerIssued) {
                            $hasBeenReissued = true;
                            \Log::info("MR #{$mr->id} (Item: {$item->unit}) has been reissued after return date: {$mr->returned_at}");
                        }
                    }
                    
                    return [
                        'id' => $mr->id,
                        'item_id' => $mr->item_id,
                        'item' => new \App\Http\Resources\V1\ItemResource($item),
                        'issued_to_code' => $mr->issued_to_code,
                        'issued_to_type' => $mr->issued_to_type,
                        'issued_at' => $mr->issued_at,
                        'returned_at' => $mr->returned_at,
                        'remarks' => $mr->remarks,
                        'previous_assignee' => $mr->issued_to_code ?? 'N/A',
                        'is_available' => $isAvailable,
                        'has_been_reissued' => $hasBeenReissued
                    ];
                })
                ->filter(function($mr) {
                    // Filter out nulls and items that have been reissued after this return
                    return $mr !== null && !($mr['has_been_reissued'] ?? false);
                })
                ->values();

            \Log::info("Returned items available for reissue: {$returnedMRs->count()} items (after filtering reissued)");

            return response()->json([
                'success' => true,
                'data' => $returnedMRs,
                'count' => $returnedMRs->count(),
                'debug' => [
                    'total_returned' => $allReturnedMRs->count(),
                    'unique_items' => $latestReturnByItem->count(),
                    'after_filtering' => $returnedMRs->count()
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error("Error fetching returned items: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch returned items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all MR records (for admin)
     */
    public function index(Request $request)
    {
        $query = MemorandumReceipt::with(['item', 'issuedToUser', 'reassignedToUser', 'processedByUser']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by user code or personnel code
        if ($request->has('user_code')) {
            $query->where(function($q) use ($request) {
                $q->where('issued_to_code', $request->input('user_code'))
                  ->orWhere('reassigned_to_code', $request->input('user_code'));
            });
        }

        // Filter by user_id
        if ($request->has('user_id')) {
            $query->where('issued_to_user_id', $request->input('user_id'));
        }

        $mrs = $query->orderBy('issued_at', 'desc')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $mrs
        ]);
    }

    /**
     * Bulk return multiple items for a user (for clearance)
     */
    public function bulkReturnItems(Request $request, $userId)
    {
        $request->validate([
            'mr_ids' => 'required|array',
            'mr_ids.*' => 'required|integer',
            'remarks' => 'nullable|string|max:1000'
        ]);

        $admin = $request->user();
        $mrIds = $request->input('mr_ids');
        $remarks = $request->input('remarks', 'Bulk returned during clearance');

        DB::beginTransaction();
        try {
            $returned = 0;
            $failed = 0;
            $errors = [];

            foreach ($mrIds as $mrId) {
                try {
                    // Handle items without MR records (null IDs from pre-MR system)
                    if ($mrId === null) {
                        $failed++;
                        $errors[] = "Item without MR record cannot be bulk returned. Please return individually.";
                        continue;
                    }

                    $mr = MemorandumReceipt::find($mrId);
                    if (!$mr) {
                        $failed++;
                        $errors[] = "MR #{$mrId} not found";
                        continue;
                    }
                    
                    // Verify this MR belongs to the user
                    if ($mr->issued_to_user_id != $userId && $mr->issued_to_location_id == null) {
                        $failed++;
                        $errors[] = "MR #{$mrId} does not belong to this user";
                        continue;
                    }

                    if ($mr->status !== 'ISSUED') {
                        $failed++;
                        $errors[] = "MR #{$mrId} is already processed";
                        continue;
                    }

                    // Mark MR as returned
                    $mr->markAsReturned($admin->id, $remarks);

                    // Update item to make it available (clear all assignments when returned)
                    $item = $mr->item;
                    if ($item) {
                        // Always clear both assignments when item is returned to make it available for reissue
                            $item->user_id = null;
                            $item->location_id = null;
                        $item->save();
                    }

                    $returned++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "MR #{$mrId}: " . $e->getMessage();
                }
            }

            // Log activity
            $this->logActivity($request, 'Bulk Items Returned', 
                "Bulk returned {$returned} items for user ID {$userId} during clearance");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully returned {$returned} item(s). " . ($failed > 0 ? "{$failed} item(s) failed." : ''),
                'data' => [
                    'returned' => $returned,
                    'failed' => $failed,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to bulk return items: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk return items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk reassign multiple items to another user/personnel (for clearance)
     */
    public function bulkReassignItems(Request $request, $userId)
    {
        $request->validate([
            'mr_ids' => 'required|array',
            'mr_ids.*' => 'required|integer',
            'reassign_to_type' => 'required|in:USER,PERSONNEL',
            'reassign_to_id' => 'required|integer',
            'remarks' => 'nullable|string|max:1000'
        ]);

        $admin = $request->user();
        $mrIds = $request->input('mr_ids');
        $reassignToType = $request->input('reassign_to_type');
        $reassignToId = $request->input('reassign_to_id');
        $remarks = $request->input('remarks', 'Bulk reassigned during clearance');

        DB::beginTransaction();
        try {
            $reassigned = 0;
            $failed = 0;
            $errors = [];

            foreach ($mrIds as $mrId) {
                try {
                    // Handle items without MR records (null IDs from pre-MR system)
                    if ($mrId === null) {
                        $failed++;
                        $errors[] = "Item without MR record cannot be bulk reassigned. Please reassign individually.";
                        continue;
                    }

                    $mr = MemorandumReceipt::find($mrId);
                    if (!$mr) {
                        $failed++;
                        $errors[] = "MR #{$mrId} not found";
                        continue;
                    }
                    
                    // Verify this MR belongs to the user
                    if ($mr->issued_to_user_id != $userId && $mr->issued_to_location_id == null) {
                        $failed++;
                        $errors[] = "MR #{$mrId} does not belong to this user";
                        continue;
                    }

                    if ($mr->status !== 'ISSUED') {
                        $failed++;
                        $errors[] = "MR #{$mrId} is already processed";
                        continue;
                    }

                    // Reassign the item
                    $mr->reassign($reassignToType, $reassignToId, $admin->id, $remarks);
                    $reassigned++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "MR #{$mrId}: " . $e->getMessage();
                }
            }

            // Log activity
            $this->logActivity($request, 'Bulk Items Reassigned', 
                "Bulk reassigned {$reassigned} items from user ID {$userId} to {$reassignToType} ID {$reassignToId} during clearance");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully reassigned {$reassigned} item(s). " . ($failed > 0 ? "{$failed} item(s) failed." : ''),
                'data' => [
                    'reassigned' => $reassigned,
                    'failed' => $failed,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to bulk reassign items: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk reassign items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formalize items by creating MR records for items without them
     * This creates formal MR records for items assigned before the MR system
     */
    public function formalizeItemsForUser(Request $request, $userId)
    {
        $request->validate([
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'exists:items,id',
            'issued_by_user_code' => 'nullable|string',
            'issued_at' => 'nullable|date',
            'remarks' => 'nullable|string'
        ]);

        try {
            $user = User::findOrFail($userId);
            $admin = $request->user();
            
            $userFullname = trim($user->fullname ?? '');
            
            // Get items that already have MR records for this user
            // Check both direct user assignment and location/personnel assignment
            $itemsWithMR = MemorandumReceipt::where('issued_to_user_id', $userId)
                ->pluck('item_id')
                ->toArray();
            
            // Also check for MRs issued to locations where this user is personnel
            $locationMRs = MemorandumReceipt::where('issued_to_type', 'PERSONNEL')
                ->whereHas('item.location', function($q) use ($userFullname) {
                    $q->where('personnel', $userFullname);
                })
                ->pluck('item_id')
                ->toArray();
            
            // Merge both arrays
            $itemsWithMR = array_unique(array_merge($itemsWithMR, $locationMRs));
            
            // If specific item_ids are provided, only formalize those
            // When item_ids are provided, we formalize them regardless of current assignment
            // (useful for items being returned that may already be unassigned)
            if ($request->has('item_ids') && !empty($request->input('item_ids'))) {
                $itemsWithoutMR = \App\Models\Item::whereIn('id', $request->input('item_ids'))
                    ->whereNotIn('id', $itemsWithMR)
                    ->with(['location', 'category', 'condition'])
                    ->get();
                
                // If no items found, check if they already have MR records
                if ($itemsWithoutMR->isEmpty()) {
                    $existingMRs = MemorandumReceipt::whereIn('item_id', $request->input('item_ids'))
                        ->get();
                    if ($existingMRs->isNotEmpty()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'All specified items already have MR records',
                            'created' => 0,
                            'data' => $existingMRs->toArray()
                        ]);
                    }
                }
            } else {
                // Otherwise, get all items assigned to this user that don't have MR records
                $itemsWithoutMR = \App\Models\Item::where(function($query) use ($userId, $userFullname) {
                    // Items directly assigned to user
                    $query->where(function($q) use ($userId) {
                        $q->where('user_id', $userId)
                          ->whereNull('location_id');
                    })
                    // OR items assigned to locations where this user is personnel
                    ->orWhereHas('location', function($q) use ($userFullname) {
                        $q->where('personnel', $userFullname);
                    });
                })
                ->whereNotIn('id', $itemsWithMR)
                ->get();
            }
            
            if ($itemsWithoutMR->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'All items already have MR records',
                    'created' => 0,
                    'data' => []
                ]);
            }
            
            $created = 0;
            $failed = 0;
            $errors = [];
            $createdMRs = [];
            $issuedBy = $request->input('issued_by_user_code', $admin->user_code ?? 'SYSTEM');
            $issuedAt = $request->input('issued_at') ? new \DateTime($request->input('issued_at')) : null;
            $remarks = $request->input('remarks', 'Formalized: Item was assigned before MR system was implemented');
            
            DB::beginTransaction();
            
            foreach ($itemsWithoutMR as $item) {
                try {
                    // Determine issued_to_type and location_id based on item assignment
                    $issuedToType = 'USER';
                    $issuedToLocationId = null;
                    $issuedToUserId = $userId;
                    
                    // If item has location_id, check if it's assigned to personnel
                    if ($item->location_id) {
                        $location = $item->location;
                        if ($location && $location->personnel && trim($location->personnel) === trim($user->fullname)) {
                            $issuedToType = 'PERSONNEL';
                            $issuedToLocationId = $item->location_id;
                            $issuedToUserId = null;
                        }
                    } elseif ($item->user_id == $userId) {
                        // Item is directly assigned to user
                        $issuedToType = 'USER';
                        $issuedToLocationId = null;
                        $issuedToUserId = $userId;
                    } else {
                        // Item might be unassigned, but we still create MR for the user
                        // This handles cases where item is being returned and already unassigned
                        $issuedToType = 'USER';
                        $issuedToLocationId = null;
                        $issuedToUserId = $userId;
                    }
                    
                    $mr = MemorandumReceipt::create([
                        'item_id' => $item->id,
                        'issued_to_user_id' => $issuedToUserId,
                        'issued_to_location_id' => $issuedToLocationId,
                        'issued_to_code' => $user->user_code ?? 'N/A',
                        'issued_to_type' => $issuedToType,
                        'issued_by_user_code' => $issuedBy,
                        'issued_at' => $issuedAt ?? $item->created_at ?? now(),
                        'status' => 'ISSUED',
                        'remarks' => $remarks
                    ]);
                    
                    $createdMRs[] = $mr->load(['item.category', 'item.location', 'item.condition']);
                    $created++;
                } catch (\Exception $e) {
                    $failed++;
                    $itemUnit = $item->unit ?? 'N/A';
                    $errors[] = "Item {$item->id} ({$itemUnit}): " . $e->getMessage();
                    \Log::error("Failed to create MR for item {$item->id}: " . $e->getMessage());
                    \Log::error("Stack trace: " . $e->getTraceAsString());
                }
            }
            
            // Log activity
            if ($created > 0) {
                $this->logActivity($request, 'Items Formalized', 
                    "Formalized {$created} item(s) for user {$user->fullname} ({$user->user_code}) by creating MR records");
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Created {$created} MR record(s) for user {$user->fullname}" . ($failed > 0 ? ". {$failed} failed." : ''),
                'created' => $created,
                'failed' => $failed,
                'total_items' => $itemsWithoutMR->count(),
                'errors' => $errors,
                'data' => $createdMRs
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error formalizing items: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to formalize items: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get accountability report for a user/personnel
     * Shows all equipment with serial numbers, models, MR details for clearance
     */
    public function getAccountabilityReport($userId)
    {
        try {
            $user = User::with('location')->findOrFail($userId);
            $userFullname = trim($user->fullname ?? '');
            
            // Get all MR records for this user (both direct and location-based)
            // Include ISSUED, RETURNED, LOST, and DAMAGED items
            $mrItemsForUser = MemorandumReceipt::where('issued_to_user_id', $userId)
                ->where('issued_to_type', 'USER')
                ->where(function($query) use ($userId) {
                    // Include ISSUED items that are still assigned
                    $query->where(function($q) use ($userId) {
                        $q->where('status', 'ISSUED')
                          ->whereHas('item', function($itemQuery) use ($userId) {
                              $itemQuery->where('user_id', $userId)
                                        ->whereNull('location_id');
                          });
                    })
                    // Include RETURNED, LOST, and DAMAGED items (regardless of current assignment - for audit trail)
                    ->orWhereIn('status', ['RETURNED', 'LOST', 'DAMAGED']);
                })
                ->with(['item.category', 'item.location', 'item.condition', 'processedByUser'])
                ->get();
            
            // Get items assigned to locations where this user is currently the personnel
            // Include ISSUED, RETURNED, LOST, and DAMAGED items
            $mrItemsForPersonnel = MemorandumReceipt::where('issued_to_type', 'PERSONNEL')
                ->where(function($query) use ($userFullname) {
                    // Include ISSUED items that are still assigned to locations with this personnel
                    $query->where(function($q) use ($userFullname) {
                        $q->where('status', 'ISSUED')
                          ->whereHas('item.location', function($locQuery) use ($userFullname) {
                              $locQuery->where('personnel', $userFullname);
                          })
                          ->whereHas('item', function($itemQuery) {
                              $itemQuery->whereNotNull('location_id');
                          });
                    })
                    // Include RETURNED, LOST, and DAMAGED items for locations where this user was/is personnel (for audit trail)
                    ->orWhere(function($q) use ($userFullname) {
                        $q->whereIn('status', ['RETURNED', 'LOST', 'DAMAGED'])
                          ->whereHas('item.location', function($locQuery) use ($userFullname) {
                              $locQuery->where('personnel', $userFullname);
                          });
                    });
                })
                ->with(['item.category', 'item.location', 'item.condition', 'processedByUser'])
                ->get();
            
            // Combine both MR sets
            $mrs = $mrItemsForUser->merge($mrItemsForPersonnel);
            
            // Also get items directly assigned to user but WITHOUT MR records (items assigned before MR system)
            $directItemsForUser = \App\Models\Item::where('user_id', $userId)
                ->whereNull('location_id') // Only items directly assigned to user, not via location
                ->with(['category', 'location', 'condition'])
                ->get()
                ->filter(function($item) use ($mrs) {
                    // Only include items that don't have an MR record
                    return !$mrs->contains(function($mr) use ($item) {
                        return $mr->item_id === $item->id;
                    });
                });
            
            // Also get items assigned to locations where this user is currently the personnel (without MR)
            $directItemsForPersonnel = \App\Models\Item::whereNotNull('location_id')
                ->whereHas('location', function($query) use ($userFullname) {
                    $query->where('personnel', $userFullname);
                })
                ->with(['category', 'location', 'condition'])
                ->get()
                ->filter(function($item) use ($mrs) {
                    // Only include items that don't have an MR record
                    return !$mrs->contains(function($mr) use ($item) {
                        return $mr->item_id === $item->id;
                    });
                });
            
            // Create virtual MR records for items without MR records
            $virtualMRs = [];
            foreach ($directItemsForUser as $item) {
                $virtualMRs[] = (object)[
                    'id' => null, // No MR number
                    'item_id' => $item->id,
                    'item' => $item,
                    'issued_at' => $item->created_at ?? now(),
                    'issued_by_user_code' => 'SYSTEM',
                    'issued_to_code' => $user->user_code ?? 'N/A',
                    'issued_to_type' => 'USER',
                    'status' => 'ISSUED',
                    'returned_at' => null,
                    'remarks' => 'Item assigned before MR system was implemented. This item was directly assigned to the personnel and does not have a Memorandum Receipt record.',
                    'processedByUser' => null
                ];
            }
            
            foreach ($directItemsForPersonnel as $item) {
                $location = $item->location;
                $virtualMRs[] = (object)[
                    'id' => null, // No MR number
                    'item_id' => $item->id,
                    'item' => $item,
                    'issued_at' => $item->created_at ?? now(),
                    'issued_by_user_code' => 'SYSTEM',
                    'issued_to_code' => $location->personnel_code ?? 'N/A',
                    'issued_to_type' => 'PERSONNEL',
                    'status' => 'ISSUED',
                    'returned_at' => null,
                    'remarks' => 'Item assigned before MR system was implemented. This item was directly assigned to the personnel and does not have a Memorandum Receipt record.',
                    'processedByUser' => null
                ];
            }
            
            // Convert Eloquent Collection to plain array of objects and combine with virtual MRs
            // We need to keep them as objects, not arrays, so we can access properties with ->
            $allMRsArray = [];
            foreach ($mrs as $mr) {
                $allMRsArray[] = $mr; // Keep as Eloquent model object
            }
            $allMRsArray = array_merge($allMRsArray, $virtualMRs);
            
            // Sort by issued_at descending
            usort($allMRsArray, function($a, $b) {
                $dateA = is_object($a) ? ($a->issued_at ?? '1970-01-01') : ($a['issued_at'] ?? '1970-01-01');
                $dateB = is_object($b) ? ($b->issued_at ?? '1970-01-01') : ($b['issued_at'] ?? '1970-01-01');
                return strtotime($dateB) - strtotime($dateA);
            });
            
            // Convert to Support Collection (not Eloquent Collection) for easier manipulation
            $allMRs = collect($allMRsArray);
            
            // Get location from user's direct relationship, or from their assigned items
            $userLocation = 'N/A';
            if ($user->location && $user->location->location) {
                $userLocation = $user->location->location;
            } else {
                // Try to get location from items assigned to this user
                $itemWithLocation = \App\Models\Item::where('user_id', $userId)
                    ->whereNotNull('location_id')
                    ->with('location')
                    ->first();
                if ($itemWithLocation && $itemWithLocation->location) {
                    $userLocation = $itemWithLocation->location->location;
                } else {
                    // Try to get location from locations where user is personnel
                    $locationAsPersonnel = \App\Models\Location::where('personnel', $userFullname)->first();
                    if ($locationAsPersonnel) {
                        $userLocation = $locationAsPersonnel->location;
                    }
                }
            }
            
            $report = [
                'personnel' => [
                    'id' => $user->id,
                    'name' => $user->fullname,
                    'user_code' => $user->user_code,
                    'status' => $user->status,
                    'location' => $userLocation
                ],
                'items' => $allMRs->map(function($mr) {
                    $item = $mr->item;
                    if (!$item) {
                        return null;
                    }
                    
                    // Handle both Eloquent models and plain objects
                    $itemObj = is_object($item) ? $item : (object)$item;
                    $category = is_object($itemObj->category ?? null) ? $itemObj->category : (object)($itemObj->category ?? []);
                    $condition = is_object($itemObj->condition ?? null) ? $itemObj->condition : (object)($itemObj->condition ?? []);
                    
                    $remarksData = null;
                    if ($mr->remarks) {
                        $remarksData = is_string($mr->remarks) ? json_decode($mr->remarks, true) : $mr->remarks;
                    }
                    
                    return [
                        'mr_number' => $mr->id ?? 'N/A',
                        'item_id' => $itemObj->id ?? null,
                        'item_uuid' => $itemObj->uuid ?? null,
                        'unit' => $itemObj->unit ?? 'N/A',
                        'description' => $itemObj->description ?? 'N/A',
                        'serial_number' => $itemObj->serial_number ?? 'N/A',
                        'model' => $itemObj->model ?? 'N/A',
                        'category' => ($category->category ?? $category->name ?? 'N/A'),
                        'pac' => $itemObj->pac ?? 'N/A',
                        'unit_value' => $itemObj->unit_value ?? 0,
                        'condition' => ($condition->condition ?? $condition->name ?? 'N/A'),
                        'issued_date' => $mr->issued_at ?? ($itemObj->created_at ?? now()),
                        'issued_by' => $mr->issued_by_user_code ?? 'SYSTEM',
                        'issued_to_code' => $mr->issued_to_code ?? 'N/A',
                        'issued_to_type' => $mr->issued_to_type ?? 'USER',
                        'status' => $mr->status ?? 'ISSUED', // ISSUED, RETURNED, LOST, DAMAGED
                        'returned_date' => $mr->returned_at ?? null,
                        'remarks' => $remarksData,
                        'processed_by' => (is_object($mr->processedByUser ?? null) ? ($mr->processedByUser->user_code ?? 'N/A') : 'N/A')
                    ];
                })->filter()->values(), // Filter out null items
                'summary' => [
                    'total_items' => $allMRs->count(),
                    'issued' => $allMRs->where('status', 'ISSUED')->count(),
                    'returned' => $allMRs->where('status', 'RETURNED')->count(),
                    'lost' => $allMRs->where('status', 'LOST')->count(),
                    'damaged' => $allMRs->where('status', 'DAMAGED')->count(),
                    'total_value' => $allMRs->sum(function($mr) {
                        $item = is_object($mr->item) ? $mr->item : (is_array($mr->item) ? (object)$mr->item : null);
                        return $item && isset($item->unit_value) ? $item->unit_value : 0;
                    }),
                    'lost_damaged_value' => $allMRs->whereIn('status', ['LOST', 'DAMAGED'])
                        ->sum(function($mr) {
                            $item = is_object($mr->item) ? $mr->item : (is_array($mr->item) ? (object)$mr->item : null);
                            return $item && isset($item->unit_value) ? $item->unit_value : 0;
                        }),
                    'issued_value' => $allMRs->where('status', 'ISSUED')
                        ->sum(function($mr) {
                            $item = is_object($mr->item) ? $mr->item : (is_array($mr->item) ? (object)$mr->item : null);
                            return $item && isset($item->unit_value) ? $item->unit_value : 0;
                        })
                ]
            ];
            
            // Check if PDF format is requested
            if (request()->has('format') && request()->input('format') === 'pdf') {
                return $this->generateAccountabilityReportPDF($report);
            }
            
            return response()->json([
                'success' => true,
                'data' => $report,
                'generated_at' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            \Log::error("Error generating accountability report: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate accountability report: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate PDF accountability report using Dompdf
     */
    private function generateAccountabilityReportPDF($report)
    {
        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->set_option('isHtml5ParserEnabled', true);
            $dompdf->set_option('isRemoteEnabled', true);
            $dompdf->set_option('defaultFont', 'Arial');
            
            $html = view('reports.accountability-report', [
                'report' => $report,
                'generated_at' => now()
            ])->render();
            
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $filename = 'Accountability_Report_' . $report['personnel']['user_code'] . '_' . now()->format('Y-m-d') . '.pdf';
            
            return response()->streamDownload(function() use ($dompdf) {
                echo $dompdf->output();
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            \Log::error("Error generating PDF report: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process clearance with status tracking (Return, Transfer, Lost, Damaged)
     * Enhanced clearance process for personnel changes
     */
    public function processClearanceWithStatus(Request $request, $userId)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.mr_id' => 'required|integer',
            'items.*.action' => 'required|in:RETURN,TRANSFER,LOST,DAMAGED',
            'items.*.status_details' => 'nullable|array',
            'items.*.new_personnel_id' => 'nullable|integer|required_if:items.*.action,TRANSFER',
            'items.*.new_location_id' => 'nullable|integer',
            'cleared_by' => 'required|string|max:255',
            'clearance_date' => 'nullable|date'
        ]);

        $user = User::findOrFail($userId);
        $admin = $request->user();
        
        $results = [
            'returned' => 0,
            'transferred' => 0,
            'lost' => 0,
            'damaged' => 0,
            'failed' => 0,
            'errors' => []
        ];

        DB::beginTransaction();
        try {
            foreach ($request->input('items') as $itemData) {
                try {
                    $mr = MemorandumReceipt::find($itemData['mr_id']);
                    if (!$mr) {
                        $results['failed']++;
                        $results['errors'][] = "MR #{$itemData['mr_id']} not found";
                        continue;
                    }

                    if ($mr->status !== 'ISSUED') {
                        $results['failed']++;
                        $results['errors'][] = "MR #{$itemData['mr_id']} is already processed";
                        continue;
                    }

                    $item = $mr->item;
                    $statusDetails = $itemData['status_details'] ?? [];
                    
                    switch ($itemData['action']) {
                        case 'RETURN':
                            $mr->markAsReturned($admin->id, 'Cleared during personnel change - Returned to inventory');
                            if ($item) {
                                // Always clear both assignments when item is returned to make it available for reissue
                                $item->user_id = null;
                                $item->location_id = null;
                                $item->save();
                            }
                            $results['returned']++;
                            break;

                        case 'TRANSFER':
                            $newPersonnelId = $itemData['new_personnel_id'];
                            $newLocationId = $itemData['new_location_id'] ?? null;
                            
                            // Determine if transferring to user or personnel
                            if ($newLocationId) {
                                $newLocation = \App\Models\Location::findOrFail($newLocationId);
                                if (!$newLocation->personnel) {
                                    throw new \Exception("Selected location has no personnel assigned.");
                                }
                                
                                if (!$newLocation->personnel_code) {
                                    $newLocation->personnel_code = \App\Models\Location::generatePersonnelCode($newLocation->location);
                                    $newLocation->save();
                                }
                                
                                $newCode = $newLocation->personnel_code;
                                $newType = 'PERSONNEL';
                                $newId = $newLocation->id;
                                
                                if ($item) {
                                    $item->location_id = $newLocation->id;
                                    $item->user_id = null;
                                    $item->save();
                                }
                            } else {
                                $newUser = User::findOrFail($newPersonnelId);
                                if ($newUser->status !== 'ACTIVE') {
                                    throw new \Exception('Cannot transfer to inactive user.');
                                }
                                $newCode = $newUser->user_code;
                                $newType = 'USER';
                                $newId = $newUser->id;
                                
                                if ($item) {
                                    $item->user_id = $newUser->id;
                                    $item->location_id = null;
                                    $item->save();
                                }
                            }

                            // Reassign MR
                            $mr->reassignTo($newId, $newCode, $newType, $admin->id, 'Transferred during clearance');

                            // Create new MR for the new assignee
                            MemorandumReceipt::create([
                                'item_id' => $item->id,
                                'issued_to_user_id' => $newType === 'USER' ? $newId : null,
                                'issued_to_location_id' => $newType === 'PERSONNEL' ? $newId : null,
                                'issued_to_code' => $newCode,
                                'issued_to_type' => $newType,
                                'issued_by_user_code' => $admin->user_code ?? 'N/A',
                                'issued_at' => now(),
                                'status' => 'ISSUED'
                            ]);
                            
                            $results['transferred']++;
                            break;

                        case 'LOST':
                            $remarksData = [
                                'type' => 'LOST',
                                'reported_by' => $statusDetails['reported_by'] ?? $user->fullname,
                                'incident_date' => $statusDetails['incident_date'] ?? now()->toDateString(),
                                'description' => $statusDetails['description'] ?? 'Lost during service',
                                'cleared_by' => $request->input('cleared_by'),
                                'cleared_at' => $request->input('clearance_date', now()->toDateTimeString()),
                                'item_details' => [
                                    'serial_number' => $item->serial_number ?? 'N/A',
                                    'model' => $item->model ?? 'N/A',
                                    'brand' => $item->brand ?? 'N/A',
                                    'unit_value' => $item->unit_value ?? 0
                                ]
                            ];
                            
                            $mr->status = 'LOST';
                            $mr->returned_at = now();
                            $mr->processed_by_user_id = $admin->id;
                            $mr->remarks = json_encode($remarksData);
                            $mr->save();
                            
                            if ($item) {
                                if ($mr->issued_to_type === 'USER') {
                                    $item->user_id = null;
                                } elseif ($mr->issued_to_type === 'PERSONNEL') {
                                    $item->location_id = null;
                                }
                                $item->save();
                            }
                            
                            $results['lost']++;
                            break;

                        case 'DAMAGED':
                            $remarksData = [
                                'type' => 'DAMAGED',
                                'reported_by' => $statusDetails['reported_by'] ?? $user->fullname,
                                'incident_date' => $statusDetails['incident_date'] ?? now()->toDateString(),
                                'description' => $statusDetails['description'] ?? 'Damaged during service',
                                'estimated_value_loss' => $statusDetails['estimated_value_loss'] ?? null,
                                'cleared_by' => $request->input('cleared_by'),
                                'cleared_at' => $request->input('clearance_date', now()->toDateTimeString()),
                                'item_details' => [
                                    'serial_number' => $item->serial_number ?? 'N/A',
                                    'model' => $item->model ?? 'N/A',
                                    'brand' => $item->brand ?? 'N/A',
                                    'unit_value' => $item->unit_value ?? 0
                                ]
                            ];
                            
                            $mr->status = 'DAMAGED';
                            $mr->returned_at = now();
                            $mr->processed_by_user_id = $admin->id;
                            $mr->remarks = json_encode($remarksData);
                            $mr->save();
                            
                            if ($item) {
                                if ($mr->issued_to_type === 'USER') {
                                    $item->user_id = null;
                                } elseif ($mr->issued_to_type === 'PERSONNEL') {
                                    $item->location_id = null;
                                }
                                $item->save();
                            }
                            
                            $results['damaged']++;
                            break;
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "MR #{$itemData['mr_id']}: " . $e->getMessage();
                    \Log::error("Clearance error for MR #{$itemData['mr_id']}: " . $e->getMessage());
                }
            }

            // Log activity
            $this->logActivity($request, 'Clearance Processed', 
                "Processed clearance for {$user->fullname} ({$user->user_code}). " .
                "Returned: {$results['returned']}, Transferred: {$results['transferred']}, " .
                "Lost: {$results['lost']}, Damaged: {$results['damaged']}. " .
                "Cleared by: {$request->input('cleared_by')}");

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Clearance processed successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to process clearance: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process clearance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reissue a returned item to a new assignee
     */
    public function reissueItem(Request $request, $mrId)
    {
        $request->validate([
            'new_user_id' => 'nullable|exists:users,id',
            'new_location_id' => 'nullable|exists:locations,id',
            'remarks' => 'nullable|string|max:1000'
        ]);

        $mr = MemorandumReceipt::findOrFail($mrId);
        $admin = $request->user();

        if ($mr->status !== 'RETURNED') {
            return response()->json([
                'success' => false,
                'message' => 'This item is not returned and cannot be reissued.'
            ], 422);
        }

        $newUserId = $request->input('new_user_id');
        $newLocationId = $request->input('new_location_id');
        
        if (!$newUserId && !$newLocationId) {
            return response()->json([
                'success' => false,
                'message' => 'Either new_user_id or new_location_id must be provided.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $item = $mr->item;
            if (!$item) {
                throw new \Exception('Item not found for this memorandum receipt.');
            }

            $newCode = null;
            $newType = null;
            $newId = null;
            
            if ($newUserId) {
                $newUser = User::findOrFail($newUserId);
                if ($newUser->status !== 'ACTIVE') {
                    throw new \Exception('Cannot reissue to inactive user.');
                }
                $newCode = $newUser->user_code;
                $newType = 'USER';
                $newId = $newUser->id;
                
                // Update item assignment
                $item->user_id = $newUser->id;
                $item->location_id = null; // Clear location if reissuing to user
                $item->save();
            } else {
                $newLocation = \App\Models\Location::findOrFail($newLocationId);
                if (!$newLocation->personnel) {
                    throw new \Exception('Selected location has no personnel assigned.');
                }
                
                // Generate personnel_code if not exists
                if (!$newLocation->personnel_code) {
                    $newLocation->personnel_code = \App\Models\Location::generatePersonnelCode($newLocation->location);
                    $newLocation->save();
                }
                
                $newCode = $newLocation->personnel_code;
                $newType = 'PERSONNEL';
                $newId = $newLocation->id;
                
                // Update item assignment
                $item->location_id = $newLocation->id;
                $item->user_id = null; // Clear user if reissuing to personnel
                $item->save();
            }

            // Create new MR for the new assignee
            $newMr = MemorandumReceipt::create([
                'item_id' => $item->id,
                'issued_to_user_id' => $newType === 'USER' ? $newId : null,
                'issued_to_location_id' => $newType === 'PERSONNEL' ? $newId : null,
                'issued_to_code' => $newCode,
                'issued_to_type' => $newType,
                'issued_by_user_code' => $admin->user_code ?? 'N/A',
                'issued_at' => now(),
                'status' => 'ISSUED',
                'remarks' => $request->input('remarks')
            ]);

            // Log activity
            $this->logActivity($request, 'Item Reissued', 
                "Reissued item '{$item->unit}' (MR #{$mr->id}) to {$newCode}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item reissued successfully.',
                'data' => $newMr->fresh(['item', 'issuedToUser', 'issuedToLocation'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error reissuing item: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reissue item: ' . $e->getMessage()
            ], 500);
        }
    }
}
