<?php

namespace App\Services\V1;

use App\Models\User;
use App\Models\ActivityLog;
use App\Events\ActivityLogCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data): User
    {
        // Handle image upload
        $imagePath = 'images/default.png'; // Default image
        if (isset($data['image']) && $data['image'] !== null) {
            try {
                $imagePath = $data['image']->store('images', 'public');
            } catch (\Exception $e) {
                \Log::warning('Image upload failed during registration: ' . $e->getMessage());
                // Continue with default image if upload fails
            }
        }
        
        // Use username if provided, otherwise use email
        $username = $data['username'] ?? $data['email'];
        
        // Set default role if not provided
        $role = $data['role'] ?? 'user';
        
        return User::create([
            'fullname' => $data['fullname'],
            'username' => $username,
            'email' => $data['email'],
            'location_id' => $data['location_id'],
            'role' => $role,
            'image' => $imagePath,
            'password' => Hash::make($data['password']),
        ]);
    }

    public function login(array $credentials, Request $request = null)
{
    $user = User::where('email', $credentials['email'])->first();

    if (!$user || !Hash::check($credentials['password'], $user->password)) {
        return false;
    }

    // Block resigned users from logging in
    if ($user->status === 'RESIGNED') {
        return [
            'error' => 'RESIGNED',
            'message' => 'This account has been resigned and cannot log in.'
        ];
    }

    // Block inactive users from logging in
    if ($user->status === 'INACTIVE') {
        return [
            'error' => 'INACTIVE',
            'message' => 'This account is inactive and cannot log in.'
        ];
    }

    $token = $user->createToken($user->username);

    // Log the login activity
    $this->logActivity($user->id, 'Logged In', 'User successfully logged in', $request);

    return [
        "message" => "You are successfully login!",
        'user' => [
            'id' => $user->id,
            'fullname' => $user->fullname,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'image' => $user->image,
            'location' => $user->location,
            'user_code' => $user->user_code,
            'status' => $user->status,
        ],
        'token' => $token->plainTextToken,
    ];
}

    public function logout(Request $request)
    {
         $user = $request->user();

         // Log the logout activity
         $this->logActivity($user->id, 'Logged Out', 'User successfully logged out', $request);

         $user->currentAccessToken()->delete();

         return;
    }

    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $description = null, Request $request = null)
    {
        try {
            $activityLog = ActivityLog::create([
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? $request->userAgent() : null,
            ]);

            // Broadcast event for real-time updates
            try {
                event(new ActivityLogCreated($activityLog));
            } catch (\Exception $e) {
                \Log::warning("Failed to broadcast ActivityLogCreated event: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            // Log error but don't break the main flow
            \Log::error('Failed to log activity: ' . $e->getMessage());
        }
    }
}
