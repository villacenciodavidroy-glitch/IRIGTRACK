<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ActivityLog::with('user.location')
                ->orderBy('created_at', 'desc');

            // Apply search filter
            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
            }

            // Apply action filter
            if ($request->has('action') && !empty($request->action)) {
                $query->byAction($request->action);
            }

            // Apply user filter
            if ($request->has('user_id') && !empty($request->user_id)) {
                $query->byUser($request->user_id);
            }

            // Apply date range filter
            if ($request->has('start_date') && !empty($request->start_date)) {
                $endDate = $request->end_date ?? now()->endOfDay();
                $query->byDateRange($request->start_date, $endDate);
            }

            // Get pagination parameters
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);

            // Execute query with pagination
            $activityLogs = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform the data for frontend
            $transformedLogs = $activityLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'name' => $log->user->fullname,
                    'email' => $log->user->email,
                    'date' => $log->created_at->format('d-m-Y'),
                    'time' => $log->created_at->format('h:i A'),
                    'action' => $log->action,
                    'role' => ucfirst($log->user->role),
                    'description' => $log->description,
                    'ip_address' => $log->ip_address,
                    'location' => $log->user->location->name ?? 'N/A',
                    'created_at' => $log->created_at->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedLogs,
                'pagination' => [
                    'current_page' => $activityLogs->currentPage(),
                    'last_page' => $activityLogs->lastPage(),
                    'per_page' => $activityLogs->perPage(),
                    'total' => $activityLogs->total(),
                    'from' => $activityLogs->firstItem(),
                    'to' => $activityLogs->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch activity logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity log statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $totalLogs = ActivityLog::count();
            $todayLogs = ActivityLog::whereDate('created_at', today())->count();
            $loginCount = ActivityLog::where('action', 'Logged In')->count();
            $logoutCount = ActivityLog::where('action', 'Logged Out')->count();

            // Get recent activities (last 7 days)
            $recentActivities = ActivityLog::with('user')
                ->where('created_at', '>=', now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'name' => $log->user->fullname,
                        'action' => $log->action,
                        'date' => $log->created_at->format('d-m-Y'),
                        'time' => $log->created_at->format('h:i A'),
                        'role' => ucfirst($log->user->role)
                    ];
                });

            return response()->json([
                'success' => true,
                'statistics' => [
                    'total_logs' => $totalLogs,
                    'today_logs' => $todayLogs,
                    'login_count' => $loginCount,
                    'logout_count' => $logoutCount,
                ],
                'recent_activities' => $recentActivities
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new activity log entry
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'action' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'ip_address' => 'nullable|ip',
                'user_agent' => 'nullable|string|max:500'
            ]);

            $activityLog = ActivityLog::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Activity logged successfully',
                'data' => $activityLog
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to log activity: ' . $e->getMessage()
            ], 500);
        }
    }
}
