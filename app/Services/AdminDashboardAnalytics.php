<?php

namespace App\Services;

use App\Models\Session;
use App\Models\User;

class AdminDashboardAnalytics
{
    // Legacy cases retain their recorded creation date; never use updated_at.
    public const SUBMISSION_DATE = 'COALESCE(submitted_at, created_date, created_at)';

    public function data(AnalyticsPeriod $period): array
    {
        $registrations = $period->series(User::query(), 'created_at');
        $requests = $period->series(Session::query(), self::SUBMISSION_DATE);
        $completed = $period->series(Session::whereIn('session_status', [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED]), 'end_time');
        $roles = User::query()->selectRaw('role, COUNT(*) AS total')->groupBy('role')->pluck('total', 'role');

        return $period->dates() + [
            'registrations' => $registrations, 'requests' => $requests, 'completed' => $completed,
            'roles' => ['labels' => $roles->keys()->map(fn ($role) => User::ROLE_LABELS[$role] ?? ucfirst($role))->all(), 'values' => $roles->values()->map(fn ($v) => (int) $v)->all()],
            'missing_completion_dates' => Session::whereIn('session_status', [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED])->whereNull('end_time')->count(),
        ];
    }
}
