<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
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


    /**
     * Register a new user
     * 
     * @param RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        try {
            // Get validated data
            $validated = $request->validated();
            
            // Set username to email if not provided
            if (empty($validated['username'])) {
            $validated['username'] = $validated['email'];
            }
            
            // Set default role to 'user' if not provided
            if (empty($validated['role'])) {
                $validated['role'] = 'user';
            }
            
            // Register the user
            $user = $this->authService->register($validated);

            // Log user registration (only if admin is creating the user)
            if ($request->user()) {
                $this->logUserActivity($request, 'Created', $user->fullname, $user->id);
            } else {
                // Log public registration
                $this->logAuthActivity($request, 'User Registered', $user->email, true);
            }

            // Load relationships for resource
            $user->load('location');

            // Create token for the newly registered user
            $token = $user->createToken($user->username)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully.',
                'user' => new UserResource($user),
                'token' => $token,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Registration error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
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

        // Check for error responses (resigned/inactive users)
        if (isset($result['error'])) {
            $this->logAuthActivity($request, 'Login Attempt', $request->email, false);
            return response()->json([
                'message' => $result['message'],
                'error' => $result['error']
            ], 403);
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
