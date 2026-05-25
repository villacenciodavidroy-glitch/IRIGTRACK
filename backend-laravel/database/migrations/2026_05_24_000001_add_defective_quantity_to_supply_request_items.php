<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supply_request_items')) {
            return;
        }

        Schema::table('supply_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('supply_request_items', 'defective_quantity')) {
                $table->unsignedInteger('defective_quantity')->default(0)->after('quantity');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('supply_request_items')) {
            return;
        }

        Schema::table('supply_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('supply_request_items', 'defective_quantity')) {
                $table->dropColumn('defective_quantity');
            }
        });
    }
};
