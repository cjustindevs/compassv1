<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Helper;
use App\Models\HelperSchedule;
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
        $request->validate(['date'=>'nullable|date_format:Y-m-d','availability'=>'nullable|in:available,offline,busy']);
        $date = $request->input('date', now('Asia/Manila')->toDateString());

        $helpers = Helper::with(['latestReadiness', 'user'])->orderBy('first_name')->get();

        if ($request->filled('availability')) {
            $helpers=$helpers->filter(function($helper)use($request){$available=app(\App\Services\HelperEligibilityService::class)->allows($helper);$busy=$helper->activeSessions()->exists();return match($request->input('availability')){'available'=>$available,'busy'=>$busy,'offline'=>!$available&&!$busy};});
        }

        $helperIds=$helpers->pluck('id');
        $dutyHelpers = $helpers->filter(fn (Helper $helper) => app(\App\Services\HelperDutyCandidates::class)->allows($helper));
        $scheduleEvents = CalendarEvent::with('helperSchedule')->where('event_type', CalendarEvent::TYPE_MEETING)
            ->whereDate('event_date', $date)
            ->when($request->filled('availability'),fn($q)=>$q->whereIn('helper_schedule_id',HelperSchedule::whereIn('helper_id',$helperIds)->select('id')))
            ->orderBy('start_time')
            ->get();

        // A helper can hold several shifts on the same date, so the duty list
        // is grouped per helper instead of assuming a single record.
        $scheduledSessions = \App\Models\Session::with('helper','seeker')
            ->whereNotNull('scheduled_start')->whereNull('archived_at')
            ->whereBetween('scheduled_start', [Carbon::parse($date,'Asia/Manila')->startOfDay()->utc(), Carbon::parse($date,'Asia/Manila')->endOfDay()->utc()])
            ->when($request->filled('availability'), fn ($q) => $q->whereIn('helper_id',$helperIds))
            ->orderBy('scheduled_start')->paginate(15)->withQueryString();

        return view('moderator.schedules', compact('date', 'helpers', 'dutyHelpers', 'scheduleEvents', 'scheduledSessions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'helper_id' => 'required|exists:helpers,id',
            'event_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        $helper = Helper::findOrFail($validated['helper_id']);
        if (! app(\App\Services\HelperDutyCandidates::class)->allows($helper)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['helper_id' => 'Select a logged-in Helper with a current passed readiness check. Refresh the page if their status has changed.']);
        }
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
