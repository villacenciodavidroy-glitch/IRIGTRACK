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
            $query = Notification::with('item')
                ->orderBy('created_at', 'desc');

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
    public function unreadCount(): JsonResponse
    {
        try {
            $count = Notification::where('is_read', false)->count();

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
