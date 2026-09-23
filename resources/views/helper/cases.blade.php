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
                <span class="stat-icon"><i class="fas fa-clipboard-list" aria-hidden="true"></i></span>
            </div>
            <div class="stat-number" id="pendingCount" style="{{ $stats['pending'] > 0 ? 'color:var(--yellow-500);' : '' }}">{{ $stats['pending'] }}</div>
            <span class="text-xs text-gray-400">Accept or decline</span>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Active</span>
                <span class="stat-icon"><i class="fas fa-circle" aria-hidden="true"></i></span>
            </div>
            <div class="stat-number">{{ $stats['active'] }}</div>
            <span class="text-xs text-gray-400">In progress</span>
        </div>
        <div class="stat-card">
            <div class="flex items-center justify-between">
                <span class="stat-label">Completed</span>
                <span class="stat-icon"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
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
                                @if($case['scheduled_start'])
                                    <div class="text-xs font-medium text-emerald-600 mt-1"><i class="fas fa-calendar-check"></i> {{ $case['scheduled_start']->setTimezone(config('app.schedule_timezone'))->format('M d, h:i A') }}</div>
                                @endif
                            </td>
                            <td>
                                @if($case['rating'])
                                    <span class="pill" style="background:#FEF3C7;color:#B45309;"><i class="fas fa-star" aria-hidden="true"></i> {{ $case['rating'] }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($case['pending'] && $case['accepted'])
                                    <a href="{{ route('helper.session.pre-assessment',$case['id']) }}" class="btn btn-primary btn-sm">Prepare session</a>
                                @elseif($case['pending'])
                                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                                            <button type="button" class="btn btn-secondary btn-sm" data-toggle-decline="{{ $case['id'] }}" aria-expanded="false" aria-controls="decline-form-{{ $case['id'] }}"><i class="fas fa-times"></i> Decline</button>
                                            <form method="POST" action="{{ route('helper.cases.accept', ['id' => $case['id']]) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Accept</button>
                                            </form>
                                        </div>
                                        <form method="POST" action="{{ route('helper.cases.decline', ['id' => $case['id']]) }}"
                                              data-confirm="Decline case?"
                                              data-confirm-message="This case will be returned to the queue and reassigned."
                                              data-confirm-text="Decline"
                                              id="decline-form-{{ $case['id'] }}"
                                              class="decline-panel hidden" style="min-width:230px;text-align:left;">
                                            @csrf
                                            <select name="reason" class="form-control" required aria-label="Reason for declining">
                                                <option value="">Choose a reason...</option>
                                                @foreach(['fatigue'=>'Fatigue','illness'=>'Illness','personal_emergency'=>'Personal emergency','academic_conflict'=>'Academic conflict','conflict_of_interest'=>'Conflict of interest','unavailable'=>'Unavailable'] as $value=>$label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <div style="display:flex;justify-content:flex-end;gap:6px;margin-top:6px;">
                                                <button type="button" class="btn btn-secondary btn-sm" data-cancel-decline="{{ $case['id'] }}">Cancel</button>
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-times"></i> Confirm decline</button>
                                            </div>
                                        </form>
                                    </div>
                                @elseif($case['expired'])
                                    <span class="text-gray-400 text-sm"><i class="fas fa-hourglass-end"></i> Expired</span>
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
                                    <h3>No cases assigned yet</h3>
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

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-toggle-decline]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const panel = document.getElementById('decline-form-' + btn.dataset.toggleDecline);
                const opening = panel.classList.contains('hidden');
                panel.classList.toggle('hidden');
                btn.setAttribute('aria-expanded', opening ? 'true' : 'false');
            });
        });

        document.querySelectorAll('[data-cancel-decline]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const panel = document.getElementById('decline-form-' + btn.dataset.cancelDecline);
                panel.classList.add('hidden');
                const toggle = document.querySelector('[data-toggle-decline="' + btn.dataset.cancelDecline + '"]');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        });
    });
</script>
@endsection
