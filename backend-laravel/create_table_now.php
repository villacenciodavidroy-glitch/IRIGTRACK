<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    if (Schema::hasTable('supply_requests')) {
        print "Table already exists!\n";
        exit(0);
    }
    
    print "Creating table...\n";
    
    DB::unprepared("CREATE TABLE supply_requests (
        id BIGSERIAL PRIMARY KEY,
        item_id VARCHAR(255) NOT NULL,
        quantity INTEGER NOT NULL,
        urgency_level VARCHAR(20) NOT NULL DEFAULT 'Medium',
        notes TEXT,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        requested_by_user_id BIGINT NOT NULL,
        approved_by BIGINT,
        forwarded_to_admin_id BIGINT,
        admin_comments TEXT,
        approved_at TIMESTAMP,
        fulfilled_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_supply_requests_requested_by FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_supply_requests_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_supply_requests_forwarded_to_admin FOREIGN KEY (forwarded_to_admin_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT check_urgency_level CHECK (urgency_level IN ('Low', 'Medium', 'High')),
        CONSTRAINT check_status CHECK (status IN ('pending', 'approved', 'rejected', 'fulfilled'))
    )");
    
    print "Table created!\n";
    
    DB::unprepared("CREATE INDEX idx_supply_requests_item_id ON supply_requests(item_id)");
    DB::unprepared("CREATE INDEX idx_supply_requests_status ON supply_requests(status)");
    DB::unprepared("CREATE INDEX idx_supply_requests_requested_by_user_id ON supply_requests(requested_by_user_id)");
    DB::unprepared("CREATE INDEX idx_supply_requests_urgency_level ON supply_requests(urgency_level)");
    
    print "Indexes created!\n";
    
    // Add additional columns
    $cols = [
        "request_number VARCHAR(255) UNIQUE",
        "rejection_reason TEXT",
        "fulfillment_notes TEXT",
        "delivery_location VARCHAR(255)",
        "expected_delivery_date DATE",
        "cancellation_reason TEXT",
        "fulfilled_by BIGINT",
        "rejected_at TIMESTAMP",
        "cancelled_at TIMESTAMP"
    ];
    
    foreach ($cols as $col) {
        $name = explode(' ', $col)[0];
        if (!Schema::hasColumn('supply_requests', $name)) {
            DB::unprepared("ALTER TABLE supply_requests ADD COLUMN {$col}");
            print "Added: {$name}\n";
        }
    }
    
    try {
        DB::unprepared("ALTER TABLE supply_requests ADD CONSTRAINT fk_supply_requests_fulfilled_by FOREIGN KEY (fulfilled_by) REFERENCES users(id) ON DELETE SET NULL");
        print "Foreign key added!\n";
    } catch (\Exception $e) {
        print "Foreign key already exists or error: " . $e->getMessage() . "\n";
    }
    
    DB::unprepared("CREATE INDEX idx_supply_requests_request_number ON supply_requests(request_number)");
    DB::unprepared("CREATE INDEX idx_supply_requests_delivery_location ON supply_requests(delivery_location)");
    DB::unprepared("CREATE INDEX idx_supply_requests_expected_delivery_date ON supply_requests(expected_delivery_date)");
    
    print "SUCCESS! Table supply_requests created with " . count(Schema::getColumnListing('supply_requests')) . " columns.\n";
    
} catch (\Exception $e) {
    print "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
