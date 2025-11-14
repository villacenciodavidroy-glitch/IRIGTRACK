<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ItemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ConditionController;
use App\Http\Controllers\Api\V1\ConditionNumberController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\UsageController;
use App\Http\Controllers\Api\V1\TransactionController;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to the API!',
        'status' => 'success'
    ]);
});

// Add auth routes outside v1 group for frontend compatibility
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Add other routes outside v1 group for frontend compatibility
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public read-only routes (no auth required)
Route::get('/conditions', [ConditionController::class, 'index']);
Route::get('/condition_numbers', [ConditionNumberController::class, 'index']);

// Public QR code scanning endpoint (no auth required for scanning)
Route::get('/items/check/{uuid}', [ItemController::class, 'checkItem']);

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    // Users - admin only for management, authenticated users can view
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/deleted', [UserController::class, 'getDeletedUsers'])->middleware('admin');
    Route::post('/users/{id}/restore', [UserController::class, 'restoreUser'])->middleware('admin');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('admin');
    Route::delete('/users/{id}/force-delete', [UserController::class, 'forceDeleteUser'])->middleware('admin');
    Route::post('/users/{id}/reassign-items', [UserController::class, 'reassignItems'])->middleware('admin');
    
    // Locations - admin only for write operations
    Route::get('/locations', [LocationController::class, 'index']);
    Route::post('/locations', [LocationController::class, 'store'])->middleware('admin');
    Route::put('/locations/{id}', [LocationController::class, 'update'])->middleware('admin');
    Route::delete('/locations/{id}', [LocationController::class, 'destroy'])->middleware('admin');
    
    // Categories - admin only for write operations
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('admin');
    Route::put('/categories/{id}', [CategoryController::class, 'update'])->middleware('admin');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->middleware('admin');
    
    // Items - authenticated users can view, admin for management
    Route::get('/items/active', [ItemController::class, 'getActiveItems']);
    Route::get('/items/deleted', [ItemController::class, 'getDeletedItems'])->middleware('admin');
    Route::post('/items/restore/{uuid}', [ItemController::class, 'restoreItem'])->middleware('admin');
    Route::delete('/items/force-delete/{uuid}', [ItemController::class, 'forceDelete'])->middleware('admin');
    Route::post('/items', [ItemController::class, 'store']);
    Route::post('/items/{uuid}/validate-qr', [ItemController::class, 'validateQRCode']);
    
    // Activity logs - admin only
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('admin');
    Route::get('/activity-logs/statistics', [ActivityLogController::class, 'statistics'])->middleware('admin');
    Route::post('/activity-logs', [ActivityLogController::class, 'store']);
    
    // Notifications - authenticated users
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/statistics', [NotificationController::class, 'statistics']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::post('/notifications/delete-multiple', [NotificationController::class, 'deleteMultiple']);
    
    // Usage analytics - authenticated users
    Route::get('/usage/quarterly', [UsageController::class, 'getQuarterlyUsage']);
    Route::get('/usage/forecast-data', [UsageController::class, 'getForecastData']);
    
    // Exports - authenticated users
    Route::get('/items/export/monitoring-assets', [ItemController::class, 'exportMonitoringAssets']);
    Route::get('/items/export/serviceable-items', [ItemController::class, 'exportServiceableItems']);
    Route::get('/items/export/life-cycles-data', [ItemController::class, 'exportLifeCyclesData']);
    
    // Transactions - admin only
    Route::get('/transactions', [TransactionController::class, 'index'])->middleware('admin');
});

