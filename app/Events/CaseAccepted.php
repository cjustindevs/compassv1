<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Session $session;

    /**
     * The USER account id of the seeker whose request was accepted.
     */
    public int $userId;

    public function __construct(Session $session, int $userId)
    {
        $this->session = $session;
        $this->userId = $userId;
    }

    /**
     * Private channel only the seeker can join.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('seeker.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'CaseAccepted';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'helper_name' => $this->session->helper?->full_name ?: 'Helper',
            'message' => 'Your request has been accepted! You can now start chatting.',
            'session_type' => $this->session->session_type,
            'link' => $this->session->session_type === 'voice' ? '/session/voice' : '/session/chat',
        ];
    }
}