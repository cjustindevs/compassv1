<?php

namespace App\Events;

use App\Models\Referral;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralRecommended implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Referral $referral;

    /**
     * The USER account id of the adviser who should review the referral.
     */
    public int $userId;

    public function __construct(Referral $referral, int $userId)
    {
        $this->referral = $referral;
        $this->userId = $userId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('adviser.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'ReferralRecommended';
    }

    public function broadcastWith(): array
    {
        return [
            'referral_id' => $this->referral->id,
            'session_id' => $this->referral->session_id,
            'seeker_alias' => $this->referral->session?->seeker?->generated_alias ?? 'Anonymous',
            'helper_name' => $this->referral->helper?->full_name ?? 'A peer helper',
            'priority_level' => ucfirst($this->referral->priority_level ?? 'Low'),
            'reason' => $this->referral->referral_reason,
            'created_at' => $this->referral->created_at?->diffForHumans(),
            'link' => '/adviser/referrals',
        ];
    }
}