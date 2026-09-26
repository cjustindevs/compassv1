<?php

namespace App\Services;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;

class AdviserScope
{
    public function actor(?User $user = null): Adviser
    {
        $user ??= auth()->user();
        abort_unless($user && $user->is_active && $user->role === 'adviser' && $user->adviser, 403);

        return $user->adviser;
    }

    public function session(Session $session): void
    {
        $id = $this->actor()->id;
        abort_unless($session->helper?->adviser_id === $id || $session->review_adviser_id === $id
            || Referral::where('session_id', $session->id)->where('adviser_id', $id)
                ->whereNotIn('status', [Referral::STATUS_CLOSED, Referral::STATUS_DECLINED, Referral::STATUS_COMPLETED])->exists(), 403);
    }

    public function referral(Referral $referral): void
    {
        $id = $this->actor()->id;
        abort_unless(Referral::whereKey($referral->id)->forAdviser($id)->exists(), 403);
    }

    public function emergency(EmergencyAlert $alert): void
    {
        $id = $this->actor()->id;
        abort_unless($alert->adviser_id === $id || $alert->session?->helper?->adviser_id === $id, 403);
    }
}
