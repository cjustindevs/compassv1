<?php

namespace App\Services;

use App\Models\Helper;
use Illuminate\Support\Facades\DB;

class HelperDutyCandidates
{
    public function allows(Helper $helper): bool
    {
        $user = $helper->user;
        return $user && $user->is_active && $user->role === 'helper'
            && $helper->verification_status === 'verified' && $helper->training_verified
            && $helper->adviser_id && $helper->adviser?->user?->is_active && !$helper->is_under_review;
    }
}
