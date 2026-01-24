<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\SupplyRequestMessage;
use App\Models\Item;
use App\Models\Category;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Location;
use App\Models\Notification;
use App\Models\ItemUsage;
use App\Events\NotificationCreated;
use App\Events\SupplyRequestApproved;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Dompdf\Dompdf;
use Dompdf\Options;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SupplyRequestController extends Controller
{
    /**
     * Get available supply stocks for user dashboard
     */
    public function getAvailableSupplies(Request $request): JsonResponse
    {
        try {
            // Find the "Supply" category
            $supplyCategory = Category::where('category', 'Supply')->first();

            if (!$supplyCategory) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No supply category found'
                ]);
            }

            $query = Item::with(['category', 'location'])
                ->where('category_id', $supplyCategory->id)
                ->whereNull('deleted_at')
                ->orderBy('unit', 'asc');

            // Apply search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('unit', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('pac', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 12);
            $items = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $items->items(),
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching available supplies: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available supplies'
            ], 500);
        }
    }

    /**
     * Get all supply requests (for Supply Account and Admin)
     */
    public function getAllRequests(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');

            // Check if user has permission (Supply or Admin)
            if (!in_array($role, ['supply', 'admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account and Admin can view all requests'
                ], 403);
            }

            $query = SupplyRequest::with([
                'requestedBy' => function($q) {
                    $q->with('location');
                },
                'approver',
                'forwardedToAdmin',
                'assignedToAdmin',
                'fulfilledBy',
                'items' // Load items relationship for multi-item requests
            ])->orderBy('created_at', 'desc');

            // Filter by target_supply_account_id for supply accounts
            // Supply accounts can only see requests submitted to them
            // Admins can see all requests
            if ($role === 'supply') {
                // Check if target_supply_account_id column exists
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    // Show requests where this supply account is the target
                    $query->where(function($q) use ($user) {
                        $q->where('target_supply_account_id', $user->id)
                          // Also show requests without target_supply_account_id (backward compatibility)
                          // for requests created before the feature was added
                          ->orWhereNull('target_supply_account_id');
                    });
                }
            }
            // Note: All admins can see ALL requests regardless of target_supply_account_id
            // Only the assigned admin can approve/reject, but all admins can view them

            // Apply filters
            if ($request->has('status') && !empty($request->status)) {
                // Validate status value to prevent constraint violations
                $validStatuses = ['pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'ready_for_pickup', 'rejected', 'fulfilled', 'cancelled'];
                $requestedStatus = $request->status;
                if (in_array($requestedStatus, $validStatuses)) {
                    $query->where('status', $requestedStatus);
                } else {
                    Log::warning("Invalid status filter requested: {$requestedStatus}");
                }
            }

            if ($request->has('start_date') && !empty($request->start_date)) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date') && !empty($request->end_date)) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('request_number', 'like', "%{$search}%")
                      ->orWhereHas('requestedBy', function($userQuery) use ($search) {
                          $userQuery->where('fullname', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            
            // Wrap pagination in try-catch to handle potential constraint issues
            try {
                $requests = $query->paginate($perPage);
            } catch (\Illuminate\Database\QueryException $e) {
                // Log the full error for debugging
                Log::error('Database query error in getAllRequests: ' . $e->getMessage());
                Log::error('SQL: ' . $e->getSql());
                Log::error('Bindings: ' . json_encode($e->getBindings()));
                
                // If it's a constraint violation, provide helpful error message
                if (strpos($e->getMessage(), 'check_status') !== false || 
                    strpos($e->getMessage(), 'CHECK constraint') !== false ||
                    strpos($e->getMessage(), 'ready_for_pickup') !== false) {
                    throw new \Exception('Database constraint error. Please ensure the migration has been run successfully and the status constraint includes all valid statuses.');
                }
                
                // Re-throw other database errors
                throw $e;
            }

            // Transform the data to include item information
            $transformedRequests = $requests->map(function ($request) {
                $item = $request->item();
                $requestedByUser = $request->requestedBy;
                
                // Get user location correctly
                $userLocation = 'N/A';
                if ($requestedByUser && $requestedByUser->location_id) {
                    try {
                        // Ensure location is loaded
                        if (!$requestedByUser->relationLoaded('location')) {
                            $requestedByUser->load('location');
                        }
                        
                        if ($requestedByUser->location) {
                            // Location model uses 'location' field, not 'name'
                            $userLocation = $requestedByUser->location->location ?? 'N/A';
                        } else {
                            // Fallback: fetch directly
                            $location = Location::find($requestedByUser->location_id);
                            if ($location) {
                                $userLocation = $location->location ?? 'N/A';
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error fetching location: ' . $e->getMessage());
                    }
                }

                // Handle case where requestedBy user might be null (deleted user)
                $userData = [
                    'id' => $requestedByUser ? ($requestedByUser->id ?? null) : null,
                    'name' => $requestedByUser ? ($requestedByUser->fullname ?? $requestedByUser->username ?? 'Unknown') : 'N/A',
                    'email' => $requestedByUser ? ($requestedByUser->email ?? null) : null,
                    'location' => $userLocation,
                ];

                // Determine supply account name (who approved or fulfilled)
                $supplyName = 'N/A';
                $approverRole = null;
                $fulfillerRole = null;
                
                if ($request->approver) {
                    $approverRole = strtolower($request->approver->role ?? '');
                }
                if ($request->fulfilledBy) {
                    $fulfillerRole = strtolower($request->fulfilledBy->role ?? '');
                }
                
                // Priority 1: If request is fulfilled, show the supply account who fulfilled it
                if ($request->status === 'fulfilled' && $request->fulfilledBy && $fulfillerRole === 'supply') {
                    $supplyName = $request->fulfilledBy->fullname ?? $request->fulfilledBy->username ?? 'Unknown';
                }
                // Priority 2: If request was approved by a supply account, show their name
                elseif ($request->approver && $approverRole === 'supply') {
                    // Show name if it's not pending or rejected
                    if (!in_array($request->status, ['pending', 'rejected', 'cancelled'])) {
                        $supplyName = $request->approver->fullname ?? $request->approver->username ?? 'Unknown';
                    } else {
                        $supplyName = 'N/A';
                    }
                }
                // Priority 3: If request is approved/admin_assigned/admin_accepted but not fulfilled
                elseif (in_array($request->status, ['approved', 'supply_approved', 'admin_assigned', 'admin_accepted']) && $request->status !== 'fulfilled') {
                    // If current approver is admin, try to find the original supply approver from messages
                    if ($approverRole === 'admin' || $approverRole === 'super_admin') {
                        try {
                            // Check messages to find supply account user who was involved
                            // Look for any message from a supply account user (they would have approved it initially)
                            $supplyMessage = SupplyRequestMessage::where('supply_request_id', $request->id)
                                ->whereHas('user', function($q) {
                                    $q->where('role', 'supply');
                                })
                                ->orderBy('created_at', 'asc')
                                ->first();
                            
                            if ($supplyMessage && $supplyMessage->user) {
                                $supplyName = $supplyMessage->user->fullname ?? $supplyMessage->user->username ?? 'Unknown';
                            } else {
                                // If status is supply_approved, there should be a supply approver
                                // But if we can't find it, show "Waiting"
                                $supplyName = 'Waiting';
                            }
                        } catch (\Exception $e) {
                            Log::error('Error finding supply approver from messages: ' . $e->getMessage());
                            $supplyName = 'Waiting';
                        }
                    } else {
                        $supplyName = 'Waiting';
                    }
                }
                // Priority 4: If request is pending or rejected, show N/A
                elseif (in_array($request->status, ['pending', 'rejected', 'cancelled'])) {
                    $supplyName = 'N/A';
                }
                // Default: show "Waiting" for other statuses that are not fulfilled
                else {
                    $supplyName = 'Waiting';
                }

                // Get all items for this request (multi-item support)
                $requestItems = [];
                if (Schema::hasTable('supply_request_items') && $request->relationLoaded('items') && $request->items->count() > 0) {
                    // Multiple items - use items from pivot table
                    foreach ($request->items as $requestItem) {
                        $itemObj = $requestItem->item();
                        if ($itemObj) {
                            $requestItems[] = [
                                'id' => $requestItem->id,
                                'item_id' => $requestItem->item_id,
                                'item_name' => $itemObj->unit ?? $itemObj->description ?? 'N/A',
                                'item_quantity' => $itemObj->quantity ?? 0,
                                'quantity' => $requestItem->quantity,
                                'status' => $requestItem->status ?? 'pending',
                                'rejection_reason' => $requestItem->rejection_reason,
                            ];
                        }
                    }
                } else {
                    // Single item - use main request data (backward compatible)
                    $requestItems[] = [
                        'id' => null,
                        'item_id' => $request->item_id,
                        'item_name' => $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A',
                        'item_quantity' => $item ? ($item->quantity ?? 0) : 0,
                        'quantity' => $request->quantity,
                        'status' => 'pending',
                        'rejection_reason' => null,
                    ];
                }

                // For backward compatibility, keep main item_name and quantity
                $mainItem = $requestItems[0] ?? null;

                return [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'item_id' => $request->item_id,
                    'item_name' => $mainItem ? $mainItem['item_name'] : ($item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A'),
                    'item_quantity' => $mainItem ? $mainItem['item_quantity'] : ($item ? ($item->quantity ?? 0) : 0),
                    'quantity' => $request->quantity, // Total quantity for backward compatibility
                    'items' => $requestItems, // Array of all items
                    'items_count' => count($requestItems), // Number of items
                    'notes' => $request->notes,
                    'status' => $request->status,
                    'user' => $userData,
                    'requested_by' => $userData,
                    'supply_name' => $supplyName,
                    'approved_by' => $request->approver ? [
                        'id' => $request->approver->id,
                        'name' => $request->approver->fullname ?? $request->approver->username ?? 'Unknown',
                    ] : null,
                    'forwarded_to_admin' => $request->forwardedToAdmin ? [
                        'id' => $request->forwardedToAdmin->id,
                        'name' => $request->forwardedToAdmin->fullname ?? $request->forwardedToAdmin->username ?? 'Unknown',
                    ] : null,
                    'assigned_to_admin' => $request->assignedToAdmin ? [
                        'id' => $request->assignedToAdmin->id,
                        'name' => $request->assignedToAdmin->fullname ?? $request->assignedToAdmin->username ?? 'Unknown',
                    ] : null,
                    'admin_comments' => $request->admin_comments,
                    'approval_proof' => $request->approval_proof ? Storage::url($request->approval_proof) : null,
                    'approved_at' => $request->approved_at ? $request->approved_at->toISOString() : null,
                    'fulfilled_at' => $request->fulfilled_at ? $request->fulfilled_at->toISOString() : null,
                    'pickup_scheduled_at' => (Schema::hasColumn('supply_requests', 'pickup_scheduled_at') && $request->pickup_scheduled_at) ? $request->pickup_scheduled_at->toISOString() : null,
                    'pickup_notified_at' => (Schema::hasColumn('supply_requests', 'pickup_notified_at') && $request->pickup_notified_at) ? $request->pickup_notified_at->toISOString() : null,
                    'created_at' => $request->created_at->toISOString(),
                    'created_at_formatted' => $request->created_at->format('M d, Y h:i A'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedRequests,
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                    'from' => $requests->firstItem(),
                    'to' => $requests->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching all supply requests: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supply requests: ' . (config('app.debug') ? $e->getMessage() : 'Please check server logs'),
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null
            ], 500);
        }
    }

    /**
     * Get user's own supply requests
     */
    public function getMyRequests(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $query = SupplyRequest::with(['items']) // Load items relationship for multi-item requests
                ->where('requested_by_user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Apply status filter
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $requests = $query->paginate($perPage);

            // Transform the data
            $transformedRequests = $requests->map(function ($request) {
                $item = $request->item();
                
                // Get all items for this request (multi-item support)
                $requestItems = [];
                if (Schema::hasTable('supply_request_items') && $request->relationLoaded('items') && $request->items->count() > 0) {
                    // Multiple items - use items from pivot table
                    foreach ($request->items as $requestItem) {
                        $itemObj = $requestItem->item();
                        if ($itemObj) {
                            $requestItems[] = [
                                'id' => $requestItem->id,
                                'item_id' => $requestItem->item_id,
                                'item_name' => $itemObj->unit ?? $itemObj->description ?? 'N/A',
                                'item_quantity' => $itemObj->quantity ?? 0,
                                'quantity' => $requestItem->quantity,
                                'status' => $requestItem->status ?? 'pending',
                                'rejection_reason' => $requestItem->rejection_reason,
                            ];
                        }
                    }
                } else {
                    // Single item - use main request data (backward compatible)
                    $requestItems[] = [
                        'id' => null,
                        'item_id' => $request->item_id,
                        'item_name' => $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A',
                        'item_quantity' => $item ? ($item->quantity ?? 0) : 0,
                        'quantity' => $request->quantity,
                        'status' => 'pending',
                        'rejection_reason' => null,
                    ];
                }

                // For backward compatibility, keep main item_name and quantity
                $mainItem = $requestItems[0] ?? null;
                
                // Check if receipt is available (approved or fulfilled status with approval_proof)
                // Verify that the file actually exists in storage
                $approvalProofExists = false;
                $approvalProofUrl = null;
                if ($request->approval_proof) {
                    $approvalProofExists = Storage::disk('public')->exists($request->approval_proof);
                    if ($approvalProofExists) {
                        $approvalProofUrl = Storage::url($request->approval_proof);
                    }
                }
                $hasReceipt = in_array($request->status, ['approved', 'fulfilled']) && $approvalProofExists;
                
                return [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'item_id' => $request->item_id,
                    'item_name' => $mainItem ? $mainItem['item_name'] : ($item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A'),
                    'item_description' => $mainItem ? $mainItem['item_name'] : ($item ? ($item->description ?? 'N/A') : 'N/A'),
                    'item_quantity' => $mainItem ? $mainItem['item_quantity'] : ($item ? ($item->quantity ?? 0) : 0),
                    'approval_proof' => $approvalProofUrl,
                    'has_receipt' => $hasReceipt, // Helper field for mobile app to easily identify receipts
                    'quantity' => $request->quantity, // Total quantity for backward compatibility
                    'items' => $requestItems, // Array of all items
                    'items_count' => count($requestItems), // Number of items
                    'notes' => $request->notes,
                    'status' => $request->status,
                    'approved_by' => $request->approver ? [
                        'id' => $request->approver->id,
                        'name' => $request->approver->fullname ?? $request->approver->username ?? 'Unknown',
                    ] : null,
                    'approved_at' => $request->approved_at ? $request->approved_at->toISOString() : null,
                    'fulfilled_at' => $request->fulfilled_at ? $request->fulfilled_at->toISOString() : null,
                    'pickup_scheduled_at' => (Schema::hasColumn('supply_requests', 'pickup_scheduled_at') && $request->pickup_scheduled_at) ? $request->pickup_scheduled_at->toISOString() : null,
                    'pickup_notified_at' => (Schema::hasColumn('supply_requests', 'pickup_notified_at') && $request->pickup_notified_at) ? $request->pickup_notified_at->toISOString() : null,
                    'created_at' => $request->created_at->toISOString(),
                    'created_at_formatted' => $request->created_at->format('M d, Y h:i A'),
                    'unread_messages_count' => $request->unreadMessagesCount(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedRequests,
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                    'from' => $requests->firstItem(),
                    'to' => $requests->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user supply requests: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supply requests: ' . (config('app.debug') ? $e->getMessage() : 'Please check server logs'),
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null
            ], 500);
        }
    }

    /**
     * Get user's supply requests (alias for getMyRequests)
     */
    public function getUserRequests(Request $request): JsonResponse
    {
        return $this->getMyRequests($request);
    }

    /**
     * Create a new supply request (alias for createRequest)
     */
    public function store(Request $request): JsonResponse
    {
        return $this->createRequest($request);
    }

    /**
     * Create a new supply request
     */
    public function createRequest(Request $request): JsonResponse
    {
        try {
            Log::info('Creating supply request', [
                'user_id' => $request->user()?->id,
                'request_data' => $request->all()
            ]);
            
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Support both single item (backward compatible) and multiple items
            $hasItems = $request->has('items') && is_array($request->items) && count($request->items) > 0;
            
            if ($hasItems) {
                // Multiple items request
                $validationRules = [
                    'items' => 'required|array|min:1',
                    'items.*.item_id' => 'required|string',
                    'items.*.quantity' => 'required|integer|min:1',
                    'urgency_level' => 'nullable|in:Low,Medium,High',
                    'notes' => 'nullable|string|max:1000',
                ];
                
                // Only validate target_supply_account_id if column exists
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    $validationRules['target_supply_account_id'] = 'nullable|exists:users,id';
                }
                
                $validated = $request->validate($validationRules);
                
                // Validate target_supply_account_id is a supply account if provided and column exists
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id') && isset($validated['target_supply_account_id'])) {
                    $targetSupplyAccount = User::find($validated['target_supply_account_id']);
                    if (!$targetSupplyAccount || strtolower($targetSupplyAccount->role ?? '') !== 'supply') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid target supply account. The selected user must be a supply account.'
                        ], 400);
                    }
                }
                
                // Validate all items exist and have enough stock
                $itemsData = [];
                foreach ($validated['items'] as $index => $itemData) {
                    $item = null;
                    $itemId = $itemData['item_id'];
                    
                    // Check if item_id is UUID or numeric ID
                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $itemId)) {
                        // Search by UUID (include soft-deleted to check if item was deleted)
                        $item = Item::withTrashed()->where('uuid', $itemId)->first();
                    } else {
                        // Search by numeric ID (include soft-deleted to check if item was deleted)
                        $item = Item::withTrashed()->where('id', $itemId)->first();
                    }
                    
                    if (!$item) {
                        Log::warning('Supply request item not found', [
                            'item_id' => $itemId,
                            'index' => $index,
                            'user_id' => $user->id
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => "Item at index {$index} (ID: {$itemId}) not found. The item may have been removed from the system."
                        ], 404);
                    }
                    
                    // Check if item is soft-deleted
                    if ($item->trashed()) {
                        $itemName = $item->unit ?? $item->description ?? 'Item';
                        Log::warning('Supply request attempted for deleted item', [
                            'item_id' => $itemId,
                            'item_name' => $itemName,
                            'index' => $index,
                            'user_id' => $user->id
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => "Item at index {$index} ({$itemName}) has been deleted and cannot be requested."
                        ], 400);
                    }
                    
                    // Check stock availability
                    if ($item->quantity < $itemData['quantity']) {
                        $itemName = $item->unit ?? $item->description ?? 'Item';
                        return response()->json([
                            'success' => false,
                            'message' => "Insufficient stock for {$itemName}. Available: {$item->quantity}, Requested: {$itemData['quantity']}"
                        ], 400);
                    }
                    
                    $itemsData[] = [
                        'item' => $item,
                        'item_id' => $itemData['item_id'],
                        'quantity' => $itemData['quantity'],
                    ];
                }
                
                // Create supply request without item_id and quantity (will be in pivot table)
                $requestData = [
                    'item_id' => $itemsData[0]['item_id'], // Keep first item for backward compatibility
                    'quantity' => array_sum(array_column($itemsData, 'quantity')), // Total quantity
                    'urgency_level' => $validated['urgency_level'] ?? 'Medium',
                    'notes' => $validated['notes'] ?? null,
                    'status' => 'pending',
                    'requested_by_user_id' => $user->id,
                ];
                
                // Only add target_supply_account_id if column exists (migration has been run)
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    $requestData['target_supply_account_id'] = $validated['target_supply_account_id'] ?? null;
                }
                
                $supplyRequest = SupplyRequest::create($requestData);
                
                // Create pivot table entries for all items (if table exists)
                if (Schema::hasTable('supply_request_items')) {
                    foreach ($itemsData as $itemData) {
                        try {
                            SupplyRequestItem::create([
                                'supply_request_id' => $supplyRequest->id,
                                'item_id' => $itemData['item_id'],
                                'quantity' => $itemData['quantity'],
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('Failed to create supply_request_item: ' . $e->getMessage());
                            // Continue - main request is already created
                        }
                    }
                } else {
                    Log::warning('supply_request_items table does not exist. Please run migration.');
                }
                
                $firstItem = $itemsData[0]['item'];
                $itemName = $firstItem->unit ?? $firstItem->description ?? 'N/A';
                $totalQuantity = array_sum(array_column($itemsData, 'quantity'));
                $itemCount = count($itemsData);
                
            } else {
                // Single item request (backward compatible)
                $validationRules = [
                    'item_id' => 'required|string',
                    'quantity' => 'required|integer|min:1',
                    'urgency_level' => 'nullable|in:Low,Medium,High',
                    'notes' => 'nullable|string|max:1000',
                ];
                
                // Only validate target_supply_account_id if column exists
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    $validationRules['target_supply_account_id'] = 'nullable|exists:users,id';
                }
                
                $validated = $request->validate($validationRules);
                
                // Validate target_supply_account_id is a supply account if provided and column exists
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id') && isset($validated['target_supply_account_id'])) {
                    $targetSupplyAccount = User::find($validated['target_supply_account_id']);
                    if (!$targetSupplyAccount || strtolower($targetSupplyAccount->role ?? '') !== 'supply') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid target supply account. The selected user must be a supply account.'
                        ], 400);
                    }
                }

                // Check if item exists and has enough stock
                $item = null;
                $itemId = $validated['item_id'];
                
                // Check if item_id is UUID or numeric ID
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $itemId)) {
                    // Search by UUID (include soft-deleted to check if item was deleted)
                    $item = Item::withTrashed()->where('uuid', $itemId)->first();
                } else {
                    // Search by numeric ID (include soft-deleted to check if item was deleted)
                    $item = Item::withTrashed()->where('id', $itemId)->first();
                }

                if (!$item) {
                    Log::warning('Supply request item not found', [
                        'item_id' => $itemId,
                        'user_id' => $user->id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => "Item (ID: {$itemId}) not found. The item may have been removed from the system."
                    ], 404);
                }
                
                // Check if item is soft-deleted
                if ($item->trashed()) {
                    $itemName = $item->unit ?? $item->description ?? 'Item';
                    Log::warning('Supply request attempted for deleted item', [
                        'item_id' => $itemId,
                        'item_name' => $itemName,
                        'user_id' => $user->id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => "Item ({$itemName}) has been deleted and cannot be requested."
                    ], 400);
                }

                if ($item->quantity < $validated['quantity']) {
                    $itemName = $item->unit ?? $item->description ?? 'Item';
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$itemName}. Available: {$item->quantity}, Requested: {$validated['quantity']}"
                    ], 400);
                }

                $requestData = [
                    'item_id' => $validated['item_id'],
                    'quantity' => $validated['quantity'],
                    'urgency_level' => $validated['urgency_level'] ?? 'Medium',
                    'notes' => $validated['notes'] ?? null,
                    'status' => 'pending',
                    'requested_by_user_id' => $user->id,
                ];
                
                // Only add target_supply_account_id if column exists (migration has been run)
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    $requestData['target_supply_account_id'] = $validated['target_supply_account_id'] ?? null;
                }
                
                $supplyRequest = SupplyRequest::create($requestData);
                
                // Create pivot table entry for single item (if table exists)
                if (Schema::hasTable('supply_request_items')) {
                    try {
                        SupplyRequestItem::create([
                            'supply_request_id' => $supplyRequest->id,
                            'item_id' => $validated['item_id'],
                            'quantity' => $validated['quantity'],
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to create supply_request_item: ' . $e->getMessage());
                        // Continue - main request is already created
                    }
                }
                
                $itemName = $item->unit ?? $item->description ?? 'N/A';
                $totalQuantity = $validated['quantity'];
                $itemCount = 1;
            }

            // Load relationships
            $supplyRequest->load(['requestedBy', 'approver']);

            // Create notification for Supply Account users when a new request is submitted
            try {
                $requestedByName = $user->fullname ?? $user->username ?? $user->email ?? 'User';
                
                if ($hasItems && $itemCount > 1) {
                    $notificationMessage = "New supply request from {$requestedByName}: {$itemCount} item(s) (Total Quantity: {$totalQuantity})";
                } else {
                    $notificationMessage = "New supply request from {$requestedByName}: {$itemName} (Quantity: {$totalQuantity})";
                }
                
                // Use first item's ID for notification (or get from supply request items)
                $firstItemId = $hasItems ? $itemsData[0]['item']->id : $item->id;
                
                $notification = Notification::create([
                    'item_id' => $firstItemId,
                    'message' => $notificationMessage,
                    'type' => 'supply_request_created',
                    'is_read' => false,
                ]);
                
                // Broadcast notification event for real-time updates
                if ($notification) {
                    $notification->refresh();
                    $notification->load('item');
                    event(new NotificationCreated($notification));
                    Log::info("Notification created for new supply request ID: {$supplyRequest->id}, Notification ID: {$notification->notification_id}");
                }
            } catch (\Exception $e) {
                Log::error('Error creating notification for new supply request: ' . $e->getMessage());
                // Continue even if notification creation fails - request already created
            }

            // Create message notification for Supply Account users when a new request is submitted
            try {
                $requestedByName = $user->fullname ?? $user->username ?? $user->email ?? 'User';
                $messageText = "New supply request submitted:\n\n";
                
                if ($hasItems && $itemCount > 1) {
                    // Multiple items
                    $messageText .= "Items ({$itemCount}):\n";
                    foreach ($itemsData as $itemData) {
                        $itemObj = $itemData['item'];
                        $itemName = $itemObj->unit ?? $itemObj->description ?? 'N/A';
                        $messageText .= "- {$itemName}: {$itemData['quantity']}\n";
                    }
                    $messageText .= "\nTotal Quantity: {$totalQuantity}\n";
                } else {
                    // Single item
                    $messageText .= "Item: {$itemName}\n";
                    $messageText .= "Quantity: {$totalQuantity}\n";
                }
                
                if (!empty($validated['notes'])) {
                    $messageText .= "Notes: {$validated['notes']}\n";
                }
                $messageText .= "\nRequested by: {$requestedByName}";
                
                $message = SupplyRequestMessage::create([
                    'supply_request_id' => $supplyRequest->id,
                    'user_id' => $user->id, // The user who sent the request
                    'message' => $messageText,
                    'is_read' => false,
                ]);
                
                if ($message) {
                    Log::info("Message created for new supply request ID: {$supplyRequest->id}, Message ID: {$message->id}");
                }
            } catch (\Exception $e) {
                Log::error('Error creating message for new supply request: ' . $e->getMessage());
                // Continue even if message creation fails - request already created
            }

            return response()->json([
                'success' => true,
                'message' => 'Supply request created successfully',
                'data' => $supplyRequest
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation error creating supply request: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error creating supply request: ' . $e->getMessage());
            Log::error('SQL: ' . $e->getSql());
            Log::error('Bindings: ' . json_encode($e->getBindings()));
            Log::error('Error Code: ' . $e->getCode());
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Please check server logs'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error creating supply request: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create supply request',
                'error' => config('app.debug') ? $e->getMessage() : 'Please check server logs',
                'file' => config('app.debug') ? $e->getFile() . ':' . $e->getLine() : null
            ], 500);
        }
    }

    /**
     * Cancel a supply request
     */
    public function cancelRequest(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);

            // Check if user owns this request
            if ($supplyRequest->requested_by_user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only cancel your own requests'
                ], 403);
            }

            // Check if request can be cancelled (only pending requests can be cancelled)
            if ($supplyRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot cancel request. Current status: {$supplyRequest->status}"
                ], 400);
            }

            // Prepare update data
            $updateData = [
                'status' => 'cancelled'
            ];

            // Only add cancelled_at if column exists
            if (Schema::hasColumn('supply_requests', 'cancelled_at')) {
                $updateData['cancelled_at'] = now();
            }

            // Only add cancellation_reason if column exists
            if (Schema::hasColumn('supply_requests', 'cancellation_reason')) {
                $updateData['cancellation_reason'] = $request->input('reason', 'Cancelled by user');
            }

            // Update status to cancelled
            try {
                $supplyRequest->update($updateData);
            } catch (\Illuminate\Database\QueryException $e) {
                // If update fails due to constraint, try to update constraint first
                if (str_contains($e->getMessage(), 'check_status') || str_contains($e->getMessage(), 'constraint')) {
                    Log::warning('Status constraint may not include "cancelled". Attempting to update constraint...');
                    
                    try {
                        // Try to update the constraint to include 'cancelled'
                        DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
                        DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled', 'cancelled'))");
                        
                        // Retry the update
                        $supplyRequest->update($updateData);
                    } catch (\Exception $constraintError) {
                        Log::error('Failed to update constraint: ' . $constraintError->getMessage());
                        // If constraint update fails, try direct DB update without constraint check
                        DB::table('supply_requests')
                            ->where('id', $supplyRequest->id)
                            ->update($updateData);
                        $supplyRequest->refresh();
                    }
                } else {
                    // Re-throw if it's a different error
                    throw $e;
                }
            }

            // Refresh the model to get updated data
            $supplyRequest->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Supply request cancelled successfully',
                'data' => $supplyRequest
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supply request not found'
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error cancelling supply request: ' . $e->getMessage());
            Log::error('SQL: ' . $e->getSql());
            Log::error('Bindings: ' . json_encode($e->getBindings()));
            return response()->json([
                'success' => false,
                'message' => 'Database error while cancelling request. Please check server logs.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error cancelling supply request: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel supply request',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Approve a supply request (Supply Account or Admin)
     */
    public function approveRequest(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');
            
            // Check if user has permission
            if (!in_array($role, ['supply', 'admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account and Admin can approve requests'
                ], 403);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);
            
            // For supply accounts, check if they are the target supply account
            if ($role === 'supply') {
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    if ($supplyRequest->target_supply_account_id !== $user->id && $supplyRequest->target_supply_account_id !== null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You can only approve requests submitted to your supply account'
                        ], 403);
                    }
                }
            }
            
            $item = $supplyRequest->item();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found for this request'
                ], 404);
            }

            // Check current status
            $currentStatus = $supplyRequest->status;
            $allowedStatuses = ['pending', 'supply_approved', 'admin_assigned'];
            
            if (!in_array($currentStatus, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot approve request with status: {$currentStatus}"
                ], 400);
            }

            // If admin is trying to approve, check if they are the assigned admin
            if (in_array($role, ['admin', 'super_admin'])) {
                // Require that the request must be assigned to an admin before approval
                if (!$supplyRequest->assigned_to_admin_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This request must be assigned to an admin by Supply Account before it can be approved'
                    ], 403);
                }
                
                // Only the assigned admin can approve
                if ($supplyRequest->assigned_to_admin_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Only the assigned admin can approve this request'
                    ], 403);
                }
                
                // If status is admin_assigned, there must be an assigned admin
                if ($currentStatus === 'admin_assigned' && !$supplyRequest->assigned_to_admin_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Request is assigned but no assigned admin found'
                    ], 400);
                }
            }

            // Multi-item: resolve non-rejected items. If all rejected, cannot approve.
            $itemsToProcess = [];
            if (Schema::hasTable('supply_request_items')) {
                $supplyRequest->load('items');
            }
            $hasMultiItem = $supplyRequest->relationLoaded('items') && $supplyRequest->items->count() > 0;
            if ($hasMultiItem) {
                foreach ($supplyRequest->items as $ri) {
                    if (!$ri->isRejected()) {
                        $itemsToProcess[] = $ri;
                    }
                }
                if (count($itemsToProcess) === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'All items have been rejected. Reject the entire request instead.'
                    ], 400);
                }
            }

            $currentQuantity = $item->quantity ?? 0;
            if ($hasMultiItem) {
                foreach ($itemsToProcess as $ri) {
                    $it = $ri->item();
                    if (!$it) {
                        return response()->json([
                            'success' => false,
                            'message' => "Item not found for line item ID: {$ri->id}"
                        ], 404);
                    }
                    $avail = (int) ($it->quantity ?? 0);
                    if ($ri->quantity > $avail) {
                        $name = $it->unit ?? $it->description ?? 'N/A';
                        return response()->json([
                            'success' => false,
                            'message' => "Insufficient stock for {$name}. Available: {$avail}, Requested: {$ri->quantity}"
                        ], 400);
                    }
                }
            } else {
                if ($supplyRequest->quantity > $currentQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock. Available: {$currentQuantity}, Requested: {$supplyRequest->quantity}"
                    ], 400);
                }
            }

            // Ensure requestedBy relationship is loaded with location BEFORE transaction
            if (!$supplyRequest->relationLoaded('requestedBy')) {
                $supplyRequest->load(['requestedBy.location']);
            } else {
                // If requestedBy is loaded but location isn't, load it
                if ($supplyRequest->requestedBy && !$supplyRequest->requestedBy->relationLoaded('location')) {
                    $supplyRequest->requestedBy->load('location');
                }
            }
            
            DB::beginTransaction();
            try {
                // Capture Supply account user ID before admin overwrites it (for notification)
                $supplyAccountUserId = null;
                if (in_array($role, ['admin', 'super_admin']) && 
                    in_array($currentStatus, ['supply_approved', 'admin_assigned'])) {
                    // If admin is approving a request that was previously approved by Supply,
                    // capture the Supply account user ID before overwriting
                    $supplyAccountUserId = $supplyRequest->approved_by;
                }
                
                // If Supply Account approves, set status to 'supply_approved' (not 'approved')
                // This will then go to Admin for final approval
                if ($role === 'supply') {
                    $supplyRequest->status = 'supply_approved';
                    $supplyRequest->approved_by = $user->id; // Supply account who approved
                    $supplyRequest->approved_at = now();
                    // No stock deduction at this stage
                } else {
                    // Admin approves supply_approved or pending requests
                    // After admin approval, request goes back to supply account for pickup scheduling
                    // NOTE: Quantity will be deducted when request is fulfilled, not at approval
                    // Stock already validated above (single or multi-item)
                    $supplyRequest->status = 'ready_for_pickup';
                    $supplyRequest->approved_by = $user->id;
                    $supplyRequest->approved_at = now();
                    
                    // Automatically generate receipt PDF (admin only)
                    try {
                        // Delete old proof if exists
                        if ($supplyRequest->approval_proof) {
                            Storage::disk('public')->delete($supplyRequest->approval_proof);
                        }
                        
                        // Generate receipt PDF
                        $receiptPath = $this->generateApprovalReceipt($supplyRequest, $user);
                        $supplyRequest->approval_proof = $receiptPath;
                        Log::info("Approval receipt generated: {$receiptPath} for supply request ID: {$supplyRequest->id}");
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Error generating approval receipt: ' . $e->getMessage());
                        Log::error('Stack trace: ' . $e->getTraceAsString());
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to generate approval receipt: ' . $e->getMessage()
                        ], 500);
                    }
                }
                
                // Save supply request after all updates
                $supplyRequest->save();

                DB::commit();
                
                // Create notification and message AFTER transaction commits successfully
                // This prevents transaction rollback if notification/message creation fails
                if (in_array($role, ['admin', 'super_admin']) && $supplyRequest->status === 'ready_for_pickup') {
                    // Reload supply request to ensure we have fresh data
                    $supplyRequest->refresh();
                    $supplyRequest->load(['requestedBy', 'approver', 'items']);
                    
                    // Get all items for this request (multi-item support) — only non-rejected
                    $requestItems = [];
                    if (Schema::hasTable('supply_request_items') && $supplyRequest->relationLoaded('items') && $supplyRequest->items->count() > 0) {
                        foreach ($supplyRequest->items as $requestItem) {
                            if ($requestItem->isRejected()) {
                                continue;
                            }
                            $itemObj = $requestItem->item();
                            if ($itemObj) {
                                $requestItems[] = [
                                    'item' => $itemObj,
                                    'item_name' => $itemObj->unit ?? $itemObj->description ?? 'N/A',
                                    'quantity' => $requestItem->quantity,
                                ];
                            }
                        }
                    } else {
                        $freshItem = $supplyRequest->item();
                        if ($freshItem) {
                            $requestItems[] = [
                                'item' => $freshItem,
                                'item_name' => $freshItem->unit ?? $freshItem->description ?? 'N/A',
                                'quantity' => $supplyRequest->quantity,
                            ];
                        }
                    }
                    
                    // Get first item for notification (backward compatibility)
                    $firstItem = $requestItems[0]['item'] ?? null;
                    $itemNameForNotification = $requestItems[0]['item_name'] ?? 'N/A';
                    $itemIdForNotification = $firstItem ? $firstItem->id : null;
                    
                    // Get approver name
                    $approverNameForNotification = $user->fullname ?? $user->username ?? $user->email ?? 'Admin';
                    
                    // Get requesting user ID for message
                    $requestingUserId = $supplyRequest->requested_by_user_id;
                    
                    Log::info("Creating message for approved supply request ID: {$supplyRequest->id}, Requesting User ID: {$requestingUserId}, Admin User ID: {$user->id}, Items Count: " . count($requestItems));
                    
                    // Create notification for the user who requested
                    if ($itemIdForNotification) {
                        try {
                            // Build notification message based on item count
                            // IMPORTANT: This is just an approval notification, NOT a "ready for pickup" notification
                            // The "ready for pickup" notification will be sent later when supply account schedules pickup time
                            if (count($requestItems) > 1) {
                                $itemNames = array_map(function($item) {
                                    return $item['item_name'] . ' (' . $item['quantity'] . ')';
                                }, $requestItems);
                                $notificationMessage = "Your supply request for " . count($requestItems) . " item(s): " . implode(', ', $itemNames) . " has been approved by {$approverNameForNotification}. The supply account will notify you when your items are ready for pickup.";
                            } else {
                                $notificationMessage = "Your supply request for {$itemNameForNotification} (Quantity: {$supplyRequest->quantity}) has been approved by {$approverNameForNotification}. The supply account will notify you when your items are ready for pickup.";
                            }
                            
                            $notification = Notification::create([
                                'item_id' => $itemIdForNotification,
                                'user_id' => $requestingUserId, // Only for the requesting user
                                'message' => $notificationMessage,
                                'type' => 'supply_request_approved',
                                'is_read' => false,
                            ]);
                            
                            // Broadcast notification event for real-time updates
                            if ($notification) {
                                $notification->refresh();
                                $notification->load('item');
                                event(new NotificationCreated($notification));
                                Log::info("Notification created and broadcasted for approved supply request ID: {$supplyRequest->id}");
                            }
                        } catch (\Exception $e) {
                            Log::error('Error creating notification for approved supply request: ' . $e->getMessage());
                            Log::error('Stack trace: ' . $e->getTraceAsString());
                            // Continue even if notification fails - transaction already committed
                        }
                    }
                    
                    // Create a message in supply request messages
                    // IMPORTANT: user_id should be the admin who sent the message, so the requesting user can see it
                    if ($requestingUserId) {
                        try {
                            // Build approval message banner based on item count
                            // IMPORTANT: This message should NOT say "ready for pickup" - that notification comes later when supply schedules pickup
                            if (count($requestItems) > 1) {
                                $itemNames = array_map(function($item) {
                                    return $item['item_name'] . ' (Quantity: ' . $item['quantity'] . ')';
                                }, $requestItems);
                                $approvalMessage = "Your supply request has been approved. " . implode(', ', $itemNames) . " will be processed.\n\n";
                                $approvalMessage .= "⏳ The supply account will prepare your items and notify you when they are ready for pickup. Please wait for their notification.";
                            } else {
                                $approvalMessage = "Your supply request has been approved. {$itemNameForNotification} (Quantity: {$supplyRequest->quantity}) will be processed.\n\n";
                                $approvalMessage .= "⏳ The supply account will prepare your items and notify you when they are ready for pickup. Please wait for their notification.";
                            }
                            
                            // Add receipt details if available
                            if ($supplyRequest->approval_proof) {
                                $receiptUrl = Storage::url($supplyRequest->approval_proof);
                                $approverName = $user->fullname ?? $user->username ?? 'Admin';
                                $approvalDate = $supplyRequest->approved_at ? $supplyRequest->approved_at->format('F d, Y h:i A') : now()->format('F d, Y h:i A');
                                
                                // Get QR code path if exists
                                $qrCodePath = null;
                                $qrCodeFiles = Storage::disk('public')->files('approval-proofs/qrcodes');
                                foreach ($qrCodeFiles as $file) {
                                    if (strpos($file, $supplyRequest->request_number) !== false) {
                                        $qrCodePath = Storage::url($file);
                                        break;
                                    }
                                }
                                
                                $approvalMessage .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
                                $approvalMessage .= "\n                    APPROVAL RECEIPT";
                                $approvalMessage .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
                                $approvalMessage .= "\n";
                                $approvalMessage .= "\n  Receipt Number    : {$supplyRequest->request_number}";
                                $approvalMessage .= "\n  Approval Date     : {$approvalDate}";
                                $approvalMessage .= "\n  Approved By       : {$approverName}";
                                $approvalMessage .= "\n  Approver Role     : " . ucfirst($user->role ?? 'Admin');
                                $approvalMessage .= "\n";
                                $approvalMessage .= "\n  ──────────────────────────────────────────────────────";
                                $approvalMessage .= "\n  Item Details:";
                                $approvalMessage .= "\n  ──────────────────────────────────────────────────────";
                                
                                // Display all items
                                if (count($requestItems) > 1) {
                                    foreach ($requestItems as $idx => $itemData) {
                                        $approvalMessage .= "\n  Item " . ($idx + 1) . ":";
                                        $approvalMessage .= "\n    Item Name         : {$itemData['item_name']}";
                                        $approvalMessage .= "\n    Quantity          : {$itemData['quantity']}";
                                        if ($idx < count($requestItems) - 1) {
                                            $approvalMessage .= "\n";
                                        }
                                    }
                                    $approvalMessage .= "\n  Total Quantity      : {$supplyRequest->quantity}";
                                } else {
                                    $approvalMessage .= "\n  Item Name         : {$itemNameForNotification}";
                                    $approvalMessage .= "\n  Quantity          : {$supplyRequest->quantity}";
                                }
                                
                                $approvalMessage .= "\n";
                                $approvalMessage .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
                                $approvalMessage .= "\n";
                                $approvalMessage .= "\nPlease present this receipt information or scan the QR code when picking up your items.";
                                $approvalMessage .= "\nFull PDF receipt available for download (optional).";
                                if ($qrCodePath) {
                                    $approvalMessage .= "\nQR Code: " . url($qrCodePath);
                                }
                                $approvalMessage .= "\n";
                                $approvalMessage .= "\nReceipt Link: " . url($receiptUrl);
                            }
                            
                            $message = SupplyRequestMessage::create([
                                'supply_request_id' => $supplyRequest->id,
                                'user_id' => $user->id, // Admin who approved (sender)
                                'message' => $approvalMessage,
                                'is_read' => false,
                            ]);
                            
                            if ($message) {
                                Log::info("Message created successfully for approved supply request ID: {$supplyRequest->id}, Message ID: {$message->id}, Admin User ID: {$user->id}, Requesting User ID: {$requestingUserId}");
                                
                                // Verify the message was created correctly
                                $verifyMessage = SupplyRequestMessage::find($message->id);
                                if ($verifyMessage) {
                                    Log::info("Message verified: Supply Request ID: {$verifyMessage->supply_request_id}, User ID: {$verifyMessage->user_id}, Message: {$verifyMessage->message}");
                                } else {
                                    Log::error("Message verification failed: Message ID {$message->id} not found after creation");
                                }
                                
                                // Broadcast event to trigger banner notification for the user
                                event(new SupplyRequestApproved($supplyRequest, $approvalMessage));
                                Log::info("SupplyRequestApproved event broadcasted for request ID: {$supplyRequest->id}");
                            } else {
                                Log::error("Message creation returned null for supply request ID: {$supplyRequest->id}");
                            }
                        } catch (\Exception $e) {
                            Log::error('Error creating message/broadcasting approval event: ' . $e->getMessage());
                            Log::error('Stack trace: ' . $e->getTraceAsString());
                            Log::error('Supply Request ID: ' . $supplyRequest->id);
                            Log::error('Admin User ID: ' . $user->id);
                            Log::error('Requesting User ID: ' . ($requestingUserId ?? 'null'));
                            // Continue even if message creation fails - transaction already committed
                        }
                    } else {
                        Log::error("Cannot create message: requesting_user_id is null for supply request ID: {$supplyRequest->id}");
                    }
                }
                
                // Notify Supply account user that request is ready for pickup scheduling
                if (in_array($role, ['admin', 'super_admin']) && 
                    $supplyRequest->status === 'ready_for_pickup') {
                    // Get the supply account user ID (either from target_supply_account_id or approved_by)
                    $supplyAccountUserId = $supplyRequest->target_supply_account_id ?? $supplyAccountUserId ?? $supplyRequest->approved_by;
                    
                    if ($supplyAccountUserId && $supplyAccountUserId !== $user->id) {
                        try {
                            $adminName = $user->fullname ?? $user->username ?? $user->email ?? 'Admin';
                            $item = $supplyRequest->item();
                            $itemName = $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A';
                            $itemId = $item ? $item->id : null;
                            
                            // Create notification for Supply account user
                            if ($itemId) {
                                try {
                                    $supplyNotificationMessage = "The supply request for {$itemName} (Quantity: {$supplyRequest->quantity}) that you approved has been approved by {$adminName}";
                                    
                                    $supplyNotification = Notification::create([
                                        'item_id' => $itemId,
                                        'user_id' => $supplyAccountUserId, // Only for the specific supply account user
                                        'message' => $supplyNotificationMessage,
                                        'type' => 'supply_request_admin_approved',
                                        'is_read' => false,
                                    ]);
                                    
                                    // Broadcast notification event for real-time updates
                                    if ($supplyNotification) {
                                        $supplyNotification->refresh();
                                        $supplyNotification->load('item');
                                        event(new NotificationCreated($supplyNotification));
                                        Log::info("Supply account notification created and broadcasted for approved supply request ID: {$supplyRequest->id}, Supply User ID: {$supplyAccountUserId}");
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Error creating notification for Supply account: ' . $e->getMessage());
                                }
                            }
                            
                            // Create message for Supply account user
                            $supplyMessage = "The supply request for {$itemName} (Quantity: {$supplyRequest->quantity}) has been approved by {$adminName}.\n\n";
                            $supplyMessage .= "Request Status: Ready for Pickup Scheduling\n";
                            $supplyMessage .= "Approved By: {$adminName} (" . ucfirst($user->role ?? 'Admin') . ")\n\n";
                            $supplyMessage .= "Please set a pickup time and notify the user when the items are ready for pickup.";
                            
                            $supplyMsg = SupplyRequestMessage::create([
                                'supply_request_id' => $supplyRequest->id,
                                'user_id' => $user->id, // Admin who approved (sender)
                                'message' => $supplyMessage,
                                'is_read' => false,
                            ]);
                            
                            if ($supplyMsg) {
                                Log::info("Supply account notification message created for request ID: {$supplyRequest->id}, Supply User ID: {$supplyAccountUserId}");
                            }
                        } catch (\Exception $e) {
                            Log::error('Error creating Supply account notification/message: ' . $e->getMessage());
                            Log::error('Stack trace: ' . $e->getTraceAsString());
                            // Continue even if notification/message creation fails
                        }
                    }
                }
                
                // Also notify supply account if they are the target supply account
                if (in_array($role, ['admin', 'super_admin']) && 
                    $supplyRequest->status === 'ready_for_pickup' &&
                    $supplyRequest->target_supply_account_id) {
                    $targetSupplyAccountId = $supplyRequest->target_supply_account_id;
                    if ($targetSupplyAccountId !== $user->id) {
                        try {
                            $adminName = $user->fullname ?? $user->username ?? $user->email ?? 'Admin';
                            $item = $supplyRequest->item();
                            $itemName = $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A';
                            $itemId = $item ? $item->id : null;
                            
                            if ($itemId) {
                                $supplyNotificationMessage = "A supply request for {$itemName} (Quantity: {$supplyRequest->quantity}) has been approved by {$adminName} and is ready for pickup scheduling. Please set a pickup time and notify the user.";
                                
                                $supplyNotification = Notification::create([
                                    'item_id' => $itemId,
                                    'user_id' => $targetSupplyAccountId, // Only for the specific supply account user
                                    'message' => $supplyNotificationMessage,
                                    'type' => 'supply_request_ready_for_pickup',
                                    'is_read' => false,
                                ]);
                                
                                if ($supplyNotification) {
                                    $supplyNotification->refresh();
                                    $supplyNotification->load('item');
                                    event(new NotificationCreated($supplyNotification));
                                    Log::info("Supply account notification created for ready_for_pickup request ID: {$supplyRequest->id}, Supply User ID: {$targetSupplyAccountId}");
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error('Error creating notification for Supply account: ' . $e->getMessage());
                        }
                    }
                }
                
                // Load relationships (don't eager load 'item')
                $supplyRequest->load(['requestedBy', 'approver']);

                return response()->json([
                    'success' => true,
                    'message' => $role === 'supply' 
                        ? 'Supply request approved. It will now be forwarded to Admin for assignment.'
                        : 'Supply request approved successfully',
                    'data' => $supplyRequest
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error in approveRequest transaction: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
                throw $e;
            }

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error approving supply request: ' . $e->getMessage());
            Log::error('SQL: ' . $e->getSql());
            Log::error('Bindings: ' . json_encode($e->getBindings()));
            
            // Check if transaction is still open and rollback if needed
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error approving supply request: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Check if transaction is still open and rollback if needed
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve supply request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a supply request (Supply role)
     */
    public function rejectRequest(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');
            
            // Check if user has permission
            if (!in_array($role, ['supply', 'admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account and Admin can reject requests'
                ], 403);
            }

                $supplyRequest = SupplyRequest::findOrFail($id);
            
            // For supply accounts, check if they are the target supply account
            if ($role === 'supply') {
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    if ($supplyRequest->target_supply_account_id !== $user->id && $supplyRequest->target_supply_account_id !== null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You can only reject requests submitted to your supply account'
                        ], 403);
                    }
                }
            }

            // Check current status - allow rejection of pending, supply_approved, or admin_assigned requests
            $currentStatus = $supplyRequest->status;
            $allowedStatuses = ['pending', 'supply_approved', 'admin_assigned'];
            
            if (!in_array($currentStatus, $allowedStatuses)) {
                    return response()->json([
                        'success' => false,
                    'message' => "Cannot reject request with status: {$currentStatus}"
                    ], 400);
                }

            // If admin is trying to reject, check if they are the assigned admin
            if (in_array($role, ['admin', 'super_admin'])) {
                // Require that the request must be assigned to an admin before rejection
                if (!$supplyRequest->assigned_to_admin_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This request must be assigned to an admin by Supply Account before it can be rejected'
                    ], 403);
                }
                
                // Only the assigned admin can reject
                if ($supplyRequest->assigned_to_admin_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Only the assigned admin can reject this request'
                    ], 403);
                }
                
                // If status is admin_assigned, there must be an assigned admin
                if ($currentStatus === 'admin_assigned' && !$supplyRequest->assigned_to_admin_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Request is assigned but no assigned admin found'
                    ], 400);
                }
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            // Capture Supply account user ID before admin overwrites it (for notification)
            $supplyAccountUserId = null;
            $currentStatus = $supplyRequest->status;
            if (in_array($role, ['admin', 'super_admin']) && 
                in_array($currentStatus, ['supply_approved', 'admin_assigned'])) {
                // If admin is rejecting a request that was previously approved by Supply,
                // capture the Supply account user ID before overwriting
                $supplyAccountUserId = $supplyRequest->approved_by;
            }

            DB::beginTransaction();
            try {
                $supplyRequest->status = 'rejected';
                $supplyRequest->rejection_reason = $validated['rejection_reason'];
                $supplyRequest->rejected_at = now();
                $supplyRequest->approved_by = $user->id; // Store who rejected it
                $supplyRequest->save();

                // Create transaction record for admin rejection
                if (in_array($role, ['admin', 'super_admin'])) {
                    // Ensure requestedBy relationship is loaded with location
                    if (!$supplyRequest->relationLoaded('requestedBy')) {
                        $supplyRequest->load(['requestedBy.location']);
                    } else {
                        if ($supplyRequest->requestedBy && !$supplyRequest->requestedBy->relationLoaded('location')) {
                            $supplyRequest->requestedBy->load('location');
                        }
                    }
                    
                    $requestedByUser = $supplyRequest->requestedBy;
                    $requestedByName = $requestedByUser ? ($requestedByUser->fullname ?? $requestedByUser->username ?? $requestedByUser->email ?? 'N/A') : 'N/A';
                    $rejecterName = $user->fullname ?? $user->username ?? $user->email ?? 'N/A';
                    $item = $supplyRequest->item();
                    $itemName = $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A';
                    
                    // Get user location
                    $userLocation = 'N/A';
                    if ($requestedByUser && $requestedByUser->location_id) {
                        try {
                            // Load location relationship if not loaded
                            if (!$requestedByUser->relationLoaded('location')) {
                                $requestedByUser->load('location');
                            }
                            
                            if ($requestedByUser->location) {
                                // Location model uses 'location' field, not 'name'
                                $userLocation = $requestedByUser->location->location ?? 'N/A';
                            } else {
                                // Fallback: fetch directly
                                $location = Location::find($requestedByUser->location_id);
                                if ($location) {
                                    $userLocation = $location->location ?? 'N/A';
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error('Error fetching location for transaction: ' . $e->getMessage());
                        }
                    }

                Transaction::create([
                        'approved_by' => $rejecterName,
                    'borrower_name' => $requestedByName,
                    'requested_by' => $requestedByName,
                        'location' => $userLocation,
                        'item_name' => $itemName,
                    'quantity' => $supplyRequest->quantity,
                    'transaction_time' => now(),
                        'role' => 'ADMIN',
                        'status' => 'Rejected',
                ]);
                }

                DB::commit();

                // Load relationships
                $supplyRequest->load(['requestedBy', 'approver']);

                // Send message and notification to the requesting user with rejection reason
                // This prevents transaction rollback if message/notification creation fails
                $requestingUserId = $supplyRequest->requested_by_user_id;
                if ($requestingUserId) {
                    try {
                        $rejecterName = $user->fullname ?? $user->username ?? $user->email ?? 'Admin';
                        $item = $supplyRequest->item();
                        $itemName = $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A';
                        $itemId = $item ? $item->id : null;
                        $rejectionReason = $validated['rejection_reason'];
                        
                        // Create notification for the user who requested
                        if ($itemId) {
                            try {
                                $notificationMessage = "Your supply request for {$itemName} (Quantity: {$supplyRequest->quantity}) has been rejected by {$rejecterName}";
                                
                                $notification = Notification::create([
                                    'item_id' => $itemId,
                                    'user_id' => $requestingUserId, // Only for the requesting user
                                    'message' => $notificationMessage,
                                    'type' => 'supply_request_rejected',
                                    'is_read' => false,
                                ]);
                                
                                // Broadcast notification event for real-time updates
                                if ($notification) {
                                    $notification->refresh();
                                    $notification->load('item');
                                    event(new NotificationCreated($notification));
                                    Log::info("Rejection notification created and broadcasted for supply request ID: {$supplyRequest->id}");
                                }
                            } catch (\Exception $e) {
                                Log::error('Error creating rejection notification: ' . $e->getMessage());
                                // Continue even if notification fails
                            }
                        }
                        
                        // Create message in supply request messages
                        $rejectionMessage = "Your supply request for {$itemName} (Quantity: {$supplyRequest->quantity}) has been rejected.\n\n";
                        $rejectionMessage .= "Rejection Reason:\n{$rejectionReason}\n\n";
                        $rejectionMessage .= "Rejected By: {$rejecterName}";
                        if ($user->role) {
                            $rejectionMessage .= " (" . ucfirst($user->role) . ")";
                        }
                        
                        $message = SupplyRequestMessage::create([
                            'supply_request_id' => $supplyRequest->id,
                            'user_id' => $user->id, // Admin/Supply who rejected (sender)
                            'message' => $rejectionMessage,
                            'is_read' => false,
                        ]);
                        
                        if ($message) {
                            Log::info("Rejection message created successfully for supply request ID: {$supplyRequest->id}, Message ID: {$message->id}, Rejecter User ID: {$user->id}, Requesting User ID: {$requestingUserId}");
                        } else {
                            Log::error("Rejection message creation returned null for supply request ID: {$supplyRequest->id}");
                        }
                    } catch (\Exception $e) {
                        Log::error('Error creating rejection message/notification: ' . $e->getMessage());
                        Log::error('Stack trace: ' . $e->getTraceAsString());
                        // Continue even if message/notification creation fails - transaction already committed
                    }
                } else {
                    Log::warning("Cannot create rejection message: requesting_user_id is null for supply request ID: {$supplyRequest->id}");
                }
                
                // Notify Supply account user if admin rejected a request that was previously approved by Supply
                if (in_array($role, ['admin', 'super_admin']) && 
                    $supplyAccountUserId && 
                    $supplyAccountUserId !== $user->id) {
                    try {
                        $adminName = $user->fullname ?? $user->username ?? $user->email ?? 'Admin';
                        $item = $supplyRequest->item();
                        $itemName = $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A';
                        $itemId = $item ? $item->id : null;
                        $rejectionReason = $validated['rejection_reason'];
                        
                        // Create notification for Supply account user
                        if ($itemId) {
                            try {
                                $supplyNotificationMessage = "The supply request for {$itemName} (Quantity: {$supplyRequest->quantity}) that you approved has been rejected by {$adminName}";
                                
                                $supplyNotification = Notification::create([
                                    'item_id' => $itemId,
                                    'user_id' => $supplyAccountUserId, // Only for the specific supply account user
                                    'message' => $supplyNotificationMessage,
                                    'type' => 'supply_request_admin_rejected',
                                    'is_read' => false,
                                ]);
                                
                                // Broadcast notification event for real-time updates
                                if ($supplyNotification) {
                                    $supplyNotification->refresh();
                                    $supplyNotification->load('item');
                                    event(new NotificationCreated($supplyNotification));
                                    Log::info("Supply account notification created and broadcasted for rejected supply request ID: {$supplyRequest->id}, Supply User ID: {$supplyAccountUserId}");
                                }
                            } catch (\Exception $e) {
                                Log::error('Error creating rejection notification for Supply account: ' . $e->getMessage());
                            }
                        }
                        
                        // Create message for Supply account user
                        $supplyMessage = "The supply request for {$itemName} (Quantity: {$supplyRequest->quantity}) that you approved has been rejected by {$adminName}.\n\n";
                        $supplyMessage .= "Rejection Reason:\n{$rejectionReason}\n\n";
                        $supplyMessage .= "Rejected By: {$adminName} (" . ucfirst($user->role ?? 'Admin') . ")";
                        
                        $supplyMsg = SupplyRequestMessage::create([
                            'supply_request_id' => $supplyRequest->id,
                            'user_id' => $user->id, // Admin who rejected (sender)
                            'message' => $supplyMessage,
                            'is_read' => false,
                        ]);
                        
                        if ($supplyMsg) {
                            Log::info("Supply account rejection message created successfully for supply request ID: {$supplyRequest->id}, Message ID: {$supplyMsg->id}, Admin User ID: {$user->id}, Supply User ID: {$supplyAccountUserId}");
                        }
                    } catch (\Exception $e) {
                        Log::error('Error creating Supply account rejection notification/message: ' . $e->getMessage());
                        Log::error('Stack trace: ' . $e->getTraceAsString());
                        // Continue even if notification/message creation fails
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Supply request rejected successfully',
                    'data' => $supplyRequest
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error rejecting supply request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject supply request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a specific line item in a multi-item supply request (e.g. defective).
     * Remaining non-rejected items can still be approved and processed.
     */
    public function rejectItem(Request $request, $id, $itemId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $role = strtolower($user->role ?? '');
            if (!in_array($role, ['supply', 'admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account and Admin can reject items'
                ], 403);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);

            if ($role === 'supply' && Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                if ($supplyRequest->target_supply_account_id !== $user->id && $supplyRequest->target_supply_account_id !== null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You can only reject items in requests submitted to your supply account'
                    ], 403);
                }
            }

            if (in_array($role, ['admin', 'super_admin'])) {
                if (!$supplyRequest->assigned_to_admin_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Request must be assigned to an admin before rejecting items'
                    ], 403);
                }
                if ($supplyRequest->assigned_to_admin_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Only the assigned admin can reject items'
                    ], 403);
                }
            }

            if (!in_array($supplyRequest->status, ['pending', 'supply_approved', 'admin_assigned'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot reject items for request with status: {$supplyRequest->status}"
                ], 400);
            }

            if (!Schema::hasTable('supply_request_items')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Multi-item requests are not supported'
                ], 400);
            }

            $lineItem = SupplyRequestItem::where('supply_request_id', $id)->where('id', $itemId)->first();
            if (!$lineItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Line item not found'
                ], 404);
            }

            if ($lineItem->isRejected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This item is already rejected'
                ], 400);
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);

            $lineItem->status = SupplyRequestItem::STATUS_REJECTED;
            $lineItem->rejection_reason = $validated['rejection_reason'];
            $lineItem->rejected_at = now();
            $lineItem->rejected_by = $user->id;
            $lineItem->save();

            $itemObj = $lineItem->item();
            $itemName = $itemObj ? ($itemObj->unit ?? $itemObj->description ?? 'N/A') : 'N/A';
            $itemId = $itemObj ? $itemObj->id : null;

            // Load supply request with requestedBy relationship for notification
            $supplyRequest->load(['requestedBy']);

            // Create notification and message for the requester about the rejected item
            $requestingUserId = $supplyRequest->requested_by_user_id;
            if ($requestingUserId && $itemId) {
                try {
                    $rejecterName = $user->fullname ?? $user->username ?? $user->email ?? 'Supply Account';
                    $rejectionReason = $validated['rejection_reason'];
                    
                    // Create notification message with rejection reason
                    $notificationMessage = "Your supply request item \"{$itemName}\" (Quantity: {$lineItem->quantity}) has been rejected by {$rejecterName}.\n\n";
                    $notificationMessage .= "Rejection Reason: {$rejectionReason}\n\n";
                    $notificationMessage .= "Note: Other items in this request can still be processed.";
                    
                    $notification = Notification::create([
                        'item_id' => $itemId,
                        'user_id' => $requestingUserId,
                        'message' => $notificationMessage,
                        'type' => 'supply_request_rejected',
                        'is_read' => false,
                    ]);
                    
                    // Broadcast notification event for real-time updates
                    if ($notification) {
                        $notification->refresh();
                        $notification->load('item');
                        event(new NotificationCreated($notification));
                        Log::info("Item rejection notification created and broadcasted for supply request ID: {$supplyRequest->id}, Item: {$itemName}");
                    }
                    
                    // Create message in supply request messages thread
                    $rejectionMessage = "Item \"{$itemName}\" (Quantity: {$lineItem->quantity}) has been rejected.\n\n";
                    $rejectionMessage .= "Rejection Reason:\n{$rejectionReason}\n\n";
                    $rejectionMessage .= "Rejected By: {$rejecterName}";
                    if ($user->role) {
                        $rejectionMessage .= " (" . ucfirst($user->role) . ")";
                    }
                    $rejectionMessage .= "\n\nNote: Other items in this request can still be processed.";
                    
                    $message = SupplyRequestMessage::create([
                        'supply_request_id' => $supplyRequest->id,
                        'user_id' => $user->id, // Supply/Admin who rejected (sender)
                        'message' => $rejectionMessage,
                        'is_read' => false,
                    ]);
                    
                    if ($message) {
                        Log::info("Item rejection message created for supply request ID: {$supplyRequest->id}, Item: {$itemName}, Message ID: {$message->id}");
                    }
                } catch (\Exception $e) {
                    Log::error('Error creating item rejection notification/message: ' . $e->getMessage());
                    Log::error('Stack trace: ' . $e->getTraceAsString());
                    // Continue even if notification/message fails - item rejection is already saved
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Item \"{$itemName}\" rejected. Remaining items can still be processed.",
                'data' => [
                    'id' => $lineItem->id,
                    'item_id' => $lineItem->item_id,
                    'status' => $lineItem->status,
                    'rejection_reason' => $lineItem->rejection_reason,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error rejecting supply request item: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Undo rejection of a specific line item (restore to pending).
     */
    public function unrejectItem(Request $request, $id, $itemId): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $role = strtolower($user->role ?? '');
            if (!in_array($role, ['supply', 'admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account and Admin can unreject items'
                ], 403);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);

            if ($role === 'supply' && Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                if ($supplyRequest->target_supply_account_id !== $user->id && $supplyRequest->target_supply_account_id !== null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You can only unreject items in requests submitted to your supply account'
                    ], 403);
                }
            }

            if (in_array($role, ['admin', 'super_admin'])) {
                if ($supplyRequest->assigned_to_admin_id && $supplyRequest->assigned_to_admin_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Only the assigned admin can unreject items'
                    ], 403);
                }
            }

            if (!in_array($supplyRequest->status, ['pending', 'supply_approved', 'admin_assigned'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot unreject items for request with status: {$supplyRequest->status}"
                ], 400);
            }

            $lineItem = SupplyRequestItem::where('supply_request_id', $id)->where('id', $itemId)->first();
            if (!$lineItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Line item not found'
                ], 404);
            }

            if (!$lineItem->isRejected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This item is not rejected'
                ], 400);
            }

            $lineItem->status = SupplyRequestItem::STATUS_PENDING;
            $lineItem->rejection_reason = null;
            $lineItem->rejected_at = null;
            $lineItem->rejected_by = null;
            $lineItem->save();

            return response()->json([
                'success' => true,
                'message' => 'Item restored. It can be processed again.',
                'data' => [
                    'id' => $lineItem->id,
                    'item_id' => $lineItem->item_id,
                    'status' => $lineItem->status,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error unrejecting supply request item: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Forward supply request to another supply account (Supply role)
     */
    public function forwardToAdmin(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');
            
            // Check if user is Supply Account
            if ($role !== 'supply') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account can forward requests to another supply account'
                ], 403);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);
            
            // For supply accounts, check if they are the target supply account
            if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                if ($supplyRequest->target_supply_account_id !== $user->id && $supplyRequest->target_supply_account_id !== null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You can only forward requests submitted to your supply account'
                    ], 403);
                }
            }

            // Check current status
            if ($supplyRequest->status !== 'supply_approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only forward supply_approved requests to another supply account'
                ], 400);
            }

            $validated = $request->validate([
                'supply_account_id' => 'required|exists:users,id',
                'comments' => 'nullable|string|max:1000',
            ]);

            // Verify supply account user
            $targetSupplyAccount = User::findOrFail($validated['supply_account_id']);
            $targetRole = strtolower($targetSupplyAccount->role ?? '');
            if ($targetRole !== 'supply') {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected user is not a supply account'
                ], 400);
            }

            // Prevent forwarding to self
            if ($targetSupplyAccount->id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot forward request to your own supply account'
                ], 400);
            }

            // Update target supply account
            if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                $supplyRequest->target_supply_account_id = $validated['supply_account_id'];
            }
            
            // Store forwarding information in admin_comments field (for backward compatibility)
            $supplyRequest->admin_comments = $validated['comments'] ?? null;
            
            // Keep status as supply_approved since it's still with supply accounts
            // The request is now assigned to a different supply account
            $supplyRequest->save();

            // Load relationships
            $supplyRequest->load(['requestedBy', 'approver']);

            return response()->json([
                'success' => true,
                'message' => 'Supply request forwarded to another supply account successfully',
                'data' => $supplyRequest
            ]);
        } catch (\Exception $e) {
            Log::error('Error forwarding supply request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to forward supply request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign supply request to admin (Supply role)
     */
    public function assignToAdmin(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');
            
            // Check if user has permission
            if (!in_array($role, ['supply', 'admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account and Admin can assign requests'
                ], 403);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);
            
            // For supply accounts, check if they are the target supply account
            if ($role === 'supply') {
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    if ($supplyRequest->target_supply_account_id !== $user->id && $supplyRequest->target_supply_account_id !== null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You can only assign requests submitted to your supply account'
                        ], 403);
                    }
                }
            }

            $validated = $request->validate([
                'admin_id' => 'required|exists:users,id',
            ]);

            // Verify admin user
            $admin = User::findOrFail($validated['admin_id']);
            $adminRole = strtolower($admin->role ?? '');
            if (!in_array($adminRole, ['admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected user is not an admin'
                ], 400);
            }

            $supplyRequest->assigned_to_admin_id = $validated['admin_id'];
            $supplyRequest->status = 'admin_assigned';
            $supplyRequest->assigned_at = now();
            $supplyRequest->save();

            // Load relationships
            $supplyRequest->load(['requestedBy', 'approver', 'assignedToAdmin']);

            return response()->json([
                'success' => true,
                'message' => 'Supply request assigned to admin successfully',
                'data' => $supplyRequest
            ]);
        } catch (\Exception $e) {
            Log::error('Error assigning supply request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign supply request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept supply request by admin (Admin role)
     */
    public function acceptByAdmin(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');
            
            // Check if user is Admin
            if (!in_array($role, ['admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Admin can accept requests'
                ], 403);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);

            // Check current status
            if ($supplyRequest->status !== 'admin_assigned') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only accept admin_assigned requests'
                ], 400);
            }

            $item = $supplyRequest->item();
            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found for this request'
                ], 404);
            }

            $currentQuantity = $item->quantity ?? 0;
            if ($supplyRequest->quantity > $currentQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock. Available: {$currentQuantity}, Requested: {$supplyRequest->quantity}"
                ], 400);
            }

            DB::beginTransaction();
            try {
                // Deduct quantity from item
                $newQuantity = $currentQuantity - $supplyRequest->quantity;
                $item->quantity = $newQuantity;
                $item->save();

                $supplyRequest->status = 'approved';
                $supplyRequest->approved_by = $user->id;
                $supplyRequest->approved_at = now();
                $supplyRequest->admin_accepted_at = now();
            $supplyRequest->save();

                DB::commit();

                // Load relationships
                $supplyRequest->load(['requestedBy', 'approver', 'assignedToAdmin']);

            return response()->json([
                'success' => true,
                    'message' => 'Supply request accepted and approved successfully',
                    'data' => $supplyRequest
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error accepting supply request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept supply request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fulfill a supply request (Supply Account or Admin)
     */
    public function fulfillRequest(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');
            
            // Check if user has permission
            if (!in_array($role, ['supply', 'admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account and Admin can fulfill requests'
                ], 403);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);
            
            // For supply accounts, check if they are the target supply account
            if ($role === 'supply') {
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    if ($supplyRequest->target_supply_account_id !== $user->id && $supplyRequest->target_supply_account_id !== null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Unauthorized: You can only fulfill requests submitted to your supply account'
                        ], 403);
                    }
                }
            }

            // Check current status - can fulfill approved or ready_for_pickup requests
            if (!in_array($supplyRequest->status, ['approved', 'ready_for_pickup'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only fulfill approved or ready-for-pickup requests'
                ], 400);
            }

            // Load items relationship if available
            $hasMultipleItems = false;
            $requestItems = [];
            
            if (Schema::hasTable('supply_request_items')) {
                $supplyRequest->load('items');
                if ($supplyRequest->items && $supplyRequest->items->count() > 0) {
                    $requestItems = $supplyRequest->items->filter(fn ($ri) => !$ri->isRejected())->values();
                    if ($requestItems->isEmpty()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No items to fulfill; all line items were rejected.'
                        ], 400);
                    }
                    $hasMultipleItems = true;
                }
            }
            
            // If no multiple items, use single item from supply_requests table
            if (!$hasMultipleItems) {
                $item = $supplyRequest->item();
                if (!$item) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Item not found for this request'
                    ], 404);
                }
                
                // Check if quantity was already deducted (if status is 'approved', it might have been deducted)
                // We'll deduct again at fulfillment regardless, but check stock first
                $currentQuantity = $item->quantity ?? 0;
                if ($supplyRequest->quantity > $currentQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock. Available: {$currentQuantity}, Requested: {$supplyRequest->quantity}"
                    ], 400);
                }
            } else {
                // Check stock for all items before processing
                $insufficientItems = [];
                foreach ($requestItems as $requestItem) {
                    $item = $requestItem->item();
                    if (!$item) {
                        $insufficientItems[] = "Item ID {$requestItem->item_id} not found";
                        continue;
                    }
                    
                    $currentQuantity = $item->quantity ?? 0;
                    if ($requestItem->quantity > $currentQuantity) {
                        $itemName = $item->unit ?? $item->description ?? "Item {$requestItem->item_id}";
                        $insufficientItems[] = "{$itemName}: Available {$currentQuantity}, Requested {$requestItem->quantity}";
                    }
                }
                
                if (!empty($insufficientItems)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock for some items: ' . implode('; ', $insufficientItems)
                    ], 400);
                }
            }

            $validated = $request->validate([
                'fulfillment_notes' => 'nullable|string|max:1000',
                'delivery_location' => 'nullable|string|max:255',
            ]);

            // Ensure requestedBy relationship is loaded BEFORE transaction
            if (!$supplyRequest->relationLoaded('requestedBy')) {
                $supplyRequest->load(['requestedBy.location']);
            } else {
                if ($supplyRequest->requestedBy && !$supplyRequest->requestedBy->relationLoaded('location')) {
                    $supplyRequest->requestedBy->load('location');
                }
            }

            DB::beginTransaction();
            try {
                // Deduct quantity from items when fulfilling
                if ($hasMultipleItems) {
                    // Process all items in the request
                    $deductedItems = [];
                    foreach ($requestItems as $requestItem) {
                        $item = $requestItem->item();
                        if (!$item) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Item not found for request item ID: {$requestItem->id}"
                            ], 404);
                        }
                        
                        $currentQuantity = $item->quantity ?? 0;
                        $requestedQuantity = $requestItem->quantity;
                        $newQuantity = $currentQuantity - $requestedQuantity;
                        
                        if ($newQuantity < 0) {
                            DB::rollBack();
                            $itemName = $item->unit ?? $item->description ?? "Item {$item->id}";
                            return response()->json([
                                'success' => false,
                                'message' => "Cannot fulfill: {$itemName} stock would become negative. Available: {$currentQuantity}, Requested: {$requestedQuantity}"
                            ], 400);
                        }
                        
                        // Update item quantity
                        try {
                            $item->quantity = $newQuantity;
                            $item->save();
                            $itemName = $item->unit ?? $item->description ?? "Item {$item->id}";
                            Log::info("Item quantity deducted on fulfillment: {$itemName} (ID: {$item->id}) from {$currentQuantity} to {$newQuantity}");
                            $deductedItems[] = $itemName;
                            
                            // Automatically track usage for this item
                            $this->trackUsageFromFulfillment($item, $currentQuantity, $newQuantity, $requestedQuantity);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Log::error('Error updating item quantity on fulfillment: ' . $e->getMessage());
                            return response()->json([
                                'success' => false,
                                'message' => 'Failed to update item quantity: ' . $e->getMessage()
                            ], 500);
                        }
                    }
                    
                    // Combine item names for transaction record
                    $itemName = implode(', ', $deductedItems);
                    // Calculate total quantity for multiple items
                    $totalQuantity = $requestItems->sum('quantity');
                } else {
                    // Single item fulfillment (original logic)
                    $newQuantity = $currentQuantity - $supplyRequest->quantity;
                    if ($newQuantity < 0) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot fulfill: Stock would become negative. Available: {$currentQuantity}, Requested: {$supplyRequest->quantity}"
                        ], 400);
                    }
                    
                    // Update item quantity
                    try {
                        $item->quantity = $newQuantity;
                        $item->save();
                        Log::info("Item quantity deducted on fulfillment: {$item->id} from {$currentQuantity} to {$newQuantity}");
                        
                        // Automatically track usage for this item
                        $this->trackUsageFromFulfillment($item, $currentQuantity, $newQuantity, $supplyRequest->quantity);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Error updating item quantity on fulfillment: ' . $e->getMessage());
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to update item quantity: ' . $e->getMessage()
                        ], 500);
                    }
                    
                    $itemName = $item->unit ?? $item->description ?? 'N/A';
                    // Set total quantity for single item
                    $totalQuantity = $supplyRequest->quantity;
                }

                // Update supply request status
                $supplyRequest->status = 'fulfilled';
                $supplyRequest->fulfilled_at = now();
                $supplyRequest->fulfilled_by = $user->id;
                $supplyRequest->fulfillment_notes = $validated['fulfillment_notes'] ?? null;
                $supplyRequest->delivery_location = $validated['delivery_location'] ?? null;
                $supplyRequest->save();

                // Prepare data for transaction record
                $requestedByUser = $supplyRequest->requestedBy;
                $requestedByName = $requestedByUser ? ($requestedByUser->fullname ?? $requestedByUser->username ?? $requestedByUser->email ?? 'N/A') : 'N/A';
                $fulfillerName = $user->fullname ?? $user->username ?? $user->email ?? 'N/A';
                // Note: $itemName and $totalQuantity are already set in the if/else block above
                
                // Get user location
                $userLocation = 'N/A';
                if ($requestedByUser && $requestedByUser->location_id) {
                    try {
                        if (!$requestedByUser->relationLoaded('location')) {
                            $requestedByUser->load('location');
                        }
                        
                        if ($requestedByUser->location) {
                            $userLocation = $requestedByUser->location->location ?? 'N/A';
                        } else {
                            $location = Location::find($requestedByUser->location_id);
                            if ($location) {
                                $userLocation = $location->location ?? 'N/A';
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error fetching location for fulfillment transaction: ' . $e->getMessage());
                    }
                }
                
                // Create transaction record for fulfillment
                try {
                    Transaction::create([
                        'approved_by' => $fulfillerName,
                        'borrower_name' => $requestedByName,
                        'requested_by' => $requestedByName,
                        'location' => $userLocation,
                        'item_name' => $itemName,
                        'quantity' => $totalQuantity,
                        'transaction_time' => now(),
                        'role' => strtoupper($role),
                        'status' => 'Fulfilled',
                    ]);
                    Log::info("Transaction created for fulfilled supply request ID: {$supplyRequest->id} with {$totalQuantity} total quantity");
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error creating fulfillment transaction record: ' . $e->getMessage());
                    Log::error('Stack trace: ' . $e->getTraceAsString());
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create transaction record: ' . $e->getMessage()
                    ], 500);
                }

                DB::commit();

                // Reload supply request to ensure we have fresh data
                $supplyRequest->refresh();
                $supplyRequest->load(['requestedBy', 'approver', 'fulfilledBy']);

                // Create messages AFTER transaction commits successfully
                // This prevents transaction rollback if message creation fails
                try {
                    // Get item names for message
                    if ($hasMultipleItems && $supplyRequest->relationLoaded('items')) {
                        $itemNames = [];
                        foreach ($supplyRequest->items as $requestItem) {
                            $freshItem = $requestItem->item();
                            if ($freshItem) {
                                $itemNames[] = ($freshItem->unit ?? $freshItem->description ?? 'N/A') . " ({$requestItem->quantity})";
                            }
                        }
                        $itemNameForMessage = implode(', ', $itemNames);
                    } else {
                        $freshItem = $supplyRequest->item();
                        $itemNameForMessage = $freshItem ? ($freshItem->unit ?? $freshItem->description ?? 'N/A') : 'N/A';
                    }
                    
                    $fulfillerName = $user->fullname ?? $user->username ?? $user->email ?? 'Supply Account';
                    $requestingUserId = $supplyRequest->requested_by_user_id;
                    $approverUserId = $supplyRequest->approved_by;
                    
                    // Message for the requesting user
                    if ($requestingUserId) {
                        try {
                            $userMessage = "Your supply request has been fulfilled! {$itemNameForMessage} (Total Quantity: {$totalQuantity}) has been delivered.";
                            if ($supplyRequest->delivery_location) {
                                $userMessage .= " Delivery location: {$supplyRequest->delivery_location}.";
                            }
                            if ($supplyRequest->fulfillment_notes) {
                                $userMessage .= " Notes: {$supplyRequest->fulfillment_notes}";
                            }
                            
                            $userMsg = SupplyRequestMessage::create([
                                'supply_request_id' => $supplyRequest->id,
                                'user_id' => $user->id, // Supply account who fulfilled (sender)
                                'message' => $userMessage,
                                'is_read' => false,
                            ]);
                            
                            if ($userMsg) {
                                Log::info("Fulfillment message created for user - Supply Request ID: {$supplyRequest->id}, Message ID: {$userMsg->id}, User ID: {$requestingUserId}, Fulfiller ID: {$user->id}");
                                
                                // Broadcast event for real-time update
                                event(new SupplyRequestApproved($supplyRequest, $userMessage));
                            }
                        } catch (\Exception $e) {
                            Log::error('Error creating fulfillment message for user: ' . $e->getMessage());
                            Log::error('Stack trace: ' . $e->getTraceAsString());
                            // Continue even if message creation fails
                        }
                    }
                    
                    // Message for the admin who approved (if different from fulfiller)
                    // Note: Admin can see all messages for requests they approved via getAllMessages endpoint
                    if ($approverUserId && $approverUserId != $user->id) {
                        try {
                            $approver = User::find($approverUserId);
                            if ($approver) {
                                $adminMessage = "Supply request has been fulfilled by {$fulfillerName}. {$itemNameForMessage} (Total Quantity: {$totalQuantity}) has been delivered to the requester.";
                                if ($supplyRequest->delivery_location) {
                                    $adminMessage .= " Delivery location: {$supplyRequest->delivery_location}.";
                                }
                                
                                $adminMsg = SupplyRequestMessage::create([
                                    'supply_request_id' => $supplyRequest->id,
                                    'user_id' => $user->id, // Supply account who fulfilled (sender)
                                    'message' => $adminMessage,
                                    'is_read' => false,
                                ]);
                                
                                if ($adminMsg) {
                                    Log::info("Fulfillment message created for admin - Supply Request ID: {$supplyRequest->id}, Message ID: {$adminMsg->id}, Admin User ID: {$approverUserId}, Fulfiller ID: {$user->id}");
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error('Error creating fulfillment message for admin: ' . $e->getMessage());
                            Log::error('Stack trace: ' . $e->getTraceAsString());
                            // Continue even if message creation fails
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error creating fulfillment messages: ' . $e->getMessage());
                    Log::error('Stack trace: ' . $e->getTraceAsString());
                    // Continue even if message creation fails - transaction already committed
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Supply request fulfilled successfully. Quantity deducted from stock.',
                    'data' => $supplyRequest
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e; // Re-throw to be caught by outer catch
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error fulfilling supply request: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fulfill supply request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule pickup time and notify user (Supply Account only)
     */
    public function schedulePickup(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');
            
            // Only supply accounts can schedule pickup
            if ($role !== 'supply') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account can schedule pickup'
                ], 403);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);
            
            // Check if supply account is the target supply account
            if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                if ($supplyRequest->target_supply_account_id !== $user->id && $supplyRequest->target_supply_account_id !== null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You can only schedule pickup for requests submitted to your supply account'
                    ], 403);
                }
            }
            
            // Check current status - must be ready_for_pickup
            if ($supplyRequest->status !== 'ready_for_pickup') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only schedule pickup for requests with status: ready_for_pickup'
                ], 400);
            }

            $validated = $request->validate([
                'pickup_scheduled_at' => 'required|date|after:now',
                'notify_user' => 'nullable|boolean',
            ]);

            $pickupScheduledAt = \Carbon\Carbon::parse($validated['pickup_scheduled_at']);
            $notifyUser = $validated['notify_user'] ?? true;

            // Load relationships
            $supplyRequest->load(['requestedBy', 'items']);
            
            // Get items for message
            $requestItems = [];
            if (Schema::hasTable('supply_request_items') && $supplyRequest->relationLoaded('items') && $supplyRequest->items->count() > 0) {
                foreach ($supplyRequest->items as $requestItem) {
                    $itemObj = $requestItem->item();
                    if ($itemObj) {
                        $requestItems[] = [
                            'item' => $itemObj,
                            'item_name' => $itemObj->unit ?? $itemObj->description ?? 'N/A',
                            'quantity' => $requestItem->quantity,
                        ];
                    }
                }
            } else {
                $item = $supplyRequest->item();
                if ($item) {
                    $requestItems[] = [
                        'item' => $item,
                        'item_name' => $item->unit ?? $item->description ?? 'N/A',
                        'quantity' => $supplyRequest->quantity,
                    ];
                }
            }

            DB::beginTransaction();
            try {
                // Update pickup scheduled time
                $supplyRequest->pickup_scheduled_at = $pickupScheduledAt;
                
                // If notify_user is true, mark as notified and send notification
                if ($notifyUser) {
                    $supplyRequest->pickup_notified_at = now();
                }
                
                $supplyRequest->save();
                
                DB::commit();
                
                // Create notification and message AFTER transaction commits
                if ($notifyUser && $supplyRequest->requested_by_user_id) {
                    try {
                        $requestingUser = $supplyRequest->requestedBy;
                        $supplyAccountName = $user->fullname ?? $user->username ?? 'Supply Account';
                        
                        // Build notification message
                        if (count($requestItems) > 1) {
                            $itemNames = array_map(function($item) {
                                return $item['item_name'] . ' (' . $item['quantity'] . ')';
                            }, $requestItems);
                            $notificationMessage = "Your supply request for " . count($requestItems) . " item(s): " . implode(', ', $itemNames) . " is ready for pickup! Scheduled pickup time: " . $pickupScheduledAt->format('M d, Y h:i A');
                        } else {
                            $itemName = $requestItems[0]['item_name'] ?? 'N/A';
                            $notificationMessage = "Your supply request for {$itemName} (Quantity: {$supplyRequest->quantity}) is ready for pickup! Scheduled pickup time: " . $pickupScheduledAt->format('M d, Y h:i A');
                        }
                        
                        $firstItem = $requestItems[0]['item'] ?? null;
                        $itemId = $firstItem ? $firstItem->id : null;
                        
                        if ($itemId) {
                            $notification = Notification::create([
                                'item_id' => $itemId,
                                'user_id' => $supplyRequest->requested_by_user_id, // Only for the requesting user
                                'message' => $notificationMessage,
                                'type' => 'supply_request_ready_pickup',
                                'is_read' => false,
                            ]);
                            
                            if ($notification) {
                                $notification->refresh();
                                $notification->load('item');
                                event(new NotificationCreated($notification));
                                Log::info("Pickup notification created for request ID: {$supplyRequest->id}");
                            }
                        }
                        
                        // Create message for user
                        $pickupMessage = "🎉 Great news! Your supply request is ready for pickup.\n\n";
                        
                        if (count($requestItems) > 1) {
                            $pickupMessage .= "Items:\n";
                            foreach ($requestItems as $itemData) {
                                $pickupMessage .= "• {$itemData['item_name']} (Quantity: {$itemData['quantity']})\n";
                            }
                        } else {
                            $itemName = $requestItems[0]['item_name'] ?? 'N/A';
                            $pickupMessage .= "Item: {$itemName}\n";
                            $pickupMessage .= "Quantity: {$supplyRequest->quantity}\n";
                        }
                        
                        $pickupMessage .= "\n";
                        $pickupMessage .= "📅 Scheduled Pickup Time: " . $pickupScheduledAt->format('F d, Y h:i A') . "\n";
                        $pickupMessage .= "👤 Prepared by: {$supplyAccountName}\n\n";
                        $pickupMessage .= "Please come to the supply office at the scheduled time to pick up your items.\n";
                        $pickupMessage .= "If you need to reschedule, please contact the supply account.";
                        
                        $message = SupplyRequestMessage::create([
                            'supply_request_id' => $supplyRequest->id,
                            'user_id' => $user->id, // Supply account who scheduled (sender)
                            'message' => $pickupMessage,
                            'is_read' => false,
                        ]);
                        
                        if ($message) {
                            Log::info("Pickup message created for request ID: {$supplyRequest->id}, Message ID: {$message->id}");
                            
                            // Broadcast event for real-time update
                            event(new SupplyRequestApproved($supplyRequest, $pickupMessage));
                        }
                    } catch (\Exception $e) {
                        Log::error('Error creating pickup notification/message: ' . $e->getMessage());
                        // Don't fail the request - pickup time is already saved
                    }
                }
                
                $supplyRequest->refresh();
                $supplyRequest->load(['requestedBy', 'approver']);
                
                return response()->json([
                    'success' => true,
                    'message' => $notifyUser ? 'Pickup time scheduled and user notified successfully' : 'Pickup time scheduled successfully',
                    'data' => $supplyRequest
                ]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error scheduling pickup: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule pickup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify user that their request is ready for pickup (Supply Account only)
     * This is separate from scheduling pickup - it just sends a notification
     */
    public function notifyUserReadyForPickup(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');
            
            // Only supply accounts can notify users
            if ($role !== 'supply') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account can notify users'
                ], 403);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);
            
            // Check if supply account is the target supply account
            if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                if ($supplyRequest->target_supply_account_id !== $user->id && $supplyRequest->target_supply_account_id !== null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You can only notify users for requests submitted to your supply account'
                    ], 403);
                }
            }
            
            // Check current status - must be ready_for_pickup
            if ($supplyRequest->status !== 'ready_for_pickup') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only notify users for requests with status: ready_for_pickup'
                ], 400);
            }

            // Load relationships
            $supplyRequest->load(['requestedBy', 'items']);
            
            // Get items for message
            $requestItems = [];
            if (Schema::hasTable('supply_request_items') && $supplyRequest->relationLoaded('items') && $supplyRequest->items->count() > 0) {
                foreach ($supplyRequest->items as $requestItem) {
                    $itemObj = $requestItem->item();
                    if ($itemObj) {
                        $requestItems[] = [
                            'item' => $itemObj,
                            'item_name' => $itemObj->unit ?? $itemObj->description ?? 'N/A',
                            'quantity' => $requestItem->quantity,
                        ];
                    }
                }
            } else {
                $item = $supplyRequest->item();
                if ($item) {
                    $requestItems[] = [
                        'item' => $item,
                        'item_name' => $item->unit ?? $item->description ?? 'N/A',
                        'quantity' => $supplyRequest->quantity,
                    ];
                }
            }

            if (!$supplyRequest->requested_by_user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot notify: Requesting user not found'
                ], 400);
            }

            try {
                $requestingUser = $supplyRequest->requestedBy;
                $supplyAccountName = $user->fullname ?? $user->username ?? 'Supply Account';
                
                // Build notification message
                if (count($requestItems) > 1) {
                    $itemNames = array_map(function($item) {
                        return $item['item_name'] . ' (' . $item['quantity'] . ')';
                    }, $requestItems);
                    $notificationMessage = "Your supply request for " . count($requestItems) . " item(s): " . implode(', ', $itemNames) . " is ready for pickup!";
                } else {
                    $itemName = $requestItems[0]['item_name'] ?? 'N/A';
                    $notificationMessage = "Your supply request for {$itemName} (Quantity: {$supplyRequest->quantity}) is ready for pickup!";
                }
                
                // Add pickup time if scheduled
                if ($supplyRequest->pickup_scheduled_at) {
                    $pickupTime = \Carbon\Carbon::parse($supplyRequest->pickup_scheduled_at);
                    $notificationMessage .= " Scheduled pickup time: " . $pickupTime->format('M d, Y h:i A');
                } else {
                    $notificationMessage .= " Please contact the supply account to arrange pickup.";
                }
                
                // Create message for user FIRST (this is the primary communication)
                $pickupMessage = "🎉 Great news! Your supply request is ready for pickup.\n\n";
                
                if (count($requestItems) > 1) {
                    $pickupMessage .= "Items:\n";
                    foreach ($requestItems as $itemData) {
                        $pickupMessage .= "• {$itemData['item_name']} (Quantity: {$itemData['quantity']})\n";
                    }
                } else {
                    $itemName = $requestItems[0]['item_name'] ?? 'N/A';
                    $pickupMessage .= "Item: {$itemName}\n";
                    $pickupMessage .= "Quantity: {$supplyRequest->quantity}\n";
                }
                
                $pickupMessage .= "\n";
                
                if ($supplyRequest->pickup_scheduled_at) {
                    $pickupTime = \Carbon\Carbon::parse($supplyRequest->pickup_scheduled_at);
                    $pickupMessage .= "📅 Scheduled Pickup Time: " . $pickupTime->format('F d, Y h:i A') . "\n";
                } else {
                    $pickupMessage .= "⏰ Please contact us to arrange a convenient pickup time.\n";
                }
                
                $pickupMessage .= "👤 Prepared by: {$supplyAccountName}\n\n";
                $pickupMessage .= "Please come to the supply office to pick up your items.\n";
                $pickupMessage .= "If you need to reschedule, please contact the supply account.";
                
                // Create message - this is the primary communication method
                $message = SupplyRequestMessage::create([
                    'supply_request_id' => $supplyRequest->id,
                    'user_id' => $user->id, // Supply account who notified (sender)
                    'message' => $pickupMessage,
                    'is_read' => false,
                ]);
                
                if (!$message) {
                    Log::error("Failed to create pickup message for request ID: {$supplyRequest->id}");
                    throw new \Exception('Failed to create message');
                }
                
                Log::info("Pickup notification message created for request ID: {$supplyRequest->id}, Message ID: {$message->id}");
                
                // Update pickup_notified_at if column exists
                if (Schema::hasColumn('supply_requests', 'pickup_notified_at')) {
                    $supplyRequest->pickup_notified_at = now();
                    $supplyRequest->save();
                }
                
                // Broadcast event for real-time update (this will trigger message refresh in frontend)
                event(new SupplyRequestApproved($supplyRequest, $pickupMessage));
                
                // Also create notification (secondary - for notification dropdown)
                $firstItem = $requestItems[0]['item'] ?? null;
                $itemId = $firstItem ? $firstItem->id : null;
                
                if ($itemId) {
                    try {
                        $notification = Notification::create([
                            'item_id' => $itemId,
                            'user_id' => $supplyRequest->requested_by_user_id, // Only for the requesting user
                            'message' => $notificationMessage,
                            'type' => 'supply_request_ready_pickup',
                            'is_read' => false,
                        ]);
                        
                        if ($notification) {
                            $notification->refresh();
                            $notification->load('item');
                            event(new NotificationCreated($notification));
                            Log::info("Pickup notification created for request ID: {$supplyRequest->id}");
                        }
                    } catch (\Exception $notifError) {
                        // Don't fail the whole operation if notification creation fails
                        // The message is more important
                        Log::warning("Failed to create notification for request ID: {$supplyRequest->id}: " . $notifError->getMessage());
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'User notified successfully',
                    'data' => $supplyRequest->fresh()
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error notifying user for pickup: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to notify user: ' . $e->getMessage()
                ], 500);
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in notifyUserReadyForPickup: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to notify user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock overview (Supply Account and Admin)
     */
    public function getStockOverview(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $role = strtolower($user->role ?? '');
            
            // Check if user has permission
            if (!in_array($role, ['supply', 'admin', 'super_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply Account and Admin can view stock overview'
                ], 403);
            }

            // Find the "Supply" category
            $supplyCategory = Category::where('category', 'Supply')->first();

            if (!$supplyCategory) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'summary' => [
                        'total_items' => 0,
                        'total_quantity' => 0,
                        'low_stock_count' => 0,
                        'low_stock_threshold' => 50,
                    ]
                ]);
            }

            $items = Item::with(['category', 'location'])
                ->where('category_id', $supplyCategory->id)
                ->whereNull('deleted_at')
                ->get();

            $lowStockThreshold = 50; // Same as CheckLowStockJob
            $lowStockItems = $items->filter(function ($item) use ($lowStockThreshold) {
                return ($item->quantity ?? 0) < $lowStockThreshold;
            });

            $transformedItems = $items->map(function ($item) use ($lowStockThreshold) {
                return [
                    'id' => $item->id,
                    'uuid' => $item->uuid,
                    'unit' => $item->unit ?? 'N/A',
                    'description' => $item->description ?? 'N/A',
                    'quantity' => $item->quantity ?? 0,
                    'category' => $item->category->category ?? 'N/A',
                    'location' => $item->location->name ?? 'N/A',
                    'pac' => $item->pac ?? 'N/A',
                    'is_low_stock' => ($item->quantity ?? 0) < $lowStockThreshold,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedItems,
                'summary' => [
                    'total_items' => $items->count(),
                    'total_quantity' => $items->sum('quantity'),
                    'low_stock_count' => $lowStockItems->count(),
                    'low_stock_threshold' => $lowStockThreshold,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching stock overview: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock overview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get messages for a supply request
     */
    public function getMessages(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);

            // Check authorization - user can see messages if:
            // 1. They requested it
            // 2. They are supply/admin/super_admin (supply only if they are the target)
            // 3. They are assigned to this request
            // 4. This request was forwarded to them
            $role = strtolower($user->role ?? '');
            $canView = $supplyRequest->requested_by_user_id === $user->id 
                    || $supplyRequest->assigned_to_admin_id === $user->id
                    || $supplyRequest->forwarded_to_admin_id === $user->id;
            
            // For supply accounts, check if they are the target supply account
            if ($role === 'supply') {
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    // Supply account can view if they are the target supply account
                    // or if target_supply_account_id is null (backward compatibility)
                    $canView = $canView || ($supplyRequest->target_supply_account_id === $user->id || $supplyRequest->target_supply_account_id === null);
                } else {
                    // If column doesn't exist, allow all supply accounts (backward compatibility)
                    $canView = $canView || true;
                }
            } elseif (in_array($role, ['admin', 'super_admin'])) {
                // Admins can always view
                $canView = true;
            }

            if (!$canView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to view messages for this request'
                ], 403);
            }

            $messages = SupplyRequestMessage::with('user')
                ->where('supply_request_id', $id)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $messages->map(function ($msg) {
                    return [
                        'id' => $msg->id,
                        'message' => $msg->message,
                        'user' => [
                            'id' => $msg->user->id,
                            'name' => $msg->user->fullname ?? $msg->user->username ?? 'Unknown',
                            'role' => $msg->user->role ?? 'user',
                        ],
                        'is_read' => $msg->is_read,
                        'read_at' => $msg->read_at ? $msg->read_at->toISOString() : null,
                        'created_at' => $msg->created_at->toISOString(),
                        'created_at_formatted' => $msg->created_at->format('M d, Y h:i A'),
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching supply request messages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message for a supply request
     */
    public function sendMessage(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);

            // Check authorization - user can send messages if:
            // 1. They requested it
            // 2. They are supply/admin/super_admin (supply only if they are the target)
            // 3. They are assigned to this request
            // 4. This request was forwarded to them
            $role = strtolower($user->role ?? '');
            $canSend = $supplyRequest->requested_by_user_id === $user->id 
                    || $supplyRequest->assigned_to_admin_id === $user->id
                    || $supplyRequest->forwarded_to_admin_id === $user->id;
            
            // For supply accounts, check if they are the target supply account
            if ($role === 'supply') {
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    // Supply account can send if they are the target supply account
                    // or if target_supply_account_id is null (backward compatibility)
                    $canSend = $canSend || ($supplyRequest->target_supply_account_id === $user->id || $supplyRequest->target_supply_account_id === null);
                } else {
                    // If column doesn't exist, allow all supply accounts (backward compatibility)
                    $canSend = $canSend || true;
                }
            } elseif (in_array($role, ['admin', 'super_admin'])) {
                // Admins can always send
                $canSend = true;
            }

            if (!$canSend) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to send messages for this request'
                ], 403);
            }

            $validated = $request->validate([
                'message' => 'required|string|max:1000',
            ]);

            $message = SupplyRequestMessage::create([
                'supply_request_id' => $id,
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_read' => false,
            ]);

            $message->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'user' => [
                        'id' => $message->user->id,
                        'name' => $message->user->fullname ?? $message->user->username ?? 'Unknown',
                        'role' => $message->user->role ?? 'user',
                    ],
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->toISOString(),
                    'created_at_formatted' => $message->created_at->format('M d, Y h:i A'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending supply request message: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark messages as read for a supply request
     */
    public function markMessagesAsRead(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);

            // Check authorization - user can mark messages as read if they have permission to view them
            $role = strtolower($user->role ?? '');
            $canView = $supplyRequest->requested_by_user_id === $user->id 
                    || $supplyRequest->assigned_to_admin_id === $user->id
                    || $supplyRequest->forwarded_to_admin_id === $user->id;
            
            // For supply accounts, check if they are the target supply account
            if ($role === 'supply') {
                if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                    // Supply account can view if they are the target supply account
                    // or if target_supply_account_id is null (backward compatibility)
                    $canView = $canView || ($supplyRequest->target_supply_account_id === $user->id || $supplyRequest->target_supply_account_id === null);
                } else {
                    // If column doesn't exist, allow all supply accounts (backward compatibility)
                    $canView = $canView || true;
                }
            } elseif (in_array($role, ['admin', 'super_admin'])) {
                // Admins can always view
                $canView = true;
            }

            if (!$canView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to mark messages as read for this request'
                ], 403);
            }

            // Mark all unread messages as read (except messages sent by the current user)
            $updated = SupplyRequestMessage::where('supply_request_id', $id)
                ->where('user_id', '!=', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "Marked {$updated} message(s) as read"
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking messages as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread messages count for a user
     */
    public function getUnreadMessagesCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Check if table exists
            if (!Schema::hasTable('supply_request_messages')) {
                return response()->json([
                    'success' => true,
                    'count' => 0
                ]);
            }

            $role = strtolower($user->role ?? '');
            $count = 0;
            
            try {
                // Use join instead of whereHas for better performance
                $query = SupplyRequestMessage::query()
                    ->join('supply_requests', 'supply_request_messages.supply_request_id', '=', 'supply_requests.id')
                    ->where('supply_request_messages.user_id', '!=', $user->id)
                    ->where('supply_request_messages.is_read', false);
                
                if (in_array($role, ['supply', 'admin', 'super_admin'])) {
                    // For supply/admin: count unread messages in all requests (except their own messages)
                    if ($role === 'supply') {
                        // Supply accounts can only see messages from requests submitted to them
                        if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                            $query->where(function($q) use ($user) {
                                $q->where('supply_requests.target_supply_account_id', $user->id)
                                  ->orWhereNull('supply_requests.target_supply_account_id');
                            });
                        }
                    }
                    // Admins can see all messages (no additional where clause needed)
                } else {
                    // For regular users: count unread messages in their own requests (except their own messages)
                    $query->where('supply_requests.requested_by_user_id', $user->id);
                }
                
                $count = $query->count();
            } catch (\Exception $e) {
                // If there's an error querying (e.g., table structure issue), return 0
                Log::warning('Error querying unread messages count: ' . $e->getMessage());
                $count = 0;
            }

            return response()->json([
                'success' => true,
                'count' => $count
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching unread messages count: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'count' => 0,
                'message' => 'Failed to fetch unread count: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get all messages for the current user across all supply requests
     */
    public function getAllMessages(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Check if table exists
            if (!Schema::hasTable('supply_request_messages')) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $role = strtolower($user->role ?? '');
            $messages = collect();

            try {
                // Use join instead of whereHas for better performance
                $query = SupplyRequestMessage::query()
                    ->join('supply_requests', 'supply_request_messages.supply_request_id', '=', 'supply_requests.id')
                    ->join('users', 'supply_request_messages.user_id', '=', 'users.id')
                    ->select(
                        'supply_request_messages.*',
                        'supply_requests.item_id as request_item_id',
                        'supply_requests.quantity as request_quantity',
                        'supply_requests.status as request_status'
                    )
                    ->where('supply_request_messages.user_id', '!=', $user->id)
                    ->orderBy('supply_request_messages.created_at', 'desc')
                    ->limit(30); // Reduced from 50 to 30 for better performance

                if (in_array($role, ['supply', 'admin', 'super_admin'])) {
                    // For supply/admin: get all messages in requests they have access to
                    if ($role === 'supply') {
                        // Supply accounts can only see messages from requests submitted to them
                        if (Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
                            $query->where(function($q) use ($user) {
                                $q->where('supply_requests.target_supply_account_id', $user->id)
                                  ->orWhereNull('supply_requests.target_supply_account_id');
                            });
                        }
                    }
                    // Admins can see all messages (no additional where clause needed)
                } else {
                    // For regular users: get all messages in their own requests
                    $query->where('supply_requests.requested_by_user_id', $user->id);
                }

                $messages = $query->get();
                
                // Eager load user relationship
                $messages->load('user');
                
                // Get unique item IDs and batch load items
                $itemIds = $messages->pluck('request_item_id')->filter()->unique()->values();
                $items = collect();
                
                if ($itemIds->isNotEmpty()) {
                    // Batch load items by both ID and UUID to handle both cases
                    $numericIds = $itemIds->filter(fn($id) => is_numeric($id))->values();
                    $uuidIds = $itemIds->reject(fn($id) => is_numeric($id))->values();
                    
                    $itemsQuery = Item::withTrashed();
                    if ($numericIds->isNotEmpty() || $uuidIds->isNotEmpty()) {
                        $itemsQuery->where(function($q) use ($numericIds, $uuidIds) {
                            if ($numericIds->isNotEmpty()) {
                                $q->whereIn('id', $numericIds);
                            }
                            if ($uuidIds->isNotEmpty()) {
                                $q->orWhereIn('uuid', $uuidIds);
                            }
                        });
                    }
                    $items = $itemsQuery->get()->keyBy(function($item) {
                        // Key by both ID and UUID for easy lookup
                        return $item->id . '|' . ($item->uuid ?? '');
                    });
                }

            } catch (\Exception $e) {
                Log::warning('Error querying all messages: ' . $e->getMessage());
                $messages = collect();
                $items = collect();
            }

            // Create item lookup map
            $itemLookup = [];
            foreach ($items as $item) {
                if ($item->id) $itemLookup[$item->id] = $item;
                if ($item->uuid) $itemLookup[$item->uuid] = $item;
            }

            $transformedMessages = $messages->map(function ($msg) use ($itemLookup) {
                $itemId = $msg->request_item_id;
                $item = null;
                
                if ($itemId) {
                    $item = $itemLookup[$itemId] ?? null;
                }
                
                $itemName = $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A';
                
                return [
                    'id' => $msg->id,
                    'message' => $msg->message,
                    'user' => [
                        'id' => $msg->user->id ?? $msg->user_id,
                        'name' => $msg->user->fullname ?? $msg->user->username ?? 'Unknown',
                        'role' => $msg->user->role ?? 'user',
                    ],
                    'supply_request' => [
                        'id' => $msg->supply_request_id,
                        'item_name' => $itemName,
                        'quantity' => $msg->request_quantity,
                        'status' => $msg->request_status,
                    ],
                    'is_read' => $msg->is_read,
                    'read_at' => $msg->read_at ? $msg->read_at->toISOString() : null,
                    'created_at' => $msg->created_at->toISOString(),
                    'created_at_formatted' => $msg->created_at->format('M d, Y h:i A'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedMessages
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all messages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Unit/Section statistics - which units/sections have requested the most
     * Only accessible to Supply account users
     */
    public function getUnitSectionStatistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Check if user is Supply account
            $role = strtolower($user->role ?? '');
            if ($role !== 'supply') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only Supply account users can access this data'
                ], 403);
            }

            // Get Unit/Section statistics grouped by location
            $statistics = SupplyRequest::select(
                'locations.id as location_id',
                'locations.location as unit_section_name',
                'locations.personnel',
                DB::raw('COUNT(supply_requests.id) as total_requests'),
                DB::raw('COUNT(CASE WHEN supply_requests.status = \'approved\' THEN 1 END) as approved_requests'),
                DB::raw('COUNT(CASE WHEN supply_requests.status = \'pending\' THEN 1 END) as pending_requests'),
                DB::raw('COUNT(CASE WHEN supply_requests.status = \'rejected\' THEN 1 END) as rejected_requests'),
                DB::raw('COUNT(CASE WHEN supply_requests.status = \'fulfilled\' THEN 1 END) as fulfilled_requests'),
                DB::raw('COUNT(DISTINCT supply_requests.requested_by_user_id) as unique_users')
            )
            ->join('users', 'supply_requests.requested_by_user_id', '=', 'users.id')
            ->join('locations', 'users.location_id', '=', 'locations.id')
            ->groupBy('locations.id', 'locations.location', 'locations.personnel')
            ->orderBy('total_requests', 'desc')
            ->get();

            // Format the statistics
            $statistics = $statistics->map(function ($stat) {
                return [
                    'location_id' => $stat->location_id,
                    'unit_section_name' => $stat->unit_section_name,
                    'personnel' => $stat->personnel,
                    'total_requests' => (int) $stat->total_requests,
                    'approved_requests' => (int) $stat->approved_requests,
                    'pending_requests' => (int) $stat->pending_requests,
                    'rejected_requests' => (int) $stat->rejected_requests,
                    'fulfilled_requests' => (int) $stat->fulfilled_requests,
                    'unique_users' => (int) $stat->unique_users,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching unit/section statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unit/section statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download/view approval receipt PDF
     */
    public function downloadReceipt(Request $request, $id): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            // Try to get user from Authorization header first (standard auth)
            $user = $request->user();
            
            // If no user from auth header, try token from query parameter (for iframe/object tags)
            if (!$user) {
                // Check Authorization header first
                $bearerToken = $request->bearerToken();
                if ($bearerToken) {
                    try {
                        $personalAccessToken = PersonalAccessToken::findToken($bearerToken);
                        if ($personalAccessToken && !$personalAccessToken->expires_at || $personalAccessToken->expires_at->isFuture()) {
                            $user = $personalAccessToken->tokenable;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error parsing bearer token: ' . $e->getMessage());
                    }
                }
                
                // If still no user, try query parameter
                if (!$user && $request->has('token')) {
                    try {
                        $token = $request->query('token');
                        // Decode URL-encoded token
                        $token = urldecode($token);
                        $personalAccessToken = PersonalAccessToken::findToken($token);
                        if ($personalAccessToken) {
                            // Check if token is expired
                            if (!$personalAccessToken->expires_at || $personalAccessToken->expires_at->isFuture()) {
                                $user = $personalAccessToken->tokenable;
                            } else {
                                Log::warning('Receipt download: Token expired for user ID: ' . $personalAccessToken->tokenable_id);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error parsing token from query parameter: ' . $e->getMessage());
                        Log::warning('Token value: ' . substr($request->query('token'), 0, 20) . '...');
                    }
                }
            }
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $supplyRequest = SupplyRequest::findOrFail($id);

            // Check authorization - user can view receipt if they have permission to view the request
            $role = strtolower($user->role ?? '');
            $canView = $supplyRequest->requested_by_user_id === $user->id 
                    || in_array($role, ['supply', 'admin', 'super_admin'])
                    || $supplyRequest->assigned_to_admin_id === $user->id
                    || $supplyRequest->forwarded_to_admin_id === $user->id;

            if (!$canView) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to view this receipt'
                ], 403);
            }

            if (!$supplyRequest->approval_proof) {
                return response()->json([
                    'success' => false,
                    'message' => 'Receipt not found'
                ], 404);
            }

            // Check if file exists
            if (!Storage::disk('public')->exists($supplyRequest->approval_proof)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Receipt file not found'
                ], 404);
            }

            $filePath = Storage::disk('public')->path($supplyRequest->approval_proof);
            $fileName = 'receipt_' . $supplyRequest->request_number . '.pdf';

            return response()->file($filePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Error downloading receipt: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate approval receipt PDF
     */
    private function generateApprovalReceipt($supplyRequest, $approver)
    {
        try {
            // Load relationships including items for multi-item support
            $supplyRequest->load(['requestedBy', 'items']);
            $item = $supplyRequest->item();
            $requestingUser = $supplyRequest->requestedBy;
            
            // Get all items for this request (multi-item support) — only non-rejected
            $requestItems = [];
            if (Schema::hasTable('supply_request_items') && $supplyRequest->relationLoaded('items') && $supplyRequest->items->count() > 0) {
                foreach ($supplyRequest->items as $requestItem) {
                    if ($requestItem->isRejected()) {
                        continue;
                    }
                    $itemObj = $requestItem->item();
                    if ($itemObj) {
                        $requestItems[] = [
                            'item_name' => $itemObj->unit ?? $itemObj->description ?? 'N/A',
                            'item_description' => $itemObj->description ?? '',
                            'quantity' => $requestItem->quantity,
                        ];
                    }
                }
            } else {
                if ($item) {
                    $requestItems[] = [
                        'item_name' => $item->unit ?? $item->description ?? 'N/A',
                        'item_description' => $item->description ?? '',
                        'quantity' => $supplyRequest->quantity,
                    ];
                }
            }
            
            // Get first item for backward compatibility
            $firstItem = $requestItems[0] ?? null;
            $itemName = $firstItem ? $firstItem['item_name'] : 'N/A';
            $itemDescription = $firstItem ? $firstItem['item_description'] : '';
            
            $requestingUserName = $requestingUser ? ($requestingUser->fullname ?? $requestingUser->username ?? 'Unknown') : 'Unknown';
            $requestingUserEmail = $requestingUser ? ($requestingUser->email ?? '') : '';
            $requestingUserLocation = '';
            if ($requestingUser && $requestingUser->location_id) {
                try {
                    $location = Location::find($requestingUser->location_id);
                    $requestingUserLocation = $location ? ($location->location ?? '') : '';
                } catch (\Exception $e) {
                    Log::warning('Could not fetch location for receipt: ' . $e->getMessage());
                }
            }
            $approverName = $approver->fullname ?? $approver->username ?? 'Admin';
            $approverRole = $approver->role ?? 'Admin';
            $currentDate = now()->format('F d, Y');
            $currentTime = now()->format('h:i A');
            
            // Generate QR code for receipt verification
            $qrCodeData = [
                'receipt_number' => $supplyRequest->request_number,
                'request_id' => $supplyRequest->id,
                'item_name' => $itemName,
                'quantity' => $supplyRequest->quantity,
                'approved_date' => $currentDate,
                'approved_time' => $currentTime,
                'approver_name' => $approverName,
                'verification_url' => url("/api/v1/supply-requests/receipt/{$supplyRequest->request_number}/verify")
            ];
            
            // Generate QR code image
            $qrCodeImage = QrCode::format('png')
                ->size(200)
                ->errorCorrection('H')
                ->generate(json_encode($qrCodeData));
            
            // Ensure QR code directory exists
            $qrCodeDirectory = 'approval-proofs/qrcodes';
            if (!Storage::disk('public')->exists($qrCodeDirectory)) {
                Storage::disk('public')->makeDirectory($qrCodeDirectory);
            }
            
            // Save QR code to storage
            $qrCodeFileName = 'receipt_qr_' . $supplyRequest->request_number . '_' . date('Y-m-d_His') . '.png';
            $qrCodePath = $qrCodeDirectory . '/' . $qrCodeFileName;
            Storage::disk('public')->put($qrCodePath, $qrCodeImage);
            
            // Get QR code as base64 for embedding in PDF
            $qrCodeBase64 = base64_encode($qrCodeImage);
            
            // Get logo as base64 for embedding in PDF
            $logoBase64 = null;
            $logoPath = public_path('logo.png');
            if (file_exists($logoPath)) {
                $logoContent = file_get_contents($logoPath);
                $logoBase64 = base64_encode($logoContent);
            }
            
            $html = $this->generateReceiptHtml([
                'request_number' => $supplyRequest->request_number,
                'item_name' => $itemName,
                'item_description' => $itemDescription,
                'quantity' => $supplyRequest->quantity,
                'items' => $requestItems, // Array of all items
                'items_count' => count($requestItems), // Number of items
                'notes' => $supplyRequest->notes,
                'requesting_user_name' => $requestingUserName,
                'requesting_user_email' => $requestingUserEmail,
                'requesting_user_location' => $requestingUserLocation,
                'approver_name' => $approverName,
                'approver_role' => $approverRole,
                'approved_date' => $currentDate,
                'approved_time' => $currentTime,
                'created_at' => $supplyRequest->created_at->format('F d, Y h:i A'),
                'qr_code_base64' => $qrCodeBase64,
                'qr_code_path' => $qrCodePath,
                'logo_base64' => $logoBase64,
            ]);
            
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'Arial');
            $options->set('chroot', base_path());
            $options->set('isPhpEnabled', true);
            $options->set('isJavascriptEnabled', false);
            
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Force output to ensure PDF is generated
            $output = $dompdf->output();
            
            $fileName = 'approval_receipt_' . $supplyRequest->request_number . '_' . date('Y-m-d_His') . '.pdf';
            $filePath = 'approval-proofs/' . $fileName;
            Storage::disk('public')->put($filePath, $output);
            
            return $filePath;
        } catch (\Exception $e) {
            Log::error('Error generating approval receipt PDF: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generateReceiptHtml($data)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 2cm 2.5cm;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: "Times New Roman", "Times", serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000000;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        .document-wrapper {
            width: 100%;
            min-height: 25cm;
        }
        .letterhead {
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .letterhead-main {
            font-size: 16pt;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }
        .letterhead-sub {
            font-size: 12pt;
            color: #1e40af;
            margin-bottom: 5px;
            font-weight: normal;
        }
        .letterhead-region {
            font-size: 11pt;
            color: #334155;
            font-weight: normal;
        }
        .letterhead-logo {
            margin: 20px auto 15px auto;
            width: 80px;
            height: 80px;
            display: block;
        }
        .document-title {
            text-align: center;
            margin: 35px 0;
            padding: 15px 0;
            border-top: 2px solid #1e3a8a;
            border-bottom: 2px solid #1e3a8a;
        }
        .document-title-main {
            font-size: 18pt;
            font-weight: bold;
            color: #000000;
            margin-bottom: 5px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .document-title-sub {
            font-size: 10pt;
            color: #475569;
            font-style: italic;
        }
        .receipt-number-section {
            text-align: center;
            margin: 30px 0 40px 0;
            padding: 20px;
            background: #f8fafc;
            border: 2px solid #cbd5e1;
        }
        .receipt-number-label {
            font-size: 10pt;
            color: #475569;
            margin-bottom: 10px;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .receipt-number-value {
            font-family: "Courier New", monospace;
            font-size: 16pt;
            font-weight: bold;
            color: #000000;
            letter-spacing: 2px;
            padding: 12px 25px;
            background: #ffffff;
            border: 2px solid #1e3a8a;
            display: inline-block;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        .info-row {
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            width: 35%;
            padding: 10px 15px 10px 0;
            vertical-align: top;
            font-size: 10pt;
            color: #000000;
            text-align: left;
        }
        .info-value {
            padding: 10px 0;
            vertical-align: top;
            font-size: 11pt;
            color: #000000;
            font-weight: normal;
        }
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .section-header {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #1e3a8a;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .section-content {
            padding: 15px 0;
            background: #ffffff;
        }
        .section-content table {
            margin: 0;
        }
        .divider {
            height: 0;
            border: none;
            border-top: 1px solid #cbd5e1;
            margin: 30px 0;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #000000;
            background: #ffffff;
        }
        .badge-medium {
            background: #fff7ed;
            border-color: #ea580c;
            color: #7c2d12;
        }
        .badge-high {
            background: #fef2f2;
            border-color: #dc2626;
            color: #7f1d1d;
        }
        .badge-low {
            background: #eff6ff;
            border-color: #0284c7;
            color: #0c4a6e;
        }
        .two-column {
            display: table;
            width: 100%;
            margin-top: 20px;
        }
        .column {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 30px;
        }
        .column:last-child {
            padding-right: 0;
        }
        .qr-code-section {
            margin: 40px 0;
            text-align: center;
            padding: 25px;
            border: 2px solid #cbd5e1;
            background: #f8fafc;
        }
        .qr-code-title {
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 11pt;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .qr-code-image {
            margin: 20px auto;
            display: block;
            border: 2px solid #1e3a8a;
            background-color: #ffffff;
            padding: 10px;
        }
        .qr-code-instruction {
            margin-top: 15px;
            font-size: 9pt;
            color: #475569;
            font-style: italic;
        }
        .notice-box {
            border: 2px solid #1e3a8a;
            padding: 20px;
            margin: 30px 0;
            background: #f8fafc;
        }
        .notice-title {
            font-weight: bold;
            margin-bottom: 12px;
            font-size: 11pt;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .notice-content {
            font-size: 10pt;
            line-height: 1.7;
            color: #000000;
        }
        .notice-content p {
            margin-bottom: 10px;
        }
        .signature-section {
            margin-top: 60px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: center;
        }
        .signature-line {
            border-top: 2px solid #000000;
            margin: 80px auto 0 auto;
            padding-top: 10px;
            width: 300px;
            text-align: center;
        }
        .signature-name {
            font-weight: bold;
            font-size: 11pt;
            color: #000000;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .signature-role {
            font-size: 10pt;
            color: #475569;
            margin-bottom: 5px;
        }
        .signature-date {
            font-size: 9pt;
            color: #64748b;
        }
        .footer {
            margin-top: 50px;
            padding-top: 15px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 8pt;
            color: #64748b;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 9pt;
            font-weight: bold;
            background: #dbeafe;
            color: #1e40af;
            text-transform: uppercase;
            border: 1px solid #2563eb;
        }
        .approval-info-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 20px;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>
    <div class="document-wrapper">
    <!-- Letterhead -->
    <div class="letterhead">
        <div class="letterhead-main">REPUBLIC OF THE PHILIPPINES</div>
        <div class="letterhead-sub">NATIONAL IRRIGATION ADMINISTRATION</div>
        <div class="letterhead-region">Region XI</div>';
        
        // Add logo if available
        if (!empty($data['logo_base64'])) {
            $html .= '
        <img src="data:image/png;base64,' . $data['logo_base64'] . '" alt="NIA Logo" class="letterhead-logo" />';
        }
        
        $html .= '
    </div>
    
    <!-- Document Title -->
    <div class="document-title">
        <div class="document-title-main">Supply Request Approval Receipt</div>
        <div class="document-title-sub">Official Government Document</div>
    </div>
    
    <!-- Receipt Number -->
    <div class="receipt-number-section">
        <div class="receipt-number-label">Receipt Number</div>
        <div class="receipt-number-value">' . htmlspecialchars($data['request_number']) . '</div>
    </div>
    
    <!-- Approval Information -->
    <div class="approval-info-box">
        <table class="info-table">
            <tr class="info-row">
                <td class="info-label">Approval Date:</td>
                <td class="info-value">' . htmlspecialchars($data['approved_date']) . '</td>
            </tr>
            <tr class="info-row">
                <td class="info-label">Approval Time:</td>
                <td class="info-value">' . htmlspecialchars($data['approved_time']) . '</td>
            </tr>
            <tr class="info-row">
                <td class="info-label">Request Date:</td>
                <td class="info-value">' . htmlspecialchars($data['created_at']) . '</td>
            </tr>
            <tr class="info-row">
                <td class="info-label">Status:</td>
                <td class="info-value"><span class="status-badge">APPROVED</span></td>
            </tr>
        </table>
    </div>
    
    <!-- Request Details Section -->
    <div class="section">
        <div class="section-header">Request Information</div>
        <div class="section-content">
            <table class="info-table">';
        
        // Display all items if multiple, otherwise single item
        if (!empty($data['items']) && count($data['items']) > 1) {
            // Multiple items
            foreach ($data['items'] as $idx => $itemData) {
                $html .= '
                <tr class="info-row">
                    <td class="info-label">Item ' . ($idx + 1) . ':</td>
                    <td class="info-value"><strong>' . htmlspecialchars($itemData['item_name']) . '</strong></td>
                </tr>';
                
                if (!empty($itemData['item_description'])) {
                    $html .= '
                <tr class="info-row">
                    <td class="info-label">Description:</td>
                    <td class="info-value">' . htmlspecialchars($itemData['item_description']) . '</td>
                </tr>';
                }
                
                $html .= '
                <tr class="info-row">
                    <td class="info-label">Quantity:</td>
                    <td class="info-value"><strong>' . htmlspecialchars($itemData['quantity']) . '</strong></td>
                </tr>';
                
                if ($idx < count($data['items']) - 1) {
                    $html .= '
                <tr class="info-row">
                    <td colspan="2" style="padding: 10px 0; border-bottom: 1px dashed #cbd5e1;"></td>
                </tr>';
                }
            }
            
            $html .= '
                <tr class="info-row">
                    <td class="info-label">Total Quantity:</td>
                    <td class="info-value"><strong>' . htmlspecialchars($data['quantity']) . '</strong></td>
                </tr>';
        } else {
            // Single item (backward compatible)
            $html .= '
                <tr class="info-row">
                    <td class="info-label">Item Name:</td>
                    <td class="info-value"><strong>' . htmlspecialchars($data['item_name']) . '</strong></td>
                </tr>';
            
            if (!empty($data['item_description'])) {
                $html .= '
                <tr class="info-row">
                    <td class="info-label">Description:</td>
                    <td class="info-value">' . htmlspecialchars($data['item_description']) . '</td>
                </tr>';
            }
            
            $html .= '
                <tr class="info-row">
                    <td class="info-label">Quantity:</td>
                    <td class="info-value"><strong>' . htmlspecialchars($data['quantity']) . '</strong></td>
                </tr>';
        }
        
        if (!empty($data['notes'])) {
            $html .= '
                <tr class="info-row">
                    <td class="info-label">Notes:</td>
                    <td class="info-value">' . htmlspecialchars($data['notes']) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <!-- Two Column Layout for User and Approval Info -->
    <div class="two-column">
        <div class="column">
            <!-- Requesting User Information Section -->
            <div class="section">
                <div class="section-header">Requestor Information</div>
                <div class="section-content">
                    <table class="info-table">
                        <tr class="info-row">
                            <td class="info-label">Name:</td>
                            <td class="info-value"><strong>' . htmlspecialchars($data['requesting_user_name']) . '</strong></td>
                        </tr>';
        
        if (!empty($data['requesting_user_email'])) {
            $html .= '
                        <tr class="info-row">
                            <td class="info-label">Email:</td>
                            <td class="info-value">' . htmlspecialchars($data['requesting_user_email']) . '</td>
                        </tr>';
        }
        
        if (!empty($data['requesting_user_location'])) {
            $html .= '
                        <tr class="info-row">
                            <td class="info-label">Location:</td>
                            <td class="info-value">' . htmlspecialchars($data['requesting_user_location']) . '</td>
                        </tr>';
        }
        
        $html .= '
                    </table>
                </div>
            </div>
        </div>
        
        <div class="column">
            <!-- Approval Information Section -->
            <div class="section">
                <div class="section-header">Approval Information</div>
                <div class="section-content">
                    <table class="info-table">
                        <tr class="info-row">
                            <td class="info-label">Approved By:</td>
                            <td class="info-value"><strong>' . htmlspecialchars($data['approver_name']) . '</strong></td>
                        </tr>
                        <tr class="info-row">
                            <td class="info-label">Role:</td>
                            <td class="info-value">' . htmlspecialchars(ucfirst($data['approver_role'])) . '</td>
                        </tr>
                        <tr class="info-row">
                            <td class="info-label">Date:</td>
                            <td class="info-value">' . htmlspecialchars($data['approved_date']) . '</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <!-- QR Code Section -->
    <div class="qr-code-section">
        <div class="qr-code-title">Verification QR Code</div>
        <img src="data:image/png;base64,' . $data['qr_code_base64'] . '" alt="Receipt QR Code" class="qr-code-image" style="width: 160px; height: 160px;" />
        <div class="qr-code-instruction">Scan with mobile device to verify receipt authenticity and view details</div>
    </div>
    
    <!-- Important Notice -->
    <div class="notice-box">
        <div class="notice-title">Important Notice</div>
        <div class="notice-content">
            <p>This receipt serves as official proof of approval for your supply request. You must present this document or scan the QR code when picking up your requested items from the Supply Office.</p>
            <p style="margin-top: 12px;"><strong>Receipt Number:</strong> <span style="font-family: \'Courier New\', monospace; font-weight: bold;">' . htmlspecialchars($data['request_number']) . '</span></p>
        </div>
    </div>
    
    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <div class="signature-name">' . htmlspecialchars($data['approver_name']) . '</div>
                <div class="signature-role">' . htmlspecialchars(ucfirst($data['approver_role'])) . '</div>
                <div class="signature-date">' . htmlspecialchars($data['approved_date']) . '</div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <p><strong>COMPUTER-GENERATED DOCUMENT</strong> | No physical signature required</p>
        <p style="margin-top: 8px;">Document Generated: ' . date('F d, Y \a\t h:i A') . ' | System Reference: ' . htmlspecialchars($data['request_number']) . '</p>
    </div>
    </div>
</body>
</html>';
        
        return $html;
    }

    /**
     * Verify receipt by receipt number (for QR code scanning)
     */
    public function verifyReceipt($receiptNumber): JsonResponse
    {
        try {
            $supplyRequest = SupplyRequest::where('request_number', $receiptNumber)->first();
            
            if (!$supplyRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Receipt not found'
                ], 404);
            }

            // Load relationships
            $supplyRequest->load(['requestedBy']);
            $item = $supplyRequest->item();
            
            $itemName = $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A';
            $requestingUserName = $supplyRequest->requestedBy ? ($supplyRequest->requestedBy->fullname ?? $supplyRequest->requestedBy->username ?? 'Unknown') : 'Unknown';
            $approver = $supplyRequest->approver;
            $approverName = $approver ? ($approver->fullname ?? $approver->username ?? 'Admin') : 'Admin';

            return response()->json([
                'success' => true,
                'data' => [
                    'receipt_number' => $supplyRequest->request_number,
                    'status' => $supplyRequest->status,
                    'item_name' => $itemName,
                    'quantity' => $supplyRequest->quantity,
                    'requested_by' => $requestingUserName,
                    'approved_by' => $approverName,
                    'approved_at' => $supplyRequest->approved_at ? $supplyRequest->approved_at->format('F d, Y h:i A') : null,
                    'created_at' => $supplyRequest->created_at->format('F d, Y h:i A'),
                    'is_valid' => $supplyRequest->status === 'approved' || $supplyRequest->status === 'fulfilled',
                    'verification_date' => now()->format('F d, Y h:i A')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying receipt: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Automatically track usage when a supply request is fulfilled
     * 
     * @param Item $item
     * @param int $oldQuantity
     * @param int $newQuantity
     * @param int $fulfilledQuantity
     * @return void
     */
    private function trackUsageFromFulfillment(Item $item, int $oldQuantity, int $newQuantity, int $fulfilledQuantity): void
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
            
            // Update usage record - add the fulfilled quantity
            $usage->usage += $fulfilledQuantity;
            $usage->stock_end = $newQuantity;
            
            // Set stock_start if it's null (first usage in this period)
            if ($usage->stock_start === null) {
                $usage->stock_start = $oldQuantity;
            }
            
            $usage->save();
            
            Log::info("Automatically tracked usage for item {$item->id} ({$item->unit}) in {$period}: {$fulfilledQuantity} units from fulfilled supply request");
        } catch (\Exception $e) {
            Log::error("Failed to track usage from fulfillment for item {$item->id}: " . $e->getMessage());
            // Don't fail the fulfillment if tracking fails
        }
    }
}
