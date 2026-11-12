@extends('layouts.app')

@section('title', 'COMPASS – Adviser Calendar')

@push('styles')
<style>
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); border: 1px solid #E5E7EB; border-radius: 16px; overflow: hidden; }
        .cal-day-head { padding: 10px 8px; text-align: center; font-size: 12px; font-weight: 700; color: #6B7280; background: #F9FAFB; border-bottom: 1px solid #E5E7EB; }
        .cal-cell { min-height: 110px; border-right: 1px solid #F3F4F6; border-bottom: 1px solid #F3F4F6; padding: 6px; cursor: pointer; transition: background 0.15s; background: white; }
        .cal-cell:nth-child(7n) { border-right: none; }
        .cal-cell:hover { background: #EAF8F0; }
        .cal-cell.other-month { background: #F9FAFB; color: #9CA3AF; }
        .cal-cell .day-num { font-size: 12px; font-weight: 600; color: #6B7280; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        .cal-cell.today .day-num { background: #04A052; color: white; }
        .cal-cell .event-chip {
            display: flex; align-items: center; gap: 4px;
            font-size: 11px; font-weight: 600; color: white;
            border-radius: 6px; padding: 2px 6px; margin-top: 3px;
            overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
        }
        .cal-cell .more { font-size: 11px; color: #9CA3AF; padding: 2px 6px; }
        .cal-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .cal-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 12px; font-size: 12px; color: #6B7280; }
        .legend-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

        .modal-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.35);
            z-index: 300; align-items: center; justify-content: center; padding: 16px;
        }
        .modal-backdrop.active { display: flex; }
        .modal-box { background: white; border-radius: 20px; padding: 24px; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; }

        .form-input {
            width: 100%; padding: 10px 14px; border: 1px solid #E5E7EB;
            border-radius: 12px; font-size: 14px; outline: none; transition: border-color 0.15s;
        }
        .form-input:focus { border-color: #04A052; }
        .form-label { font-size: 12px; font-weight: 600; color: #4B5563; margin-bottom: 6px; display: block; }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        @media (max-width: 768px) {
            .cal-cell { min-height: 70px; }
            .cal-cell .event-chip { display: none; }
            .cal-cell .more { display: none; }
            .cal-day-head { font-size: 11px; }
        }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <x-ui.page-header title="Calendar" description="Sessions, evaluations, and team events">
            <x-slot:actions>
                <button type="button" class="btn-primary" id="openEventModal"><x-ui-icon name="plus" /> Add event</button>
            </x-slot:actions>
        </x-ui.page-header>

        @if(session('success'))
            <div class="flash-success" role="status">
                 {{ session('success') }}
            </div>
        @endif

        <x-ui.kpi-grid>
            <x-ui.kpi label="Sessions" :value="$sessions->count()" tone="green" />
            <x-ui.kpi label="Evaluations" :value="$evaluations->count()" tone="blue" />
            <x-ui.kpi label="Events" :value="$customEvents->count()" tone="amber" />
            <x-ui.kpi label="Days With Activity" :value="count($eventsByDate)" tone="neutral" />
        </x-ui.kpi-grid>

        <x-ui.card :title="now()->setDate($year, $month, 1)->format('F Y')">
            <x-slot:actions>
                <div class="cal-tools">
                    <a href="{{ route('adviser.calendar', ['month' => $prevMonth->month, 'year' => $prevMonth->year]) }}" class="btn-secondary" aria-label="Previous month" title="Previous month"><x-ui-icon name="chevron-left" /></a>
                    <a href="{{ route('adviser.calendar', ['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}" class="btn-secondary" aria-label="Next month" title="Next month"><x-ui-icon name="chevron-right" /></a>
                    <a href="{{ route('adviser.calendar') }}" class="btn-secondary"><x-ui-icon name="calendar" /> Today</a>
                </div>
            </x-slot:actions>
            <div class="cal-legend">
                <span><span class="legend-dot" style="background:#04A052;"></span> Session</span>
                <span><span class="legend-dot" style="background:#3B82F6;"></span> Evaluation</span>
                <span><span class="legend-dot" style="background:#F59E0B;"></span> Event</span>
            </div>

            <div class="cal-grid" id="calGrid">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                    <div class="cal-day-head">{{ $d }}</div>
                @endforeach

                @foreach($weeks as $week)
                    @foreach($week as $cell)
                        @if($cell)
                            <div class="cal-cell {{ $cell['date'] === now()->format('Y-m-d') ? 'today' : '' }}"
                                 data-date="{{ $cell['date'] }}"
                                 data-day="{{ $cell['day'] }}">
                                <div class="day-num">{{ $cell['day'] }}</div>
                                @foreach(array_slice($cell['events'], 0, 3) as $event)
                                    <div class="event-chip" style="background:{{ $event['color'] }}">

                                        {{ $event['title'] }}
                                    </div>
                                @endforeach
                                @if(count($cell['events']) > 3)
                                    <div class="more">+{{ count($cell['events']) - 3 }} more</div>
                                @endif
                            </div>
                        @else
                            <div class="cal-cell other-month"></div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </x-ui.card>

        <!-- Day Detail Panel -->
        <x-ui.card id="dayPanel" class="mt-6" style="display:none;">
            <div class="ui-card-head">
                <h2 class="ui-card-title" id="dayPanelTitle">Events</h2>
                <button type="button" class="btn-secondary" id="closeDayPanel"><x-ui-icon name="close" /> Close</button>
            </div>
            <div id="dayPanelList" style="display:flex;flex-direction:column;"></div>
        </x-ui.card>

        <!-- Add Event Modal -->
        <div class="modal-backdrop" id="eventModal">
            <div class="modal-box">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="ui-card-title">Add Calendar Event</h2>
                    <button type="button" class="btn-secondary" id="closeEventModal" aria-label="Close dialog" title="Close dialog"><x-ui-icon name="close" /></button>
                </div>

                <form class="form-maximized" method="POST" action="{{ route('adviser.calendar.event.store') }}">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" required maxlength="255" class="form-input" placeholder="e.g. Weekly supervision meeting">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-input" placeholder="Optional notes"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="form-label">Date *</label>
                            <input type="date" name="event_date" required class="form-input" id="eventDateInput">
                        </div>
                        <div>
                            <label class="form-label">Type *</label>
                            <select name="event_type" class="form-input">
                                <option value="training">Training</option>
                                <option value="meeting">Meeting</option>
                                <option value="session">Session</option>
                                <option value="evaluation">Evaluation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-input">
                        </div>
                    </div>
                    <div class="mb-6">
                        <label class="form-label">Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="color" value="#04A052" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer">
                            <span class="text-xs text-gray-400">Pick a highlight color for this event</span>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary w-full justify-center"><x-ui-icon name="save" /> Save Event</button>
                </form>
            </div>
        </div>
</div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
// ── Event Modal ──
            const eventModal = document.getElementById('eventModal');
            document.getElementById('openEventModal').addEventListener('click', function () {
                eventModal.classList.add('active');
            });
            document.getElementById('closeEventModal').addEventListener('click', function () {
                eventModal.classList.remove('active');
            });
            eventModal.addEventListener('click', function (e) {
                if (e.target === eventModal) eventModal.classList.remove('active');
            });

            // ── Events by date (embedded JSON) ──
            const eventsByDate = @json($eventsByDate);

            // ── Day panel ──
            const dayPanel = document.getElementById('dayPanel');
            const dayPanelTitle = document.getElementById('dayPanelTitle');
            const dayPanelList = document.getElementById('dayPanelList');
            const today = new Date().toISOString().slice(0, 10);

            function renderDay(dateStr) {
                const events = eventsByDate[dateStr] || [];
                const label = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                });
                dayPanelTitle.textContent = label;
                dayPanelList.innerHTML = '';

                if (events.length === 0) {
                    dayPanelList.innerHTML = '<p class="text-gray-400 text-sm text-center py-8"><x-ui-icon name="calendar" class="text-2xl block mb-2 opacity-50" />No events on this day</p>';
                } else {
                    events.forEach(function (ev) {
                        const row = document.createElement('div');
                        row.className = 'flex items-start gap-3 p-3 rounded-xl mb-2';
                        row.style.background = '#F9FAFB';
                        row.innerHTML =
                            '<div style="width:8px;height:8px;border-radius:50%;background:' + ev.color + ';margin-top:6px;flex-shrink:0;"></div>' +
                            '<div class="flex-1 min-w-0">' +
                                '<p class="font-semibold text-gray-800 text-sm">' + (ev.time ? '<span class="text-gray-400 font-normal mr-1">' + ev.time + '</span>' : '') + ev.title + '</p>' +
                                '<p class="text-xs text-gray-500 mt-0.5">' + ev.detail + '</p>' +
                            '</div>';
                        dayPanelList.appendChild(row);
                    });
                }
                dayPanel.style.display = 'block';
                dayPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            document.querySelectorAll('.cal-cell[data-date]').forEach(function (cell) {
                cell.addEventListener('click', function () {
                    renderDay(cell.getAttribute('data-date'));
                });
            });

            document.getElementById('closeDayPanel').addEventListener('click', function () {
                dayPanel.style.display = 'none';
            });

            // Open the event modal prefilled with the clicked date
            document.getElementById('eventDateInput').value = today;
        });
    </script>
@endsection
