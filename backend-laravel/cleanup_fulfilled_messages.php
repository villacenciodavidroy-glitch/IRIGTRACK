<?php

/**
 * Cleanup script to delete all messages for fulfilled and rejected supply requests
 * Run this once to clean up existing messages in the database
 * 
 * Usage: php cleanup_fulfilled_messages.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestMessage;

echo "Starting cleanup of messages for fulfilled and rejected requests...\n";

try {
    // Get all fulfilled and rejected request IDs
    $completedRequestIds = SupplyRequest::whereIn('status', ['fulfilled', 'rejected', 'cancelled'])
        ->pluck('id')
        ->toArray();
    
    if (empty($completedRequestIds)) {
        echo "No fulfilled/rejected/cancelled requests found.\n";
        exit(0);
    }
    
    echo "Found " . count($completedRequestIds) . " completed request(s) (fulfilled/rejected/cancelled).\n";
    
    // Delete all messages for these requests
    $deletedCount = SupplyRequestMessage::whereIn('supply_request_id', $completedRequestIds)->delete();
    
    echo "Successfully deleted {$deletedCount} message(s) for completed requests.\n";
    echo "Cleanup completed!\n";
    
} catch (\Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
