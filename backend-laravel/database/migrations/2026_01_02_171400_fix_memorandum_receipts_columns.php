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
        if (Schema::hasTable('memorandum_receipts')) {
            // Make issued_to_code NOT NULL if it exists and is nullable
            if (Schema::hasColumn('memorandum_receipts', 'issued_to_code')) {
                try {
                    // First, set any NULL values to a default
                    DB::statement("UPDATE memorandum_receipts SET issued_to_code = 'N/A' WHERE issued_to_code IS NULL");
                    // Then make it NOT NULL
                    DB::statement("ALTER TABLE memorandum_receipts ALTER COLUMN issued_to_code SET NOT NULL");
                } catch (\Exception $e) {
                    // Ignore if already NOT NULL
                }
            }
            
            // If old column exists, make it nullable and copy data, then drop it
            if (Schema::hasColumn('memorandum_receipts', 'issued_to_user_code')) {
                try {
                    // Copy any remaining data
                    DB::statement("UPDATE memorandum_receipts SET issued_to_code = issued_to_user_code WHERE (issued_to_code IS NULL OR issued_to_code = '') AND issued_to_user_code IS NOT NULL");
                    // Make old column nullable first
                    DB::statement("ALTER TABLE memorandum_receipts ALTER COLUMN issued_to_user_code DROP NOT NULL");
                    // Drop the old column
                    Schema::table('memorandum_receipts', function (Blueprint $table) {
                        $table->dropColumn('issued_to_user_code');
                    });
                } catch (\Exception $e) {
                    \Log::warning("Could not drop issued_to_user_code: " . $e->getMessage());
                }
            }
            
            // Same for reassigned_to_user_code
            if (Schema::hasColumn('memorandum_receipts', 'reassigned_to_user_code')) {
                try {
                    // Copy any remaining data
                    DB::statement("UPDATE memorandum_receipts SET reassigned_to_code = reassigned_to_user_code WHERE (reassigned_to_code IS NULL OR reassigned_to_code = '') AND reassigned_to_user_code IS NOT NULL");
                    // Drop the old column
                    Schema::table('memorandum_receipts', function (Blueprint $table) {
                        $table->dropColumn('reassigned_to_user_code');
                    });
                } catch (\Exception $e) {
                    \Log::warning("Could not drop reassigned_to_user_code: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't reverse
    }
};

