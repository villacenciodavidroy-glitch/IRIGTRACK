<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add equipment tracking fields: serial_number, model, brand, manufacturer
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->after('description');
            $table->string('model')->nullable()->after('serial_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'model']);
        });
    }
};

