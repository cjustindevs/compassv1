<?php

namespace App\Services;

use App\Events\NewCaseAssigned;
use App\Events\QueueUpdated;
use App\Models\AuditLog;
use App\Models\HelpSeeker;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Models\User;
use App\Traits\BroadcastsSafely;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HelperMatchingService
{
    use BroadcastsSafely;

    protected int $preSessionBriefMinutes = Helper::PRE_SESSION_BRIEF_MINUTES;

    public function findBestMatchForRisk(string $riskLevel, ?int $excludeHelperId = null): ?Helper
    {
        return $this->findBestMatch(null, $riskLevel, null, null, $excludeHelperId);
    }

    public function countEligibleHelpers(string $riskLevel): int
    {
        return $this->getEligibleHelpers($riskLevel)->count();
    }

    public function findBestMatch(?HelpSeeker $seeker, string $riskLevel, ?string $concernCategory, ?string $language, ?int $excludeHelperId = null): ?Helper
    {
        $eligibleHelpers = $this->getEligibleHelpers($riskLevel, $excludeHelperId);

        if ($eligibleHelpers->isEmpty()) {
            Log::info('No eligible helpers found', ['risk_level' => $riskLevel]);

            return null;
        }

        if ($seeker) $eligibleHelpers=$eligibleHelpers->reject(fn($helper)=>DB::table('helper_conflicts')->where('helper_id',$helper->id)->where('seeker_id',$seeker->id)->exists());
        return $this->rankHelpers($eligibleHelpers, $riskLevel, $concernCategory, $language)->first();
    }

    protected function getEligibleHelpers(string $riskLevel, ?int $excludeHelperId = null): Collection
    {
        $relaxed = (bool) config('app.relax_duty_hours', false);
        if (! $relaxed && ! app(OperatingHoursService::class)->acceptsAssignments()) return collect();
        $query = Helper::query()
            // Eligible helpers are selected by declared availability OR the
            // operational status, then narrowed authoritatively by
            // HelperEligibilityService via isAvailable() below.
            ->where(function ($query) {
                $query->whereIn('status', ['available', 'busy'])
                    ->orWhere('availability', 'available');
            })
            ->whereHas('user', fn ($q) => $q->where('is_active', true));
        if (! $relaxed) {
            $query->whereHas('currentReadiness', fn ($query) => $query->ready());
        }
        $query->with(['currentReadiness', 'schedule', 'helperSpecialties'])
            ->withCount('activeSessions as live_active_sessions_count');

        if (Schema::hasColumn('helpers', 'is_under_review')) {
            $query->where('is_under_review', false);
        }

        if ($excludeHelperId) {
            $query->where('id', '!=', $excludeHelperId);
        }

        return $query->get()->filter(fn (Helper $helper) => $helper->isAvailable() && $helper->canHandleRiskLevel($riskLevel));
    }

    protected function getRequiredCompetencyForRisk(string $riskLevel): int
    {
        return match ($riskLevel) {
            'emergency' => 4,
            'high' => 3,
            'moderate' => 2,
            default => 1,
        };
    }

    protected function rankHelpers(Collection $helpers, string $riskLevel, ?string $concernCategory, ?string $language): Collection
    {
        return $helpers->map(function (Helper $helper) use ($riskLevel, $concernCategory, $language) {
            $matchingScore = $helper->calculateMatchingScore($riskLevel, $concernCategory, $language);

            $helper->current_shift_sessions = Helper::MAX_SESSIONS_PER_SHIFT - $helper->getRemainingCapacity();
            $helper->matching_score = $matchingScore;
            $helper->matching_details = [
                'competency_score' => ((float) $helper->competency_score / 5) * 100,
                'workload_score' => $helper->calculateWorkloadScore(),
                'specialty_score' => $helper->getSpecialtyMatchScore($concernCategory ?? ''),
                'language_score' => $helper->getLanguageMatchScore($language ?? ''),
                'availability_score' => $helper->getAvailabilityScore(),
                'experience_score' => $helper->getExperienceScore(),
                'total_score' => $matchingScore,
            ];

            return $helper;
        })->sortBy([['matching_score','desc'],['current_shift_sessions','asc'],['available_since','asc'],['id','asc']])->values();
    }

    public function getMatchingDetails(Helper $helper): array
    {
        return $helper->matching_details ?? [
            'competency_score' => ((float) $helper->competency_score / 5) * 100,
            'workload_score' => $helper->calculateWorkloadScore(),
            'specialty_score' => 0,
            'language_score' => 0,
            'availability_score' => $helper->getAvailabilityScore(),
            'experience_score' => $helper->getExperienceScore(),
            'total_score' => 0,
        ];
    }

    public function matchWaitingRequests(): void
    {
        // Reconcile helpers whose declared availability is ahead of their
        // operational status (e.g. just verified or just came on shift) so a
        // seeker refresh can match without a running scheduler.
        app(HelperWorkflowMaintenance::class)->reconcileAllReadyHelpers();

        QueueRequest::where('request_status', 'waiting')
            ->orderByRaw("CASE priority_level WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->orderBy('request_date')->orderBy('id')->get()
            ->each(fn (QueueRequest $queue) => $this->processQueueRequest($queue));
    }

    public function processQueueRequest(QueueRequest $queue, ?int $excludeHelperId = null): ?Session
    {
        if ($queue->request_status !== 'waiting' || (! config('app.relax_duty_hours', false) && ! app(OperatingHoursService::class)->acceptsAssignments())) return null;
        $session=$this->pendingSessionForQueue($queue);
        if (!$session || !$session->submitted_at || !$session->risk_level || $session->requires_adviser_review || $session->risk_level==='emergency') return null;
        $helper=$this->findBestMatch($queue->seeker,$session->risk_level,$session->concern?->concern_name,$queue->seeker?->user?->preferred_language,$excludeHelperId);
        $result = $helper ? $this->manualAssign($queue,$helper->id,false,false,'automatic') : null;
        return is_string($result) ? null : $result;
    }

    /**
     * Assign a helper to a waiting request.
     *
     * @return Session on success, or a string containing the concrete
     *                 rejection reason when the assignment cannot complete.
     */
    public function manualAssign(QueueRequest $queue, int $helperId, bool $emergencyOverride = false, bool $reassign = false, string $method = 'manual', ?\Illuminate\Support\Carbon $scheduledStart = null): Session|string|null
    {
        $reason = DB::transaction(function () use ($queue, $helperId, $emergencyOverride, $reassign, $method, $scheduledStart) {
            $queue = QueueRequest::whereKey($queue->id)->lockForUpdate()->firstOrFail();
            if ($queue->request_status !== ($reassign ? 'assigned' : 'waiting')) return 'This request is no longer waiting in the queue.';
            $helper = Helper::whereKey($helperId)->lockForUpdate()->first();
            $session = $this->pendingSessionForQueue($queue);
            if (!$session || !$session->submitted_at || !$session->risk_level || $session->risk_level === 'emergency' || $session->requires_adviser_review || ($session->risk_level === 'high' && !$session->peer_support_approved_at)) {
                return 'This request requires a different competency or adviser review and cannot be assigned to a peer helper.';
            }
            if (!$helper) {
                return 'The selected helper could not be found.';
            }
            if (!$helper->user?->is_active) {
                return 'The selected helper account is inactive.';
            }
            $consents=app(ConsentService::class);
            if (!$consents->valid($session->seeker,'privacy_policy') || !$consents->valid($session->seeker,'informed_consent')) return 'The seeker does not have the current consents required for a session.';
            if (DB::table('helper_conflicts')->where('helper_id',$helperId)->where('seeker_id',$session->seeker_id)->exists()) return 'A declared conflict prevents this assignment.';
            if (!$helper->canHandleRiskLevel($session->risk_level)) return 'This request requires a different competency or adviser review and cannot be assigned to a peer helper.';

            // Capacity and concurrency rules: a helper is never overloaded past
            // one live session or the two-session duty-shift limit. The
            // duty-shift limit is bypassed while RELAX_DUTY_HOURS is enabled
            // for testing; concurrency is always enforced, even under an
            // override.
            if (! config('app.relax_duty_hours', false) && $helper->getRemainingCapacity() === 0) {
                return 'The two-session duty-shift limit has been reached.';
            }
            if ($helper->activeSessions()->where('id','!=',$session->id)->exists()) {
                return 'The helper is already assigned to an active session.';
            }

            $overrideReasons = [];
            $helperEligibility = app(HelperEligibilityService::class);
            if ($emergencyOverride) {
                // Audited moderator override: bypass verification, off-duty and
                // readiness/availability blocks, but never the seeker-safety or
                // capacity rules enforced above.
                $overrideReasons = collect($helperEligibility->reasons($helper, $session, true))
                    ->reject(fn (string $r) => str_contains($r, 'Service is closed for new assignments.'))
                    ->values()->all();
            } else {
                if (! config('app.relax_duty_hours', false) && ! app(OperatingHoursService::class)->acceptsAssignments()) {
                    return 'Service is closed for new assignments.';
                }
                // Single authoritative eligibility check (readiness, shift, capacity…).
                if (!$helperEligibility->allows($helper,$session)) {
                    $reasons = $helperEligibility->reasons($helper,$session);
                    return collect($reasons)->first(fn ($r) => !str_contains($r, 'Service is closed for new assignments.'))
                        ?? $reasons[0] ?? 'The helper is not currently eligible for this assignment.';
                }
            }
            $session = $this->pendingSessionForQueue($queue);
            if ($reassign && (!$session || $session->session_status !== Session::STATUS_HELPER_ASSIGNED)) return 'This request is no longer awaiting helper acceptance.';
            $oldHelperId = $session?->helper_id;
            if ($reassign && $oldHelperId === $helperId) return 'That helper is already assigned to this request.';
            $session ??= Session::create([
                'seeker_id' => $queue->seeker_id, 'session_type' => 'chat',
                'risk_level' => $queue->priority_level, 'created_date' => now(),
            ]);
            $session->update([
                'helper_id' => $helper->id, 'queue_request_id' => $queue->id,
                'moderator_id' => Auth::user()?->moderator?->id ?? $session->moderator_id,
                'session_status' => Session::STATUS_HELPER_ASSIGNED, 'scheduled_start' => $scheduledStart ?? now()->addMinutes(2),
                'match_method' => $method, 'matched_by' => $method === 'automatic' ? 'system' : (string) Auth::id(),
                'helper_accepted_at' => null, 'match_status'=>'awaiting_acceptance',
                'pre_session_brief_expires_at' => $scheduledStart ?? now()->addMinutes($this->preSessionBriefMinutes),
            ]);
            $queue->update([
                'assigned_helper_id' => $helper->id, 'request_status' => 'assigned',
                'queue_position' => null, 'estimated_wait' => null, 'matched_date' => now(),
                'matching_started_at'=>now(), 'helper_proposed_at'=>now(),
                'scheduled_date' => $scheduledStart,
            ]);
            $helper->update(['status' => 'busy']);
            $helper->syncSessionCounters();
            if ($oldHelperId && $oldHelperId !== $helper->id && ($old = Helper::find($oldHelperId))) {
                $old->syncSessionCounters();
                if ($old->status === 'busy' && !$old->activeSessions()->exists()) $old->update(['status' => 'available']);
            }
            $this->logAssignment($queue, $helper, $method);
            SupportAudit::record('match_recommended',$session);
            \Illuminate\Support\Facades\Cache::forget('moderator_dashboard_stats');
            if ($emergencyOverride) {
                SupportAudit::record('match_override',$session,[
                    'helper_id' => $helper->id,
                    'queue_id' => $queue->id,
                    'moderator_id' => Auth::user()?->moderator?->id,
                    'bypassed_reasons' => $overrideReasons,
                ]);
            }
            DB::afterCommit(function () use ($session, $helper, $reassign) {
                $this->sendAssignmentNotifications($session, $helper);
                $this->broadcastQueueUpdated();
                if ($session->seeker) {
                    $this->broadcastSafely(new \App\Events\NewHelperAssigned($session, $session->seeker->user_account_id, 'Peer Helper '.str_pad((string) $helper->id, 4, '0', STR_PAD_LEFT), $helper->competency_level));
                }
            });
            return $session;
        });
        return $reason;
    }

    protected function pendingSessionForQueue(QueueRequest $queue): ?Session
    {
        return Session::with('concern')
            ->where('seeker_id', $queue->seeker_id)
            ->where('queue_request_id', $queue->id)
            ->whereIn('session_status', Session::PENDING_STATUSES)
            ->latest('created_date')
            ->first();
    }

    protected function logAssignment(QueueRequest $queue, Helper $helper, string $method): void
    {
        AuditLog::create([
            'user_account_id' => Auth::id(),
            'action' => 'helper_assigned',
            'module' => 'matching',
            'description' => json_encode([
                'queue_id' => $queue->id,
                'seeker_id' => $queue->seeker_id,
                'helper_id' => $helper->id,
                'risk_level' => $queue->priority_level,
                'method' => $method,
                'matching_score' => $helper->matching_score ?? null,
            ]),
        ]);
    }

    protected function sendAssignmentNotifications(Session $session, Helper $helper): void
    {
        Notification::create([
            'user_account_id' => $helper->user_account_id,
            'title' => 'New case assigned',
            'message' => 'You have been assigned to support ' . ($session->seeker?->generated_alias ?? 'a seeker') . '. Follow the support plan and escalate safety concerns.',
            'notification_type' => 'assignment',
            'type_icon' => 'fa-clipboard-list',
            'link' => '/helper/session/' . $session->id . '/pre-assessment',
            'status' => 'unread',
        ]);

        if ($session->seeker?->user_account_id) {
            Notification::create([
                'user_account_id' => $session->seeker->user_account_id,
                'title' => 'Helper matched',
                'message' => 'A helper has been matched to your request.',
                'notification_type' => 'assignment',
                'type_icon' => 'fa-handshake',
                'link' => '/request/matching',
                'status' => 'unread',
            ]);
        }

        $this->broadcastSafely(new NewCaseAssigned($session, $helper->user_account_id));
    }

    protected function broadcastQueueUpdated(): void
    {
        foreach (User::where('role', 'moderator')->pluck('id') as $moderatorUserId) {
            $this->broadcastSafely(new QueueUpdated($moderatorUserId));
        }
    }
}
