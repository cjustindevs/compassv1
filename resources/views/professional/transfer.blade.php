@extends('layouts.app')
@section('content')
@include('partials.management-ui-styles')
<div class="cm-page"><section class="cm-card"><h1>Professional transfer #{{ $transfer->id }}</h1>
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<p>Referral #{{ $transfer->referral_id }} &middot; {{ ucfirst($transfer->status) }}</p>
<p>The current Professional remains responsible until acceptance. Case details become available after acceptance; identity access requires separate authorization.</p>
<p><strong>Transfer reason:</strong> {{ decrypt($transfer->reason) }}</p>
@if($transfer->status === 'pending')<form method="POST" action="{{ route('professional.transfer.respond', $transfer->id) }}">@csrf<button class="cm-button cm-primary" name="decision" value="accept">Accept responsibility</button><button class="cm-button" name="decision" value="decline">Decline transfer</button></form>@endif
<a href="{{ route('professional.referrals') }}">Back to referrals</a></section></div>
@endsection
