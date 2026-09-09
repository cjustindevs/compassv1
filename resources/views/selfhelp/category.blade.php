<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – {{ $meta['label'] }}</title>

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
        }

        body { background: #F8FBF9; font-family: 'Inter', sans-serif; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--green-500); border-radius: 4px; }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .search-box {
            display: flex; align-items: center; gap: 10px;
            background: white; border: 1px solid var(--gray-200);
            border-radius: 14px; padding: 10px 16px; flex: 1; min-width: 220px;
            transition: all 0.2s ease;
        }
        .search-box:focus-within { border-color: var(--green-500); box-shadow: 0 0 0 4px rgba(4,160,82,0.08); }
        .search-box i { color: var(--gray-400); }
        .search-box input { border: none; outline: none; background: transparent; flex: 1; font-size: 14px; color: var(--gray-700); }

        .category-header {
            background: white; border: 1px solid var(--gray-200); border-radius: 24px;
            padding: 28px 32px; display: flex; align-items: center; gap: 20px; margin-bottom: 24px;
            background-image: linear-gradient(120deg, #ffffff 55%, var(--green-50));
        }
        .category-header .big-icon {
            width: 72px; height: 72px; border-radius: 22px; font-size: 34px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }

        .resource-card {
            background: white; border-radius: 24px; padding: 22px;
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

        .empty-state { background: white; border-radius: 24px; border: 1px dashed var(--gray-300); padding: 48px 24px; text-align: center; }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .category-header { padding: 20px; }
        }
    </style>
</head>
<body>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <a href="{{ route('selfhelp') }}" class="text-xs font-semibold text-[#04A052] hover:underline">
                        <i class="fas fa-arrow-left mr-1"></i>All resources
                    </a>
                    <h1 class="text-xl md:text-2xl font-extrabold text-gray-800 mt-1">{{ $meta['label'] }}</h1>
                </div>
            </div>
            <form method="GET" action="{{ route('selfhelp.category', $category) }}" class="flex items-center gap-2 w-full md:w-auto">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search {{ strtolower($meta['label']) }}…">
                </div>
            </form>
        </div>

        <!-- Category header -->
        <div class="category-header">
            <div class="big-icon" style="background: var(--green-50);"><x-ui-icon :value="$meta['icon']" /></div>
            <div>
                <h2 class="font-extrabold text-xl text-gray-800">{{ $meta['label'] }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $resources->count() }} {{ $resources->count() === 1 ? 'resource' : 'resources' }} available
                    @if($search !== '') · searching "<strong>{{ $search }}</strong>" @endif
                </p>
            </div>
        </div>

        @if($resources->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($resources as $resource)
                    <a href="{{ route('selfhelp.show', $resource->id) }}" class="resource-card">
                        <div class="icon-wrap"><x-ui-icon :value="$resource->icon" /></div>
                        <h3>{{ $resource->title }}</h3>
                        <p>{{ \Illuminate\Support\Str::limit($resource->description, 90) }}</p>
                        <div class="meta">
                            <span class="chip"><i class="far fa-clock mr-1"></i>{{ $resource->duration }}</span>
                            <span class="chip"><i class="fas fa-signal mr-1"></i>{{ $resource->difficulty_label }}</span>
                            @foreach(array_slice($resource->tags_list, 0, 2) as $tag)
                                <span class="chip">#{{ $tag }}</span>
                            @endforeach
                            @if(in_array($resource->id, $savedIds))
                                <i class="fas fa-bookmark text-[#04A052] ml-auto" title="Saved"></i>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <div class="text-5xl mb-3"><x-ui-icon :value="$meta['icon']" /></div>
                <h3 class="font-semibold text-gray-800">No resources found</h3>
                <p class="text-sm text-gray-500 mt-1">Try a different search, or check back soon.</p>
                <a href="{{ route('selfhelp.category', $category) }}" class="inline-block mt-4 text-sm font-semibold text-[#04A052] hover:underline">
                    <i class="fas fa-times mr-1"></i>Clear search
                </a>
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