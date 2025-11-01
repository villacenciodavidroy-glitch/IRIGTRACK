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
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/deleted', [UserController::class, 'getDeletedUsers']);
Route::post('/users/{id}/restore', [UserController::class, 'restoreUser']);
Route::delete('/users/{id}/force-delete', [UserController::class, 'forceDeleteUser']);
Route::get('/locations', [LocationController::class, 'index']);
Route::get('/conditions', [ConditionController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/condition_numbers', [ConditionNumberController::class, 'index']);
Route::get('/items/active', [ItemController::class, 'getActiveItems']);
Route::get('/items/deleted', [ItemController::class, 'getDeletedItems']);
Route::post('/items/restore/{uuid}', [ItemController::class, 'restoreItem']);
Route::delete('/items/force-delete/{uuid}', [ItemController::class, 'forceDelete']);
Route::post('/items', [ItemController::class, 'store']);
Route::get('/activity-logs', [ActivityLogController::class, 'index']);
Route::get('/activity-logs/statistics', [ActivityLogController::class, 'statistics']);
Route::post('/activity-logs', [ActivityLogController::class, 'store']);
Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/statistics', [NotificationController::class, 'statistics']);
Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

// QR Code Validation endpoint outside v1 group
Route::post('/items/{uuid}/validate-qr', [ItemController::class, 'validateQRCode']);

Route::group(['prefix'=>'v1', 'namespace' => 'App\Http\Controllers\Api\V1'], function() {

    // AUTHENTICATION
    Route::post('/register', [AuthController::class, 'register']);
    // Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

    // USER PROFILE
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // ITEMS - Specific routes first
    Route::get('items/active', [ItemController::class, 'getActiveItems']);
    Route::get('items/deleted', [ItemController::class, 'getDeletedItems']);
    Route::get('items/check/{uuid}', [ItemController::class, 'checkItem']);
    Route::post('items/restore/{uuid}', [ItemController::class, 'restoreItem']);
    Route::post('items/{uuid}/validate-qr', [ItemController::class, 'validateQRCode']);
    Route::delete('items/delete/{uuid}', [ItemController::class, 'destroy']);
    Route::delete('items/delete-by-id/{id}', [ItemController::class, 'destroy']);
    Route::delete('items/force-delete/{uuid}', [ItemController::class, 'forceDelete']);
    
    // General resource routes last
    Route::apiResource('items', ItemController::class);
    Route::post('items/{uuid}/borrow', [ItemController::class, 'borrowItem']);

    Route::apiResource('locations', LocationController::class);

    Route::get('users/deleted', [UserController::class, 'getDeletedUsers']);
    Route::post('users/{id}/restore', [UserController::class, 'restoreUser']);
    Route::delete('users/{id}/force-delete', [UserController::class, 'forceDeleteUser']);
    Route::post('users/{id}/reassign-items', [UserController::class, 'reassignItems']);
    Route::apiResource('users', UserController::class);

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('conditions', ConditionController::class);
    Route::apiResource('condition_numbers', ConditionNumberController::class);

    // ACTIVITY LOGS
    Route::get('activity-logs', [ActivityLogController::class, 'index']);
    Route::get('activity-logs/statistics', [ActivityLogController::class, 'statistics']);
    Route::post('activity-logs', [ActivityLogController::class, 'store']);

    // NOTIFICATIONS
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/statistics', [NotificationController::class, 'statistics']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']);

});