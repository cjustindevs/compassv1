<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsychologyProfessional extends Model
{
    protected $table = 'psychology_professionals';

    protected $fillable = [
        'user_account_id',
        'first_name',
        'last_name',
        'email',
        'specialization',
        'license_number',
        'phone',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'professional_id', 'id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProfessionalNote::class, 'professional_id', 'id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: ($this->email ?? 'Professional');
    }

    public function getTitleAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

        return $name ? 'Dr. ' . $name : ($this->email ?? 'Professional');
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(
            mb_substr($this->first_name ?? '', 0, 1) . mb_substr($this->last_name ?? '', 0, 1)
        ) ?: 'PR';
    }
}
