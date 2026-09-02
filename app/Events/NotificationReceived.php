<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;

    public string $message;

    public string $type;

    public ?string $link;

    public int $unreadCount;

    public function __construct(int $userId, string $message, string $type = 'info', ?string $link = null, ?int $unreadCount = null)
    {
        $this->userId = $userId;
        $this->message = $message;
        $this->type = $type;
        $this->link = $link;
        $this->unreadCount = $unreadCount ?? (User::find($userId)?->unreadNotifications()->count() ?? 0);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('helper.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'NotificationReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'link' => $this->link,
            'unread_count' => $this->unreadCount,
        ];
    }
}
