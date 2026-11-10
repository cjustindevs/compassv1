@extends('layouts.app')
@section('title', 'Live sessions - COMPASS')
@section('content')
<div class="adviser-page-content ls-page">
    <header class="ls-head">
        <div>
            <h1>Live sessions</h1>
            <p>Active chats of the Helpers you supervise. Watching is read-only; Seekers are shown by alias. You can send private coaching notes that only the Helper sees.</p>
        </div>
        <a class="ls-refresh" href="{{ route('adviser.live.index') }}">Refresh</a>
    </header>
    @if(session('status'))<p class="ls-flash" role="status">{{ session('status') }}</p>@endif
    @if($sessions->isEmpty())
        <section class="ls-empty"><strong>No live sessions right now</strong><p>When one of your Helpers starts a chat, it will appear here.</p></section>
    @else
        <div class="ls-grid">
            @foreach($sessions as $session)
                <article class="ls-card">
                    <div class="ls-top"><span class="ls-live">Live</span><span class="ls-risk ls-risk-{{ $session->risk_level ?? 'low' }}">{{ ucfirst($session->risk_level ?? 'low') }} risk</span></div>
                    <h2>{{ $session->helper?->full_name ?? 'Helper' }}</h2>
                    <p class="ls-meta">With {{ $session->seeker?->generated_alias ?? 'Seeker' }} · {{ $session->concern?->concern_name ?? 'General concern' }}</p>
                    <p class="ls-meta">Started {{ $session->start_time?->copy()->timezone('Asia/Manila')->format('g:i A') }} PHT · {{ $session->start_time?->diffForHumans(null, true) }} ago</p>
                    <a class="ls-watch" href="{{ route('adviser.live.show', $session->id) }}">Watch live</a>
                </article>
            @endforeach
        </div>
    @endif
</div>
<style>
.ls-page { padding: 20px 28px 40px; display: grid; gap: 16px; }
.ls-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.ls-head h1 { margin: 0; font-size: 24px; font-weight: 800; color: #163b2d; }
.ls-head p { margin: 4px 0 0; font-size: 14px; color: #64746b; max-width: 75ch; }
.ls-refresh { border: 1px solid #cbd5cf; border-radius: 10px; padding: 8px 14px; font-size: 13px; font-weight: 600; color: #26443a; text-decoration: none; background: #fff; }
.ls-flash { background: #fff8e1; border: 1px solid #f1dea0; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #7a5a00; margin: 0; }
.ls-empty { background: #fff; border: 1px dashed #cbd5cf; border-radius: 14px; padding: 28px; text-align: center; color: #64746b; font-size: 14px; }
.ls-empty strong { display: block; color: #163b2d; margin-bottom: 4px; }
.ls-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.ls-card { background: #fff; border: 1px solid #dfe9e3; border-radius: 14px; padding: 14px 16px; display: grid; gap: 6px; }
.ls-top { display: flex; gap: 6px; }
.ls-live { font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #fff; background: #d03b3b; border-radius: 99px; padding: 2px 9px; }
.ls-risk { font-size: 11px; font-weight: 700; border-radius: 99px; padding: 2px 9px; background: #f1f4f2; color: #4b5b52; }
.ls-risk-high, .ls-risk-emergency { background: #fdecec; color: #a32626; }
.ls-risk-moderate { background: #fff8e1; color: #7a5a00; }
.ls-card h2 { margin: 4px 0 0; font-size: 16px; font-weight: 700; color: #163b2d; }
.ls-meta { margin: 0; font-size: 12.5px; color: #64746b; }
.ls-watch { margin-top: 6px; justify-self: start; background: #078749; color: #fff; border-radius: 10px; padding: 8px 14px; font-size: 13px; font-weight: 700; text-decoration: none; }
@media (max-width: 700px) { .ls-page { padding: 16px; } }
</style>
@endsection
