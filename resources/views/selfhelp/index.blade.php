<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Self-Help Tools</title>

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
            --card-bg: #ffffff; --page-bg: #F8FBF9;
        }

        body { background: var(--page-bg); font-family: 'Inter', sans-serif; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--green-500); border-radius: 4px; }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        /* Text-only controls. The menu button keeps the id that sidebar.js binds. */
        .menu-btn {
            display: none; align-items: center; gap: 8px; background: var(--card-bg);
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
            background: var(--card-bg); border: 1px solid var(--gray-200);
            border-radius: 14px; padding: 10px 16px; flex: 1; min-width: 220px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .search-box:focus-within { border-color: var(--green-500); box-shadow: 0 0 0 4px rgba(4,160,82,0.08); }
        .search-box input { border: none; outline: none; background: transparent; flex: 1; font-size: 14px; color: var(--gray-700); width: 100%; }
        .search-box input::placeholder { color: var(--gray-400); }

        .link-btn {
            display: inline-flex; align-items: center; background: var(--card-bg);
            border: 1px solid var(--gray-200); border-radius: 12px; color: var(--gray-600);
            font-size: 13px; font-weight: 600; padding: 9px 16px; cursor: pointer;
            text-decoration: none; white-space: nowrap;
            transition: border-color 0.2s ease, color 0.2s ease;
        }
        .link-btn:hover { border-color: var(--green-400); color: var(--green-700); }

        .btn-primary {
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 14px; padding: 11px 22px; border-radius: 12px;
            border: none; cursor: pointer; text-decoration: none;
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white; box-shadow: 0 4px 16px rgba(4,160,82,0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(4,160,82,0.35); }

        .flash-banner {
            background: var(--green-50); border: 1px solid var(--green-200); color: var(--green-800);
            border-radius: 14px; padding: 12px 18px; font-size: 14px; font-weight: 500;
            display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
        }
        .flash-banner button {
            margin-left: auto; background: none; border: none; cursor: pointer;
            color: var(--green-700); font-size: 12px; font-weight: 600; text-decoration: underline;
        }

        /* Section blocks */
        .support-section { margin-bottom: 40px; }
        .support-section header { border-bottom: 1px solid var(--gray-200); padding-bottom: 12px; margin-bottom: 18px; }
        .section-eyebrow {
            display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--green-600); margin-bottom: 6px;
        }
        .section-title { font-weight: 800; font-size: 20px; color: var(--gray-800); line-height: 1.3; }
        .section-description { font-size: 14px; color: var(--gray-500); margin-top: 6px; max-width: 62ch; line-height: 1.65; }
        .section-count { font-size: 12px; font-weight: 600; color: var(--gray-400); white-space: nowrap; }

        .resource-card {
            background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 20px;
            padding: 20px; display: flex; flex-direction: column; text-decoration: none;
            height: 100%; transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
        }
        .resource-card:hover { transform: translateY(-3px); box-shadow: 0 14px 40px rgba(4,160,82,0.10); border-color: var(--green-300); }
        .resource-card:focus-visible { outline: 3px solid var(--green-500); outline-offset: 2px; }

        .card-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .kind {
            font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
            color: var(--green-700); background: var(--green-50);
            border: 1px solid var(--green-100); padding: 3px 10px; border-radius: 20px;
        }
        .badge-featured {
            font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
            color: var(--green-800); background: var(--green-100);
            padding: 3px 10px; border-radius: 20px;
        }
        .resource-card h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); margin-bottom: 6px; line-height: 1.35; }
        .resource-card .summary { font-size: 13px; color: var(--gray-500); line-height: 1.65; margin-bottom: 14px; flex: 1; }

        .meta { display: flex; align-items: center; gap: 8px; font-size: 11px; color: var(--gray-500); flex-wrap: wrap; }
        .meta .chip { background: var(--gray-100); padding: 3px 10px; border-radius: 20px; font-weight: 500; }
        .meta .chip.saved { background: var(--green-50); color: var(--green-700); font-weight: 600; }
        .meta .chip.tag { color: var(--gray-400); }

        .progress-track { height: 6px; background: var(--gray-100); border-radius: 20px; overflow: hidden; }
        .progress-track .fill { height: 100%; background: linear-gradient(90deg, var(--green-400), var(--green-600)); border-radius: 20px; transition: width 0.5s ease; }

        .progress-card {
            background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 20px;
            padding: 18px; display: block; text-decoration: none;
            transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
        }
        .progress-card:hover { transform: translateY(-3px); box-shadow: 0 10px 32px rgba(0,0,0,0.06); border-color: var(--green-300); }
        .progress-card:focus-visible { outline: 3px solid var(--green-500); outline-offset: 2px; }

        .type-card {
            background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 18px;
            padding: 16px 18px; display: block; text-decoration: none;
            transition: border-color 0.25s ease, transform 0.25s ease;
        }
        .type-card:hover { transform: translateY(-2px); border-color: var(--green-300); }
        .type-card:focus-visible { outline: 3px solid var(--green-500); outline-offset: 2px; }
        .type-card .label { font-weight: 700; font-size: 15px; color: var(--gray-800); }
        .type-card .count { font-size: 12px; color: var(--gray-400); margin-top: 4px; }

        .empty-state { background: var(--card-bg); border: 1px dashed var(--gray-300); border-radius: 20px; padding: 36px 24px; text-align: center; }

        a, button { -webkit-tap-highlight-color: transparent; }
        :focus-visible { outline: 3px solid var(--green-500); outline-offset: 2px; border-radius: 6px; }

        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
        }
    </style>
