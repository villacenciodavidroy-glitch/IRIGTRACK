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
            // Change remaining_years from integer to float to support decimal values
            $table->float('remaining_years')->nullable()->change();
            // Also ensure lifespan_estimate is float (should already be, but ensure it)
            $table->float('lifespan_estimate')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Revert to integer (though we shouldn't need this)
            $table->integer('remaining_years')->nullable()->change();
        });
    }
};
