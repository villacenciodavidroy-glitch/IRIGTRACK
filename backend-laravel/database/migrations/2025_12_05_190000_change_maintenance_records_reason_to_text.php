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
        // For PostgreSQL, we need to drop the enum constraint and change to text
        // First, check if we're using PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            // Drop the enum constraint
            DB::statement("ALTER TABLE maintenance_records ALTER COLUMN reason TYPE TEXT USING reason::TEXT");
        } else {
            // For MySQL/MariaDB
            Schema::table('maintenance_records', function (Blueprint $table) {
                $table->text('reason')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Converting back to enum is complex and may lose data
        // This is a one-way migration in practice
        if (DB::getDriverName() === 'pgsql') {
            // Create enum type if it doesn't exist
            DB::statement("DO $$ BEGIN
                CREATE TYPE maintenance_reason_enum AS ENUM ('Wet', 'Overheat', 'Wear', 'Electrical', 'Other');
            EXCEPTION
                WHEN duplicate_object THEN null;
            END $$;");
            
            // Try to convert back (may fail if data doesn't match enum values)
            DB::statement("ALTER TABLE maintenance_records ALTER COLUMN reason TYPE maintenance_reason_enum USING reason::maintenance_reason_enum");
        } else {
            Schema::table('maintenance_records', function (Blueprint $table) {
                $table->enum('reason', ['Wet', 'Overheat', 'Wear', 'Electrical', 'Other'])->change();
            });
        }
    }
};

