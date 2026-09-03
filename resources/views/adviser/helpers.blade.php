@extends('layouts.app')

@section('title', 'COMPASS – Manage Helpers')

@push('styles')
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

        .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-badge.online { background: #DCFCE7; color: #166534; }
        .status-badge.available { background: #E0F2FE; color: #075985; }
        .status-badge.offline { background: #FEF3C7; color: #92400E; }
        .status-badge.inactive { background: #F3F4F6; color: #6B7280; }

        .level-tag { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .level-tag.expert { background: #DCFCE7; color: #166534; }
        .level-tag.advanced { background: #DBEAFE; color: #1E40AF; }
        .level-tag.intermediate { background: #FEF3C7; color: #92400E; }
        .level-tag.beginner { background: #FEE2E2; color: #991B1B; }
        .level-tag.trainee { background: #F3F4F6; color: #6B7280; }

        .filter-pill {
            padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
            border: 1px solid var(--gray-200); background: white; color: var(--gray-500);
            text-decoration: none; transition: all 0.15s; display: inline-flex; align-items: center; gap: 4px;
        }
        .filter-pill:hover { border-color: var(--green-500); color: var(--green-700); }
        .filter-pill.active { background: var(--green-50); border-color: var(--green-500); color: var(--green-700); }

        .helper-table { width: 100%; border-collapse: collapse; }
        .helper-table th {
            text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.04em; color: var(--gray-400); padding: 10px 12px; border-bottom: 1px solid var(--gray-200);
        }
        .helper-table td { padding: 14px 12px; border-bottom: 1px solid var(--gray-100); font-size: 13px; vertical-align: middle; }
        .helper-table tr:last-child td { border-bottom: none; }
        .helper-table tr:hover td { background: var(--gray-50); }

        .mini-select {
            padding: 6px 10px; border: 1px solid var(--gray-200); border-radius: 10px;
            font-size: 12px; outline: none; background: white; color: var(--gray-700); max-width: 160px;
        }
        .mini-select:focus { border-color: var(--green-500); }

        .flash-success { background: var(--green-50); color: var(--green-700); border: 1px solid var(--green-100); border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        @media (max-width: 768px) {
            .helper-table { min-width: 720px; }
            .table-wrap { overflow-x: auto; }
        }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Manage Helpers</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Monitor performance, availability, and supervision
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Helpers</span>
                    <span class="text-2xl">👥</span>
                </div>
                <div class="stat-number">{{ $totalHelpers }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Available</span>
                    <span class="text-2xl">🟢</span>
                </div>
                <div class="stat-number">{{ $availableHelpers }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Advanced+ Competency</span>
                    <span class="text-2xl">⭐</span>
                </div>
                <div class="stat-number">{{ $highCompetency }}</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="card mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2 flex-wrap">
                    @php
                        $filters = ['all' => 'All', 'online' => 'Online', 'available' => 'Available', 'offline' => 'Offline', 'inactive' => 'Inactive'];
                    @endphp
                    @foreach($filters as $key => $label)
                        <a href="{{ route('adviser.helpers', ['status' => $key]) }}"
                           class="filter-pill {{ $statusFilter === $key ? 'active' : '' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('adviser.helpers.export', ['status' => $statusFilter]) }}" class="btn-outline">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- Helper List -->
        <div class="card">
            <div class="card-header">
                <h3>Helpers ({{ $helperData->count() }})</h3>
            </div>

            @if($helperData->isNotEmpty())
                <div class="table-wrap">
                    <table class="helper-table">
                        <thead>
                            <tr>
                                <th>Helper</th>
                                <th>Status</th>
                                <th>Level</th>
                                <th>Score</th>
                                <th>Active Cases</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($helperData as $row)
                                @php $helper = $row['helper']; @endphp
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-[#04A052] text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                                                {{ $row['initials'] }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-800 truncate">{{ $helper->getFullNameAttribute() }}</p>
                                                <p class="text-xs text-gray-400 truncate">{{ $helper->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="status-badge {{ $row['status'] }}">{{ ucfirst($row['status']) }}</span></td>
                                    <td><span class="level-tag {{ strtolower($row['level']) }}">{{ $row['level'] }}</span></td>
                                    <td class="font-semibold text-gray-700">
                                        {{ $row['competency_score'] > 0 ? $row['competency_score'] . ' / 5' : '—' }}
                                    </td>
                                    <td>
                                        <span class="font-semibold text-gray-700">{{ $row['active_cases'] }}</span>
                                        <span class="text-xs text-gray-400">active</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('adviser.helper.show', ['id' => $helper->id]) }}" class="btn-outline">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-users text-4xl mb-3 block opacity-50"></i>
                    <p class="text-lg font-medium text-gray-600">No helpers found</p>
                    <p>Try a different status filter.</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>
</div>
    <!-- Bottom Navigation -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
});
    </script>
@endsection
