<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $table = 'referrals';

    protected $fillable = [
        'session_id',
        'helper_id',
        'moderator_id',
        'adviser_id',
        'professional_id',
        'priority_level',
        'help_seeker_consent',
        'identity_disclosed',
        'referral_reason',
        'referral_date',
        'status',
        'closed_date',
    ];

    protected $casts = [
        'help_seeker_consent' => 'boolean',
        'identity_disclosed' => 'boolean',
        'referral_date' => 'datetime',
        'closed_date' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'helper_id', 'id');
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'adviser_id', 'id');
    }
}