<?php

namespace App\Services;

use App\Models\Helper;
use App\Models\ReadinessCheck;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HelperReadinessService
{
    public const VERSION = 'compass-v4-listener-1';

    public const SKILLS = ['active_listening', 'empathy', 'reflection', 'clarification', 'summarizing'];

    public function submit(User $user, array $data): ReadinessCheck
    {
        Gate::forUser($user)->authorize('helper-workflow');

        return DB::transaction(function () use ($user, $data) {
            $helper = Helper::whereKey($user->helper->id)->lockForUpdate()->firstOrFail();
            $schedule = \App\Models\HelperSchedule::coveringShiftFor($helper->id);
            $until = now()->addHours(4);
            if ($schedule) {
                // Readiness must not outlive the duty day it was submitted for.
                [, $dutyEnd] = $schedule->window();
                if ($dutyEnd->lt($until)) {
                    $until = $dutyEnd;
                }
            }
            $passed = $data['availability_status'] === 'available' && $data['emotionally_ready'] && $data['willing_to_listen'] && $data['stress_level'] !== 'high' && count(array_diff(self::SKILLS, $data['skills_confirmed'])) === 0;
            $helper->readinessChecks()->where('is_active', true)->update(['is_active' => false]);
            $record = ReadinessCheck::create(array_intersect_key($data, array_flip(['emotionally_ready', 'willing_to_listen', 'stress_level', 'availability_status', 'physical_condition', 'notes'])) + [
                'helper_id' => $helper->id, 'assessment_result' => $passed ? 'ready' : 'not_ready', 'assessment_date' => now(), 'valid_until' => $until->copy()->utc(), 'breathing_exercise' => $data['exercise_completed'], 'skills_confirmed' => $data['skills_confirmed'], 'form_version' => self::VERSION, 'helper_schedule_id' => $schedule?->id, 'is_active' => true]);
            $helper->unsetRelations();
            // The helper's chosen availability status records their willingness
            // to serve this shift; the operational status is only flipped to
            // Available once they are actually on duty and eligible.
            $willing = $passed && $data['availability_status'] === 'available';
            $available = $willing && app(HelperEligibilityService::class)->allows($helper, null, true);
            $helper->update(['is_ready' => $passed, 'last_readiness_at' => now()]);
            // Submitting readiness alone does not manufacture schedule or verification approval.
            if (! $helper->activeSessions()->exists()) {
                $helper->update(['status' => $available ? 'available' : 'offline', 'availability' => $willing ? 'available' : 'unavailable', 'available_since' => $available ? now() : null]);
            }
            SupportAudit::record('readiness_submitted', $record, ['version' => self::VERSION]);
            SupportAudit::record($passed ? 'readiness_passed' : 'readiness_failed', $record);
            if (! $available) {
                foreach ($helper->activeSessions()->whereNull('helper_accepted_at')->get() as $pending) {
                    DB::afterCommit(fn () => app(HelperWorkflowMaintenance::class)->releaseRecommendation($pending, 'readiness_changed'));
                }
            }
            DB::afterCommit(function () use ($helper, $willing) {
                if ($willing) {
                    app(HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());
                }
                app(HelperMatchingService::class)->matchWaitingRequests();
            });

            return $record;
        });
    }
}
