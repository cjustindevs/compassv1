@extends('layouts.app')

@section('title', 'COMPASS – Manage Helpers')

@push('styles')
<style>
    .helpers-flash { border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; }
    .helper-person { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .helper-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--compass-green, #04A052); color: #fff; display: grid; place-items: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
    .helper-person strong, .helper-person small { display: block; }
    .ui-table td small { display: block; font-size: 13px; color: var(--compass-muted, #6B7280); }
    .elig-why { max-width: 28ch; }
    .helpers-footer { margin-top: 24px; text-align: center; font-size: 14px; color: var(--compass-muted, #6B7280); }
    .helpers-clear { font-size: 14px; color: var(--compass-muted, #6B7280); }
</style>
@endpush

@section('content')
    @php
        $statusLabels = ['all' => 'All', 'online' => 'Online', 'available' => 'Available', 'offline' => 'Offline', 'inactive' => 'Inactive'];
        $statusTone = ['online' => 'green', 'available' => 'blue', 'offline' => 'amber', 'inactive' => 'neutral'];
        $levelTone = ['expert' => 'green', 'advanced' => 'blue', 'intermediate' => 'amber', 'beginner' => 'red', 'trainee' => 'neutral'];
        $chips = $statusFilter !== 'all' ? \App\Support\FilterChips::from(request(), ['status' => 'Status']) : [];
        $eligibility = app(\App\Services\HelperEligibilityService::class);
    @endphp

    <x-ui.page-header title="Manage Helpers" description="Monitor performance, availability, and supervision">
        <x-slot:actions>
            <a href="{{ route('adviser.helpers.create') }}" class="btn-primary"><x-ui-icon name="user-plus" /> Add a Helper</a>
            <a href="{{ route('adviser.helpers.export', ['status' => $statusFilter]) }}" class="btn-secondary"><x-ui-icon name="file-spreadsheet" /> Export CSV</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('success'))
        <div class="helpers-flash">{{ session('success') }}</div>
    @endif

    <x-ui.kpi-grid>
        <x-ui.kpi label="Total Helpers" :value="$totalHelpers" tone="neutral" />
        <x-ui.kpi label="Available" :value="$availableHelpers" tone="green" />
        <x-ui.kpi label="Advanced / Expert competency" :value="$highCompetency" tone="blue" />
    </x-ui.kpi-grid>

    <x-ui.filter-bar :action="route('adviser.helpers')">
        <x-ui.field label="Status" for="helperStatus">
            <select id="helperStatus" name="status" data-control="select">
                @foreach($statusLabels as $key => $label)
                    <option value="{{ $key }}" @selected($statusFilter === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </x-ui.field>
    </x-ui.filter-bar>
    <x-ui.filter-chips :chips="$chips" :reset-href="route('adviser.helpers')" />

    <x-ui.card :title="'Helpers ('.$helperData->count().')'">
        @if($helperData->isNotEmpty())
            <x-ui.table :stack="true" class="ui-table--wide">
                <thead>
                    <tr><th>Helper</th><th>Status</th><th>Level</th><th>Score</th><th>Duty / capacity</th><th>Can take sessions</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @foreach($helperData as $row)
                        @php $helper = $row['helper']; $blockers = $eligibility->reasons($helper); @endphp
                        <tr>
                            <td data-label="Helper">
                                <div class="helper-person">
                                    <span class="helper-avatar" aria-hidden="true">{{ $row['initials'] }}</span>
                                    <span><strong>{{ $helper->getFullNameAttribute() }}</strong><small>{{ $helper->email }}</small></span>
                                </div>
                            </td>
                            <td data-label="Status"><x-ui.badge :tone="$statusTone[$row['status']] ?? 'neutral'">{{ ucfirst($row['status']) }}</x-ui.badge></td>
                            <td data-label="Level"><x-ui.badge :tone="$levelTone[strtolower($row['level'])] ?? 'neutral'">{{ $row['level'] }}</x-ui.badge></td>
                            <td data-label="Score"><strong>{{ $row['competency_score'] > 0 ? $row['competency_score'] : '—' }}</strong></td>
                            <td data-label="Duty / capacity"><x-helper-capacity :helper="$helper" /></td>
                            <td data-label="Can take sessions">
                                @if($blockers === [])
                                    <x-ui.badge tone="green">Eligible</x-ui.badge>
                                @else
                                    <span title="{{ implode(' ', $blockers) }}"><x-ui.badge tone="amber">{{ count($blockers) }} {{ \Illuminate\Support\Str::plural('blocker', count($blockers)) }}</x-ui.badge></span>
                                    <small class="elig-why">{{ $blockers[0] }}</small>
                                @endif
                            </td>
                            <td data-label="Actions" class="ui-actions">
                                <a href="{{ route('adviser.helper.show', ['id' => $helper->id]) }}" class="btn-secondary"><x-ui-icon name="eye" /> View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @else
            <x-ui.empty title="No helpers found" text="Try a different status filter." />
        @endif
    </x-ui.card>

    <p class="helpers-footer">Quality supervision leads to quality support.</p>
@endsection
