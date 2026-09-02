@extends('layouts.helper')

@section('title', 'Assigned Cases')

@section('heading', 'Assigned Cases')
@section('subheading', 'All sessions assigned to you, straight from the database.')

@section('content')

    <!-- Summary stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Awaiting Action</span>
                <span class="stat-icon">📋</span>
            </div>
            <div class="stat-number" id="pendingCount" style="{{ $stats['pending'] > 0 ? 'color:var(--yellow-500);' : '' }}">{{ $stats['pending'] }}</div>
            <span class="text-xs text-gray-400">Accept or decline</span>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Active</span>
                <span class="stat-icon">🟢</span>
            </div>
            <div class="stat-number">{{ $stats['active'] }}</div>
            <span class="text-xs text-gray-400">In progress</span>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Completed</span>
                <span class="stat-icon">✅</span>
            </div>
            <div class="stat-number">{{ $stats['completed'] }}</div>
            <span class="text-xs text-gray-400">Finished sessions</span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>All Cases</h3>
            <span class="text-xs text-gray-400">{{ $cases->count() }} session(s)</span>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Seeker</th>
                        <th>Concern</th>
                        <th>Risk</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Rating</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                        <tr>
                            <td class="font-medium">{{ $case['reference'] }}</td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="avatar-sm">{{ \Illuminate\Support\Str::substr($case['alias'], 0, 2) }}</div>
                                    <div>
                                        <div class="font-medium">{{ $case['alias'] }}</div>
                                        <div class="text-xs text-gray-400">{{ $case['gender'] ? ucfirst($case['gender']) . ($case['age'] ? ' · ' . $case['age'] . ' yrs' : '') : ($case['age'] ? $case['age'] . ' yrs' : '—') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-sm text-gray-500">{{ Illuminate\Support\Str::limit($case['concern'], 24) }}</td>
                            <td><span class="risk-badge {{ $case['risk_class'] }}">{{ $case['risk'] }}</span></td>
                            <td>{{ $case['mode'] }}</td>
                            <td><span class="status-badge {{ str_replace('_', '-', $case['status']) }}">{{ $case['status_label'] }}</span></td>
                            <td class="text-sm text-gray-500">
                                {{ $case['created'] }}
                                <div class="text-xs text-gray-400">{{ $case['waiting'] }}</div>
                            </td>
                            <td>
                                @if($case['rating'])
                                    <span class="pill" style="background:#FEF3C7;color:#B45309;">★ {{ $case['rating'] }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($case['pending'])
                                    <div style="display:flex;gap:6px;justify-content:flex-end;">
                                        <form method="POST" action="{{ route('helper.cases.decline', ['id' => $case['id']]) }}"
                                              data-confirm="Decline case?"
                                              data-confirm-message="This case will be returned to the queue and reassigned."
                                              data-confirm-text="Decline"
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-times"></i> Decline</button>
                                        </form>
                                        <form method="POST" action="{{ route('helper.cases.accept', ['id' => $case['id']]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Accept</button>
                                        </form>
                                    </div>
                                @elseif($case['completed'])
                                    <a href="{{ route('helper.session.notes', ['id' => $case['id']]) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-alt"></i> Notes</a>
                                @else
                                    <a href="{{ route('helper.session.chat', ['id' => $case['id']]) }}" class="btn btn-secondary btn-sm"><i class="fas fa-comment-dots"></i> Open</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <h3 style="font-size:16px;font-weight:700;color:var(--gray-800);margin-bottom:6px;">No cases assigned yet</h3>
                                    <p>When a seeker is matched to you, the case will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection