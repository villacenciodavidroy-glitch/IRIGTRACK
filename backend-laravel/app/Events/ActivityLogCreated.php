<?php

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityLogCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $activityLog;

    /**
     * Create a new event instance.
     */
    public function __construct(ActivityLog $activityLog)
    {
        $this->activityLog = $activityLog;
        
        // Load user relationship for the broadcast
        try {
            $this->activityLog->load('user');
        } catch (\Exception $e) {
            \Log::warning("Failed to load user relationship for ActivityLogCreated broadcast: " . $e->getMessage());
        }
    }

    /**
     * Determine if this event should be broadcast.
     */
    public function shouldBroadcast(): bool
    {
        return true;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('activity-logs'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ActivityLogCreated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $user = $this->activityLog->user;
        
        return [
            'id' => $this->activityLog->id,
            'name' => $user ? $user->fullname : 'Unknown User',
            'action' => $this->activityLog->action,
            'description' => $this->activityLog->description,
            'date' => $this->activityLog->created_at->format('d-m-Y'),
            'time' => $this->activityLog->created_at->format('h:i A'),
            'role' => $user ? ucfirst($user->role) : 'Guest',
            'created_at' => $this->activityLog->created_at->toISOString(),
        ];
    }
}


