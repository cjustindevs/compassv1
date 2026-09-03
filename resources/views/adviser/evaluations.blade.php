@extends('layouts.app')

@section('title', 'COMPASS – Pending Evaluations')

@push('styles')
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
            --gray-900: #111827;
            --red-500: #EF4444;
            --yellow-500: #F59E0B;
            --blue-500: #3B82F6;
        }

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

        .eval-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.2s;
        }
        .eval-item:hover { background: var(--gray-50); }
        .eval-item:last-child { border-bottom: none; }
        .eval-item .seeker-info .alias { font-weight: 600; color: var(--gray-800); }
        .eval-item .seeker-info .details { font-size: 13px; color: var(--gray-500); }
        .eval-item .seeker-info .concern { font-size: 12px; color: var(--gray-400); }
        .eval-item .actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .risk-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .risk-badge.low { background: var(--green-100); color: var(--green-700); }
        .risk-badge.moderate { background: #FEF3C7; color: #D97706; }
        .risk-badge.high { background: #FEE2E2; color: #DC2626; }
        .risk-badge.emergency { background: #FEE2E2; color: #DC2626; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

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

        .btn-skip {
            background: transparent;
            color: var(--gray-400);
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-skip:hover { color: var(--gray-600); text-decoration: underline; }
        @media (max-width: 768px) {
            .stat-number { font-size: 22px; }
            .grid-cols-3 { grid-template-columns: 1fr; }
            .eval-item { flex-direction: column; align-items: flex-start; gap: 10px; }
            .eval-item .actions { width: 100%; }
            .eval-item .actions .btn-primary,
            .eval-item .actions .btn-outline,
            .eval-item .actions .btn-skip { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .grid-cols-3 { grid-template-columns: 1fr; }
            .stat-number { font-size: 18px; }
            .card { padding: 16px; }
        }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Pending Evaluations</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Review session documentation and evaluate helper competency
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Pending Reviews</span>
                    <span class="text-2xl">📋</span>
                </div>
                <div class="stat-number">{{ $totalPending }}</div>
                <span class="text-xs text-gray-400">Sessions awaiting review</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">High Risk</span>
                    <span class="text-2xl">⚠️</span>
                </div>
                <div class="stat-number">{{ $highRiskPending }}</div>
                <span class="text-xs text-gray-400">Require immediate attention</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Feedback Given</span>
                    <span class="text-2xl">📝</span>
                </div>
                <div class="stat-number">{{ $feedbackCount }}</div>
                <span class="text-xs text-gray-400">Evaluations completed</span>
            </div>
        </div>

        <!-- Pending Evaluations -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Awaiting Review</h3>
                <span class="text-sm text-gray-400">{{ $totalPending }} sessions</span>
            </div>

            @if($pendingReports->isNotEmpty())
                @foreach($pendingReports as $report)
                    <div class="eval-item">
                        <div class="seeker-info">
                            <div class="alias">
                                {{ $report->session->seeker->generated_alias ?? 'Anonymous' }}
                                <span class="risk-badge {{ $report->session->risk_level ?? 'low' }}">
                                    {{ ucfirst($report->session->risk_level ?? 'Low') }}
                                </span>
                            </div>
                            <div class="details">
                                {{ $report->session->helper->first_name ?? 'Unknown' }} {{ $report->session->helper->last_name ?? '' }}
                                · {{ $report->created_at->format('M d, Y') }}
                                · {{ $report->session->session_type ?? 'Chat' }}
                            </div>
                            <div class="concern">
                                Concern: {{ $report->session->concern->concern_name ?? 'General' }}
                                @if($report->referral_recommended)
                                    <span class="text-yellow-600 font-medium ml-2">⚡ Referral Recommended</span>
                                @endif
                            </div>
                        </div>
                        <div class="actions">
                            <a href="{{ route('adviser.evaluate', $report->id) }}" class="btn-primary">
                                <i class="fas fa-check mr-1"></i> Evaluate
                            </a>
                            <a href="{{ route('adviser.session.show', $report->session_id) }}" class="btn-outline">
                                <i class="fas fa-eye mr-1"></i> View Session
                            </a>
                            <form method="POST" action="{{ route('adviser.evaluations.skip', $report->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn-skip">
                                    <i class="fas fa-forward mr-1"></i> Skip
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-check-circle text-5xl mb-3 block text-green-500"></i>
                    <p class="text-lg font-medium text-gray-600">All caught up!</p>
                    <p class="text-sm">No pending evaluations to review</p>
                </div>
            @endif
        </div>

        <!-- Recently Completed -->
        @if($completedReports->isNotEmpty())
            <div class="card">
                <div class="card-header">
                    <h3>Recently Completed</h3>
                    <span class="text-sm text-gray-400">Last 10 reviews</span>
                </div>
                @foreach($completedReports as $report)
                    <div class="eval-item">
                        <div class="seeker-info">
                            <div class="alias text-gray-500">
                                {{ $report->session->seeker->generated_alias ?? 'Anonymous' }}
                                <span class="text-xs text-green-600 font-medium ml-2">✓ Reviewed</span>
                            </div>
                            <div class="details text-gray-400">
                                {{ $report->session->helper->first_name ?? 'Unknown' }}
                                · {{ $report->updated_at->format('M d, Y') }}
                            </div>
                        </div>
                        <span class="text-xs text-gray-400">{{ $report->updated_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>
</div>
@endsection
