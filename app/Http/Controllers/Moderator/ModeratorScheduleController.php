<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Helper;
use App\Models\ReadinessCheck;
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

        return view('moderator.schedules', compact('date', 'helpers', 'scheduleEvents', 'attendance'));
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

        $conflict = \App\Models\HelperSchedule::where('helper_id', $helper->id)
            ->whereDate('date', $date)
            ->exists()
            || CalendarEvent::where('event_type', CalendarEvent::TYPE_MEETING)
            ->whereDate('event_date', $date)
            ->where('title', 'like', 'Duty: ' . $helper->full_name . '%')
            ->exists();

        if ($conflict) {
            return back()->with('error', 'Schedule conflict detected for ' . $helper->full_name . '.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $helper, $date) {
        CalendarEvent::create([
            'title' => 'Duty: ' . $helper->full_name,
            'description' => $validated['description'] ?? null,
            'event_date' => $date,
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'event_type' => CalendarEvent::TYPE_MEETING,
            'created_by' => Auth::id(),
            'color' => '#04A052',
        ]);

        \App\Models\HelperSchedule::create([
            'helper_id' => $helper->id, 'date' => $date,
            'shift_start' => $validated['start_time'] ?? null, 'shift_end' => $validated['end_time'] ?? null,
            'is_active' => true, 'created_by' => Auth::id(),
        ]);
        });
        app(\App\Services\HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());
        app(\App\Services\HelperMatchingService::class)->matchWaitingRequests();

        return redirect()->route('moderator.schedules', ['date' => $date])
            ->with('success', 'Helper duty schedule created.');
    }
}
