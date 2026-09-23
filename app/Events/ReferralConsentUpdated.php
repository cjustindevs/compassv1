<?php

namespace App\Events;

use App\Models\Referral;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralConsentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Referral $referral;

    public bool $accepted;

    public function __construct(Referral $referral, bool $accepted)
    {
        $this->referral = $referral;
        $this->accepted = $accepted;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('session.' . $this->referral->session_id);
    }

    public function broadcastAs(): string
    {
        return 'ReferralConsentUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'referral_id' => $this->referral->id,
            'session_id' => $this->referral->session_id,
            'status' => $this->referral->status,
            'accepted' => $this->accepted,
            'help_seeker_consent' => (bool) $this->referral->help_seeker_consent,
            'link' => '/session/chat',
        ];
    }
}