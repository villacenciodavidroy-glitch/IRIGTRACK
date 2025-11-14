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
        
        // Determine title and priority based on type
        $title = $type === 'borrow_request' ? 'Borrow Request' : 'Low Stock Alert';
        $priority = $type === 'borrow_request' ? 'high' : 'high';
        
        $data = [
            'id' => $this->notification_id,
            'item_id' => $this->item_id,
            'message' => $this->message,
            'type' => $type,
            'title' => $title,
            'priority' => $priority,
            'timestamp' => $this->created_at->toISOString(),
            'date' => $this->created_at->format('d-m-Y'),
            'time' => $this->created_at->format('h:i A'),
            'created_at' => $this->created_at->toISOString(),
            'isRead' => $this->is_read ?? false,
            'item' => $item ? [
                'id' => $item->id,
                'uuid' => $item->uuid ?? null,
                'unit' => $item->unit,
                'description' => $item->description,
                'quantity' => $item->quantity,
            ] : null,
        ];
        
        // Add borrow request data if this is a borrow request notification
        if ($type === 'borrow_request' && $borrowRequest) {
            $data['borrowRequest'] = [
                'id' => $borrowRequest->id,
                'quantity' => $borrowRequest->quantity,
                'location' => $borrowRequest->location,
                'borrowed_by' => $borrowRequest->borrowed_by,
                'status' => $borrowRequest->status,
                'created_at' => $borrowRequest->created_at->toISOString(),
            ];
        }
        
        return $data;
    }
}
