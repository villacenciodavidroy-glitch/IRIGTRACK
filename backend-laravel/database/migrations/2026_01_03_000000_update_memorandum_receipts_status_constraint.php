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
        // Update the check constraint to include LOST and DAMAGED status
        try {
            DB::statement("ALTER TABLE memorandum_receipts DROP CONSTRAINT IF EXISTS check_mr_status");
            DB::statement("ALTER TABLE memorandum_receipts ADD CONSTRAINT check_mr_status CHECK (status IN ('ISSUED', 'RETURNED', 'LOST', 'DAMAGED'))");
        } catch (\Exception $e) {
            // Constraint might not exist or already updated, ignore
            \Log::info("Status constraint update: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original constraint
        try {
            DB::statement("ALTER TABLE memorandum_receipts DROP CONSTRAINT IF EXISTS check_mr_status");
            DB::statement("ALTER TABLE memorandum_receipts ADD CONSTRAINT check_mr_status CHECK (status IN ('ISSUED', 'RETURNED'))");
        } catch (\Exception $e) {
            // Ignore errors
        }
    }
};

