@extends('layouts.app')
@section('title', 'Emergency Management - COMPASS')
@section('content')
<div class="adviser-page-content adviser-compact-page emergency-workspace">
    <header class="ew-header"><h1>Emergency Management</h1></header>
    @if(session('success'))<p class="ew-feedback" role="status">{{ session('success') }}</p>@endif
    <x-performance-cards :cards="[['label'=>'Active Emergencies','value'=>$activeCount],['label'=>'Resolved','value'=>$resolvedCount],['label'=>'Total Emergencies','value'=>$totalEmergencies]]" />
    <section class="ew-panel">
        <header class="ew-toolbar">
            <nav class="ew-tabs" aria-label="Emergency cases">
                <a href="{{ route('adviser.emergencies', ['tab'=>'active']) }}" @if($tab==='active') aria-current="page" @endif>Active <span>{{ $activeCount }}</span></a>
                <a href="{{ route('adviser.emergencies', ['tab'=>'resolved']) }}" @if($tab==='resolved') aria-current="page" @endif>Resolved <span>{{ $resolvedCount }}</span></a>
            </nav>
            <form method="GET" action="{{ route('adviser.emergencies') }}" class="ew-search"><input type="hidden" name="tab" value="{{ $tab }}"><label class="sr-only" for="emergencySearch">Search cases</label><input id="emergencySearch" name="q" value="{{ $search }}" maxlength="100" placeholder="Case, alias or Helper"><button type="submit">Search</button>@if($search)<a href="{{ route('adviser.emergencies',['tab'=>$tab]) }}">Reset</a>@endif</form>
        </header>
        @php($alerts = $tab==='resolved' ? $resolvedAlerts : $openAlerts)
        <h2 class="ew-section-title">{{ $tab==='resolved' ? 'Resolved Cases' : 'Open Emergency Cases' }}</h2>
        <div class="ew-table-wrap"><table class="ew-table"><thead><tr><th>Case / Seeker</th><th>Helper</th><th>Primary Adviser</th><th>Emergency coordinator</th><th>{{ $tab==='resolved' ? 'Resolved on' : 'Detected' }}</th><th>Status</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
        @forelse($alerts as $alert)
        <tr><td data-label="Case / Seeker"><strong>#{{ $alert->id }}</strong><span class="ew-cell-detail">{{ $alert->session?->seeker?->generated_alias ?? 'Unavailable' }}</span></td>
            <td data-label="Helper">{{ $alert->session?->helper?->full_name ?: 'Unassigned' }}</td>
            <td data-label="Primary Adviser">{{ $alert->session?->helper?->adviser?->full_name ?: 'Unassigned' }}</td>
            <td data-label="Emergency coordinator">{{ app(\App\Services\EmergencyCoordinator::class)->name($alert->adviser) }}</td>
            <td data-label="{{ $tab==='resolved' ? 'Resolved on' : 'Detected' }}">@php($at = $tab==='resolved' ? $alert->resolved_at : $alert->triggered_at)@if($at)<time datetime="{{ $at->toIso8601String() }}">{{ $at->copy()->timezone('Asia/Manila')->format('M d, Y') }}<span class="ew-cell-detail">{{ $at->copy()->timezone('Asia/Manila')->format('g:i A') }} PHT</span></time>@else — @endif</td>
            <td data-label="Status"><span class="ew-badge {{ $tab==='resolved' ? 'ew-resolved' : 'ew-active' }}">{{ $tab==='resolved' ? 'Resolved' : ($alert->acknowledged_at ? 'Responding' : 'Awaiting acknowledgment') }}</span></td>
            <td><a class="ew-button" href="{{ route('adviser.emergencies.show', $alert->id) }}">View case<span class="sr-only"> #{{ $alert->id }}</span></a></td></tr>
        @empty<tr><td colspan="7" class="ew-empty">{{ $search ? 'No cases match your search.' : ($tab==='resolved' ? 'No resolved emergency cases yet.' : 'No Open Emergency Cases') }}</td></tr>@endforelse
        </tbody></table></div>
        <footer class="ew-pagination">{{ $alerts->links() }}</footer>
    </section>
</div>
<link rel="stylesheet" href="{{ asset('css/emergency-workspace.css') }}?v={{ filemtime(public_path('css/emergency-workspace.css')) }}">
@endsection
