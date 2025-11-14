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
        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('send_to')->nullable()->after('requested_by_user_id');
            $table->foreign('send_to')->references('id')->on('locations')->onDelete('set null');
            $table->index('send_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->dropForeign(['send_to']);
            $table->dropIndex(['send_to']);
            $table->dropColumn('send_to');
        });
    }
};
