<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Consent events are append-only.'));
        static::deleting(fn()=>throw new \LogicException('Consent events are append-only.'));
    }

    protected $table = 'consent_records';

    protected $fillable = [
        'purpose',
        'scope',
        'decision',
        'session_id',
        'referral_id',

        'seeker_id',
        'document_type',
        'ip_address',
        'user_agent',
        'version',
        'consent_given',
        'consent_date',
        'withdrawn',
        'withdrawn_date'
    ];

    protected $casts = [
        'consent_date' => 'datetime',
        'withdrawn_date' => 'datetime',
        'consent_given' => 'boolean',
        'withdrawn' => 'boolean'
    ];

    public function seeker()
    {
        return $this->belongsTo(HelpSeeker::class, 'seeker_id', 'id');
    }
}
