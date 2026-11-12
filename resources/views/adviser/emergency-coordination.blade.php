@extends('layouts.app')
@section('title', 'Emergency coordination - COMPASS')
@section('content')
<div class="er-coordination">
    <x-ui.page-header :title="'Emergency #'.$alert->id" description="Shared emergency coordination notice for all Advisers." :back-href="route('adviser.emergencies')" back-label="Back to Emergencies" />
    <x-ui.card :title="$alert->acknowledged_at ? 'Reviewer has acknowledged this case' : 'Awaiting reviewer acknowledgment'" subtitle="Shared awareness only. This case is not assigned to you.">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('adviser.emergency.coordinate', ['emergency_alerts', $alert->id]) }}">Open / Assign Helper</a>
        </x-slot:actions>
        <dl class="erc-facts">
            <div><dt>Responsible emergency coordinator</dt><dd>{{ app(\App\Services\EmergencyCoordinator::class)->name($alert->adviser) }}</dd></div>
            <div><dt>Primary Adviser</dt><dd>{{ app(\App\Services\EmergencyCoordinator::class)->name($alert->session?->helper?->adviser) }}</dd></div>
            <div><dt>Assigned Helper</dt><dd>{{ $alert->session?->helper?->full_name ?? 'Awaiting match' }}</dd></div>
            <div><dt>Status</dt><dd><x-ui.badge :tone="in_array($alert->status, ['resolved','closed']) ? 'green' : 'amber'">{{ ucfirst($alert->status) }}</x-ui.badge></dd></div>
            <div><dt>Reported</dt><dd>{{ $alert->triggered_at?->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</dd></div>
        </dl>
        <p class="erc-note">Coordinate with the responsible reviewer. Private screening, session documentation, and review actions remain with the assigned reviewer and Helper supervisor.</p>
    </x-ui.card>
</div>
<style>
.erc-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px 24px;margin:0 0 16px}
.erc-facts dt{font-size:13px;color:var(--compass-muted,#6b7280);margin-bottom:4px}.erc-facts dd{margin:0;font-size:15px;font-weight:600;overflow-wrap:anywhere}
.erc-note{margin:0;font-size:14px;color:var(--compass-muted,#6b7280)}
</style>
@endsection
