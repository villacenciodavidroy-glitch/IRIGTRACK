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
        if (!Schema::hasTable('memorandum_receipts')) {
            Schema::create('memorandum_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->unsignedBigInteger('issued_to_user_id')->nullable(); // Nullable for personnel without accounts
            $table->foreign('issued_to_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->unsignedBigInteger('issued_to_location_id')->nullable(); // For personnel without accounts
            $table->foreign('issued_to_location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->string('issued_to_code'); // Can be user_code or personnel_code - permanent tracking
            $table->string('issued_to_type')->default('USER'); // USER or PERSONNEL
            $table->string('issued_by_user_code'); // Admin who issued the item
            $table->timestamp('issued_at');
            $table->timestamp('returned_at')->nullable();
            $table->string('status')->default('ISSUED'); // Use string for PostgreSQL compatibility
            $table->text('remarks')->nullable(); // For lost/damaged items
            $table->unsignedBigInteger('reassigned_to_user_id')->nullable();
            $table->foreign('reassigned_to_user_id')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('reassigned_to_location_id')->nullable(); // For personnel reassignment
            $table->foreign('reassigned_to_location_id')->references('id')->on('locations')->onDelete('set null');
            $table->string('reassigned_to_code')->nullable(); // Can be user_code or personnel_code
            $table->string('reassigned_to_type')->nullable(); // USER or PERSONNEL
            $table->unsignedBigInteger('processed_by_user_id')->nullable(); // Admin who processed clearance
            $table->foreign('processed_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('issued_to_user_id');
            $table->index('issued_to_location_id');
            $table->index('issued_to_code');
            $table->index('issued_to_type');
            $table->index('status');
            });
            
            // Add check constraint for status (PostgreSQL compatible)
            try {
                DB::statement("ALTER TABLE memorandum_receipts ADD CONSTRAINT check_mr_status CHECK (status IN ('ISSUED', 'RETURNED'))");
            } catch (\Exception $e) {
                // Constraint might already exist, ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memorandum_receipts');
    }
};
