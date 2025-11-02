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
            $categoryName = $this->category->category;
        } elseif ($this->category_id) {
            // If relationship not loaded but category_id exists, return null or try to access
            // In this case, we'll just set it to null to avoid errors
            $categoryName = null;
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
        
        // Get issued_to: priority is location personnel, fallback to user fullname
        $issuedTo = null;
        if ($this->whenLoaded('location') && $this->location && $this->location->personnel) {
            $issuedTo = $this->location->personnel;
        } elseif ($this->whenLoaded('user') && $this->user) {
            $issuedTo = $this->user->fullname;
        }
        
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'unit' => $this->unit,
            'description' => $this->description,
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
            'category_id' => $this->category_id,
            'location_id' => $this->location_id,
            'condition_id' => $this->condition_id,
            'condition_number_id' => $this->condition_number_id,
            'maintenance_reason' => $this->maintenance_reason,
            'maintenance_count' => $this->maintenance_count ?? 0,
            'lifespan_estimate' => $this->lifespan_estimate,
            'remaining_years' => $this->remaining_years,
            'deleted_at' => $this->deleted_at,
            'deletion_reason' => $this->deletion_reason
        ];
    }
}
