@extends('layouts.app')

@section('title', 'COMPASS · Referral Assignment')

@push('styles')
    <meta name="user-id" content="{{ auth()->id() }}">
    @vite(['resources/js/moderator-notifications.js'])
    <style>
        .ref-flash { border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        .ref-flash.success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; }
        .ref-flash.error { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; }
        .ref-assign { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 0; }
        .ref-assign select, .ref-complete select { min-height: 44px; padding: 0 12px; border-radius: 12px; border: 1px solid var(--compass-border, #E5E7EB); font-size: 15px; background: var(--bg-card, #fff); color: var(--compass-text, #1F2937); max-width: 100%; }
        .ref-assign select:focus, .ref-complete select:focus { outline: 2px solid var(--compass-green, #04A052); outline-offset: 1px; }
        .ref-reason { max-width: 420px; color: var(--compass-muted, #6B7280); }
        .ref-complete { display: grid; gap: 8px; margin-top: 8px; }
        .ref-complete label { display: grid; gap: 6px; font-size: 14px; font-weight: 600; }
        .ref-item { padding: 12px 0; border-bottom: 1px solid var(--compass-divider, #F3F4F6); }
        .ref-item:last-child { border-bottom: 0; }
        .ref-item p { margin: 0; font-size: 15px; }
        @media (max-width: 768px) { .ref-assign select { flex: 1 1 100%; } .ref-reason { max-width: none; } }
    </style>
@endpush

@section('content')
    @php
        $priorityTone = fn ($level) => match ($level) {
            'emergency' => 'red',
            'high' => 'orange',
            'moderate' => 'amber',
            default => 'neutral',
        };
    @endphp

    <x-ui.page-header title="Referrals Awaiting Adviser Assignment" description="A Helper raised a referral recommendation but has no assigned Adviser, so nobody can review it. Assign an active Adviser to hand the recommendation over. The recommendation itself is preserved.">
        <x-slot:actions>
            <a href="{{ route('moderator.dashboard') }}" class="btn btn-outline">Back to Dashboard</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('success'))
        <div class="ref-flash success" role="status">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="ref-flash error" role="alert">{{ session('error') }}</div>
    @endif

    <x-ui.card>
        @if ($referrals->isEmpty())
            <x-ui.empty title="No referrals are waiting for an Adviser assignment." />
        @else
            <x-ui.table :stack="true">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Raised</th>
                        <th>Helper</th>
                        <th>Priority</th>
                        <th>Reason</th>
                        <th>Assign Adviser</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($referrals as $referral)
                        <tr>
                            <td data-label="#"><strong>{{ $referral->id }}</strong></td>
                            <td data-label="Raised">{{ optional($referral->created_at)->diffForHumans() }}</td>
                            <td data-label="Helper">{{ $referral->helper?->user?->name ?? 'Unknown Helper' }}</td>
                            <td data-label="Priority"><x-ui.badge :tone="$priorityTone($referral->priority_level)">{{ ucfirst($referral->priority_level) }}</x-ui.badge></td>
                            <td data-label="Reason" class="ref-reason">{{ \Illuminate\Support\Str::limit($referral->referral_reason, 140) }}</td>
                            <td data-label="Assign Adviser">
                                <form method="POST" action="{{ route('moderator.referrals.assign-adviser', $referral->id) }}"
                                      class="ref-assign"
                                      data-confirm="Assign this referral to the selected Adviser?">
                                    @csrf
                                    <select name="adviser_id" required aria-label="Adviser for referral {{ $referral->id }}">
                                        <option value="">Select an Adviser&hellip;</option>
                                        @foreach ($advisers as $adviser)
                                            <option value="{{ $adviser->id }}">
                                                {{ $adviser->user?->name ?? 'Adviser #'.$adviser->id }}
                                                &mdash; {{ $adviser->supervised_helpers_count }} {{ \Illuminate\Support\Str::plural('helper', $adviser->supervised_helpers_count) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-primary">Assign</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Completion reports awaiting an active Adviser" subtitle="Assignment information only. Private reports remain with authorized clinical reviewers.">
        @forelse($completionAssignments as $completionAssignment)
            <div class="ref-item"><p>Referral #{{ $completionAssignment->id }} ? {{ ucwords(str_replace('_',' ',$completionAssignment->status)) }}</p><form method="POST" action="{{ route('referrals.completion.assign',$completionAssignment) }}" class="ref-complete">@csrf<label>Completion reviewer<select name="adviser_id" required><option value="">Choose active Adviser</option>@foreach($advisers as $adviser)<option value="{{ $adviser->id }}">{{ $adviser->full_name }}</option>@endforeach</select></label><div><button class="btn-primary" type="submit">Assign completion reviewer</button></div></form></div>
        @empty
            <x-ui.empty title="No completion reports need reassignment." />
        @endforelse
    </x-ui.card>
@endsection
