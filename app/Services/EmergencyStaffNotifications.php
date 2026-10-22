<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Stored notices for every active emergency coordinator; no WebSocket dependency. */
class EmergencyStaffNotifications
{
    public function deliver(EmergencyAlert $alert, bool $freshChatReport = false): void
    {
        DB::transaction(function () use ($alert, $freshChatReport) {
            $alert = EmergencyAlert::lockForUpdate()->findOrFail($alert->id);
            if (in_array($alert->status, ModeratorEmergencyCases::TERMINAL, true) || $alert->archived_at) {
                return;
            }
            $owner = app(EmergencyCoordinator::class)->ensure($alert);
            $revision = AuditLog::where('target_type', $alert->getTable())->where('target_id', $alert->id)
                ->where('action', 'emergency_coordinator_transferred')->latest('id')->value('id');
            $popupEvent = 'emergency:alert:'.$alert->id.($revision ? ':assignment:'.$revision : '')
                .($freshChatReport ? ':report:'.Str::uuid() : '');
            $ownerDelivered = false;
            foreach (User::where('is_active', true)->whereIn('role', ['adviser', 'moderator'])->get(['id', 'role']) as $user) {
                $adviser = $user->role === 'adviser';
                $link = $adviser ? route('adviser.emergencies.show', $alert->id, false) : '/moderator/emergency';
                ['title' => $title, 'message' => $message] = $this->content($alert, $user);
                $exists = Notification::withoutGlobalScopes()->where('user_account_id', $user->id)
                    ->where('notification_type', 'emergency')->where(function ($q) use ($popupEvent, $revision, $freshChatReport, $alert, $link) {
                        $q->where('popup_event_key', $popupEvent);
                        if (!$freshChatReport) $q->orWhere('popup_event_key', 'like', $popupEvent.':report:%');
                        // Upgrading labels must not resend legacy alerts or archived mail.
                        if (!$revision && !$freshChatReport) $q->orWhere(fn ($old) => $old
                            ->where(fn ($id) => $id->where('emergency_alert_id', $alert->id)->orWhere(fn ($legacy) => $legacy
                                ->whereNull('emergency_alert_id')->where('link', $link)->where('message', 'like', 'Emergency #'.$alert->id.' %')))
                            ->whereIn('title', ['Emergency risk detected', 'Emergency requires attention', 'Emergency assigned to you', 'Shared emergency alert', 'Emergency supervision update']));
                    })->exists();
                if ($freshChatReport || ! $exists) {
                    $notice = Notification::create(['user_account_id' => $user->id, 'emergency_alert_id' => $alert->id, 'title' => $title, 'message' => $message,
                        'notification_type' => 'emergency', 'status' => 'unread', 'type_icon' => 'fa-life-ring', 'link' => $link, 'popup_event_key' => $popupEvent]);
                    // Queue only new events. Provider requests never delay case creation,
                    // and reconciliation does not email old displayed notices again.
                    app(StaffMailService::class)->enqueue($user, 'emergency-alert:'.$alert->id.':notice:'.$notice->id,
                        'COMPASS - '.$title, 'Emergency #'.$alert->id.'. Sign in to view your role and the current coordinator. Delivery does not confirm a response.');
                    SupportAudit::record('emergency_notice_delivered', $alert, ['recipient_user_id' => $user->id,
                        'coordinator_adviser_id' => $owner?->id, 'event_key' => $popupEvent]);
                }
                if ($owner && (int) $owner->user_account_id === (int) $user->id) $ownerDelivered = true;
            }
            if ($ownerDelivered && (! $alert->adviser_notified || ! $alert->notification_sent)) {
                $alert->forceFill(['adviser_notified' => true, 'adviser_notified_at' => now(), 'notification_sent' => true,
                    'notification_sent_at' => now(), 'notification_result' => 'in_app_delivered', 'status' => $alert->status === 'pending' ? 'notified' : $alert->status])->save();
            }
        }, 3);
    }

    public function content(EmergencyAlert $alert, User $user): array
    {
        $coordinator = app(EmergencyCoordinator::class)->name($alert->adviser);
        $prefix = 'Emergency #'.$alert->id.'. Coordinator: '.$coordinator.'. ';
        if ($user->role === 'moderator') return ['title' => 'Emergency requires attention',
            'message' => $prefix.'Operational coordination: review Helper availability and support progress.'];
        if ((int) $alert->adviser?->user_account_id === (int) $user->id) return ['title' => 'Emergency assigned to you',
            'message' => $prefix.'You are responsible for the emergency review and follow-up.'];
        if ((int) $alert->session?->helper?->adviser?->user_account_id === (int) $user->id) return ['title' => 'Emergency supervision update',
            'message' => $prefix.'You supervise the Helper. Coordinate with the responsible reviewer.'];
        return ['title' => 'Shared emergency alert', 'message' => $prefix.'Shared awareness only; this case is not assigned to you.'];
    }

    public function followUpRecipients(EmergencyAlert $alert)
    {
        $ids = array_filter([$alert->adviser?->user_account_id, $alert->session?->helper?->adviser?->user_account_id]);
        return User::where('is_active', true)->where(fn ($q) => $q->where('role', 'moderator')
            ->orWhere(fn ($advisers) => $advisers->where('role', 'adviser')->whereIn('id', $ids)))->get();
    }

    public function reconcileOpen(): void
    {
        EmergencyAlert::whereNotIn('status', ModeratorEmergencyCases::TERMINAL)->whereNull('archived_at')->eachById(fn ($alert) => $this->deliver($alert));
    }
}
