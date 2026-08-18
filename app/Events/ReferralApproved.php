<?php

namespace App\Events;

use App\Models\Referral;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralApproved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Referral $referral;

    /**
     * The USER account id of the helper whose referral was approved.
     */
    public int $userId;

    public function __construct(Referral $referral, int $userId)
    {
        $this->referral = $referral;
        $this->userId = $userId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('helper.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'ReferralApproved';
    }

    public function broadcastWith(): array
    {
        return [
            'referral_id' => $this->referral->id,
            'session_id' => $this->referral->session_id,
            'seeker_alias' => $this->referral->session?->seeker?->generated_alias ?? 'Anonymous',
            'professional_name' => $this->referral->professional?->full_name ?? null,
            'priority_level' => ucfirst($this->referral->priority_level ?? 'Low'),
            'message' => 'Your referral has been approved by the adviser.',
            'link' => '/helper/cases',
        ];
    }
}