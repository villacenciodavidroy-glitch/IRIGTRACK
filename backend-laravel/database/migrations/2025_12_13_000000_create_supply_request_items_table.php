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
        if (!Schema::hasTable('supply_request_items')) {
            Schema::create('supply_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supply_request_id')->constrained('supply_requests')->onDelete('cascade');
                $table->string('item_id'); // UUID or ID of the item
                $table->integer('quantity');
                $table->timestamps();
                
                // Indexes
                $table->index('supply_request_id');
                $table->index('item_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_request_items');
    }
};

