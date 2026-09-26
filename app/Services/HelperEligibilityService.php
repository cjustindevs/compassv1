<?php

namespace App\Services;

use App\Models\Helper;
use App\Models\Session;
use Illuminate\Support\Facades\DB;

class HelperEligibilityService
{
    public function reasons(Helper $helper, ?Session $session = null, bool $activating = false): array
    {
        $reasons = [];
        $relaxed = (bool) config('app.relax_duty_hours', false);

        if (! $helper->user?->is_active || $helper->user?->role !== 'helper') {
            $reasons[] = 'Account is inactive.';
        }
        if (! $relaxed && ($helper->verification_status !== 'verified' || ! $helper->verified_at || ! $helper->verified_by || ! $helper->training_verified || ($helper->verification_expires_at && $helper->verification_expires_at->isPast()))) {
            $reasons[] = 'Institutional verification and training approval are required.';
        }
        if (! $helper->adviser_id || ! $helper->adviser?->user?->is_active) {
            $reasons[] = 'An active primary adviser is required.';
        }
        if ($helper->is_under_review) {
            $reasons[] = 'Session assignments are restricted pending adviser review.';
        }
        if (! $relaxed && ! app(OperatingHoursService::class)->acceptsAssignments()) {
            $reasons[] = 'Service is closed for new assignments.';
        }
        if (! $relaxed) {
            // Shift-based duty: a helper counts as on duty only while the clock
            // is inside one of their shifts, so an overnight shift also covers
            // the small hours of the following day.
            if (! \App\Models\HelperSchedule::coveringShiftFor($helper->id)) {
                $reasons[] = 'You are not on an official duty shift.';
            }
            if (! $helper->getCurrentReadiness()) {
                $reasons[] = 'A current passed readiness check is required.';
            }
        }
        if (! $activating && ! $relaxed && $helper->availability !== 'available') {
            $reasons[] = 'Availability is not set to Available.';
        }
        if ($helper->activeSessions()->when($session, fn ($q) => $q->where('id', '!=', $session->id))->exists()) {
            $reasons[] = 'Another assignment or active session already occupies your capacity.';
        }
        if (! $relaxed && $helper->getRemainingCapacity() === 0) {
            $reasons[] = 'The two-session duty-shift limit has been reached.';
        }
        if ($session) {
            if (! $helper->canHandleRiskLevel($session->risk_level) || $session->risk_level === 'emergency' || $session->requires_adviser_review || ($session->risk_level === 'high' && ! $session->peer_support_approved_at)) {
                $reasons[] = 'This request requires a different competency or adviser review.';
            }
            if (DB::table('helper_conflicts')->where('helper_id', $helper->id)->where('seeker_id', $session->seeker_id)->exists()) {
                $reasons[] = 'A declared conflict prevents this assignment.';
            }
        }

        return $reasons;
    }

    public function allows(Helper $helper, ?Session $session = null, bool $activating = false): bool
    {
        return $this->reasons($helper, $session, $activating) === [];
    }

    /**
     * Single source of truth for how helpers are presented to moderators,
     * advisers, helpers themselves, and the matching engine.
     *
     * @return array{label:string, reason:?string, assignable:bool, reasons:array}
     */
    public function status(Helper $helper, ?Session $session = null, bool $activating = false): array
    {
        $reasons = $this->reasons($helper, $session, $activating);
        $primary = collect($reasons)->first(
            fn (string $reason) => $reason !== 'Service is closed for new assignments.'
        ) ?? $reasons[0] ?? null;

        return [
            'label' => $primary ? $this->labelForReason($primary) : 'Available',
            'reason' => $primary,
            'assignable' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    public function labelForReason(string $reason): string
    {
        return match (true) {
            str_contains($reason, 'Account is inactive') => 'Account inactive',
            str_contains($reason, 'verification and training') => 'Pending verification',
            str_contains($reason, 'active primary adviser') => 'No active adviser',
            str_contains($reason, 'pending adviser review') => 'Under review',
            str_contains($reason, 'Service is closed') => 'Outside service hours',
            str_contains($reason, 'not on an official duty shift') => 'Off duty',
            str_contains($reason, 'readiness check') => 'Readiness required',
            str_contains($reason, 'not set to Available') => 'Not available',
            str_contains($reason, 'already occupies your capacity') => 'In session',
            str_contains($reason, 'two-session duty-shift limit') => 'At capacity (2/2)',
            str_contains($reason, 'different competency or adviser review') => 'Unavailable for this request',
            str_contains($reason, 'conflict prevents this assignment') => 'Conflict prevented',
            default => 'Unavailable',
        };
    }

    public function requireEligible(Helper $helper, ?Session $session = null, bool $activating = false): void
    {
        $reasons = $this->reasons($helper, $session, $activating);
        abort_if($reasons !== [], 409, implode(' ', $reasons));
    }
}
