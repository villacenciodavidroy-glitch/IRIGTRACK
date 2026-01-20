<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->item;
        $borrowRequest = $this->borrowRequest;
        $type = $this->type ?? 'low_stock';
        
        // Get the user who requested this borrow request (if applicable)
        $requestedByName = 'N/A';
        if ($type === 'borrow_request' && $borrowRequest) {
            if ($borrowRequest->requested_by_user_id) {
                $requestedByUser = \App\Models\User::find($borrowRequest->requested_by_user_id);
                if ($requestedByUser) {
                    $requestedByName = $requestedByUser->fullname ?? $requestedByUser->username ?? $requestedByUser->email ?? 'N/A';
                }
            }
            // Fallback to borrowed_by if no user found
            if ($requestedByName === 'N/A' && $borrowRequest->borrowed_by) {
                $requestedByName = $borrowRequest->borrowed_by;
            }
        }
        
        // Determine title and priority based on type
        $title = match($type) {
            'borrow_request' => $requestedByName,
            'supply_request_created' => 'New Supply Request',
            'supply_request_approved' => 'Receipt Available',
            'supply_request_admin_approved' => 'Receipt Available',
            'supply_request_rejected' => 'Request Rejected',
            'supply_request_admin_rejected' => 'Request Rejected',
            'supply_request_ready_pickup' => 'Ready for Pickup',
            'supply_request_ready_for_pickup' => 'Ready for Pickup',
            'item_lost_damaged_report' => 'Item Lost/Damaged Report',
            'item_recovered' => 'Item Recovered',
            default => 'Low Stock Alert'
        };
        $priority = in_array($type, ['borrow_request', 'item_lost_damaged_report', 'item_recovered']) ? 'high' : 'high';
        
        $data = [
            'id' => $this->notification_id,
            'item_id' => $this->item_id,
            'message' => $this->message ?? '',
            'type' => $type,
            'title' => $title,
            'priority' => $priority,
            'timestamp' => $this->created_at ? $this->created_at->toISOString() : now()->toISOString(),
            'date' => $this->created_at ? $this->created_at->format('d-m-Y') : now()->format('d-m-Y'),
            'time' => $this->created_at ? $this->created_at->format('h:i A') : now()->format('h:i A'),
            'created_at' => $this->created_at ? $this->created_at->toISOString() : now()->toISOString(),
            'isRead' => $this->is_read ?? false,
            'item' => $item ? [
                'id' => $item->id ?? null,
                'uuid' => $item->uuid ?? null,
                'unit' => $item->unit ?? null,
                'description' => $item->description ?? null,
                'quantity' => $item->quantity ?? 0,
                'serial_number' => $item->serial_number ?? null,
                'model' => $item->model ?? null,
                'image_path' => ($item->image_path && !empty($item->image_path)) ? asset('storage/' . $item->image_path) : null,
                'unit_value' => $item->unit_value ?? null,
                'category' => (method_exists($item, 'relationLoaded') && $item->relationLoaded('category') && $item->category) ? ($item->category->category ?? null) : null,
                'location' => (method_exists($item, 'relationLoaded') && $item->relationLoaded('location') && $item->location) ? ($item->location->location ?? null) : null,
                'condition' => (method_exists($item, 'relationLoaded') && $item->relationLoaded('condition') && $item->condition) ? ($item->condition->condition ?? null) : null,
            ] : null,
        ];
        
        // Add borrow request data if this is a borrow request notification
        if ($type === 'borrow_request' && $borrowRequest) {
            
            $data['borrowRequest'] = [
                'id' => $borrowRequest->id,
                'quantity' => $borrowRequest->quantity,
                'location' => $borrowRequest->location,
                'borrowed_by' => $borrowRequest->borrowed_by,
                'requested_by' => $requestedByName,
                'requested_by_user_id' => $borrowRequest->requested_by_user_id,
                'status' => $borrowRequest->status,
                'created_at' => $borrowRequest->created_at ? $borrowRequest->created_at->toISOString() : now()->toISOString(),
            ];
        }
        
        return $data;
    }
}
