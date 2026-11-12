<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – {{ $resource->title }}</title>
    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --green-50: #EAF8F0; --green-100: #DCF5E0; --green-200: #A8E0B0; --green-300: #6DCB80;
            --green-400: #30B650; --green-500: #04A052; --green-600: #038A45; --green-700: #027039;
            --green-800: #01562B; --gray-100: #F1F4F2; --gray-200: #E2E8E4; --gray-300: #CBD5CF;
            --gray-400: #94A39A; --gray-500: #64746B; --gray-600: #4B5B52; --gray-800: #163B2D;
        }
        body { background: #F8FBF9; }
        .main-content { margin-left: var(--sidebar-w, 264px); padding: 24px 32px 80px; min-height: 100vh; }
        .menu-btn { display: none; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; color: var(--gray-600); font-size: 13px; font-weight: 600; padding: 8px 14px; cursor: pointer; }
        .menu-btn::before { content: ''; width: 16px; height: 2px; border-radius: 2px; background: currentColor; box-shadow: 0 5px 0 currentColor, 0 -5px 0 currentColor; }
        @media (max-width: 768px) { .menu-btn { display: inline-flex; } }

        .rs-wrap { max-width: none; display: grid; gap: 16px; }
        .rs-body { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 16px; align-items: start; }
        .rs-side { display: grid; gap: 16px; position: sticky; top: calc(12px + env(safe-area-inset-top, 0px)); }
        .rs-side .player { position: static; box-shadow: none; }
        .rs-side .breathe { padding: 18px; }
        .rs-side .breathe-stage { width: 160px; height: 160px; }
        .hero-card { display: grid; grid-template-columns: 220px minmax(0, 1fr); }
        .hero-card .sh-art { height: 100% !important; min-height: 140px; }
        .hero-body { padding: 18px 22px !important; gap: 8px !important; }
        .hero-card h1 { font-size: 24px !important; }
        .content-card { padding: 24px 28px !important; }
        @media (max-width: 1100px) { .rs-body { grid-template-columns: 1fr; } .rs-side { position: static; order: -1; } }
        @media (max-width: 640px) { .hero-card { grid-template-columns: 1fr; } .hero-card .sh-art { height: 120px !important; min-height: 0; } }
        .rs-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .back-link { display: inline-flex; align-items: center; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; color: var(--gray-600); font-size: 13px; font-weight: 600; padding: 9px 14px; text-decoration: none; }
        .back-link:hover { border-color: var(--green-400); color: var(--green-700); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; font-size: 14px; padding: 10px 18px; border-radius: 12px; border: 1px solid transparent; cursor: pointer; text-decoration: none; min-height: 42px; }
        .btn:disabled { cursor: default; opacity: .7; }
        .btn-primary { background: var(--green-600); color: #fff; }
        .btn-primary:hover:not(:disabled) { background: var(--green-700); }
        .btn-outline { background: #fff; border-color: var(--gray-300); color: var(--gray-600); }
        .btn-outline:hover:not(:disabled) { border-color: var(--green-500); color: var(--green-700); }
        .btn-saved { background: var(--green-50); border-color: var(--green-300); color: var(--green-700); }
        .btn[aria-pressed="true"] { background: var(--green-50); border-color: var(--green-400); color: var(--green-800); }

        .hero-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 20px; overflow: hidden; }
        .hero-card .sh-art { display: block; width: 100%; height: 150px; }
        .hero-body { padding: 22px 24px 24px; display: grid; gap: 10px; }
        .eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--green-600); }
        .hero-card h1 { font-size: 24px; font-weight: 800; color: var(--gray-800); line-height: 1.25; text-wrap: balance; }
        .lede { color: var(--gray-500); line-height: 1.65; max-width: 65ch; }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip { display: inline-flex; align-items: center; background: var(--gray-100); color: var(--gray-600); font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 99px; text-decoration: none; }
        .chip.green { background: var(--green-50); color: var(--green-700); }

        /* Player: progress, listen, calm sound */
        .player { position: sticky; top: calc(8px + env(safe-area-inset-top, 0px)); z-index: 5; background: rgba(255,255,255,.96); backdrop-filter: blur(6px); border: 1px solid var(--gray-200); border-radius: 16px; padding: 14px 16px; display: grid; gap: 10px; box-shadow: 0 6px 20px rgba(22,59,45,.06); }
        .player-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .player-status { font-size: 13px; color: var(--gray-600); }
        .player-status strong { color: var(--gray-800); }
        .player-tools { display: flex; gap: 8px; flex-wrap: wrap; }
        .player-tools .btn { min-height: 38px; padding: 7px 14px; font-size: 13px; }
        .progress-track { height: 8px; background: var(--gray-100); border-radius: 99px; overflow: hidden; }
        .progress-track .fill { height: 100%; background: linear-gradient(90deg, var(--green-400), var(--green-600)); border-radius: 99px; transition: width .4s ease; }
        .step-dots { display: flex; gap: 6px; flex-wrap: wrap; }
        .step-dot { width: 28px; height: 6px; border-radius: 99px; background: var(--gray-200); border: 0; cursor: pointer; padding: 0; }
        .step-dot.is-done { background: var(--green-300); }
        .step-dot.is-current { background: var(--green-600); }

        /* Breathing guide */
        .breathe { background: #fff; border: 1px solid var(--gray-200); border-radius: 20px; padding: 22px; display: grid; justify-items: center; gap: 14px; text-align: center; }
        .breathe h2 { font-size: 16px; font-weight: 800; color: var(--gray-800); }
        .breathe-stage { width: 200px; height: 200px; display: grid; place-items: center; position: relative; }
        .breathe-ring { position: absolute; inset: 0; border-radius: 50%; background: radial-gradient(circle, #cfe6f7 0%, #e6f2fb 70%); transform: scale(.55); transition: transform 4s ease-in-out; }
        .breathe-label { position: relative; font-size: 18px; font-weight: 700; color: #2f6fa8; }
        .breathe-count { position: relative; font-size: 13px; color: var(--gray-500); }
        .breathe p { font-size: 13px; color: var(--gray-500); max-width: 46ch; }

        /* Steps */
        .content-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 20px; padding: 28px 32px; line-height: 1.8; font-size: 16px; color: var(--gray-600); }
        .sh-step + .sh-step { margin-top: 28px; padding-top: 24px; border-top: 1px solid var(--gray-100); }
        .is-stepping .sh-step + .sh-step { margin-top: 0; padding-top: 0; border-top: 0; }
        .step-label { font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--green-600); margin-bottom: 4px; }
        .content-card h2 { font-size: 20px; font-weight: 800; color: var(--gray-800); margin-bottom: 10px; line-height: 1.35; }
        .content-card p { margin-bottom: 12px; max-width: 68ch; }
        .content-card p:last-child { margin-bottom: 0; }
        .step-nav { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--gray-100); }
        .step-nav[hidden] { display: none; }
        .step-nav .spacer { flex: 1; }
        .finish { display: grid; gap: 8px; justify-items: start; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--gray-100); }
        .finish p { font-size: 13px; color: var(--gray-500); }
        .linkish { background: none; border: 0; color: var(--green-700); font-weight: 600; font-size: 13px; text-decoration: underline; cursor: pointer; }

        .related-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 16px; overflow: hidden; display: block; text-decoration: none; }
        .related-card .sh-art { display: block; width: 100%; height: 64px; }
        .related-card div { padding: 12px 14px; }
        .related-card h3 { font-weight: 700; font-size: 14px; color: var(--gray-800); line-height: 1.35; }
        .related-card .meta { font-size: 12px; color: var(--gray-400); margin-top: 4px; }
        .related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .section-heading { font-size: 16px; font-weight: 800; color: var(--gray-800); margin-bottom: 12px; }
        .flash-banner { background: var(--green-50); border: 1px solid var(--green-200); color: var(--green-800); border-radius: 12px; padding: 12px 16px; font-size: 14px; display: flex; gap: 12px; align-items: center; }
        .flash-banner button { margin-left: auto; background: none; border: none; color: var(--green-700); font-weight: 600; text-decoration: underline; cursor: pointer; }
        .toast { position: fixed; bottom: calc(24px + env(safe-area-inset-bottom, 0px)); left: 50%; transform: translateX(-50%) translateY(160%); background: var(--gray-800); color: #fff; padding: 12px 20px; border-radius: 12px; font-size: 14px; z-index: 300; transition: transform .3s ease; }
        .toast.show { transform: translateX(-50%) translateY(0); }
        .toast:not(.show) { visibility: hidden; }
        /* selfhelp-mobile.css gives every button 44px and every card padding; these elements opt out. */
        .main-content.selfhelp-content .step-dot { min-height: 0; height: 6px; }
        .main-content.selfhelp-content .hero-card, .main-content.selfhelp-content .related-card { padding: 0; }
        @media (max-width: 768px) { .player { position: static; } .step-dot { width: 22px; } }
        .sh-foot { text-align: center; font-size: 13px; color: var(--gray-400); border-top: 1px solid var(--gray-200); padding-top: 20px; }
        :focus-visible { outline: 3px solid var(--green-500); outline-offset: 2px; border-radius: 6px; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px 16px 100px; } .content-card { padding: 20px; } .hero-body { padding: 18px; } .hero-card h1 { font-size: 24px; } .step-nav .btn, .finish .btn { width: 100%; } .step-nav .spacer { display: none; } }
    </style>
