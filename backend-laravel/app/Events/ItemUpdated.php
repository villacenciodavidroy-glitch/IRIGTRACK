<?php

namespace App\Events;

use App\Models\Item;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Http\Resources\V1\ItemResource;

class ItemUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $item;

    /**
     * Create a new event instance.
     */
    public function __construct(Item $item)
    {
        $this->item = $item;
        // Load relationships for the broadcast (use try-catch to handle missing relationships)
        try {
            $this->item->load(['category', 'location', 'condition', 'condition_number', 'qrCode', 'user']);
        } catch (\Exception $e) {
            \Log::warning("Failed to load some relationships for ItemUpdated broadcast: " . $e->getMessage());
            // Continue anyway, relationships might be optional
        }
    }

    /**
     * Determine if this event should be broadcast.
     * Only broadcast if broadcasting is configured, otherwise skip silently.
     */
    public function shouldBroadcast(): bool
    {
        // Always return true - let Laravel handle driver checking
        // This ensures the event is always broadcast if driver is configured
        $driver = config('broadcasting.default');
        \Log::info("ItemUpdated::shouldBroadcast() called. Driver: {$driver}");
        return true; // Always broadcast if event is fired
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        \Log::info("ItemUpdated event broadcasting on 'inventory' channel for item: {$this->item->id}");
        return [
            new Channel('inventory'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ItemUpdated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        try {
            $resource = new ItemResource($this->item);
            $data = [
                'item' => $resource->toArray(request()),
            ];
            \Log::info("ItemUpdated broadcast data prepared for item: {$this->item->id}, Quantity: {$this->item->quantity}");
            return $data;
        } catch (\Exception $e) {
            \Log::error("Failed to serialize item for broadcast: " . $e->getMessage());
            // Return basic item data if resource fails
            $data = [
                'item' => [
                    'id' => $this->item->id,
                    'uuid' => $this->item->uuid,
                    'quantity' => $this->item->quantity,
                    'unit' => $this->item->unit,
                    'description' => $this->item->description,
                ],
            ];
            \Log::info("ItemUpdated broadcast using fallback data for item: {$this->item->id}, Quantity: {$this->item->quantity}");
            return $data;
        }
    }
}
