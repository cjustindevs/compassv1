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
        $this->followUpAcknowledged();
        $minutes = (int) config('emergency.reminder_minutes', 0);
        if ($minutes < 1) {
            return;
        }

        EmergencyAlert::whereNotIn('status', ['resolved', 'closed','cancelled'])->whereNull('archived_at')->whereNull('acknowledged_at')
            ->where('created_at', '<=', now()->subMinutes($minutes))->eachById(function ($row) use ($minutes) {
                DB::transaction(function () use ($row, $minutes) {
                    $alert = EmergencyAlert::lockForUpdate()->findOrFail($row->id);
                    if ($alert->acknowledged_at || $alert->archived_at || in_array($alert->status, ['resolved', 'closed','cancelled'], true)) {
                        return;
                    }
                    $last = AuditLog::where('action', 'emergency_acknowledgment_reminder')
                        ->where('target_type', $alert->getTable())->where('target_id', $alert->id)->latest('id')->first();
                    if ($last && $last->created_at->gt(now()->subMinutes($minutes))) {
                        return;
                    }
                    $recipients = app(EmergencyStaffNotifications::class)->followUpRecipients($alert);
                    $coordinator = app(EmergencyCoordinator::class)->name($alert->adviser);
                    foreach ($recipients as $user) {
                        Notification::create(['user_account_id' => $user->id, 'emergency_alert_id' => $alert->id, 'title' => 'Emergency acknowledgment pending',
                            'message' => 'Coordinator: '.$coordinator.'. Emergency #'.$alert->id.' remains unacknowledged. Coordinate with the responsible reviewer.',
                            'notification_type' => 'emergency', 'popup_event_key'=>'emergency:alert:'.$alert->id,
                            'link' => $user->role === 'adviser' ? route('adviser.emergencies.show', $alert->id, false) : '/moderator/emergency']);
                    }
                    SupportAudit::record('emergency_acknowledgment_reminder', $alert, ['recipient_count' => $recipients->count()]);
                }, 3);
            });
    }

    private function followUpAcknowledged(): void
    {
        $minutes = (int) config('emergency.progress_reminder_minutes', 15);
        if ($minutes < 1) return;
        EmergencyAlert::whereNotIn('status', ['resolved','closed','cancelled'])->whereNull('archived_at')
            ->whereNotNull('acknowledged_at')->eachById(function ($row) use ($minutes) {
                DB::transaction(function () use ($row, $minutes) {
                    $alert = EmergencyAlert::lockForUpdate()->findOrFail($row->id);
                    if (!$alert->acknowledged_at || $alert->archived_at || in_array($alert->status, ['resolved','closed','cancelled'], true)) return;
                    $lastAction = DB::table('emergency_review_actions')->where('emergency_alert_id',$alert->id)->orderByDesc('id')->first();
                    $at = $lastAction ? \Illuminate\Support\Carbon::parse($lastAction->created_at) : $alert->acknowledged_at;
                    $handoff = AuditLog::where('action', 'emergency_coordinator_transferred')->where('target_type', $alert->getTable())
                        ->where('target_id', $alert->id)->latest('id')->first();
                    if ($handoff && $handoff->created_at->gt($at)) $at = $handoff->created_at;
                    if ($alert->professional_referred_at && $alert->professional_referred_at->gt($at)) $at = $alert->professional_referred_at;
                    if ($at->gt(now()->subMinutes($minutes))) return;
                    $progressKey = ($lastAction?->id ?? 0).':'.$at->timestamp;
                    if (AuditLog::where('action','emergency_progress_reminder')->where('target_type',$alert->getTable())
                        ->where('target_id',$alert->id)->where('metadata->progress_key',$progressKey)->exists()) return;
                    $elapsed = (int)$at->diffInMinutes(now());
                    $recipients = app(EmergencyStaffNotifications::class)->followUpRecipients($alert);
                    $coordinator = app(EmergencyCoordinator::class)->name($alert->adviser);
                    foreach ($recipients as $user) Notification::create([
                        'user_account_id'=>$user->id, 'emergency_alert_id'=>$alert->id,
                        'title'=>'Emergency follow-up needed', 'message'=>'Coordinator: '.$coordinator.'. Case #'.$alert->id.' has no documented progress for '.$elapsed.' minutes. Coordinate the next action with the responsible reviewer.',
                        'notification_type'=>'emergency', 'popup_event_key'=>'emergency:progress:'.$alert->id.':'.$progressKey,
                        'link'=>$user->role==='adviser' ? route('adviser.emergencies.show',$alert->id,false) : '/moderator/emergency/cases/emergency_alerts/'.$alert->id,
                    ]);
                    SupportAudit::record('emergency_progress_reminder',$alert,['elapsed_minutes'=>$elapsed,'last_action_at'=>$at->toIso8601String(),'progress_key'=>$progressKey]);
                },3);
            });
    }
}
