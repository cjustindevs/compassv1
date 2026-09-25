@extends('layouts.app')

@section('title', 'Emergency Support | COMPASS')

@push('styles')
<style>
    :root {
        --green-50: #EAF8F0; --green-100: #D0F0D8; --green-300: #6DCB80;
        --green-400: #38C172; --green-500: #04A052; --green-600: #038A45; --green-700: #027039;
        --gray-50: #F9FAFB; --gray-100: #F3F4F6; --gray-200: #E5E7EB; --gray-300: #D1D5DB;
        --gray-400: #9CA3AF; --gray-500: #6B7280; --gray-600: #4B5563; --gray-700: #374151;
        --gray-800: #163B2D;
        --red-50: #FEF2F2; --red-100: #FEE2E2; --red-200: #FECACA; --red-500: #EF4444; --red-600: #DC2626; --red-700: #B91C1C;
        --card-bg: #ffffff; --page-bg: #F8FBF9;
    }

    .emg-page { max-width: 1080px; margin: 0 auto; padding: 28px 32px 80px; }

    /* ── Page header ── */
    .emg-eyebrow {
        display: inline-flex; align-items: center; gap: 7px;
        background: var(--red-50); color: var(--red-700);
        border: 1px solid var(--red-100);
        padding: 5px 12px; border-radius: 20px;
        font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
    }
    .emg-title {
        margin: 14px 0 0;
        font-size: 30px; font-weight: 800; letter-spacing: -.02em; line-height: 1.2;
        color: var(--gray-800);
    }
    .emg-lede { margin: 8px 0 0; max-width: 60ch; font-size: 14px; line-height: 1.65; color: var(--gray-500); }

    /* ── Immediate danger callout ── */
    .emg-urgent {
        position: relative; overflow: hidden;
        margin-top: 24px; padding: 22px 24px;
        background: linear-gradient(135deg, var(--red-600), var(--red-700));
        border-radius: 20px; color: #fff;
        box-shadow: 0 16px 40px rgba(220, 38, 38, .22);
    }
    .emg-urgent-head { display: flex; align-items: center; gap: 12px; }
    .emg-urgent-icon {
        width: 44px; height: 44px; flex: none;
        display: flex; align-items: center; justify-content: center;
        border-radius: 14px; background: rgba(255, 255, 255, .18); font-size: 20px;
    }
    .emg-urgent h2 { margin: 0; font-size: 17px; font-weight: 700; }
    .emg-urgent p { margin: 10px 0 0; font-size: 13.5px; line-height: 1.6; color: rgba(255, 255, 255, .92); max-width: 62ch; }
    .emg-urgent-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
    .emg-urgent-btn {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 18px; border-radius: 999px;
        background: #fff; color: var(--red-700);
        font-size: 13px; font-weight: 700; text-decoration: none;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .emg-urgent-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0, 0, 0, .18); }
    .emg-urgent-btn.ghost { background: rgba(255, 255, 255, .14); color: #fff; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .4); }
    .emg-urgent-btn.ghost:hover { background: rgba(255, 255, 255, .24); }

    /* ── Section headings ── */
    .emg-section-title {
        display: flex; align-items: center; gap: 10px;
        margin: 32px 0 4px;
        font-size: 18px; font-weight: 800; color: var(--gray-800);
    }
    .emg-section-title i { color: var(--red-500); font-size: 16px; }
    .emg-section-sub { margin: 0 0 16px; font-size: 13px; line-height: 1.6; color: var(--gray-500); }

    /* ── Hotline cards ── */
    .emg-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
    .emg-card {
        display: flex; flex-direction: column;
        background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 20px;
        padding: 18px;
        transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
    }
    .emg-card:hover { transform: translateY(-3px); border-color: var(--red-200); box-shadow: 0 14px 34px rgba(220, 38, 38, .08); }
    .emg-card-head { display: flex; align-items: center; gap: 12px; }
    .emg-card-icon {
        width: 40px; height: 40px; flex: none;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px; background: var(--red-50); color: var(--red-600); font-size: 17px;
    }
    .emg-card h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--gray-800); line-height: 1.35; }
    .emg-card p { margin: 12px 0 16px; flex: 1; font-size: 13px; line-height: 1.6; color: var(--gray-500); }
    .emg-call {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        padding: 10px 14px; border-radius: 12px;
        background: var(--red-600); color: #fff;
        font-size: 14px; font-weight: 700; text-decoration: none; letter-spacing: .01em;
        transition: background .2s ease, transform .2s ease;
    }
    .emg-call:hover { background: var(--red-700); transform: translateY(-1px); }

    /* ── Empty state ── */
    .emg-empty {
        display: flex; flex-direction: column; align-items: center; text-align: center;
        padding: 36px 24px;
        background: var(--card-bg); border: 1px dashed var(--gray-300); border-radius: 20px;
    }
    .emg-empty i { font-size: 26px; color: var(--gray-400); }
    .emg-empty h3 { margin: 14px 0 6px; font-size: 15px; font-weight: 700; color: var(--gray-700); }
    .emg-empty p { margin: 0; max-width: 46ch; font-size: 13px; line-height: 1.6; color: var(--gray-500); }

    /* ── Next steps / footer cards ── */
    .emg-panel {
        display: flex; align-items: flex-start; gap: 14px;
        margin-top: 14px; padding: 18px 20px;
        background: var(--card-bg); border: 1px solid var(--gray-200); border-radius: 20px;
    }
    .emg-panel-icon {
        width: 40px; height: 40px; flex: none;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px; background: var(--green-50); color: var(--green-600); font-size: 17px;
    }
    .emg-panel-body { min-width: 0; }
    .emg-panel h3 { margin: 0 0 4px; font-size: 15px; font-weight: 700; color: var(--gray-800); }
    .emg-panel p { margin: 0 0 10px; font-size: 13px; line-height: 1.6; color: var(--gray-500); }
    .emg-panel-link {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 700; color: var(--green-600); text-decoration: none;
    }
    .emg-panel-link:hover { color: var(--green-700); text-decoration: underline; }

    .emg-disclaimer {
        display: flex; align-items: flex-start; gap: 10px;
        margin-top: 22px; padding: 14px 18px;
        background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 16px;
        font-size: 12.5px; line-height: 1.6; color: #92400E;
    }
    .emg-disclaimer i { margin-top: 2px; flex: none; }

    @media (max-width: 768px) {
        .emg-page { padding: 20px 16px 72px; }
        .emg-title { font-size: 24px; }
        .emg-urgent { padding: 18px; }
        .emg-urgent-actions .emg-urgent-btn { flex: 1; justify-content: center; }
    }
