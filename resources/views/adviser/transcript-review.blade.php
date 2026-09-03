@extends('layouts.app')

@section('title', 'Transcripts - COMPASS')

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
            <h1 class="text-xl md:text-2xl font-bold text-gray-800">Transcript Review</h1>
            <p class="text-sm text-gray-500 hidden sm:block">Review and verify completed session transcripts.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-yellow-50 text-yellow-700">{{ count($unverifiedTranscripts) }} Pending</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="stat-card border-l-4 border-l-yellow-500">
            <span class="stat-label">Pending Review</span>
            <div class="stat-number text-yellow-600">{{ count($unverifiedTranscripts) }}</div>
            <span class="text-xs text-gray-400">Awaiting verification</span>
        </div>
        <div class="stat-card border-l-4 border-l-green-500">
            <span class="stat-label">Verified</span>
            <div class="stat-number text-green-600">{{ $verifiedCount ?? 0 }}</div>
            <span class="text-xs text-gray-400">Completed reviews</span>
        </div>
        <div class="stat-card border-l-4 border-l-blue-500">
            <span class="stat-label">Total Transcripts</span>
            <div class="stat-number text-gray-800">{{ ($verifiedCount ?? 0) + count($unverifiedTranscripts) }}</div>
            <span class="text-xs text-gray-400">All sessions</span>
        </div>
    </div>

    <!-- Transcripts List -->
    @forelse($unverifiedTranscripts as $item)
        <section class="card mb-6">
            <div class="card-header">
                <div>
                    <h3>Session #{{ $item['session_id'] }}</h3>
                    <p class="text-sm text-gray-500">{{ $item['seeker_alias'] }} to {{ $item['helper_name'] }} &middot; {{ $item['session_date']?->format('M d, Y h:i A') }}</p>
                </div>
                <form method="POST" action="{{ route('adviser.transcript.verify', $item['session_id']) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-check mr-1"></i>Verify
                    </button>
                </form>
            </div>
            <div class="max-h-80 overflow-y-auto bg-gray-50 rounded-xl p-4 space-y-3 mb-4">
                @foreach($item['messages'] as $message)
                    <div>
                        <div class="text-xs text-gray-500 font-medium">{{ $message->senderName() }} &middot; {{ $message->time_formatted }}</div>
                        <div class="text-sm text-gray-700 mt-0.5">{{ $message->transcript ?: $message->message_text }}</div>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm">
                    <i class="fas fa-print mr-1"></i>Print
                </button>
                <a href="{{ route('api.transcript.download', $item['session_id']) }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-download mr-1"></i>Download
                </a>
            </div>
        </section>
    @empty
        <section class="card">
            <div class="text-center py-8">
                <i class="fas fa-check-circle text-4xl text-green-500 mb-2 block"></i>
                <h3 class="text-lg font-semibold text-gray-800">All Transcripts Reviewed</h3>
                <p class="text-sm text-gray-500">All transcripts have been reviewed. Good job!</p>
            </div>
        </section>
    @endforelse
</div>
@endsection
