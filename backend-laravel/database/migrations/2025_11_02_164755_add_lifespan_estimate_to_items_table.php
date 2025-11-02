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
        Schema::table('items', function (Blueprint $table) {
            // Add lifespan_estimate column if it doesn't exist
            if (!Schema::hasColumn('items', 'lifespan_estimate')) {
                // Try to add after remaining_years if it exists, otherwise just add it
                if (Schema::hasColumn('items', 'remaining_years')) {
                    $table->float('lifespan_estimate')->nullable()->after('remaining_years');
                } else {
                    $table->float('lifespan_estimate')->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'lifespan_estimate')) {
                $table->dropColumn('lifespan_estimate');
            }
        });
    }
};
