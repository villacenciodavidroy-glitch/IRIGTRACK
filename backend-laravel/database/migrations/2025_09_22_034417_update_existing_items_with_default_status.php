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
        // Update existing items that don't have a status to 'Good'
        \DB::table('items')->whereNull('status')->update(['status' => 'Good']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this data update
    }
};
