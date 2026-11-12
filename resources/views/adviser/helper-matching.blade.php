@extends('layouts.app')

@section('title', 'Helper Matching – COMPASS')

@push('styles')
<style>
    .hm-form { display: grid; grid-template-columns: 1fr 2fr auto; gap: 12px; align-items: center; }
    .hm-form input { min-height: 44px; border: 1px solid var(--compass-border, #E5E7EB); border-radius: 12px; padding: 0 12px; font-size: 15px; background: #fff; width: 100%; }
    @media (max-width: 768px) { .hm-form { grid-template-columns: 1fr auto; } .hm-form input[name="remarks"] { grid-column: 1 / -1; grid-row: 2; } }
    .hm-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .hm-empty { font-size: 15px; color: var(--compass-muted, #6B7280); }
</style>
@endpush

@section('content')
    <x-ui.page-header :title="'Matching Profile: '.($helper->full_name ?: ($helper->user?->name ?? 'Name not recorded'))"
        :description="'Current display score: '.number_format($matchingScore, 2).'%'"
        :back-href="route('adviser.helpers')" back-label="Back to Manage Helpers" />

    <x-ui.kpi-grid>
        <x-ui.kpi label="Display score" :value="number_format($matchingScore, 2).'%'" tone="green" />
        @foreach($matchingDetails as $label => $score)
            <x-ui.kpi :label="ucwords(str_replace('_', ' ', $label))" :value="is_numeric($score) ? number_format($score, 2) : $score" tone="neutral" />
        @endforeach
    </x-ui.kpi-grid>

    <x-ui.card title="Update Competency">
        <form class="hm-form" method="POST" action="{{ route('adviser.helper.competency.update', $helper->id) }}">
            @csrf
            <input name="competency_score" type="number" step="0.01" min="1" max="5" value="{{ $helper->competency_score }}" aria-label="Competency score">
            <input name="remarks" type="text" placeholder="Remarks" aria-label="Remarks">
            <button class="btn-primary" type="submit">Save</button>
        </form>
    </x-ui.card>

    <x-ui.card title="Recent Matches">
        <x-ui.table :stack="true">
            <thead><tr><th>Session</th><th>Seeker</th><th>Risk</th><th>Method</th><th>Score</th><th>Date</th></tr></thead>
            <tbody>
                @forelse($recentMatches as $session)
                    <tr>
                        <td data-label="Session">{{ $session->reference_number }}</td>
                        <td data-label="Seeker">{{ $session->seeker?->generated_alias }}</td>
                        <td data-label="Risk">{{ ucfirst($session->risk_level) }}</td>
                        <td data-label="Method">{{ ucfirst($session->match_method ?? 'automatic') }}</td>
                        <td data-label="Score">{{ $session->matching_details['total_score'] ?? 'N/A' }}</td>
                        <td data-label="Date">{{ ($session->created_date ?? $session->created_at)?->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No matches yet.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="Specialties">
        <div class="hm-chips">
            @forelse($specialties as $specialty)
                <x-ui.badge tone="green">{{ ucfirst($specialty->category) }} · L{{ $specialty->proficiency_level }}</x-ui.badge>
            @empty
                <span class="hm-empty">No specialties recorded.</span>
            @endforelse
        </div>
    </x-ui.card>
@endsection
