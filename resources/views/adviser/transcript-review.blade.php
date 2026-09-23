@extends('layouts.app')
@section('title', 'Transcript access - COMPASS')
@section('content')
<div class="adviser-page-content space-y-5">
    <header><h1 class="text-2xl font-bold text-gray-800">Transcript access</h1><p class="text-sm text-gray-500 mt-1">Completed sessions only. Each access requires a purpose, current supervision, and session-specific transcription consent.</p></header>
    @foreach($errors->all() as $error)<p role="alert" class="text-red-700">{{ $error }}</p>@endforeach
    @forelse($unverifiedTranscripts as $item)
        <section class="bg-white rounded-2xl border border-gray-200 p-5">
            <h2 class="font-semibold">Session #{{ $item['session_id'] }} ? {{ $item['helper_name'] }}</h2>
            <p class="text-sm text-gray-500 mt-1">{{ $item['session_date']?->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
            @if($item['eligible'])
                <form method="POST" action="{{ route('adviser.transcript.access', $item['session_id']) }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="block text-sm">Purpose
                        <select name="purpose" required class="block w-full rounded-lg border-gray-300 mt-1">
                            @foreach(\App\Services\AdviserTranscriptAccess::PURPOSES as $purpose)<option value="{{ $purpose }}">{{ ucfirst(str_replace('_', ' ', $purpose)) }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-sm">Reason for access
                        <textarea name="reason" required minlength="10" maxlength="500" class="block w-full rounded-lg border-gray-300 mt-1" placeholder="Explain why the submitted documentation is insufficient. Avoid identity details."></textarea>
                    </label>
                    <button class="bg-green-600 text-white rounded-xl px-4 py-2 text-sm font-semibold">Open transcript</button>
                </form>
            @else
                <p class="text-sm text-gray-500 mt-3">Unavailable: valid session-specific transcription consent is required. An Adviser cannot provide consent on the participant?s behalf.</p>
            @endif
        </section>
    @empty
        <section class="bg-white rounded-2xl border border-gray-200 p-5 text-sm text-gray-500">No completed sessions awaiting transcript verification.</section>
    @endforelse
</div>
@endsection
