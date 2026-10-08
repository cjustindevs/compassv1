@extends('layouts.app')
@section('title','Emergency Contacts - COMPASS')
@section('content')
<div class="adviser-page-content av-page">
<header><div><h1>Emergency contacts</h1><p class="av-muted">The shared emergency directory. Updates use the existing resource publication and version history.</p></div><a class="av-button" href="{{ route('adviser.resources') }}">Back to resources</a></header>
@if(session('success'))<p class="av-note" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p class="av-note" role="alert">{{ $errors->first() }}</p>@endif
        <section class="av-panel">
            <h2>Emergency contacts</h2>
            <p class="text-sm text-gray-500 my-3">Use agency-verified telephone numbers. Active contacts appear on the emergency resources page.</p>
            @foreach($hotlines->concat([null]) as $hotline)
                <form method="POST" action="{{ route('adviser.emergency-resources.save') }}" class="av-filter border-t border-gray-200 py-4">
                    @csrf
                    @if($hotline)<input type="hidden" name="id" value="{{ $hotline->id }}">@endif
                    <label class="text-sm font-medium">Agency name<input name="agency_name" value="{{ $hotline?->agency_name }}" required maxlength="255" class="form-input block w-full mt-1"></label>
                    <label class="text-sm font-medium">Telephone number<input name="hotline" value="{{ $hotline?->hotline }}" required maxlength="50" class="form-input block w-full mt-1"></label>
                    <label class="text-sm font-medium">Description<input name="description" value="{{ $hotline?->description }}" maxlength="1000" class="form-input block w-full mt-1"></label>
                    <label class="text-sm font-medium">Publication status<select name="status" class="form-input block w-full mt-1"><option value="active">Active</option><option value="inactive" @selected($hotline?->status === 'inactive')>Inactive</option></select></label>
                    <label class="text-sm font-medium">Audience<select name="visibility" class="form-input block w-full mt-1"><option value="public">Public</option><option value="internal" @selected($hotline?->visibility === 'internal')>Internal</option></select></label>
                    <label class="text-sm font-medium">Review due<input type="date" name="review_date" value="{{ $hotline?->review_date ? \Illuminate\Support\Carbon::parse($hotline->review_date)->format('Y-m-d') : '' }}" class="form-input block w-full mt-1"></label>
                    <div><button type="submit" class="av-button av-button-primary">{{ $hotline ? 'Save contact' : 'Add contact' }}</button></div>                    @if($hotline)<div class="w-full"><x-supervision-history :record="$hotline" /></div>@endif

                </form>
            @endforeach
        </section>
</div>
@endsection
