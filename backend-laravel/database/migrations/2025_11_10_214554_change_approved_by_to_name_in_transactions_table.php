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
        // Drop foreign key constraint first (constraint name is fk_approved_by)
        \DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS fk_approved_by');
        
        // Change column type after dropping foreign key
        \DB::statement('ALTER TABLE transactions ALTER COLUMN approved_by TYPE VARCHAR(255) USING approved_by::text');
        \DB::statement('ALTER TABLE transactions ALTER COLUMN approved_by DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Cannot fully reverse as we lose the ID data when converting to string
        Schema::table('transactions', function (Blueprint $table) {
            // This would require manual data restoration
        });
    }
};