<link rel="stylesheet" href="{{ asset('css/selfhelp-mobile.css') }}?v={{ filemtime(public_path('css/selfhelp-mobile.css')) }}">
    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
</head>
<body>
@php
    // Each "## Heading" starts a step; text before the first heading is an introduction step.
    $steps = [];
    foreach (preg_split('/\n\s*\n/', (string) ($resource->content ?? '')) as $block) {
        $block = trim($block);
        if ($block === '') continue;
        if (str_starts_with($block, '## ')) {
            $lines = preg_split('/\n/', $block, 2);
            // Older content numbers its own headings ("Step 3: Hold"); the page numbers steps itself.
            $steps[] = ['title' => preg_replace('/^step\s*\d+\s*[:.\x{2013}\x{2014}-]\s*/iu', '', trim(\Illuminate\Support\Str::after($lines[0], '## '))), 'paragraphs' => isset($lines[1]) && trim($lines[1]) !== '' ? [trim($lines[1])] : []];
        } else {
            if (! $steps) $steps[] = ['title' => 'Introduction', 'paragraphs' => []];
            $steps[count($steps) - 1]['paragraphs'][] = $block;
        }
    }
    if (! $steps) $steps[] = ['title' => $resource->title, 'paragraphs' => [$resource->description ?: 'No content yet.']];
    $isArticle = $resource->category === 'article';
    $categoryLabel = \App\Http\Controllers\SelfHelpController::CATEGORIES[$resource->category]['label'] ?? \Illuminate\Support\Str::headline($resource->category);
    $savedPercent = (int) $progress->progress_percentage;
    $completed = (bool) $progress->is_completed;
