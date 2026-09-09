@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="bg-white rounded-xl shadow p-6">
        <a href="{{ route('adviser.helpers') }}" class="text-sm text-gray-500 hover:text-gray-700">Back to helpers</a>
        <h1 class="text-2xl font-bold mt-2">Matching Profile: {{ $helper->user?->name ?? $helper->full_name }}</h1>
        <p class="text-gray-600">Current display score: {{ number_format($matchingScore, 2) }}%</p>
    </div>

    <div class="grid md:grid-cols-3 gap-4">
        @foreach($matchingDetails as $label => $score)
            <div class="bg-white rounded-xl shadow p-4">
                <div class="text-sm text-gray-500">{{ ucwords(str_replace('_', ' ', $label)) }}</div>
                <div class="text-xl font-bold">{{ is_numeric($score) ? number_format($score, 2) : $score }}</div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-3">Update Competency</h2>
        <form class="form-maximized grid md:grid-cols-3 gap-3" method="POST" action="{{ route('adviser.helper.competency.update', $helper->id) }}">
            @csrf
            <input name="competency_score" type="number" step="0.01" min="1" max="5" value="{{ $helper->competency_score }}" class="border rounded px-3 py-2">
            <input name="remarks" type="text" placeholder="Remarks" class="border rounded px-3 py-2 md:col-span-1">
            <button class="bg-green-600 text-white rounded px-4 py-2">Save</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-3">Recent Matches</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-2">Session</th><th>Seeker</th><th>Risk</th><th>Method</th><th>Score</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse($recentMatches as $session)
                        <tr class="border-t"><td class="py-2">{{ $session->reference_number }}</td><td>{{ $session->seeker?->generated_alias }}</td><td>{{ ucfirst($session->risk_level) }}</td><td>{{ ucfirst($session->match_method ?? 'automatic') }}</td><td>{{ $session->matching_details['total_score'] ?? 'N/A' }}</td><td>{{ ($session->created_date ?? $session->created_at)?->format('M d, Y') }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-gray-500">No matches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="font-semibold mb-3">Specialties</h2>
        <div class="flex flex-wrap gap-2">
            @forelse($specialties as $specialty)
                <span class="px-3 py-1 bg-green-50 text-green-700 rounded-full text-sm">{{ ucfirst($specialty->category) }} · L{{ $specialty->proficiency_level }}</span>
            @empty
                <span class="text-gray-500">No specialties recorded.</span>
            @endforelse
        </div>
    </div>
</div>
@endsection
