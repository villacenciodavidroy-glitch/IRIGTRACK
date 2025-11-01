<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Services\V1\AuthService;
use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use LogsActivity;
    
    protected $authService;

    public function __construct(AuthService $authService)
    {
        // Calls automatically
        $this->authService = $authService;

    }


    public function register(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'fullname'     => 'required|string|max:255',
                'email'    => 'required|string|email|max:255|unique:users',
                'location_id' => 'required|exists:locations,id',
                'password' => 'required|string|min:8|confirmed',
                'role'     => 'in:admin,user',
                'image' =>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5120'
            ]);
            
            // Add username to validated data (use email as username)
            $validated['username'] = $validated['email'];
            
            $user = $this->authService->register($validated);

            // Log user registration (only if user is authenticated)
            if ($request->user()) {
                $this->logUserActivity($request, 'Created', $user->fullname, $user->id);
            }

            return response()->json([
                'message' => 'New user registered successfully.',
                'user' => $user,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    
    public function login(Request $request)
    {
        
        $result = $this->authService->login($request->only('email', 'password'), $request);

        if (!$result) {
            // Log failed login attempt
            $this->logAuthActivity($request, 'Login Attempt', $request->email, false);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Log successful login
        $this->logAuthActivity($request, 'Logged In', $request->email, true);

        return response()->json($result, 200);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function logout(Request $request)
    {
        // Log logout before actually logging out (to capture user info)
        $user = $request->user();
        if ($user) {
            $this->logAuthActivity($request, 'Logged Out', $user->email, true);
        }
        
        $this->authService->logout($request);

        return response()->json(['message' => 'You are Logged out']);
    }
}
