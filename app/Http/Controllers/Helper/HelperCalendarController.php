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

        $month = $request->integer('month', now('Asia/Manila')->month);
        $year = $request->integer('year', now('Asia/Manila')->year);

        $month = max(1, min(12, $month));
        $year = min(2100, max(2000, $year));

        $firstDay = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Manila')->startOfDay();
        $lastDay = $firstDay->endOfMonth();
        $utcStart=$firstDay->utc(); $utcEnd=$lastDay->utc();

        $sessions = Session::with(['seeker', 'concern'])
            ->where('helper_id', $helper->id)
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('COALESCE(scheduled_start,start_time,created_date,created_at)'), [$utcStart,$utcEnd])
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
                'date' => ($session->scheduled_start ?? $session->start_time ?? $session->created_date ?? $session->created_at)->copy()->timezone('Asia/Manila')->format('Y-m-d'),
                'time' => ($session->scheduled_start ?? $session->start_time)?->copy()->timezone('Asia/Manila')->format('h:i A'),
            ]);

        $prev = $firstDay->subMonth();
        $next = $firstDay->addMonth();

        $schedules=$helper->schedules()->whereBetween('date', [$firstDay->toDateString(), $lastDay->toDateString()])->orderBy('date')->get();
        $events=$sessions->map(fn($s)=>$s+['kind'=>'session']);
        foreach($schedules as $shift) $events->push(['kind'=>'duty','date'=>$shift->date->format('Y-m-d'),'time'=>$shift->shift_label,
            'status'=>$shift->is_active ? ($shift->isOnDuty() ? 'current' : ($shift->window()[1]->isPast() ? 'past' : 'upcoming')) : 'inactive',
            'reference'=>'Duty','alias'=>'Assigned duty']);
        $grid = $this->buildMonthGrid($year, $month, $events->groupBy('date'));

        return view('helper.calendar', [
            'schedules' => $schedules,
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

    /**
     * Build the 6-week calendar grid cells.
     */
    private function buildMonthGrid(int $year, int $month, $eventsByDay): array
    {
        $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Manila');
        $start = $first->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY);
        $end = $first->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $days[] = [
                'day' => $day->day,
                'date' => $key,
                'in_month' => $day->month === $month,
                'today' => $day->isToday(),
                'events' => $eventsByDay->get($key, collect()),
            ];
        }

        return array_chunk($days, 7);
    }
}
