@extends('layouts.helper')

@section('title', 'Take Care of Yourself')
@section('heading', 'Self-Care Tools')
@section('subheading', 'Take a moment for yourself — you can\'t pour from an empty cup.')

@section('styles')
    <style>
        .selfcare-card {
            background: white; border-radius: 20px; padding: 28px 24px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            text-align: center; transition: all 0.3s ease;
            display: flex; flex-direction: column; align-items: center; gap: 12px;
            height: 100%;
        }
        .selfcare-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.06); }
        .selfcare-card .icon {
            width: 72px; height: 72px; border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px;
        }
        .selfcare-card h3 { font-size: 16px; font-weight: 700; color: var(--gray-800); }
        .selfcare-card p { font-size: 13px; color: var(--gray-500); }
        .icon-purple { background: #F3E8FF; }
        .icon-green { background: var(--green-50); }
        .icon-amber { background: #FFFBEB; }
        .icon-red { background: #FEF2F2; }
        .icon-blue { background: var(--blue-50); }
    </style>
@endsection

@section('content')
    @if(session('warning'))
        <div class="alert alert-warning" data-flash><i class="fas fa-heart"></i> {{ session('warning') }}</div>
    @endif

    <div class="mb-6 p-4 rounded-xl" style="background:var(--green-50);border:1px solid var(--green-200);">
        <p class="text-sm font-medium" style="color:var(--green-700);">
            <i class="fas fa-hand-holding-heart mr-1"></i>
            Your well-being comes first. Use these tools to recharge, then you can
            <a href="{{ route('helper.readiness') }}" style="color:var(--green-600);font-weight:700;">check in again</a>
            whenever you're ready to help.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <a href="{{ route('helper.self-help.breathing') }}" class="selfcare-card" style="text-decoration:none;color:inherit;">
            <div class="icon icon-green"><i class="fas fa-wind"></i></div>
            <h3>Breathing Exercise</h3>
            <p>Calm your mind and body with a guided 4-4-4 breathing session.</p>
            <span class="btn btn-primary mt-auto"><i class="fas fa-play mr-1"></i> Start</span>
        </a>

        <a href="{{ route('helper.self-help.grounding') }}" class="selfcare-card" style="text-decoration:none;color:inherit;">
            <div class="icon icon-blue"><i class="fas fa-tree"></i></div>
            <h3>Grounding Exercise</h3>
            <p>Bring yourself back to the present moment with a 5-4-3-2-1 check-in.</p>
            <span class="btn btn-primary mt-auto"><i class="fas fa-play mr-1"></i> Start</span>
        </a>

        <a href="{{ route('helper.self-help.journal') }}" class="selfcare-card" style="text-decoration:none;color:inherit;">
            <div class="icon icon-amber"><i class="fas fa-book-open"></i></div>
            <h3>Private Journal</h3>
            <p>Write down your thoughts and feelings. Only you can see these entries.</p>
            <span class="btn btn-primary mt-auto"><i class="fas fa-edit mr-1"></i> Write</span>
        </a>

        <a href="{{ route('helper.self-help.hotlines') }}" class="selfcare-card" style="text-decoration:none;color:inherit;">
            <div class="icon icon-red"><i class="fas fa-phone-alt"></i></div>
            <h3>Emergency Resources</h3>
            <p>If you need immediate support, find hotlines and crisis resources here.</p>
            <span class="btn btn-primary mt-auto"><i class="fas fa-arrow-right mr-1"></i> View</span>
        </a>

        <a href="{{ route('helper.readiness') }}" class="selfcare-card" style="text-decoration:none;color:inherit;">
            <div class="icon icon-purple"><i class="fas fa-heartbeat"></i></div>
            <h3>Check In Again</h3>
            <p>Feeling better? Complete the readiness check to resume taking sessions.</p>
            <span class="btn btn-outline-danger mt-auto" style="border:1px solid var(--green-500);color:var(--green-600);"><i class="fas fa-sync mr-1"></i> Readiness Check</span>
        </a>
    </div>

    <div class="mt-8 text-center text-sm text-gray-400">
        <i class="fas fa-heart text-[#04A052] mr-1"></i>
        Remember: You cannot pour from an empty cup.
    </div>
@endsection
