<?php

namespace App\Events;

use App\Models\Referral;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralConsentRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Referral $referral;

    public function __construct(Referral $referral)
    {
        $this->referral = $referral;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('session.' . $this->referral->session_id);
    }

    public function broadcastAs(): string
    {
        return 'ReferralConsentRequested';
    }

    public function broadcastWith(): array
    {
        return [
            'referral_id' => $this->referral->id,
            'session_id' => $this->referral->session_id,
            'status' => $this->referral->status,
            'summary' => $this->referral->referral_reason,
            'link' => '/session/chat',
        ];
    }
}