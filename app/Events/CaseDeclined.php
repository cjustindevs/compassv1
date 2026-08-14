<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseDeclined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Session $session;

    /**
     * The USER account id of the seeker whose request was declined.
     */
    public int $userId;

    public ?string $reason;

    public function __construct(Session $session, int $userId, ?string $reason = null)
    {
        $this->session = $session;
        $this->userId = $userId;
        $this->reason = $reason;
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
        return 'CaseDeclined';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'message' => 'A helper was unable to take your request. We are looking for another helper.',
            'reason' => $this->reason,
            'link' => '/request/matching',
        ];
    }
}