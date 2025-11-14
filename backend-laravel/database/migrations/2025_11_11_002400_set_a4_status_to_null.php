<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ConditionNumber;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set A4 condition_status to null
        ConditionNumber::where('condition_number', 'A4')
            ->update(['condition_status' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration doesn't need a reverse operation
        // as we're setting it to null which is the default
    }
};

