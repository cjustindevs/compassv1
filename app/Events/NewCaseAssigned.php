<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewCaseAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Session $session;

    /**
     * The USER account id of the helper who was assigned the case.
     * This is what Echo uses to subscribe to the private helper channel.
     */
    public int $userId;

    public function __construct(Session $session, int $userId)
    {
        $this->session = $session;
        $this->userId = $userId;
    }

    /**
     * Private channel only the assigned helper can join.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('helper.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'NewCaseAssigned';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'case_id' => $this->session->reference_number,
            'seeker_alias' => $this->session->seeker?->generated_alias ?? 'Anonymous',
            'concern' => $this->session->concern?->concern_name ?? 'General',
            'risk_level' => ucfirst($this->session->risk_level ?? 'Low'),
            'created_at' => $this->session->created_at?->diffForHumans(),
            'link' => '/helper/cases',
        ];
    }
}
