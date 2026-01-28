<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the check constraint to include 'for_claiming' status
        try {
            // Drop the existing constraint if it exists
            DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
            
            // Add the constraint with 'for_claiming' status included
            DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'ready_for_pickup', 'for_claiming', 'rejected', 'fulfilled', 'cancelled'))");
        } catch (\Exception $e) {
            // Log the error but don't fail the migration
            \Log::info("Status constraint update for for_claiming status: " . $e->getMessage());
            
            // Try alternative approach for different database systems
            try {
                // For MySQL/MariaDB, we might need to use a different syntax
                if (DB::getDriverName() === 'mysql') {
                    // MySQL doesn't support CHECK constraints in older versions, so we skip it
                    \Log::info("MySQL detected - CHECK constraint skipped (not supported in older versions)");
                }
            } catch (\Exception $e2) {
                \Log::warning("Could not update status constraint: " . $e2->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous constraint (without 'for_claiming')
        try {
            DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
            DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'ready_for_pickup', 'rejected', 'fulfilled', 'cancelled'))");
        } catch (\Exception $e) {
            \Log::info("Could not revert status constraint: " . $e->getMessage());
        }
    }
};
