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
         Schema::create('deleted_items', function (Blueprint $table) {
            $table->id('deleted_id'); // Primary key
            $table->unsignedBigInteger('item_id'); // FK -> items.id
            $table->string('reason_for_deletion', 255)->nullable();
            $table->unsignedBigInteger('user_id'); // FK -> users.id
            $table->timestamp('created_at')->useCurrent();

            // Foreign key constraints
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_items');
    }
};
