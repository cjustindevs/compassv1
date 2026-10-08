<?php
namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationRetentionService
{
    public function run(): void
    {
        $thresholds = User::query()
            ->where('is_active', true)
            ->whereNotNull('auto_archive_read_days')
            ->where('auto_archive_read_days', '>', 0)
            ->pluck('auto_archive_read_days', 'id');

        foreach ($thresholds as $userId => $days) {
            $cutoff = now()->subDays($days);
            $affected = 0;

            Notification::where('user_account_id', $userId)
                ->where('status', 'read')
                ->whereNull('archived_at')
                ->where('read_at', '<', $cutoff)
                ->eachById(function (Notification $notification) use (&$affected) {
                    $notification->update(['archived_at' => now()]);
                    $affected++;
                }, 500);

            if ($affected > 0) {
                Cache::forget('unread_count_'.$userId);
                SupportAudit::record('notifications_auto_archived', User::findOrFail($userId), ['archived' => $affected, 'days' => intval($days)]);
                Log::info('Read notifications auto-archived', ['user_id' => $userId, 'archived' => $affected, 'days' => $days]);
            }
        }
    }
}