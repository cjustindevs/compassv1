<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class NotificationsChanged implements ShouldBroadcastNow
{
    public function __construct(public int $userId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('notifications.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'NotificationsChanged';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
