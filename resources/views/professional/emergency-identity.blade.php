<x-app-layout>
    <div class="max-w-xl mx-auto p-6 bg-white rounded-xl my-8">
        <h1 class="text-2xl font-bold">Emergency identity — Session #{{ $session->id }}</h1>
        <p class="my-4">This emergency access is logged and requires adviser review. Use these details only to respond to the immediate threat to life.</p>
        <dl>
            @foreach ($identity as $field => $value)
                <dt class="font-bold mt-3">{{ ucwords(str_replace('_', ' ', $field)) }}</dt>
                <dd>{{ $value ?: 'Not provided' }}</dd>
            @endforeach
        </dl>
    </div>
</x-app-layout>
