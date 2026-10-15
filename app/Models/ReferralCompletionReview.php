<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralCompletionReview extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['summary' => 'encrypted', 'follow_up_notes' => 'encrypted', 'review_notes' => 'encrypted', 'appointment_disposition_notes' => 'encrypted', 'follow_up_required' => 'boolean', 'reviewed_at' => 'datetime'];

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function professional()
    {
        return $this->belongsTo(PsychologyProfessional::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
