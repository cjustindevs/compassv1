@extends('layouts.app')
@section('title', 'Emergency Management - COMPASS')
@section('content')
@php
    $alerts = $tab === 'resolved' ? $resolvedAlerts : $openAlerts;
    $chips = \App\Support\FilterChips::from(request(), ['q' => 'Search'], ['tab']);
@endphp
<div class="emergency-workspace">
    <x-ui.page-header title="Emergency Management" description="Review, coordinate and resolve emergency cases" />
    @if(session('success'))<p class="ew-feedback" role="status">{{ session('success') }}</p>@endif
    <x-ui.kpi-grid>
        <x-ui.kpi label="Active Emergencies" :value="$activeCount" tone="red" />
        <x-ui.kpi label="Resolved" :value="$resolvedCount" tone="green" />
        <x-ui.kpi label="Total Emergencies" :value="$totalEmergencies" tone="neutral" />
    </x-ui.kpi-grid>
    <x-ui.filter-bar :action="route('adviser.emergencies')">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <x-ui.field label="Search cases" for="emergencySearch">
            <input id="emergencySearch" name="q" value="{{ $search }}" maxlength="100" placeholder="Case, alias or Helper" data-control="search">
        </x-ui.field>
        <x-slot:buttons><button type="submit" class="btn-primary">Search</button></x-slot:buttons>
    </x-ui.filter-bar>
    <x-ui.filter-chips :chips="$chips" :reset-href="route('adviser.emergencies', ['tab' => $tab])" />
    <x-ui.card :title="$tab === 'resolved' ? 'Resolved Cases' : 'Open Emergency Cases'">
        <x-slot:actions>
            <a class="btn {{ $tab === 'active' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('adviser.emergencies', ['tab'=>'active']) }}" @if($tab==='active') aria-current="page" @endif>Active <span>{{ $activeCount }}</span></a>
            <a class="btn {{ $tab === 'resolved' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('adviser.emergencies', ['tab'=>'resolved']) }}" @if($tab==='resolved') aria-current="page" @endif>Resolved <span>{{ $resolvedCount }}</span></a>
        </x-slot:actions>
        @if($alerts->count())
        <x-ui.table :stack="true" class="ui-table--wide">
            <thead><tr><th>Case / Seeker</th><th>Helper</th><th>Primary Adviser</th><th>Emergency coordinator</th><th>{{ $tab==='resolved' ? 'Resolved on' : 'Detected' }}</th><th>Status</th><th><span class="sr-only">Action</span></th></tr></thead>
            <tbody>
            @foreach($alerts as $alert)
            <tr>
                <td data-label="Case / Seeker"><strong>#{{ $alert->id }}</strong><span class="ew-cell-detail">{{ $alert->session?->seeker?->generated_alias ?? 'Unavailable' }}</span></td>
                <td data-label="Helper">{{ $alert->session?->helper?->full_name ?: 'Unassigned' }}</td>
                <td data-label="Primary Adviser">{{ $alert->session?->helper?->adviser?->full_name ?: 'Unassigned' }}</td>
                <td data-label="Emergency coordinator">{{ app(\App\Services\EmergencyCoordinator::class)->name($alert->adviser) }}</td>
                <td data-label="{{ $tab==='resolved' ? 'Resolved on' : 'Detected' }}">@php($at = $tab==='resolved' ? $alert->resolved_at : $alert->triggered_at)@if($at)<time datetime="{{ $at->toIso8601String() }}">{{ $at->copy()->timezone('Asia/Manila')->format('M d, Y') }}<span class="ew-cell-detail">{{ $at->copy()->timezone('Asia/Manila')->format('g:i A') }} PHT</span></time>@else — @endif</td>
                <td data-label="Status"><x-ui.badge :tone="$tab==='resolved' ? 'green' : ($alert->acknowledged_at ? 'blue' : 'amber')">{{ $tab==='resolved' ? 'Resolved' : ($alert->acknowledged_at ? 'Responding' : 'Awaiting acknowledgment') }}</x-ui.badge></td>
                <td data-label="Action" class="ui-actions"><a class="btn btn-outline" href="{{ route('adviser.emergencies.show', $alert->id) }}">View case<span class="sr-only"> #{{ $alert->id }}</span></a></td>
            </tr>
            @endforeach
            </tbody>
        </x-ui.table>
        @else
            <x-ui.empty :title="$search ? 'No cases match your search.' : ($tab==='resolved' ? 'No resolved emergency cases yet.' : 'No Open Emergency Cases')" />
        @endif
        <footer class="ew-pagination">{{ $alerts->links('vendor.pagination.compass', ['noun' => 'cases']) }}</footer>
    </x-ui.card>
</div>
<link rel="stylesheet" href="{{ asset('css/emergency-workspace.css') }}?v={{ filemtime(public_path('css/emergency-workspace.css')) }}">
@endsection
