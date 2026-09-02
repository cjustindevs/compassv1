<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Reports</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-primary, #F8FBF9); }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .card {
            background: var(--bg-card, white);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border-color, #E5E7EB);
            box-shadow: var(--card-shadow, 0 4px 20px rgba(0,0,0,0.01));
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--text-primary, #1F2937); }

        table.report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.report-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted, #9CA3AF);
            padding: 10px 12px;
            border-bottom: 1px solid #E5E7EB;
        }
        table.report-table td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--border-light, #F3F4F6);
            color: var(--text-primary, #374151);
        }
        table.report-table tr:last-child td { border-bottom: none; }
        table.report-table tr:hover td { background: var(--bg-hover, #F9FAFB); }

        .benchmark-box {
            background: var(--bg-hover, #F9FAFB);
            border: 1px solid #F3F4F6;
            border-radius: 14px;
            padding: 16px;
            text-align: center;
        }
        .benchmark-box .value { font-size: 22px; font-weight: 800; color: var(--text-primary, #1F2937); }
        .benchmark-box .label { font-size: 11px; color: var(--text-muted, #9CA3AF); text-transform: uppercase; letter-spacing: 0.04em; margin-top: 2px; }

        .risk-badge { padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .risk-badge.emergency { background: #FEE2E2; color: #DC2626; }
        .risk-badge.high { background: #FEE2E2; color: #991B1B; }
        .risk-badge.moderate { background: #FEF3C7; color: #92400E; }
        .risk-badge.low { background: #DCFCE7; color: #166534; }

        .status-pill { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
        .status-pill.open { background: #FEE2E2; color: #DC2626; }
        .status-pill.resolved { background: #DCFCE7; color: #166534; }

        .growth-up { color: #059669; font-weight: 700; }
        .growth-down { color: #DC2626; font-weight: 700; }

        .filter-input {
            padding: 8px 12px;
            border-radius: 12px;
            border: 1.5px solid var(--border-color, #E5E7EB);
            font-size: 13px;
            outline: none;
            background: var(--bg-card, white);
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
            background: var(--bg-card, white);
            color: var(--text-primary, #374151);
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            border: 1.5px solid var(--border-color, #E5E7EB);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-outline:hover { border-color: #04A052; color: #04A052; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: var(--text-primary, #374151); cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--navbar-bg, rgba(255,255,255,0.94));
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
            color: var(--text-muted, #9CA3AF);
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
            .table-wrap { overflow-x: auto; }
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Reports</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Operations reports and exportable logs</p>
                </div>
            </div>
            <form method="GET" action="{{ route('moderator.reports') }}" class="flex flex-wrap items-center gap-2">
                <input type="date" name="from" value="{{ $from }}" class="filter-input">
                <span class="text-xs text-gray-400">to</span>
                <input type="date" name="to" value="{{ $to }}" class="filter-input">
                <button type="submit" class="btn-outline"><i class="fas fa-filter mr-1"></i> Apply</button>
                <a href="{{ route('moderator.reports.export', ['from' => $from, 'to' => $to]) }}" class="btn-primary">
                    <i class="fas fa-download"></i> Export CSV
                </a>
            </form>
        </div>

        <!-- Performance Benchmarks -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="benchmark-box">
                <p class="value">{{ $benchmarks['avg_response_min'] }}<span class="text-xs font-semibold text-gray-400"> min</span></p>
                <p class="label">Avg Response</p>
            </div>
            <div class="benchmark-box">
                <p class="value">{{ $benchmarks['avg_length_min'] }}<span class="text-xs font-semibold text-gray-400"> min</span></p>
                <p class="label">Avg Session</p>
            </div>
            <div class="benchmark-box">
                <p class="value">{{ $benchmarks['avg_rating'] }}</p>
                <p class="label">Avg Rating</p>
            </div>
            <div class="benchmark-box">
                <p class="value">{{ $benchmarks['utilization_pct'] }}<span class="text-xs font-semibold text-gray-400">%</span></p>
                <p class="label">Utilization</p>
            </div>
            <div class="benchmark-box">
                <p class="value">{{ $benchmarks['sessions_total'] }}</p>
                <p class="label">Sessions</p>
            </div>
        </div>

        <!-- Monthly Session Report -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Monthly Session Report</h3>
                <span class="text-xs text-gray-400">Volume · settings · outcomes</span>
            </div>
            <div class="table-wrap">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total</th>
                            <th>Chat</th>
                            <th>Voice</th>
                            <th>Completed</th>
                            <th>Cancelled / No-Show</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($monthly as $m)
                            <tr>
                                <td class="font-semibold text-gray-800">{{ $m['month'] }}</td>
                                <td>{{ $m['total'] }}</td>
                                <td>{{ $m['chat'] }}</td>
                                <td>{{ $m['voice'] }}</td>
                                <td class="text-emerald-600 font-semibold">{{ $m['completed'] }}</td>
                                <td class="text-red-500">{{ $m['cancelled'] }}</td>
                                <td>{{ $m['total'] > 0 ? round(($m['completed'] / $m['total']) * 100) . '%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-gray-400 py-8">No sessions in this period</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Referral Outcomes -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Referral Outcomes</h3>
                <span class="text-xs text-gray-400">{{ $from }} → {{ $to }}</span>
            </div>
            <div class="table-wrap">
                <table class="report-table">
                    <thead>
                        <tr><th>Status</th><th>Count</th><th>Share</th></tr>
                    </thead>
                    <tbody>
                        @php
                            $referralTotal = array_sum($referralOutcomes);
                            $statusColors = [
                                'pending_adviser' => 'text-amber-600',
                                'pending_professional' => 'text-blue-600',
                                'approved' => 'text-emerald-600',
                                'completed' => 'text-emerald-600',
                                'declined' => 'text-red-500',
                            ];
                        @endphp
                        @forelse($referralOutcomes as $status => $count)
                            <tr>
                                <td class="font-semibold text-gray-700 capitalize">{{ str_replace('_', ' ', $status) }}</td>
                                <td class="font-semibold {{ $statusColors[$status] ?? 'text-gray-700' }}">{{ $count }}</td>
                                <td class="text-gray-500">{{ $referralTotal > 0 ? round(($count / $referralTotal) * 100) . '%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-gray-400 py-8">No referrals in this period</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Helper Competency Growth -->
            <div class="card">
                <div class="card-header">
                    <h3>Helper Competency Growth</h3>
                    <span class="text-xs text-gray-400">First vs latest evaluation</span>
                </div>
                <div class="table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr><th>Helper</th><th>First</th><th>Latest</th><th>Growth</th></tr>
                        </thead>
                        <tbody>
                            @forelse($competencyGrowth as $row)
                                <tr>
                                    <td class="font-semibold text-gray-800">{{ $row['helper'] }}</td>
                                    <td>{{ $row['first_score'] !== null ? number_format($row['first_score'], 1) : '—' }}</td>
                                    <td>{{ $row['latest_score'] !== null ? number_format($row['latest_score'], 1) : '—' }}</td>
                                    <td>
                                        @if($row['growth'] > 0)
                                            <span class="growth-up">+{{ $row['growth'] }}</span>
                                        @elseif($row['growth'] < 0)
                                            <span class="growth-down">{{ $row['growth'] }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-gray-400 py-8">No competency data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Emergency Incident Log -->
            <div class="card">
                <div class="card-header">
                    <h3>Emergency Incident Log</h3>
                    <a href="{{ route('moderator.emergency') }}" class="text-xs text-[#04A052] hover:underline">Workspace</a>
                </div>
                <div class="table-wrap">
                    <table class="report-table">
                        <thead>
                            <tr><th>Case</th><th>Alias</th><th>Category</th><th>Risk</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse($incidentLog as $incident)
                                <tr>
                                    <td class="font-semibold text-gray-700">#{{ str_pad($incident->id, 3, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $incident->session?->seeker?->generated_alias ?? 'Unknown' }}</td>
                                    <td class="text-gray-600">{{ $incident->incident_category }}</td>
                                    <td><span class="risk-badge {{ $incident->risk_level }}">{{ ucfirst($incident->risk_level) }}</span></td>
                                    <td><span class="status-pill {{ $incident->status }}">{{ ucfirst(str_replace('_', ' ', $incident->status)) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-gray-400 py-8">No incidents in this period</td></tr>
                            @endforelse
                        </tbody>
                    </table>
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
        <a href="{{ route('moderator.analytics') }}" class="nav-item">
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
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
