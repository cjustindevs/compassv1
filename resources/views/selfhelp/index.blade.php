<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Self-Help Tools</title>
    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --green-50: #EAF8F0; --green-100: #DCF5E0; --green-200: #A8E0B0; --green-300: #6DCB80;
            --green-400: #30B650; --green-500: #04A052; --green-600: #038A45; --green-700: #027039;
            --green-800: #01562B; --gray-100: #F1F4F2; --gray-200: #E2E8E4; --gray-300: #CBD5CF;
            --gray-400: #94A39A; --gray-500: #64746B; --gray-600: #4B5B52; --gray-800: #163B2D;
            --card-bg: #ffffff; --page-bg: #F8FBF9;
        }
        body { background: var(--page-bg); }
        .main-content { margin-left: var(--sidebar-w, 264px); padding: 24px 32px 80px; min-height: 100vh; }
        .menu-btn { display: none; align-items: center; gap: 8px; background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 12px; color: var(--gray-600); font-size: 13px; font-weight: 600; padding: 8px 14px; cursor: pointer; }
        .menu-btn::before { content: ''; width: 16px; height: 2px; border-radius: 2px; background: currentColor; box-shadow: 0 5px 0 currentColor, 0 -5px 0 currentColor; }
        @media (max-width: 768px) { .menu-btn { display: inline-flex; } }

        .sh-wrap { max-width: 1180px; display: grid; gap: 28px; }
        .sh-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .sh-head h1 { font-size: 26px; font-weight: 800; color: var(--gray-800); }
        .sh-head p { font-size: 14px; color: var(--gray-500); margin-top: 4px; }
        .sh-search { display: flex; gap: 8px; flex: 0 1 420px; min-width: 240px; }
        .search-box { display: flex; align-items: center; background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 12px; padding: 10px 14px; flex: 1; }
        .search-box:focus-within { border-color: var(--green-500); box-shadow: 0 0 0 4px rgba(4,160,82,.08); }
        .search-box input { border: none; outline: none; background: transparent; flex: 1; font-size: 14px; color: var(--gray-800); width: 100%; }
        .link-btn { display: inline-flex; align-items: center; background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 12px; color: var(--gray-600); font-size: 13px; font-weight: 600; padding: 9px 14px; text-decoration: none; white-space: nowrap; }
        .link-btn:hover { border-color: var(--green-400); color: var(--green-700); }
        .btn-primary { display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; padding: 10px 20px; border-radius: 12px; text-decoration: none; background: var(--green-600); color: #fff; }
        .flash-banner { background: var(--green-50); border: 1px solid var(--green-200); color: var(--green-800); border-radius: 12px; padding: 12px 16px; font-size: 14px; display: flex; gap: 12px; align-items: center; }
        .flash-banner button { margin-left: auto; background: none; border: none; color: var(--green-700); font-weight: 600; text-decoration: underline; cursor: pointer; }

        .sh-block-title { font-size: 17px; font-weight: 800; color: var(--gray-800); }
        .sh-block-sub { font-size: 13px; color: var(--gray-500); margin-top: 2px; }

        /* Your progress */
        .sh-mine { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 16px; }
        .sh-panel { background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 16px; padding: 18px; display: grid; gap: 12px; align-content: start; }
        .sh-continue { display: grid; gap: 10px; }
        .sh-continue a { display: grid; grid-template-columns: 56px minmax(0, 1fr); gap: 12px; align-items: center; text-decoration: none; padding: 8px; margin: -8px; border-radius: 12px; }
        .sh-continue a:hover { background: var(--page-bg); }
        .sh-continue .sh-art { width: 56px; height: 56px; border-radius: 12px; }
        .sh-continue strong { display: block; font-size: 14px; color: var(--gray-800); }
        .sh-continue span { font-size: 12px; color: var(--gray-500); }
        .sh-bar { height: 6px; background: var(--gray-100); border-radius: 99px; overflow: hidden; margin-top: 6px; }
        .sh-bar > i { display: block; height: 100%; background: var(--green-500); border-radius: 99px; }
        .sh-saved { display: grid; gap: 2px; }
        .sh-saved a { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-top: 1px solid var(--gray-100); text-decoration: none; font-size: 14px; color: var(--gray-800); }
        .sh-saved a:first-child { border-top: 0; }
        .sh-saved small { color: var(--gray-400); font-size: 12px; white-space: nowrap; }
        .sh-empty { font-size: 13px; color: var(--gray-500); }

        /* Filters */
        .sh-filters { display: grid; gap: 10px; }
        .sh-chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .sh-chips-label { font-size: 12px; font-weight: 600; color: var(--gray-500); margin-right: 4px; }
        .sh-chip { border: 1px solid var(--gray-200); background: var(--card-bg); color: var(--gray-600); font-size: 13px; font-weight: 600; padding: 7px 14px; border-radius: 99px; cursor: pointer; text-decoration: none; }
        .sh-chip:hover { border-color: var(--green-400); color: var(--green-700); }
        .sh-chip[aria-pressed="true"] { background: var(--green-700); border-color: var(--green-700); color: #fff; }
        .sh-chip small { font-weight: 500; opacity: .75; }

        /* Topic sections */
        .support-section { display: grid; gap: 14px; }
        .support-section[hidden] { display: none; }
        .support-section header { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; }
        .section-title { font-weight: 800; font-size: 18px; color: var(--gray-800); }
        .section-description { font-size: 13px; color: var(--gray-500); margin-top: 2px; max-width: 70ch; }
        .section-count { font-size: 12px; font-weight: 600; color: var(--gray-400); }
        .sh-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
        .resource-card { background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; text-decoration: none; transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
        .resource-card:hover { border-color: var(--green-300); box-shadow: 0 10px 28px rgba(4,160,82,.10); transform: translateY(-2px); }
        .resource-card .sh-art { display: block; width: 100%; height: 96px; }
        .resource-card .body { padding: 14px 16px 16px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .card-top { display: flex; gap: 6px; flex-wrap: wrap; }
        .kind, .badge-featured, .chip { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 99px; background: var(--gray-100); color: var(--gray-600); }
        .kind { background: var(--green-50); color: var(--green-700); }
        .badge-featured { background: #fff4dc; color: #8a5a00; }
        .chip.saved { background: var(--green-50); color: var(--green-700); }
        .resource-card h3 { font-size: 15px; font-weight: 700; color: var(--gray-800); line-height: 1.35; }
        .resource-card .summary { font-size: 13px; color: var(--gray-500); line-height: 1.55; flex: 1; }
        .meta { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .meta .done { color: var(--green-700); background: var(--green-50); }
        .resource-card[hidden] { display: none; }
        /* selfhelp-mobile.css pads cards and buttons for phones; picture cards and chips opt out. */
        .main-content.selfhelp-content .resource-card { padding: 0; height: auto; }
        .main-content.selfhelp-content .sh-chip { min-height: 0; }
        .topic-label { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--green-700); }
        .sh-topic-note { font-size: 14px; color: var(--gray-600); background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 12px; padding: 12px 16px; }
        .sh-topic-note strong { color: var(--gray-800); }
        .sh-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
        .empty-state { background: var(--card-bg); border: 1px dashed var(--gray-300); border-radius: 16px; padding: 32px 24px; text-align: center; }
        .sh-foot { text-align: center; font-size: 13px; color: var(--gray-400); border-top: 1px solid var(--gray-200); padding-top: 20px; }

        :focus-visible { outline: 3px solid var(--green-500); outline-offset: 2px; border-radius: 6px; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } .sh-mine { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px 16px 100px; } .sh-search { flex-basis: 100%; } }
    </style>
<link rel="stylesheet" href="{{ asset('css/selfhelp-mobile.css') }}?v={{ filemtime(public_path('css/selfhelp-mobile.css')) }}">
    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
</head>
<body>
    <main class="main-content selfhelp-content">
        <div class="sh-wrap">
            @if(session('success'))
                <div class="flash-banner" role="status" aria-live="polite"><span>{{ session('success') }}</span><button type="button" onclick="this.parentElement.remove()">Dismiss</button></div>
            @endif

            <header class="sh-head">
                <div style="display:flex;align-items:center;gap:12px">
                    <button type="button" class="menu-btn" id="hamburgerBtn" aria-label="Open navigation" aria-controls="sidebar">Menu</button>
                    <div>
                        <h1>Self-Help Tools</h1>
                        <p>Short exercises and readings you can do on your own. Each one has steps, sound, and a picture guide.</p>
                    </div>
                </div>
                <form method="GET" action="{{ route('selfhelp') }}" class="sh-search" role="search">
                    <label for="selfhelpSearch" class="sr-only">Search self-help resources</label>
                    <div class="search-box"><input type="search" id="selfhelpSearch" name="q" value="{{ $search }}" placeholder="Search, for example anxiety or sleep"></div>
                    @if($search !== '')<a href="{{ route('selfhelp') }}" class="link-btn">Clear</a>@endif
                </form>
            </header>

            @if($search !== '')
                <p class="sh-block-sub" role="status">Results for <strong>&ldquo;{{ $search }}&rdquo;</strong> · {{ $all->count() }} {{ $all->count() === 1 ? 'resource' : 'resources' }} found</p>
            @else
                {{-- Your progress and saved items, side by side --}}
                <div class="sh-mine">
                    <section class="sh-panel" aria-labelledby="continueTitle">
                        <div><h2 class="sh-block-title" id="continueTitle">Continue where you left off</h2><p class="sh-block-sub">Your step progress is saved as you go.</p></div>
                        @if($continueProgress->count() > 0)
                            <div class="sh-continue">
                                @foreach($continueProgress as $entry)
                                    @php($r = $entry->resource)
                                    @php($pct = (int) $entry->progress_percentage)
                                    <a href="{{ route('selfhelp.show', $r->id) }}">
                                        <x-selfhelp-art :topic="app(\App\Http\Controllers\SelfHelpController::class)->sectionKeyFor($r)" size="thumb" />
                                        <div>
                                            <strong>{{ $r->title }}</strong>
                                            <span>{{ $pct > 0 ? $pct.'% done' : 'Opened, not started' }}@if($r->duration) · {{ $r->duration }}@endif</span>
                                            <div class="sh-bar" role="progressbar" aria-label="{{ $r->title }} progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><i style="width:{{ max($pct, 2) }}%"></i></div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="sh-empty">Nothing in progress. Pick any resource below to start.</p>
                        @endif
                    </section>
                    <section class="sh-panel" aria-labelledby="savedTitle">
                        <div><h2 class="sh-block-title" id="savedTitle">Saved resources</h2><p class="sh-block-sub">Your personal library.</p></div>
                        @if($savedResources->count() > 0)
                            <div class="sh-saved">
                                @foreach($savedResources as $resource)
                                    <a href="{{ route('selfhelp.show', $resource->id) }}"><span>{{ \Illuminate\Support\Str::limit($resource->title, 44) }}</span><small>{{ $resource->duration ?: ($categoryLabels[$resource->category] ?? ucfirst($resource->category)) }}</small></a>
                                @endforeach
                            </div>
                        @else
                            <p class="sh-empty">You have not saved any resources yet. Use Save on any resource to keep it here.</p>
                        @endif
                    </section>
                </div>
            @endif

            {{-- Library: filter by topic (on this page) or by format (separate page) --}}
            <section class="sh-filters" aria-labelledby="libraryTitle">
                <div><h2 class="sh-block-title" id="libraryTitle">{{ $search === '' ? 'Browse by what you need' : 'Matching resources' }}</h2><p class="sh-block-sub">Grouped by the kind of support each resource offers.</p></div>
                @if(count($sections) > 0)
                    <div class="sh-chips" role="group" aria-label="Filter by topic">
                        <span class="sh-chips-label">Topic</span>
                        <button type="button" class="sh-chip" data-topic-filter="all" aria-pressed="true">All topics</button>
                        @foreach($sections as $section)
                            <button type="button" class="sh-chip" data-topic-filter="{{ $section['key'] }}" aria-pressed="false">{{ $section['label'] }} <small>{{ $section['resources']->count() }}</small></button>
                        @endforeach
                    </div>
                @endif
                @if($categories->count() > 0)
                    <nav class="sh-chips" aria-label="Browse by format">
                        <span class="sh-chips-label">Format</span>
                        @foreach($categories as $cat)
                            <a class="sh-chip" href="{{ route('selfhelp.category', $cat['slug']) }}">{{ $cat['label'] }} <small>{{ $cat['count'] }}</small></a>
                        @endforeach
                    </nav>
                @endif
            </section>

            @if(count($sections))
                {{-- One grid for every resource; the topic chips above filter it. --}}
                @foreach($sections as $section)
                    <p class="sh-topic-note" data-topic-note="{{ $section['key'] }}" hidden><strong>{{ $section['label'] }}.</strong> {{ $section['description'] }}</p>
                @endforeach
                <div class="sh-grid" id="resourceGrid">
                    @foreach($sections as $section)
                        @foreach($section['resources'] as $resource)
                            <a href="{{ route('selfhelp.show', $resource->id) }}" class="resource-card" data-topic="{{ $section['key'] }}">
                                <x-selfhelp-art :topic="$section['key']" />
                                <div class="body">
                                    <span class="topic-label">{{ $section['label'] }}</span>
                                    <h3>{{ $resource->title }}</h3>
                                    <p class="summary">{{ \Illuminate\Support\Str::limit($resource->description, 100) }}</p>
                                    <div class="meta">
                                        <span class="kind">{{ $categoryLabels[$resource->category] ?? ucfirst($resource->category) }}</span>
                                        @if($resource->duration && strtoupper($resource->duration) !== 'N/A')<span class="chip">{{ $resource->duration }}</span>@endif
                                        <span class="chip">{{ $resource->difficulty_label }}</span>
                                        @if(in_array($resource->id, $featuredIds, true))<span class="badge-featured">Featured</span>@endif
                                        @if(in_array($resource->id, $savedIds, true))<span class="meta-saved chip saved">Saved</span>@endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <h3 class="section-title">No resources found</h3>
                    <p class="sh-block-sub">Try a different keyword such as anxiety, sleep, or focus.</p>
                    <a href="{{ route('selfhelp') }}" class="btn-primary" style="margin-top:14px">Back to all resources</a>
                </div>
            @endif

            <p class="sh-foot">Small steps every day add up. You are doing great.</p>
        </div>
    </main>

    @include('partials.sidebar', ['active' => ['selfhelp*'], 'role' => 'Help Seeker'])
    @include('layouts.partials.pwa-banner')
    <script>
        // Topic chips show one topic at a time; every resource stays in the page for search and no-JS use.
        (() => {
            const chips = document.querySelectorAll('[data-topic-filter]');
            const sections = document.querySelectorAll('#resourceGrid [data-topic]');
            const notes = document.querySelectorAll('[data-topic-note]');
            chips.forEach((chip) => chip.addEventListener('click', () => {
                const topic = chip.dataset.topicFilter;
                chips.forEach((c) => c.setAttribute('aria-pressed', String(c === chip)));
                sections.forEach((s) => { s.hidden = topic !== 'all' && s.dataset.topic !== topic; });
                notes.forEach((n) => { n.hidden = n.dataset.topicNote !== topic; });
            }));
        })();
    </script>
</body>
</html>
