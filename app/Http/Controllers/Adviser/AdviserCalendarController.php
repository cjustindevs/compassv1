<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\HelperCompetencyHistory;
use App\Models\Session;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdviserCalendarController extends Controller
{
    public function index(Request $request): View
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $monthStart = now()->setDate($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        // Sessions whose effective date falls within this month
        $sessions = Session::where(function ($query) use ($monthStart, $monthEnd) {
            $query->whereBetween('scheduled_start', [$monthStart, $monthEnd])
                ->orWhereBetween('start_time', [$monthStart, $monthEnd])
                ->orWhereBetween('created_date', [$monthStart, $monthEnd]);
        })
            ->with(['seeker', 'helper'])
            ->get();

        // Competency evaluations within this month
        $evaluations = HelperCompetencyHistory::whereBetween('evaluation_date', [$monthStart, $monthEnd])
            ->with(['helper', 'adviser'])
            ->get();

        // Custom events within this month
        $customEvents = CalendarEvent::whereBetween('event_date', [$monthStart, $monthEnd])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        $eventsByDate = $this->collectEvents($monthStart, $sessions, $evaluations, $customEvents);

        $weeks = $this->buildMonthGrid($year, $month, $eventsByDate);

        $prevMonth = $monthStart->copy()->subMonth();
        $nextMonth = $monthStart->copy()->addMonth();

        return view('adviser.calendar', compact(
            'month',
            'year',
            'weeks',
            'eventsByDate',
            'sessions',
            'evaluations',
            'customEvents',
            'prevMonth',
            'nextMonth'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'event_type' => 'required|in:' . implode(',', CalendarEvent::EVENT_TYPES),
            'color' => 'nullable|string|max:20',
        ]);

        CalendarEvent::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'event_type' => $validated['event_type'],
            'created_by' => Auth::id(),
            'color' => $validated['color'] ?? '#04A052',
        ]);

        return redirect()->route('adviser.calendar', [
            'month' => \Illuminate\Support\Carbon::parse($validated['event_date'])->month,
            'year' => \Illuminate\Support\Carbon::parse($validated['event_date'])->year,
        ])->with('success', 'Event created successfully.');
    }

    /**
     * Build the associative map date => list of events.
     */
    private function collectEvents($monthStart, $sessions, $evaluations, $customEvents): array
    {
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $events = [];

        foreach ($sessions as $session) {
            $effective = $session->scheduled_start ?? $session->start_time ?? $session->created_date;
            if ($effective && $effective->between($monthStart, $monthEnd)) {
                $date = $effective->format('Y-m-d');
                $events[$date][] = [
                    'type' => 'session',
                    'title' => 'Session: ' . ($session->seeker->generated_alias ?? 'Seeker'),
                    'color' => '#04A052',
                    'time' => $effective->format('H:i'),
                    'detail' => $session->getStatusLabelAttribute() . ' · ' . ucfirst($session->session_type),
                ];
            }
        }

        foreach ($evaluations as $evaluation) {
            if ($evaluation->evaluation_date && $evaluation->evaluation_date->between($monthStart, $monthEnd)) {
                $date = $evaluation->evaluation_date->format('Y-m-d');
                $events[$date][] = [
                    'type' => 'evaluation',
                    'title' => 'Evaluation: ' . ($evaluation->helper?->getFullNameAttribute() ?? 'Helper'),
                    'color' => '#3B82F6',
                    'time' => $evaluation->evaluation_date->format('H:i'),
                    'detail' => 'Score ' . round((float) ($evaluation->overall_score ?? 0), 1) . ' / 5',
                ];
            }
        }

        foreach ($customEvents as $event) {
            $date = $event->event_date->format('Y-m-d');
            $events[$date][] = [
                'type' => $event->event_type,
                'title' => $event->title,
                'color' => $event->color,
                'time' => $event->start_time ? substr((string) $event->start_time, 0, 5) : null,
                'detail' => $event->getTypeLabelAttribute() . ($event->description ? ' — ' . $event->description : ''),
            ];
        }

        return $events;
    }

    /**
     * Monday-first month grid: array of weeks, each with 7 day cells.
     * Cells are null when outside the month, otherwise [day, date, events].
     */
    private function buildMonthGrid(int $year, int $month, array $eventsByDate): array
    {
        $firstDayOfWeek = (int) now()->setDate($year, $month, 1)->format('N'); // 1 = Monday
        $daysInMonth = (int) now()->setDate($year, $month, 1)->daysInMonth;

        $weeks = [];
        $day = 1;
        $week = [];

        for ($i = 0; $i < $firstDayOfWeek - 1; $i++) {
            $week[] = null;
        }

        while ($day <= $daysInMonth) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $week[] = [
                'day' => $day,
                'date' => $date,
                'events' => $eventsByDate[$date] ?? [],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $day++;
        }

        while (count($week) > 0 && count($week) < 7) {
            $week[] = null;
        }

        if ($week) {
            $weeks[] = $week;
        }

        return $weeks;
    }
}
