<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Http\Resources\V1\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications with pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $role = $user ? strtolower($user->role ?? '') : '';
            
            $query = Notification::with([
                'item.category',
                'item.location',
                'item.condition',
                'borrowRequest'
            ])
                ->orderBy('created_at', 'desc');

            // Filter notifications by user role and type
            // For User Account: show only approval/rejection notifications for their requests
            if ($role === 'user') {
                $query->whereIn('type', [
                    'supply_request_approved',
                    'supply_request_admin_approved',
                    'supply_request_rejected',
                    'supply_request_admin_rejected',
                    'supply_request_ready_pickup'
                ]);
            }
            // For Supply Account: show new requests and approval/rejection notifications
            elseif ($role === 'supply') {
                $query->whereIn('type', [
                    'supply_request_created',
                    'supply_request_approved',
                    'supply_request_admin_approved',
                    'supply_request_rejected',
                    'supply_request_admin_rejected',
                    'supply_request_ready_for_pickup',
                    'supply_request_ready_pickup'
                ])->where(function($q) use ($user) {
                    // Show notifications without user_id (for all supply accounts) OR notifications for this specific supply account user
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $user->id);
                });
            }
            // For Admin: show all notifications EXCEPT user-specific ones (unless they're for this admin)
            elseif (in_array($role, ['admin', 'super_admin'])) {
                $query->where(function($q) use ($user) {
                    // Show notifications without user_id (for all admins) OR notifications specifically for this admin
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $user->id);
                });
            }

            // Apply search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('message', 'like', "%{$search}%")
                      ->orWhereHas('item', function($itemQuery) use ($search) {
                          $itemQuery->where('unit', 'like', "%{$search}%")
                                   ->orWhere('description', 'like', "%{$search}%");
                      });
                });
            }

            // Get pagination parameters
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);

            // Execute query with pagination
            $notifications = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => NotificationResource::collection($notifications),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'from' => $notifications->firstItem(),
                    'to' => $notifications->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead($id): JsonResponse
    {
        try {
            $notification = Notification::find($id);
            
            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => new NotificationResource($notification)
            ]);
        } catch (\Exception $e) {
            \Log::error('Error marking notification as read: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $role = $user ? strtolower($user->role ?? '') : '';
            
            $query = Notification::where('is_read', false);
            
            // Filter notifications by user role and type
            // For User Account: show only approval/rejection notifications for their requests
            if ($role === 'user') {
                $query->whereIn('type', [
                    'supply_request_approved',
                    'supply_request_admin_approved',
                    'supply_request_rejected',
                    'supply_request_admin_rejected',
                    'supply_request_ready_pickup'
                ]);
            }
            // For Supply Account: show new requests and approval/rejection notifications
            elseif ($role === 'supply') {
                $query->whereIn('type', [
                    'supply_request_created',
                    'supply_request_approved',
                    'supply_request_admin_approved',
                    'supply_request_rejected',
                    'supply_request_admin_rejected',
                    'supply_request_ready_for_pickup',
                    'supply_request_ready_pickup'
                ])->where(function($q) use ($user) {
                    // Show notifications without user_id (for all supply accounts) OR notifications for this specific supply account user
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $user->id);
                });
            }
            // For Admin: count unread notifications EXCEPT user-specific ones (unless they're for this admin)
            elseif (in_array($role, ['admin', 'super_admin'])) {
                $query->where(function($q) use ($user) {
                    // Count notifications without user_id (for all admins) OR notifications specifically for this admin
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $user->id);
                });
            }
            
            $count = $query->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching unread count: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unread count',
                'count' => 0
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy($id): JsonResponse
    {
        try {
            $notification = Notification::find($id);
            
            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error deleting notification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete multiple notifications
     */
    public function deleteMultiple(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|integer|exists:notifications,notification_id'
            ]);

            $deletedCount = Notification::whereIn('notification_id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} notification(s) deleted successfully",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error deleting multiple notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $total = Notification::count();
            $unread = Notification::where('is_read', false)->count();
            $today = Notification::whereDate('created_at', today())->count();
            $thisWeek = Notification::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count();
            $thisMonth = Notification::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'unread' => $unread,
                    'today' => $today,
                    'this_week' => $thisWeek,
                    'this_month' => $thisMonth,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching notification statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification statistics'
            ], 500);
        }
    }
}
