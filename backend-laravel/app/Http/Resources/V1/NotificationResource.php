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
        
        return [
            'id' => $this->notification_id,
            'item_id' => $this->item_id,
            'message' => $this->message,
            'type' => 'low_stock', // All notifications are low stock alerts
            'title' => 'Low Stock Alert',
            'priority' => 'high', // Low stock is high priority
            'timestamp' => $this->created_at->toISOString(),
            'date' => $this->created_at->format('d-m-Y'),
            'time' => $this->created_at->format('h:i A'),
            'created_at' => $this->created_at->toISOString(),
            'isRead' => $this->is_read ?? false,
            'item' => $item ? [
                'id' => $item->id,
                'unit' => $item->unit,
                'description' => $item->description,
                'quantity' => $item->quantity,
            ] : null,
        ];
    }
}
