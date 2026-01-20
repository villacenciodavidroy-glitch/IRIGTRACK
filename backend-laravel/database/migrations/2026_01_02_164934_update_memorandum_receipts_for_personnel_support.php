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
            Schema::table('memorandum_receipts', function (Blueprint $table) {
                // Add new columns for personnel support
                if (!Schema::hasColumn('memorandum_receipts', 'issued_to_location_id')) {
                    $table->unsignedBigInteger('issued_to_location_id')->nullable()->after('issued_to_user_id');
                    $table->foreign('issued_to_location_id')->references('id')->on('locations')->onDelete('restrict');
                }
                
                if (!Schema::hasColumn('memorandum_receipts', 'issued_to_code')) {
                    // Create issued_to_code column first
                    $table->string('issued_to_code')->nullable()->after('issued_to_location_id');
                }
                
                if (!Schema::hasColumn('memorandum_receipts', 'issued_to_type')) {
                    $table->string('issued_to_type')->default('USER')->after('issued_to_code');
                }
                
                if (!Schema::hasColumn('memorandum_receipts', 'reassigned_to_location_id')) {
                    $table->unsignedBigInteger('reassigned_to_location_id')->nullable()->after('reassigned_to_user_id');
                    $table->foreign('reassigned_to_location_id')->references('id')->on('locations')->onDelete('set null');
                }
                
                if (!Schema::hasColumn('memorandum_receipts', 'reassigned_to_code')) {
                    $table->string('reassigned_to_code')->nullable()->after('reassigned_to_location_id');
                }
            });
            
            // Copy data from old columns to new columns after all columns are created
            if (Schema::hasColumn('memorandum_receipts', 'issued_to_user_code') && Schema::hasColumn('memorandum_receipts', 'issued_to_code')) {
                try {
                    DB::statement("UPDATE memorandum_receipts SET issued_to_code = issued_to_user_code WHERE issued_to_user_code IS NOT NULL AND (issued_to_code IS NULL OR issued_to_code = '')");
                } catch (\Exception $e) {
                    // Ignore errors
                }
            }
            
            // Set type for existing records
            if (Schema::hasColumn('memorandum_receipts', 'issued_to_type')) {
                try {
                    DB::statement("UPDATE memorandum_receipts SET issued_to_type = 'USER' WHERE issued_to_type IS NULL OR issued_to_type = ''");
                } catch (\Exception $e) {
                    // Ignore errors
                }
            }
            
            if (Schema::hasColumn('memorandum_receipts', 'reassigned_to_user_code') && Schema::hasColumn('memorandum_receipts', 'reassigned_to_code')) {
                try {
                    DB::statement("UPDATE memorandum_receipts SET reassigned_to_code = reassigned_to_user_code WHERE reassigned_to_user_code IS NOT NULL AND (reassigned_to_code IS NULL OR reassigned_to_code = '')");
                } catch (\Exception $e) {
                    // Ignore errors
                }
            }
            
            Schema::table('memorandum_receipts', function (Blueprint $table) {
                
                if (!Schema::hasColumn('memorandum_receipts', 'reassigned_to_type')) {
                    $table->string('reassigned_to_type')->nullable()->after('reassigned_to_code');
                }
                
                // Make issued_to_user_id nullable if not already
                if (Schema::hasColumn('memorandum_receipts', 'issued_to_user_id')) {
                    // Check if column is already nullable
                    $column = DB::select("SELECT is_nullable FROM information_schema.columns WHERE table_name = 'memorandum_receipts' AND column_name = 'issued_to_user_id'");
                    if (!empty($column) && $column[0]->is_nullable === 'NO') {
                        DB::statement("ALTER TABLE memorandum_receipts ALTER COLUMN issued_to_user_id DROP NOT NULL");
                    }
                }
            });
            
            // Add indexes
            Schema::table('memorandum_receipts', function (Blueprint $table) {
                try {
                    if (!Schema::hasColumn('memorandum_receipts', 'issued_to_location_id')) {
                        $table->index('issued_to_location_id');
                    }
                } catch (\Exception $e) {
                    // Index might already exist
                }
                try {
                    if (Schema::hasColumn('memorandum_receipts', 'issued_to_code') && !DB::select("SELECT 1 FROM pg_indexes WHERE tablename = 'memorandum_receipts' AND indexname = 'memorandum_receipts_issued_to_code_index'")) {
                        $table->index('issued_to_code');
                    }
                } catch (\Exception $e) {
                    // Index might already exist
                }
                try {
                    if (Schema::hasColumn('memorandum_receipts', 'issued_to_type') && !DB::select("SELECT 1 FROM pg_indexes WHERE tablename = 'memorandum_receipts' AND indexname = 'memorandum_receipts_issued_to_type_index'")) {
                        $table->index('issued_to_type');
                    }
                } catch (\Exception $e) {
                    // Index might already exist
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't reverse - keep the new structure
    }
};
