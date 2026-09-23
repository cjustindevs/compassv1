@extends('layouts.helper')

@section('title', 'Feedback Detail')
@section('heading', 'Feedback Detail')
@section('subheading', 'Adviser evaluation notes and training recommendations.')

@section('content')
        <a href="{{ route('helper.feedback') }}" class="btn btn-secondary btn-sm mb-4"><i class="fas fa-arrow-left"></i> Back to feedback</a>

    <div class="card">
        <div class="card-header">
            <h3>{{ $feedback->report?->session?->reference_number ?? 'Session Feedback' }}</h3>
            <span class="pill">{{ ucfirst($feedback->competency_level ?? 'Pending') }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div><div class="text-xs text-gray-400 mb-1">Adviser</div><div class="font-semibold text-gray-800">{{ $feedback->adviser?->full_name ?? 'Adviser' }}</div></div>
            <div><div class="text-xs text-gray-400 mb-1">Rating</div><div class="font-semibold text-gray-800">{{ $feedback->competency_rating ?? 'N/A' }}</div></div>
        </div>
        <hr class="divider">
        <h4 class="font-semibold text-gray-800 mb-2">Feedback</h4>
        <p class="text-sm text-gray-600 mb-4">{{ $feedback->feedback_text ?: 'No narrative feedback provided.' }}</p>
        <h4 class="font-semibold text-gray-800 mb-2">Strengths</h4>
        <p class="text-sm text-gray-600 mb-4">{{ $feedback->strengths ?: 'N/A' }}</p>
        <h4 class="font-semibold text-gray-800 mb-2">Improvement Areas</h4>
        <p class="text-sm text-gray-600 mb-4">{{ $feedback->improvement_areas ?: 'N/A' }}</p>
        <h4 class="font-semibold text-gray-800 mb-2">Training Recommendation</h4>
        <p class="text-sm text-gray-600">{{ $feedback->training_recommendation ?: 'No training recommendation recorded.' }}</p>
        <hr class="divider">
        @if($feedback->acknowledged_at)<p class="text-sm text-gray-500">Acknowledged {{ $feedback->acknowledged_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
        @else<form method="POST" action="{{ route('helper.feedback.acknowledge',$feedback->id) }}">@csrf<button class="btn btn-primary" type="submit">Acknowledge feedback</button></form>@endif
    </div>
<x-supervision-history :record="$feedback" />
@endsection
