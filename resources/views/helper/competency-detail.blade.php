@extends('layouts.helper')

@section('title', 'Competency Evaluation')
@section('heading', 'Competency Evaluation')
@section('subheading', $evaluation->evaluation_date?->format('M d, Y') . ' · ' . ($evaluation->evaluation_period ?: 'Evaluation period N/A'))

@section('content')
        <a href="{{ route('helper.competency') }}" class="btn btn-secondary btn-sm mb-4"><i class="fas fa-arrow-left"></i> Back to competency</a>

    <div class="card">
        <div class="card-header">
            <h3>Overall Score: {{ (int) round($evaluation->overall_score ?? 0) }}%</h3>
            <span class="pill">{{ $evaluation->level_label }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($evaluation->score_breakdown as $key => $score)
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span class="text-sm font-medium text-gray-700">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                        <span class="text-sm font-semibold text-gray-800">{{ (int) round($score) }}%</span>
                    </div>
                    <div class="progress-bar"><span style="width:{{ min(100, $score) }}%"></span></div>
                </div>
            @endforeach
        </div>
        <hr class="divider">
        <p class="text-sm text-gray-500">{{ $evaluation->remarks ?: 'No remarks recorded.' }}</p>
    </div>
@endsection
