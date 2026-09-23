@extends('layouts.helper')

@section('title', 'Referral Status')
@section('heading', 'Referral Status')
@section('subheading', 'Referral #' . $referral->id . ' · ' . ($referral->session?->seeker?->generated_alias ?? 'Seeker'))

@section('content')
        <a href="{{ route('helper.cases.show', ['id' => $referral->session_id]) }}" class="btn btn-secondary btn-sm mb-4"><i class="fas fa-arrow-left"></i> Back to case</a>

    <div class="card">
        <div class="card-header">
            <h3>{{ ucfirst(str_replace('_', ' ', $referral->status)) }}</h3>
            <span class="risk-badge {{ $referral->priority_level }}">{{ ucfirst($referral->priority_level) }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div><div class="text-xs text-gray-400 mb-1">Submitted</div><div class="font-semibold text-gray-800">{{ $referral->referral_date?->format('M d, Y h:i A') }}</div></div>
            <div><div class="text-xs text-gray-400 mb-1">Adviser</div><div class="font-semibold text-gray-800">{{ $referral->adviser?->full_name ?? 'Pending' }}</div></div>
            <div><div class="text-xs text-gray-400 mb-1">Professional</div><div class="font-semibold text-gray-800">{{ $referral->professional?->full_name ?? 'Pending' }}</div></div>
        </div>
        <hr class="divider">
        <h4 class="font-semibold text-gray-800 mb-2">Reason</h4>
        <p class="text-sm text-gray-600">{{ $referral->referral_reason }}</p>
        @if($referral->decline_reason)
            <hr class="divider">
            <h4 class="font-semibold text-gray-800 mb-2">Decline Reason</h4>
            <p class="text-sm text-gray-600">{{ $referral->decline_reason }}</p>
        @endif
    </div>

    @if($referral->clarification_requested_at)
    <section class="card p-5 my-4" aria-label="Referral clarification">
        <h3 class="font-semibold">{{ $referral->clarification_received_at ? 'Clarification received' : 'Awaiting Helper clarification' }}</h3>
        <p class="text-sm mt-2">{{ $referral->clarification_question }}</p>
        <p class="text-sm mt-2">{{ $referral->clarification_response }}</p>
        @if(!$referral->clarification_received_at && $referral->status === 'pending_adviser')
        <form method="POST" action="{{ route('helper.referral.clarify',$referral->id) }}">@csrf
            <label for="clarification">Your response</label>
            <textarea id="clarification" name="response" required minlength="10" maxlength="2000" class="w-full rounded-lg border-gray-300">{{ old('response') }}</textarea>
            @error('response')<p class="text-red-700">{{ $message }}</p>@enderror
            <button class="btn btn-primary mt-3">Send clarification</button>
        </form>
        @endif
    </section>
    @endif
    <x-supervision-history :record="$referral" />
@endsection
