<?php

namespace App\Services;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Stored notices for every active emergency coordinator; no WebSocket dependency. */
class EmergencyStaffNotifications
{
    public function deliver(EmergencyAlert $alert, bool $freshChatReport = false): void
    {
        DB::transaction(function () use ($alert, $freshChatReport) {
            $alert = EmergencyAlert::lockForUpdate()->findOrFail($alert->id);
            if (in_array($alert->status, ['resolved', 'closed'], true) || $alert->archived_at) {
                return;
            }
            if (! $alert->adviser?->user?->is_active || $alert->adviser?->user?->role !== 'adviser') {
                $eligible = fn ($q) => $q->where('role', 'adviser')->where('is_active', true);
                $owner = Adviser::whereHas('user', $eligible)->find($alert->session?->review_adviser_id)
                    ?? Adviser::whereHas('user', $eligible)->find($alert->session?->helper?->adviser_id)
                    ?? Adviser::whereHas('user', $eligible)->oldest('id')->first();
                if ($owner) {
                    $alert->update(['adviser_id' => $owner->id]);
                    $alert->unsetRelation('adviser');
                    $alert->session?->update(['review_adviser_id' => $owner->id]);
                }
            }
            foreach (User::where('is_active', true)->whereIn('role', ['adviser', 'moderator'])->get(['id', 'role']) as $user) {
                $adviser = $user->role === 'adviser';
                $link = $adviser ? route('adviser.emergencies.show', $alert->id, false) : '/moderator/emergency';
                $title = $adviser ? 'Emergency risk detected' : 'Emergency requires attention';
                $message = 'Emergency #'.$alert->id.' requires staff attention. Open the emergency case to coordinate support.';
                $exists = Notification::withoutGlobalScopes()->where('user_account_id', $user->id)
                    ->where('notification_type', 'emergency')->where('link', $link)->where('title', $title)->where('message', $message)->exists();
                if ($freshChatReport || ! $exists) {
                    Notification::create(['user_account_id' => $user->id, 'emergency_alert_id' => $alert->id, 'title' => $title, 'message' => $message,
                        'notification_type' => 'emergency', 'status' => 'unread', 'type_icon' => 'fa-life-ring', 'link' => $link]);
                }
            }
            if ($alert->adviser?->user?->is_active && $alert->adviser->user->role === 'adviser' && (! $alert->adviser_notified || ! $alert->notification_sent)) {
                $alert->forceFill(['adviser_notified' => true, 'adviser_notified_at' => now(), 'notification_sent' => true,
                    'notification_sent_at' => now(), 'notification_result' => 'in_app_delivered', 'status' => $alert->status === 'pending' ? 'notified' : $alert->status])->save();
            }
        }, 3);
    }

    public function reconcileOpen(): void
    {
        EmergencyAlert::whereNotIn('status', ['resolved', 'closed'])->whereNull('archived_at')->eachById(fn ($alert) => $this->deliver($alert));
    }
}