@endphp
    <main class="main-content selfhelp-content">
        <div class="rs-wrap">
            @if(session('success'))
                <div class="flash-banner" role="status" aria-live="polite"><span>{{ session('success') }}</span><button type="button" onclick="this.parentElement.remove()">Dismiss</button></div>
            @endif

            <div class="rs-top">
                <div style="display:flex;align-items:center;gap:10px">
                    <button type="button" class="menu-btn" id="hamburgerBtn" aria-label="Open navigation" aria-controls="sidebar">Menu</button>
                    <a href="{{ url()->previous() == url()->current() ? route('selfhelp') : url()->previous() }}" class="back-link">Back</a>
                </div>
                <button type="button" class="btn {{ $isSaved ? 'btn-saved' : 'btn-outline' }}" id="saveBtn" onclick="toggleSave()"><span id="saveLabel">{{ $isSaved ? 'Saved' : 'Save' }}</span></button>
            </div>

            <article class="hero-card">
                <x-selfhelp-art :topic="$sectionKey" size="hero" />
                <div class="hero-body">
                    <span class="eyebrow">{{ $sectionLabel }}</span>
                    <h1>{{ $resource->title }}</h1>
                    <p class="lede">{{ $resource->description }}</p>
                    <div class="chips">
                        <a href="{{ route('selfhelp.category', $resource->category) }}" class="chip green">{{ $categoryLabel }}</a>
                        @if($resource->duration && strtoupper($resource->duration) !== 'N/A')<span class="chip">{{ $resource->duration }}</span>@endif
                        <span class="chip">{{ $resource->difficulty_label }}</span>
                        <span class="chip">{{ count($steps) }} {{ count($steps) === 1 ? 'step' : 'steps' }}</span>
                        @if($completed)<span class="chip green">Completed</span>@endif
                    </div>
                </div>
            </article>



            <div class="rs-body">
                <div class="rs-main">
            <section class="content-card" id="resourceSteps" aria-label="Resource steps">
                @foreach($steps as $i => $step)
                    <section class="sh-step" data-step="{{ $i }}" aria-labelledby="step-title-{{ $i }}">
                        @if(count($steps) > 1)<p class="step-label">Step {{ $i + 1 }} of {{ count($steps) }}</p>@endif
                        <h2 id="step-title-{{ $i }}">{{ $step['title'] }}</h2>
                        @foreach($step['paragraphs'] as $paragraph)<p>{!! nl2br(e($paragraph)) !!}</p>@endforeach
                    </section>
                @endforeach
                @if(count($steps) > 1)
                    <div class="step-nav" data-step-nav hidden>
                        <button type="button" class="btn btn-outline" data-step-prev>Previous</button>
                        <button type="button" class="linkish" data-show-all>Show all steps</button>
                        <span class="spacer"></span>
                        <button type="button" class="btn btn-primary" data-step-next>Next step</button>
                    </div>
                @endif
                <div class="finish" data-finish>
                    <p>{{ $isArticle ? 'Finished reading? Mark it as read to save your progress.' : 'Done with the activity? Mark it complete to save your progress.' }}</p>
                    <button type="button" class="btn btn-primary" id="completeBtn" onclick="markComplete()" @disabled($completed)><span id="completeLabel">{{ $completed ? 'Completed' : ($isArticle ? 'Mark as read' : 'Mark complete') }}</span></button>
                </div>
            </section>
                </div>
                <aside class="rs-side">
            {{-- Progress follows the steps you actually open; it is saved automatically. --}}
            <section class="player" aria-label="Your progress and audio">
                <div class="player-row">
                    <p class="player-status" id="resourceActivityState" role="status">
                        @if($completed)<strong>{{ $isArticle ? 'Read' : 'Completed' }}</strong> · all {{ count($steps) }} steps
                        @else<strong>Step <span data-step-now>1</span> of {{ count($steps) }}</strong> · <span data-step-percent>{{ $savedPercent }}</span>% done
                        @endif
                    </p>
                    <div class="player-tools">
                        <button type="button" class="btn btn-outline" data-listen aria-pressed="false" hidden>Listen</button>
                        <button type="button" class="btn btn-outline" data-calm-sound aria-pressed="false" hidden>Calm sound</button>
                    </div>
                </div>
                <div class="progress-track" role="progressbar" aria-label="Progress through this resource" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completed ? 100 : $savedPercent }}" data-progress-bar><div class="fill" style="width: {{ $completed ? 100 : $savedPercent }}%"></div></div>
                @if(count($steps) > 1)
                    <div class="step-dots" aria-label="Jump to a step" hidden data-step-dots>
                        @foreach($steps as $i => $step)<button type="button" class="step-dot" data-goto-step="{{ $i }}" aria-label="Step {{ $i + 1 }}: {{ $step['title'] }}"></button>@endforeach
                    </div>
                @endif
            </section>
            @if($sectionKey === 'breathing')
                <section class="breathe" aria-labelledby="breatheTitle" data-breathe>
                    <h2 id="breatheTitle">Breathe with the circle</h2>
                    <div class="breathe-stage"><div class="breathe-ring" data-breathe-ring></div><div><div class="breathe-label" data-breathe-label aria-live="polite">Ready</div><div class="breathe-count" data-breathe-count>In 4 · Hold 4 · Out 6</div></div></div>
                    <button type="button" class="btn btn-primary" data-breathe-toggle>Start breathing</button>
                    <p>Follow the circle: breathe in as it grows, hold, then breathe out slowly as it shrinks. Stop whenever you like.</p>
                </section>
            @endif
                </aside>
            </div>

            @if($related->count() > 0)
                <section>
                    <h2 class="section-heading">Related resources</h2>
                    <div class="related-grid">
                        @foreach($related as $item)
                            <a href="{{ route('selfhelp.show', $item->id) }}" class="related-card">
                                <x-selfhelp-art :topic="app(\App\Http\Controllers\SelfHelpController::class)->sectionKeyFor($item)" size="thumb" />
                                <div><h3>{{ $item->title }}</h3><p class="meta">{{ \App\Http\Controllers\SelfHelpController::CATEGORIES[$item->category]['label'] ?? \Illuminate\Support\Str::headline($item->category) }}@if($item->duration && strtoupper($item->duration) !== 'N/A') · {{ $item->duration }}@endif</p></div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <p class="sh-foot">Be gentle with yourself. Every practice counts.</p>
        </div>
    </main>

    <form method="POST" action="{{ route('selfhelp.save', $resource->id) }}" id="saveForm" style="display:none">@csrf</form>
    <form method="POST" action="{{ route('selfhelp.unsave', $resource->id) }}" id="unsaveForm" style="display:none">@csrf</form>
    <form method="POST" action="{{ route('selfhelp.progress', $resource->id) }}" id="progressForm" style="display:none">@csrf<input type="hidden" name="percentage" id="progressInput"></form>
    <form method="POST" action="{{ route('selfhelp.complete', $resource->id) }}" id="completeForm" style="display:none">@csrf</form>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>

    @include('partials.sidebar', ['active' => ['selfhelp*'], 'role' => 'Help Seeker'])

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let isSaved = {{ $isSaved ? 'true' : 'false' }};
        let isCompleted = {{ $completed ? 'true' : 'false' }};
        const totalSteps = {{ count($steps) }};
        const completeText = @json($isArticle ? 'Read' : 'Completed');

        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2600);
        }

        async function post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
                body: body ? JSON.stringify(body) : undefined,
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

        function setBar(percent) {
            const bar = document.querySelector('[data-progress-bar]');
            bar.setAttribute('aria-valuenow', String(percent));
            bar.querySelector('.fill').style.width = percent + '%';
        }

        async function markComplete() {
            const btn = document.getElementById('completeBtn');
            btn.disabled = true;
            try {
                await post('{{ route("selfhelp.complete", $resource->id) }}');
                isCompleted = true;
                document.getElementById('resourceActivityState').innerHTML = '<strong>' + completeText + '</strong> · all ' + totalSteps + ' steps';
                document.getElementById('completeLabel').textContent = 'Completed';
                setBar(100);
                showToast('Nice work. Your progress is saved.');
            } catch (e) {
                btn.disabled = false;
                document.getElementById('completeForm').submit();
            }
        }

        // ── Steps: one at a time, progress = furthest step reached, saved as you go ──
        (() => {
            const steps = [...document.querySelectorAll('.sh-step')];
            const nav = document.querySelector('[data-step-nav]');
            if (steps.length < 2 || !nav) return;
            const card = document.getElementById('resourceSteps');
            const finish = document.querySelector('[data-finish]');
            const dots = document.querySelector('[data-step-dots]');
            const prev = nav.querySelector('[data-step-prev]');
            const next = nav.querySelector('[data-step-next]');
            const showAll = nav.querySelector('[data-show-all]');
            const nowEl = document.querySelector('[data-step-now]');
            const pctEl = document.querySelector('[data-step-percent]');
            const saved = {{ $completed ? 100 : $savedPercent }};
            let furthest = Math.min(steps.length - 1, Math.max(0, Math.ceil(saved / 100 * steps.length) - 1));
            let current = isCompleted ? 0 : furthest;
            let stepping = true;
            let lastSent = saved;

            const percentFor = (index) => Math.round((index + 1) / steps.length * 100);
            const save = (percent) => {
                // 100% is recorded by the explicit complete button, so stepping stops at 99.
                const value = Math.min(percent, 99);
                if (isCompleted || value <= lastSent) return;
                lastSent = value;
                post('{{ route("selfhelp.progress", $resource->id) }}', { percentage: value }).catch(() => {});
            };

            function render() {
                card.classList.toggle('is-stepping', stepping);
                steps.forEach((step, i) => { step.hidden = stepping && i !== current; });
                prev.disabled = current === 0;
                prev.hidden = !stepping;
                next.hidden = !stepping || current === steps.length - 1;
                finish.hidden = stepping && current !== steps.length - 1;
                showAll.textContent = stepping ? 'Show all steps' : 'Show one step at a time';
                dots.querySelectorAll('[data-goto-step]').forEach((dot, i) => {
                    dot.classList.toggle('is-current', stepping && i === current);
                    dot.classList.toggle('is-done', i <= furthest || isCompleted);
                });
                if (!isCompleted) {
                    nowEl.textContent = String(current + 1);
                    const pct = Math.min(percentFor(furthest), 99);
                    pctEl.textContent = String(pct);
                    setBar(pct);
                }
            }

            function go(index) {
                current = Math.max(0, Math.min(steps.length - 1, index));
                if (current > furthest) furthest = current;
                save(percentFor(furthest));
                render();
                card.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
            }

            nav.hidden = false;
            dots.hidden = false;
            prev.addEventListener('click', () => go(current - 1));
            next.addEventListener('click', () => go(current + 1));
            showAll.addEventListener('click', () => { stepping = !stepping; if (!stepping) { furthest = steps.length - 1; save(percentFor(furthest)); } render(); });
            dots.addEventListener('click', (e) => { const b = e.target.closest('[data-goto-step]'); if (b) { stepping = true; go(Number(b.dataset.gotoStep)); } });
            if (!isCompleted && saved === 0) save(percentFor(0));
            render();
            window.compassCurrentStepText = () => (stepping ? steps[current] : card).innerText;
        })();

        // ── Listen: read the current step aloud with the device's built-in voice ──
        (() => {
            const btn = document.querySelector('[data-listen]');
            if (!btn || !('speechSynthesis' in window)) return;
            btn.hidden = false;
            const stop = () => { speechSynthesis.cancel(); btn.setAttribute('aria-pressed', 'false'); btn.textContent = 'Listen'; };
            btn.addEventListener('click', () => {
                if (btn.getAttribute('aria-pressed') === 'true') return stop();
                const text = window.compassCurrentStepText ? window.compassCurrentStepText() : document.getElementById('resourceSteps').innerText;
                const utterance = new SpeechSynthesisUtterance(text.replace(/\s+/g, ' ').trim());
                utterance.rate = 0.92;
                utterance.onend = stop;
                utterance.onerror = stop;
                speechSynthesis.cancel();
                speechSynthesis.speak(utterance);
                btn.setAttribute('aria-pressed', 'true');
                btn.textContent = 'Stop reading';
            });
            document.addEventListener('click', (e) => { if (e.target.closest('[data-step-prev],[data-step-next],[data-goto-step]')) stop(); });
            window.addEventListener('pagehide', stop);
        })();

        // ── Calm sound: soft generated rain-like noise with slow swells (no audio files) ──
        (() => {
            const btn = document.querySelector('[data-calm-sound]');
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!btn || !AudioCtx) return;
            btn.hidden = false;
            let ctx = null, master = null;
            function start() {
                ctx = new AudioCtx();
                const buffer = ctx.createBuffer(1, ctx.sampleRate * 4, ctx.sampleRate);
                const data = buffer.getChannelData(0);
                let last = 0;
                for (let i = 0; i < data.length; i++) { const white = Math.random() * 2 - 1; last = (last + 0.02 * white) / 1.02; data[i] = last * 3.2; }
                const noise = ctx.createBufferSource(); noise.buffer = buffer; noise.loop = true;
                const filter = ctx.createBiquadFilter(); filter.type = 'lowpass'; filter.frequency.value = 900;
                master = ctx.createGain(); master.gain.value = 0;
                const swell = ctx.createOscillator(); swell.frequency.value = 0.08;
                const swellDepth = ctx.createGain(); swellDepth.gain.value = 0.05;
                swell.connect(swellDepth).connect(master.gain);
                noise.connect(filter).connect(master).connect(ctx.destination);
                noise.start(); swell.start();
                master.gain.linearRampToValueAtTime(0.14, ctx.currentTime + 2);
            }
            function stop() {
                if (!ctx) return;
                const c = ctx; ctx = null;
                master.gain.linearRampToValueAtTime(0, c.currentTime + 0.8);
                setTimeout(() => c.close(), 900);
            }
            btn.addEventListener('click', () => {
                const on = btn.getAttribute('aria-pressed') !== 'true';
                btn.setAttribute('aria-pressed', String(on));
                btn.textContent = on ? 'Stop sound' : 'Calm sound';
                on ? start() : stop();
            });
            window.addEventListener('pagehide', stop);
        })();

        // ── Breathing guide: in 4s, hold 4s, out 6s ──
        (() => {
            const box = document.querySelector('[data-breathe]');
            if (!box) return;
            const ring = box.querySelector('[data-breathe-ring]');
            const label = box.querySelector('[data-breathe-label]');
            const count = box.querySelector('[data-breathe-count]');
            const toggle = box.querySelector('[data-breathe-toggle]');
            const phases = [['Breathe in', 4, 1], ['Hold', 4, 1], ['Breathe out', 6, .55]];
            let timer = null, tick = null, cycles = 0;
            function run(i) {
                const [text, seconds, scale] = phases[i];
                label.textContent = text;
                ring.style.transitionDuration = seconds + 's';
                ring.style.transform = 'scale(' + scale + ')';
                let left = seconds;
                count.textContent = left + 's';
                clearInterval(tick);
                tick = setInterval(() => { left -= 1; if (left > 0) count.textContent = left + 's'; }, 1000);
                timer = setTimeout(() => {
                    if (i === 2) cycles += 1;
                    run((i + 1) % phases.length);
                }, seconds * 1000);
            }
            toggle.addEventListener('click', () => {
                if (timer) {
                    clearTimeout(timer); clearInterval(tick); timer = null;
                    ring.style.transform = 'scale(.55)'; label.textContent = cycles ? 'Well done' : 'Ready';
                    count.textContent = cycles ? cycles + (cycles === 1 ? ' breath completed' : ' breaths completed') : 'In 4 · Hold 4 · Out 6';
                    toggle.textContent = 'Start breathing';
                } else {
                    cycles = 0; toggle.textContent = 'Stop'; run(0);
                }
            });
        })();
    </script>

    @include('layouts.partials.pwa-banner')
</body>
</html>
