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
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'unit' => $this->unit,
            'description' => $this->description,
            'category' => $this->category->category,
            'quantity' => $this->quantity,
            'pac' =>$this->pac,
            'unit_value' => $this->unit_value,
            'po_number' => $this->po_number,
            "date_acquired" => $this->date_acquired,
            'location' => $this->location ? $this->location->location : null,
            // 'conditionType' => $this->condition ? $this->condition->condition : null,
            'condition' => ($this->condition && $this->condition_number)
    ? $this->condition->condition . ' (' . $this->condition_number->condition_number . ')'
    : "(Supply)",
            'qr_code' => $this->qrCode ? $this->qrCode->qr_code_data : null,
            'qr_code_uuid' => $this->qrCode ? $this->qrCode->id : null,
            'qr_code_version' => $this->qrCode ? $this->qrCode->version : null,
            'qr_code_is_active' => $this->qrCode ? $this->qrCode->is_active : null,
            'image_path' => asset('storage/' . $this->image_path) ?? null,
            'qr_code_image' => $this->qrCode ? asset('storage/' . $this->qrCode->image_path)  : null,
            'user_id' => $this->user_id,
            'issued_to' => $this->user ? $this->user->fullname : null,
            'deleted_at' => $this->deleted_at,
            'deletion_reason' => $this->deletion_reason
        ];
    }
}