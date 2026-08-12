<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Helper extends Model
{
    protected $table = 'helpers';

    protected $fillable = [
        'user_account_id',
        'adviser_id',
        'first_name',
        'last_name',
        'email',
        'status',
        'competency_level',
        'max_concurrent_sessions',
        'specializations',
    ];

    protected $casts = [
        'competency_level' => 'integer',
        'max_concurrent_sessions' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'helper_id', 'id');
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(Session::class, 'helper_id', 'id')
            ->whereIn('session_status', ['scheduled', 'active']);
    }

    public function queueRequests(): HasMany
    {
        return $this->hasMany(QueueRequest::class, 'assigned_helper_id', 'id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}