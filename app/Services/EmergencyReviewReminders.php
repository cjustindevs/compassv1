<?php

namespace App\Services;

use App\Models\{AuditLog, EmergencyAlert, Notification, User};
use Illuminate\Support\Facades\DB;

class EmergencyReviewReminders
{
    public function run(): void
    {
        $minutes = (int) config('emergency.reminder_minutes', 0);
        if ($minutes < 1) return;

        EmergencyAlert::whereNotIn('status', ['resolved', 'closed'])->whereNull('acknowledged_at')
            ->where('created_at', '<=', now()->subMinutes($minutes))->eachById(function ($row) use ($minutes) {
                DB::transaction(function () use ($row, $minutes) {
                    $alert = EmergencyAlert::lockForUpdate()->findOrFail($row->id);
                    if ($alert->acknowledged_at || in_array($alert->status, ['resolved', 'closed'], true)) return;
                    $last = AuditLog::where('action', 'emergency_acknowledgment_reminder')
                        ->where('target_type', $alert->getTable())->where('target_id', $alert->id)->latest('id')->first();
                    if ($last && $last->created_at->gt(now()->subMinutes($minutes))) return;
                    $recipients = User::where('is_active', true)->where('role', 'moderator')->pluck('id');
                    if ($alert->adviser?->user?->is_active && $alert->adviser->user->role === 'adviser') {
                        $recipients->push($alert->adviser->user_account_id);
                    }
                    foreach ($recipients->unique() as $id) {
                        Notification::create(['user_account_id'=>$id, 'title'=>'Emergency acknowledgment pending',
                            'message'=>'An emergency review remains unacknowledged. Open the emergency queue to coordinate support.',
                            'notification_type'=>'emergency', 'link'=>$id === $alert->adviser?->user_account_id ? '/adviser/emergencies' : '/moderator/emergency']);
                    }
                    SupportAudit::record('emergency_acknowledgment_reminder', $alert, ['recipient_count'=>$recipients->unique()->count()]);
                }, 3);
            });
    }
}
