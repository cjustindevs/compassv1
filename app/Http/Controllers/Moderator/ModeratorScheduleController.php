<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\ReadinessCheck;
use App\Services\HelperShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ModeratorScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $date = Carbon::parse($request->get('date', now(config('app.schedule_timezone'))->toDateString()))->toDateString();

        $helpers = Helper::with('latestReadiness')->orderBy('first_name')->get();

        $scheduleEvents = CalendarEvent::where('event_type', CalendarEvent::TYPE_MEETING)
            ->whereDate('event_date', $date)
            ->orderBy('start_time')
            ->get();

        // A helper can hold several shifts on the same date, so the duty list
        // is grouped per helper instead of assuming a single record.
        $shiftsByHelper = HelperSchedule::with('helper')
            ->forDate($date)
            ->orderByRaw('COALESCE(shift_start, \'00:00:00\')')
            ->orderBy('id')
            ->get()
            ->groupBy('helper_id');

        $attendance = ReadinessCheck::with('helper')
            ->whereDate('assessment_date', $date)
            ->latest('assessment_date')
            ->get()
            ->map(function (ReadinessCheck $check) {
                $dutyMinutes = $check->shift_start && $check->shift_end
                    ? max(0, $check->shift_start->diffInMinutes($check->shift_end))
                    : null;

                return [
                    'helper' => $check->helper?->full_name ?? 'Unknown helper',
                    'status' => $check->availability_status,
                    'result' => $check->result_label,
                    'shift_start' => $check->shift_start?->format('H:i'),
                    'shift_end' => $check->shift_end?->format('H:i'),
                    'duty_hours' => $dutyMinutes !== null ? round($dutyMinutes / 60, 2) : null,
                ];
            });

        return view('moderator.schedules', compact('date', 'helpers', 'scheduleEvents', 'attendance', 'shiftsByHelper'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'helper_id' => 'required|exists:helpers,id',
            'event_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'description' => 'nullable|string|max:500',
        ]);

        $helper = Helper::findOrFail($validated['helper_id']);
        $date = Carbon::parse($validated['event_date'])->toDateString();

        // A helper may hold several non-overlapping shifts on one date, so a
        // clash is an overlapping shift rather than any existing duty record.
        $shift = app(HelperShiftService::class)->saveShift(
            $helper,
            $date,
            $validated['start_time'] ?? null,
            $validated['end_time'] ?? null,
            attributes: ['created_by' => Auth::id()],
        );

        CalendarEvent::create([
            'title' => 'Duty: '.$helper->full_name,
            'description' => ($validated['description'] ?? null)
                ? ($validated['description'].' ('.$shift->shift_label.')')
                : ('Duty shift '.$shift->shift_label),
            'event_date' => $date,
            'start_time' => $shift->shift_start,
            'end_time' => $shift->shift_end,
            'event_type' => CalendarEvent::TYPE_MEETING,
            'helper_schedule_id' => $shift->id,
            'created_by' => Auth::id(),
            'color' => '#04A052',
        ]);

        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());
        app(\App\Services\HelperMatchingService::class)->matchWaitingRequests();

        return redirect()->route('moderator.schedules', ['date' => $date])
            ->with('success', $helper->full_name.' is on duty '.$shift->shift_label.'.');
    }

    public function destroy(Request $request, int $shift): RedirectResponse
    {
        $validated = $request->validate([
            'helper_id' => 'required|exists:helpers,id',
            'date' => 'required|date',
        ]);

        $helper = Helper::findOrFail($validated['helper_id']);
        $date = Carbon::parse($validated['date'])->toDateString();

        app(HelperShiftService::class)->deleteShift($helper, $shift);

        CalendarEvent::where('helper_schedule_id', $shift)->delete();

        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());

        return redirect()->route('moderator.schedules', ['date' => $date])
            ->with('success', 'Duty shift removed for '.$helper->full_name.'.');
    }
}
