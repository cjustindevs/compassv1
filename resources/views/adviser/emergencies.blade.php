@extends('layouts.app')

@section('title', 'Emergencies - COMPASS')

@push('styles')
<style>
    .adviser-page-content { background: #F8FBF9; }
    .card {
        background: white; border-radius: 16px; padding: 20px 24px;
        border: 1px solid #e5e7eb; box-shadow: 0 4px 20px rgba(0,0,0,0.01);
    }
    .card-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 16px; gap: 12px; flex-wrap: wrap;
    }
    .card-header h3 { font-weight: 700; font-size: 16px; color: #163B2D; }
    .btn, .btn:link { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 18px; border-radius: 12px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s ease; text-decoration: none; }
    .btn-primary { background: #04A052; color: white; }
    .btn-primary:hover { background: #038A45; color: white; }
    .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 10px; }
    .stat-card { background: white; border-radius: 16px; padding: 20px 24px; border: 1px solid #e5e7eb; transition: all 0.3s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.04); }
    .stat-number { font-size: 28px; font-weight: 800; color: #163B2D; }
    .stat-label { font-size: 13px; color: #6B7280; }
</style>
@endpush

@section('content')
<div class="adviser-page-content">
    <!-- Top Bar -->
    <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <div>
            <h1 class="text-xl md:text-2xl font-bold text-gray-800">Emergency Management</h1>
            <p class="text-sm text-gray-500 hidden sm:block">Review emergency escalations for helpers under your supervision.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-red-50 text-red-700">
                <i class="fas fa-circle text-[8px] mr-2"></i>{{ $openAlerts->count() }} Active
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-100 p-4 text-sm text-green-700 mb-6">
            <i class="fas fa-check-circle mr-1"></i>{{ session('success') }}
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="stat-card border-l-4 border-l-red-500">
            <span class="stat-label">Active Emergencies</span>
            <div class="stat-number text-red-600">{{ $openAlerts->count() }}</div>
            <span class="text-xs text-gray-400">Requires immediate attention</span>
        </div>
        <div class="stat-card border-l-4 border-l-green-500">
            <span class="stat-label">Resolved</span>
            <div class="stat-number text-green-600">{{ $resolvedAlerts->count() }}</div>
            <span class="text-xs text-gray-400">Successfully handled</span>
        </div>
        <div class="stat-card border-l-4 border-l-blue-500">
            <span class="stat-label">Total Emergencies</span>
            <div class="stat-number text-gray-800">{{ $totalEmergencies }}</div>
            <span class="text-xs text-gray-400">All visible cases</span>
        </div>
    </div>

    <!-- Open Emergency Cases -->
    <section class="card mb-6">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Open Emergency Cases</h3>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700">{{ $openAlerts->count() }}</span>
        </div>
        @forelse($openAlerts as $alert)
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 p-4 bg-gray-50 rounded-xl mb-3 last:mb-0">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">URGENT</span>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">{{ $alert->triggered_at?->diffForHumans() }}</span>
                    </div>
                    <p class="font-semibold text-gray-800">{{ $alert->session?->seeker?->generated_alias ?? 'Unknown seeker' }}</p>
                    <p class="text-sm text-gray-600"><strong>Helper:</strong> {{ $alert->session?->helper?->user?->name ?? $alert->session?->helper?->full_name ?? 'N/A' }}</p>
                    <p class="text-sm text-gray-500"><strong>Reason:</strong> {{ $alert->trigger_reason ?? 'Emergency detected' }}</p>
                    <p class="text-xs text-gray-400 mt-1">Triggered {{ $alert->triggered_at?->format('M d, Y h:i A') ?? 'recently' }}</p>
                </div>
                <a href="{{ route('adviser.emergencies.show', $alert->id) }}" class="btn btn-primary btn-sm whitespace-nowrap">
                    <i class="fas fa-eye mr-1"></i>View Details
                </a>
            </div>
        @empty
            <div class="text-center py-8">
                <i class="fas fa-check-circle text-4xl text-green-500 mb-2 block"></i>
                <h3 class="text-lg font-semibold text-gray-800">No Open Emergency Cases</h3>
                <p class="text-sm text-gray-500">All emergencies have been resolved.</p>
            </div>
        @endforelse
    </section>

    <!-- Resolved Cases -->
    <section class="card">
        <div class="card-header">
            <h3><i class="fas fa-check-circle text-green-500 mr-2"></i>Resolved Cases</h3>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-50 text-green-700">{{ $resolvedAlerts->count() }}</span>
        </div>
        @forelse($resolvedAlerts as $alert)
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 py-3 border-b border-gray-100 last:border-0 text-sm">
                <div>
                    <span class="font-semibold text-gray-800">#{{ $alert->id }}</span>
                    <span class="text-gray-600">
                        {{ $alert->session?->seeker?->generated_alias ?? 'Unknown seeker' }} with {{ $alert->session?->helper?->user?->name ?? $alert->session?->helper?->full_name ?? 'N/A' }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-green-700 font-semibold">{{ $alert->resolved_at?->diffForHumans() ?? 'Resolved' }}</span>
                    <a href="{{ route('adviser.emergencies.show', $alert->id) }}" class="text-[#04A052] font-semibold hover:underline">View</a>
                </div>
            </div>
        @empty
            <div class="text-center py-8">
                <i class="fas fa-inbox text-4xl text-gray-300 mb-2 block"></i>
                <p class="text-sm text-gray-500">No resolved emergency cases yet.</p>
            </div>
        @endforelse
    </section>
</div>
@endsection
