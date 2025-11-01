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
        return new UserCollection(User::all());
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
        
        // Get validated data
        $validatedData = $request->validated();
        
        // Check if password is being updated
        $passwordChanged = false;
        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
            // Remove password_confirmation from data to be saved
            unset($validatedData['password_confirmation']);
            $passwordChanged = true;
        }
        
        // Handle image upload if present
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            
            // Store new image
            $path = $request->file('image')->store('user_images', 'public');
            $validatedData['image'] = $path;
        }
        
        // Update user with validated data
        $user->update($validatedData);
        
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
        
        // Delete the user
        $user->delete();
        
        // Log user deletion
        $this->logUserActivity($request, 'Deleted', $user->fullname, $user->id);
        
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
