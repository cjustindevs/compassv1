@extends('layouts.helper')

@section('title', 'Take Care of Yourself')
@section('heading', 'Self-Care Tools')
@section('subheading', 'Take a moment for yourself — you can\'t pour from an empty cup.')

@section('styles')
    <style>
        .selfcare-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
        .selfcare-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; text-decoration: none; color: inherit; transition: border-color .2s ease, box-shadow .2s ease; }
        .selfcare-card:hover { border-color: var(--green-300, #6dcb80); box-shadow: none; }
        .selfcare-card .sh-art { display: block; width: 100%; height: 72px; }
        .selfcare-card .body { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .selfcare-card h3 { font-size: 14px; font-weight: 700; color: var(--gray-800); }
        .selfcare-card p { font-size: 13px; color: var(--gray-500); line-height: 1.5; flex: 1; }
        .selfcare-card .action { align-self: flex-start; font-size: 13px; font-weight: 600; color: var(--green-700, #027039); }
        .selfcare-card.is-urgent .action { color: #b42318; }
    </style>
@endsection

@section('content')
    @if(session('warning'))
        <div class="alert alert-warning" data-flash> {{ session('warning') }}</div>
    @endif

    <div class="mb-6 p-4 rounded-xl" style="background:var(--green-50);border:1px solid var(--green-200);">
        <p class="text-sm font-medium" style="color:var(--green-700);">

            Your well-being comes first. Use these tools to recharge, then you can
            <a href="{{ route('helper.readiness') }}" style="color:var(--green-600);font-weight:700;">check in again</a>
            whenever you're ready to help.
        </p>
    </div>

    <div class="selfcare-grid">
        <a href="{{ route('helper.self-help.breathing') }}" class="selfcare-card">
            <x-selfhelp-art topic="breathing" />
            <div class="body"><h3>Breathing Exercise</h3><p>Calm your mind and body with a guided 4-4-4 breathing session.</p><span class="action">Start &rarr;</span></div>
        </a>
        <a href="{{ route('helper.self-help.grounding') }}" class="selfcare-card">
            <x-selfhelp-art topic="grounding" />
            <div class="body"><h3>Grounding Exercise</h3><p>Bring yourself back to the present moment with a 5-4-3-2-1 check-in.</p><span class="action">Start &rarr;</span></div>
        </a>
        <a href="{{ route('helper.self-help.journal') }}" class="selfcare-card">
            <x-selfhelp-art topic="journaling" />
            <div class="body"><h3>Private Journal</h3><p>Write down your thoughts and feelings. Only you can see these entries.</p><span class="action">Write &rarr;</span></div>
        </a>
        <a href="{{ route('helper.self-help.hotlines') }}" class="selfcare-card is-urgent">
            <x-selfhelp-art topic="coping" />
            <div class="body"><h3>Emergency Resources</h3><p>If you need immediate support, find hotlines and crisis resources here.</p><span class="action">View &rarr;</span></div>
        </a>
        <a href="{{ route('helper.readiness') }}" class="selfcare-card">
            <x-selfhelp-art topic="mindfulness" />
            <div class="body"><h3>Check In Again</h3><p>Feeling better? Complete the readiness check to resume taking sessions.</p><span class="action">Readiness Check &rarr;</span></div>
        </a>
    </div>

    <div class="mt-8 text-center text-sm text-gray-400">

        Remember: You cannot pour from an empty cup.
    </div>
@endsection
