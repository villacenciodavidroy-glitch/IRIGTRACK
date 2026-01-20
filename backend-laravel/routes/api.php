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
use App\Http\Controllers\Api\V1\MaintenanceRecordController;
use App\Http\Controllers\Api\V1\SupplyRequestController;
use App\Http\Controllers\Api\V1\MemorandumReceiptController;

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
    Route::post('/users/{id}/mark-resigned', [UserController::class, 'markAsResigned'])->middleware('admin');
    Route::get('/users/pending-items', [UserController::class, 'getUsersWithPendingItems'])->middleware('admin');
    Route::get('/users/resigned-pending-items', [UserController::class, 'getResignedUsersWithPendingItems'])->middleware('admin');
    
    // Memorandum Receipts (MR) - admin only
    Route::get('/memorandum-receipts', [MemorandumReceiptController::class, 'index'])->middleware('admin');
    Route::get('/memorandum-receipts/user/{userId}/pending', [MemorandumReceiptController::class, 'getPendingItems'])->middleware('admin');
    Route::get('/memorandum-receipts/user/{userId}/all', [MemorandumReceiptController::class, 'getAllIssuedItemsForUser'])->middleware('admin');
    Route::get('/memorandum-receipts/personnel/{locationId}/pending', [MemorandumReceiptController::class, 'getPendingItemsForPersonnel'])->middleware('admin');
    Route::get('/memorandum-receipts/personnel/{locationId}/all', [MemorandumReceiptController::class, 'getAllIssuedItemsForPersonnel'])->middleware('admin');
    Route::post('/memorandum-receipts/{mrId}/return', [MemorandumReceiptController::class, 'returnItem'])->middleware('admin');
    Route::post('/memorandum-receipts/{mrId}/reassign', [MemorandumReceiptController::class, 'reassignItem'])->middleware('admin');
    Route::post('/memorandum-receipts/{mrId}/lost-damaged', [MemorandumReceiptController::class, 'markAsLostOrDamaged'])->middleware('admin');
    Route::post('/memorandum-receipts/{mrId}/recover', [MemorandumReceiptController::class, 'recoverItem'])->middleware('admin');
    Route::post('/memorandum-receipts/user/{userId}/bulk-return', [MemorandumReceiptController::class, 'bulkReturnItems'])->middleware('admin');
    Route::post('/memorandum-receipts/user/{userId}/bulk-reassign', [MemorandumReceiptController::class, 'bulkReassignItems'])->middleware('admin');
    Route::post('/memorandum-receipts/formalize/user/{userId}', [MemorandumReceiptController::class, 'formalizeItemsForUser'])->middleware('admin');
    Route::get('/memorandum-receipts/audit-trail/{userCode}', [MemorandumReceiptController::class, 'getAuditTrailByUserCode'])->middleware('admin');
    Route::get('/memorandum-receipts/accountability-report/user/{userId}', [MemorandumReceiptController::class, 'getAccountabilityReport'])->middleware('admin');
    Route::post('/memorandum-receipts/clearance/user/{userId}', [MemorandumReceiptController::class, 'processClearanceWithStatus'])->middleware('admin');
    Route::get('/memorandum-receipts/returned/available-for-reissue', [MemorandumReceiptController::class, 'getReturnedItemsAvailableForReissue'])->middleware('admin');
    Route::post('/memorandum-receipts/{mrId}/reissue', [MemorandumReceiptController::class, 'reissueItem'])->middleware('admin');
    
    // Memorandum Receipts (MR) - authenticated users can view their own items
    Route::get('/memorandum-receipts/my-items', [MemorandumReceiptController::class, 'getMyIssuedItems']);
    
    // Memorandum Receipts (MR) - authenticated users can return lost items (mobile app)
    Route::post('/memorandum-receipts/return-lost-item', [MemorandumReceiptController::class, 'returnLostItem']);
    
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
    Route::get('/items/generate-serial-number', [ItemController::class, 'generateSerialNumber']);
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
    Route::get('/usage/monthly', [UsageController::class, 'getMonthlyUsage']);
    Route::get('/usage/ranking', [UsageController::class, 'getSupplyUsageRanking']);
    Route::get('/usage/ranking/export-excel', [UsageController::class, 'exportSupplyRankingExcel']);
    Route::get('/usage/ranking/export-pdf', [UsageController::class, 'exportSupplyRankingPDF']);
    Route::get('/usage/user-supply-usage', [UsageController::class, 'getUserSupplyUsage']);
    Route::get('/usage/item-usage-by-user', [UsageController::class, 'getSupplyItemUsageByUser']);
    Route::get('/usage/forecast-data', [UsageController::class, 'getForecastData']);
    Route::post('/usage', [UsageController::class, 'store'])->middleware('admin');
    Route::post('/usage/bulk', [UsageController::class, 'bulkStore'])->middleware('admin');
    
    // Exports - authenticated users
    Route::get('/items/export/monitoring-assets', [ItemController::class, 'exportMonitoringAssets']);
    Route::match(['get', 'post'], '/items/export/monitoring-assets-pdf', [ItemController::class, 'exportMonitoringAssetsPdf']);
    Route::get('/items/export/serviceable-items', [ItemController::class, 'exportServiceableItems']);
    Route::match(['get', 'post'], '/items/export/serviceable-items-pdf', [ItemController::class, 'exportServiceableItemsPdf']);
    Route::get('/items/export/life-cycles-data', [ItemController::class, 'exportLifeCyclesData']);
    Route::match(['get', 'post'], '/items/export/life-cycles-data-pdf', [ItemController::class, 'exportLifeCyclesDataPdf']);
    
    // Transactions - admin or supply accounts
    Route::get('/transactions', [TransactionController::class, 'index'])->middleware('admin_or_supply');
    Route::get('/transactions/export', [TransactionController::class, 'exportTransactions'])->middleware('admin_or_supply');
    Route::get('/transactions/export-pdf', [TransactionController::class, 'exportPdf'])->middleware('admin_or_supply');
    
    // Maintenance Records - authenticated users
    Route::get('/maintenance-records', [MaintenanceRecordController::class, 'index']);
    Route::get('/maintenance-records/export', [MaintenanceRecordController::class, 'export']);
    Route::get('/maintenance-records/export-pdf', [MaintenanceRecordController::class, 'exportPdf']);
    
    // Supply Requests - User role
    Route::get('/supply-requests/available-supplies', [SupplyRequestController::class, 'getAvailableSupplies']);
    Route::post('/supply-requests', [SupplyRequestController::class, 'store']);
    Route::get('/supply-requests/my-requests', [SupplyRequestController::class, 'getUserRequests']);
    Route::delete('/supply-requests/{id}/cancel', [SupplyRequestController::class, 'cancelRequest']);
    
    // Supply Requests - Supply role
    Route::get('/supply-requests/all', [SupplyRequestController::class, 'getAllRequests'])->middleware('admin_or_supply');
    Route::post('/supply-requests/{id}/approve', [SupplyRequestController::class, 'approveRequest'])->middleware('admin_or_supply');
    Route::post('/supply-requests/{id}/reject', [SupplyRequestController::class, 'rejectRequest'])->middleware('admin_or_supply');
    Route::post('/supply-requests/{id}/forward', [SupplyRequestController::class, 'forwardToAdmin'])->middleware('admin_or_supply');
    Route::get('/supply-requests/stock-overview', [SupplyRequestController::class, 'getStockOverview'])->middleware('admin_or_supply');
    Route::get('/supply-requests/{id}/receipt', [SupplyRequestController::class, 'downloadReceipt']);
    Route::get('/supply-requests/unit-section-statistics', [SupplyRequestController::class, 'getUnitSectionStatistics'])->middleware('admin_or_supply');
    
    // Supply Requests - Admin assignment and acceptance
    Route::post('/supply-requests/{id}/assign-admin', [SupplyRequestController::class, 'assignToAdmin'])->middleware('admin_or_supply');
    Route::post('/supply-requests/{id}/accept-admin', [SupplyRequestController::class, 'acceptByAdmin'])->middleware('admin');
    
    // Message routes for supply requests
    Route::get('/supply-requests/{id}/messages', [SupplyRequestController::class, 'getMessages']);
    Route::post('/supply-requests/{id}/messages', [SupplyRequestController::class, 'sendMessage']);
    Route::post('/supply-requests/{id}/messages/mark-read', [SupplyRequestController::class, 'markMessagesAsRead']);
    Route::get('/supply-requests/messages/unread-count', [SupplyRequestController::class, 'getUnreadMessagesCount']);
    Route::get('/supply-requests/messages/all', [SupplyRequestController::class, 'getAllMessages']);
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

    // Borrow request routes - require authentication to track who sent the request
    // The mobile app MUST send the Bearer token in the Authorization header
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('items/{id}/borrow-request', [ItemController::class, 'createBorrowRequest'])->where('id', '[0-9a-fA-F\-]+');
        Route::get('items/{id}/borrow-requests', [ItemController::class, 'getBorrowRequests'])->where('id', '[0-9a-fA-F\-]+');
    });

    // Protected routes - require authentication
    Route::middleware('auth:sanctum')->group(function () {
        // USER PROFILE
        Route::put('/profile', [AuthController::class, 'updateProfile']);

        // ITEMS - Specific routes first
        Route::get('items/active', [ItemController::class, 'getActiveItems']);
        Route::get('items/generate-serial-number', [ItemController::class, 'generateSerialNumber']);
        Route::get('items/deleted', [ItemController::class, 'getDeletedItems'])->middleware('admin');
        Route::post('items/restore/{uuid}', [ItemController::class, 'restoreItem'])->middleware('admin');
        Route::post('items/{uuid}/validate-qr', [ItemController::class, 'validateQRCode']);
        Route::delete('items/delete/{uuid}', [ItemController::class, 'destroy']);
        Route::delete('items/delete-by-id/{id}', [ItemController::class, 'destroy']);
        Route::delete('items/force-delete/{uuid}', [ItemController::class, 'forceDelete'])->middleware('admin');
        
        // Item exports - must be before apiResource to avoid route conflicts
        // Use explicit route names and place them before apiResource
        Route::get('items/export/monitoring-assets', [ItemController::class, 'exportMonitoringAssets'])->name('items.export.monitoring-assets');
        Route::match(['get', 'post'], 'items/export/monitoring-assets-pdf', [ItemController::class, 'exportMonitoringAssetsPdf'])->name('items.export.monitoring-assets-pdf');
        Route::get('items/export/serviceable-items', [ItemController::class, 'exportServiceableItems'])->name('items.export.serviceable-items');
        Route::match(['get', 'post'], 'items/export/serviceable-items-pdf', [ItemController::class, 'exportServiceableItemsPdf'])->name('items.export.serviceable-items-pdf');
        Route::get('items/export/life-cycles-data', [ItemController::class, 'exportLifeCyclesData'])->name('items.export.life-cycles-data');
        Route::match(['get', 'post'], 'items/export/life-cycles-data-pdf', [ItemController::class, 'exportLifeCyclesDataPdf'])->name('items.export.life-cycles-data-pdf');
        
        // General resource routes last (excluding show since it's public for QR codes)
        Route::apiResource('items', ItemController::class)->except(['show']);
        // Update lifespan predictions - requires authentication (explicit middleware to prevent bypass)
        Route::post('items/update-lifespan-predictions', [ItemController::class, 'updateLifespanPredictions'])->middleware('auth:sanctum');

        // Locations - admin for write operations
        Route::get('locations', [LocationController::class, 'index']);
        Route::get('locations/admins', [LocationController::class, 'getAdminLocations']);
        Route::post('locations', [LocationController::class, 'store'])->middleware('admin');
        Route::put('locations/{id}', [LocationController::class, 'update'])->middleware('admin');
        Route::delete('locations/{id}', [LocationController::class, 'destroy'])->middleware('admin');

        // Users - admin only for management
        Route::get('users/deleted', [UserController::class, 'getDeletedUsers'])->middleware('admin');
        Route::post('users/{id}/restore', [UserController::class, 'restoreUser'])->middleware('admin');
        Route::delete('users/{id}/force-delete', [UserController::class, 'forceDeleteUser'])->middleware('admin');
        Route::post('users/{id}/reassign-items', [UserController::class, 'reassignItems'])->middleware('admin');
        Route::post('users/{id}/mark-resigned', [UserController::class, 'markAsResigned'])->middleware('admin');
        Route::get('users/pending-items', [UserController::class, 'getUsersWithPendingItems'])->middleware('admin');
        Route::get('users/resigned-pending-items', [UserController::class, 'getResignedUsersWithPendingItems'])->middleware('admin');
        // Custom POST route for FormData updates (PUT with FormData doesn't parse correctly in Laravel)
        Route::post('users/{id}/update', [UserController::class, 'update'])->middleware('admin');
        Route::get('users', [UserController::class, 'index']);
        Route::apiResource('users', UserController::class)->except(['index'])->middleware('admin');
        
        // Memorandum Receipts (MR) - admin only
        Route::get('memorandum-receipts', [MemorandumReceiptController::class, 'index'])->middleware('admin');
        // Specific routes must come before parameterized routes
        Route::get('memorandum-receipts/returned/available-for-reissue', [MemorandumReceiptController::class, 'getReturnedItemsAvailableForReissue'])->middleware('admin');
        Route::get('memorandum-receipts/user/{userId}/pending', [MemorandumReceiptController::class, 'getPendingItems'])->middleware('admin');
        Route::get('memorandum-receipts/user/{userId}/all', [MemorandumReceiptController::class, 'getAllIssuedItemsForUser'])->middleware('admin');
        Route::get('memorandum-receipts/personnel/{locationId}/pending', [MemorandumReceiptController::class, 'getPendingItemsForPersonnel'])->middleware('admin');
        Route::get('memorandum-receipts/personnel/{locationId}/all', [MemorandumReceiptController::class, 'getAllIssuedItemsForPersonnel'])->middleware('admin');
        Route::post('memorandum-receipts/{mrId}/reissue', [MemorandumReceiptController::class, 'reissueItem'])->middleware('admin');
        Route::post('memorandum-receipts/{mrId}/return', [MemorandumReceiptController::class, 'returnItem'])->middleware('admin');
        Route::post('memorandum-receipts/{mrId}/reassign', [MemorandumReceiptController::class, 'reassignItem'])->middleware('admin');
        Route::post('memorandum-receipts/{mrId}/lost-damaged', [MemorandumReceiptController::class, 'markAsLostOrDamaged'])->middleware('admin');
        Route::post('memorandum-receipts/{mrId}/recover', [MemorandumReceiptController::class, 'recoverItem'])->middleware('admin');
        Route::post('memorandum-receipts/formalize/user/{userId}', [MemorandumReceiptController::class, 'formalizeItemsForUser'])->middleware('admin');
        Route::get('memorandum-receipts/audit-trail/{userCode}', [MemorandumReceiptController::class, 'getAuditTrailByUserCode'])->middleware('admin');
        Route::get('memorandum-receipts/accountability-report/user/{userId}', [MemorandumReceiptController::class, 'getAccountabilityReport'])->middleware('admin');
        Route::post('memorandum-receipts/clearance/user/{userId}', [MemorandumReceiptController::class, 'processClearanceWithStatus'])->middleware('admin');
        
        // Memorandum Receipts (MR) - authenticated users can view their own items
        Route::get('memorandum-receipts/my-items', [MemorandumReceiptController::class, 'getMyIssuedItems']);
        // Users can report their own items as lost/damaged
        Route::post('memorandum-receipts/{mrId}/report-lost-damaged', [MemorandumReceiptController::class, 'reportLostOrDamaged']);
        // Users can return lost items when found (mobile app - QR scan)
        Route::post('memorandum-receipts/return-lost-item', [MemorandumReceiptController::class, 'returnLostItem']);

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
        Route::get('usage/monthly', [UsageController::class, 'getMonthlyUsage']);
        Route::get('usage/ranking', [UsageController::class, 'getSupplyUsageRanking']);
        Route::get('usage/ranking/export-excel', [UsageController::class, 'exportSupplyRankingExcel']);
        Route::get('usage/ranking/export-pdf', [UsageController::class, 'exportSupplyRankingPDF']);
        Route::get('usage/user-supply-usage', [UsageController::class, 'getUserSupplyUsage']);
        Route::get('usage/item-usage-by-user', [UsageController::class, 'getSupplyItemUsageByUser']);
        Route::get('usage/forecast-data', [UsageController::class, 'getForecastData']);

        // BORROW REQUEST MANAGEMENT - admin only
        Route::post('items/{itemId}/borrow-request/{requestId}/approve', [ItemController::class, 'approveBorrowRequest'])->where(['itemId' => '[0-9a-fA-F\-]+', 'requestId' => '[0-9]+'])->middleware('admin');
        Route::post('items/{itemId}/borrow-request/{requestId}/reject', [ItemController::class, 'rejectBorrowRequest'])->where(['itemId' => '[0-9a-fA-F\-]+', 'requestId' => '[0-9]+'])->middleware('admin');
        Route::get('admin/borrow-requests', [ItemController::class, 'getAllBorrowRequests'])->middleware('admin');
        
        // TRANSACTIONS - admin or supply accounts
        Route::get('transactions', [TransactionController::class, 'index'])->middleware('admin_or_supply');
        Route::get('transactions/export', [TransactionController::class, 'exportTransactions'])->middleware('admin_or_supply');
        Route::get('transactions/export-pdf', [TransactionController::class, 'exportPdf'])->middleware('admin_or_supply');
        
        // MAINTENANCE RECORDS - authenticated users
        Route::get('maintenance-records', [MaintenanceRecordController::class, 'index']);
        Route::get('maintenance-records/export', [MaintenanceRecordController::class, 'export']);
        Route::get('maintenance-records/export-pdf', [MaintenanceRecordController::class, 'exportPdf']);
        
        // SUPPLY REQUESTS - User role
        Route::get('supply-requests/available-supplies', [SupplyRequestController::class, 'getAvailableSupplies']);
        Route::post('supply-requests', [SupplyRequestController::class, 'store']);
        Route::get('supply-requests/my-requests', [SupplyRequestController::class, 'getUserRequests']);
        Route::delete('supply-requests/{id}/cancel', [SupplyRequestController::class, 'cancelRequest']);
        
        // SUPPLY REQUESTS - Supply role
        Route::get('supply-requests/all', [SupplyRequestController::class, 'getAllRequests'])->middleware('admin_or_supply');
        Route::post('supply-requests/{id}/approve', [SupplyRequestController::class, 'approveRequest'])->middleware('admin_or_supply');
        Route::post('supply-requests/{id}/reject', [SupplyRequestController::class, 'rejectRequest'])->middleware('admin_or_supply');
        Route::post('supply-requests/{id}/forward', [SupplyRequestController::class, 'forwardToAdmin'])->middleware('admin_or_supply');
        Route::post('supply-requests/{id}/fulfill', [SupplyRequestController::class, 'fulfillRequest'])->middleware('admin_or_supply');
        Route::post('supply-requests/{id}/schedule-pickup', [SupplyRequestController::class, 'schedulePickup'])->middleware('admin_or_supply');
        Route::post('supply-requests/{id}/notify-ready-pickup', [SupplyRequestController::class, 'notifyUserReadyForPickup'])->middleware('admin_or_supply');
        Route::get('supply-requests/stock-overview', [SupplyRequestController::class, 'getStockOverview'])->middleware('admin_or_supply');
        Route::get('supply-requests/unit-section-statistics', [SupplyRequestController::class, 'getUnitSectionStatistics'])->middleware('admin_or_supply');
        
        // SUPPLY REQUESTS - Admin assignment and acceptance
        Route::post('supply-requests/{id}/assign-admin', [SupplyRequestController::class, 'assignToAdmin'])->middleware('admin_or_supply');
        Route::post('supply-requests/{id}/accept-admin', [SupplyRequestController::class, 'acceptByAdmin'])->middleware('admin');
        
        // Message routes for supply requests
        Route::get('supply-requests/{id}/messages', [SupplyRequestController::class, 'getMessages']);
        Route::post('supply-requests/{id}/messages', [SupplyRequestController::class, 'sendMessage']);
        Route::post('supply-requests/{id}/messages/mark-read', [SupplyRequestController::class, 'markMessagesAsRead']);
        Route::get('supply-requests/messages/unread-count', [SupplyRequestController::class, 'getUnreadMessagesCount']);
        Route::get('supply-requests/messages/all', [SupplyRequestController::class, 'getAllMessages']);
        
        // Receipt verification route (public, no auth required for scanning)
        Route::get('supply-requests/receipt/{receiptNumber}/verify', [SupplyRequestController::class, 'verifyReceipt']);
    });
    
    // Receipt download route (outside auth middleware to handle token from query parameter)
    // Authentication is handled manually in the controller to support iframe/object tags
    Route::get('supply-requests/{id}/receipt', [SupplyRequestController::class, 'downloadReceipt']);

});