<?php

namespace App\Services\V1;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data): User
    {
        $img = $data['image'] ?? null;
        if($img === null){
            $imagePath = 'images/default.png';
        } else {
            $imagePath = $img->store('images', 'public');
        }
        
        return User::create([
            'fullname' => $data['fullname'],
            'username' => $data['email'], // Use email as username
            'email' => $data['email'],
            'location_id' => $data['location_id'],
            'role' => $data['role'],
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
            ActivityLog::create([
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? $request->userAgent() : null,
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the main flow
            \Log::error('Failed to log activity: ' . $e->getMessage());
        }
    }
}
