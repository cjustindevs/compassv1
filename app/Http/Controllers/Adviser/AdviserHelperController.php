<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelperSchedule;
use App\Models\Session;
use App\Services\HelperMatchingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdviserHelperController extends Controller
{
    /**
     * Show helper management page with status filtering.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->get('status', 'all');
        $adviserId = Auth::user()->adviser?->id;

        $helpers = Helper::with(['user', 'latestReadiness', 'adviser'])
            ->where('adviser_id', $adviserId)
            ->get();

        $helperData = $helpers->map(function (Helper $helper) {
            $activeSessions = Session::where('helper_id', $helper->id)
                ->where('session_status', 'active')
                ->count();

            $competency = HelperCompetencyHistory::where('helper_id', $helper->id)
                ->latest('evaluation_date')
                ->first();

            $averageScore = HelperCompetencyHistory::where('helper_id', $helper->id)
                ->avg('overall_score') ?? 0;

            $readiness = $helper->latestReadiness;

            return [
                'helper' => $helper,
                'initials' => $this->getInitials($helper->first_name, $helper->last_name),
                'level' => $this->getCompetencyLevel($helper->competency_level),
                'competency_score' => $competency ? round($competency->overall_score, 1) : 0,
                'average_rating' => round($averageScore, 1),
                'active_cases' => $activeSessions,
                'status' => $this->getHelperStatus($helper, $readiness),
                'is_available' => $helper->isOnline(),
                'readiness' => $readiness,
            ];
        });

        if ($statusFilter !== 'all') {
            $helperData = $helperData->where('status', $statusFilter);
        }

        $helperData = $helperData->sortByDesc('competency_score')->values();

        $totalHelpers = $helpers->count();
        $availableHelpers = $helpers->where('status', 'available')->count();
        $highCompetency = $helpers->filter(fn (Helper $helper) => $helper->competency_level >= 4)->count();

        return view('adviser.helpers', compact(
            'helperData',
            'totalHelpers',
            'availableHelpers',
            'highCompetency',
            'statusFilter'
        ));
    }

    /**
     * Show helper details.
     */
    public function show(int $id): View
    {
        $helper = Helper::with([
            'user',
            'latestReadiness',
            'adviser',
            'sessions' => function ($query) {
                $query->where('session_status', 'active');
            },
        ])->findOrFail($id);

        $this->authorizeHelper($helper);

        $competencyHistory = HelperCompetencyHistory::where('helper_id', $helper->id)
            ->with('adviser')
            ->orderBy('evaluation_date', 'desc')
            ->limit(10)
            ->get();

        return view('adviser.helper-detail', compact('helper', 'competencyHistory'));
    }

    public function matching(int $id): View
    {
        $helper = Helper::with(['user', 'helperSpecialties', 'competencyHistory', 'sessions.seeker', 'sessions.queue'])->findOrFail($id);
        $this->authorizeHelper($helper);

        $matchingScore = $helper->calculateMatchingScore('low', null, null);
        $matchingDetails = app(HelperMatchingService::class)->getMatchingDetails($helper);
        $recentMatches = Session::where('helper_id', $helper->id)
            ->with(['seeker', 'queue'])
            ->latest('created_date')
            ->limit(10)
            ->get();
        $matchingHistory = AuditLog::where('action', 'helper_assigned')
            ->where('description', 'like', '%"helper_id":' . $helper->id . '%')
            ->latest()
            ->limit(20)
            ->get();
        $specialties = $helper->helperSpecialties;

        return view('adviser.helper-matching', compact('helper', 'matchingHistory', 'matchingScore', 'matchingDetails', 'recentMatches', 'specialties'));
    }

    public function updateCompetency(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'competency_score' => 'required|numeric|min:1|max:5',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $helper = Helper::findOrFail($id);
        $this->authorizeHelper($helper);
        $adviser = Auth::user()->adviser;
        $score = (float) $validated['competency_score'];

        $evaluation = HelperCompetencyHistory::create([
            'helper_id' => $helper->id,
            'adviser_id' => $adviser->id,
            'evaluation_date' => now(),
            'active_listening_score' => $score,
            'empathy_score' => $score,
            'respect_score' => $score,
            'ethical_practices_score' => $score,
            'referral_accuracy_score' => $score,
            'overall_score' => $score,
            'competency_level' => match (true) {
                $score >= 4.5 => 'Outstanding',
                $score >= 3.5 => 'Very Good',
                $score >= 2.5 => 'Satisfactory',
                $score >= 1.5 => 'Needs Improvement',
                default => 'Unsatisfactory',
            },
            'evaluation_period' => now()->format('F Y'),
            'remarks' => $validated['remarks'] ?? null,
        ]);

        $helper->updateCompetency($evaluation);

        return back()->with('success', 'Helper competency updated successfully.');
    }

    public function availability()
    {
        $adviserId = Auth::user()->adviser?->id;

        return response()->json(Helper::where('adviser_id', $adviserId)->with('user')->get()->map(fn (Helper $helper) => [
            'id' => $helper->id,
            'name' => $helper->user?->name ?? $helper->full_name,
            'availability' => $helper->availability ?? ($helper->status === 'available' ? 'available' : 'unavailable'),
            'is_ready' => $helper->isReady(),
            'current_sessions' => $helper->current_shift_sessions,
            'max_sessions' => Helper::MAX_SESSIONS_PER_SHIFT,
            'competency_score' => $helper->competency_score,
            'competency_level' => $helper->competency_level,
            'status' => $helper->status,
            'is_under_review' => $helper->is_under_review,
        ]));
    }

    public function manageSchedule(Request $request): View
    {
        $helperIds = Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');
        $date = Carbon::parse($request->get('date', now()->toDateString()))->startOfDay();
        $schedules = HelperSchedule::whereIn('helper_id', $helperIds)->whereDate('date', $date->toDateString())->with(['helper.user'])->get();
        $helpers = Helper::whereIn('id', $helperIds)->with(['user', 'latestReadiness'])->orderBy('first_name')->get();

        $scheduleData = $helpers->map(function (Helper $helper) use ($schedules) {
            $schedule = $schedules->firstWhere('helper_id', $helper->id);

            return [
                'helper_id' => $helper->id,
                'name' => $helper->user?->name ?? $helper->full_name,
                'availability' => $helper->availability ?? $helper->status ?? 'offline',
                'is_ready' => $helper->isReady(),
                'current_sessions' => $helper->current_shift_sessions ?? 0,
                'max_sessions' => Helper::MAX_SESSIONS_PER_SHIFT,
                'has_schedule' => $schedule !== null,
                'is_on_shift' => $schedule?->isWithinShift() ?? false,
                'can_accept_sessions' => $helper->canAcceptSessions(),
                'shift_start' => $schedule?->shift_start,
                'shift_end' => $schedule?->shift_end,
            ];
        });

        return view('adviser.schedule', compact('scheduleData', 'helpers', 'date'));
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'helper_id' => 'required|exists:helpers,id',
            'date' => 'required|date',
            'shift_start' => 'required|date_format:H:i',
            'shift_end' => 'required|date_format:H:i|after:shift_start',
            'is_recurring' => 'nullable|boolean',
            'recurrence_pattern' => 'nullable|array',
        ]);

        $helper = Helper::where('id', $validated['helper_id'])
            ->where('adviser_id', Auth::user()->adviser?->id)
            ->firstOrFail();

        HelperSchedule::updateOrCreate(
            ['helper_id' => $helper->id, 'date' => $validated['date']],
            [
                'shift_start' => $validated['shift_start'],
                'shift_end' => $validated['shift_end'],
                'is_recurring' => (bool) ($validated['is_recurring'] ?? false),
                'recurrence_pattern' => $validated['recurrence_pattern'] ?? null,
                'created_by' => Auth::id(),
                'approved_by' => Auth::user()->adviser?->id,
                'approved_at' => now(),
                'is_active' => true,
            ]
        );

        return back()->with('success', 'Schedule updated successfully.');
    }

    public function assign(Request $request, int $id): RedirectResponse
    {
        $helper = Helper::findOrFail($id);
        $this->authorizeHelper($helper);

        return back()->with('error', 'Adviser assignments are handled automatically by the system and moderators.');
    }

    public function reassignHelpers(Request $request): RedirectResponse
    {
        return back()->with('error', 'Helper reassignment is restricted to moderators and system assignment.');
    }

    /**
     * Export helper data as CSV.
     */
    public function export(Request $request)
    {
        $statusFilter = $request->get('status', 'all');
        $adviserId = Auth::user()->adviser?->id;

        $helpers = Helper::with(['adviser'])
            ->where('adviser_id', $adviserId)
            ->get()
            ->map(function (Helper $helper) {
                $competency = HelperCompetencyHistory::where('helper_id', $helper->id)
                    ->latest('evaluation_date')
                    ->first();

                return [
                    'helper' => $helper,
                    'status' => $this->getHelperStatus($helper, $helper->latestReadiness),
                    'competency_score' => $competency ? round($competency->overall_score, 1) : 0,
                    'active_cases' => Session::where('helper_id', $helper->id)
                        ->where('session_status', 'active')
                        ->count(),
                    'total_sessions' => Session::where('helper_id', $helper->id)->count(),
                ];
            });

        if ($statusFilter !== 'all') {
            $helpers = $helpers->where('status', $statusFilter);
        }

        $filename = 'compass-helpers-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($helpers) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Name',
                'Email',
                'Status',
                'Competency Level',
                'Latest Score',
                'Active Cases',
                'Total Sessions',
                'Assigned Adviser',
                'Joined',
            ]);

            foreach ($helpers as $row) {
                $helper = $row['helper'];
                fputcsv($file, [
                    $helper->getFullNameAttribute(),
                    $helper->email,
                    ucfirst($row['status']),
                    $helper->competency_level,
                    $row['competency_score'],
                    $row['active_cases'],
                    $row['total_sessions'],
                    $helper->adviser ? $helper->adviser->first_name . ' ' . $helper->adviser->last_name : 'Unassigned',
                    $helper->created_at?->format('Y-m-d') ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get competency level based on the numeric competency_level column
     * (1 = trainee … 5 = expert).
     */
    private function getCompetencyLevel(?int $level): string
    {
        return match ($level) {
            5 => 'Expert',
            4 => 'Advanced',
            3 => 'Intermediate',
            2 => 'Beginner',
            default => 'Trainee',
        };
    }

    /**
     * Get helper status.
     */
    private function getHelperStatus(Helper $helper, $readiness): string
    {
        if ($helper->isOnline() && $readiness && $readiness->assessment_result === 'ready') {
            return 'online';
        }

        if ($readiness && $readiness->assessment_result === 'ready') {
            return 'available';
        }

        if ($readiness && $readiness->assessment_result === 'not_ready') {
            return 'offline';
        }

        return 'inactive';
    }

    /**
     * Get initials from first and last name.
     */
    private function getInitials(?string $firstName, ?string $lastName): string
    {
        return strtoupper(substr((string) $firstName, 0, 1) . substr((string) $lastName, 0, 1));
    }

    private function authorizeHelper(Helper $helper): void
    {
        abort_unless($helper->adviser_id === Auth::user()->adviser?->id, 403, 'You are not authorized to manage this helper.');
    }
}
