<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LocationCollection;
use App\Http\Resources\V1\LocationResource;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        
        $locations = Location::orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json([
            'success' => true,
            'data' => new LocationCollection($locations->items()),
            'pagination' => [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
                'from' => $locations->firstItem(),
                'to' => $locations->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'location' => 'required|string|max:255|unique:locations,location',
            'personnel' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $location = Location::create([
                'location' => $request->location,
                'personnel' => $request->personnel
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location created successfully',
                'data' => new LocationResource($location)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $location = Location::find($id);
        
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LocationResource($location)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $location = Location::find($id);
        
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'location' => 'required|string|max:255|unique:locations,location,' . $id,
            'personnel' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $location->update([
                'location' => $request->location,
                'personnel' => $request->personnel
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => new LocationResource($location)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get locations associated with admin users.
     */
    public function getAdminLocations(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        
        // Get location IDs that have at least one admin user
        $adminLocationIds = User::where('role', 'admin')
            ->whereNotNull('location_id')
            ->distinct()
            ->pluck('location_id')
            ->toArray();
        
        // Get locations that have admin users
        $locations = Location::whereIn('id', $adminLocationIds)
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json([
            'success' => true,
            'data' => new LocationCollection($locations->items()),
            'pagination' => [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
                'from' => $locations->firstItem(),
                'to' => $locations->lastItem(),
            ]
        ]);
    }

    /**
     * Mark personnel as resigned (clear personnel from location)
     */
    public function markPersonnelAsResigned(Request $request, string $id)
    {
        $location = Location::find($id);
        
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        if (empty(trim($location->personnel ?? ''))) {
            return response()->json([
                'success' => false,
                'message' => 'Location does not have assigned personnel'
            ], 422);
        }

        // Check if personnel has pending items
        // Get items assigned to this specific location
        $pendingItems = \App\Models\Item::where('location_id', $location->id)
            ->whereHas('memorandumReceipts', function($query) {
                $query->where('status', 'ISSUED');
            })
            ->count();

        // Also check for items without MR records assigned to this location
        $itemsWithoutMr = \App\Models\Item::where('location_id', $location->id)
            ->whereDoesntHave('memorandumReceipts')
            ->count();

        $totalPending = $pendingItems + $itemsWithoutMr;

        if ($totalPending > 0) {
            return response()->json([
                'success' => false,
                'error' => 'PENDING_ITEMS',
                'message' => "Personnel has {$totalPending} pending item(s). Please complete clearance first.",
                'pending_items_count' => $totalPending
            ], 422);
        }

        try {
            // Clear personnel from location
            $oldPersonnel = $location->personnel;
            $location->update([
                'personnel' => null,
                'personnel_code' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => "Personnel '{$oldPersonnel}' marked as resigned successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark personnel as resigned',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $location = Location::find($id);
        
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        // Check if location is being used by items
        if ($location->item()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete location. It is being used by one or more items.',
                'items_count' => $location->item()->count()
            ], 422);
        }

        // Check if location is being used by users
        if ($location->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete location. It is being used by one or more users.',
                'users_count' => $location->users()->count()
            ], 422);
        }

        try {
            $location->delete();

            return response()->json([
                'success' => true,
                'message' => 'Location deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete location',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
