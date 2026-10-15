<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmergencyReviewReminders
{
    public function run(): void
    {
        // Repair missed recipients even when timed acknowledgment reminders are disabled.
        app(EmergencyStaffNotifications::class)->reconcileOpen();
        $minutes = (int) config('emergency.reminder_minutes', 0);
        if ($minutes < 1) {
            return;
        }

        EmergencyAlert::whereNotIn('status', ['resolved', 'closed'])->whereNull('acknowledged_at')
            ->where('created_at', '<=', now()->subMinutes($minutes))->eachById(function ($row) use ($minutes) {
                DB::transaction(function () use ($row, $minutes) {
                    $alert = EmergencyAlert::lockForUpdate()->findOrFail($row->id);
                    if ($alert->acknowledged_at || in_array($alert->status, ['resolved', 'closed'], true)) {
                        return;
                    }
                    $last = AuditLog::where('action', 'emergency_acknowledgment_reminder')
                        ->where('target_type', $alert->getTable())->where('target_id', $alert->id)->latest('id')->first();
                    if ($last && $last->created_at->gt(now()->subMinutes($minutes))) {
                        return;
                    }
                    $recipients = User::where('is_active', true)->whereIn('role', ['adviser', 'moderator'])->get();
                    foreach ($recipients as $user) {
                        Notification::create(['user_account_id' => $user->id, 'emergency_alert_id' => $alert->id, 'title' => 'Emergency acknowledgment pending',
                            'message' => 'An emergency review remains unacknowledged. Open the emergency case to coordinate support.',
                            'notification_type' => 'emergency', 'link' => $user->role === 'adviser' ? route('adviser.emergencies.show', $alert->id, false) : '/moderator/emergency']);
                    }
                    SupportAudit::record('emergency_acknowledgment_reminder', $alert, ['recipient_count' => $recipients->count()]);
                }, 3);
            });
    }
}
