@extends('layouts.helper')

@section('title', 'Resources')

@section('heading', 'Resource Library')
@section('subheading', 'Curated growth resources to support your practice.')

@section('content')

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
        <form method="GET" action="{{ route('helper.resources') }}" style="display:flex;gap:8px;flex:1;min-width:220px;">
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search resources..." style="flex:1;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
        <a href="{{ route('helper.resources') }}" class="btn btn-secondary" style="{{ $activeCategory ? '' : 'background:var(--green-500);color:white;border-color:var(--green-500);' }}">All</a>
        @foreach($categories as $category)
            <a href="{{ route('helper.resources', ['category' => $category]) }}" class="btn btn-secondary" style="{{ $activeCategory === $category ? 'background:var(--green-500);color:white;border-color:var(--green-500);' : '' }}">{{ $category }}</a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($resources as $resource)
            <div class="card" style="display:flex;flex-direction:column;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:var(--green-50);display:flex;align-items:center;justify-content:center;font-size:24px;">{{ $resource['icon'] }}</div>
                    <div>
                        <div class="font-semibold text-gray-800" style="font-size:15px;">{{ $resource['title'] }}</div>
                        <div style="display:flex;gap:6px;margin-top:2px;flex-wrap:wrap;">
                            <span class="pill">{{ $resource['category'] }}</span>
                            @if($resource['featured'])
                                <span class="pill" style="background:#FEF3C7;color:#B45309;">★ Featured</span>
                            @endif
                        </div>
                    </div>
                </div>
                <p class="text-sm text-gray-500" style="flex:1;">{{ Illuminate\Support\Str::limit($resource['description'], 130) }}</p>
                <hr class="divider">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div class="text-xs text-gray-400">
                        <i class="far fa-clock mr-1"></i>{{ $resource['duration'] ?? '—' }}
                        <span class="ml-2"><i class="far fa-eye mr-1"></i>{{ $resource['views'] }}</span>
                    </div>
                    <a href="{{ route('selfhelp.show', ['id' => $resource['id']]) }}" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-external-link-alt"></i> Open</a>
                </div>
            </div>
        @empty
            <div class="card" style="grid-column:1/-1;">
                <div class="empty-state">
                    <i class="fas fa-book"></i>
                    <h3>No resources found</h3>
                    <p>Try a different category or search term.</p>
                </div>
            </div>
        @endforelse
    </div>

    {{ $resources->links() }}

@endsection