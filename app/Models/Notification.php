<?php

namespace App\Models;

use App\Events\NotificationsChanged;
use App\Services\SupportAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        static::created(function (self $notice) {
            // Emergency delivery uses short database polling. Do not serialize a
            // synchronous network broadcast for every recipient before returning.
            if ($notice->notification_type === 'emergency') {
                return;
            }
            $userId = (int) $notice->user_account_id;
            DB::afterCommit(function () use ($userId) {
                try {
                    event(new NotificationsChanged($userId));
                } catch (\Throwable $e) {
                    Log::warning('Notification live signal unavailable; inbox polling remains active.');
                }
            });
        });
    }

    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
        Cache::forget('unread_count_'.$this->user_account_id);
        SupportAudit::record('notification_archived', $this);
    }

    public function restoreToInbox(): void
    {
        $this->update(['archived_at' => null]);
        Cache::forget('unread_count_'.$this->user_account_id);
        SupportAudit::record('notification_restored', $this);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    /** Collapse only duplicate completion/documentation reminders for the same session/action. */
    public function scopeWithoutDuplicateCompletionReminders(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('notifications.title', '!=', 'Session completed')
                ->orWhereNull('notifications.link')
                ->orWhere('notifications.link', 'not like', '/helper/session/%/notes')
                ->orWhereNotExists(function ($older) {
                    $older->selectRaw('1')->from('notifications as earlier')
                        ->whereColumn('earlier.user_account_id', 'notifications.user_account_id')
                        ->whereColumn('earlier.title', 'notifications.title')
                        ->whereColumn('earlier.notification_type', 'notifications.notification_type')
                        ->whereColumn('earlier.link', 'notifications.link')
                        ->whereColumn('earlier.id', '<', 'notifications.id');
                });
        });
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