Route::group(['prefix'=>'v1', 'namespace' => 'App\Http\Controllers\Api\V1'], function() {

    // AUTHENTICATION
    Route::post('/register', [AuthController::class, 'register']);
    // Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

    // Public QR code scanning endpoints (no auth required)
    Route::get('items/check/{uuid}', [ItemController::class, 'checkItem']);
    // Public route for QR code scanning - allows accessing item details via UUID
    Route::get('items/{item}', [ItemController::class, 'show'])->where('item', '[a-f0-9\-]{36}');
    // Public borrow endpoint for mobile apps (QR code scanning and borrowing)
    // Note: If Bearer token is provided in Authorization header, user will be authenticated
    Route::post('items/{uuid}/borrow', [ItemController::class, 'borrowItem']);

    // Borrow request routes (public - no auth required for creating requests)
    Route::post('items/{id}/borrow-request', [ItemController::class, 'createBorrowRequest'])->where('id', '[0-9a-fA-F\-]+');
    Route::get('items/{id}/borrow-requests', [ItemController::class, 'getBorrowRequests'])->where('id', '[0-9a-fA-F\-]+');

    // Protected routes - require authentication
    Route::middleware('auth:sanctum')->group(function () {
        // USER PROFILE
        Route::put('/profile', [AuthController::class, 'updateProfile']);

        // ITEMS - Specific routes first
        Route::get('items/active', [ItemController::class, 'getActiveItems']);
        Route::get('items/deleted', [ItemController::class, 'getDeletedItems'])->middleware('admin');
        Route::post('items/restore/{uuid}', [ItemController::class, 'restoreItem'])->middleware('admin');
        Route::post('items/{uuid}/validate-qr', [ItemController::class, 'validateQRCode']);
        Route::delete('items/delete/{uuid}', [ItemController::class, 'destroy']);
        Route::delete('items/delete-by-id/{id}', [ItemController::class, 'destroy']);
        Route::delete('items/force-delete/{uuid}', [ItemController::class, 'forceDelete'])->middleware('admin');
        
        // General resource routes last (excluding show since it's public for QR codes)
        Route::apiResource('items', ItemController::class)->except(['show']);
        Route::post('items/update-lifespan-predictions', [ItemController::class, 'updateLifespanPredictions']);

        // Locations - admin for write operations
        Route::get('locations', [LocationController::class, 'index']);
        Route::post('locations', [LocationController::class, 'store'])->middleware('admin');
        Route::put('locations/{id}', [LocationController::class, 'update'])->middleware('admin');
        Route::delete('locations/{id}', [LocationController::class, 'destroy'])->middleware('admin');

        // Users - admin only for management
        Route::get('users/deleted', [UserController::class, 'getDeletedUsers'])->middleware('admin');
        Route::post('users/{id}/restore', [UserController::class, 'restoreUser'])->middleware('admin');
        Route::delete('users/{id}/force-delete', [UserController::class, 'forceDeleteUser'])->middleware('admin');
        Route::post('users/{id}/reassign-items', [UserController::class, 'reassignItems'])->middleware('admin');
        // Custom POST route for FormData updates (PUT with FormData doesn't parse correctly in Laravel)
        Route::post('users/{id}/update', [UserController::class, 'update'])->middleware('admin');
        Route::get('users', [UserController::class, 'index']);
        Route::apiResource('users', UserController::class)->except(['index'])->middleware('admin');

        // Categories - admin for write operations
        Route::get('categories', [CategoryController::class, 'index']);
        Route::post('categories', [CategoryController::class, 'store'])->middleware('admin');
        Route::put('categories/{id}', [CategoryController::class, 'update'])->middleware('admin');
        Route::delete('categories/{id}', [CategoryController::class, 'destroy'])->middleware('admin');

        // Conditions - read-only for authenticated users
        Route::apiResource('conditions', ConditionController::class);
        Route::apiResource('condition_numbers', ConditionNumberController::class);

        // ACTIVITY LOGS - admin only
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('admin');
        Route::get('activity-logs/statistics', [ActivityLogController::class, 'statistics'])->middleware('admin');
        Route::post('activity-logs', [ActivityLogController::class, 'store']);

        // NOTIFICATIONS - authenticated users
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/statistics', [NotificationController::class, 'statistics']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
        Route::post('notifications/delete-multiple', [NotificationController::class, 'deleteMultiple']);

        // USAGE ANALYTICS - authenticated users
        Route::get('usage/quarterly', [UsageController::class, 'getQuarterlyUsage']);
        Route::get('usage/forecast-data', [UsageController::class, 'getForecastData']);

        // ITEM EXPORTS - authenticated users
        Route::get('items/export/monitoring-assets', [ItemController::class, 'exportMonitoringAssets']);
        Route::get('items/export/serviceable-items', [ItemController::class, 'exportServiceableItems']);
        Route::get('items/export/life-cycles-data', [ItemController::class, 'exportLifeCyclesData']);

        // BORROW REQUEST MANAGEMENT - admin only
        Route::post('items/{itemId}/borrow-request/{requestId}/approve', [ItemController::class, 'approveBorrowRequest'])->where(['itemId' => '[0-9a-fA-F\-]+', 'requestId' => '[0-9]+'])->middleware('admin');
        Route::post('items/{itemId}/borrow-request/{requestId}/reject', [ItemController::class, 'rejectBorrowRequest'])->where(['itemId' => '[0-9a-fA-F\-]+', 'requestId' => '[0-9]+'])->middleware('admin');
        Route::get('admin/borrow-requests', [ItemController::class, 'getAllBorrowRequests'])->middleware('admin');
        
        // TRANSACTIONS - admin only
        Route::get('transactions', [TransactionController::class, 'index'])->middleware('admin');
    });

});