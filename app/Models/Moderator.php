<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Moderator extends Model
{
    protected $table = 'moderators';

    protected $fillable = [
        'user_account_id',
        'first_name',
        'last_name',
        'assigned_shift',
        'status',
        'email',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: ($this->email ?? 'Moderator');
    }
}