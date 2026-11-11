@extends('layouts.app')
@section('title','Emergency Alerts - COMPASS')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/moderator-operations.css') }}?v={{ filemtime(public_path('css/moderator-operations.css')) }}">
<style>
    #moderatorEmergencyContent.mo-page { padding: 0; }
    .mo-monitoring { margin: 0 0 16px !important; text-align: left; }
    .mo-emergency-grid { margin-bottom: 24px; }
    .mo-emergency-grid > .ui-card { margin-bottom: 0; }
    .mo-emergency-cases .ui-badge, .mo-emergency-cases .btn { white-space: nowrap; }
    @media (max-width: 1499px) { .mo-emergency-grid { grid-template-columns: minmax(0, 1fr); } .mo-emergency-grid > .ui-card { margin-bottom: 24px; } }
    .mo-workflow .mo-filter-note { margin-top: 16px; }
</style>
@endpush
@section('content')
<x-ui.page-header title="Emergency Alerts" description="Monitor emergency reports and coordinate with the responsible Adviser.">
    <x-slot:actions><a href="{{ route('moderator.emergency') }}" class="btn btn-outline">Refresh</a></x-slot:actions>
</x-ui.page-header>
@include('moderator.partials.emergency-content')
<meta id="moderatorEmergencyConfig" content="{{ json_encode(['stats' => route('moderator.emergency.stats'), 'signature' => $emergencySignature]) }}">
<script src="{{ asset('js/moderator-emergency.js') }}?v={{ filemtime(public_path('js/moderator-emergency.js')) }}" defer></script>
@endsection
