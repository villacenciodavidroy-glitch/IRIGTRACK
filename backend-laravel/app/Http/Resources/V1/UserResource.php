<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Safely get location name - check if relationship is loaded and is a Location model
        $locationName = null;
        if ($this->relationLoaded('location')) {
            $location = $this->getRelation('location');
            if ($location instanceof \App\Models\Location && isset($location->location)) {
                $locationName = $location->location;
            }
        }
        
        return [
            'id' => $this->id,
            'fullname' => $this->fullname,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'location_id' => $this->location_id,
            'location' => $locationName,
            'user_code' => $this->user_code ?? null,
            'status' => $this->status ?? 'ACTIVE',
            'pending_items_count' => null, // Will be calculated on frontend if needed
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null
        ];
    }
}
