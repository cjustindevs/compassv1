<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_account_id',
        'title',
        'message',
        'notification_type',
        'type_icon',
        'link',
        'status',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', 'unread');
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('notification_type', $type) : $query;
    }

    public function getIsReadAttribute(): bool
    {
        return $this->status === 'read';
    }

    public function markAsRead(): void
    {
        if ($this->status !== 'read') {
            $this->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }
    }

    public function getTypeIconAttribute(?string $value): string
    {
        if ($value) {
            return $value;
        }

        return match ($this->notification_type) {
            'session' => '💬',
            'reminder' => '⏰',
            'system' => '🔔',
            'update' => '🎉',
            default => '🔔',
        };
    }
}