@extends('layouts.app')

@section('title', 'Helper Detail – COMPASS')

@push('styles')
<style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }
        .card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #e5e7eb; box-shadow: 0 4px 20px rgba(0,0,0,0.01); }
        .competency-level { padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .competency-level.expert { background: #dcfce7; color: #166534; }
        .competency-level.advanced { background: #dbeafe; color: #1e40af; }
        .competency-level.intermediate { background: #fef3c7; color: #92400e; }
        .competency-level.beginner { background: #fee2e2; color: #991b1b; }
        .competency-level.trainee { background: #e5e7eb; color: #6b7280; }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        @media (max-width: 768px) {
            .card { padding: 16px; }
        }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center gap-4 mb-6">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Helper Detail</h1>
                <p class="text-sm text-gray-500 hidden sm:block">
                    Performance history and supervision
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif

        <!-- Back Link -->
        <a href="{{ route('adviser.helpers') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-6 text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Back to Helpers
        </a>

        <div class="card">

            <!-- Header -->
            <div class="flex flex-wrap items-center gap-4 mb-6 pb-4 border-b border-gray-200">
                <div class="w-16 h-16 rounded-full bg-green-500 flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
                    {{ substr($helper->first_name, 0, 1) }}{{ substr($helper->last_name, 0, 1) }}
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ $helper->first_name }} {{ $helper->last_name }}</h1>
                    <p class="text-sm text-gray-500">
                        @if($helper->adviser)
                            Supervised by: {{ $helper->adviser->first_name }} {{ $helper->adviser->last_name }}
                        @else
                            No adviser assigned
                        @endif
                    </p>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <span class="competency-level {{ strtolower($competencyHistory->first()->competency_level ?? 'Beginner') }}">
                        {{ $competencyHistory->first()->competency_level ?? 'Beginner' }}
                    </span>
                    <span class="text-sm text-gray-500">Score: {{ $competencyHistory->first()->overall_score ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">{{ $helper->sessions->count() }}</p>
                    <p class="text-xs text-gray-400">Active Sessions</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">{{ $competencyHistory->count() }}</p>
                    <p class="text-xs text-gray-400">Evaluations</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">
                        <x-ui-icon :value="$helper->isOnline() ? 'fa-circle-check' : 'fa-circle-xmark'" />
                    </p>
                    <p class="text-xs text-gray-400">Available</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">
                        <x-ui-icon :value="$helper->latestReadiness?->assessment_result === 'ready' ? 'fa-circle' : 'fa-circle'" />
                    </p>
                    <p class="text-xs text-gray-400">Readiness</p>
                </div>
            </div>

            <!-- Supervision -->
            <h3 class="font-semibold text-gray-700 mb-4">Supervision</h3>
            <div class="flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-xl mb-6">
                <span class="text-sm text-gray-500">Assigned adviser:</span>
                <span class="text-sm font-semibold text-gray-700">{{ $helper->adviser?->first_name }} {{ $helper->adviser?->last_name }}</span>
                <span class="text-xs text-gray-400">Assignment is handled by moderators and the system.</span>
            </div>

            <!-- Competency History -->
            <h3 class="font-semibold text-gray-700 mb-4">Competency History</h3>
            @if($competencyHistory->isNotEmpty())
                <div class="space-y-2">
                    @foreach($competencyHistory as $evaluation)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <div>
                                <p class="font-medium text-gray-800">{{ $evaluation->evaluation_period ?? $evaluation->created_at->format('M Y') }}</p>
                                <p class="text-sm text-gray-500">Adviser: {{ $evaluation->adviser->first_name ?? 'Unknown' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-800">{{ round($evaluation->overall_score, 2) }} / 5.00</p>
                                <span class="competency-level {{ strtolower($evaluation->competency_level ?? 'Beginner') }}">
                                    {{ $evaluation->competency_level ?? 'Beginner' }}
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
});
    </script>
@endsection
