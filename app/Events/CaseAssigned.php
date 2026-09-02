<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CaseAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Session $session;

    public int $userId;

    public function __construct(Session $session, int $userId)
    {
        $this->session = $session;
        $this->userId = $userId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('helper.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'CaseAssigned';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'helper_name' => $this->session->helper?->full_name ?? 'Helper',
            'seeker_name' => $this->session->seeker?->generated_alias ?? 'Seeker',
            'pending_count' => Session::where('helper_id', $this->session->helper_id)
                ->whereIn('session_status', ['helper_assigned'])
                ->count(),
            'message' => 'A new case has been assigned to you.',
        ];
    }
}
