<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your Professional Support</title>@vite(['resources/css/app.css'])</head><body style="font-family:Inter,sans-serif;background:#f8fbf9;padding:20px">
<h1 class="text-xl font-semibold mb-4">Your Professional Support</h1>
@if(session('success'))<p role="status" class="p-3 bg-green-50 rounded-lg">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@if($referral->professional && app(\App\Services\ConsentService::class)->valid($referral->session->seeker,'identity_disclosure',$referral->id))<p class="font-semibold">{{ $referral->professional->full_name }}</p>@endif
@include('partials.referral-appointments')
<a href="{{ route('seeker.referrals') }}" target="_top" class="text-green-700">View referral history</a>
</body></html>
