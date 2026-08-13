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
                'risk' => ucfirst($session->risk_level ?? 'Low'),
                'concern' => $session->concern->concern_name ?? 'Session',
                'mode' => $session->mode_label,
                'date' => ($session->scheduled_start ?? $session->start_time ?? $session->created_date)->format('Y-m-d'),
                'time' => ($session->scheduled_start ?? $session->start_time)?->format('h:i A'),
            ]);

        $prev = $firstDay->subMonth();
        $next = $firstDay->addMonth();

        $grid = $this->buildMonthGrid($year, $month, $sessions->groupBy('date'));

        return view('helper.calendar', [
            'sessions' => $sessions,
            'grid' => $grid,
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