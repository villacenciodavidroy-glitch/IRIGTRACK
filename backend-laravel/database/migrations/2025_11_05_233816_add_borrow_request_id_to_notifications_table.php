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
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('borrow_request_id')->nullable()->after('item_id');
            $table->foreign('borrow_request_id')->references('id')->on('borrow_requests')->onDelete('cascade');
            $table->string('type')->default('low_stock')->after('message'); // 'low_stock' or 'borrow_request'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['borrow_request_id']);
            $table->dropColumn(['borrow_request_id', 'type']);
        });
    }
};
