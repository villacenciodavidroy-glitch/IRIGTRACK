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
        // Drop the old constraint
        DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
        
        // Add new status: supply_approved, admin_assigned, admin_accepted
        DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'supply_approved', 'admin_assigned', 'admin_accepted', 'approved', 'rejected', 'fulfilled'))");
        
        // Add assigned_to_admin_id field (only if doesn't exist)
        Schema::table('supply_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('supply_requests', 'assigned_to_admin_id')) {
                $table->foreignId('assigned_to_admin_id')->nullable()->after('forwarded_to_admin_id')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('supply_requests', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_to_admin_id');
            }
            if (!Schema::hasColumn('supply_requests', 'admin_accepted_at')) {
                $table->timestamp('admin_accepted_at')->nullable()->after('assigned_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_admin_id']);
            $table->dropColumn(['assigned_to_admin_id', 'assigned_at', 'admin_accepted_at']);
        });
        
        // Restore old constraint
        DB::statement("ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS check_status");
        DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'approved', 'rejected', 'fulfilled'))");
    }
};

