<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – {{ $resource->title }}</title>

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

        .back-link {
            display: inline-flex; align-items: center; background: white; border: 1px solid var(--gray-200);
            border-radius: 12px; color: var(--gray-600); font-size: 13px; font-weight: 600;
            padding: 9px 16px; text-decoration: none;
            transition: border-color 0.2s ease, color 0.2s ease;
        }
        .back-link:hover { border-color: var(--green-400); color: var(--green-700); }

        .hero-card {
            background: white; border: 1px solid var(--gray-200); border-radius: 24px;
            padding: 32px; margin-bottom: 24px;
            background-image: linear-gradient(130deg, #ffffff 60%, var(--green-50));
        }
        .hero-card .eyebrow {
            display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--green-600); margin-bottom: 10px;
        }
        .hero-card h1 { font-weight: 800; color: var(--gray-800); line-height: 1.25; }
        .hero-card .lede { color: var(--gray-500); margin-top: 10px; max-width: 65ch; line-height: 1.7; }

        .chip {
            display: inline-flex; align-items: center; background: var(--gray-100); color: var(--gray-600);
            font-size: 11px; font-weight: 600; padding: 5px 12px; border-radius: 20px;
        }
        .chip.green { background: var(--green-50); color: var(--green-700); }
        .chip.tag { background: transparent; border: 1px solid var(--gray-200); color: var(--gray-500); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            font-weight: 600; font-size: 14px; padding: 11px 22px; border-radius: 12px;
            border: none; cursor: pointer; transition: all 0.2s ease; text-decoration: none;
        }
        .btn:disabled { cursor: default; opacity: 0.75; }
        .btn-primary { background: linear-gradient(135deg, var(--green-500), var(--green-600)); color: white; box-shadow: 0 4px 16px rgba(4,160,82,0.25); }
        .btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(4,160,82,0.35); }
        .btn-outline { background: white; border: 1px solid var(--gray-300); color: var(--gray-600); }
        .btn-outline:hover { border-color: var(--green-500); color: var(--green-700); }
        .btn-saved { background: var(--green-50); border: 1px solid var(--green-300); color: var(--green-700); }

        .content-card {
            background: white; border: 1px solid var(--gray-200); border-radius: 24px;
            padding: 32px 36px; line-height: 1.85; font-size: 15px; color: var(--gray-600);
            margin-bottom: 24px;
        }
        .content-card h2 {
            font-size: 19px; font-weight: 800; color: var(--gray-800);
            margin: 28px 0 10px; padding-left: 14px; border-left: 4px solid var(--green-500); line-height: 1.4;
        }
        .content-card h2:first-child { margin-top: 0; }
        .content-card p { margin-bottom: 12px; }
        .content-card strong { color: var(--gray-800); }

        .progress-panel { background: rgba(255,255,255,0.8); border: 1px solid var(--gray-200); border-radius: 18px; padding: 20px; }
        .progress-track { height: 8px; background: var(--gray-100); border-radius: 20px; overflow: hidden; }
        .progress-track .fill { height: 100%; background: linear-gradient(90deg, var(--green-400), var(--green-600)); border-radius: 20px; transition: width 0.5s ease; }

        input[type="range"] { -webkit-appearance: none; width: 100%; height: 6px; border-radius: 20px; background: var(--gray-200); outline: none; }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none; width: 20px; height: 20px; border-radius: 50%;
            background: var(--green-500); border: 3px solid white; box-shadow: 0 2px 8px rgba(4,160,82,0.4); cursor: pointer;
        }
        input[type="range"]::-moz-range-thumb {
            width: 20px; height: 20px; border-radius: 50%; background: var(--green-500);
            border: 3px solid white; box-shadow: 0 2px 8px rgba(4,160,82,0.4); cursor: pointer;
        }

        .related-card {
            background: white; border: 1px solid var(--gray-200); border-radius: 20px;
            padding: 18px; display: block; text-decoration: none; height: 100%;
            transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
        }
        .related-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(4,160,82,0.08); border-color: var(--green-300); }
        .related-card h3 { font-weight: 700; font-size: 15px; color: var(--gray-800); line-height: 1.35; }
        .related-card .meta { font-size: 12px; color: var(--gray-400); margin-top: 6px; }

        .flash-banner {
            background: var(--green-50); border: 1px solid var(--green-200); color: var(--green-800);
            border-radius: 14px; padding: 12px 18px; font-size: 14px; font-weight: 500;
            display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
        }
        .flash-banner button {
            margin-left: auto; background: none; border: none; cursor: pointer;
            color: var(--green-700); font-size: 12px; font-weight: 600; text-decoration: underline;
        }

        .toast {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(120%);
            background: var(--gray-800); color: white; padding: 12px 22px; border-radius: 12px;
            font-size: 14px; font-weight: 500; z-index: 300; transition: transform 0.35s ease;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .toast.show { transform: translateX(-50%) translateY(0); }

        :focus-visible { outline: 3px solid var(--green-500); outline-offset: 2px; border-radius: 6px; }
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hero-card, .content-card { padding: 20px; }
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

        {{-- Top bar --}}
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <button type="button" class="menu-btn" id="hamburgerBtn">Menu</button>
                <a href="{{ url()->previous() == url()->current() ? route('selfhelp') : url()->previous() }}" class="back-link">Back</a>
            </div>
            <button type="button" class="btn {{ $isSaved ? 'btn-saved' : 'btn-outline' }}" id="saveBtn" onclick="toggleSave()">
                <span id="saveLabel">{{ $isSaved ? 'Saved' : 'Save' }}</span>
            </button>
        </div>

        {{-- Hero --}}
        <div class="hero-card mb-6">
            <span class="eyebrow">{{ \Illuminate\Support\Str::headline($resource->category) }}</span>
            <h1 class="text-2xl md:text-3xl font-extrabold">{{ $resource->title }}</h1>
            <p class="lede text-sm">{{ $resource->description }}</p>

            <div class="flex flex-wrap items-center gap-2 mt-5">
                <a href="{{ route('selfhelp.category', $resource->category) }}" class="chip green">
                    {{ \App\Http\Controllers\SelfHelpController::CATEGORIES[$resource->category]['label'] ?? \Illuminate\Support\Str::headline($resource->category) }}
                </a>
                @if($resource->duration)
                    <span class="chip">{{ $resource->duration }}</span>
                @endif
                <span class="chip">{{ $resource->difficulty_label }}</span>
                @foreach($resource->tags_list as $tag)
                    <span class="chip tag">#{{ $tag }}</span>
                @endforeach
                @if($resource->views_count > 0)
                    <span class="chip">{{ number_format($resource->views_count) }} views</span>
                @endif
                @if($progress->is_completed)
                    <span class="chip green">Completed</span>
                @endif
            </div>

            @if(in_array($resource->category, ['exercise', 'meditation', 'tool'], true))
                <div class="progress-panel mt-8">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-bold text-sm text-gray-800">Your progress</h2>
                        <span class="text-sm font-extrabold text-[#04A052]" id="progressValue">{{ $progress->progress_percentage }}%</span>
                    </div>
                    <div class="progress-track mb-4" role="progressbar" aria-valuenow="{{ $progress->progress_percentage }}" aria-valuemin="0" aria-valuemax="100" aria-label="Your progress on this resource">
                        <div class="fill" id="progressFill" style="width: {{ $progress->progress_percentage }}%"></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex-1 min-w-[200px]">
                            <label for="progressSlider" class="sr-only">Progress percentage</label>
                            <input type="range" id="progressSlider" min="0" max="100" step="5"
                                   value="{{ $progress->progress_percentage }}">
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-outline text-xs px-4 py-2" id="updateProgressBtn" onclick="saveProgress()">Update</button>
                            <button type="button" class="btn btn-primary text-xs px-4 py-2" id="completeBtn" onclick="markComplete()">
                                <span id="completeLabel">{{ $progress->is_completed ? 'Completed' : 'Mark complete' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Content --}}
        <div class="content-card mb-6">
            @php
                $sections = preg_split('/\n\s*\n/', $resource->content ?? '');
            @endphp
            @foreach($sections as $section)
                @if(str_starts_with(trim($section), '## '))
                    <h2>{{ trim(Str::after($section, '## ')) }}</h2>
                @elseif(trim($section) !== '')
                    <p>{!! nl2br(e(trim($section))) !!}</p>
                @endif
            @endforeach
        </div>

        {{-- Related --}}
        @if($related->count() > 0)
            <div class="mb-6">
                <h2 class="font-extrabold text-lg text-gray-800 mb-4">Related resources</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach($related as $item)
                        <a href="{{ route('selfhelp.show', $item->id) }}" class="related-card">
                            <h3>{{ $item->title }}</h3>
                            <p class="meta">
                                {{ \App\Http\Controllers\SelfHelpController::CATEGORIES[$item->category]['label'] ?? \Illuminate\Support\Str::headline($item->category) }}
                                @if($item->duration) &middot; {{ $item->duration }} @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            Be gentle with yourself &mdash; every practice counts.
        </p>

    </main>

    {{-- Hidden forms for non-JS fallback --}}
    <form method="POST" action="{{ route('selfhelp.save', $resource->id) }}" id="saveForm" style="display:none">@csrf</form>
    <form method="POST" action="{{ route('selfhelp.unsave', $resource->id) }}" id="unsaveForm" style="display:none">@csrf</form>
    <form method="POST" action="{{ route('selfhelp.progress', $resource->id) }}" id="progressForm" style="display:none">
        @csrf
        <input type="hidden" name="percentage" id="progressInput">
    </form>
    <form method="POST" action="{{ route('selfhelp.complete', $resource->id) }}" id="completeForm" style="display:none">@csrf</form>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>

    @include('partials.sidebar', [
        'active' => ['selfhelp*'],
        'role'   => 'Help Seeker',
    ])

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let isSaved = {{ $isSaved ? 'true' : 'false' }};

        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(function () { toast.classList.remove('show'); }, 2600);
        }

        async function post(url) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('Request failed');
            return res.json();
        }

        async function toggleSave() {
            const btn = document.getElementById('saveBtn');
            const label = document.getElementById('saveLabel');
            btn.disabled = true;
            try {
                const data = await post(isSaved ? '{{ route("selfhelp.unsave", $resource->id) }}' : '{{ route("selfhelp.save", $resource->id) }}');
                isSaved = data.saved;
                label.textContent = isSaved ? 'Saved' : 'Save';
                btn.className = 'btn ' + (isSaved ? 'btn-saved' : 'btn-outline');
                showToast(isSaved ? 'Saved to your collection.' : 'Removed from your collection.');
            } catch (e) {
                showToast('Could not update your collection. Please try again.');
            } finally {
                btn.disabled = false;
            }
        }

        const slider = document.getElementById('progressSlider');
        if (slider) {
            slider.addEventListener('input', function () {
                document.getElementById('progressValue').textContent = slider.value + '%';
                document.getElementById('progressFill').style.width = slider.value + '%';
            });
        }

        async function saveProgress() {
            if (!slider) return;
            const btn = document.getElementById('updateProgressBtn');
            btn.disabled = true;
            document.getElementById('progressInput').value = slider.value;
            try {
                await post('{{ route("selfhelp.progress", $resource->id) }}?percentage=' + slider.value);
                showToast('Progress saved (' + slider.value + '%).');
            } catch (e) {
                document.getElementById('progressForm').submit();
            } finally {
                btn.disabled = false;
            }
        }

        async function markComplete() {
            const btn = document.getElementById('completeBtn');
            const label = document.getElementById('completeLabel');
            btn.disabled = true;
            try {
                await post('{{ route("selfhelp.complete", $resource->id) }}');
                if (slider) {
                    slider.value = 100;
                    document.getElementById('progressValue').textContent = '100%';
                    document.getElementById('progressFill').style.width = '100%';
                }
                label.textContent = 'Completed';
                showToast('Great job! Resource marked as complete.');
            } catch (e) {
                btn.disabled = false;
                document.getElementById('completeForm').submit();
            }
        }
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
