<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModeratorAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The USER account id of the moderator.
     */
    public int $userId;

    public string $type;

    public string $title;

    public string $message;

    public ?string $link;

    public function __construct(int $userId, string $type, string $title, string $message, ?string $link = null)
    {
        $this->userId = $userId;
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
        $this->link = $link;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('moderator.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'ModeratorAlert';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
        ];
    }
}