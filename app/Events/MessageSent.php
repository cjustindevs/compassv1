<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * The private channel only the seeker and helper of this session can join.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('session.' . $this->message->session_id);
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'message' => $this->message->message_text,
            'sender_id' => $this->message->sender_id,
            'sender' => $this->message->sender_role,
            'sender_role' => $this->message->sender_role,
            'sender_name' => $this->message->senderName(),
            'sent_datetime' => $this->message->time_formatted,
        ];
    }
}
