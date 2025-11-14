<?php

namespace App\Events;

use App\Models\Notification;
use App\Http\Resources\V1\NotificationResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    /**
     * Create a new event instance.
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
        // Load relationships for the broadcast
        try {
            $this->notification->load(['item', 'borrowRequest']);
        } catch (\Exception $e) {
            \Log::warning("Failed to load some relationships for NotificationCreated broadcast: " . $e->getMessage());
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('notifications'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        try {
            $resource = new NotificationResource($this->notification);
            $unreadCount = Notification::where('is_read', false)->count();
            
            return [
                'notification' => $resource->toArray(request()),
                'unread_count' => $unreadCount,
            ];
        } catch (\Exception $e) {
            \Log::error("Failed to serialize notification for broadcast: " . $e->getMessage());
            return [
                'notification' => [
                    'id' => $this->notification->notification_id,
                    'message' => $this->notification->message,
                    'type' => $this->notification->type ?? 'low_stock',
                    'isRead' => $this->notification->is_read ?? false,
                ],
                'unread_count' => 0,
            ];
        }
    }
}
