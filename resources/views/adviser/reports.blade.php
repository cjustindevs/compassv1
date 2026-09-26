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
<div class="adviser-page-content space-y-5">
<header class="flex flex-wrap justify-between items-start gap-3"><div><h1 class="text-2xl font-bold text-gray-800">Reports and analytics</h1><p class="text-sm text-gray-500 mt-1">Authorized supervision records. {{ $filters['start']->copy()->timezone('Asia/Manila')->format('M d, Y H:i') }} to {{ $filters['end']->copy()->timezone('Asia/Manila')->format('M d, Y H:i') }} (Asia/Manila).</p></div>
<div class="flex gap-2"><a class="btn-outline" href="{{ route('adviser.reports.export', array_merge(request()->except('page'),['format'=>'csv'])) }}">Export CSV</a><a class="btn-primary" href="{{ route('adviser.reports.export', array_merge(request()->except('page'),['format'=>'pdf'])) }}">Export PDF</a></div></header>
@if($errors->any())<p role="alert" class="text-red-700">{{ $errors->first() }}</p>@endif
<form method="GET" class="card grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
<label class="text-sm">Period<select name="period" class="filter-select w-full mt-1">@foreach(['weekly'=>'Last 7 days','monthly'=>'Last 30 days','quarterly'=>'Last 90 days','yearly'=>'Last year'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['period'] ?? 'monthly')===$key)>{{ $label }}</option>@endforeach</select></label>
<label class="text-sm">Helper<select name="helper_id" class="filter-select w-full mt-1"><option value="">All supervised Helpers</option>@foreach(\App\Models\Helper::where('adviser_id',auth()->user()->adviser->id)->get() as $helper)<option value="{{ $helper->id }}" @selected(request('helper_id')==$helper->id)>{{ $helper->public_alias }}</option>@endforeach</select></label>
<label class="text-sm">Concern<select name="concern_id" class="filter-select w-full mt-1"><option value="">All concerns</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('concern_id')==$category->id)>{{ $category->concern_name }}</option>@endforeach</select></label>
<label class="text-sm">From (Asia/Manila)<input name="from" type="datetime-local" value="{{ request('from') }}" class="filter-select w-full mt-1"></label>
<label class="text-sm">To (Asia/Manila)<input name="to" type="datetime-local" value="{{ request('to') }}" class="filter-select w-full mt-1"></label>
<label class="text-sm">Referral status<select name="referral_status" class="filter-select w-full mt-1"><option value="">All referrals</option>@foreach(\App\Models\Referral::STATUSES as $status)<option value="{{ $status }}" @selected(request('referral_status')===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
<label class="text-sm">Competency metric<select name="competency_metric" class="filter-select w-full mt-1">@foreach(['overall_score'=>'Overall competency'] + collect(\App\Services\CompetencyRubric::CRITERIA)->mapWithKeys(fn($c)=>[$c[2]=>$c[0]])->all() as $key=>$label)<option value="{{ $key }}" @selected(($filters['competency_metric'] ?? 'overall_score')===$key)>{{ $label }}</option>@endforeach</select></label>
<div class="flex items-end gap-3"><button class="btn-primary" type="submit">Apply filters</button><a href="{{ route('adviser.reports') }}" class="text-sm text-green-700">Reset</a></div>
</form>
<x-adviser-metrics :metrics="$metrics" :definitions="$definitions" />
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
<section class="card"><h2 class="font-semibold mb-4">Monthly session activity</h2>@forelse($trends as $trend)<div class="mb-4 text-sm"><div class="flex justify-between"><span>{{ $trend['month'] }}</span><span>{{ $trend['sessions'] }} sessions ? {{ $trend['completed'] }} completed</span></div><div class="h-3 bg-gray-100 rounded-full mt-2"><div class="h-3 bg-green-600 rounded-full" style="width: {{ 100*$trend['sessions']/max(1,$trends->max('sessions')) }}%"></div></div></div>@empty<p class="text-sm text-gray-500">No session activity in this period.</p>@endforelse</section>
<section class="card"><h2 class="font-semibold mb-4">Competency trend (1?5)</h2><p class="text-xs text-gray-500 mb-3">{{ $filters['competency_metric'] }} ? scored on the evaluation date in the selected period.</p>@forelse($competency as $trend)<p class="flex justify-between text-sm py-2 border-b"><span>{{ $trend['month'] }}</span><span>{{ $trend['score'] }} / 5</span></p>@empty<p class="text-sm text-gray-500">No competency data in this period.</p>@endforelse</section>
</div>
<section class="card"><h2 class="font-semibold mb-3">Session records</h2><p class="text-xs text-gray-500 mb-4">{{ $definitions['period'] }}</p><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-gray-500 border-b"><th class="p-3">Session</th><th class="p-3">Helper</th><th class="p-3">Concern</th><th class="p-3">Status</th><th class="p-3">Documentation</th></tr></thead><tbody>@forelse($sessions as $session)<tr class="border-b"><td class="p-3">{{ $session->reference_number }}</td><td class="p-3">{{ $session->helper?->public_alias ?? 'Unassigned' }}</td><td class="p-3">{{ $session->concern?->concern_name ?? 'Not recorded' }}</td><td class="p-3">{{ ucfirst(str_replace('_',' ',$session->session_status)) }}</td><td class="p-3"><a class="text-green-700 underline" href="{{ route('adviser.session.show',$session->id) }}">Review</a></td></tr>@empty<tr><td colspan="5" class="p-5 text-gray-500">No records match these filters.</td></tr>@endforelse</tbody></table></div><div class="mt-4">{{ $sessions->links() }}</div></section>
<details class="card"><summary class="font-semibold cursor-pointer">Metric definitions</summary>@foreach($definitions as $name=>$definition)<p class="text-sm mt-3"><strong>{{ ucfirst(str_replace('_',' ',$name)) }}:</strong> {{ $definition }}</p>@endforeach<p class="text-sm mt-3">Durations use minutes; duty coverage uses hours; rates use percentages. Calculations round to two decimals. Missing or invalid intervals are excluded. Empty denominators show No data.</p></details>
</div>
@endsection
