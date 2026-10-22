<?php

namespace App\Services;

use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\Session;
use Illuminate\Support\Facades\DB;

/** Stored case ownership is distinct from screening review and Helper supervision. */
class EmergencyCoordinator
{
    public function candidates()
    {
        return Adviser::whereHas('user', fn ($q) => $q->where('is_active', true)->where('role', 'adviser'));
    }

    public function name(?Adviser $adviser): string
    {
        return $adviser ? (trim($adviser->first_name.' '.$adviser->last_name) ?: 'Adviser #'.$adviser->id) : 'Awaiting assignment';
    }

    public function choose(?Session $session): ?Adviser
    {
        return $this->candidates()->find($session?->helper?->adviser_id)
            ?? $this->candidates()->find($session?->review_adviser_id)
            ?? $this->candidates()->withCount(['reviewSessions as pending_count' => fn ($q) => $q->where('requires_adviser_review', true)
                ->whereNotIn('session_status', ['completed', 'evaluated', 'cancelled', 'no_show'])])
                ->orderBy('pending_count')->orderBy('id')->first();
    }

    /** Caller holds the alert lock. Never replace an active recorded coordinator. */
    public function ensure(EmergencyAlert $alert): ?Adviser
    {
        $owner = $alert->adviser;
        if ($owner?->user?->is_active && $owner->user->role === 'adviser') {
            return $owner;
        }
        $next = $this->choose($alert->session);
        if ($next) {
            $this->assign($alert, $next, $owner ? 'Previous coordinator is unavailable.' : 'Initial emergency coordinator assignment.');
        } else {
            $alert->forceFill(['adviser_notified' => false, 'notification_sent' => false, 'notification_result' => 'no_active_adviser'])->save();
        }
        return $next;
    }

    /** Explicit handoff preserves original acknowledgment, screening and action records. */
    public function transfer(EmergencyAlert $alert, int $adviserId, ?int $expectedId, string $reason): void
    {
        DB::transaction(function () use ($alert, $adviserId, $expectedId, $reason) {
            $case = EmergencyAlert::lockForUpdate()->findOrFail($alert->id);
            app(AdviserScope::class)->emergency($case);
            abort_if(in_array($case->status, ModeratorEmergencyCases::TERMINAL, true) || $case->archived_at, 409, 'This emergency has concluded.');
            $next = $this->candidates()->findOrFail($adviserId);
            if ((int) $case->adviser_id === $adviserId) return;
            abort_unless(($case->adviser_id === null ? null : (int) $case->adviser_id) === $expectedId, 409, 'The coordinator changed. Refresh before transferring.');
            $this->assign($case, $next, $reason, true);
            app(EmergencyStaffNotifications::class)->deliver($case);
        }, 3);
    }

    private function assign(EmergencyAlert $alert, Adviser $next, string $reason, bool $manual = false): void
    {
        $previousId = $alert->adviser_id;
        $previous = $alert->adviser;
        $alert->forceFill(['adviser_id' => $next->id, 'adviser_notified' => false, 'adviser_notified_at' => null,
            'notification_sent' => false, 'notification_sent_at' => null, 'notification_result' => 'pending_delivery'])->save();
        $alert->setRelation('adviser', $next);
        // A separately assigned screening reviewer is not silently overwritten.
        if ($alert->session_id) Session::whereKey($alert->session_id)->whereNull('review_adviser_id')->update(['review_adviser_id' => $next->id]);
        SupportAudit::record($previousId ? 'emergency_coordinator_transferred' : 'emergency_coordinator_assigned', $alert,
            ['previous_adviser_id' => $previousId, 'adviser_id' => $next->id, 'reason' => $reason, 'assignment_mode' => $manual ? 'manual' : 'automatic']);
        if ($previousId && $manual) DB::table('emergency_review_actions')->insert([
            'emergency_alert_id' => $alert->id, 'actor_id' => auth()->id(), 'action' => 'coordinator_changed',
            'notes' => $this->name($previous).' -> '.$this->name($next).'. '.$reason, 'created_at' => now(),
        ]);
    }
}
