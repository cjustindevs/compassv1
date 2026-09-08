@extends('layouts.helper')

@section('title', 'Calendar')

@section('heading', 'Calendar')
@section('subheading', 'Your scheduled sessions for ' . $monthName . '.')

@section('content')

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
        <div style="display:flex;gap:8px;">
            <a href="{{ route('helper.calendar', ['month' => $prevMonth, 'year' => $prevYear]) }}" class="btn btn-secondary btn-sm"><i class="fas fa-chevron-left"></i> Prev</a>
            <a href="{{ route('helper.calendar', ['month' => $nextMonth, 'year' => $nextYear]) }}" class="btn btn-secondary btn-sm">Next <i class="fas fa-chevron-right"></i></a>
        </div>
        <div class="text-lg font-bold text-gray-800">{{ $monthName }}</div>
        <form method="GET" action="{{ route('helper.calendar') }}" style="display:flex;align-items:center;gap:8px;">
            <input type="hidden" name="year" value="{{ $year }}">
            <select name="month" class="form-control form-control-sm" onchange="this.form.submit()">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m === $month ? 'selected' : '' }}>{{ now()->month($m)->format('F') }}</option>
                @endfor
            </select>
        </form>
    </div>

    <div class="card">
        <div class="calendar-grid calendar-head">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                <div class="day-name">{{ $day }}</div>
            @endforeach
        </div>
        <hr class="divider">
        <div class="calendar-grid">
            @foreach($grid as $week)
                @foreach($week as $cell)
                    <div class="calendar-cell {{ !$cell['in_month'] ? 'outside' : '' }} {{ $cell['today'] ? 'today' : '' }}">
                        <div class="day-num">{{ $cell['day'] }}</div>
                        @foreach($cell['events'] as $event)
                            <a href="{{ route('helper.cases.show', ['id' => $event['id']]) }}" class="calendar-event {{ str_replace('_', '-', $event['status']) }}" title="{{ $event['reference'] }} — {{ $event['alias'] }}">
                                <span class="dot"></span>{{ $event['time'] ?? $event['reference'] }} · {{ $event['alias'] }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    <div class="card mt-6">
        <div class="card-header">
            <h3>Sessions this month</h3>
            <span class="text-xs text-gray-400">{{ $sessions->count() }} session(s)</span>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Seeker</th>
                        <th>Concern</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Mode</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr>
                            <td class="font-medium">{{ $session['reference'] }}</td>
                            <td><a href="{{ route('helper.cases.show', ['id' => $session['id']]) }}" class="link">{{ $session['alias'] }}</a></td>
                            <td class="text-sm text-gray-500">{{ Illuminate\Support\Str::limit($session['concern'], 26) }}</td>
                            <td>{{ $session['date'] }}</td>
                            <td>{{ $session['time'] ?? '—' }}</td>
                            <td>{{ $session['mode'] }}</td>
                            <td><span class="status-badge {{ $session['status_class'] }}">{{ $session['status_label'] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-gray-400">No sessions scheduled this month.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection