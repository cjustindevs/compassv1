<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\Session;
use App\Services\DutySchedulingService;
use App\Services\HelperDutyCandidates;
use App\Services\HelperEligibilityService;
use App\Services\HelperMatchingService;
use App\Services\HelperShiftService;
use App\Services\HelperWorkflowMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ModeratorScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d', 'availability' => 'nullable|in:available,offline,busy',
            'helper_id' => 'nullable|integer|exists:helpers,id', 'edit' => 'nullable|integer', 'tab' => 'nullable|in:duties,sessions']);
        $date = $request->input('date', now('Asia/Manila')->toDateString());

        $helpers = $this->helpers();
        // Duty candidates must be chosen before the operational availability
        // filter: a ready Helper without a duty day is not assignable yet.
        $dutyHelpers = $this->dutyHelpers($helpers);

        if ($request->filled('availability')) {
            $helpers = $helpers->filter(function ($helper) use ($request) {
                $available = app(HelperEligibilityService::class)->allows($helper);
                $busy = $helper->activeSessions()->exists();

                return match ($request->input('availability')) {
                    'available' => $available,'busy' => $busy,'offline' => ! $available && ! $busy
                };
            });
        }

        $helperIds = $helpers->pluck('id');
        $scheduleEvents = CalendarEvent::with('helperSchedule')->where('event_type', CalendarEvent::TYPE_MEETING)
            ->whereDate('event_date', $date)
            ->when($request->filled('availability'), fn ($q) => $q->whereIn('helper_schedule_id', HelperSchedule::whereIn('helper_id', $helperIds)->select('id')))
            ->orderBy('start_time')
            ->get();

        // Keep scheduled support sessions separate from Helper duty dates.
        $scheduledSessions = Session::with('helper', 'seeker')
            ->whereNotNull('scheduled_start')->whereNull('archived_at')
            ->whereBetween('scheduled_start', [Carbon::parse($date, 'Asia/Manila')->startOfDay()->utc(), Carbon::parse($date, 'Asia/Manila')->endOfDay()->utc()])
            ->when($request->filled('availability'), fn ($q) => $q->whereIn('helper_id', $helperIds))
            ->orderBy('scheduled_start')->paginate(15)->withQueryString();

        $duties = HelperSchedule::with('helper.user')->where('is_active', true)->whereDate('date', '>=', $date)->whereDate('date', '<=', Carbon::parse($date)->addDays(30))
            ->when($request->filled('helper_id'), fn ($q) => $q->where('helper_id', (int) $request->input('helper_id')))->orderBy('date')->orderBy('id')->paginate(15, ['*'], 'duties_page')->withQueryString();
        $edit = $request->filled('edit') ? HelperSchedule::where('is_active', true)->findOrFail((int) $request->input('edit')) : null;

        return view('moderator.schedules', compact('date', 'helpers', 'dutyHelpers', 'scheduleEvents', 'scheduledSessions', 'duties', 'edit'));
    }

    public function candidates(): JsonResponse
    {
        return response()->json([
            'helpers' => $this->dutyHelpers($this->helpers())->map(fn (Helper $helper) => [
                'id' => $helper->id,
                'name' => $helper->full_name,
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function helpers(): Collection
    {
        return Helper::with('user', 'adviser.user')->orderBy('first_name')->orderBy('id')->get();
    }

    private function dutyHelpers(Collection $helpers): Collection
    {
        return $helpers->filter(fn (Helper $helper) => app(HelperDutyCandidates::class)->allows($helper));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['schedule_type' => $request->input('schedule_type', 'single'), 'shift_start' => $request->input('shift_start', '18:00'),
            'shift_end' => $request->input('shift_end', '23:00'), 'end_date' => $request->input('end_date', $request->input('event_date'))]);
        $validated = $request->validate([
            'helper_id' => 'required|exists:helpers,id',
            'event_date' => 'required|date',
            'description' => 'nullable|string|max:300',
            'schedule_type' => 'required|in:single,recurring', 'end_date' => 'required|date|after_or_equal:event_date',
            'shift_start' => 'required|date_format:H:i', 'shift_end' => 'required|date_format:H:i|after:shift_start',
            'weekdays' => 'required_if:schedule_type,recurring|array', 'weekdays.*' => 'integer|between:0,6|distinct',
            'shift_id' => 'nullable|integer',
        ]);

        $helper = Helper::findOrFail($validated['helper_id']);
        if (! app(HelperDutyCandidates::class)->allows($helper)) {
            throw ValidationException::withMessages(['helper_id' => 'Select an active, verified Helper with an active Adviser assignment. Readiness and login are not required for scheduling.']);
        }
        $date = Carbon::parse($validated['event_date'])->toDateString();

        // A date can carry as many helpers as are rostered on it, so the only
        // clash is the same helper being rostered twice for that day.
        app(DutySchedulingService::class)->save($helper, $validated);

        app(HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());
        app(HelperMatchingService::class)->matchWaitingRequests();

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

        // Cancel duty while retaining readiness and audit history.
        $request->validate(['scope' => 'nullable|in:one,future']);
        $selected = HelperSchedule::where('helper_id', $helper->id)->findOrFail($shiftId);
        if ($selected->date->lt(now('Asia/Manila')->startOfDay())) {
            throw ValidationException::withMessages(['shift_id' => 'Past duty records are retained. Choose a current or future duty.']);
        }
        DB::transaction(function () use ($request, $helper, $selected) {
            $targets = $request->input('scope') === 'future' && $selected->series_id
                ? HelperSchedule::where('helper_id', $helper->id)->where('is_active', true)->where('series_id', $selected->series_id)->whereDate('date', '>=', $selected->date->format('Y-m-d'))->get() : collect([$selected]);
            foreach ($targets as $target) {
                app(HelperShiftService::class)->removeDuty($helper, $target->id, $targets->count() === 1);
            }
            if ($targets->count() > 1) {
                app(DutySchedulingService::class)->notify($helper, 'cancelled',
                    $targets->count().' duty dates from '.$selected->date->format('M d, Y'));
            }
        });

        app(HelperWorkflowMaintenance::class)->reconcileHelperAvailability($helper->fresh());

        return redirect()->route('moderator.schedules', ['date' => $date])
            ->with('success', 'Duty removed for '.$helper->full_name.' on '.Carbon::parse($date)->format('M j, Y').'.');
    }
}
