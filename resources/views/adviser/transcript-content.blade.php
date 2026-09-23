@extends('layouts.app')
@section('title', 'Authorized transcript - COMPASS')
@section('content')
<div class="adviser-page-content space-y-4">
    <a href="{{ route('adviser.transcripts') }}" class="text-green-700 text-sm">Back to transcript access</a>
    <h1 class="text-2xl font-bold">Session #{{ $session->id }} transcript</h1>
    <p class="text-sm text-gray-500">Access is audited and expires after ten minutes. Withdrawal of consent or supervision blocks further access.</p>
    <section class="bg-white border border-gray-200 rounded-2xl divide-y divide-gray-100">
        @forelse($transcript['messages'] as $message)
            <div class="p-4"><p class="text-xs text-gray-500">{{ $message['sender_type'] }} ? {{ $message['timestamp'] }}</p><p class="text-sm whitespace-pre-wrap mt-1">{{ $message['message'] }}</p></div>
        @empty
            <p class="p-4 text-gray-500">No messages recorded.</p>
        @endforelse
    </section>
    <form method="POST" action="{{ route('adviser.transcript.verify', $session->id) }}">@csrf<button class="bg-green-600 rounded-xl text-white px-4 py-2 text-sm">Confirm transcript reviewed</button></form>
</div>
@endsection
