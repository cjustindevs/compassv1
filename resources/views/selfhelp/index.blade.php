<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Self-Help Tools</title>

    <script src="https://cdn.tailwindcss.com"></script>
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

        .search-box {
            display: flex; align-items: center; gap: 10px;
            background: var(--card-bg); border: 1px solid var(--gray-200);
            border-radius: 14px; padding: 10px 16px; flex: 1; min-width: 220px;
            transition: all 0.2s ease;
        }
        .search-box:focus-within { border-color: var(--green-500); box-shadow: 0 0 0 4px rgba(4,160,82,0.08); }
        .search-box i { color: var(--gray-400); }
        .search-box input { border: none; outline: none; background: transparent; flex: 1; font-size: 14px; color: var(--gray-700); width: 100%; }

        .category-card {
            background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 20px;
            padding: 18px; display: flex; align-items: center; gap: 14px;
            text-decoration: none; transition: all 0.3s ease; cursor: pointer;
        }
        .category-card:hover { transform: translateY(-4px); box-shadow: 0 12px 36px rgba(4,160,82,0.10); border-color: var(--green-300); }
        .category-card .icon {
            width: 48px; height: 48px; border-radius: 14px; font-size: 22px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .cat-purple .icon { background: #EDE9FE; } .cat-purple:hover { border-color: #A78BFA; }
        .cat-blue .icon { background: #DBEAFE; } .cat-blue:hover { border-color: #60A5FA; }
        .cat-green .icon { background: var(--green-50); } .cat-green:hover { border-color: var(--green-300); }
        .cat-amber .icon { background: #FEF3C7; } .cat-amber:hover { border-color: #FBBF24; }
        .cat-red .icon { background: #FEE2E2; } .cat-red:hover { border-color: #F87171; }
        .category-card .count { font-size: 11px; font-weight: 600; color: var(--gray-400); }

        .resource-card {
            background: var(--card-bg); border-radius: 24px; padding: 22px;
            border: 1px solid var(--gray-200); transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer; position: relative; overflow: hidden; display: flex; flex-direction: column;
            text-decoration: none;
        }
        .resource-card::after {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--green-400), var(--green-500), var(--green-600));
            opacity: 0; transition: opacity 0.3s ease;
        }
        .resource-card:hover { transform: translateY(-6px); box-shadow: 0 20px 60px rgba(4, 160, 82, 0.10); border-color: var(--green-300); }
        .resource-card:hover::after { opacity: 1; }
        .resource-card .icon-wrap {
            width: 54px; height: 54px; border-radius: 16px; background: var(--green-50);
            display: flex; align-items: center; justify-content: center; font-size: 25px;
            margin-bottom: 14px; transition: all 0.3s ease;
        }
        .resource-card:hover .icon-wrap { background: var(--green-500); transform: scale(1.06); }
        .resource-card h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); margin-bottom: 6px; line-height: 1.35; }
        .resource-card p { font-size: 13px; color: var(--gray-500); line-height: 1.6; margin-bottom: 14px; flex: 1; }
        .resource-card .meta { display: flex; align-items: center; gap: 8px; font-size: 11px; color: var(--gray-400); flex-wrap: wrap; }
        .resource-card .meta .tag {
            background: var(--green-50); color: var(--green-700); font-weight: 600;
            padding: 3px 10px; border-radius: 20px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .resource-card .meta .chip { background: var(--gray-100); padding: 3px 10px; border-radius: 20px; font-weight: 500; }

        .section-title { font-weight: 800; font-size: 17px; color: var(--gray-800); }
        .section-subtitle { font-size: 13px; color: var(--gray-500); }

        .progress-track { height: 6px; background: var(--gray-100); border-radius: 20px; overflow: hidden; }
        .progress-track .fill { height: 100%; background: linear-gradient(90deg, var(--green-400), var(--green-600)); border-radius: 20px; transition: width 0.5s ease; }

        .progress-card {
            background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 20px;
            padding: 18px; display: flex; align-items: flex-start; gap: 14px; text-decoration: none;
            transition: all 0.3s ease;
        }
        .progress-card:hover { transform: translateY(-3px); box-shadow: 0 10px 32px rgba(0,0,0,0.06); border-color: var(--green-300); }
        .progress-card .icon {
            width: 44px; height: 44px; border-radius: 12px; background: var(--green-50);
            display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;
        }

        .flash-banner {
            background: var(--green-500); color: white; border-radius: 14px; padding: 12px 18px;
            font-size: 14px; font-weight: 500; display: none; align-items: center; gap: 10px;
            box-shadow: 0 8px 28px rgba(4,160,82,0.3);
        }
        .flash-banner.show { display: flex; animation: slideDown 0.4s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .category-card { padding: 14px; }
        }
    </style>
</head>
<body>

    <main class="main-content">

        @if(session('success'))
            <div class="flash-banner show mb-4" id="flashBanner">
                <i class="fas fa-check-circle"></i>
                <span>{{ session('success') }}</span>
                <button class="ml-auto text-lg leading-none" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-extrabold text-gray-800">Self-Help Tools</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Resources, exercises, and tools to support your wellbeing. <i class="fas fa-leaf" aria-hidden="true"></i></p>
                </div>
            </div>
            <form method="GET" action="{{ route('selfhelp') }}" class="flex items-center gap-2 w-full md:w-auto" id="searchForm">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search by title or tag… (e.g. anxiety)">
                </div>
                @if($search !== '')
                    <a href="{{ route('selfhelp') }}" class="text-xs font-semibold text-gray-400 hover:text-[#04A052] whitespace-nowrap">
                        <i class="fas fa-times mr-1"></i>Clear
                    </a>
                @endif
            </form>
        </div>

        @if($search !== '')
            <div class="mb-6 bg-white rounded-2xl border border-gray-200 p-4 flex items-center gap-3">
                <i class="fas fa-filter text-[#04A052]"></i>
                <p class="text-sm text-gray-600">
                    Showing results for <strong>"{{ $search }}"</strong>
                    <span class="text-gray-400">({{ $all->count() }} found)</span>
                </p>
            </div>
        @endif

        <!-- Categories -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 mb-8">
            @foreach($categories as $cat)
                <a href="{{ route('selfhelp.category', $cat['slug']) }}" class="category-card cat-{{ $cat['color'] }}">
                    <div class="icon"><x-ui-icon :value="$cat['icon']" /></div>
                    <div>
                        <h3 class="font-bold text-sm text-gray-800">{{ $cat['label'] }}</h3>
                        <p class="count">{{ $cat['count'] }} {{ $cat['count'] === 1 ? 'item' : 'items' }}</p>
                    </div>
                </a>
            @endforeach
        </div>

        @if($search === '')
            <!-- Continue where you left off -->
            @if($continueProgress->count() > 0)
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="section-title"><i class="fas fa-play-circle text-[#04A052] mr-2"></i>Continue where you left off</h2>
                            <p class="section-subtitle">Pick up where you stopped.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($continueProgress as $entry)
                            @php $r = $entry->resource; @endphp
                            <a href="{{ route('selfhelp.show', $r->id) }}" class="progress-card">
                                <div class="icon"><x-ui-icon :value="$r->icon" /></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <h4 class="font-semibold text-sm text-gray-800 truncate">{{ $r->title }}</h4>
                                        <span class="text-xs font-bold text-[#04A052] whitespace-nowrap">{{ $entry->progress_percentage }}%</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-2">{{ $r->duration }} · {{ ucfirst($r->category) }}</p>
                                    <div class="progress-track">
                                        <div class="fill" style="width: {{ $entry->progress_percentage }}%"></div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Featured -->
            @if($featured->count() > 0)
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="section-title"><i class="fas fa-star text-amber-400 mr-2"></i>Featured for you</h2>
                            <p class="section-subtitle">Handpicked resources to get you started.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($featured as $resource)
                            <a href="{{ route('selfhelp.show', $resource->id) }}" class="resource-card">
                                <div class="icon-wrap"><x-ui-icon :value="$resource->icon" /></div>
                                <h3>{{ $resource->title }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit($resource->description, 90) }}</p>
                                <div class="meta">
                                    <span class="tag">{{ ucfirst($resource->category) }}</span>
                                    <span class="chip"><i class="far fa-clock mr-1"></i>{{ $resource->duration }}</span>
                                    @if($resource->views_count > 0)
                                        <span class="chip"><i class="far fa-eye mr-1"></i>{{ number_format($resource->views_count) }}</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Saved -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="section-title"><i class="fas fa-bookmark text-[#04A052] mr-2"></i>Saved resources</h2>
                        <p class="section-subtitle">Your personal library.</p>
                    </div>
                </div>
                @if($savedResources->count() > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @foreach($savedResources as $resource)
                            <a href="{{ route('selfhelp.show', $resource->id) }}" class="resource-card">
                                <div class="icon-wrap"><x-ui-icon :value="$resource->icon" /></div>
                                <h3>{{ \Illuminate\Support\Str::limit($resource->title, 40) }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit($resource->description, 70) }}</p>
                                <div class="meta">
                                    <span class="tag">{{ ucfirst($resource->category) }}</span>
                                    <span class="chip"><i class="far fa-clock mr-1"></i>{{ $resource->duration }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-8 text-center">
                        <div class="text-4xl mb-3"><i class="fas fa-bookmark" aria-hidden="true"></i></div>
                        <p class="text-sm text-gray-500">You haven't saved any resources yet.</p>
                        <p class="text-xs text-gray-400 mt-1">Tap the bookmark icon on any resource to save it here.</p>
                    </div>
                @endif
            </div>
        @endif

        <!-- All resources -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="section-title"><i class="fas fa-layer-group text-[#04A052] mr-2"></i>All resources</h2>
                    <p class="section-subtitle">Browse the full library.</p>
                </div>
            </div>
            @if($all->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($all as $resource)
                        <a href="{{ route('selfhelp.show', $resource->id) }}" class="resource-card">
                            <div class="icon-wrap"><x-ui-icon :value="$resource->icon" /></div>
                            <h3>{{ $resource->title }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit($resource->description, 90) }}</p>
                            <div class="meta">
                                <span class="tag">{{ ucfirst($resource->category) }}</span>
                                <span class="chip"><i class="far fa-clock mr-1"></i>{{ $resource->duration }}</span>
                                <span class="chip"><i class="fas fa-signal mr-1"></i>{{ $resource->difficulty_label }}</span>
                                @foreach(array_slice($resource->tags_list, 0, 2) as $tag)
                                    <span class="chip">#{{ $tag }}</span>
                                @endforeach
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-2xl border border-dashed border-gray-300 p-10 text-center">
                    <div class="text-5xl mb-3"><i class="fas fa-magnifying-glass" aria-hidden="true"></i></div>
                    <h3 class="font-semibold text-gray-800">No results found</h3>
                    <p class="text-sm text-gray-500 mt-1">Try a different keyword like "anxiety", "sleep", or "focus".</p>
                </div>
            @endif
        </div>

        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Small steps every day add up. You're doing great.
        </div>

    </main>

    @include('partials.sidebar', [
        'active' => ['selfhelp*'],
        'role'   => 'Help Seeker',
    ])

    @include('layouts.partials.pwa-banner')

</body>
</html>
