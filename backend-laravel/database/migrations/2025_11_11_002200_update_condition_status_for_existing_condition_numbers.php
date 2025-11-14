<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ConditionNumber;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing condition numbers with their status
        $statusMap = [
            'A1' => 'Good',
            'A2' => 'Less Reliable',
            'A3' => 'Un-operational',
            'R' => 'Disposal',
        ];

        foreach ($statusMap as $conditionNumber => $status) {
            ConditionNumber::where('condition_number', $conditionNumber)
                ->update(['condition_status' => $status]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear condition_status for all records
        ConditionNumber::query()->update(['condition_status' => null]);
    }
};

