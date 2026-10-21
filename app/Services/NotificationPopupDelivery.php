<?php

namespace App\Services;

use App\Models\{Notification, Referral, Session, User};
use Illuminate\Support\Facades\DB;

class NotificationPopupDelivery
{
    public const FRESH_MINUTES = 5;

    public function __construct(private EmergencyNoticeState $emergencies) {}

    public function key(Notification $notice, User $user): string
    {
        if ($notice->popup_event_key) return $notice->popup_event_key;
        if ($notice->notification_type === 'emergency' && $case = $this->emergencies->key($notice, $user)) return 'emergency:'.$case;
        // Identical wording can describe a new review or appointment change. Only an explicit
        // source event key may group separate rows; never infer event identity from their text.
        return 'notification:'.$notice->id;
    }

    public function actionable(Notification $notice, User $user): bool
    {
        if (preg_match('/^queue-wait:(\d+)$/', $notice->popup_event_key ?? '', $match)) {
            $queue = \App\Models\QueueRequest::find((int) $match[1]);
            return $queue && $queue->request_status === 'waiting' && !$queue->assigned_helper_id
                && Session::where('queue_request_id', $queue->id)->where('session_status', 'waiting')->whereNull('helper_id')->exists();
        }
        if ($notice->notification_type === 'emergency' && in_array($user->role, ['adviser', 'moderator', 'professional'], true)) {
            return $this->emergencies->key($notice, $user) !== null;
        }
        if (preg_match('#/(?:referral|referrals)/(\d+)(?:/|$)#', $notice->link ?? '', $match)) {
            $referral = Referral::find((int) $match[1]);
            if ($referral && in_array($referral->status, ['declined', 'completed', 'closed', 'cancelled'], true)
                && !preg_match('/completed|completion|declined|closed|cancelled|withdrawn|resolved/i', $notice->title)) return false;
        }
        if ($notice->notification_type === 'assignment' && preg_match('#/helper/(?:session|cases)/(\d+)(?:/|$)#', $notice->link ?? '', $match)) {
            $session = Session::find((int) $match[1]);
            if ($session && (!in_array($session->session_status, ['helper_assigned', 'active', 'scheduled'], true)
                || $session->helper?->user_account_id !== $user->id)) return false;
        }
        return true;
    }

    public function eligible(Notification $notice, User $user): bool
    {
        return $user->is_active && $notice->user_account_id === $user->id && !$notice->is_read
            && !$notice->popup_presented_at && !$notice->popup_dismissed_at
            && $notice->created_at && $notice->created_at->gte(now()->subMinutes(self::FRESH_MINUTES))
            && $notice->created_at->lte(now()) && $this->actionable($notice, $user)
            && !DB::table('notification_popup_deliveries')->where('user_account_id', $user->id)
                ->where('event_key', $this->key($notice, $user))->exists();
    }

    /** The unique recipient/event key arbitrates between tabs, devices and hostnames. */
    public function claim(int $id, User $user): bool
    {
        return DB::transaction(function () use ($id, $user) {
            $notice = Notification::where('user_account_id', $user->id)->lockForUpdate()->findOrFail($id);
            if (!$this->eligible($notice, $user)) return false;
            $claimed = DB::table('notification_popup_deliveries')->insertOrIgnore([
                'user_account_id' => $user->id, 'event_key' => $this->key($notice, $user),
                'notification_id' => $notice->id, 'presented_at' => now(),
            ]) === 1;
            $notice->forceFill(['popup_presented_at' => now()])->save();
            if ($claimed && $notice->notification_type === 'emergency' && $notice->emergency_alert_id) {
                DB::table('notification_popup_deliveries')->insertOrIgnore([
                    'user_account_id'=>$user->id, 'event_key'=>'emergency:alert:'.$notice->emergency_alert_id,
                    'notification_id'=>$notice->id, 'presented_at'=>now(),
                ]);
                // A new report supersedes older alerts about this same case, never a later report.
                Notification::where('user_account_id', $user->id)->where('notification_type', 'emergency')
                    ->where('emergency_alert_id', $notice->emergency_alert_id)->where('id', '<', $notice->id)
                    ->whereNull('popup_presented_at')->update(['popup_presented_at' => now()]);
            }
            return $claimed;
        });
    }
}
