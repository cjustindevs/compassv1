@extends('layouts.app')

@section('title', 'COMPASS – Reports & Analytics')

@push('styles')
<style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.04); }
        .stat-number { font-size: 28px; font-weight: 800; color: var(--gray-800); }
        .stat-label { font-size: 13px; color: var(--gray-500); }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }

        .filter-select {
            padding: 8px 14px;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            outline: none;
            font-size: 13px;
            background: white;
        }
        .filter-select:focus { border-color: var(--green-500); }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-primary:hover { background: var(--green-700); transform: scale(1.02); }
        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            padding: 8px 18px;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-300); }

        .chart-bar {
            display: flex;
            align-items: flex-end;
            height: 120px;
            gap: 6px;
        }
        .chart-bar .bar {
            flex: 1;
            border-radius: 4px 4px 0 0;
            min-height: 8px;
            transition: height 0.5s ease;
            background: linear-gradient(180deg, #04A052, #38C172);
        }
        .chart-bar .bar-label {
            font-size: 10px;
            text-align: center;
            color: var(--gray-400);
            margin-top: 4px;
        }

        .ranking-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 12px;
            transition: background 0.2s;
        }
        .ranking-item:hover { background: var(--gray-50); }
        .ranking-item .rank { font-weight: 700; font-size: 14px; color: var(--gray-400); width: 24px; }
        .ranking-item .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }
        .ranking-item .info { flex: 1; }
        .ranking-item .info .name { font-weight: 500; font-size: 14px; color: var(--gray-800); }
        .ranking-item .info .detail { font-size: 12px; color: var(--gray-400); }
        .ranking-item .score { font-weight: 600; font-size: 14px; color: var(--gray-800); }

        .competency-level {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .competency-level.expert { background: #dcfce7; color: #166534; }
        .competency-level.advanced { background: #dbeafe; color: #1e40af; }
        .competency-level.intermediate { background: #fef3c7; color: #92400e; }
        .competency-level.beginner { background: #fee2e2; color: #991b1b; }
        .competency-level.trainee { background: #e5e7eb; color: #6b7280; }
        @media (max-width: 768px) {
            .stat-number { font-size: 22px; }
            .grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
            .card { padding: 16px; }
        }
        @media (max-width: 480px) {
            .grid-cols-4 { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-number { font-size: 18px; }
        }
    </style>
@endpush

@section('content')
<div class="card mb-6">
    <h2 class="text-lg font-semibold mb-3">Risk distribution</h2>
    @foreach(['low', 'moderate', 'high', 'emergency'] as $risk)
        <div class="flex items-center gap-3 mb-2">
            <span class="w-24">{{ ucfirst($risk) }}</span>
            <progress class="flex-1" value="{{ $riskDistribution[$risk] ?? 0 }}" max="{{ max(1, $totalSessions) }}" aria-label="{{ ucfirst($risk) }} sessions"></progress>
            <span>{{ $riskDistribution[$risk] ?? 0 }}</span>
        </div>
    @endforeach
</div>

<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Reports & Analytics</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Program performance metrics and helper analytics
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <form class="inline" action="{{ route('adviser.reports.export') }}" method="GET">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <input type="hidden" name="helper_id" value="{{ $helperId }}">
                    @if(request('from'))
                        <input type="hidden" name="from" value="{{ request('from') }}">
                        <input type="hidden" name="to" value="{{ request('to') }}">
                    @endif
                    <button type="submit" class="btn-outline">
                        <i class="fas fa-file-export mr-1"></i> Export
                    </button>
                </form>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-6 flex flex-wrap items-center gap-4">
            <form class="form-maximized flex flex-wrap items-center gap-3" method="GET" action="{{ route('adviser.reports') }}">
                <select name="period" class="filter-select">
                    <option value="weekly" {{ $period == 'weekly' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="monthly" {{ $period == 'monthly' ? 'selected' : '' }}>Last 30 Days</option>
                    <option value="quarterly" {{ $period == 'quarterly' ? 'selected' : '' }}>Last 90 Days</option>
                    <option value="yearly" {{ $period == 'yearly' ? 'selected' : '' }}>Last Year</option>
                </select>

                <select name="helper_id" class="filter-select">
                    <option value="">All Helpers</option>
                    @foreach($helpers as $helper)
                        <option value="{{ $helper->id }}" {{ $helperId == $helper->id ? 'selected' : '' }}>
                            {{ $helper->first_name }} {{ $helper->last_name }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="from" value="{{ request('from') }}" class="filter-select" title="From date">
                <input type="date" name="to" value="{{ request('to') }}" class="filter-select" title="To date">

                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter mr-1"></i> Apply Filters
                </button>
            </form>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Sessions</span>
                    <span class="text-2xl"><i class="fas fa-chart-column" aria-hidden="true"></i></span>
                </div>
                <div class="stat-number">{{ $totalSessions }}</div>
                <span class="text-xs text-gray-400">All sessions</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Completion Rate</span>
                    <span class="text-2xl"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
                </div>
                <div class="stat-number">{{ $completionRate }}%</div>
                <span class="text-xs text-gray-400">{{ $completedSessions }} completed</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Avg Response</span>
                    <span class="text-2xl"><i class="fas fa-stopwatch" aria-hidden="true"></i></span>
                </div>
                <div class="stat-number">{{ $avgResponseTime }}</div>
                <span class="text-xs text-gray-400">First response</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Avg Waiting</span>
                    <span class="text-2xl"><i class="fas fa-clock" aria-hidden="true"></i></span>
                </div>
                <div class="stat-number">{{ $avgWaitingTime }}</div>
                <span class="text-xs text-gray-400">Queue to session</span>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

            <!-- Referral Stats -->
            <div class="card">
                <div class="card-header">
                    <h3>Referral Statistics</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total Referrals</span>
                        <span class="font-bold text-gray-800">{{ $referralStats['total'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Approved</span>
                        <span class="font-bold text-green-600">{{ $referralStats['approved'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Pending Review</span>
                        <span class="font-bold text-yellow-600">{{ $referralStats['pending'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Declined</span>
                        <span class="font-bold text-red-600">{{ $referralStats['declined'] }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                        <span class="text-sm font-semibold text-gray-700">Acceptance Rate</span>
                        <span class="font-bold text-gray-800">{{ $referralStats['acceptance_rate'] }}%</span>
                    </div>
                </div>
            </div>

            <!-- Satisfaction Scores -->
            <div class="card">
                <div class="card-header">
                    <h3>Satisfaction Scores</h3>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Overall Satisfaction</span>
                            <span class="font-bold text-gray-800">{{ $satisfactionScores['overall'] }} / 5</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full mt-1">
                            <div class="h-full bg-green-500 rounded-full" style="width: {{ ($satisfactionScores['overall'] / 5) * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Helpfulness</span>
                            <span class="font-bold text-gray-800">{{ $satisfactionScores['helpfulness'] }} / 5</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full mt-1">
                            <div class="h-full bg-green-500 rounded-full" style="width: {{ ($satisfactionScores['helpfulness'] / 5) * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Comfort Level</span>
                            <span class="font-bold text-gray-800">{{ $satisfactionScores['comfort'] }} / 5</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full mt-1">
                            <div class="h-full bg-green-500 rounded-full" style="width: {{ ($satisfactionScores['comfort'] / 5) * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Post-Session Feeling</span>
                            <span class="font-bold text-gray-800">{{ $satisfactionScores['feeling'] }} / 5</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full mt-1">
                            <div class="h-full bg-green-500 rounded-full" style="width: {{ ($satisfactionScores['feeling'] / 5) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Monthly Trends -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Monthly Trends</h3>
                <span class="text-sm text-gray-400">Sessions & performance</span>
            </div>
            <div class="overflow-x-auto">
                <div class="chart-bar" style="height: 180px;">
                    @foreach($monthlyTrends as $trend)
                        <div class="flex-1 flex flex-col items-center">
                            <div class="w-full flex flex-col items-center gap-1">
                                <span class="text-xs text-gray-600 font-medium">{{ $trend['sessions'] }}</span>
                                <div class="bar w-full" style="height: {{ ($trend['sessions'] / 60) * 140 }}px; background: linear-gradient(180deg, #04A052, #38C172);"></div>
                            </div>
                            <span class="text-xs text-gray-400 mt-2">{{ $trend['month'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-center gap-6 mt-4 text-xs text-gray-500">
                <span><span class="inline-block w-3 h-3 bg-green-500 rounded-sm mr-1"></span> Sessions</span>
                <span><span class="inline-block w-3 h-3 bg-blue-400 rounded-sm mr-1"></span> Completion Rate</span>
            </div>
        </div>

        <!-- Helper Ranking -->
        <div class="card">
            <div class="card-header">
                <h3>Helper Performance Ranking</h3>
                <span class="text-sm text-gray-400">Top performers</span>
            </div>
            <div class="space-y-1">
                @foreach($helperRanking->take(10) as $index => $helper)
                    <div class="ranking-item">
                        <span class="rank">#{{ $index + 1 }}</span>
                        <div class="avatar" style="background: {{ $helper->competency >= 4 ? '#04A052' : ($helper->competency >= 3 ? '#3B82F6' : '#9CA3AF') }};">
                            {{ $helper->initials }}
                        </div>
                        <div class="info">
                            <div class="name">{{ $helper->name }}</div>
                            <div class="detail">
                                {{ $helper->sessions }} sessions · {{ $helper->rating }} <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                                <span class="competency-level {{ strtolower($helper->level) }} ml-2">
                                    {{ $helper->level }}
                                </span>
                            </div>
                        </div>
                        <div class="score">{{ $helper->competency }} / 5</div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Data-driven decisions lead to better outcomes.
        </div>
</div>
@endsection
