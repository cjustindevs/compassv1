<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Analytics</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 18px 22px;
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.04); }
        .stat-number { font-size: 26px; font-weight: 800; color: #1F2937; }
        .stat-label { font-size: 12px; color: #6B7280; }
        .stat-sub { font-size: 11px; color: #9CA3AF; }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: #1F2937; }

        .filter-input {
            padding: 8px 12px;
            border-radius: 12px;
            border: 1.5px solid #E5E7EB;
            font-size: 13px;
            outline: none;
            background: white;
        }
        .filter-input:focus { border-color: #04A052; box-shadow: 0 0 0 3px rgba(4,160,82,0.08); }

        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
            color: white;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary:hover { background: #027039; }
        .btn-outline {
            background: white;
            color: #374151;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            border: 1.5px solid #E5E7EB;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-outline:hover { border-color: #04A052; color: #04A052; }

        .chart-box { position: relative; height: 280px; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: #374151; cursor: pointer; padding: 4px; }
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
            border-top: 1px solid #E5E7EB;
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #9CA3AF;
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: #04A052; }
        .bottom-nav .nav-item.active i { color: #04A052; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.moderator-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Analytics</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Program performance metrics and trends</p>
                </div>
            </div>
            <form method="GET" action="{{ route('moderator.analytics') }}" class="flex flex-wrap items-center gap-2">
                <input type="date" name="from" value="{{ $from }}" class="filter-input">
                <span class="text-xs text-gray-400">to</span>
                <input type="date" name="to" value="{{ $to }}" class="filter-input">
                <button type="submit" class="btn-outline"><i class="fas fa-filter mr-1"></i> Apply</button>
                <a href="{{ route('moderator.analytics.export', ['from' => $from, 'to' => $to]) }}" class="btn-primary">
                    <i class="fas fa-download"></i> Export CSV
                </a>
            </form>
        </div>

        <!-- Metrics -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="stat-label">Sessions Completed</div>
                <div class="stat-number text-emerald-600">{{ $metrics['sessions_completed'] }}</div>
                <div class="stat-sub">{{ $metrics['queue_served'] }} queue jobs served</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Session Length</div>
                <div class="stat-number text-blue-600">{{ $metrics['avg_length'] }}<span class="text-sm font-semibold"> min</span></div>
                <div class="stat-sub">Completed sessions only</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Competency Growth</div>
                <div class="stat-number {{ $metrics['competency_growth'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $metrics['competency_growth'] >= 0 ? '+' : '' }}{{ $metrics['competency_growth'] }}<span class="text-sm font-semibold"> pts</span>
                </div>
                <div class="stat-sub">Avg across helpers</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Rating</div>
                <div class="stat-number text-amber-500">{{ $metrics['avg_rating'] }}<span class="text-sm font-semibold"> /5</span></div>
                <div class="stat-sub">{{ $metrics['active_helpers'] }} helpers available</div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="card">
                <div class="card-header">
                    <h3>Risk Distribution</h3>
                    <span class="text-xs text-gray-400">{{ $from }} → {{ $to }}</span>
                </div>
                <div class="chart-box">
                    <canvas id="riskChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Competency Growth Trajectory</h3>
                    <span class="text-xs text-gray-400">Average overall score</span>
                </div>
                <div class="chart-box">
                    <canvas id="trajectoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card">
                <div class="card-header">
                    <h3>Skill Radar</h3>
                    <span class="text-xs text-gray-400">Team-wide averages</span>
                </div>
                <div class="chart-box">
                    <canvas id="radarChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Workload Distribution</h3>
                    <span class="text-xs text-gray-400">Active sessions per helper</span>
                </div>
                <div class="chart-box">
                    <canvas id="workloadChart"></canvas>
                </div>
            </div>
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('moderator.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i><span>Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item">
            <i class="fas fa-hourglass-half"></i><span>Queue</span>
        </a>
        <a href="{{ route('moderator.sessions') }}" class="nav-item">
            <i class="fas fa-comments"></i><span>Sessions</span>
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item">
            <i class="fas fa-exclamation-triangle"></i><span>Emergency</span>
        </a>
        <a href="{{ route('moderator.analytics') }}" class="nav-item active">
            <i class="fas fa-chart-line"></i><span>Analytics</span>
        </a>
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

            if (!window.Chart) return;

            const risk = @json($riskDistribution);
            const trajectory = @json($competencyTrajectory);
            const radar = @json($radar);
            const workload = @json($workload);

            new Chart(document.getElementById('riskChart'), {
                type: 'bar',
                data: {
                    labels: ['Low', 'Moderate', 'High', 'Emergency'],
                    datasets: [{
                        data: [risk.low, risk.moderate, risk.high, risk.emergency],
                        backgroundColor: ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
                        borderRadius: 8,
                        maxBarThickness: 56,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                    },
                },
            });

            new Chart(document.getElementById('trajectoryChart'), {
                type: 'line',
                data: {
                    labels: trajectory.labels,
                    datasets: [{
                        label: 'Avg competency score',
                        data: trajectory.values,
                        borderColor: '#04A052',
                        backgroundColor: 'rgba(4,160,82,0.1)',
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#04A052',
                        pointRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: false, suggestedMin: 50, suggestedMax: 100 },
                    },
                },
            });

            new Chart(document.getElementById('radarChart'), {
                type: 'radar',
                data: {
                    labels: radar.labels,
                    datasets: [{
                        label: 'Team average',
                        data: radar.values,
                        borderColor: '#04A052',
                        backgroundColor: 'rgba(4,160,82,0.15)',
                        pointBackgroundColor: '#04A052',
                        pointRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        r: { suggestedMin: 0, suggestedMax: 100, ticks: { display: false } },
                    },
                },
            });

            new Chart(document.getElementById('workloadChart'), {
                type: 'bar',
                data: {
                    labels: workload.map(w => w.helper),
                    datasets: [{
                        label: 'Active sessions',
                        data: workload.map(w => w.count),
                        backgroundColor: '#38C172',
                        borderRadius: 8,
                        maxBarThickness: 40,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                    },
                },
            });
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
