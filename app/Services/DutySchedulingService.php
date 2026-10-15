<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\Notification;
use App\Models\ReadinessCheck;
use App\Models\StaffMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DutySchedulingService
{
    public function save(Helper $helper, array $data): void
    {
        if (! app(HelperDutyCandidates::class)->allows($helper)) {
            throw ValidationException::withMessages(['helper_id' => 'Choose an active, verified Helper with an active Adviser assignment. Readiness and login are not required for scheduling.']);
        }
        $start = Carbon::parse($data['event_date'], 'Asia/Manila');
        $end = Carbon::parse($data['end_date'] ?? $data['event_date'], 'Asia/Manila');
        if ($start->lt(now('Asia/Manila')->startOfDay())) {
            throw ValidationException::withMessages(['event_date' => 'Choose today or a future duty date.']);
        }
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'The end date must follow the start date.']);
        }
        if ($start->diffInDays($end) > 90) {
            throw ValidationException::withMessages(['end_date' => 'Schedule up to 90 days at a time.']);
        }
        if (config('app.enforce_duty_hours') && ($data['shift_start'] < '18:00' || $data['shift_end'] > '23:00')) {
            throw ValidationException::withMessages(['shift_start' => 'Choose a shift within 6 PM–11 PM.']);
        }
        DB::transaction(function () use ($helper, $data, $start, $end) {
            $helper = Helper::whereKey($helper->id)->lockForUpdate()->firstOrFail();
            if (! app(HelperDutyCandidates::class)->allows($helper)) {
                throw ValidationException::withMessages(['helper_id' => 'This Helper is no longer approved for scheduling.']);
            }
            $edit = isset($data['shift_id']) ? HelperSchedule::where('helper_id', $helper->id)->where('is_active', true)->findOrFail($data['shift_id']) : null;
            if (auth()->user()?->role === 'adviser' && $helper->adviser_id !== auth()->user()->adviser?->id) abort(403, 'Helper supervision changed. Refresh the schedule.');
            if ($edit && isset($data['expected_updated_at']) && ! $edit->updated_at->equalTo(Carbon::parse($data['expected_updated_at']))) {
                throw ValidationException::withMessages(['shift_id' => 'This duty was changed by another staff member. Refresh it before editing.']);
            }
            if ($edit && $edit->date->lt(now('Asia/Manila')->startOfDay())) {
                throw ValidationException::withMessages(['shift_id' => 'Past duty records cannot be edited.']);
            }
            if ($edit && $edit->isOnDuty() && $helper->activeSessions()->exists()) {
                throw ValidationException::withMessages(['shift_id' => 'Finish or hand off the active assignment before editing duty.']);
            }
            if ($edit) {
                ReadinessCheck::where('helper_schedule_id', $edit->id)->where('is_active', true)->update(['is_active' => false]);
            }
            $recurring = ($data['schedule_type'] ?? 'single') === 'recurring' && ! $edit;
            $series = $recurring ? (string) Str::uuid() : $edit?->series_id;
            $saved = 0;
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($recurring && ! in_array($date->dayOfWeek, array_map('intval', $data['weekdays'] ?? []), true)) {
                    continue;
                }
                $shift = app(HelperShiftService::class)->scheduleDuty($helper, $date->toDateString(), $edit?->id,
                    ['shift_start' => $data['shift_start'], 'shift_end' => $data['shift_end'], 'series_id' => $series, 'duty_notes' => $data['description'] ?? null, 'created_by' => auth()->id()], 'event_date');
                if ($date->isSameDay(now('Asia/Manila'))) {
                    ReadinessCheck::where('helper_id', $helper->id)->where('is_active', true)
                        ->where(fn ($q) => $q->whereNull('helper_schedule_id')->orWhere('helper_schedule_id', '!=', $shift->id))->update(['is_active' => false]);
                }
                CalendarEvent::updateOrCreate(['helper_schedule_id' => $shift->id], ['title' => 'Duty: '.$helper->full_name, 'description' => $data['description'] ?? null,
                    'event_date' => $date->toDateString(), 'start_time' => $data['shift_start'], 'end_time' => $data['shift_end'], 'event_type' => CalendarEvent::TYPE_MEETING, 'created_by' => auth()->id(), 'color' => '#04A052']);
                SupportAudit::record($edit ? 'helper_duty_updated' : 'helper_duty_created', $shift);
                $saved++;
                if (! $recurring) {
                    break;
                }
            }
            if (! $saved) {
                throw ValidationException::withMessages(['weekdays' => 'No selected weekdays fall within the date range.']);
            }
            if (! $helper->activeSessions()->exists() && ! $helper->getCurrentReadiness()?->isReady()) {
                $helper->update(['status' => 'offline', 'availability' => 'unavailable', 'is_ready' => false, 'available_since' => null]);
            }
            $this->notify($helper, $edit ? 'updated' : 'scheduled', $start->format('M d, Y').($recurring ? ' to '.$end->format('M d, Y') : '').' ('.$data['shift_start'].'–'.$data['shift_end'].' Philippine Time)');
        });
    }

    public function notify(Helper $helper, string $action, string $period): void
    {
        $message = 'Your duty has been '.$action.': '.$period.'. Check your dashboard for the current schedule.';
        $notice = Notification::create(['user_account_id' => $helper->user_account_id, 'title' => 'Duty '.$action, 'message' => $message, 'notification_type' => 'reminder', 'link' => '/helper/dashboard']);
        if ($helper->user) {
            app(StaffMailService::class)->enqueue($helper->user, 'duty-notice-'.$notice->id, 'COMPASS - Duty '.$action, $message);
        }
    }

    public function remind(): void
    {
        $today = now('Asia/Manila')->toDateString();
        foreach (HelperSchedule::with('helper.user')->whereDate('date', $today)->where('is_active', true)->get() as $shift) {
            $key = 'duty-reminder-'.$shift->id.'-'.$today;
            if (StaffMail::where('event_key', $key)->exists()) {
                continue;
            }
            DB::transaction(function () use ($shift, $key) {
                if (! $shift->helper?->user?->is_active) {
                    return;
                }
                $message = 'You have duty today: '.$shift->shift_label.' Philippine Time. Complete readiness before accepting sessions.';
                Notification::create(['user_account_id' => $shift->helper->user_account_id, 'title' => 'Duty today', 'message' => $message, 'notification_type' => 'reminder', 'link' => '/helper/dashboard']);
                app(StaffMailService::class)->enqueue($shift->helper->user, $key, 'COMPASS - Duty today', $message);
            });
        }
    }
}
