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

        $helpers = Helper::with(['user:id,id,name', 'latestReadiness', 'adviser'])
            ->withCount(['activeSessions as active_cases'])
            ->withAggregate('latestCompetency', 'overall_score')
            ->where('adviser_id', $adviserId)
            ->get();

        $helperIds = $helpers->pluck('id');

        $avgScores = HelperCompetencyHistory::whereIn('helper_id', $helperIds)
            ->selectRaw('helper_id, AVG(overall_score) as avg_score')
            ->groupBy('helper_id')
            ->pluck('avg_score', 'helper_id');

        $helperData = $helpers->map(function (Helper $helper) use ($avgScores) {
            $readiness = $helper->latestReadiness;
            $status = $this->getHelperStatus($helper);

            return [
                'helper' => $helper,
                'initials' => $this->getInitials($helper->first_name, $helper->last_name),
                'level' => $this->getCompetencyLevel($helper->competency_level),
                'competency_score' => $helper->latest_competency_overall_score ? round((float) $helper->latest_competency_overall_score, 1) : 0,
                'average_rating' => round((float) ($avgScores->get($helper->id, 0)), 1),
                'active_cases' => $helper->active_cases,
                'status' => $status,
                'is_available' => $status === 'available',
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

                $transferAdvisers = \App\Models\Adviser::where('id', '!=', $helper->adviser_id)->whereHas('user', fn ($q) => $q->where('is_active', true))->get();
        $assignmentHistory = \Illuminate\Support\Facades\DB::table('adviser_helper_assignments')->where('helper_id', $helper->id)->latest('id')->get();
        return view('adviser.helper-detail', compact('helper', 'competencyHistory', 'transferAdvisers', 'assignmentHistory'));
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()?->role==='adviser' && $request->user()?->is_active,403);
        $helper=Helper::findOrFail($id);
        $this->authorizeHelper($helper);
        $data=$request->validate([
            'currently_enrolled'=>'accepted', 'recognized_member'=>'accepted', 'training_completed'=>'accepted',
            'qualification_evidence'=>'required|string|max:2000',
            'verification_expires_at'=>'nullable|date|after:today',
        ]);
        \Illuminate\Support\Facades\DB::transaction(function()use($helper,$data,$request){
            $helper=Helper::lockForUpdate()->findOrFail($helper->id);
            $this->authorizeHelper($helper);
            $helper->update(['verification_status'=>'verified','verified_at'=>now(),'verified_by'=>$request->user()->id,'training_verified'=>true,'qualification_evidence'=>$data['qualification_evidence'],'verification_expires_at'=>$data['verification_expires_at']??null]);
            \App\Services\SupportAudit::record('helper_institutionally_verified',$helper,['verifier_id'=>$request->user()->id]);
            \App\Models\Notification::create(['user_account_id'=>$helper->user_account_id,'title'=>'Helper verification approved','message'=>'Your adviser verified your institutional eligibility and training. Check your duty schedule and complete readiness before accepting sessions.','notification_type'=>'system','type_icon'=>'fa-user-check','link'=>'/helper/dashboard']);
        },3);

        // Verification is an eligibility change: reconcile the operational
        // status so a willing, on-shift, ready helper becomes matchable
        // immediately instead of waiting for the scheduler.
        $helper = $helper->fresh();
        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper);
        app(\App\Services\HelperMatchingService::class)->matchWaitingRequests();

        return back()->with('success','Institutional eligibility and training verification recorded.');
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
        $helper=Helper::findOrFail($id);
        $this->authorizeHelper($helper);
        return redirect()->route('adviser.evaluations')->with('info','Use the session evaluation form to rate all five competency criteria separately.');
    }

    public function availability()
    {
        $adviserId = Auth::user()->adviser?->id;

        return response()->json(Helper::where('adviser_id', $adviserId)->with('user')->get()->map(fn (Helper $helper) => [
            'id' => $helper->id,
            'name' => $helper->user?->name ?? $helper->full_name,
            'availability' => $helper->availability ?? ($helper->status === 'available' ? 'available' : 'unavailable'),
            'is_ready' => (bool) $helper->getCurrentReadiness(),
            'current_sessions' => $helper->current_shift_sessions,
            'max_sessions' => Helper::MAX_SESSIONS_PER_SHIFT,
            'competency_score' => $helper->competency_score,
            'competency_level' => $helper->competency_level,
            'status' => $helper->status,
            'status_label' => app(\App\Services\HelperEligibilityService::class)->status($helper)['label'],
            'can_accept_sessions' => app(\App\Services\HelperEligibilityService::class)->status($helper)['assignable'],
            'is_under_review' => $helper->is_under_review,
        ]));
    }

    public function manageSchedule(Request $request): View
    {
        $helperIds = Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $date = Carbon::parse($request->get('date', now(config('app.schedule_timezone'))->toDateString()))->startOfDay();
        $schedules = HelperSchedule::whereIn('helper_id', $helperIds)->whereDate('date', $date->toDateString())->with(['helper.user'])->get();
        $helpers = Helper::whereIn('id', $helperIds)->with(['user', 'latestReadiness'])->orderBy('first_name')->get();

        $scheduleData = $helpers->map(function (Helper $helper) use ($schedules) {
            $schedule = $schedules->firstWhere('helper_id', $helper->id);

            return [
                'helper_id' => $helper->id,
                'name' => $helper->user?->name ?? $helper->full_name,
                'availability' => $helper->availability ?? $helper->status ?? 'offline',
                'is_ready' => (bool) $helper->getCurrentReadiness(),
                'current_sessions' => $helper->current_shift_sessions ?? 0,
                'max_sessions' => Helper::MAX_SESSIONS_PER_SHIFT,
                'has_schedule' => $schedule !== null,
                'is_on_shift' => $schedule?->isWithinShift() ?? false,
                'can_accept_sessions' => app(\App\Services\HelperEligibilityService::class)->status($helper)['assignable'],
                'status_label' => app(\App\Services\HelperEligibilityService::class)->status($helper)['label'],
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
            'date' => 'required|date_format:Y-m-d',
            'shift_start' => 'required|date_format:H:i',
            'shift_end' => 'required|date_format:H:i|after:shift_start',
            'is_recurring' => 'nullable|boolean',
            'recurrence_pattern' => 'nullable|array',
        ]);

        $helper = Helper::where('id', $validated['helper_id'])
            ->where('adviser_id', Auth::user()->adviser?->id)
            ->firstOrFail();

        $schedule = HelperSchedule::where('helper_id', $helper->id)
            ->whereDate('date', $validated['date'])->first()
            ?? new HelperSchedule(['helper_id' => $helper->id, 'date' => $validated['date']]);
        $schedule->fill([
                'shift_start' => $validated['shift_start'],
                'shift_end' => $validated['shift_end'],
                'is_recurring' => (bool) ($validated['is_recurring'] ?? false),
                'recurrence_pattern' => $validated['recurrence_pattern'] ?? null,
                'created_by' => Auth::id(),
                'approved_by' => Auth::user()->adviser?->id,
                'approved_at' => now(),
                'is_active' => true,
        ])->save();

        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());
        app(\App\Services\HelperMatchingService::class)->matchWaitingRequests();

        return redirect()->route('adviser.schedule', ['date' => $validated['date']])
            ->with('success', 'Schedule updated successfully.');
    }

    public function assign(Request $request, int $id): RedirectResponse
    {
        $helper = Helper::findOrFail($id);
        $this->authorizeHelper($helper);

        return back()->with('error', 'Adviser assignments are handled automatically by the system and moderators.');
    }

    public function reassignHelpers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'helper_ids' => 'required|array|min:1', 'helper_ids.*' => 'required|integer|distinct|exists:helpers,id',
            'adviser_id' => 'required|integer|exists:advisers,id', 'reason' => 'required|string|max:1000',
        ]);
        app(\App\Services\AdviserAssignmentService::class)->transfer($data['helper_ids'], (int) $data['adviser_id'], $data['reason']);
        return redirect()->route('adviser.helpers')->with('success', 'Supervision and active referrals transferred successfully.');
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
            ->get();

        $helperIds = $helpers->pluck('id');

        $latestCompetency = HelperCompetencyHistory::whereIn('helper_id', $helperIds)
            ->selectRaw('helper_id, overall_score')
            ->orderBy('evaluation_date', 'desc')
            ->get()->unique('helper_id')
            ->pluck('overall_score', 'helper_id');

        $activeCounts = Session::whereIn('helper_id', $helperIds)
            ->where('session_status', 'active')
            ->selectRaw('helper_id, COUNT(*) as cnt')
            ->groupBy('helper_id')
            ->pluck('cnt', 'helper_id');

        $totalCounts = Session::whereIn('helper_id', $helperIds)
            ->selectRaw('helper_id, COUNT(*) as cnt')
            ->groupBy('helper_id')
            ->pluck('cnt', 'helper_id');

        $helpers = $helpers->map(function (Helper $helper) use ($latestCompetency, $activeCounts, $totalCounts) {
            return [
                'helper' => $helper,
                'status' => $this->getHelperStatus($helper),
                'competency_score' => $latestCompetency->has($helper->id) ? round($latestCompetency[$helper->id], 1) : 0,
                'active_cases' => $activeCounts->get($helper->id, 0),
                'total_sessions' => $totalCounts->get($helper->id, 0),
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
     * Get helper status derived from the single HelperEligibilityService
     * source so advisers always see the same state the matching engine sees.
     */
    private function getHelperStatus(Helper $helper): string
    {
        $status = app(\App\Services\HelperEligibilityService::class)->status($helper);

        if ($status['assignable']) {
            return 'available';
        }

        return match ($status['label']) {
            'In session', 'At capacity (2/2)' => 'online',
            'Readiness required', 'Off duty', 'Outside service hours', 'Not available' => 'offline',
            default => 'inactive',
        };
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
        $adviser = app(\App\Services\AdviserScope::class)->actor();
        abort_unless($helper->adviser_id === $adviser->id, 403, 'You are not authorized to manage this helper.');
    }
}
