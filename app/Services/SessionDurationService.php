<?php

namespace App\Services;

use App\Events\SessionEnded;
use App\Models\Session;
use App\Traits\BroadcastsSafely;
use Illuminate\Support\Facades\DB;

class SessionDurationService
{
    use BroadcastsSafely;

    public const MAX_MINUTES = 90;

    public function state(Session $session): array
    {
        $this->expire($session);
        $deadline = $session->start_time?->copy()->addMinutes(self::MAX_MINUTES);

        return [
            'status' => $session->session_status,
            'ended' => $session->isCompleted(),
            'remaining_seconds' => $session->isCompleted() ? 0 : ($deadline ? max(0, now()->diffInSeconds($deadline, false)) : null),
            'limit_seconds' => self::MAX_MINUTES * 60,
            'message' => $session->auto_completed ? 'The 90-minute session limit has been reached.' : 'This session has ended.',
            'seeker_redirect' => '/session/evaluation',
            'helper_redirect' => '/helper/session/'.$session->id.'/notes',
        ];
    }

    public function expire(Session $session): bool
    {
        if (! $session->isActive() || ! $session->start_time || now()->lt($session->start_time->copy()->addMinutes(self::MAX_MINUTES))) {
            return false;
        }

        $expired = DB::transaction(function () use ($session) {
            $locked = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isActive() || ! $locked->start_time || now()->lt($locked->start_time->copy()->addMinutes(self::MAX_MINUTES))) {
                return false;
            }
            $locked->update([
                'session_status' => Session::STATUS_COMPLETED,
                'completion_status' => 'completed',
                'end_time' => $locked->start_time->copy()->addMinutes(self::MAX_MINUTES),
                'duration' => self::MAX_MINUTES, 'auto_completed' => true, 'auto_completed_at' => now(),
            ]);
            \App\Models\AuditLog::create([
                'user_account_id' => auth()->id(),
                'action' => 'session_duration_limit_exceeded',
                'module' => auth()->user()?->role === 'helper' ? 'helper' : 'session',
                'description' => 'Session #'.$locked->id.' automatically closed at the 90-minute limit. Recorded duration capped at 90 minutes.',
            ]);
            if ($helper = $locked->helper) {
                $helper->syncSessionCounters();
                if ($helper->status === 'busy' && $helper->activeSessions()->doesntExist()) {
                    $helper->update(['status' => 'available']);
                }
            }
            return true;
        });

        $session->refresh();
        if ($expired) {
            $this->broadcastSafely(new SessionEnded($session, 'system'));
        }
        return $expired;
    }
}
