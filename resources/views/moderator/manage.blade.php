@extends('layouts.app')

@section('title', 'COMPASS – Manage Team')

@push('styles')
    <meta name="user-id" content="{{ auth()->id() }}">
    @vite(['resources/js/moderator-notifications.js'])
    <style>
        .manage-flash { border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        .manage-flash.success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; }
        .manage-flash.error { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; }
        .manage-columns { display: grid; grid-template-columns: 1fr; gap: 0 24px; align-items: start; margin-bottom: 24px; }
        .manage-columns > .ui-card { margin-bottom: 0; }
        @media (min-width: 1024px) { .manage-columns { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); } }
        .adviser-card { display: block; border: 1px solid var(--compass-border, #E5E7EB); border-radius: 16px; padding: 16px; margin-bottom: 12px; text-decoration: none; color: inherit; }
        .adviser-card:hover, .adviser-card.selected { border-color: var(--compass-green, #04A052); }
        .adviser-card.selected { background: var(--compass-green-soft, #F0FDF4); }
        .adviser-card .ui-list-row { padding: 0; border-bottom: 0; }
        .adviser-card .ui-list-row { flex-wrap: nowrap; } .adviser-card .ui-list-side { width: auto; }
        .adviser-chevron { color: var(--compass-muted, #6B7280); flex-shrink: 0; }
        .slot-bar { display: flex; gap: 4px; margin-top: 12px; }
        .slot { flex: 1; height: 6px; border-radius: 3px; background: #E5E7EB; }
        .slot.filled { background: var(--compass-green, #04A052); }
        .workspace-sub { font-size: 15px; color: var(--compass-muted, #6B7280); margin: 0 0 8px; }
        .workspace-sub strong { color: var(--compass-text, #1F2937); }
        .workspace-section { margin-top: 24px; }
        .ui-empty-note { text-align: center; padding: 24px 0; font-size: 15px; color: var(--compass-muted, #6B7280); }
        .btn-pill { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 18px; border-radius: 999px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-pill-outline { background: var(--bg-card, #fff); color: var(--compass-text, #1F2937); border: 1px solid var(--compass-border, #E5E7EB); }
        .btn-pill-outline:hover { border-color: #DC2626; color: #B91C1C; }
        .btn-pill-primary { background: var(--compass-green, #04A052); color: #fff; border: 1px solid var(--compass-green, #04A052); }
        .btn-pill-primary:hover:not(:disabled) { background: var(--compass-green-dark, #027039); }
        .btn-pill:disabled { opacity: .5; cursor: not-allowed; }
        .ui-list-actions form { margin: 0; }
        .status-pill { display: inline-block; font-size: 13px; font-weight: 600; padding: 4px 12px; border-radius: 999px; white-space: nowrap; }
        .status-pill.available { background: #DCFCE7; color: #166534; }
        .status-pill.offline { background: #F3F4F6; color: #4B5563; }
        .helpers-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; }
        .helpers-tools a { font-size: 14px; color: var(--compass-muted, #6B7280); text-decoration: none; }
        .helpers-tools a:hover { color: var(--compass-text, #1F2937); }
        .helpers-tools input[type="text"] { min-height: 44px; width: 220px; max-width: 100%; border: 1px solid var(--compass-border, #E5E7EB); border-radius: 999px; padding: 0 16px; font-size: 15px; background: var(--bg-card, #fff); }
        .helpers-tools input[type="text"]:focus { outline: 2px solid var(--compass-green, #04A052); outline-offset: 1px; }
        .ui-table td small { display: block; font-size: 13px; color: var(--compass-muted, #6B7280); }
        .assign-inline { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .assign-inline select { min-height: 44px; border: 1px solid var(--compass-border, #E5E7EB); border-radius: 12px; padding: 0 12px; font-size: 15px; background: var(--bg-card, #fff); max-width: 100%; }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-helper-assignment]').forEach(form=>form.addEventListener('submit',event=>{
                if(form.dataset.submitting==='true'){event.preventDefault();return;}
                if(!form.checkValidity())return;form.dataset.submitting='true';form.querySelectorAll('button[type="submit"]').forEach(button=>{button.disabled=true;button.textContent='Saving...';});
            }));

            const setStat = (key, value) => {
                document.querySelectorAll('[data-stat="' + key + '"] .ui-kpi-value').forEach((el) => { el.textContent = value; });
            };
            setInterval(function () {
                fetch('/moderator/manage/stats', {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        setStat('total_helpers', data.total_helpers);
                        setStat('unassigned_helpers', data.unassigned_helpers);
                        setStat('total_advisers', data.total_advisers);
                        setStat('slots_taken', data.slots_taken);
                    })
                    .catch(() => {});
            }, 30000);
        });
    </script>
@endpush

@section('content')
    @php
        $levelTone = ['advanced' => 'blue', 'expert' => 'green', 'proficient' => 'amber'];
        $initials = fn ($m) => strtoupper(substr($m->first_name, 0, 1).substr($m->last_name, 0, 1));
        $workspaceAdviser = $selectedAdviser
            ? $advisers->firstWhere('id', (int) $selectedAdviser)
            : $advisers->first();
    @endphp

    <x-ui.page-header title="Manage Team" description="Helpers and adviser assignment workspace" />

    @if(session('success'))
        <div class="manage-flash success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="manage-flash error">{{ session('error') }}</div>
    @endif

    <x-ui.kpi-grid>
        <x-ui.kpi label="Total Helpers" :value="$manageStats['total_helpers']" tone="neutral" data-stat="total_helpers" />
        <x-ui.kpi label="Helpers without an Adviser" :value="$manageStats['unassigned_helpers']" tone="amber" data-stat="unassigned_helpers" />
        <x-ui.kpi label="Advisers" :value="$advisers->count()" tone="blue" data-stat="total_advisers" />
        <x-ui.kpi label="Slots Used" :value="$advisers->sum('assigned_helpers')" tone="green" data-stat="slots_taken" />
    </x-ui.kpi-grid>

    <div class="manage-columns">
        <x-ui.card title="Advisers">
            <x-slot:actions>
                <span class="ui-card-sub">Capacity: {{ \App\Models\Helper::MAX_HELPERS_PER_ADVISER }} each</span>
            </x-slot:actions>
            @foreach($advisers as $adviser)
                <a href="{{ route('moderator.manage', array_merge(request()->except('workspace'), ['workspace' => $adviser->id])) }}"
                   class="adviser-card {{ $workspaceAdviser && $workspaceAdviser->id == $adviser->id ? 'selected' : '' }}"
                   @if($workspaceAdviser && $workspaceAdviser->id == $adviser->id) aria-current="true" @endif>
                    <x-ui.list-row :initials="$initials($adviser)" :title="$adviser->full_name" :subtitle="$adviser->assigned_helpers.' assigned · '.$adviser->remaining_slots.' slots left'">
                        <x-slot:actions>
                            <x-ui-icon name="chevron-right" class="adviser-chevron" />
                        </x-slot:actions>
                    </x-ui.list-row>
                    <div class="slot-bar" aria-hidden="true">
                        @for($i = 0; $i < $adviser->capacity; $i++)
                            <div class="slot {{ $i < $adviser->assigned_helpers ? 'filled' : '' }}"></div>
                        @endfor
                    </div>
                </a>
            @endforeach
        </x-ui.card>

        <x-ui.card title="Assignment Workspace">
            @if($workspaceAdviser)
                <x-slot:actions>
                    <span class="ui-card-sub">{{ $workspaceAdviser->remaining_slots }} of {{ $workspaceAdviser->capacity }} slots remaining</span>
                </x-slot:actions>

                <p class="workspace-sub">Helpers under <strong>{{ $workspaceAdviser->full_name }}</strong></p>
                @forelse($workspaceHelpers as $helper)
                    @php $level = $helper->latestCompetency?->competency_level ?? 'trainee'; @endphp
                    <x-ui.list-row :initials="$initials($helper)" :title="$helper->full_name" :subtitle="$helper->email">
                        <x-slot:badge><x-ui.badge :tone="$levelTone[$level] ?? 'neutral'">{{ ucfirst($level) }}</x-ui.badge></x-slot:badge>
                        <x-slot:actions>
                            <form method="POST" data-helper-assignment action="{{ route('moderator.manage.unassign') }}">
                                @csrf
                                <input type="hidden" name="helper_id" value="{{ $helper->id }}">
                                <button type="submit" class="btn-pill btn-pill-outline" title="Move to Helpers without an Adviser">
                                    <x-ui-icon name="user-minus" /> Unassign
                                </button>
                            </form>
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <p class="ui-empty-note">No helpers assigned to this adviser yet.</p>
                @endforelse
                {{ $workspaceHelpers->links('vendor.pagination.compass', ['noun' => 'helpers']) }}

                <p class="workspace-sub workspace-section">Add Helpers without an Adviser</p>
                @forelse($poolHelpers as $helper)
                    @php $level = $helper->latestCompetency?->competency_level ?? 'trainee'; @endphp
                    <x-ui.list-row :initials="$initials($helper)" :title="$helper->full_name" :subtitle="$helper->email">
                        <x-slot:badge><x-ui.badge :tone="$levelTone[$level] ?? 'neutral'">{{ ucfirst($level) }}</x-ui.badge></x-slot:badge>
                        <x-slot:actions>
                            <form method="POST" data-helper-assignment action="{{ route('moderator.manage.assign') }}">
                                @csrf
                                <input type="hidden" name="helper_id" value="{{ $helper->id }}">
                                <input type="hidden" name="adviser_id" value="{{ $workspaceAdviser->id }}">
                                <button type="submit" class="btn-pill btn-pill-primary" {{ $workspaceAdviser->remaining_slots <= 0 ? 'disabled' : '' }}>
                                    <x-ui-icon name="user-plus" /> Assign
                                </button>
                            </form>
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <p class="ui-empty-note">No helpers without an Adviser.</p>
                @endforelse
                {{ $poolHelpers->links('vendor.pagination.compass', ['noun' => 'helpers']) }}
            @else
                <x-ui.empty title="Select an adviser to open the assignment workspace" />
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="All Helpers">
        <x-slot:actions>
            <div class="helpers-tools">
                <a href="{{ route('moderator.manage', request()->except(['adviser','page'])) }}">All</a>
                <a href="{{ route('moderator.manage', array_merge(request()->except(['adviser','page']), ['adviser' => 'unassigned'])) }}">Unassigned</a>
                <form method="GET" action="{{ route('moderator.manage') }}">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search name or ID..." aria-label="Search helpers by name or ID">
                    @if($adviserFilter) <input type="hidden" name="adviser" value="{{ $adviserFilter }}"> @endif
                    @if($selectedAdviser) <input type="hidden" name="workspace" value="{{ $selectedAdviser }}"> @endif
                </form>
            </div>
        </x-slot:actions>

        <x-ui.table :stack="true">
            <thead><tr><th>Helper</th><th>Primary Adviser</th><th>Duty / readiness</th><th>Capacity</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($helpers as $helper)
                    <tr>
                        <td data-label="Helper"><strong>{{ $helper->full_name }}</strong><small>H-{{ str_pad($helper->id,3,'0',STR_PAD_LEFT) }}</small></td>
                        <td data-label="Primary Adviser">{{ $helper->adviser?->full_name ?? 'Unassigned' }}</td>
                        <td data-label="Duty / readiness">{{ \App\Models\HelperSchedule::coveringShiftFor($helper->id) ? 'On duty' : 'Off duty' }}<small>{{ $helper->getCurrentReadiness()?->assessment_result === 'ready' ? 'Ready' : 'Readiness required' }}</small></td>
                        <td data-label="Capacity"><x-helper-capacity :helper="$helper" /></td>
                        <td data-label="Status"><span class="status-pill {{ $helper->eligibility['assignable'] ? 'available' : 'offline' }}">{{ $helper->eligibility['label'] }}</span></td>
                        <td data-label="Action" class="ui-actions">
                            @if(!$helper->adviser)
                                <form method="POST" data-helper-assignment action="{{ route('moderator.manage.assign') }}" class="assign-inline">@csrf<input type="hidden" name="helper_id" value="{{ $helper->id }}"><label class="sr-only" for="assign-adviser-{{ $helper->id }}">Assign Adviser</label><select id="assign-adviser-{{ $helper->id }}" name="adviser_id" required><option value="">Select Adviser</option>@foreach($advisers as $adviser)<option value="{{ $adviser->id }}" @disabled($adviser->remaining_slots <= 0)>{{ $adviser->full_name }}</option>@endforeach</select><button type="submit" class="btn-pill btn-pill-primary">Assign</button></form>
                            @else — @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No helpers in this list</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
        {{ $helpers->links('vendor.pagination.compass', ['noun' => 'helpers']) }}
    </x-ui.card>
@endsection
