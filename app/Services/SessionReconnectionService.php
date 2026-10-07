<?php

namespace App\Services;

use App\Models\Helper;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionReconnection;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionReconnectionService
{
    public function current(Session $session)
    {
        return SessionReconnection::where('session_id', $session->id)->latest('id')->first();
    }

    private function notice($recipient, $title, $link)
    {
        if ($recipient) {
            Notification::create(['user_account_id' => $recipient, 'title' => $title, 'message' => 'Open the session connection review for the next step.', 'notification_type' => 'session', 'link' => $link]);
        }
    }

    private function staff(Session $session, $title)
    {
        foreach (User::where('role', 'moderator')->where('is_active', true)->pluck('id') as $id) {
            $this->notice($id, $title, '/moderator/reconnections');
        }if ($session->requires_immediate_action || $session->risk_level === 'emergency') {
            $reviewer = $session->supportEmergencyAlert?->adviser ?? $session->emergencyAlerts()->latest('id')->first()?->adviser;
            $this->notice($reviewer?->user_account_id ?? $session->helper?->adviser?->user_account_id, 'Emergency session connection updated', '/adviser/emergencies');
        }
    }

    public function heartbeat(Session $session): void
    {
        abort_unless(Auth::user()?->is_active && Auth::user()->role === 'helper' && Auth::user()->helper?->id === $session->helper_id, 403);
        DB::transaction(function () use ($session) {
            $session = Session::lockForUpdate()->findOrFail($session->id);
            abort_unless($session->helper_id === Auth::user()->helper->id && $session->isActive(), 409);
            $session->forceFill(['helper_heartbeat_at' => now()])->save();
            $incident = $this->current($session);
            if ($incident && in_array($incident->status, ['interrupted', 'waiting', 'requested', 'offered'])) {
                $incident->update(['status' => 'reconnected', 'resolved_at' => now()]);
                SupportAudit::record('helper_reconnected', $session);
                $this->staff($session, 'Helper reconnected');
                $this->notice($session->seeker->user_account_id, 'Your Helper reconnected', '/session/chat');
            }
        });
    }

    public function detect(): void
    {
        SessionReconnection::whereIn('status', ['interrupted', 'waiting', 'requested', 'offered'])->eachById(function ($row) {
            DB::transaction(function () use ($row) {
                $session = Session::lockForUpdate()->findOrFail($row->session_id);
                $incident = SessionReconnection::lockForUpdate()->findOrFail($row->id);
                if (! in_array($incident->status, ['interrupted', 'waiting', 'requested', 'offered'])) {
                    return;
                }if (! $session->isActive() || app(SessionDurationService::class)->expire($session)) {
                    $incident->update(['status' => 'closed', 'resolved_at' => now()]);

                    return;
                }if ($incident->status === 'offered' && $incident->offered_at?->lte(now()->subMinutes(2))) {
                    $previousHelperId = $incident->offered_helper_id;
                    $incident->update(['status' => 'requested', 'offered_helper_id' => null]);
                    SupportAudit::record('replacement_offer_expired', $session, ['helper_id'=>$previousHelperId]);
                    $this->staff($session, 'Replacement offer expired');
                }
            });
        });
        Session::where('session_status', 'active')->where('session_type', 'chat')->whereNotNull('helper_heartbeat_at')->where('helper_heartbeat_at', '<=', now()->subSeconds(60))->eachById(function ($row) {
            DB::transaction(function () use ($row) {
                $session = Session::lockForUpdate()->findOrFail($row->id);
                if (! $session->isActive() || ! $session->helper_heartbeat_at || Carbon::parse($session->helper_heartbeat_at)->gt(now()->subSeconds(60))) {
                    return;
                }if (app(SessionDurationService::class)->expire($session)) {
                    return;
                }$incident = $this->current($session);
                if ($incident && in_array($incident->status, ['interrupted', 'waiting', 'requested', 'offered'])) {
                    return;
                }$incident = SessionReconnection::create(['session_id' => $session->id, 'original_helper_id' => $session->helper_id, 'detected_at' => now()]);
                SupportAudit::record('helper_connection_interrupted', $session);
                $this->staff($session, 'Helper connection interrupted');
                $this->notice($session->seeker->user_account_id, 'Your Helper is reconnecting', '/session/chat');
            });
        });
    }

    public function choose(Session $session, string $decision): void
    {
        abort_unless(Auth::user()?->is_active && Auth::user()->role === 'seeker' && Auth::user()->helpSeeker?->id === $session->seeker_id, 403);
        DB::transaction(function () use ($session, $decision) {
            $session = Session::lockForUpdate()->findOrFail($session->id);
            $incident = $this->current($session);
            if (! $session->isActive() || ! $incident || ! in_array($incident->status, ['interrupted', 'waiting', 'requested', 'offered']) || $incident->detected_at->gt(now()->subMinutes(2))) {
                throw ValidationException::withMessages(['connection' => 'Please allow the reconnection window to finish.']);
            }if (($decision === 'replace' && in_array($incident->status, ['requested', 'offered'])) || ($decision === 'wait' && $incident->status === 'waiting')) {
                return;
            }$incident->update(['status' => $decision === 'replace' ? 'requested' : 'waiting', 'offered_helper_id' => null, 'requested_at' => $decision === 'replace' ? now() : null]);
            SupportAudit::record('seeker_reconnection_choice', $session, ['decision' => $decision]);
            if ($decision === 'replace') {
                $this->staff($session, 'Seeker requested another Helper');
            }
        });
    }

    public function offer(SessionReconnection $incident): void
    {
        abort_unless(Auth::user()?->is_active && Auth::user()->role === 'moderator', 403);
        DB::transaction(function () use ($incident) {
            $session = Session::lockForUpdate()->findOrFail($incident->session_id);
            $incident = SessionReconnection::lockForUpdate()->findOrFail($incident->id);
            if (! $session->isActive() || $incident->status !== 'requested' || app(SessionDurationService::class)->expire($session)) {
                throw ValidationException::withMessages(['connection' => 'This request is no longer awaiting replacement.']);
            }if (($session->requires_immediate_action || $session->risk_level === 'emergency') && !$session->permitsEmergencySupport()) {
                throw ValidationException::withMessages(['connection' => 'An emergency is active. Coordinate with the responsible Adviser instead of ordinary replacement matching.']);
            }$excluded = $session->permitsEmergencySupport() ? \App\Models\AuditLog::where('target_type',$session->getTable())->where('target_id',$session->id)->whereIn('action',['replacement_offer_declined','replacement_offer_expired'])->get()->map(fn($row)=>(int)($row->metadata['helper_id'] ?? 0))->all() : [];
            $helper = app(HelperMatchingService::class)->findBestMatch($session->seeker, $session->risk_level ?? 'low', $session->concern_category, null, $session->helper_id, $excluded);
            if (! $helper) {
                throw ValidationException::withMessages(['connection' => 'No eligible Helper is available. The Seeker remains in the current session. Try again when availability changes.']);
            }$incident->update(['status' => 'offered', 'offered_helper_id' => $helper->id, 'offered_at' => now()]);
            $this->notice($helper->user_account_id, 'Replacement session offer', '/helper/reconnections');
            SupportAudit::record('replacement_helper_offered', $session, ['helper_id' => $helper->id]);
        });
    }

    public function accept(SessionReconnection $incident, bool $accept): void
    {
        abort_unless(Auth::user()?->is_active && Auth::user()->role === 'helper' && Auth::user()->helper?->id === $incident->offered_helper_id, 403);
        DB::transaction(function () use ($incident, $accept) {
            $helper = Helper::lockForUpdate()->findOrFail(Auth::user()->helper->id);
            $session = Session::lockForUpdate()->findOrFail($incident->session_id);
            $incident = SessionReconnection::lockForUpdate()->findOrFail($incident->id);
            if ($incident->offered_at?->lte(now()->subMinutes(2)) || $incident->status !== 'offered' || $incident->offered_helper_id !== $helper->id || ! $session->isActive() || app(SessionDurationService::class)->expire($session)) {
                throw ValidationException::withMessages(['connection' => 'This offer is no longer available.']);
            }if (! $accept) {
                $incident->update(['status' => 'requested', 'offered_helper_id' => null]);
                $this->staff($session, 'Replacement offer declined');
                SupportAudit::record('replacement_offer_declined', $session, ['helper_id'=>$helper->id]);

                return;
            }if (! app(HelperEligibilityService::class)->allows($helper, $session) || ! $helper->canHandleRiskLevel($session->risk_level ?? 'low') || (($session->requires_immediate_action || $session->risk_level === 'emergency') && !$session->permitsEmergencySupport()) || DB::table('helper_conflicts')->where('helper_id', $helper->id)->where('seeker_id', $session->seeker_id)->exists()) {
                throw ValidationException::withMessages(['connection' => 'Eligibility changed. Ask the Moderator to review this replacement.']);
            }
            $continuation = Session::create(['seeker_id' => $session->seeker_id, 'helper_id' => $helper->id, 'concern_id' => $session->concern_id, 'concern_category' => $session->concern_category, 'risk_level' => $session->risk_level, 'session_type' => $session->session_type, 'peer_support_approved_at' => $session->peer_support_approved_at, 'requires_closer_monitoring' => $session->requires_closer_monitoring, 'session_status' => 'active', 'start_time' => $session->start_time, 'helper_accepted_at' => now(), 'created_date' => now(), 'submitted_at' => $session->submitted_at, 'review_adviser_id' => $helper->adviser_id]);
            if ($session->permitsEmergencySupport()) {
                $alert = $session->supportEmergencyAlert ?? $session->emergencyAlerts()->whereNotIn('status', ['resolved','closed'])->latest('id')->firstOrFail();
                $continuation->forceFill([
                    'support_emergency_alert_id'=>$alert->id,
                    'review_adviser_id'=>$alert->adviser_id ?? $session->review_adviser_id,
                    'requires_immediate_action'=>true, 'requires_adviser_review'=>true,
                    'escalation_required'=>true,
                ])->save();
                SupportAudit::record('emergency_support_continued', $alert, ['original_session_id'=>$session->id,'continuation_id'=>$continuation->id]);
            }
            $continuation->forceFill(['helper_heartbeat_at' => now()])->save();
            $session->forceFill(['session_status' => 'completed', 'completion_status' => 'completed', 'end_time' => now(), 'completion_reason' => 'connection_handoff'])->save();
            $incident->update(['status' => 'transferred', 'continuation_id' => $continuation->id, 'resolved_at' => now()]);
            $session->helper?->syncSessionCounters();
            $helper->syncSessionCounters();
            SupportAudit::record('connection_handoff_completed', $session, ['continuation_id' => $continuation->id, 'previous_helper_id' => $session->helper_id, 'helper_id' => $helper->id]);
            $this->notice($session->seeker->user_account_id,'A replacement Helper is ready','/session/chat');
            $this->notice($session->helper?->user_account_id,'Your session was handed over','/helper/cases');
            $this->staff($session,'Helper handoff completed');
        });
    }
}
