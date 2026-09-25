<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – {{ $meta['label'] }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    {{-- Loaded only for the shared sidebar/nav chrome, which still uses icon
         classes. No icon markup is used inside <main> on this page. --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0; --green-100: #DCF5E0; --green-200: #A8E0B0; --green-300: #6DCB80;
            --green-400: #30B650; --green-500: #04A052; --green-600: #038A45; --green-700: #027039;
            --green-800: #01562B; --green-900: #003D1E;
            --gray-50: #F9FAFB; --gray-100: #F3F4F6; --gray-200: #E5E7EB; --gray-300: #D1D5DB;
            --gray-400: #9CA3AF; --gray-500: #6B7280; --gray-600: #4B5563; --gray-700: #374151;
            --gray-800: #163B2D; --gray-900: #111827;
        }

        body { background: #F8FBF9; font-family: 'Inter', sans-serif; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--green-500); border-radius: 4px; }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .menu-btn {
            display: none; align-items: center; gap: 8px; background: #ffffff;
            border: 1px solid var(--gray-200); border-radius: 12px; color: var(--gray-600);
            font-size: 13px; font-weight: 600; padding: 8px 14px; cursor: pointer;
            transition: border-color 0.2s ease, color 0.2s ease;
        }
        .menu-btn::before {
            content: ''; width: 16px; height: 2px; border-radius: 2px;
            background: currentColor; box-shadow: 0 5px 0 currentColor, 0 -5px 0 currentColor;
        }
        .menu-btn:hover { border-color: var(--green-400); color: var(--green-700); }
        @media (max-width: 768px) { .menu-btn { display: inline-flex; } }

        .search-box {
            display: flex; align-items: center; gap: 10px;
            background: white; border: 1px solid var(--gray-200);
            border-radius: 14px; padding: 10px 16px; flex: 1; min-width: 220px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .search-box:focus-within { border-color: var(--green-500); box-shadow: 0 0 0 4px rgba(4,160,82,0.08); }
        .search-box input { border: none; outline: none; background: transparent; flex: 1; font-size: 14px; color: var(--gray-700); width: 100%; }

        .link-btn {
            display: inline-flex; align-items: center; background: white;
            border: 1px solid var(--gray-200); border-radius: 12px; color: var(--gray-600);
            font-size: 13px; font-weight: 600; padding: 9px 16px; cursor: pointer;
            text-decoration: none; white-space: nowrap;
            transition: border-color 0.2s ease, color 0.2s ease;
        }
        .link-btn:hover { border-color: var(--green-400); color: var(--green-700); }

        .page-header {
            background: white; border: 1px solid var(--gray-200); border-radius: 22px;
            padding: 26px 30px; margin-bottom: 24px;
            background-image: linear-gradient(120deg, #ffffff 60%, var(--green-50));
        }
        .page-header .eyebrow {
            display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--green-600); margin-bottom: 6px;
        }
        .page-header h1 { font-weight: 800; font-size: 22px; color: var(--gray-800); line-height: 1.3; }
        .page-header .meta { font-size: 13px; color: var(--gray-500); margin-top: 8px; }

        .resource-card {
            background: white; border: 1px solid var(--gray-200); border-radius: 20px;
            padding: 20px; display: flex; flex-direction: column; text-decoration: none; height: 100%;
            transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
        }
        .resource-card:hover { transform: translateY(-3px); box-shadow: 0 14px 40px rgba(4,160,82,0.10); border-color: var(--green-300); }
        .resource-card:focus-visible { outline: 3px solid var(--green-500); outline-offset: 2px; }

        .kind {
            align-self: flex-start; font-size: 10px; font-weight: 700; letter-spacing: 0.06em;
            text-transform: uppercase; color: var(--green-700); background: var(--green-50);
            border: 1px solid var(--green-100); padding: 3px 10px; border-radius: 20px; margin-bottom: 12px;
        }
        .resource-card h2 { font-weight: 700; font-size: 16px; color: var(--gray-800); margin-bottom: 6px; line-height: 1.35; }
        .resource-card .summary { font-size: 13px; color: var(--gray-500); line-height: 1.65; margin-bottom: 14px; flex: 1; }

        .meta { display: flex; align-items: center; gap: 8px; font-size: 11px; color: var(--gray-500); flex-wrap: wrap; }
        .meta .chip { background: var(--gray-100); padding: 3px 10px; border-radius: 20px; font-weight: 500; }
        .meta .chip.saved { background: var(--green-50); color: var(--green-700); font-weight: 600; }
        .meta .chip.tag { color: var(--gray-400); }

        .empty-state { background: white; border: 1px dashed var(--gray-300); border-radius: 20px; padding: 40px 24px; text-align: center; }

        .btn-primary {
            display: inline-flex; align-items: center; justify-content: center; margin-top: 16px;
            font-weight: 600; font-size: 14px; padding: 10px 20px; border-radius: 12px;
            border: none; cursor: pointer; text-decoration: none; color: white;
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
        }

        :focus-visible { outline: 3px solid var(--green-500); outline-offset: 2px; border-radius: 6px; }
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .page-header { padding: 20px; }
        }
    </style>
</head>
<body>

    <main class="main-content">

        {{-- Top bar --}}
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <button type="button" class="menu-btn" id="hamburgerBtn">Menu</button>
                <div>
                    <a href="{{ route('selfhelp') }}" class="link-btn">All resources</a>
                    <h1 class="text-xl md:text-2xl font-extrabold text-gray-800 mt-2">{{ $meta['label'] }}</h1>
                </div>
            </div>
            <form method="GET" action="{{ route('selfhelp.category', $category) }}" class="flex items-center gap-2 w-full md:w-auto" role="search">
                <label for="categorySearch" class="sr-only">Search {{ strtolower($meta['label']) }}</label>
                <div class="search-box">
                    <input type="search" id="categorySearch" name="q" value="{{ $search }}"
                           placeholder="Search {{ strtolower($meta['label']) }}">
                </div>
                @if($search !== '')
                    <a href="{{ route('selfhelp.category', $category) }}" class="link-btn">Clear</a>
                @endif
            </form>
        </div>

        {{-- Category header --}}
        <div class="page-header">
            <span class="eyebrow">Format</span>
            <h2>{{ $meta['label'] }}</h2>
            <p class="meta">
                {{ $resources->count() }} {{ $resources->count() === 1 ? 'resource' : 'resources' }} available
                @if($search !== '')
                    &mdash; searching &ldquo;<strong>{{ $search }}</strong>&rdquo;
                @endif
            </p>
        </div>

        @if($resources->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($resources as $resource)
                    <a href="{{ route('selfhelp.show', $resource->id) }}" class="resource-card">
                        <span class="kind">{{ $meta['label'] }}</span>
                        <h2>{{ $resource->title }}</h2>
                        <p class="summary">{{ \Illuminate\Support\Str::limit($resource->description, 100) }}</p>
                        <div class="meta">
                            @if($resource->duration)
                                <span class="chip">{{ $resource->duration }}</span>
                            @endif
                            <span class="chip">{{ $resource->difficulty_label }}</span>
                            @foreach(array_slice($resource->tags_list, 0, 2) as $tag)
                                <span class="chip tag">#{{ $tag }}</span>
                            @endforeach
                            @if(in_array($resource->id, $savedIds))
                                <span class="chip saved">Saved</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <h3 class="font-semibold text-gray-800">No resources found</h3>
                <p class="text-sm text-gray-500 mt-1">Try a different search, or check back soon.</p>
                @if($search !== '')
                    <a href="{{ route('selfhelp.category', $category) }}" class="btn-primary">Clear search</a>
                @else
                    <a href="{{ route('selfhelp') }}" class="btn-primary">Browse all resources</a>
                @endif
            </div>
        @endif

    </main>

    @include('partials.sidebar', [
        'active' => ['selfhelp*'],
        'role'   => 'Help Seeker',
    ])

    @include('layouts.partials.pwa-banner')

</body>
</html>
