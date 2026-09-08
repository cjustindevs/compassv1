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

    public function findBestMatch(?HelpSeeker $seeker, string $riskLevel, ?string $concernCategory, ?string $language, ?int $excludeHelperId = null): ?Helper
    {
        $eligibleHelpers = $this->getEligibleHelpers($riskLevel, $excludeHelperId);

        if ($eligibleHelpers->isEmpty()) {
            Log::info('No eligible helpers found', ['risk_level' => $riskLevel]);

            return null;
        }

        return $this->rankHelpers($eligibleHelpers, $riskLevel, $concernCategory, $language)->first();
    }

    protected function getEligibleHelpers(string $riskLevel, ?int $excludeHelperId = null): Collection
    {
        $query = Helper::query()
            ->whereIn('status', ['available', 'busy'])
            ->whereHas('currentReadiness', fn ($query) => $query->ready())
            ->with(['currentReadiness', 'schedule', 'helperSpecialties'])
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
        })->sortByDesc('matching_score')->values();
    }

    public function getMatchingDetails(Helper $helper): array
    {
        return $helper->matching_details ?? [
            'competency_score' => ((float) $helper->competency_score / 5) * 100,
            'workload_score' => $helper->calculateWorkloadScore(),
            'specialty_score' => 0,
            'language_score' => 100,
            'availability_score' => $helper->getAvailabilityScore(),
            'experience_score' => $helper->getExperienceScore(),
            'total_score' => 0,
        ];
    }

    public function processQueueRequest(QueueRequest $queue): ?Session
    {
        if ($queue->request_status !== 'waiting') {
            return null;
        }

        $session = $this->pendingSessionForQueue($queue);
        $concernCategory = $session?->concern?->concern_name;
        $language = $queue->seeker?->user?->preferred_language;
        $helper = $this->findBestMatch($queue->seeker, $queue->priority_level, $concernCategory, $language);

        if (! $helper) {
            return null;
        }

        return DB::transaction(function () use ($queue, $helper, $session) {
            $queue = QueueRequest::whereKey($queue->id)->lockForUpdate()->firstOrFail();
            $lockedHelper = Helper::whereKey($helper->id)->lockForUpdate()->firstOrFail();
            if ($queue->request_status !== 'waiting' || ! $lockedHelper->isAvailable() || ! $lockedHelper->canHandleRiskLevel($queue->priority_level)) {
                return null;
            }
            $session = $session ?: Session::create([
                'seeker_id' => $queue->seeker_id,
                'moderator_id' => Auth::user()?->moderator?->id,
                'session_type' => $queue->preferred_session_type ?? 'chat',
                'risk_level' => $queue->priority_level,
                'concern_category' => $session?->concern?->concern_name,
                'session_status' => Session::STATUS_HELPER_ASSIGNED,
                'created_date' => now(),
            ]);

            $session->update([
                'helper_id' => $helper->id,
                'queue_request_id' => $queue->id,
                'moderator_id' => Auth::user()?->moderator?->id ?? $session->moderator_id,
                'session_type' => $queue->preferred_session_type ?? $session->session_type,
                'risk_level' => $queue->priority_level ?? $session->risk_level,
                'concern_category' => $session->concern?->concern_name ?? $session->concern_category,
                'session_status' => Session::STATUS_HELPER_ASSIGNED,
                'scheduled_start' => now()->addMinutes(2),
                'match_method' => 'automatic',
                'matched_by' => 'system',
                'matching_details' => $helper->matching_details,
                'pre_session_brief_expires_at' => now()->addMinutes($this->preSessionBriefMinutes),
                'voice_consent_obtained' => (bool) $queue->voice_consent,
                'voice_recording_consent' => (bool) $queue->voice_consent,
            ]);

            $queue->update([
                'assigned_helper_id' => $helper->id,
                'request_status' => 'assigned',
                'queue_position' => null,
                'estimated_wait' => null,
                'matched_date' => now(),
            ]);

            $helper->incrementShiftSessions();
            $helper->update(['status' => 'busy']);

            $this->logAssignment($queue, $helper, 'automatic');
            $this->sendAssignmentNotifications($session, $helper);
            $this->broadcastQueueUpdated();

            return $session;
        });
    }

    public function manualAssign(QueueRequest $queue, int $helperId): ?Session
    {
        $helper = Helper::find($helperId);

        if (! $helper || ! $helper->isAvailable() || ! $helper->canHandleRiskLevel($queue->priority_level)) {
            Log::warning('Manual assignment failed', [
                'queue_id' => $queue->id,
                'helper_id' => $helperId,
                'risk_level' => $queue->priority_level,
            ]);

            return null;
        }

        $session = $this->pendingSessionForQueue($queue);

        return DB::transaction(function () use ($queue, $helper, $session) {
            $session = $session ?: Session::create([
                'seeker_id' => $queue->seeker_id,
                'session_type' => $queue->preferred_session_type ?? 'chat',
                'risk_level' => $queue->priority_level,
                'concern_category' => $session?->concern?->concern_name,
                'created_date' => now(),
            ]);

            $session->update([
                'helper_id' => $helper->id,
                'queue_request_id' => $queue->id,
                'moderator_id' => Auth::user()?->moderator?->id ?? $session->moderator_id,
                'session_status' => Session::STATUS_HELPER_ASSIGNED,
                'scheduled_start' => now()->addMinutes(2),
                'match_method' => 'manual',
                'matched_by' => (string) Auth::id(),
                'matching_details' => $helper->matching_details,
                'pre_session_brief_expires_at' => now()->addMinutes($this->preSessionBriefMinutes),
                'voice_consent_obtained' => (bool) $queue->voice_consent,
                'voice_recording_consent' => (bool) $queue->voice_consent,
            ]);

            $queue->update([
                'assigned_helper_id' => $helper->id,
                'request_status' => 'assigned',
                'queue_position' => null,
                'estimated_wait' => null,
                'matched_date' => now(),
            ]);

            $helper->incrementShiftSessions();
            $helper->update(['status' => 'busy']);

            $this->logAssignment($queue, $helper, 'manual');
            $this->sendAssignmentNotifications($session, $helper);
            $this->broadcastQueueUpdated();

            return $session;
        });
    }

    protected function pendingSessionForQueue(QueueRequest $queue): ?Session
    {
        return Session::with('concern')
            ->where('seeker_id', $queue->seeker_id)
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
            'type_icon' => '📋',
            'link' => '/helper/session/' . $session->id . '/pre-assessment',
            'status' => 'unread',
        ]);

        if ($session->seeker?->user_account_id) {
            Notification::create([
                'user_account_id' => $session->seeker->user_account_id,
                'title' => 'Helper matched',
                'message' => 'A helper has been matched to your request.',
                'notification_type' => 'assignment',
                'type_icon' => '🤝',
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
