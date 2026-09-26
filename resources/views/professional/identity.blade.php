<x-app-layout>
    <div class="max-w-xl mx-auto p-6 bg-white rounded-xl my-8">
        <h1 class="text-2xl font-bold">Released identity — Referral #{{ $referral->id }}</h1>
        <p class="my-4">This access has been recorded. Use these details only for this approved referral.</p>
        <dl>
            @foreach ($identity as $field => $value)
                <dt class="font-bold mt-3">{{ ucwords(str_replace('_', ' ', $field)) }}</dt>
                <dd>{{ $value ?: 'Not provided' }}</dd>
            @endforeach
        </dl>
        <h2 class="font-semibold mt-5">Approved referral</h2>
        <p class="whitespace-pre-line">{{ $referral->referral_reason }}</p>
        @include('partials.referral-recommendation')
        <p class="text-sm mt-3">Adviser review: {{ $referral->review_notes }}</p>
        <form method="POST" action="{{ route('identity.acknowledge', $referral) }}" class="mt-6">
            @csrf
            <button class="bg-green-700 text-white p-3 rounded">Acknowledge receipt</button>
        </form>
    </div>
</x-app-layout>
