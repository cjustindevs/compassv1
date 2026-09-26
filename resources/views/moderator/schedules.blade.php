<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Schedules - COMPASS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="compass-compact bg-gray-50">
    @include('layouts.partials.moderator-sidebar')

    <main class="main-content p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Schedule Management</h1>
                <p class="text-sm text-gray-500">Plan helper duty dates and monitor readiness-based attendance.</p>
            </div>
            <form method="GET" action="{{ route('moderator.schedules') }}" class="flex gap-2">
                <input type="date" name="date" value="{{ $date }}" class="rounded-lg border-gray-300 text-sm">
                <button class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold">View</button>
            </form>
        </div>

        @if(session('success')) <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700">{{ session('success') }}</div> @endif
        @if(session('error')) <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700">{{ session('error') }}</div> @endif
        @if($errors->any()) <div role="alert" class="mb-4 p-3 rounded-lg bg-red-50 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div> @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 mb-4">Schedule Helper Duty</h2>
                <form method="POST" action="{{ route('moderator.schedules.store') }}" class="space-y-3">
                    @csrf
                    <select name="helper_id" required class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Select helper</option>
                        @foreach($helpers as $helper)
                            <option value="{{ $helper->id }}" @selected((string) old('helper_id') === (string) $helper->id)>{{ $helper->full_name }} · {{ ucfirst($helper->status) }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="event_date" value="{{ old('event_date', $date) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                    <select name="shift_slot" required class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Select shift</option>
                        @foreach(\App\Models\HelperSchedule::SHIFT_SLOTS as $slotKey => $slot)
                            <option value="{{ $slotKey }}" @selected(old('shift_slot') === $slotKey)>
                                {{ $slot['label'] }}@if($slot['start']) ({{ \Illuminate\Support\Carbon::parse($slot['start'])->format('g:i A') }} - {{ \Illuminate\Support\Carbon::parse($slot['end'])->format('g:i A') }})@endif
                            </option>
                        @endforeach
                    </select>
                    <textarea name="description" rows="3" maxlength="500" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Duty notes or assignment details">{{ old('description') }}</textarea>
                    <button class="w-full px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold">Add Duty Shift</button>
                </form>

                @if($shiftsByHelper->isNotEmpty())
                    <h3 class="font-semibold text-gray-800 mt-6 mb-2">Shifts on this date</h3>
                    @foreach($shiftsByHelper as $helperId => $shifts)
                        <div class="border-t border-gray-100 py-2">
                            <div class="text-sm font-medium text-gray-700">{{ $shifts->first()->helper?->full_name ?? 'Helper #'.$helperId }}</div>
                            @foreach($shifts as $shift)
                                <div class="flex items-center gap-2 text-xs text-gray-600 mt-1">
                                    <span>{{ $shift->shift_label }}</span>
                                    @if($shift->isWithinShift())
                                        <span class="text-green-600">On duty now</span>
                                    @endif
                                    <form method="POST" action="{{ route('moderator.schedules.destroy') }}" class="ml-auto">
                                        @csrf
                                        <input type="hidden" name="helper_id" value="{{ $shift->helper_id }}">
                                        <input type="hidden" name="date" value="{{ $date }}">
                                        <input type="hidden" name="shift_id" value="{{ $shift->id }}">
                                        <button type="submit" class="text-red-600 hover:underline">Remove</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @endif
            </section>

            <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 mb-4">Duty Schedules for {{ \Illuminate\Support\Carbon::parse($date)->format('M d, Y') }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-gray-500 border-b">
                            <tr><th class="py-2">Duty</th><th class="py-2">Notes</th><th class="py-2">Created</th></tr>
                        </thead>
                        <tbody>
                            @forelse($scheduleEvents as $event)
                                <tr class="border-b last:border-0">
                                    <td class="py-3 font-medium text-gray-800">{{ $event->title }}<br><span class="text-xs text-gray-500">{{ $event->start_time && $event->end_time ? substr($event->start_time, 0, 5) . ' - ' . substr($event->end_time, 0, 5) : 'All day' }}</span></td>
                                    <td class="py-3 text-gray-600">{{ $event->description ?: '—' }}</td>
                                    <td class="py-3 text-gray-500">{{ $event->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-gray-400">No duty schedules for this date.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Attendance and Duty Hours</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500 border-b">
                        <tr><th class="py-2">Helper</th><th class="py-2">Availability</th><th class="py-2">Readiness</th><th class="py-2">Shift</th><th class="py-2">Duty Hours</th></tr>
                    </thead>
                    <tbody>
                        @forelse($attendance as $row)
                            <tr class="border-b last:border-0">
                                <td class="py-3 font-medium text-gray-800">{{ $row['helper'] }}</td>
                                <td class="py-3">{{ ucfirst($row['status']) }}</td>
                                <td class="py-3">{{ $row['result'] }}</td>
                                <td class="py-3">{{ $row['shift_start'] ?? '—' }} - {{ $row['shift_end'] ?? '—' }}</td>
                                <td class="py-3">{{ $row['duty_hours'] !== null ? $row['duty_hours'] . 'h' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-gray-400">No readiness attendance records for this date.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