</style>
@endpush

@section('content')
<div class="emg-page">

    <span class="emg-eyebrow"><i class="fas fa-heart-crack" aria-hidden="true"></i> Emergency</span>
    <h1 class="emg-title">Emergency support</h1>
    <p class="emg-lede">
        If there is an immediate threat to safety, contact an appropriate emergency service or seek help
        from someone nearby. COMPASS is a peer support network &mdash; it does not replace emergency or
        professional services and cannot promise an immediate response.
    </p>

    {{-- Immediate danger: escalate above everything else on the page. --}}
    <section class="emg-urgent" aria-labelledby="emgUrgentTitle">
        <div class="emg-urgent-head">
            <span class="emg-urgent-icon"><i class="fas fa-phone-volume" aria-hidden="true"></i></span>
            <h2 id="emgUrgentTitle">If you are in danger right now</h2>
        </div>
        <p>
            Call your local emergency number or go to your nearest emergency department. If you can,
            move to a place where someone else can see you, and tell a person you trust what is happening.
        </p>
        <div class="emg-urgent-actions">
            <a class="emg-urgent-btn" href="{{ route('selfhelp') }}">
                <i class="fas fa-heart" aria-hidden="true"></i> Self-help tools
            </a>
            <a class="emg-urgent-btn ghost" href="{{ route('session.history') }}">
                <i class="fas fa-comments" aria-hidden="true"></i> My sessions
            </a>
        </div>
    </section>

    {{-- Published crisis lines. --}}
    <h2 class="emg-section-title"><i class="fas fa-phone" aria-hidden="true"></i> Crisis hotlines</h2>
    <p class="emg-section-sub">Published contacts, kept current by the COMPASS team.</p>

    @if ($hotlines->isNotEmpty())
        <div class="emg-grid">
            @foreach ($hotlines as $hotline)
                <article class="emg-card">
                    <div class="emg-card-head">
                        <span class="emg-card-icon"><i class="fas fa-life-ring" aria-hidden="true"></i></span>
                        <h3>{{ $hotline->agency_name }}</h3>
                    </div>
                    @if ($hotline->description)
                        <p>{{ $hotline->description }}</p>
                    @else
                        <p>Contact this agency for confidential crisis support.</p>
                    @endif
                    <a class="emg-call" href="tel:{{ preg_replace('/[^+0-9]/', '', $hotline->hotline) }}">
                        <i class="fas fa-phone" aria-hidden="true"></i> {{ $hotline->hotline }}
                    </a>
                </article>
            @endforeach
        </div>
    @else
        <div class="emg-empty">
            <i class="fas fa-circle-info" aria-hidden="true"></i>
            <h3>No published contacts are available right now</h3>
            <p>
                Use your local emergency service, or reach out to a trusted person nearby. Crisis hotlines
                are added by the COMPASS team &mdash; please come back later or contact local services directly.
            </p>
        </div>
    @endif

    {{-- What COMPASS can do. --}}
    <h2 class="emg-section-title"><i class="fas fa-hands-holding-circle" aria-hidden="true"></i> What COMPASS can do</h2>
    <p class="emg-section-sub">Peer support is not crisis intervention, but it can still help.</p>

    <div class="emg-panel">
        <span class="emg-panel-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
        <div class="emg-panel-body">
            <h3>Talk to a peer helper</h3>
            <p>Request a support session and match with a trained peer helper who can listen and stay with you while you figure out your next step.</p>
            <a class="emg-panel-link" href="{{ route('request.screening') }}">Request support <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </div>

    <div class="emg-panel">
        <span class="emg-panel-icon"><i class="fas fa-book-open" aria-hidden="true"></i></span>
        <div class="emg-panel-body">
            <h3>Use the self-help library</h3>
            <p>Guided grounding, breathing and coping exercises you can work through on your own time.</p>
            <a class="emg-panel-link" href="{{ route('selfhelp') }}">Browse self-help tools <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </div>

    <div class="emg-disclaimer">
        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
        <span>
            COMPASS peer sessions are not monitored continuously and are not an emergency service. If a safety
            concern is raised during a session, the assigned peer helper and a moderator are notified so the
            right escalation can happen &mdash; but response is never guaranteed. In an emergency, always contact
            your local emergency number first.
        </span>
    </div>

</div>
@endsection
