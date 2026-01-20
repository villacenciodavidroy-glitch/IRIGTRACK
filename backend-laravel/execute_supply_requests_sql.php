<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Executing SQL to create supply_requests table ===\n\n";

try {
    // Check if table already exists
    if (Schema::hasTable('supply_requests')) {
        echo "Table 'supply_requests' already exists!\n";
        $columns = Schema::getColumnListing('supply_requests');
        echo "Columns: " . count($columns) . "\n";
        exit(0);
    }
    
    echo "Creating supply_requests table...\n";
    
    // Execute the CREATE TABLE statement
    DB::statement("
        CREATE TABLE IF NOT EXISTS supply_requests (
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
            CONSTRAINT fk_supply_requests_requested_by 
                FOREIGN KEY (requested_by_user_id) 
                REFERENCES users(id) 
                ON DELETE CASCADE,
            CONSTRAINT fk_supply_requests_approved_by 
                FOREIGN KEY (approved_by) 
                REFERENCES users(id) 
                ON DELETE SET NULL,
            CONSTRAINT fk_supply_requests_forwarded_to_admin 
                FOREIGN KEY (forwarded_to_admin_id) 
                REFERENCES users(id) 
                ON DELETE SET NULL,
            CONSTRAINT check_urgency_level CHECK (urgency_level IN ('Low', 'Medium', 'High')),
            CONSTRAINT check_status CHECK (status IN ('pending', 'approved', 'rejected', 'fulfilled'))
        )
    ");
    
    echo "✓ Base table created!\n";
    
    // Create indexes
    DB::statement("CREATE INDEX IF NOT EXISTS idx_supply_requests_item_id ON supply_requests(item_id)");
    DB::statement("CREATE INDEX IF NOT EXISTS idx_supply_requests_status ON supply_requests(status)");
    DB::statement("CREATE INDEX IF NOT EXISTS idx_supply_requests_requested_by_user_id ON supply_requests(requested_by_user_id)");
    DB::statement("CREATE INDEX IF NOT EXISTS idx_supply_requests_urgency_level ON supply_requests(urgency_level)");
    
    echo "✓ Indexes created!\n";
    
    // Add additional columns
    $additionalColumns = [
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
    
    foreach ($additionalColumns as $column) {
        $columnName = explode(' ', $column)[0];
        if (!Schema::hasColumn('supply_requests', $columnName)) {
            DB::statement("ALTER TABLE supply_requests ADD COLUMN IF NOT EXISTS {$column}");
            echo "✓ Added column: {$columnName}\n";
        }
    }
    
    // Add foreign key for fulfilled_by
    try {
        DB::statement("
            ALTER TABLE supply_requests 
            ADD CONSTRAINT fk_supply_requests_fulfilled_by 
            FOREIGN KEY (fulfilled_by) 
            REFERENCES users(id) 
            ON DELETE SET NULL
        ");
        echo "✓ Foreign key constraint added!\n";
    } catch (\Exception $e) {
        // Constraint might already exist
        if (strpos($e->getMessage(), 'already exists') === false) {
            throw $e;
        }
        echo "✓ Foreign key constraint already exists\n";
    }
    
    // Create indexes for new fields
    DB::statement("CREATE INDEX IF NOT EXISTS idx_supply_requests_request_number ON supply_requests(request_number)");
    DB::statement("CREATE INDEX IF NOT EXISTS idx_supply_requests_delivery_location ON supply_requests(delivery_location)");
    DB::statement("CREATE INDEX IF NOT EXISTS idx_supply_requests_expected_delivery_date ON supply_requests(expected_delivery_date)");
    
    echo "✓ Additional indexes created!\n";
    
    // Verify table creation
    if (Schema::hasTable('supply_requests')) {
        $columns = Schema::getColumnListing('supply_requests');
        echo "\n=== SUCCESS ===\n";
        echo "Table 'supply_requests' created successfully!\n";
        echo "Total columns: " . count($columns) . "\n";
        echo "\nColumns:\n";
        foreach ($columns as $col) {
            echo "  - {$col}\n";
        }
    } else {
        throw new \Exception("Table was not created successfully");
    }
    
} catch (\Exception $e) {
    echo "\n=== ERROR ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
