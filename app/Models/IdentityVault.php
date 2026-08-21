<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVault extends Model
{
    protected $table = 'identity_vault';

    protected $fillable = [
        'seeker_id',
        'real_name',
        'phone_number',
        'email',
        'address',
        'released_by',
        'released_date',
        'released_reason',
        'approved_by',
        'emergency_override',
    ];

    protected $casts = [
        'released_date' => 'datetime',
        'emergency_override' => 'boolean',
    ];

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(HelpSeeker::class, 'seeker_id', 'id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by', 'id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'approved_by', 'id');
    }
}