<?php

namespace App\Traits;

use App\Models\ActivityLog;
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

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => $action,
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
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
     */
    protected function logBorrowActivity(
        Request $request,
        string $action,
        ?string $itemName = null,
        ?int $quantity = null,
        ?string $borrowedBy = null
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
        
        if ($borrowedBy) {
            $descriptionParts[] = "by {$borrowedBy}";
        }
        
        $description = implode(' ', $descriptionParts);
        
        $this->logActivity($request, $action, $description);
    }
}
