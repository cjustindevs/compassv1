<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\User;

/** Refresh ownership labels without rewriting stored notification history. */
class EmergencyNotificationPresentation
{
    private array $alerts = [];
    private array $revisions = [];
    private const TITLES = ['Emergency risk detected', 'Emergency requires attention', 'Emergency assigned to you',
        'Shared emergency alert', 'Emergency supervision update'];
    private const FOLLOW_UP_TITLES = ['Emergency acknowledgment pending', 'Emergency follow-up needed', 'Emergency needs your coordination'];

    private function alert(Notification $notice): ?EmergencyAlert
    {
        if (!$notice->emergency_alert_id) return null;
        return $this->alerts[$notice->emergency_alert_id] ??= EmergencyAlert::with('adviser', 'session.helper.adviser')->find($notice->emergency_alert_id);
    }

    public function current(Notification $notice, User $user): bool
    {
        if (in_array($notice->title, self::FOLLOW_UP_TITLES, true) && $notice->emergency_alert_id) {
            $case = $this->alert($notice);
            return $case && $user->is_active && ($user->role === 'moderator' || ($user->role === 'adviser'
                && in_array((int)$user->id, array_map('intval', array_filter([
                    $case->adviser?->user_account_id, $case->session?->helper?->adviser?->user_account_id,
                ])), true)));
        }
        if (!in_array($notice->title, self::TITLES, true) || !$notice->emergency_alert_id) return true;
        $alert = $this->alert($notice);
        if (!$alert) return false;
        if ($notice->title === 'Emergency assigned to you' && (int) $alert->adviser?->user_account_id !== (int) $user->id) return false;
        $revision = $this->revisions[$alert->id] ??= (AuditLog::where('target_type', $alert->getTable())->where('target_id', $alert->id)
            ->where('action', 'emergency_coordinator_transferred')->latest('id')->value('id') ?? 0);
        return !$revision || str_contains($notice->popup_event_key ?? '', ':assignment:'.$revision.':')
            || str_ends_with($notice->popup_event_key ?? '', ':assignment:'.$revision);
    }

    public function present(Notification $notice, User $user): array
    {
        $original = ['title' => $notice->title, 'message' => $notice->message];
        if ($notice->notification_type !== 'emergency' || !in_array($user->role, ['adviser', 'moderator'], true)) return $original;
        $alert = $this->alert($notice);
        if (!$alert) return ['title' => 'Emergency coordination update', 'message' => 'Open the emergency case to view the current coordinator and authorized next steps.'];
        if (in_array($notice->title, self::FOLLOW_UP_TITLES, true) && $this->current($notice, $user)) {
            $instruction = match ($notice->title) {
                'Emergency acknowledgment pending' => 'Emergency #'.$alert->id.' remains unacknowledged.',
                'Emergency needs your coordination' => 'Emergency #'.$alert->id.' needs Helper coordination.',
                default => 'Emergency #'.$alert->id.' needs a documented follow-up.',
            };
            if ($notice->title === 'Emergency follow-up needed' && preg_match('/no documented progress for (\d+) minutes/', $notice->message, $elapsed)) {
                $instruction = 'Emergency #'.$alert->id.' had no documented progress for '.(int)$elapsed[1].' minutes when detected.';
            }
            return ['title' => $notice->title, 'message' => 'Current coordinator: '.app(EmergencyCoordinator::class)->name($alert->adviser)
                .'. '.$instruction.' Coordinate the next action with the responsible reviewer.'];
        }
        $content = app(EmergencyStaffNotifications::class)->content($alert, $user);
        if (!$this->current($notice, $user)) return ['title' => 'Emergency coordinator updated',
            'message' => 'Emergency #'.$alert->id.'. Current coordinator: '.app(EmergencyCoordinator::class)->name($alert->adviser).'. This earlier notification is retained as history.'];
        return $content;
    }
}
