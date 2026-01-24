<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allows rejecting specific line items (e.g. defective) while processing the rest.
     */
    public function up(): void
    {
        if (!Schema::hasTable('supply_request_items')) {
            return;
        }

        Schema::table('supply_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('supply_request_items', 'status')) {
                $table->string('status', 32)->default('pending')->after('quantity');
            }
            if (!Schema::hasColumn('supply_request_items', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('supply_request_items', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('supply_request_items', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete()->after('rejected_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('supply_request_items')) {
            return;
        }

        Schema::table('supply_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('supply_request_items', 'rejected_by')) {
                $table->dropForeign(['rejected_by']);
            }
            if (Schema::hasColumn('supply_request_items', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('supply_request_items', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('supply_request_items', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
