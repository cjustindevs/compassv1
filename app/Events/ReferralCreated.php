<?php

namespace App\Events;

use App\Models\Referral;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Referral $referral) {}
}
