<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'success' => false
            ], 401);
        }

        // Check if user is admin
        $role = strtolower($user->role ?? '');
        if (!in_array($role, ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
                'success' => false
            ], 403);
        }

        return $next($request);
    }
}
