@if(count($resources))
<section class="mt-6 pt-5 border-t border-gray-200">
    <div class="flex justify-between items-center gap-3 mb-3">
        <h3 class="font-semibold text-gray-800">While you wait</h3>
        <a href="{{ route('selfhelp') }}" class="text-sm text-green-700 underline">View more resources</a>
    </div>
    <div class="request-resource-list">
        @foreach($resources as $resource)
        <a href="{{ $resource['link'] }}">
            <h4>{{ $resource['title'] }}</h4><p>{{ $resource['description'] }}</p>
            <span class="duration">{{ $resource['duration'] }}</span>
        </a>
        @endforeach
    </div>
</section>
@else
<p class="mt-4 text-sm"><a class="text-green-700 underline" href="{{ route('selfhelp') }}">Browse self-help resources</a></p>
@endif
