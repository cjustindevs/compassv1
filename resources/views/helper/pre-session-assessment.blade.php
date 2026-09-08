@extends('layouts.helper')

@section('title', 'Pre-Session Brief')

@section('heading', 'Pre-Session Brief')
@section('subheading', 'Review seeker info before starting the session.')

@section('content')

    <a href="{{ route('helper.cases.show', ['id' => $session->id]) }}" class="btn btn-secondary btn-sm mb-4">
        <i class="fas fa-arrow-left"></i> Back to case
    </a>

    <div class="card" style="padding:32px 28px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
            <div>
                <h2 style="font-size:20px;font-weight:700;color:var(--gray-800);margin-bottom:4px;">Pre-Session Brief</h2>
                <p class="text-sm text-gray-500">Seeker: <strong>{{ $session->seeker?->generated_alias ?? 'Anonymous' }}</strong></p>
            </div>
            <div class="pill" style="background:var(--green-50);color:var(--green-700);">
                <i class="fas fa-clock mr-1"></i> {{ floor($timeRemaining / 60) }}m {{ $timeRemaining % 60 }}s remaining
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div style="border:1px solid var(--gray-200);border-radius:12px;padding:18px;">
                <h3 style="font-size:14px;font-weight:700;color:var(--gray-800);margin-bottom:10px;"><i class="fas fa-clipboard-list" style="color:var(--green-500);margin-right:6px;"></i> Screening Summary</h3>
                <p class="text-sm text-gray-700" style="margin-bottom:8px;">Concern: <strong>{{ $screeningSummary['concern_category'] }}</strong></p>
                <p class="text-sm text-gray-500">{{ $screeningSummary['summary'] }}</p>
            </div>

            <div style="border:1px solid var(--gray-200);border-radius:12px;padding:18px;">
                <h3 style="font-size:14px;font-weight:700;color:var(--gray-800);margin-bottom:10px;"><i class="fas fa-bullseye" style="color:var(--green-500);margin-right:6px;"></i> Match Details</h3>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <div style="display:flex;justify-content:space-between;"><span class="text-sm text-gray-500">Competency</span><span class="text-sm font-semibold text-gray-800">{{ round($matchingDetails['competency_score'] ?? 0) }}%</span></div>
                    <div style="display:flex;justify-content:space-between;"><span class="text-sm text-gray-500">Workload</span><span class="text-sm font-semibold text-gray-800">{{ round($matchingDetails['workload_score'] ?? 0) }}%</span></div>
                    <div style="display:flex;justify-content:space-between;"><span class="text-sm text-gray-500">Specialty</span><span class="text-sm font-semibold text-gray-800">{{ round($matchingDetails['specialty_score'] ?? 0) }}%</span></div>
                    <div style="display:flex;justify-content:space-between;"><span class="text-sm text-gray-500">Language</span><span class="text-sm font-semibold text-gray-800">{{ round($matchingDetails['language_score'] ?? 0) }}%</span></div>
                    <hr class="divider" style="margin:4px 0;">
                    <div style="display:flex;justify-content:space-between;"><span class="text-sm font-semibold text-gray-800">Overall</span><span class="text-sm font-bold" style="color:var(--green-500);">{{ round($matchingDetails['total_score'] ?? 0) }}%</span></div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('helper.session.start', $session->id) }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-play"></i> Start Session
            </button>
        </form>
    </div>

@endsection
