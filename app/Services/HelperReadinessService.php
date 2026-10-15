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
    public const GROUPED_VERSION = 'compass-v4-listener-grouped-1';
    public const RETRY_MINUTES = 30;

    public function retryAt(Helper $helper): ?\Illuminate\Support\Carbon
    {
        $last = $helper->readinessChecks()->latest('created_at')->latest('id')->first();
        return $last?->created_at?->copy()->addMinutes(self::RETRY_MINUTES);
    }

    public const SKILLS = ['active_listening', 'empathy', 'reflection', 'clarification', 'summarizing'];

    public function submit(User $user, array $data): ReadinessCheck
    {
        Gate::forUser($user)->authorize('helper-workflow');
        if (($data['exercise_completed'] ?? null) !== 'completed') {
            throw \Illuminate\Validation\ValidationException::withMessages(['exercise_completed' => 'Complete all three breathing rounds before submitting readiness.']);
        }
        $grouped = array_key_exists('listener_skills_ready', $data);
        if ($grouped) {
            // An explicit Yes confirms the entire displayed checklist, not a competency score.
            $data['skills_confirmed'] = $data['listener_skills_ready'] ? self::SKILLS : [];
        }
        $version = $grouped ? self::GROUPED_VERSION : self::VERSION;

        return DB::transaction(function () use ($user, $data, $version) {
            $helper = Helper::whereKey($user->helper->id)->lockForUpdate()->firstOrFail();
            $retryAt = $this->retryAt($helper);
            if ($retryAt && now()->lt($retryAt)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['readiness' => 'You can take another readiness check in '.(int) ceil(now()->diffInSeconds($retryAt) / 60).' minutes ('.$retryAt->copy()->timezone('Asia/Manila')->format('g:i A').' PHT).']);
            }
            if ($helper->readinessBlockers()->exists() || $helper->getRemainingCapacity() === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['readiness' => 'Your current assignment or duty capacity prevents another readiness check. You can set yourself unavailable at any time.']);
            }
            $wasAvailable=$helper->status==='available';
            $schedule = \App\Models\HelperSchedule::readinessDutyFor($helper->id);
            if(!$schedule)throw \Illuminate\Validation\ValidationException::withMessages(['schedule'=>'You do not have an applicable scheduled duty. Please contact your Moderator.']);
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
                'helper_id' => $helper->id, 'assessment_result' => $passed ? 'ready' : 'not_ready', 'assessment_date' => now(), 'valid_until' => $until->copy()->utc(), 'breathing_exercise' => $data['exercise_completed'], 'skills_confirmed' => $data['skills_confirmed'], 'form_version' => $version, 'helper_schedule_id' => $schedule?->id, 'is_active' => true]);
            $helper->unsetRelations();
            // The helper's chosen availability status records their willingness
            // to serve this shift; the operational status is only flipped to
            // Available once they are actually on duty and eligible.
            $willing = $passed && $data['availability_status'] === 'available';
            $available = $willing && app(HelperEligibilityService::class)->allows($helper, null, true);
            $helper->update(['is_ready' => $passed, 'last_readiness_at' => now()]);
            // Submitting readiness alone does not manufacture schedule or verification approval.
            if (! $helper->assignmentBlockers()->exists()) {
                $helper->update(['status' => $available ? 'available' : 'offline', 'availability' => $willing ? 'available' : 'unavailable', 'available_since' => $available ? now() : null]);
            }
            SupportAudit::record('readiness_submitted', $record, ['version' => $version]);
            SupportAudit::record($passed ? 'readiness_passed' : 'readiness_failed', $record);
            if($available && !$wasAvailable)foreach(\App\Models\User::where('role','moderator')->where('is_active',true)->get() as $moderator)
                \App\Models\Notification::create(['user_account_id'=>$moderator->id,'title'=>'Helper ready for duty','message'=>$helper->full_name.' has passed readiness for scheduled duty.','notification_type'=>'assignment','link'=>'/moderator/manage']);
            if (! $available) {
                foreach ($helper->readinessBlockers()->whereNull('helper_accepted_at')->get() as $pending) {
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
