@extends('layouts.helper')

@section('title', 'Competency')

@section('heading', 'Competency')
@section('subheading', 'Your competency evaluations and growth over time.')

@section('content')
    <p class="mb-4">Sessions completed: <strong>{{ $completedSessions ?? 0 }}</strong></p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Latest score card -->
        <div class="lg:col-span-1">
            <div class="card" style="text-align:center;padding:32px 24px;">
                @if($latest)
                    <div class="text-xs text-gray-400 uppercase tracking-wide">Latest Overall Score</div>
                    <div style="font-size:64px;font-weight:800;color:var(--green-500);line-height:1.1;margin:8px 0;">{{ (int) round($latest->overall_score) }}%</div>
                    <div class="pill">{{ $latest->level_label }}</div>
                    <div class="text-xs text-gray-400 mt-3">{{ $latest->evaluation_date?->format('M d, Y') }} · {{ $latest->evaluation_period ?: 'Period N/A' }}</div>
                    <div class="text-xs text-gray-500 mt-2">Evaluator: {{ $latest->adviser?->full_name ?: 'Adviser' }}</div>
                    <hr class="divider">
                    <div style="display:flex;justify-content:center;gap:16px;flex-wrap:wrap;">
                        <div style="text-align:center;"><div class="font-bold text-gray-800">{{ (int) round($latest->active_listening_score ?? 0) }}</div><div class="text-[10px] text-gray-400 uppercase">Listening</div></div>
                        <div style="text-align:center;"><div class="font-bold text-gray-800">{{ (int) round($latest->empathy_score ?? 0) }}</div><div class="text-[10px] text-gray-400 uppercase">Empathy</div></div>
                    </div>
                    @if($latest->remarks)
                        <hr class="divider">
                        <div class="text-sm text-gray-500" style="font-style:italic;">"{{ $latest->remarks }}"</div>
                    @endif
                @else
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <h3>No evaluations yet</h3>
                        <p>Your competency score will appear here once your adviser evaluates your sessions.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Breakdown + trend -->
        <div class="lg:col-span-2">
            @if($latest)
                <div class="card mb-6">
                    <div class="card-header">
                        <h3>Skill Breakdown</h3>
                        <span class="text-xs text-gray-400">Latest evaluation</span>
                    </div>
                    @php $breakdown = $latest->score_breakdown; @endphp
                    @foreach($breakdown as $key => $score)
                        <div class="mb-4">
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span class="text-sm font-medium text-gray-700">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                                <span class="text-sm font-semibold text-gray-800">{{ (int) round($score) }}%</span>
                            </div>
                            <div class="progress-bar"><span style="width:{{ min(100, $score) }}%"></span></div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="card mb-6">
                <div class="card-header">
                    <h3>Score Trend</h3>
                    <span class="text-xs text-gray-400">{{ $totalEvaluations }} evaluation(s)</span>
                </div>
                @if($trend->isNotEmpty())
                    <div style="display:flex;align-items:flex-end;gap:12px;height:160px;padding:8px 4px;">
                        @foreach($trend as $point)
                            <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;">
                                <span style="font-size:11px;font-weight:700;color:var(--gray-700);margin-bottom:4px;">{{ (int) round($point['score']) }}</span>
                                <div style="width:100%;max-width:48px;background:linear-gradient(180deg,#38C172,var(--green-500));border-radius:8px 8px 0 0;transition:height .4s ease;height:{{ max(8, $point['score']) }}%;" title="{{ $point['label'] }}: {{ $point['score'] }}%"></div>
                                <span style="font-size:9px;color:var(--gray-400);margin-top:6px;">{{ \Illuminate\Support\Carbon::parse($point['label'])->format('M') }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state"><i class="fas fa-chart-bar"></i><p>Trend will appear once you have evaluations.</p></div>
                @endif
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>History</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Overall</th>
                                <th>Level</th>
                                <th>Evaluator</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history as $record)
                                <tr>
                                    <td>{{ $record['date'] }}</td>
                                    <td><strong class="text-green-600">{{ (int) round($record['overall']) }}%</strong></td>
                                    <td><span class="pill">{{ $record['level'] }}</span></td>
                                    <td class="text-sm text-gray-500">{{ $record['adviser'] }}</td>
                                    <td class="text-sm text-gray-500">{{ Illuminate\Support\Str::limit($record['remarks'], 40) ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-6 text-gray-400">No evaluations yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $history->links() }}
            </div>
        </div>

    </div>

@endsection