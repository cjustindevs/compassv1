@extends('layouts.app')
@section('title', 'Session documentation - COMPASS')
@section('content')
<div class="adviser-page-content space-y-5">
    <header class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <a href="{{ route('adviser.dashboard') }}" class="text-sm text-green-700"><i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>Back to dashboard</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-2">Session documentation</h1>
            <p class="text-sm text-gray-500 mt-1">Review the Helper?s submitted summary, reflection, and correction history.</p>
        </div>
        <span class="rounded-full bg-green-50 text-green-800 px-3 py-1 text-sm">{{ ucfirst($session->session_status) }}</span>
    </header>
    <section class="bg-white rounded-2xl border border-gray-200 p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
        <div><p class="text-gray-500">Session</p><p class="font-semibold">{{ $session->reference_number }}</p></div>
        <div><p class="text-gray-500">Helper</p><p class="font-semibold">{{ $session->helper?->public_alias ?? 'Unassigned' }}</p></div>
        <div><p class="text-gray-500">Documentation</p><p class="font-semibold">{{ ucfirst($session->documentation_status ?? 'Pending') }}</p></div>
    </section>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        @foreach(['Session summary' => 'session_summary', 'Helper reflection' => 'personal_reflection', 'Session result' => 'session_result', 'Follow-up plan' => 'follow_up_plan'] as $label => $field)
            <section class="bg-white rounded-2xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-800 mb-3">{{ $label }}</h2>
                <p class="text-sm text-gray-600 whitespace-pre-wrap break-words">{{ $session->report?->$field ?: 'Not submitted yet.' }}</p>
            </section>
        @endforeach
    </div>
    @if($session->report && in_array($session->session_status, ['completed', 'evaluated']))
        <a class="inline-flex bg-green-600 text-white rounded-xl px-4 py-2 font-semibold text-sm" href="{{ route('adviser.evaluate', $session->report->id) }}">Review competency evaluation</a>
    @endif
    <section class="bg-white rounded-2xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-800 mb-3">Previous documentation versions</h2>
        @forelse($revisions as $revision)
            <details class="border-t border-gray-100 py-3">
                <summary class="cursor-pointer text-sm font-medium">{{ $revision->created_at }} ? {{ $revision->reason }}</summary>
                @php($snapshot = json_decode($revision->snapshot, true) ?? [])
                <p class="text-sm text-gray-600 whitespace-pre-wrap mt-3">{{ $snapshot['session_summary'] ?? 'No summary in this version.' }}</p>
                <p class="text-sm text-gray-600 whitespace-pre-wrap mt-3">{{ $snapshot['personal_reflection'] ?? 'No reflection in this version.' }}</p>
            </details>
        @empty
            <p class="text-sm text-gray-500">No corrections recorded.</p>
        @endforelse
    </section>
    <p class="text-sm text-gray-500">Conversation access is separate and requires a documented purpose and valid session consent. <a class="text-green-700 underline" href="{{ route('adviser.transcripts') }}">Review transcript access</a></p>
</div>
@endsection
