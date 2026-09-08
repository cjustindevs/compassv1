<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpSeeker extends Model
{
    protected $table = 'help_seekers';

    protected $fillable = [
        'user_account_id',
        'generated_alias',
        'pseudo_id',
        'registration_ip',
        'is_verified',
        'verified_at',
        'age',
        'gender',
        'current_risk_level',
        'risk_last_updated',
        'has_emergency',
        'last_emergency_at',
        'account_created'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'account_created' => 'datetime',
        'risk_last_updated' => 'datetime',
        'has_emergency' => 'boolean',
        'last_emergency_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'seeker_id', 'id');
    }

    public function referrals(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Referral::class, Session::class, 'seeker_id', 'session_id');
    }

    public function storeIdentityInVault(Referral $referral, array $identityData): void
    {
        abort_unless($referral->session?->seeker_id === $this->id, 403);
        app(\App\Services\IdentityVaultService::class)->storeForReferral($referral, $identityData);
    }

    public function getIdentityFromVault(Referral $referral): array
    {
        abort_unless($referral->session?->seeker_id === $this->id, 403);
        return app(\App\Services\IdentityVaultService::class)->readForReferral($referral);
    }

    public function consentRecords()
    {
        return $this->hasMany(ConsentRecord::class, 'seeker_id', 'id');
    }

    public function evaluations()
    {
        return $this->hasManyThrough(
            HelpSeekerEvaluation::class,
            Session::class,
            'seeker_id',
            'session_id',
            'id',
            'id'
        );
    }

    public function queueRequests()
    {
        return $this->hasMany(QueueRequest::class, 'seeker_id', 'id');
    }

    public function screeningResponses()
    {
        return $this->hasMany(ScreeningResponse::class, 'seeker_id', 'id');
    }

    public function emergencyAlerts()
    {
        return $this->hasMany(EmergencyAlert::class, 'seeker_id', 'id');
    }
}
