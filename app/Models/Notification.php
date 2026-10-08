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
        'archived_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('unarchived', fn (Builder $query) => $query->whereNull('notifications.archived_at'));
    }

    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
        \Illuminate\Support\Facades\Cache::forget('unread_count_'.$this->user_account_id);
        \App\Services\SupportAudit::record('notification_archived', $this);
    }

    public function restoreToInbox(): void
    {
        $this->update(['archived_at' => null]);
        \Illuminate\Support\Facades\Cache::forget('unread_count_'.$this->user_account_id);
        \App\Services\SupportAudit::record('notification_restored', $this);
    }

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
            'session' => 'fa-comments',
            'reminder' => 'fa-clock',
            'system' => 'fa-bell',
            'update' => 'fa-star',
            default => 'fa-bell',
        };
    }
}