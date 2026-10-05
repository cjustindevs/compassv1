<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\Session;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelperCalendarController extends Controller
{
    /**
     * Show a monthly calendar with the helper's sessions from the database.
     */
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Auth::user()->helper;

        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $month = max(1, min(12, $month));
        $year = min(2100, max(2000, $year));

        $firstDay = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $lastDay = $firstDay->endOfMonth();

        $sessions = Session::with(['seeker', 'concern'])
            ->where('helper_id', $helper->id)
            ->where(function ($query) use ($firstDay, $lastDay) {
                $query->whereBetween('scheduled_start', [$firstDay, $lastDay])
                    ->orWhereBetween('start_time', [$firstDay, $lastDay])
                    ->orWhereBetween('created_date', [$firstDay, $lastDay]);
            })
            ->get()
            ->map(fn (Session $session) => [
                'id' => $session->id,
                'reference' => $session->reference_number,
                'alias' => $session->seeker->generated_alias ?? 'Seeker',
                'status' => $session->session_status,
                'status_label' => $session->status_label,
                'status_class' => str_replace('_', '-', $session->session_status),
                'risk' => 'Assigned support',
                'concern' => $session->concern->concern_name ?? 'Session',
                'mode' => $session->mode_label,
                'date' => ($session->scheduled_start ?? $session->start_time ?? $session->created_date)->format('Y-m-d'),
                'time' => ($session->scheduled_start ?? $session->start_time)?->format('h:i A'),
            ]);

        $prev = $firstDay->subMonth();
        $next = $firstDay->addMonth();

        $grid = $this->buildMonthGrid($year, $month, $sessions->groupBy('date'));

        return view('helper.calendar', [
            'schedules' => $helper->schedules()->whereBetween('date', [$firstDay->toDateString(), $lastDay->toDateString()])->orderBy('date')->get(),
            'sessions' => $sessions,
            'grid' => $grid,
            'adviser' => $helper->adviser?->loadMissing('user'),
            'month' => $month,
            'year' => $year,
            'monthName' => $firstDay->format('F Y'),
            'prevMonth' => $prev->month,
            'prevYear' => $prev->year,
            'nextMonth' => $next->month,
            'nextYear' => $next->year,
        ]);
    }

    public function declareDuty(Request $request)
    {
        abort_unless($request->user()?->is_active && $request->user()->role === 'helper' && $request->user()->helper,403);
        $data=$request->validate(['date'=>['required','date_format:Y-m-d','after_or_equal:'.now('Asia/Manila')->toDateString()]]);
        $helper=$request->user()->helper;
        \Illuminate\Support\Facades\DB::transaction(function()use($helper,$data){
            $helper=\App\Models\Helper::lockForUpdate()->findOrFail($helper->id);
            $readiness=$helper->getCurrentReadiness();
            if(!$readiness || $readiness->assessment_result!=='ready') throw \Illuminate\Validation\ValidationException::withMessages(['date'=>'Complete and pass your readiness check before declaring duty.']);
            $shift=app(\App\Services\HelperShiftService::class)->scheduleDuty($helper,$data['date'],attributes:['created_by'=>auth()->id()]);
            \App\Services\SupportAudit::record('helper_duty_declared',$shift);
        });
        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());
        return back()->with('success','Duty date recorded. Your Moderator can now see it. Readiness must remain valid before assignment.');
    }

    /**
     * Build the 6-week calendar grid cells.
     */
    private function buildMonthGrid(int $year, int $month, $eventsByDay): array
    {
        $first = CarbonImmutable::create($year, $month, 1);
        $start = $first->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY);
        $end = $first->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $weeks = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $weeks[$day->format('W')][] = [
                'day' => $day->day,
                'date' => $key,
                'in_month' => $day->month === $month,
                'today' => $day->isToday(),
                'events' => $eventsByDay->get($key, collect()),
            ];
        }

        return array_values($weeks);
    }
}
