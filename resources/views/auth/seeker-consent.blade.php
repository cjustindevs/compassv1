@extends('layouts.auth')
@section('container-width', 'max-w-3xl')
@section('title', 'Privacy and Consent - COMPASS')
@section('subtitle', 'Your privacy matters')
@section('content')
    <h1 class="text-xl font-semibold mb-4">Terms, privacy and consent</h1>
    <p class="mb-5 p-4 rounded-xl bg-green-50 border border-green-200 text-sm text-gray-700">Your sign-in alias is <strong>{{ auth()->user()->helpSeeker->generated_alias }}</strong>. Save it before continuing.</p>
    @if($errors->any())<p class="text-red-700 mb-4">Please accept each item to continue.</p>@endif
    <form method="POST" action="{{ route('seeker.consent.accept') }}" class="space-y-4">
        @csrf
        <div class="space-y-3">
        <label class="consent-option"><input type="checkbox" name="agree_privacy" value="1" required @checked(old('agree_privacy'))><span class="text-sm text-gray-600 leading-relaxed"><strong class="block text-gray-800 mb-1">Privacy notice</strong>I understand that COMPASS stores my account details, screening answers, chat, and feedback to provide and supervise support.</span></label>
        <label class="consent-option"><input type="checkbox" name="agree_terms" value="1" required @checked(old('agree_terms'))><span class="text-sm text-gray-600 leading-relaxed"><strong class="block text-gray-800 mb-1">Terms of support</strong>I understand that peer support is provided by trained volunteers under adviser supervision.</span></label>
        <label class="consent-option"><input type="checkbox" name="agree_emergency" value="1" required @checked(old('agree_emergency'))><span class="text-sm text-gray-600 leading-relaxed"><strong class="block text-gray-800 mb-1">Safety and referrals</strong>I understand that safety concerns may be shared with authorized supervisors and referred for professional support.</span></label>
        <label class="consent-option"><input type="checkbox" name="agree_consent" value="1" required @checked(old('agree_consent'))><span class="text-sm text-gray-600 leading-relaxed"><strong class="block text-gray-800 mb-1">Voluntary participation</strong>I voluntarily agree to receive peer support and can stop participating at any time.</span></label>
        </div>
        <button class="btn-primary w-full rounded-xl text-white font-semibold px-4 py-3">Agree and continue</button>
    </form>
@endsection
@section('footer')
<form method="POST" action="{{ route('logout') }}" class="text-center">@csrf<button type="submit" class="text-sm font-medium text-gray-600">Sign out and decide later</button></form>
@endsection
@push('styles')
<style>
.consent-option { display:flex; align-items:flex-start; gap:14px; padding:16px; border:1px solid #e5e7eb; border-radius:12px; cursor:pointer; }
.consent-option:has(input:checked) { background:#f0fdf4; border-color:#86efac; }
.consent-option:focus-within { outline:2px solid #16a34a; outline-offset:2px; }
.consent-option input { margin-top:3px; width:18px; height:18px; flex-shrink:0; accent-color:#15803d; }
</style>
@endpush
