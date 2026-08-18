<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Adviser Calendar</title>

    @vite(['resources/js/app.js', 'resources/js/adviser-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
            --green-100: #D0F0D8;
            --green-500: #04A052;
            --green-600: #038A45;
            --green-700: #027039;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #163B2D;
            --red-500: #EF4444;
            --yellow-500: #F59E0B;
            --blue-500: #3B82F6;
        }

        body { background: #F8FBF9; }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(4,160,82,0.06);
            box-shadow: 4px 0 40px rgba(0,0,0,0.02);
            z-index: 100;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            flex-direction: column;
            padding: 24px 16px 20px;
        }
        .sidebar.closed { transform: translateX(-100%); }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
        }
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
            gap: 12px;
            flex-wrap: wrap;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }

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
            gap: 6px;
        }
        .btn-primary:hover { background: var(--green-700); transform: scale(1.02); }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            padding: 6px 14px;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover { background: var(--green-50); border-color: var(--green-500); color: var(--green-700); }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: var(--gray-700); cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--gray-200);
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--gray-400);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item.active { color: var(--green-500); }
        .bottom-nav .nav-item.active i { color: var(--green-500); }

        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); border: 1px solid var(--gray-200); border-radius: 16px; overflow: hidden; }
        .cal-day-head { padding: 10px 8px; text-align: center; font-size: 12px; font-weight: 700; color: var(--gray-500); background: var(--gray-50); border-bottom: 1px solid var(--gray-200); }
        .cal-cell { min-height: 110px; border-right: 1px solid var(--gray-100); border-bottom: 1px solid var(--gray-100); padding: 6px; cursor: pointer; transition: background 0.15s; background: white; }
        .cal-cell:nth-child(7n) { border-right: none; }
        .cal-cell:hover { background: var(--green-50); }
        .cal-cell.other-month { background: var(--gray-50); color: var(--gray-400); }
        .cal-cell .day-num { font-size: 12px; font-weight: 600; color: var(--gray-500); width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        .cal-cell.today .day-num { background: var(--green-500); color: white; }
        .cal-cell .event-chip {
            display: flex; align-items: center; gap: 4px;
            font-size: 10px; font-weight: 600; color: white;
            border-radius: 6px; padding: 2px 6px; margin-top: 3px;
            overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
        }
        .cal-cell .more { font-size: 10px; color: var(--gray-400); padding: 2px 6px; }

        .legend-dot { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

        .modal-backdrop {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.35);
            z-index: 300; align-items: center; justify-content: center; padding: 16px;
        }
        .modal-backdrop.active { display: flex; }
        .modal-box { background: white; border-radius: 20px; padding: 24px; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; }

        .form-input {
            width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200);
            border-radius: 12px; font-size: 13px; outline: none; transition: border-color 0.15s;
        }
        .form-input:focus { border-color: var(--green-500); }
        .form-label { font-size: 12px; font-weight: 600; color: var(--gray-600); margin-bottom: 6px; display: block; }

        .flash-success { background: var(--green-50); color: var(--green-700); border: 1px solid var(--green-100); border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .cal-cell { min-height: 70px; }
            .cal-cell .event-chip { display: none; }
            .cal-cell .more { display: none; }
            .cal-day-head { font-size: 10px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.adviser-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Calendar</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Sessions, evaluations, and team events
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success">
                <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            </div>
        @endif

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Sessions</span>
                    <span class="text-2xl">💬</span>
                </div>
                <div class="stat-number">{{ $sessions->count() }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Evaluations</span>
                    <span class="text-2xl">📊</span>
                </div>
                <div class="stat-number">{{ $evaluations->count() }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Events</span>
                    <span class="text-2xl">📅</span>
                </div>
                <div class="stat-number">{{ $customEvents->count() }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Days With Activity</span>
                    <span class="text-2xl">🗓️</span>
                </div>
                <div class="stat-number">{{ count($eventsByDate) }}</div>
            </div>
        </div>

        <!-- Calendar Card -->
        <div class="card">
            <div class="card-header">
                <div class="flex items-center gap-2">
                    <a href="{{ route('adviser.calendar', ['month' => $prevMonth->month, 'year' => $prevMonth->year]) }}" class="btn-outline"><i class="fas fa-chevron-left"></i></a>
                    <a href="{{ route('adviser.calendar', ['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}" class="btn-outline"><i class="fas fa-chevron-right"></i></a>
                    <a href="{{ route('adviser.calendar') }}" class="btn-outline"><i class="fas fa-calendar-day"></i> Today</a>
                </div>
                <h3 class="text-lg font-bold text-gray-800">{{ now()->setDate($year, $month, 1)->format('F Y') }}</h3>
                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <span><span class="legend-dot" style="background:#04A052;"></span> Session</span>
                    <span><span class="legend-dot" style="background:#3B82F6;"></span> Evaluation</span>
                    <span><span class="legend-dot" style="background:#F59E0B;"></span> Event</span>
                    <button class="btn-primary" id="openEventModal"><i class="fas fa-plus"></i> Add Event</button>
                </div>
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
                                        <i class="fas {{ $event['type'] === 'session' ? 'fa-comments' : ($event['type'] === 'evaluation' ? 'fa-star' : 'fa-calendar-alt') }}"></i>
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
        </div>

        <!-- Day Detail Panel -->
        <div class="card mt-6" id="dayPanel" style="display:none;">
            <div class="card-header">
                <h3 id="dayPanelTitle">Events</h3>
                <button class="btn-outline" id="closeDayPanel"><i class="fas fa-times"></i> Close</button>
            </div>
            <div id="dayPanelList" style="display:flex;flex-direction:column;"></div>
        </div>

        <!-- Add Event Modal -->
        <div class="modal-backdrop" id="eventModal">
            <div class="modal-box">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-800 text-lg">Add Calendar Event</h3>
                    <button class="btn-outline" id="closeEventModal"><i class="fas fa-times"></i></button>
                </div>

                <form method="POST" action="{{ route('adviser.calendar.event.store') }}">
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
                    <button type="submit" class="btn-primary w-full justify-center"><i class="fas fa-save"></i> Save Event</button>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('adviser.dashboard') }}" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
        <a href="{{ route('adviser.evaluations') }}" class="nav-item"><i class="fas fa-clipboard-list"></i><span>Evaluations</span></a>
        <a href="{{ route('adviser.referrals') }}" class="nav-item"><i class="fas fa-arrow-right"></i><span>Referrals</span></a>
        <a href="{{ route('adviser.helpers') }}" class="nav-item"><i class="fas fa-users"></i><span>Helpers</span></a>
        <a href="{{ route('adviser.notifications') }}" class="nav-item"><i class="fas fa-bell"></i><span>Alerts</span></a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const hamburger = document.getElementById('hamburgerBtn');

            function toggleSidebar() {
                sidebar.classList.toggle('closed');
                overlay.classList.toggle('active');
            }

            function closeSidebar() {
                sidebar.classList.add('closed');
                overlay.classList.remove('active');
            }

            hamburger.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 768) closeSidebar();
            });

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
                    dayPanelList.innerHTML = '<p class="text-gray-400 text-sm text-center py-8"><i class="fas fa-calendar-minus text-2xl block mb-2 opacity-50"></i>No events on this day</p>';
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

    @include('layouts.partials.pwa-banner')

</body>
</html>
