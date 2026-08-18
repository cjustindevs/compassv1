<?php

namespace App\Events;

use App\Models\IncidentReport;
use App\Models\Session;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Session $session;

    public IncidentReport $incident;

    /**
     * The USER account id of the adviser who should be alerted.
     */
    public int $userId;

    public function __construct(Session $session, IncidentReport $incident, int $userId)
    {
        $this->session = $session;
        $this->incident = $incident;
        $this->userId = $userId;
    }

    /**
     * Private channel only the adviser can join.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('adviser.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'EmergencyTriggered';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'incident_id' => $this->incident->id,
            'seeker_alias' => $this->session->seeker?->generated_alias ?? 'Anonymous',
            'helper_name' => $this->session->helper?->full_name ?? 'A peer helper',
            'risk_level' => ucfirst($this->session->risk_level ?? 'Emergency'),
            'description' => $this->incident->description,
            'created_at' => $this->incident->created_at?->diffForHumans(),
            'link' => '/adviser/dashboard',
        ];
    }
}