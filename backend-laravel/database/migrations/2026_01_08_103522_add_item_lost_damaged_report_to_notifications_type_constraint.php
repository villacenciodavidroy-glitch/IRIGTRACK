<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the check constraint to include item_lost_damaged_report notification type
        try {
            // Drop the existing constraint if it exists
            DB::statement("ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check");
            
            // Add the constraint with all notification types included
            // Types: low_stock, borrow_request, supply_request_created, supply_request_approved, 
            // supply_request_ready_for_pickup, supply_request_ready_pickup, item_lost_damaged_report, item_recovered
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type IN ('low_stock', 'borrow_request', 'supply_request_created', 'supply_request_approved', 'supply_request_ready_for_pickup', 'supply_request_ready_pickup', 'supply_request_admin_approved', 'supply_request_rejected', 'supply_request_admin_rejected', 'item_lost_damaged_report', 'item_recovered'))");
        } catch (\Exception $e) {
            \Log::info("Notifications type constraint update: " . $e->getMessage());
            
            // Try alternative approach for different database systems
            try {
                // For MySQL/MariaDB, we might need to use a different syntax
                if (DB::getDriverName() === 'mysql') {
                    // MySQL doesn't support CHECK constraints in older versions, so we skip it
                    \Log::info("MySQL detected - CHECK constraint skipped (not supported in older versions)");
                }
            } catch (\Exception $e2) {
                \Log::warning("Could not update notifications type constraint: " . $e2->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous constraint (without item_lost_damaged_report)
        try {
            DB::statement("ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_type_check");
            // Re-add with previous types (without item_recovered)
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_type_check CHECK (type IN ('low_stock', 'borrow_request', 'supply_request_created', 'supply_request_approved', 'supply_request_ready_for_pickup', 'supply_request_ready_pickup', 'supply_request_admin_approved', 'supply_request_rejected', 'supply_request_admin_rejected', 'item_lost_damaged_report'))");
            \Log::info("Notifications type constraint reverted in down migration");
        } catch (\Exception $e) {
            \Log::info("Could not revert notifications type constraint: " . $e->getMessage());
        }
    }
};
