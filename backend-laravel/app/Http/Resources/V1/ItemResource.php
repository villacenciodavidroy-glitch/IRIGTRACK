<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Safely get category name
        $categoryName = null;
        if ($this->whenLoaded('category') && $this->category) {
            // Category model has 'category' field (not 'name')
            $categoryName = $this->category->category;
        } elseif ($this->category_id) {
            // If relationship not loaded but category_id exists, try to load it
            try {
                $category = \App\Models\Category::find($this->category_id);
                if ($category) {
                    $categoryName = $category->category;
                }
            } catch (\Exception $e) {
                // If category doesn't exist, leave as null
                $categoryName = null;
            }
        }
        
        // Safely get location name
        $locationName = null;
        if ($this->whenLoaded('location') && $this->location) {
            $locationName = $this->location->location;
        }
        
        // Safely get condition
        $condition = "(Supply)";
        if ($this->whenLoaded('condition') && $this->condition) {
            if ($this->whenLoaded('condition_number') && $this->condition_number) {
                $condition = $this->condition->condition . ' (' . $this->condition_number->condition_number . ')';
            } else {
                $condition = $this->condition->condition;
            }
        }
        
        // Get condition_status from condition_number
        $conditionStatus = null;
        if ($this->whenLoaded('condition_number') && $this->condition_number) {
            $conditionStatus = $this->condition_number->condition_status;
        }
        
        // Get issued_to: priority is location personnel, fallback to user fullname with user_code
        $issuedTo = null;
        $issuedToCode = null;
        $issuedToUserStatus = null;
        $issuedToType = null;
        
        if ($this->whenLoaded('location') && $this->location && $this->location->personnel) {
            $issuedTo = $this->location->personnel;
            $issuedToCode = $this->location->personnel_code;
            $issuedToType = 'PERSONNEL';
        } elseif ($this->whenLoaded('user') && $this->user) {
            $issuedTo = $this->user->fullname;
            $issuedToCode = $this->user->user_code;
            $issuedToType = 'USER';
            $issuedToUserStatus = $this->user->status ?? 'ACTIVE';
        }
        
        // Keep issued_to as name only (code will be displayed separately in frontend)
        // Format with status badge if needed
        if ($issuedTo && $issuedToCode) {
            $statusBadge = '';
            if ($issuedToType === 'USER' && $issuedToUserStatus === 'RESIGNED') {
                $statusBadge = ' [RESIGNED]';
            } elseif ($issuedToType === 'USER' && $issuedToUserStatus === 'INACTIVE') {
                $statusBadge = ' [INACTIVE]';
            }
            // Keep name and code separate for frontend formatting
            $issuedTo = $issuedTo . $statusBadge;
        }
        
        // Calculate remaining lifespan in days for mobile app display
        $remainingDays = null;
        $lifespanStatus = null;
        $lifespanStatusText = null;
        
        if ($this->remaining_years !== null) {
            // Calculate remaining days
            $remainingDays = (int) round($this->remaining_years * 365);
            
            // Get condition number string to check for disposal
            $conditionNumberStr = null;
            if ($this->whenLoaded('condition_number') && $this->condition_number) {
                $conditionNumberStr = strtoupper($this->condition_number->condition_number ?? '');
            }
            
            // Check if item should be disposed (R condition number, Disposal status, or Non-Serviceable)
            $shouldDispose = (
                $conditionNumberStr === 'R' ||
                $conditionStatus === 'Disposal' ||
                (strpos($condition ?? '', 'Non-Serviceable') !== false) ||
                (strpos($condition ?? '', 'Non - Serviceable') !== false)
            );
            
            // Determine lifespan status based on remaining years or disposal flag
            if ($shouldDispose) {
                $lifespanStatus = 'DISPOSAL';
                $lifespanStatusText = 'URGENT: Item marked for disposal';
            } elseif ($this->remaining_years <= 0.082) { // <= 30 days
                $lifespanStatus = 'FOR_CHECKING';
                $lifespanStatusText = 'URGENT: End of life reached - Replacement required';
            } elseif ($this->remaining_years <= 0.164) { // <= 60 days
                $lifespanStatus = 'SOON';
                $lifespanStatusText = 'Plan replacement soon';
            } elseif ($this->remaining_years <= 0.5) { // <= 6 months
                $lifespanStatus = 'MONITOR';
                $lifespanStatusText = 'Monitor closely';
            } else {
                $lifespanStatus = 'GOOD';
                $lifespanStatusText = 'Good condition';
            }
        }
        
        // Extract recovery information if item was recovered from LOST/DAMAGED status
        $recoveryInfo = null;
        if ($this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt && $this->latestMemorandumReceipt->remarks) {
            try {
                $remarks = is_string($this->latestMemorandumReceipt->remarks) 
                    ? json_decode($this->latestMemorandumReceipt->remarks, true) 
                    : $this->latestMemorandumReceipt->remarks;
                
                if (is_array($remarks) && isset($remarks['recovered']) && $remarks['recovered'] === true) {
                    $recoveryInfo = [
                        'original_status' => $remarks['original_status'] ?? 'LOST',
                        'recovery_notes' => $remarks['recovery_notes'] ?? null,
                        'recovered_by' => $remarks['recovered_by'] ?? null,
                        'recovery_date' => $remarks['recovery_date'] ?? null,
                        'recovered_at' => $remarks['recovered_at'] ?? null,
                        'processed_by' => $remarks['processed_by'] ?? null
                    ];
                }
            } catch (\Exception $e) {
                // If parsing fails, leave as null
            }
        }
        
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'unit' => $this->unit,
            'description' => $this->description,
            'serial_number' => $this->serial_number,
            'model' => $this->model,
            'category' => $categoryName,
            'quantity' => $this->quantity,
            'pac' => $this->pac,
            'unit_value' => $this->unit_value,
            'po_number' => $this->po_number,
            "date_acquired" => $this->date_acquired,
            'location' => $locationName,
            'condition' => $condition,
            'qr_code' => $this->whenLoaded('qrCode') && $this->qrCode ? $this->qrCode->qr_code_data : null,
            'qr_code_uuid' => $this->whenLoaded('qrCode') && $this->qrCode ? $this->qrCode->id : null,
            'qr_code_version' => $this->whenLoaded('qrCode') && $this->qrCode ? $this->qrCode->version : null,
            'qr_code_is_active' => $this->whenLoaded('qrCode') && $this->qrCode ? $this->qrCode->is_active : null,
            'image_path' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'qr_code_image' => $this->whenLoaded('qrCode') && $this->qrCode && $this->qrCode->image_path ? asset('storage/' . $this->qrCode->image_path) : null,
            'user_id' => $this->user_id,
            'issued_to' => $issuedTo ?: 'Not Assigned',
            'issued_to_code' => $issuedToCode,
            'issued_to_type' => $issuedToType,
            'issued_to_user_status' => $issuedToUserStatus,
            'category_id' => $this->category_id,
            'location_id' => $this->location_id,
            'condition_id' => $this->condition_id,
            'condition_number_id' => $this->condition_number_id,
            'condition_status' => $conditionStatus, // Inventory status: Good, Less Reliable, Un-operational, Disposal
            // Get maintenance_reason from latest maintenance record (free text field)
            'maintenance_reason' => $this->whenLoaded('maintenance_records') && $this->maintenance_records->isNotEmpty()
                ? $this->maintenance_records->sortByDesc('maintenance_date')->first()->reason
                : ($this->maintenance_reason ?? null), // Fallback to items.maintenance_reason if exists
            'maintenance_count' => $this->maintenance_count ?? 0,
            'lifespan_estimate' => $this->lifespan_estimate,
            'remaining_years' => $this->remaining_years,
            'remaining_days' => $remainingDays, // Added: Remaining lifespan in days for mobile app
            'lifespan_status' => $lifespanStatus, // Added: Lifespan-based status (GOOD, MONITOR, SOON, FOR_CHECKING, DISPOSAL)
            'lifespan_status_text' => $lifespanStatusText, // Added: Human-readable lifespan status message
            'deleted_at' => $this->deleted_at,
            'deletion_reason' => $this->deletion_reason, // Keep for backward compatibility
            'reason_for_deletion' => $this->reason_for_deletion ?? null, // From deleted_items table
            'deleted_by_user_id' => $this->deleted_by_user_id ?? null,
            'maintenance_records' => $this->whenLoaded('maintenance_records') ? $this->maintenance_records->map(function ($record) {
                return [
                    'id' => $record->id,
                    'maintenance_date' => $record->maintenance_date,
                    'reason' => $record->reason,
                    'technician_notes' => $record->technician_notes,
                    'condition_before_id' => $record->condition_before_id,
                    'condition_after_id' => $record->condition_after_id
                ];
            }) : null,
            // Memorandum Receipt (MR) status information for QR code scanning
            'mr_status' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt 
                ? $this->latestMemorandumReceipt->status 
                : null,
            'mr_issued_to' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt
                ? ($this->latestMemorandumReceipt->issued_to_code ?? 'N/A')
                : null,
            'mr_issued_to_name' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt
                ? ($this->latestMemorandumReceipt->issued_to_type === 'USER' 
                    ? ($this->latestMemorandumReceipt->issuedToUser->fullname ?? 'N/A')
                    : ($this->latestMemorandumReceipt->issuedToLocation->personnel ?? 'N/A'))
                : null,
            'mr_issued_at' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt
                ? $this->latestMemorandumReceipt->issued_at
                : null,
            'mr_remarks' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt
                ? $this->latestMemorandumReceipt->remarks
                : null,
            'mr_id' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt
                ? $this->latestMemorandumReceipt->id
                : null,
            'mr_number' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt
                ? $this->latestMemorandumReceipt->id
                : null, // MR number is the same as MR ID for display purposes
            // Helper fields for mobile app to easily identify lost/damaged items
            'is_lost' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt
                ? ($this->latestMemorandumReceipt->status === 'LOST')
                : false,
            'is_damaged' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt
                ? ($this->latestMemorandumReceipt->status === 'DAMAGED')
                : false,
            'is_lost_or_damaged' => $this->whenLoaded('latestMemorandumReceipt') && $this->latestMemorandumReceipt
                ? (in_array($this->latestMemorandumReceipt->status, ['LOST', 'DAMAGED']))
                : false,
            // Recovery information (if item was recovered from LOST/DAMAGED status)
            'recovery_info' => $recoveryInfo
        ];
    }
}
