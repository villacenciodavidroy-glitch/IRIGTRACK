<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column already exists before adding
        if (!Schema::hasColumn('supply_requests', 'target_supply_account_id')) {
            Schema::table('supply_requests', function (Blueprint $table) {
                // Add target_supply_account_id to specify which supply account the request is submitted to
                $table->foreignId('target_supply_account_id')->nullable()->after('requested_by_user_id')->constrained('users')->onDelete('set null');
                
                // Add index for better query performance
                $table->index('target_supply_account_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropForeign(['target_supply_account_id']);
            $table->dropIndex(['target_supply_account_id']);
            $table->dropColumn('target_supply_account_id');
        });
    }
};

