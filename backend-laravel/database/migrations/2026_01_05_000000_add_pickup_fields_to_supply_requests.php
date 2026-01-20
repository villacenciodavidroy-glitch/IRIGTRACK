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
        // Add pickup_scheduled_at column
        if (!Schema::hasColumn('supply_requests', 'pickup_scheduled_at')) {
            Schema::table('supply_requests', function (Blueprint $table) {
                $table->timestamp('pickup_scheduled_at')->nullable()->after('fulfilled_at');
            });
        }
        
        // Add pickup_notified_at column
        if (!Schema::hasColumn('supply_requests', 'pickup_notified_at')) {
            Schema::table('supply_requests', function (Blueprint $table) {
                $table->timestamp('pickup_notified_at')->nullable()->after('pickup_scheduled_at');
            });
        }
        
        // Update the check constraint to include 'ready_for_pickup' status
        try {
            // Drop the existing constraint if it exists
            DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
            
            // Add the constraint with 'ready_for_pickup' status included
            DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'ready_for_pickup', 'rejected', 'fulfilled', 'cancelled'))");
        } catch (\Exception $e) {
            // Log the error but don't fail the migration
            \Log::info("Status constraint update for ready_for_pickup status: " . $e->getMessage());
            
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
        // Remove columns
        if (Schema::hasColumn('supply_requests', 'pickup_notified_at')) {
            Schema::table('supply_requests', function (Blueprint $table) {
                $table->dropColumn('pickup_notified_at');
            });
        }
        
        if (Schema::hasColumn('supply_requests', 'pickup_scheduled_at')) {
            Schema::table('supply_requests', function (Blueprint $table) {
                $table->dropColumn('pickup_scheduled_at');
            });
        }
        
        // Revert to previous constraint (without 'ready_for_pickup')
        try {
            DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
            DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled', 'cancelled'))");
        } catch (\Exception $e) {
            \Log::info("Could not revert status constraint: " . $e->getMessage());
        }
    }
};

