<?php

namespace App\Events;

use App\Models\SupplyRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplyRequestApproved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $supplyRequest;
    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(SupplyRequest $supplyRequest, $message = null)
    {
        $this->supplyRequest = $supplyRequest;
        $this->message = $message ?? "Your supply request has been approved!";
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to a user-specific private channel and general notifications channel
        return [
            new PrivateChannel('user.' . $this->supplyRequest->requested_by_user_id),
            new Channel('notifications'), // Also broadcast to general notifications channel
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'SupplyRequestApproved';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $item = $this->supplyRequest->item();
        
        return [
            'supply_request_id' => $this->supplyRequest->id,
            'item_name' => $item ? ($item->unit ?? $item->description ?? 'N/A') : 'N/A',
            'quantity' => $this->supplyRequest->quantity,
            'message' => $this->message,
            'status' => $this->supplyRequest->status,
            'approved_at' => $this->supplyRequest->approved_at ? $this->supplyRequest->approved_at->toISOString() : null,
        ];
    }
}
