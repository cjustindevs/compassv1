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
        $date = Carbon::parse($request->get('date', now()->toDateString()))->toDateString();

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
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'description' => 'nullable|string|max:500',
        ]);

        $helper = Helper::findOrFail($validated['helper_id']);

        $conflict = CalendarEvent::where('event_type', CalendarEvent::TYPE_MEETING)
            ->whereDate('event_date', $validated['event_date'])
            ->where('title', 'like', 'Duty: ' . $helper->full_name . '%')
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhere(function ($query) use ($validated) {
                        $query->where('start_time', '<=', $validated['start_time'])
                            ->where('end_time', '>=', $validated['end_time']);
                    });
            })
            ->exists();

        if ($conflict) {
            return back()->with('error', 'Schedule conflict detected for ' . $helper->full_name . '.');
        }

        CalendarEvent::create([
            'title' => 'Duty: ' . $helper->full_name,
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'event_type' => CalendarEvent::TYPE_MEETING,
            'created_by' => Auth::id(),
            'color' => '#04A052',
        ]);

        return redirect()->route('moderator.schedules', ['date' => $validated['event_date']])
            ->with('success', 'Helper duty schedule created.');
    }
}
