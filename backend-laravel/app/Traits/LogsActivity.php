<?php

namespace App\Traits;

use App\Models\ActivityLog;
use App\Events\ActivityLogCreated;
use Illuminate\Http\Request;

trait LogsActivity
{
    /**
     * Log user activity
     */
    protected function logActivity(
        Request $request,
        string $action,
        ?string $description = null,
        array $additionalData = []
    ): void {
        try {
            $user = $request->user();
            
            if (!$user) {
                return; // Don't log if no authenticated user
            }

            $activityLog = ActivityLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Broadcast event for real-time updates
            try {
                event(new ActivityLogCreated($activityLog));
            } catch (\Exception $e) {
                \Log::warning("Failed to broadcast ActivityLogCreated event: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            // Log the error but don't break the main functionality
            \Log::error('Failed to log activity: ' . $e->getMessage());
        }
    }

    /**
     * Log item-related activities
     */
    protected function logItemActivity(
        Request $request,
        string $action,
        ?string $itemName = null,
        ?string $itemUuid = null,
        array $additionalData = []
    ): void {
        $description = $itemName 
            ? "Item '{$itemName}' was {$action}" 
            : "Item was {$action}";
            
        if ($itemUuid) {
            $description .= " (UUID: {$itemUuid})";
        }

        $this->logActivity($request, $action, $description, $additionalData);
    }

    /**
     * Log user management activities
     */
    protected function logUserActivity(
        Request $request,
        string $action,
        ?string $targetUserName = null,
        ?int $targetUserId = null,
        array $additionalData = []
    ): void {
        $description = $targetUserName 
            ? "User '{$targetUserName}' was {$action}" 
            : "User was {$action}";
            
        if ($targetUserId) {
            $description .= " (ID: {$targetUserId})";
        }

        $this->logActivity($request, $action, $description, $additionalData);
    }

    /**
     * Log authentication activities
     */
    protected function logAuthActivity(
        Request $request,
        string $action,
        ?string $email = null,
        bool $success = true
    ): void {
        $status = $success ? 'successfully' : 'unsuccessfully';
        $description = $email 
            ? "User '{$email}' {$action} {$status}" 
            : "User {$action} {$status}";

        $this->logActivity($request, $action, $description);
    }

    /**
     * Log borrow transaction activities
     * Always uses authenticated user if available (logged-in user)
     * Uses personnel from item location in description
     */
    protected function logBorrowActivity(
        Request $request,
        string $action,
        ?string $itemName = null,
        ?int $quantity = null,
        ?string $personnel = null,
        ?\App\Models\User $authenticatedUser = null
    ): void {
        $descriptionParts = [];
        
        // Start with action (e.g., "Borrowed")
        $descriptionParts[] = $action;
        
        if ($itemName) {
            $descriptionParts[] = "Item '{$itemName}'";
        } else {
            $descriptionParts[] = 'Item';
        }
        
        if ($quantity !== null) {
            $descriptionParts[] = "with quantity of {$quantity}";
        }
        
        if ($personnel) {
            $descriptionParts[] = "by {$personnel}";
        }
        
        $description = implode(' ', $descriptionParts);
        
        // ALWAYS prioritize authenticated user passed explicitly, then check request
        // This ensures we use the actual logged-in account data (ABCD user account), not dummy/fallback data
        $user = $authenticatedUser ?: $request->user();
        
        // Debug log to help identify authentication issues
        if (!$user) {
            \Log::info('Borrow activity: No authenticated user found. Token present: ' . ($request->bearerToken() ? 'Yes' : 'No'));
        } else {
            \Log::info('Borrow activity: Authenticated user found - ' . $user->fullname . ' (ID: ' . $user->id . ', Role: ' . $user->role . ')');
        }
        
        // Only if no authenticated user exists, try to find by personnel name (as fallback)
        // This handles cases where mobile app borrows without authentication token
        if (!$user && $personnel) {
            // Try to find user by fullname, username, or email matching personnel
            $user = \App\Models\User::where(function($query) use ($personnel) {
                $query->where('fullname', $personnel)
                    ->orWhere('username', $personnel)
                    ->orWhere('email', $personnel);
            })->first();
            
            if ($user) {
                \Log::info('Borrow activity: Found user by personnel name - ' . $user->fullname . ' (ID: ' . $user->id . ')');
            }
        }
        
        // Create activity log - use authenticated user's ID if available
        try {
            $activityLog = ActivityLog::create([
                'user_id' => $user ? $user->id : null,
                'action' => $action,
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            \Log::info('Borrow activity logged - User ID: ' . ($user ? $user->id : 'null') . ', Description: ' . $description);

            // Broadcast event for real-time updates
            try {
                event(new ActivityLogCreated($activityLog));
            } catch (\Exception $e) {
                \Log::warning("Failed to broadcast ActivityLogCreated event: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            // Log the error but don't break the main functionality
            \Log::error('Failed to log borrow activity: ' . $e->getMessage());
        }
    }
}
