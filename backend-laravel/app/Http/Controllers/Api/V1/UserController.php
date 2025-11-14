<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateUserRequest;
use App\Http\Resources\V1\UserCollection;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    use LogsActivity;
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('location')->get();
        return new UserCollection($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('location')->findOrFail($id);
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);
        
        // Log incoming request data BEFORE validation
        // Try to get data from different sources to debug FormData parsing
        \Log::info('User update request', [
            'user_id' => $id,
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'all_request_data' => $request->all(),
            'input' => $request->input(),
            'get' => $request->query->all(),
            'post' => $request->request->all(),
            'has_fullname' => $request->has('fullname'),
            'has_username' => $request->has('username'),
            'has_email' => $request->has('email'),
            'has_role' => $request->has('role'),
            'has_location_id' => $request->has('location_id'),
            'files' => $request->allFiles(),
            'request_keys' => array_keys($request->all())
        ]);
        
        // For PUT requests with FormData, Laravel sometimes doesn't parse it correctly
        // Try to get data from request->request (POST data bag) if request->all() is empty
        $requestData = $request->all();
        if (empty($requestData) && !empty($request->request->all())) {
            \Log::info('FormData not in request->all(), trying request->request');
            // Manually merge request data from POST bag
            $postData = $request->request->all();
            foreach ($postData as $key => $value) {
                $request->merge([$key => $value]);
            }
            $requestData = $request->all();
            \Log::info('After manual merge', ['data' => $requestData]);
        }
        
        // Get validated data
        $validatedData = $request->validated();
        
        // Handle location_id - convert empty string to null
        if (isset($validatedData['location_id']) && ($validatedData['location_id'] === '' || $validatedData['location_id'] === null)) {
            $validatedData['location_id'] = null;
        }
        
        // Log validated data
        \Log::info('Validated data', ['validated' => $validatedData]);
        
        // Check if password is being updated
        $passwordChanged = false;
        if (isset($validatedData['password']) && !empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
            // Remove password_confirmation from data to be saved
            unset($validatedData['password_confirmation']);
            $passwordChanged = true;
        } else {
            // Remove password fields if not being updated
            unset($validatedData['password']);
            unset($validatedData['password_confirmation']);
        }
        
        // Handle image upload if present
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            
            // Store new image
            $path = $request->file('image')->store('images', 'public');
            $validatedData['image'] = $path;
        }
        
        // Log what will be updated
        \Log::info('Updating user with data', ['data' => $validatedData]);
        
        // Update user with validated data
        $user->update($validatedData);
        
        // Refresh to get updated data
        $user->refresh();
        
        // Log after update
        \Log::info('User updated', [
            'user_id' => $user->id,
            'fullname' => $user->fullname,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'location_id' => $user->location_id
        ]);
        
        // Log specific activity based on what was updated
        if ($passwordChanged) {
            // Log password reset specifically
            $currentUser = $request->user();
            $description = $currentUser && $currentUser->id === $user->id 
                ? "Password reset for their own account" 
                : "Password reset for user '{$user->fullname}' (ID: {$user->id})";
            $this->logActivity($request, 'Password Reset', $description);
        } else {
            // Log general user update
            $this->logUserActivity($request, 'Updated', $user->fullname, $user->id);
        }
        
        // Return the updated user
        return new UserResource($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, Request $request)
    {
        $user = User::findOrFail($id);
        
        // Check if user has associated items
        if ($user->items && $user->items->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete user with associated items. Please reassign or delete the items first.',
                'status' => 'error',
                'itemCount' => $user->items->count(),
                'userId' => $user->id
            ], 422);
        }
        
        // Store user info for logging before deletion
        $userFullname = $user->fullname;
        $userId = $user->id;
        
        // Permanently delete the user from the database
        $user->forceDelete();
        
        // Log user deletion
        $this->logUserActivity($request, 'Deleted', $userFullname, $userId);
        
        return response()->json([
            'message' => 'User deleted successfully',
            'status' => 'success'
        ], 200);
    }
    
    /**
     * Get all deleted users
     */
    public function getDeletedUsers()
    {
        $deletedUsers = User::onlyTrashed()
            ->with('location')
            ->orderBy('deleted_at', 'desc')
            ->get();

        return response()->json([
            'data' => $deletedUsers,
            'status' => 'success'
        ], 200);
    }

    /**
     * Restore a deleted user
     */
    public function restoreUser($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return response()->json([
            'message' => 'User restored successfully',
            'status' => 'success'
        ], 200);
    }

    /**
     * Permanently delete a user
     */
    public function forceDeleteUser($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->forceDelete();

        return response()->json([
            'message' => 'User permanently deleted successfully',
            'status' => 'success'
        ], 200);
    }
    
    /**
     * Reassign all items from one user to another
     */
    public function reassignItems(Request $request, string $id)
    {
        $request->validate([
            'new_user_id' => 'required|exists:users,id'
        ]);
        
        $user = User::findOrFail($id);
        $newUserId = $request->input('new_user_id');
        
        // Make sure we're not reassigning to the same user
        if ($id == $newUserId) {
            return response()->json([
                'message' => 'Cannot reassign items to the same user',
                'status' => 'error'
            ], 422);
        }
        
        // Get count of items before reassigning
        $itemCount = $user->items->count();
        
        // Reassign all items to the new user
        $user->items()->update(['user_id' => $newUserId]);
        
        return response()->json([
            'message' => $itemCount . ' items reassigned successfully',
            'status' => 'success'
        ], 200);
    }
}
