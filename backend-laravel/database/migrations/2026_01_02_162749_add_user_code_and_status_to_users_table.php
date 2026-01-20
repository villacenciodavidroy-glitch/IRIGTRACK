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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'user_code')) {
                $table->string('user_code')->unique()->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'status')) {
                // Use string for PostgreSQL compatibility, add check constraint
                $table->string('status')->default('ACTIVE')->after('role');
            }
        });
        
        // Set default status for existing users
        if (Schema::hasColumn('users', 'status')) {
            DB::table('users')->whereNull('status')->update(['status' => 'ACTIVE']);
        }
        
        // Add check constraint for status (PostgreSQL compatible)
        if (Schema::hasColumn('users', 'status')) {
            try {
                // Drop existing constraint if it exists
                DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS check_user_status");
                // Add new constraint
                DB::statement("ALTER TABLE users ADD CONSTRAINT check_user_status CHECK (status IN ('ACTIVE', 'INACTIVE', 'RESIGNED'))");
            } catch (\Exception $e) {
                // Constraint might already exist, ignore
                \Log::warning('Could not add status constraint: ' . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['user_code', 'status']);
        });
    }
};
