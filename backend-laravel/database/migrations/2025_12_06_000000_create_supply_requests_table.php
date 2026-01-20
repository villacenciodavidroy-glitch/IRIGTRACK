<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('supply_requests')) {
            Schema::create('supply_requests', function (Blueprint $table) {
                $table->id();
                $table->string('item_id'); // UUID of the supply item
                $table->integer('quantity');
                $table->string('urgency_level', 20)->default('Medium');
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('pending');
                $table->foreignId('requested_by_user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('forwarded_to_admin_id')->nullable()->constrained('users')->onDelete('set null');
                $table->text('admin_comments')->nullable(); // Comments when forwarding to admin
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('fulfilled_at')->nullable();
                $table->timestamps();
                
                // Indexes for faster queries
                $table->index('item_id');
                $table->index('status');
                $table->index('requested_by_user_id');
                $table->index('urgency_level');
            });
            
            // Add CHECK constraints for PostgreSQL (only if they don't exist)
            try {
                DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_urgency_level CHECK (urgency_level IN ('Low', 'Medium', 'High'))");
            } catch (\Exception $e) {
                // Constraint might already exist
            }
            
            try {
                DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT check_status CHECK (status IN ('pending', 'approved', 'rejected', 'fulfilled'))");
            } catch (\Exception $e) {
                // Constraint might already exist
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_requests');
    }
};
