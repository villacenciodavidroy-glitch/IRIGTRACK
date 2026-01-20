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
        Schema::table('supply_requests', function (Blueprint $table) {
            // Add request reference number for easier tracking (only if doesn't exist)
            if (!Schema::hasColumn('supply_requests', 'request_number')) {
                $table->string('request_number')->unique()->nullable()->after('id');
            }
            
            // Add rejection reason when request is rejected
            if (!Schema::hasColumn('supply_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('admin_comments');
            }
            
            // Add fulfillment notes when request is fulfilled
            if (!Schema::hasColumn('supply_requests', 'fulfillment_notes')) {
                $table->text('fulfillment_notes')->nullable()->after('rejection_reason');
            }
            
            // Add delivery location (where supplies should be delivered)
            if (!Schema::hasColumn('supply_requests', 'delivery_location')) {
                $table->string('delivery_location')->nullable()->after('fulfillment_notes');
            }
            
            // Add expected delivery date (when user needs the supplies)
            if (!Schema::hasColumn('supply_requests', 'expected_delivery_date')) {
                $table->date('expected_delivery_date')->nullable()->after('delivery_location');
            }
            
            // Add cancellation reason when user cancels
            if (!Schema::hasColumn('supply_requests', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('expected_delivery_date');
            }
            
            // Add fulfilled_by to track who fulfilled the request
            if (!Schema::hasColumn('supply_requests', 'fulfilled_by')) {
                $table->foreignId('fulfilled_by')->nullable()->constrained('users')->onDelete('set null')->after('fulfilled_at');
            }
            
            // Add rejected_at timestamp
            if (!Schema::hasColumn('supply_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
            
            // Add cancelled_at timestamp
            if (!Schema::hasColumn('supply_requests', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('rejected_at');
            }
        });
        
        // Add indexes separately (only if columns exist and indexes don't exist)
        Schema::table('supply_requests', function (Blueprint $table) {
            if (Schema::hasColumn('supply_requests', 'request_number')) {
                try {
                    $table->index('request_number');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }
            if (Schema::hasColumn('supply_requests', 'expected_delivery_date')) {
                try {
                    $table->index('expected_delivery_date');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }
            if (Schema::hasColumn('supply_requests', 'delivery_location')) {
                try {
                    $table->index('delivery_location');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropForeign(['fulfilled_by']);
            $table->dropIndex(['request_number']);
            $table->dropIndex(['expected_delivery_date']);
            $table->dropIndex(['delivery_location']);
            
            $table->dropColumn([
                'request_number',
                'rejection_reason',
                'fulfillment_notes',
                'delivery_location',
                'expected_delivery_date',
                'cancellation_reason',
                'fulfilled_by',
                'rejected_at',
                'cancelled_at'
            ]);
        });
    }
};
