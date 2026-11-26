@extends('layouts.app')
@section('title', 'Transcript access - COMPASS')
@section('content')
@php
    $rows = collect($unverifiedTranscripts);
    $helperNames = $rows->pluck('helper_name')->unique()->sort()->values();
    $statuses = $rows->pluck('session_status')->unique()->sort()->values();
    $failedId = $errors->any() ? (string) old('access_session_id') : null;
@endphp
<div class="adviser-page-content at-page">
    <x-ui.page-header title="Transcript access" description="Review completed conversations from Helpers you currently supervise.">
        <x-slot:actions><a class="btn-secondary" href="{{ route('adviser.reports',['tab'=>'cases']) }}">View session records</a></x-slot:actions>
    </x-ui.page-header>
    @if(session('success'))<p class="ui-notice" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="at-error" role="alert"><strong>Check your access request</strong><p>Correct the highlighted fields in the open form.</p></div>@endif
    <div class="at-access-note"><x-ui-icon name="info" /><p><strong>Access is logged.</strong> Choose a review purpose and give a reason to open a conversation. Access lasts ten minutes. Seekers agree to this when they request support.</p></div>

    <x-ui.card class="at-queue" title="Awaiting transcript verification" :subtitle="$rows->count().' '.($rows->count()===1 ? 'session' : 'sessions').'. Dates are Philippine Time.'">
    @if($rows->isEmpty())
        <x-ui.empty title="No transcripts awaiting verification" text="Completed sessions requiring transcript review will appear here."><a class="btn-secondary" href="{{ route('adviser.reports',['tab'=>'cases']) }}">View session records</a></x-ui.empty>
    @else
        <div data-table-pager>
            <div class="ui-filter-bar ui-filter-bar--grid at-toolbar" role="search" aria-label="Transcript filters">
                <label class="ui-field at-toolbar-search"><span class="ui-field-label">Search</span><input type="search" data-pager-search placeholder="Session ID or Helper"></label>
                <label class="ui-field"><span class="ui-field-label">Helper</span><select data-pager-filter="helper"><option value="all">All Helpers</option>@foreach($helperNames as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach</select></label>
                <label class="ui-field"><span class="ui-field-label">Session status</span><select data-pager-filter="status"><option value="all">All</option>@foreach($statuses as $status)<option value="{{ $status }}">{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
                <label class="ui-field"><span class="ui-field-label">Transcript</span><select data-pager-filter="transcript"><option value="all">All</option><option value="available">Available</option><option value="unavailable">Not available</option></select></label>
                <label class="ui-field"><span class="ui-field-label">From</span><input type="date" data-pager-from></label>
                <label class="ui-field"><span class="ui-field-label">To</span><input type="date" data-pager-to></label>
                <div class="at-toolbar-actions"><button type="button" class="btn-secondary" data-pager-reset>Reset</button></div>
            </div>

            <x-ui.table :stack="true" role="region" aria-label="Transcripts awaiting verification">
                <thead><tr><th>Session ID</th><th>Helper</th><th>Date and time (PHT)</th><th>Session status</th><th>Transcript</th><th class="ui-actions">Actions</th></tr></thead>
                <tbody>
                @foreach($rows as $item)
                    @php $id = $item['session_id']; $date = $item['session_date']?->copy()->timezone('Asia/Manila'); @endphp
                    <tr data-pager-row data-search="{{ strtolower($item['reference_number'].' '.$item['helper_name']) }}" data-helper="{{ $item['helper_name'] }}" data-status="{{ $item['session_status'] }}" data-transcript="{{ $item['eligible'] ? 'available' : 'unavailable' }}" data-date="{{ $date?->format('Y-m-d') }}">
                        <td data-label="Session ID"><strong>{{ $item['reference_number'] }}</strong></td>
                        <td data-label="Helper">{{ $item['helper_name'] }}</td>
                        <td data-label="Date and time">@if($date)<time datetime="{{ $date->toIso8601String() }}">{{ $date->format('M d, Y g:i A') }}</time>@else<span class="ui-muted-dash">—</span>@endif</td>
                        <td data-label="Session status"><x-ui.status-badge :status="$item['session_status']" /></td>
                        <td data-label="Transcript">@if($item['eligible'])<x-ui.badge tone="green">Available</x-ui.badge>@else<x-ui.badge tone="neutral">Not available</x-ui.badge>@endif</td>
                        <td data-label="Actions" class="ui-actions">
                            @if($item['eligible'])
                                <x-ui.icon-button icon="eye" label="Request access to {{ $item['reference_number'] }}" data-dialog-open="transcript-access-{{ $id }}" />
                            @else
                                <x-ui.icon-button icon="info" label="Not available: needs a completed chat session and current supervision of its Helper" data-dialog-open="transcript-why-{{ $id }}" />
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr data-pager-empty hidden><td colspan="6" class="at-empty">No sessions match these filters.</td></tr>
                </tbody>
            </x-ui.table>

            <footer class="at-pager">
                <label class="at-pager-size">Rows per page <select data-pager-size><option value="10" selected>10</option><option value="15">15</option><option value="25">25</option><option value="50">50</option></select></label>
                <span data-pager-status aria-live="polite"></span>
                <span class="at-pager-nav"><button type="button" class="btn-secondary" data-pager-prev>Previous</button><button type="button" class="btn-secondary" data-pager-next>Next</button></span>
            </footer>
        </div>

        @foreach($rows as $item)
            @php $id = $item['session_id']; $failed = $failedId === (string) $id; @endphp
            @if($item['eligible'])
            <dialog id="transcript-access-{{ $id }}" class="workflow-dialog" aria-labelledby="transcript-access-title-{{ $id }}" @if($failed) data-open-on-load @endif>
                <header class="workflow-dialog__header"><div><h2 id="transcript-access-title-{{ $id }}">Open transcript {{ $item['reference_number'] }}</h2><p>{{ $item['helper_name'] }}. Opening it records this access.</p></div><button type="button" data-dialog-close aria-label="Close"><x-ui-icon name="close" /></button></header>
                <form method="POST" action="{{ route('adviser.transcript.access', $id) }}">
                    @csrf
                    <div class="workflow-dialog__body at-access-form">
                        <div class="at-purpose-field">
                            <label for="purpose-{{ $id }}">Review purpose <span aria-hidden="true">*</span></label>
                            <select id="purpose-{{ $id }}" name="purpose" required class="av-field" @if($failed && $errors->has('purpose')) aria-invalid="true" @endif aria-describedby="purpose-hint-{{ $id }}">
                                <option value="">Select a review purpose</option>
                                @foreach(\App\Services\AdviserTranscriptAccess::PURPOSES as $purpose)<option value="{{ $purpose }}" @selected($failed && old('purpose')===$purpose)>{{ ucwords(str_replace('_',' ',$purpose)) }}</option>@endforeach
                            </select>
                            <p id="purpose-hint-{{ $id }}" class="av-muted">Choose the purpose that matches your review.</p>
                            @if($failed)@error('purpose')<p class="at-field-error">{{ $message }}</p>@enderror @endif
                        </div>
                        <div class="at-reason-field">
                            <label for="reason-{{ $id }}">Reason for access <span aria-hidden="true">*</span></label>
                            <textarea id="reason-{{ $id }}" name="reason" required minlength="10" maxlength="500" rows="4" class="av-field" placeholder="Explain what you need to clarify from the conversation." aria-describedby="reason-hint-{{ $id }}" @if($failed && $errors->has('reason')) aria-invalid="true" @endif>{{ $failed ? old('reason') : '' }}</textarea>
                            <p id="reason-hint-{{ $id }}" class="av-muted">10-500 characters. Keep the reason relevant to the review and avoid identity details.</p>
                            @if($failed)@error('reason')<p class="at-field-error">{{ $message }}</p>@enderror @endif
                        </div>
                    </div>
                    <footer class="workflow-dialog__footer"><button type="button" class="btn-secondary" data-dialog-close>Cancel</button><button type="submit" class="btn-primary">Open transcript</button></footer>
                </form>
            </dialog>
            @else
            <dialog id="transcript-why-{{ $id }}" class="workflow-dialog" aria-labelledby="transcript-why-title-{{ $id }}">
                <header class="workflow-dialog__header"><div><h2 id="transcript-why-title-{{ $id }}">{{ $item['reference_number'] }}: transcript not available</h2></div><button type="button" data-dialog-close aria-label="Close"><x-ui-icon name="close" /></button></header>
                <div class="workflow-dialog__body"><p>Transcript review needs a completed chat session and your current supervision of its Helper.</p></div>
                <footer class="workflow-dialog__footer"><button type="button" class="btn-secondary" data-dialog-close>Close</button></footer>
            </dialog>
            @endif
        @endforeach
    @endif
    </x-ui.card>
</div>
<style>
.at-access-note{display:flex;gap:10px;align-items:flex-start}
.at-access-note>.compass-icon{flex:none;color:#31711d;margin-top:1px}
.at-access-note p{margin:0}
.at-toolbar{grid-template-columns:minmax(0,1.6fr) repeat(3,minmax(0,1fr)) repeat(2,minmax(150px,1fr)) auto;align-items:end;margin-bottom:12px}
.at-toolbar-actions{display:flex;align-items:end}
.at-access-form{display:grid;gap:14px}
.at-pager{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;padding-top:12px;font-size:13px;color:#53665a}
.at-pager-size{display:inline-flex;gap:8px;align-items:center}
.at-pager-nav{display:inline-flex;gap:8px}
.at-empty{text-align:center;color:#64748b;padding:20px}
@media(max-width:1100px){.at-toolbar{grid-template-columns:repeat(3,minmax(0,1fr))}.at-toolbar-search{grid-column:1/-1}}
@media(max-width:600px){.at-toolbar{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}}
</style>
@endsection
@push('scripts')
<script src="{{ asset('js/table-pager.js') }}?v={{ filemtime(public_path('js/table-pager.js')) }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('dialog[data-open-on-load]').forEach((d) => (window.CompassDialogs?.open ? window.CompassDialogs.open(d) : d.showModal()));
});
</script>
@endpush
