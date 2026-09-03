@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Pre-Session Brief</h1>
                <p class="text-gray-600">Seeker: {{ $session->seeker?->generated_alias ?? 'Anonymous' }}</p>
            </div>
            <div class="text-sm text-gray-600">
                Review time remaining: <strong>{{ floor($timeRemaining / 60) }}m {{ $timeRemaining % 60 }}s</strong>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="border rounded-lg p-4">
                <h2 class="font-semibold text-gray-900">Screening Summary</h2>
                <p class="mt-2 text-sm text-gray-700">Risk: <strong>{{ ucfirst($screeningSummary['risk_level']) }}</strong></p>
                <p class="text-sm text-gray-700">Concern: <strong>{{ $screeningSummary['concern_category'] }}</strong></p>
                <p class="mt-3 text-sm text-gray-700">{{ $screeningSummary['summary'] }}</p>
            </div>

            <div class="border rounded-lg p-4">
                <h2 class="font-semibold text-gray-900">Match Details</h2>
                <dl class="mt-2 text-sm text-gray-700 space-y-1">
                    <div>Competency: {{ round($matchingDetails['competency_score'] ?? 0) }}%</div>
                    <div>Workload: {{ round($matchingDetails['workload_score'] ?? 0) }}%</div>
                    <div>Specialty: {{ round($matchingDetails['specialty_score'] ?? 0) }}%</div>
                    <div>Language: {{ round($matchingDetails['language_score'] ?? 0) }}%</div>
                    <div>Overall: {{ round($matchingDetails['total_score'] ?? 0) }}%</div>
                </dl>
            </div>
        </div>

        <form method="POST" action="{{ route('helper.session.start', $session->id) }}" class="mt-6">
            @csrf
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Start Session
            </button>
        </form>
    </div>
</div>
@endsection
