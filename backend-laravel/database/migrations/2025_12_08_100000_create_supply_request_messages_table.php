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
        if (!Schema::hasTable('supply_request_messages')) {
            Schema::create('supply_request_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supply_request_id')->constrained('supply_requests')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Who sent the message
                $table->text('message'); // The message content
                $table->boolean('is_read')->default(false); // Whether the message has been read
                $table->timestamp('read_at')->nullable(); // When the message was read
                $table->timestamps();
                
                // Indexes for better query performance
                $table->index('supply_request_id');
                $table->index('user_id');
                $table->index('is_read');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_request_messages');
    }
};
