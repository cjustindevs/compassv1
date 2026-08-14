<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewHelperAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Session $session;

    /**
     * The USER account id of the seeker whose request was reassigned.
     */
    public int $userId;

    public ?string $helperName;

    public ?int $competencyLevel;

    public function __construct(Session $session, int $userId, ?string $helperName = null, ?int $competencyLevel = null)
    {
        $this->session = $session;
        $this->userId = $userId;
        $this->helperName = $helperName;
        $this->competencyLevel = $competencyLevel;
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
        return 'NewHelperAssigned';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'helper_name' => $this->helperName,
            'competency_level' => $this->competencyLevel,
            'message' => 'A new helper has been assigned to you!',
            'link' => '/request/matching',
        ];
    }
}