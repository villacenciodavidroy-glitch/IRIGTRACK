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
        // Update R to have status "Disposal"
        ConditionNumber::where('condition_number', 'R')
            ->update(['condition_status' => 'Disposal']);
        
        // Also ensure all other statuses are correct
        $statusMap = [
            'A1' => 'Good',
            'A2' => 'Less Reliable',
            'A3' => 'Un-operational',
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
        // Set R status back to null
        ConditionNumber::where('condition_number', 'R')
            ->update(['condition_status' => null]);
    }
};

