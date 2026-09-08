@extends('layouts.helper')

@section('title', 'Dashboard')

@section('heading', 'Good ' . (now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening')) . ', ' . ($helper?->first_name ?? 'Helper'))

@section('subheading', 'Here is what is happening with your sessions today.')

@section('content')
    <p class="mb-4">Sessions completed: <strong>{{ $helper?->completedSessions()->count() ?? 0 }}</strong></p>

    @if(isset($helperProfileMissing) && $helperProfileMissing)
        <div class="card mb-6">
            <div class="empty-state">
                <i class="fas fa-user-plus"></i>
                <h3 style="font-size:18px;font-weight:700;color:var(--gray-800);margin-bottom:8px;">Your helper profile is not complete</h3>
                <p style="margin-bottom:16px;">Your account is registered as a helper, but a helper profile has not been created yet. Please contact your adviser or an administrator to complete your onboarding.</p>
                <a href="{{ route('helper.settings') }}" class="btn btn-secondary">Go to Settings</a>
            </div>
        </div>
    @else

    <!-- ─── STATS ─── -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Total Sessions</span>
                <span class="stat-icon">📊</span>
            </div>
            <div class="stat-number">{{ $stats['total_sessions'] }}</div>
            <span class="text-xs text-gray-400">All time</span>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Active Sessions</span>
                <span class="stat-icon">🟢</span>
            </div>
            <div class="stat-number">{{ $stats['active_sessions'] }}</div>
            <span class="text-xs text-gray-400">Currently ongoing</span>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Pending Requests</span>
                <span class="stat-icon">📋</span>
            </div>
            <div class="stat-number">{{ $stats['pending_requests'] }}</div>
            <span class="text-xs text-gray-400">Awaiting your action</span>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Competency Score</span>
                <span class="stat-icon">⭐</span>
            </div>
            <div class="stat-number">{{ $stats['competency_score'] }}%</div>
            <span class="text-xs text-gray-400">{{ optional($competency)->level_label ?: 'Not yet evaluated' }}</span>
        </div>
    </div>

    <!-- ─── EMERGENCY ALERT ─── -->
    @if(isset($emergencyCases) && count($emergencyCases) > 0)
        <div class="emergency-alert mb-6">
            <span class="alert-text">
                <i class="fas fa-exclamation-triangle"></i>
                Emergency case: {{ $emergencyCases[0]['alias'] }} ({{ $emergencyCases[0]['incident'] }}) - {{ $emergencyCases[0]['time'] }}
            </span>
            <a href="{{ route('helper.cases.show', ['id' => $emergencyCases[0]['id']]) }}" class="btn-escalate" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-eye"></i> Review now
            </a>
        </div>
    @endif

    <!-- ─── ACTIVE SESSION ─── -->
    @if(isset($activeSessionData) && $activeSessionData)
        <div class="card mb-6">
            <div class="card-header">
                <h3>Active Session</h3>
                <span class="text-xs text-green-600 font-medium">🟢 Live</span>
            </div>
            <div class="active-session-card">
                    <div class="session-info">
                        <div class="alias">{{ $activeSessionData['alias'] }}</div>
                        <div class="mode">{{ $activeSessionData['mode'] }} · {{ $activeSessionData['elapsed'] }} elapsed</div>
                        @if(($activeSessionData['elapsed_minutes'] ?? 0) >= 85)
                            <div class="text-xs {{ $activeSessionData['elapsed_minutes'] >= 90 ? 'text-red-600' : 'text-yellow-600' }} mt-1">
                                {{ $activeSessionData['elapsed_minutes'] >= 90 ? '90-minute limit exceeded. End and document this session.' : 'Approaching the 90-minute session limit.' }}
                            </div>
                        @endif
                    </div>
                <a href="{{ route('helper.session.chat', ['id' => $activeSessionData['id']]) }}" class="btn-resume">
                    <i class="fas fa-play mr-1"></i> Resume chat
                </a>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- ─── LEFT COLUMN (2/3) ─── -->
        <div class="lg:col-span-2">

            <!-- Assigned Cases -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3>Assigned Cases</h3>
                    <a href="{{ route('helper.cases') }}" class="link">View all</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Alias</th>
                                <th>Risk</th>
                                <th class="hidden sm:table-cell">Mode</th>
                                <th class="hidden md:table-cell">Language</th>
                                <th class="hidden lg:table-cell">Waiting</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeCases as $case)
                                <tr>
                                    <td class="font-medium">{{ $case['reference'] }}</td>
                                    <td>{{ $case['alias'] }}</td>
                                    <td><span class="risk-badge {{ strtolower($case['risk']) }}">{{ $case['risk'] }}</span></td>
                                    <td class="hidden sm:table-cell">{{ $case['mode'] }}</td>
                                    <td class="hidden md:table-cell">{{ $case['language'] }}</td>
                                    <td class="hidden lg:table-cell">{{ $case['waiting'] }}</td>
                                    <td class="text-sm text-gray-500">{{ Illuminate\Support\Str::limit($case['notes'], 30) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-6 text-gray-400">No active cases</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    @foreach($quickActions as $action)
                        <a href="{{ route($action['route'], $action['params']) }}" class="btn-action">
                            <i class="fas {{ $action['icon'] }}"></i> {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

        </div>

        <!-- ─── RIGHT COLUMN (1/3) ─── -->
        <div class="lg:col-span-1">

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                    <a href="{{ route('helper.notifications') }}" class="link">View all</a>
                </div>
                <div>
                    @forelse($recentActivity as $activity)
                        <div class="activity-item">
                            <div class="icon {{ $activity['type'] }}">
                                @if($activity['type'] == 'emergency')
                                    <i class="fas fa-exclamation"></i>
                                @elseif($activity['type'] == 'assignment')
                                    <i class="fas fa-user-plus"></i>
                                @elseif($activity['type'] == 'feedback')
                                    <i class="fas fa-star"></i>
                                @elseif($activity['type'] == 'reminder')
                                    <i class="fas fa-clock"></i>
                                @else
                                    <i class="fas fa-bell"></i>
                                @endif
                            </div>
                            <div class="content">
                                <div class="message">{{ $activity['message'] }}</div>
                                <div class="detail">{{ $activity['detail'] }}</div>
                                <div class="time">{{ $activity['time'] }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No recent activity</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

    <!-- Footer -->
    <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
        <i class="fas fa-heart text-[#04A052] mr-1"></i>
        You are making a difference. Keep going.
    </div>

    @endif

@endsection
