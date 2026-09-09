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
            <h2 class="font-semibold text-gray-800 mb-3">Recent Transcript</h2>
            <div class="rounded-xl border border-gray-100 divide-y mb-6">
                @forelse($alert->session?->messages ?? [] as $message)
                    <p class="p-3 text-sm"><span class="font-semibold">{{ ucfirst($message->sender) }}:</span> {{ $message->message_text }}</p>
                @empty
                    <p class="p-3 text-sm text-gray-400">No chat messages recorded.</p>
                @endforelse
            </div>
            @if($alert->status !== 'resolved')
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
