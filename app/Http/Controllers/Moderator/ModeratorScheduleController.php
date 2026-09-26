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
            'description' => 'nullable|string|max:500',
        ]);

        $helper = Helper::findOrFail($validated['helper_id']);
        $date = Carbon::parse($validated['event_date'])->toDateString();

        // A date can carry as many helpers as are rostered on it, so the only
        // clash is the same helper being rostered twice for that day.
        $shift = app(HelperShiftService::class)->scheduleDuty(
            $helper,
            $date,
            attributes: ['created_by' => Auth::id()],
            errorKey: 'event_date',
        );

        CalendarEvent::create([
            'title' => 'Duty: '.$helper->full_name,
            'description' => $validated['description'] ?? null,
            'event_date' => $date,
            'start_time' => null,
            'end_time' => null,
            'event_type' => CalendarEvent::TYPE_MEETING,
            'helper_schedule_id' => $shift->id,
            'created_by' => Auth::id(),
            'color' => '#04A052',
        ]);

        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());
        app(\App\Services\HelperMatchingService::class)->matchWaitingRequests();

        return redirect()->route('moderator.schedules', ['date' => $date])
            ->with('success', $helper->full_name.' is on duty on '.Carbon::parse($date)->format('M j, Y').'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'helper_id' => 'required|exists:helpers,id',
            'date' => 'required|date',
            'shift_id' => 'required|integer',
        ]);

        $helper = Helper::findOrFail($validated['helper_id']);
        $date = Carbon::parse($validated['date'])->toDateString();
        $shiftId = (int) $validated['shift_id'];

        // The duty calendar event is linked to its day and cascades on delete,
        // so removing the rostered day takes the event with it.
        app(HelperShiftService::class)->removeDuty($helper, $shiftId);

        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());

        return redirect()->route('moderator.schedules', ['date' => $date])
            ->with('success', 'Duty removed for '.$helper->full_name.' on '.Carbon::parse($date)->format('M j, Y').'.');
    }
}
