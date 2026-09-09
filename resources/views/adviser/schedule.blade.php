@extends('layouts.app')

@section('title', 'Helper Schedule - COMPASS')

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
    .btn-secondary { background: white; color: #6B7280; border: 1px solid #e5e7eb; }
    .btn-secondary:hover { background: #EAF8F0; color: #04A052; }
    .btn-sm { padding: 6px 14px; font-size: 12px; border-radius: 10px; }
    .form-control { width: 100%; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 12px; font-size: 14px; color: #163B2D; background: white; outline: none; transition: all 0.2s ease; }
    .form-control:focus { border-color: #04A052; box-shadow: 0 0 0 3px rgba(4,160,82,0.12); }
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
            <h1 class="text-xl md:text-2xl font-bold text-gray-800">Helper Schedules</h1>
            <p class="text-sm text-gray-500 hidden sm:block">Review and update shifts for helpers under your supervision.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('adviser.schedule', ['date' => $date->copy()->subDay()->toDateString()]) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-chevron-left"></i>
            </a>
            <span class="px-3 py-1.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg">{{ $date->format('F d, Y') }}</span>
            <a href="{{ route('adviser.schedule', ['date' => $date->copy()->addDay()->toDateString()]) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-100 p-4 text-sm text-green-700 mb-6">
            <i class="fas fa-check-circle mr-1"></i>{{ session('success') }}
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="stat-card border-l-4 border-l-blue-500">
            <span class="stat-label">Total Helpers</span>
            <div class="stat-number text-gray-800">{{ $scheduleData->count() }}</div>
        </div>
        <div class="stat-card border-l-4 border-l-green-500">
            <span class="stat-label">On Shift</span>
            <div class="stat-number text-green-600">{{ $scheduleData->filter(fn($d) => $d['is_on_shift'])->count() }}</div>
        </div>
        <div class="stat-card border-l-4 border-l-yellow-500">
            <span class="stat-label">Off Shift</span>
            <div class="stat-number text-yellow-600">{{ $scheduleData->filter(fn($d) => !$d['is_on_shift'] && $d['has_schedule'])->count() }}</div>
        </div>
        <div class="stat-card border-l-4 border-l-red-500">
            <span class="stat-label">Not Scheduled</span>
            <div class="stat-number text-red-600">{{ $scheduleData->filter(fn($d) => !$d['has_schedule'])->count() }}</div>
        </div>
    </div>

    <!-- Create or Update Schedule -->
    <section class="card mb-6">
        <div class="card-header">
            <h3><i class="fas fa-calendar-plus text-[#04A052] mr-2"></i>Create or Update Schedule</h3>
        </div>
        <form class="form-maximized grid md:grid-cols-5 gap-3" method="POST" action="{{ route('adviser.schedule.update') }}">
            @csrf
            <select name="helper_id" class="form-control" required>
                <option value="">Select helper...</option>
                @foreach($helpers as $helper)
                    <option value="{{ $helper->id }}">{{ $helper->user?->name ?? $helper->full_name }}</option>
                @endforeach
            </select>
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" required>
            <input type="time" name="shift_start" class="form-control" required>
            <input type="time" name="shift_end" class="form-control" required>
            <button type="submit" class="btn btn-primary whitespace-nowrap">
                <i class="fas fa-save mr-1"></i>Save
            </button>
        </form>
    </section>

    <!-- Schedules List -->
    <section class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-alt text-[#04A052] mr-2"></i>Schedules for {{ $date->format('M d, Y') }}</h3>
        </div>
        @forelse($scheduleData as $data)
            <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-center py-4 border-b border-gray-100 last:border-0 text-sm">
                <div class="font-semibold text-gray-800 md:col-span-2">{{ $data['name'] }}</div>
                <div>
                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $data['availability'] === 'available' ? 'bg-green-50 text-green-700' : ($data['availability'] === 'break' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700') }}">
                        {{ ucfirst($data['availability']) }}
                    </span>
                </div>
                <div>
                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $data['is_ready'] ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                        {{ $data['is_ready'] ? 'Ready' : 'Not Ready' }}
                    </span>
                </div>
                <div class="text-gray-600">{{ $data['current_sessions'] }}/{{ $data['max_sessions'] }} sessions</div>
                <div>
                    @if($data['has_schedule'])
                        <div class="font-medium text-gray-700">{{ \Carbon\Carbon::parse($data['shift_start'])->format('h:i A') }} - {{ \Carbon\Carbon::parse($data['shift_end'])->format('h:i A') }}</div>
                        <div class="text-xs {{ $data['is_on_shift'] ? 'text-green-600' : 'text-yellow-600' }}">{{ $data['is_on_shift'] ? 'On shift' : 'Off shift' }}</div>
                    @else
                        <div class="text-gray-400">Not scheduled</div>
                        <div class="text-xs text-red-500">Cannot receive sessions</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-8">
                <i class="fas fa-calendar-times text-4xl text-gray-300 mb-2 block"></i>
                <h3 class="text-lg font-semibold text-gray-700">No supervised helpers found</h3>
                <p class="text-sm text-gray-500">Helpers assigned to you will appear here for scheduling.</p>
            </div>
        @endforelse
    </section>
</div>
@endsection
