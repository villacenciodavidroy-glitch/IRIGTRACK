<?php

namespace App\Events;

use App\Models\Item;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Http\Resources\V1\ItemResource;

class ItemBorrowed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $item;
    public $borrowedQuantity;
    public $borrowedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(Item $item, $borrowedQuantity, $borrowedBy)
    {
        $this->item = $item;
        $this->borrowedQuantity = $borrowedQuantity;
        $this->borrowedBy = $borrowedBy;
        
        // Load relationships for the broadcast
        try {
            $this->item->load(['category', 'location', 'condition', 'conditionNumber', 'qrCode', 'user']);
        } catch (\Exception $e) {
            \Log::warning("Failed to load some relationships for ItemBorrowed broadcast: " . $e->getMessage());
        }
    }

    /**
     * Determine if this event should be broadcast.
     */
    public function shouldBroadcast(): bool
    {
        $driver = config('broadcasting.default');
        \Log::info("ItemBorrowed::shouldBroadcast() called. Driver: {$driver}");
        return true;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        \Log::info("ItemBorrowed event broadcasting on 'inventory' channel for item: {$this->item->id}");
        return [
            new Channel('inventory'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ItemBorrowed';
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
                'borrowed_quantity' => $this->borrowedQuantity,
                'borrowed_by' => $this->borrowedBy,
            ];
            \Log::info("ItemBorrowed broadcast data prepared for item: {$this->item->id}, New Quantity: {$this->item->quantity}");
            return $data;
        } catch (\Exception $e) {
            \Log::error("Failed to serialize item for ItemBorrowed broadcast: " . $e->getMessage());
            // Return basic item data if resource fails
            $data = [
                'item' => [
                    'id' => $this->item->id,
                    'uuid' => $this->item->uuid,
                    'quantity' => $this->item->quantity,
                    'unit' => $this->item->unit,
                    'description' => $this->item->description,
                ],
                'borrowed_quantity' => $this->borrowedQuantity,
                'borrowed_by' => $this->borrowedBy,
            ];
            \Log::info("ItemBorrowed broadcast using fallback data for item: {$this->item->id}, New Quantity: {$this->item->quantity}");
            return $data;
        }
    }
}

