@extends('layouts.app')
@section('content')
    @include('partials.referral-ui-styles')
    <div class="referral-ui" style="max-width:1000px;margin:24px auto;padding:0 16px"><section class="ru-card">
        <h1 class="text-2xl font-bold">Released identity — Referral #{{ $referral->id }}</h1>
        <p class="my-4">This access has been recorded. Use these details only for this approved referral.</p>
        <a class="inline-block text-green-700 my-3" href="{{ route('professional.cases.show', $referral->case_reference) }}">Back to case</a>
        <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
            @foreach ($identity as $field => $value)
                <div style="padding:14px;background:#f5faf7;border-radius:10px;overflow-wrap:anywhere"><dt class="text-sm text-gray-500">{{ ucwords(str_replace('_', ' ', $field)) }}</dt>
                <dd>{{ $value ?: 'Not provided' }}</dd></div>
            @endforeach
        </dl>
        </section><section class="ru-card"><h2>Approved referral</h2>
        <p class="whitespace-pre-line">{{ $referral->referral_reason }}</p>
        @include('partials.referral-recommendation')
        <p class="text-sm mt-3">Adviser review: {{ $referral->review_notes }}</p>
        <form method="POST" action="{{ route('identity.acknowledge', $referral) }}" class="mt-6">
            @csrf
            <button class="bg-green-700 text-white p-3 rounded">Acknowledge receipt</button>
        </form>
    </section></div>
@endsection
