<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Schedules - COMPASS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="compass-compact bg-gray-50">
    @include('layouts.partials.moderator-sidebar')

    <main class="main-content p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Schedule Management</h1>
                <p class="text-sm text-gray-500">Plan helper duty dates and monitor helper readiness on each duty day.</p>
            </div>
            <form method="GET" action="{{ route('moderator.schedules') }}" class="flex flex-wrap items-center gap-2">
                <input type="date" name="date" value="{{ $date }}" class="rounded-lg border-gray-300 text-sm">
                <label for="availability" class="sr-only">Current availability</label>
                <select id="availability" name="availability" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All availability</option>
                    @foreach(['available'=>'Available','busy'=>'Handling a session','offline'=>'Offline'] as $value=>$label)
                        <option value="{{ $value }}" @selected(request('availability') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
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
                        <option value="">{{ $dutyHelpers->isEmpty() ? 'No online, ready Helpers' : 'Select an online, ready Helper' }}</option>
                        @foreach($dutyHelpers as $helper)
                            <option value="{{ $helper->id }}" @selected((string) old('helper_id') === (string) $helper->id)>{{ $helper->full_name }} · Online | Ready</option>
                        @endforeach
                    </select>
                    <input type="date" name="event_date" value="{{ old('event_date', $date) }}" required class="w-full rounded-lg border-gray-300 text-sm">
                    <textarea name="description" rows="3" maxlength="500" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Duty notes or assignment details">{{ old('description') }}</textarea>
                    <button @disabled($dutyHelpers->isEmpty()) class="disabled:opacity-50 disabled:cursor-not-allowed w-full px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold">Add Duty Day</button>
                </form>


            </section>

            <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 mb-4">Duty Schedules for {{ \Illuminate\Support\Carbon::parse($date)->format('M d, Y') }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" style="min-width:580px">
                        <thead class="text-left text-gray-500 border-b">
                            <tr><th class="py-2">Duty</th><th class="py-2">Notes</th><th class="py-2">Created</th><th class="py-2">Action</th></tr>
                        </thead>
                        <tbody>
                            @forelse($scheduleEvents as $event)
                                <tr class="border-b last:border-0">
                                    <td class="py-3 font-medium text-gray-800">{{ $event->title }}<br><span class="text-xs text-gray-500">{{ $event->start_time && $event->end_time ? substr($event->start_time, 0, 5) . ' - ' . substr($event->end_time, 0, 5) : 'All day' }}</span></td>
                                    <td class="py-3 text-gray-600">{{ $event->description ?: '—' }}</td>
                                    <td class="py-3 text-gray-500">{{ $event->created_at?->diffForHumans() }}</td><td class="py-3">@if($event->helperSchedule)<form method="POST" action="{{ route('moderator.schedules.destroy') }}">@csrf<input type="hidden" name="helper_id" value="{{ $event->helperSchedule->helper_id }}"><input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="shift_id" value="{{ $event->helper_schedule_id }}"><button class="text-red-600 hover:underline">Remove duty</button></form>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-gray-400">No duty schedules for this date.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <section class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Scheduled support sessions</h2>
            <p class="text-sm text-gray-500 mb-3">Appointment times are Philippine Time (Asia/Manila). Duty roster entries are shown separately above.</p>
            <div class="overflow-x-auto"><table class="w-full text-sm" style="min-width:580px"><thead class="text-left text-gray-500 border-b"><tr><th class="py-2">Helper</th><th>Seeker alias</th><th>Date</th><th>Time</th><th>Status</th><th>Details</th></tr></thead><tbody>
            @forelse($scheduledSessions as $supportSession)<tr class="border-b"><td class="py-3">{{ $supportSession->helper?->full_name ?? 'Unassigned' }}</td><td>{{ $supportSession->seeker?->generated_alias ?? 'Unavailable' }}</td><td>{{ $supportSession->scheduled_start->copy()->timezone('Asia/Manila')->format('M j, Y') }}</td><td>{{ $supportSession->scheduled_start->copy()->timezone('Asia/Manila')->format('g:i A') }}</td><td>{{ ucwords(str_replace('_',' ',$supportSession->session_status)) }}</td><td><a class="text-green-700" href="{{ route('moderator.sessions.show',$supportSession->id) }}">View session status</a></td></tr>
            @empty<tr><td colspan="6" class="py-6 text-center text-gray-500">No support sessions scheduled for this date.</td></tr>@endforelse
            </tbody></table></div>{{ $scheduledSessions->links() }}
        </section>
    </main>
</body>
</html>
