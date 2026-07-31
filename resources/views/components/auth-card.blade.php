@props(['header' => null])

<div {{ $attributes->merge(['class' => 'max-w-md w-full mx-auto bg-white rounded-2xl shadow-lg overflow-hidden']) }}>
    @if ($header)
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            {{ $header }}
        </div>
    @endif

    <div class="p-6">
        {{ $slot }}
    </div>
</div>