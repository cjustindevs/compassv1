<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS â€“ Reports</title>

    @vite(['resources/js/app.js'])
    @vite('resources/js/dashboard-analytics.js')
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


        @media print {
            .sidebar, .hamburger, .no-print { display: none !important; }
            .main-content { margin-left: 0; padding: 0; }
            .card { box-shadow: none; border: 1px solid #ddd; page-break-inside: avoid; }
            .chart-box { height: 220px; }
        }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
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
    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
</head>
<body class="compass-compact">

    @include('layouts.partials.professional-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn" aria-label="Open navigation" aria-controls="sidebar">
                    <x-ui-icon name="menu"  />
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Performance Reports</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Review authorized referral records and recorded outcomes
                    </p>
                </div>
            </div>
            <div class="flex gap-2 no-print">
                <a href="{{ route('professional.reports.export', $dates + $filters) }}" class="btn-outline">
                    <x-ui-icon name="file-spreadsheet" class="mr-1" /> Export CSV
                </a>
                <button class="btn-primary" onclick="window.print()">
                    <x-ui-icon name="file-text" class="mr-1" /> Print / Save PDF
                </button>
            </div>
        </div>

        <section class="analytics-section" aria-label="Professional referral report">
            <form method="GET" action="{{ route('professional.reports') }}" class="analytics-period-form no-print">
                <label>From (Philippine Time)<input type="date" name="from" value="{{ $dates['from'] }}" required></label>
                <label>To (Philippine Time)<input type="date" name="to" value="{{ $dates['to'] }}" max="{{ now('Asia/Manila')->toDateString() }}" required></label>
                <label>Date basis<select name="activity">@foreach(['received'=>'Received','completed'=>'Completed','closed'=>'Closed'] as $key=>$label)<option value="{{ $key }}" @selected($filters['activity'] === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="analytics-report-status">Referral status<select name="status"><option value="">All authorized statuses</option>@foreach(App\Services\ProfessionalAnalytics::STATUSES as $state)<option value="{{ $state }}" @selected(($filters['status'] ?? '') === $state)>{{ Str::headline($state) }}</option>@endforeach</select></label>
                <button class="analytics-button analytics-button-primary" type="submit">Apply filters</button>
                <a class="analytics-button" href="{{ route('professional.reports') }}">Reset</a>
                <a class="analytics-button" href="{{ route('professional.dashboard', $dates) }}">View dashboard charts</a>
            </form>
            @if($errors->any())<div class="analytics-feedback" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <p class="analytics-period-caption">{{ $dates['from'] }} – {{ $dates['to'] }} · Philippine Time. The status filter applies to all measures and records. Each measure uses its named event date; the table uses your selected date basis.</p>
            <dl class="analytics-measures">
                <div><dt>Total Referrals Received</dt><dd>{{ number_format($analytics['received']['total']) }}</dd><small>Notification date; legacy creation fallback</small></div>
                <div><dt>Completed Referrals</dt><dd>{{ number_format($analytics['completed']['total']) }}</dd><small>Completion date; may have been received earlier</small></div>
                <div><dt>Closed Referrals</dt><dd>{{ number_format($analytics['closed']) }}</dd><small>Closed status and recorded closure date</small></div>
                <div><dt>Average Acceptance Time</dt><dd>{{ $analytics['response_hours'] === null ? 'No data' : $analytics['response_hours'].' h' }}</dd><small>{{ $analytics['response_samples'] }} valid acceptances in period</small></div>
            </dl>
            <details class="analytics-definitions"><summary>Calculations and data coverage</summary>
                <p>Received uses professional notification dates. {{ $analytics['legacy_received'] }} records in this period use legacy creation dates because no notification timestamp exists. Completion uses completed_at, or closed_date only for legacy completed records. Closed-only records remain separate.</p>
                <p>Acceptance Rate: {{ $analytics['acceptance_rate'] === null ? 'No data' : $analytics['acceptance_rate'].'%' }}. {{ $analytics['accepted'] }} of {{ $analytics['received']['total'] }} referrals received in this period have a recorded acceptance. This is a received-referral cohort, not a rate of decisions made in this period.</p>
                <p>Acceptance time measures professional notification to recorded acceptance; missing, negative, and future intervals are excluded. {{ $analytics['missing_completion_dates'] }} historical finished records lack usable completion dates and are excluded from completion totals. Archived cases remain in authorized history.</p>
                <p>Charts and Reports use the same sources. CSV includes all matching table records and the same filters. Printing includes only the current page; use CSV for the full filtered dataset.</p>
            </details>
            <div class="card analytics-report-table">
                <div class="card-header"><h3>Referral records</h3><span class="text-sm text-gray-500">{{ number_format($records->total()) }} matching records</span></div>
                <p class="analytics-period-caption">Date basis: {{ ucfirst($filters['activity']) }} · Status: {{ isset($filters['status']) && $filters['status'] ? Str::headline($filters['status']) : 'All authorized statuses' }}</p>
                <div class="analytics-table-scroll" role="region" aria-label="Filtered referral records" tabindex="0">
                    <table><thead><tr><th scope="col">Reference</th><th scope="col">Seeker alias</th><th scope="col">Priority</th><th scope="col">Status</th><th scope="col">Received</th><th scope="col">Completed</th><th scope="col">Closed</th><th scope="col" class="no-print">Action</th></tr></thead><tbody>
                        @forelse($records as $referral)
                            @php
                                $receivedAt = $referral->professional_notified_at ?? $referral->created_at;
                                $completedAt = $referral->completed_at ?? ($referral->status === 'completed' ? $referral->closed_date : null);
                                $closedAt = $referral->status === 'closed' ? $referral->closed_date : null;
                            @endphp
                            <tr><td>#{{ $referral->id }}</td><td>{{ $referral->session?->seeker?->generated_alias ?? 'Anonymous' }}</td><td>{{ ucfirst($referral->priority_level) }}</td><td>{{ Str::headline($referral->status) }}</td><td>{{ $receivedAt?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Unrecorded' }}@if(!$referral->professional_notified_at)<br><small>Legacy creation date</small>@endif</td><td>{{ $completedAt?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Unrecorded' }}</td><td>{{ $closedAt?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Unrecorded' }}</td><td class="no-print"><a class="analytics-button" href="{{ route('professional.referral.show', $referral->id) }}">View</a></td></tr>
                        @empty
                            <tr><td colspan="8">No referrals match these filters. Try a different date range or status.</td></tr>
                        @endforelse
                    </tbody></table>
                </div>
                <div class="analytics-report-footer"><span>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} records</span><div class="no-print">{{ $records->links() }}</div></div>
            </div>
        </section>
        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">

            Data-driven care, human-centered outcomes.
        </div>

    </main>

    <!-- Bottom Navigation -->



    @include('layouts.partials.pwa-banner')

</body>
</html>