</head>
<body>

    <main class="main-content">

        @if(session('success'))
            <div class="flash-banner" role="status" aria-live="polite">
                <span>{{ session('success') }}</span>
                <button type="button" onclick="this.parentElement.remove()">Dismiss</button>
            </div>
        @endif

        {{-- Page header and search --}}
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <button type="button" class="menu-btn" id="hamburgerBtn">Menu</button>
                <div>
                    <h1 class="text-xl md:text-2xl font-extrabold text-gray-800">Self-Help Tools</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Exercises, reading, and tools to support your wellbeing.</p>
                </div>
            </div>
            <form method="GET" action="{{ route('selfhelp') }}" class="flex items-center gap-2 w-full md:w-auto" role="search">
                <label for="selfhelpSearch" class="sr-only">Search self-help resources</label>
                <div class="search-box">
                    <input type="search" id="selfhelpSearch" name="q" value="{{ $search }}"
                           placeholder="Search by title or tag, for example anxiety or sleep">
                </div>
                @if($search !== '')
                    <a href="{{ route('selfhelp') }}" class="link-btn">Clear</a>
                @endif
            </form>
        </div>

        @if($search !== '')
            <div class="mb-6 bg-white rounded-2xl border border-gray-200 p-4" role="status">
                <p class="text-sm text-gray-600">
                    Results for <strong>&ldquo;{{ $search }}&rdquo;</strong>
                    <span class="text-gray-400">&mdash; {{ $all->count() }} {{ $all->count() === 1 ? 'resource' : 'resources' }} found</span>
                </p>
            </div>
        @endif

        @if($search === '')
            {{-- Continue where you left off --}}
            @if($continueProgress->count() > 0)
                <div class="mb-8">
                    <h2 class="section-title mb-1">Continue where you left off</h2>
                    <p class="section-subtitle text-sm text-gray-500 mb-4">Pick up where you stopped.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($continueProgress as $entry)
                            @php $r = $entry->resource; @endphp
                            <a href="{{ route('selfhelp.show', $r->id) }}" class="progress-card">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-semibold text-sm text-gray-800">{{ $r->title }}</h3>
                                    <span class="text-xs font-bold text-[#04A052] whitespace-nowrap">{{ $entry->progress_percentage }}%</span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1 mb-2">
                                    {{ $categoryLabels[$r->category] ?? ucfirst($r->category) }} @if($r->duration) &middot; {{ $r->duration }} @endif
                                </p>
                                <div class="progress-track" role="progressbar" aria-valuenow="{{ $entry->progress_percentage }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $r->title }} progress">
                                    <div class="fill" style="width: {{ $entry->progress_percentage }}%"></div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Saved resources --}}
            <div class="mb-8">
                <h2 class="section-title mb-1">Saved resources</h2>
                <p class="section-subtitle text-sm text-gray-500 mb-4">Your personal library.</p>
                @if($savedResources->count() > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach($savedResources as $resource)
                            <a href="{{ route('selfhelp.show', $resource->id) }}" class="resource-card">
                                <div class="card-top">
                                    <span class="kind">{{ $categoryLabels[$resource->category] ?? ucfirst($resource->category) }}</span>
                                    <span class="chip saved">Saved</span>
                                </div>
                                <h3>{{ \Illuminate\Support\Str::limit($resource->title, 44) }}</h3>
                                <p class="summary">{{ \Illuminate\Support\Str::limit($resource->description, 70) }}</p>
                                @if($resource->duration)
                                    <div class="meta"><span class="chip">{{ $resource->duration }}</span></div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">
                        <p class="text-sm text-gray-500">You have not saved any resources yet.</p>
                        <p class="text-xs text-gray-400 mt-1">Use the Save button on any resource to keep it here.</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Support sections: the main way the library is organised --}}
        <div class="mb-8">
            <h2 class="section-title mb-1">{{ $search === '' ? 'Browse by what you need' : 'Matching resources' }}</h2>
            <p class="section-subtitle text-sm text-gray-500 mb-6">
                Grouped by the kind of support each resource offers.
            </p>

            @forelse($sections as $section)
                <section class="support-section" aria-labelledby="section-{{ $section['key'] }}">
                    <header class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <span class="section-eyebrow">Section</span>
                            <h3 class="section-title" id="section-{{ $section['key'] }}">{{ $section['label'] }}</h3>
                            <p class="section-description">{{ $section['description'] }}</p>
                        </div>
                        <span class="section-count">
                            {{ $section['resources']->count() }} {{ $section['resources']->count() === 1 ? 'resource' : 'resources' }}
                        </span>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($section['resources'] as $resource)
                            <a href="{{ route('selfhelp.show', $resource->id) }}" class="resource-card">
                                <div class="card-top">
                                    <span class="kind">{{ $categoryLabels[$resource->category] ?? ucfirst($resource->category) }}</span>
                                    @if(in_array($resource->id, $featuredIds, true))
                                        <span class="badge-featured">Featured</span>
                                    @endif
                                    @if(in_array($resource->id, $savedIds, true))
                                        <span class="meta-saved chip saved">Saved</span>
                                    @endif
                                </div>
                                <h3>{{ $resource->title }}</h3>
                                <p class="summary">{{ \Illuminate\Support\Str::limit($resource->description, 100) }}</p>
                                <div class="meta">
                                    @if($resource->duration)
                                        <span class="chip">{{ $resource->duration }}</span>
                                    @endif
                                    <span class="chip">{{ $resource->difficulty_label }}</span>
                                    @foreach(array_slice($resource->tags_list, 0, 2) as $tag)
                                        <span class="chip tag">#{{ $tag }}</span>
                                    @endforeach
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="empty-state">
                    <h3 class="font-semibold text-gray-800">No resources found</h3>
                    <p class="text-sm text-gray-500 mt-1">Try a different keyword such as anxiety, sleep, or focus.</p>
                    <a href="{{ route('selfhelp') }}" class="btn-primary mt-4">Back to all resources</a>
                </div>
            @endforelse
        </div>

        {{-- Content types, kept as a secondary filter --}}
        @if($categories->count() > 0)
            <div class="mb-8">
                <h2 class="section-title mb-1">Browse by format</h2>
                <p class="section-subtitle text-sm text-gray-500 mb-4">Filter the library by the kind of resource it is.</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach($categories as $cat)
                        <a href="{{ route('selfhelp.category', $cat['slug']) }}" class="type-card">
                            <span class="label">{{ $cat['label'] }}</span>
                            <span class="count">{{ $cat['count'] }} {{ $cat['count'] === 1 ? 'item' : 'items' }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            Small steps every day add up. You are doing great.
        </p>

    </main>

    @include('partials.sidebar', [
        'active' => ['selfhelp*'],
        'role'   => 'Help Seeker',
    ])

    @include('layouts.partials.pwa-banner')

</body>
</html>
