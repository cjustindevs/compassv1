<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Reports</title>

    @vite(['resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
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
        }

        body { background: #F8FBF9; }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

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

        .chart-box { position: relative; height: 280px; }

        .period-btn {
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-500);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .period-btn:hover { border-color: var(--green-500); color: var(--green-700); }
        .period-btn.active {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            border-color: transparent;
        }

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

        .report-table { width: 100%; border-collapse: collapse; }
        .report-table thead th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-400);
            padding: 10px 12px;
            border-bottom: 1px solid var(--gray-100);
        }
        .report-table tbody td {
            padding: 12px;
            border-bottom: 1px solid var(--gray-100);
            font-size: 13px;
            color: var(--gray-600);
        }
        .report-table tbody tr:last-child td { border-bottom: none; }

        .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray-700);
            cursor: pointer;
            padding: 4px;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.25);
            z-index: 99;
        }
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
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--green-500); }
        .bottom-nav .nav-item.active i { color: var(--green-500); }

        @media print {
            .sidebar, .bottom-nav, .hamburger, .no-print { display: none !important; }
            .main-content { margin-left: 0; padding: 0; }
            .card { box-shadow: none; border: 1px solid #ddd; page-break-inside: avoid; }
            .chart-box { height: 220px; }
        }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .stat-number { font-size: 22px; }
            .report-table thead { display: none; }
            .report-table, .report-table tbody, .report-table tr, .report-table td { display: block; width: 100%; }
            .report-table tbody tr { border: 1px solid var(--gray-100); border-radius: 12px; margin-bottom: 10px; }
            .report-table tbody td { border: none; padding: 8px 12px; }
        }
        @media (max-width: 480px) {
            .stat-number { font-size: 18px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.professional-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Performance Reports</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Track referrals, outcomes, and response times
                    </p>
                </div>
            </div>
            <div class="flex gap-2 no-print">
                <a href="{{ route('professional.reports.export', ['period' => $period]) }}" class="btn-outline">
                    <i class="fas fa-file-csv mr-1"></i> Export CSV
                </a>
                <button class="btn-primary" onclick="window.print()">
                    <i class="fas fa-file-pdf mr-1"></i> Save as PDF
                </button>
            </div>
        </div>

        <!-- Period Filter -->
        <div class="flex items-center gap-2 mb-6 flex-wrap no-print">
            <span class="text-sm font-medium text-gray-500 mr-1">Period:</span>
            @foreach(['weekly' => 'Last 7 Days', 'monthly' => 'Last 30 Days', 'quarterly' => 'Last 90 Days', 'yearly' => 'Last 12 Months'] as $value => $label)
                <a href="{{ route('professional.reports', ['period' => $value]) }}"
                   class="period-btn {{ $period === $value ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <!-- Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Referrals</span>
                    <span class="text-2xl">📋</span>
                </div>
                <div class="stat-number">{{ $totalReferrals }}</div>
                <span class="text-xs text-gray-400">In selected period</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Acceptance Rate</span>
                    <span class="text-2xl">✅</span>
                </div>
                <div class="stat-number">{{ $acceptanceRate }}%</div>
                <span class="text-xs text-gray-400">{{ $accepted }} accepted of {{ $totalReferrals }}</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Avg Cases Completed</span>
                    <span class="text-2xl">🏁</span>
                </div>
                <div class="stat-number">{{ $completed }}</div>
                <span class="text-xs text-gray-400">Completed in period</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Avg Response Time</span>
                    <span class="text-2xl">⏱️</span>
                </div>
                <div class="stat-number">
                    @if($avgResponseHours > 48)
                        {{ number_format($avgResponseHours / 24, 1) }}<span class="text-base">d</span>
                    @else
                        {{ $avgResponseHours }}<span class="text-base">h</span>
                    @endif
                </div>
                <span class="text-xs text-gray-400">Assignment to decision</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Referral Trends -->
            <div class="card">
                <div class="card-header">
                    <h3>Referral Trends <span class="text-xs font-normal text-gray-400">(last 6 months)</span></h3>
                </div>
                <div class="chart-box">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Case Outcomes -->
            <div class="card">
                <div class="card-header">
                    <h3>Case Outcomes</h3>
                </div>
                <div class="chart-box">
                    <canvas id="outcomesChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Intervention Breakdown -->
            <div class="card lg:col-span-1">
                <div class="card-header">
                    <h3>Interventions</h3>
                </div>
                <div class="chart-box" style="height: 220px;">
                    <canvas id="interventionChart"></canvas>
                </div>
            </div>

            <!-- Recent Referrals in Period -->
            <div class="card lg:col-span-2">
                <div class="card-header">
                    <h3>Recent Referrals</h3>
                    <span class="text-sm text-gray-400">{{ $recentReferrals->count() }} in period</span>
                </div>

                @if($recentReferrals->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Seeker</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentReferrals as $referral)
                                    <tr>
                                        <td>#{{ $referral->id }}</td>
                                        <td class="font-semibold text-gray-700">{{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}</td>
                                        <td class="capitalize">{{ $referral->priority_level }}</td>
                                        <td class="capitalize">{{ str_replace('_', ' ', $referral->status) }}</td>
                                        <td>{{ $referral->created_at->format('M d, Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-10 text-gray-400">
                        <p class="text-lg font-medium text-gray-600">No referrals in this period</p>
                        <p class="text-sm">Try a different date range</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Data-driven care, human-centered outcomes.
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('professional.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('professional.referrals') }}" class="nav-item">
            <i class="fas fa-clipboard-list"></i>
            <span>Referrals</span>
        </a>
        <a href="{{ route('professional.cases') }}" class="nav-item">
            <i class="fas fa-folder-open"></i>
            <span>Cases</span>
        </a>
        <a href="{{ route('professional.reports') }}" class="nav-item active">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <a href="{{ route('professional.profile') }}" class="nav-item">
            <i class="fas fa-user-circle"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) closeSidebar();
            });

            // Referral trends chart
            const trendLabels = @json(array_column($monthlyTrends, 'month'));
            const trendReferrals = @json(array_column($monthlyTrends, 'referrals'));
            const trendCompleted = @json(array_column($monthlyTrends, 'completed'));

            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [
                        {
                            label: 'Referrals',
                            data: trendReferrals,
                            borderColor: '#04A052',
                            backgroundColor: 'rgba(4,160,82,0.08)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#04A052'
                        },
                        {
                            label: 'Completed',
                            data: trendCompleted,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59,130,246,0.08)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#3B82F6'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });

            // Case outcomes donut
            const outcomes = @json($outcomes);
            new Chart(document.getElementById('outcomesChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Closed', 'Declined', 'Active'],
                    datasets: [{
                        data: [outcomes.completed, outcomes.closed, outcomes.declined, outcomes.active],
                        backgroundColor: ['#04A052', '#9CA3AF', '#EF4444', '#3B82F6'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
                                    return context.label + ': ' + context.parsed + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // Intervention type breakdown
            const interventions = @json($interventions);
            const keys = Object.keys(interventions).map(k => k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
            const values = Object.values(interventions);

            if (keys.length > 0) {
                new Chart(document.getElementById('interventionChart'), {
                    type: 'bar',
                    data: {
                        labels: keys,
                        datasets: [{
                            label: 'Sessions',
                            data: values,
                            backgroundColor: 'rgba(4,160,82,0.7)',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            }
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>