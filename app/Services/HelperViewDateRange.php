<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Shared display filters for Helper reports, feedback and competency; all dates are PHT. */
class HelperViewDateRange
{
    public function apply(Request $request): array
    {
        $request->validate(['date_range' => 'nullable|string|max:24']);
        if ($request->filled('date_range')) {
            if (! preg_match('/^(\d{4}-\d{2}-\d{2}) to (\d{4}-\d{2}-\d{2})$/', (string) $request->input('date_range'), $dates)) {
                throw ValidationException::withMessages(['date_range' => 'Choose a valid start and end date.']);
            }
            $request->merge(['from' => $dates[1], 'to' => $dates[2]]);
        }
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $data['from'] ?? now('Asia/Manila')->subDays(30)->toDateString();
        $to = $data['to'] ?? now('Asia/Manila')->toDateString();
        $start = Carbon::parse($from, 'Asia/Manila')->startOfDay();
        $end = Carbon::parse($to, 'Asia/Manila')->endOfDay();
        if ($end->lt($start) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['date_range' => 'Choose an ordered range of no more than 366 days.']);
        }
        $request->merge(['from' => $from, 'to' => $to]);

        return ['from' => $from, 'to' => $to, 'start' => $start->utc(), 'end' => $end->utc()];
    }
}
