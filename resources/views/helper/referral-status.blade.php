@extends('layouts.helper')

@section('title', 'Referral Status')
@section('heading', 'Referral Status')
@section('subheading', 'Referral #' . $referral->id . ' · ' . ($referral->session?->seeker?->generated_alias ?? 'Seeker'))

@section('content')
    <a href="{{ route('helper.cases.show', ['id' => $referral->session_id]) }}" class="btn btn-secondary btn-sm mb-4" style="padding:6px 14px;"><i class="fas fa-arrow-left"></i> Back to case</a>

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
@endsection
