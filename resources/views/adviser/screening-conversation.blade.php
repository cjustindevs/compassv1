@extends('layouts.app')
@section('title', 'Session conversation - COMPASS')
@section('content')
<div class="adviser-page-content space-y-5">
    <header class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <a href="{{ route('adviser.screenings') }}" class="text-sm text-green-700"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>Back to screening reviews</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-2">Session conversation</h1>
            <p class="text-sm text-gray-500 mt-1">Read-only view for this screening review. Access is audited and uses aliases only.</p>
        </div>
        <span class="rounded-full bg-green-50 text-green-800 px-3 py-1 text-sm">{{ $session->status_label }}</span>
    </header>

    <section class="bg-white rounded-2xl border border-gray-200 p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
        <div><p class="text-gray-500">Request</p><p class="font-semibold">{{ $session->reference_number }}</p></div>
        <div><p class="text-gray-500">Seeker</p><p class="font-semibold">{{ $session->seeker?->generated_alias ?? 'Seeker' }}</p></div>
        <div><p class="text-gray-500">Helper</p><p class="font-semibold">{{ $session->helper?->public_alias ?? 'Unassigned' }}</p></div>
    </section>

    <section class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
        @forelse($messages as $message)
            @php($isSeeker = $message->sender === 'seeker')
            <div class="p-4 flex gap-3 {{ $isSeeker ? '' : 'bg-green-50/30' }}">
                <span class="shrink-0 mt-1 h-8 w-8 rounded-full {{ $isSeeker ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700' }} flex items-center justify-center text-xs font-semibold">
                    {{ $isSeeker ? mb_substr($session->seeker?->generated_alias ?? 'S', 0, 1) : mb_substr($session->helper?->public_alias ?? 'H', 0, 1) }}
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="text-xs font-semibold {{ $isSeeker ? 'text-gray-700' : 'text-green-700' }}">
                            {{ $isSeeker ? ($session->seeker?->generated_alias ?? 'Seeker') : ($session->helper?->public_alias ?? 'Helper') }}
                        </span>
                        <span class="text-xs text-gray-400">{{ ($message->sent_datetime ?? $message->created_at)?->timezone('Asia/Manila')->format('M d, Y \a\t g:i A') }}</span>
                    </div>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap break-words mt-1">{{ $message->message_text }}</p>
                </div>
            </div>
        @empty
            <p class="p-6 text-sm text-gray-500">No chat messages recorded for this session.</p>
        @endforelse
    </section>

    <p class="text-sm text-gray-500">Conversation content is shown as recorded in the session; it does not use voice transcription.</p>
</div>
@endsection