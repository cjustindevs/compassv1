@extends('layouts.app')
@section('title','Emergency Contacts - COMPASS')
@section('content')
<div class="er-resources">
    <x-ui.page-header title="Emergency contacts" description="The shared emergency directory. Updates use the existing resource publication and version history." :back-href="route('adviser.resources')" back-label="Back to resources" />
    @if(session('success'))<p class="erx-note" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="erx-note erx-error" role="alert">{{ $errors->first() }}</p>@endif
    <x-ui.card title="Emergency contacts" subtitle="Use agency-verified telephone numbers. Active contacts appear on the emergency resources page.">
        @foreach($hotlines->concat([null]) as $hotline)
            <form method="POST" action="{{ route('adviser.emergency-resources.save') }}" class="erx-form">
                @csrf
                @if($hotline)<input type="hidden" name="id" value="{{ $hotline->id }}">@endif
                <div class="erx-grid">
                    <x-ui.field label="Agency name" :for="'agency'.($hotline?->id ?? 'new')"><input id="agency{{ $hotline?->id ?? 'new' }}" name="agency_name" value="{{ $hotline?->agency_name }}" required maxlength="255"></x-ui.field>
                    <x-ui.field label="Telephone number" :for="'hotline'.($hotline?->id ?? 'new')"><input id="hotline{{ $hotline?->id ?? 'new' }}" name="hotline" value="{{ $hotline?->hotline }}" required maxlength="50"></x-ui.field>
                    <x-ui.field label="Description" :for="'desc'.($hotline?->id ?? 'new')"><input id="desc{{ $hotline?->id ?? 'new' }}" name="description" value="{{ $hotline?->description }}" maxlength="1000"></x-ui.field>
                    <x-ui.field label="Publication status" :for="'status'.($hotline?->id ?? 'new')"><select id="status{{ $hotline?->id ?? 'new' }}" name="status" data-control="select"><option value="active">Active</option><option value="inactive" @selected($hotline?->status === 'inactive')>Inactive</option></select></x-ui.field>
                    <x-ui.field label="Audience" :for="'vis'.($hotline?->id ?? 'new')"><select id="vis{{ $hotline?->id ?? 'new' }}" name="visibility" data-control="select"><option value="public">Public</option><option value="internal" @selected($hotline?->visibility === 'internal')>Internal</option></select></x-ui.field>
                    <x-ui.field label="Review due" :for="'review'.($hotline?->id ?? 'new')"><input id="review{{ $hotline?->id ?? 'new' }}" type="date" name="review_date" data-control="date" value="{{ $hotline?->review_date ? \Illuminate\Support\Carbon::parse($hotline->review_date)->format('Y-m-d') : '' }}"></x-ui.field>
                </div>
                <div><button type="submit" class="btn btn-primary">{{ $hotline ? 'Save contact' : 'Add contact' }}</button></div>
                @if($hotline)<div class="erx-history"><x-supervision-history :record="$hotline" /></div>@endif
            </form>
        @endforeach
    </x-ui.card>
</div>
<style>
.erx-note{background:#edf8f1;color:#17633d;border-radius:12px;padding:12px 16px;margin:0 0 16px;font-size:14px}.erx-error{background:#fef2f2;color:#b42318}
.erx-form{padding:16px 0;border-top:1px solid var(--compass-divider,#e5e7eb)}.erx-form:first-of-type{border-top:0;padding-top:0}
.erx-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 16px;margin-bottom:16px}
.erx-grid .ui-field{width:auto;min-width:0}
.erx-history{margin-top:16px}
</style>
@endsection
