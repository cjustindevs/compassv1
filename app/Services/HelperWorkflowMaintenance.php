<?php

namespace App\Services;

use App\Models\Helper;
use App\Models\HelperAvailabilityLog;
use App\Models\Notification;
use App\Models\ReadinessCheck;
use App\Models\Session;
use Illuminate\Support\Facades\DB;

class HelperWorkflowMaintenance
{
    public function run(): void
    {
        $this->reconcileAllReadyHelpers();
        Session::where('session_status', Session::STATUS_HELPER_ASSIGNED)->whereNull('helper_accepted_at')
            ->eachById(function ($session) {
                if ($session->pre_session_brief_expires_at?->isPast()) {
                    $this->releaseRecommendation($session, 'expired');
                } elseif ($session->helper && ! app(HelperEligibilityService::class)->allows($session->helper, $session)) {
                    $this->releaseRecommendation($session, 'eligibility_changed');
                }
            });
        ReadinessCheck::where('is_active', true)->whereNull('expiry_notified_at')->where('valid_until', '<=', now())->eachById(function ($check) {
            DB::transaction(function () use ($check) {
                $helper = Helper::lockForUpdate()->findOrFail($check->helper_id);
                $check = ReadinessCheck::lockForUpdate()->findOrFail($check->id);
                if ($check->expiry_notified_at || ! $check->is_active) {
                    return;
                }
                $check->update(['is_active' => false, 'expiry_notified_at' => now()]);
                if ($helper->readinessChecks()->latest('id')->value('id') === $check->id) {
                    $helper->update(['is_ready' => false]);
                }
                SupportAudit::record('readiness_expired', $check);
                $this->notify($helper->user_account_id, 'Readiness check expired', 'Complete a new readiness check before accepting another session.', '/helper/readiness');
            }, 3);
        });
        Session::whereNotNull('end_time')->where('end_time', '<=', now()->subHours(24))->whereNull('documentation_notified_at')
            ->where('documentation_status', '!=', 'submitted')->whereNotNull('helper_id')->eachById(function ($session) {
                DB::transaction(function () use ($session) {
                    $session = Session::lockForUpdate()->findOrFail($session->id);
                    if ($session->documentation_notified_at || $session->documentation_status === 'submitted') {
                        return;
                    }
                    $session->update(['documentation_notified_at' => now()]);
                    $this->notify($session->helper->user_account_id, 'Session documentation is overdue', 'Complete the summary and reflection for session #'.$session->id.'.', '/helper/session/'.$session->id.'/notes');
                    SupportAudit::record('documentation_reminder_sent', $session);
                }, 3);
            });
    }

    /**
     * Flip an eligible, willing helper to Available the moment their duty
     * shift begins, and re-check the waiting queue. This is the single
     * reconciliation point so a readiness-passed helper needs no manual
     * availability toggle once their scheduled shift starts.
     */
    public function reconcileHelperAvailability(Helper $helper, bool $matchWaiting = true): bool
    {
        $helper = Helper::whereKey($helper->id)->lockForUpdate()->first();
        if (! $helper || $helper->activeSessions()->exists()) {
            return false;
        }
        if ($helper->availability !== 'available') {
            return false;
        }
        // Everything but the system-wide service-hours closure must be met:
        // account, verification, adviser, on-duty shift, current readiness.
        $blocking = collect(app(HelperEligibilityService::class)->reasons($helper, null, true))
            ->reject(fn (string $reason) => str_contains($reason, 'Service is closed for new assignments.'))
            ->values();
        if ($blocking->isNotEmpty()) {
            return false;
        }
        if ($helper->status === 'available') {
            return true;
        }

        $helper->update([
            'status' => 'available',
            'availability' => 'available',
            'available_since' => now(),
            'break_started_at' => null,
            'is_ready' => true,
        ]);
        HelperAvailabilityLog::create([
            'helper_id' => $helper->id,
            'previous_status' => 'unavailable',
            'new_status' => 'available',
            'changed_at' => now(),
            'reason' => 'shift_start_auto_available',
            'changed_by' => null,
        ]);
        SupportAudit::record('helper_auto_available', $helper, ['reason' => 'shift_start']);
        if ($matchWaiting) {
            DB::afterCommit(fn () => app(HelperMatchingService::class)->matchWaitingRequests());
        }

        return true;
    }

    /**
     * Batch reconciliation used by the scheduled maintenance pass so helpers
     * who came on duty or passed readiness become available automatically.
     */
    public function reconcileAllReadyHelpers(): void
    {
        Helper::where('availability', 'available')
            ->where('status', '!=', 'available')
            ->eachById(fn (Helper $helper) => $this->reconcileHelperAvailability($helper, false));
    }

    public function releaseRecommendation(Session $session, string $reason): void
    {
        DB::transaction(function () use ($session, $reason) {
            $helper = Helper::whereKey($session->helper_id)->lockForUpdate()->first();
            $session = Session::lockForUpdate()->findOrFail($session->id);
            if (! $helper || $session->helper_id !== $helper->id || $session->session_status !== Session::STATUS_HELPER_ASSIGNED || $session->helper_accepted_at) {
                return;
            }
            if ($reason === 'expired' && (! $session->pre_session_brief_expires_at || $session->pre_session_brief_expires_at->isFuture())) {
                return;
            }
            if ($reason === 'eligibility_changed' && app(HelperEligibilityService::class)->allows($helper, $session)) {
                return;
            }
            $session->update(['helper_id' => null, 'session_status' => Session::STATUS_WAITING, 'match_status' => $reason, 'pre_session_brief_expires_at' => null]);
            $session->queue?->update(['assigned_helper_id' => null, 'request_status' => 'waiting', 'helper_proposed_at' => null]);
            $helper->syncSessionCounters();
            $helper->update(['status' => $helper->availability === 'available' ? 'available' : 'offline']);
            SupportAudit::record('helper_recommendation_'.$reason, $session, ['helper_id' => $helper->id]);
            $this->notify($helper->user_account_id, 'Assignment returned to the queue', 'The pending assignment is no longer reserved for you.', '/helper/cases');
            $this->notify($session->seeker?->user_account_id, 'Finding another available helper', 'Your support request remains in the queue.', '/request/matching');
            if ($queue = $session->queue) {
                DB::afterCommit(fn () => app(HelperMatchingService::class)->processQueueRequest($queue, $helper->id));
            }
        }, 3);
    }

    private function notify(?int $userId, string $title, string $message, string $link): void
    {
        if ($userId) {
            Notification::create(['user_account_id' => $userId, 'title' => $title, 'message' => $message, 'link' => $link, 'notification_type' => 'system', 'type_icon' => 'fa-circle-info']);
        }
    }
}
