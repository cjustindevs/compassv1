<?php

namespace App\Observers;

use App\Models\Referral;
use App\Services\SupportAudit;

class ReferralAuditObserver
{
    public function created(Referral $referral): void
    {
        SupportAudit::record('referral_state_created', $referral, ['status' => $referral->status]);
    }

    public function updated(Referral $referral): void
    {
        foreach (['status', 'adviser_id', 'professional_id', 'help_seeker_consent'] as $field) {
            if ($referral->wasChanged($field)) {
                SupportAudit::record('referral_'.$field.'_changed', $referral, [
                    'previous' => $referral->getOriginal($field), 'current' => $referral->getAttribute($field),
                ]);
            }
        }
    }
}
