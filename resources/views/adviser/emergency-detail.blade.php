@extends('layouts.app')

@section('title', 'Emergency Detail - COMPASS')

@section('content')
<div class="adviser-page-content">
        <a href="{{ route('adviser.emergencies') }}" class="text-sm text-gray-500 hover:text-gray-700">Back to emergencies</a>
        <div class="mt-4 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h1 class="text-2xl font-bold text-gray-800">Emergency Review</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $alert->session?->reference_number ?? 'Session' }} · {{ ucfirst($alert->status) }}</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 my-6 text-sm">
                <div><span class="text-gray-400">Seeker</span><p class="font-semibold">{{ $alert->session?->seeker?->generated_alias ?? 'Anonymous' }}</p></div>
                <div><span class="text-gray-400">Helper</span><p class="font-semibold">{{ $alert->session?->helper?->full_name ?? 'Unassigned' }}</p></div>
                <div><span class="text-gray-400">Risk</span><p class="font-semibold text-red-600">{{ ucfirst($alert->risk_level) }}</p></div>
            </div>
            <div class="p-4 rounded-xl bg-red-50 text-red-800 text-sm mb-6">{{ $alert->trigger_reason }}</div>
            <h2 class="font-semibold text-gray-800 mb-3">Supporting documentation</h2>
            <p class="text-sm text-gray-600 whitespace-pre-wrap mb-4">{{ $alert->session?->report?->session_summary ?? 'No session summary submitted yet.' }}</p>
            @if($alert->session)
                <a class="text-green-700 underline" href="{{ route('adviser.session.show', $alert->session_id) }}">Review session documentation</a>
            @endif
            <section class="my-5"><h2 class="font-semibold">Action history</h2>@forelse(\Illuminate\Support\Facades\DB::table('emergency_review_actions')->where('emergency_alert_id',$alert->id)->orderBy('id')->get() as $action)<div class="border-t py-3 text-sm"><strong>{{ ucfirst($action->action) }}</strong> ? {{ $action->created_at }} ? Account #{{ $action->actor_id }}<p class="whitespace-pre-wrap mt-1">{{ $action->notes }}</p></div>@empty<p class="text-sm text-gray-500">No actions recorded yet.</p>@endforelse</section>
            @if(!in_array($alert->status,['resolved','closed']))
                <form method="POST" action="{{ route('adviser.emergencies.action',$alert->id) }}" class="my-4 space-y-3">@csrf<label class="block text-sm">Action<select name="action" class="block w-full rounded-lg border-gray-300">@if(!$alert->acknowledged_at)<option value="acknowledged">Acknowledge escalation</option>@endif<option value="instruction">Document instructions</option><option value="coordination">Document coordination</option></select></label><label class="block text-sm">Notes<textarea name="notes" required maxlength="2000" class="block w-full rounded-lg border-gray-300"></textarea></label><button class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm">Record action</button></form>

                <form class="form-maximized" method="POST" action="{{ route('adviser.emergencies.resolve', $alert->id) }}">
                    @csrf
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Document emergency actions</label>
                    <textarea name="resolution_notes" required rows="4" maxlength="1000" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Actions taken, coordination completed, and follow-up plan"></textarea>
                    <button class="mt-3 px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold">Mark Resolved</button>
                </form>
            @endif
        </div>
</div>
@endsection
