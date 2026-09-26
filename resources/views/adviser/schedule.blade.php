<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management - COMPASS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="compass-compact bg-gray-50">
    @include('layouts.partials.adviser-sidebar')

    <main class="main-content p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Schedule Management</h1>
                <p class="text-sm text-gray-500">Plan helper duty dates and monitor readiness-based attendance.</p>
            </div>
            <form method="GET" action="{{ route('adviser.schedule') }}" class="flex gap-2">
                <input type="date" name="date" value="{{ $date->toDateString() }}" class="rounded-lg border-gray-300 text-sm">
                <button class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold">View</button>
            </form>
        </div>

        @if(session('success')) <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700">{{ session('success') }}</div> @endif
        @if(session('error')) <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700">{{ session('error') }}</div> @endif
        @if($errors->any())
            <div role="alert" class="mb-4 p-3 rounded-lg bg-red-50 text-red-700">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <p class="text-sm text-gray-500 mb-4">Add a duty shift for a helper. A helper can hold several shifts on the same date as long as they do not overlap, and an overnight shift is allowed when the end time is earlier than the start. Helpers also need a current readiness assessment and available status to receive sessions.</p>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 mb-4">Schedule Helper Duty</h2>
                <form method="POST" action="{{ route('adviser.schedule.update') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="shift_id" value="{{ old('shift_id') }}">
                    <select name="helper_id" required class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Select helper</option>
                        @foreach($helpers as $helper)
                            <option value="{{ $helper->id }}" @selected((string) old('helper_id') === (string) $helper->id)>{{ $helper->full_name }} · {{ ucfirst($helper->status) }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date" value="{{ old('date', $date->toDateString()) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-xs text-gray-600">Shift starts
                            <input type="time" name="shift_start" value="{{ old('shift_start', '18:00') }}" required class="w-full rounded-lg border-gray-300 text-sm">
                        </label>
                        <label class="text-xs text-gray-600">Shift ends
                            <input type="time" name="shift_end" value="{{ old('shift_end', '23:00') }}" required class="w-full rounded-lg border-gray-300 text-sm">
                        </label>
                    </div>
                    <p class="text-xs text-gray-500">Leave both times empty for whole-day duty. An end earlier than the start continues into the next day.</p>
                    <button class="w-full px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold">Add Duty Shift</button>
                </form>
            </section>

            <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 mb-4">Duty Schedules for {{ $date->format('M d, Y') }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-gray-500 border-b">
                            <tr><th class="py-2">Helper</th><th class="py-2">Duty shifts</th><th class="py-2">Status &amp; Readiness</th></tr>
                        </thead>
                        <tbody>
                            @forelse($scheduleData as $data)
                                <tr class="border-b last:border-0">
                                    <td class="py-3 font-medium text-gray-800">{{ $data['name'] }}</td>
                                    <td class="py-3">
                                        @forelse($data['shifts'] as $shift)
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="font-medium text-gray-700">{{ $shift['label'] }}</span>
                                                @if($shift['on_shift'])
                                                    <span class="text-xs text-green-600">On duty now</span>
                                                @endif
                                                <button type="button"
                                                        class="ml-auto text-xs text-red-600 hover:underline"
                                                        data-shift-edit
                                                        data-helper-id="{{ $data['helper_id'] }}"
                                                        data-date="{{ $date->toDateString() }}"
                                                        data-shift-id="{{ $shift['id'] }}"
                                                        data-shift-start="{{ $shift['start'] }}"
                                                        data-shift-end="{{ $shift['end'] }}">Edit</button>
                                                <form method="POST" action="{{ route('adviser.schedule.destroy') }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="helper_id" value="{{ $data['helper_id'] }}">
                                                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                                    <input type="hidden" name="shift_id" value="{{ $shift['id'] }}">
                                                    <button type="submit" class="text-xs text-red-600 hover:underline">Remove</button>
                                                </form>
                                            </div>
                                        @empty
                                            <span class="text-gray-400">Not scheduled</span>
                                        @endforelse
                                        @if($data['has_schedule'] && ! $data['is_on_shift'])
                                            <span class="text-xs text-yellow-600">Not on duty at this time</span>
                                        @endif
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $data['availability'] === 'available' ? 'bg-green-50 text-green-700' : ($data['availability'] === 'break' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">{{ ucfirst($data['availability']) }}</span>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $data['is_ready'] ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }} ml-1">{{ $data['is_ready'] ? 'Ready' : 'Not Ready' }}</span>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ $data['current_sessions'] }}/{{ $data['max_sessions'] }} sessions · {{ $data['status_label'] }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-gray-400">No supervised helpers found for scheduling.</td></tr>
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

    <script>
        // Clicking Edit loads the shift back into the form with its id, so the
        // next submit updates that row instead of adding a second one.
        document.querySelectorAll('[data-shift-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                const form = document.querySelector('form[action="{{ route('adviser.schedule.update') }}"]');
                form.querySelector('[name="shift_id"]').value = button.dataset.shiftId;
                form.querySelector('[name="helper_id"]').value = button.dataset.helperId;
                form.querySelector('[name="date"]').value = button.dataset.date;
                form.querySelector('[name="shift_start"]').value = (button.dataset.shiftStart || '').slice(0, 5);
                form.querySelector('[name="shift_end"]').value = (button.dataset.shiftEnd || '').slice(0, 5);
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    </script>
</body>
</html>