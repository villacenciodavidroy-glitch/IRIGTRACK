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
        Schema::table('condition_numbers', function (Blueprint $table) {
            $table->string('condition_status')->nullable()->after('condition_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('condition_numbers', function (Blueprint $table) {
            $table->dropColumn('condition_status');
        });
    }
};
