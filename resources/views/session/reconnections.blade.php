@extends('layouts.app')
@section('title', 'Connection review - COMPASS')
@section('content')
@include('partials.referral-ui-styles')
@php
    $moderator = auth()->user()->role === 'moderator';
@endphp
<div class="referral-ui connection-review">
    <header class="cr-header"><div><h1>Connection review</h1><p class="ru-muted">{{ $moderator ? 'Help Seekers reconnect with support when a chat is interrupted.' : 'Review invitations to continue an interrupted chat.' }}</p></div><a class="ru-button" href="{{ url()->current() }}"><i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh</a></header>
    @if(session('success'))<div class="cr-alert" role="status"> {{ session('success') }}</div>@endif
    @if($errors->any())<div class="cr-alert cr-error" role="alert">{{ $errors->first() }}</div>@endif
    <section class="cr-summary" aria-label="Connection overview">
        <div class="cr-count"><div><strong>{{ $incidents->total() }}</strong><span class="ru-muted">{{ $moderator ? 'Open interruptions' : 'Replacement offers' }}</span></div></div>
        <p class="ru-muted">Use Refresh to check for the latest updates.</p>
    </section>
    <div class="cr-list-heading"><h2>{{ $moderator ? 'Chats needing attention' : 'Your invitations' }}</h2><span class="ru-muted">Philippine Time (PHT)</span></div>
    @forelse($incidents as $incident)
    @php
        $emergency = $incident->session->requires_immediate_action || $incident->session->risk_level === 'emergency';
        $labels = ['interrupted'=>'Reconnecting', 'waiting'=>'Seeker chose to wait', 'requested'=>'Replacement requested', 'offered'=>'Awaiting Helper response'];
        $explanations = ['interrupted'=>'Allow the reconnection window to finish. The Seeker can then request a replacement.', 'waiting'=>'The Seeker is waiting for their original Helper. No replacement is requested.', 'requested'=>'The Seeker has requested another Helper. Find an eligible Helper to send an invitation.', 'offered'=>'An invitation has been sent. The Helper must accept before the chat is transferred.'];
    @endphp
    <section class="ru-card">
        <div class="cr-header"><h2>Interrupted chat <span class="ru-muted">#{{ $incident->session_id }}</span></h2><span class="cr-badge">{{ $emergency ? 'Adviser coordination required' : ($labels[$incident->status] ?? ucfirst($incident->status)) }}</span></div>
        <p>{{ $emergency ? 'Coordinate this emergency with the responsible Adviser. Ordinary replacement matching is unavailable.' : $explanations[$incident->status] }}</p>
        <dl class="cr-details"><div><dt>Interruption detected</dt><dd>{{ $incident->detected_at->timezone('Asia/Manila')->format('M j, g:i A') }} PHT</dd></div><div><dt>Session deadline</dt><dd>{{ $incident->session->start_time?->copy()->addMinutes(90)->timezone('Asia/Manila')->format('g:i A') ?? 'Unavailable' }} PHT</dd></div></dl>
        <div class="cr-bottom">
        @if($moderator)
            <p class="ru-muted">Only eligible Helpers receive an offer. The original session deadline stays the same.</p>
            @if($incident->status === 'requested' && !$emergency)
            <form method="POST" action="{{ route('reconnections.offer',$incident) }}" data-connection-form>@csrf<button type="submit">Find replacement Helper</button></form>
            @endif
        @else
            <p class="ru-muted">Earlier private messages are not shared. Please introduce yourself when the new chat opens.</p>
            <form method="POST" action="{{ route('reconnections.accept',$incident) }}" class="ru-actions" data-connection-form>@csrf<button type="submit" name="accept" value="1">Accept and open chat</button><button type="submit" name="accept" value="0" class="cr-secondary">Decline offer</button></form>
        @endif
        </div>
    </section>
    @empty
        <div class="ru-card cr-empty"><h2>{{ $moderator ? 'No interruptions to review' : 'No replacement offers' }}</h2><p class="ru-muted">{{ $moderator ? 'New connection interruptions will appear here. You will also receive a notification.' : 'You will receive a notification when a Moderator sends you an offer.' }}</p><a class="ru-button" href="{{ route($moderator ? 'moderator.dashboard' : 'helper.dashboard') }}">Back to dashboard</a></div>
    @endforelse
    {{ $incidents->links() }}
</div>
<style>
.connection-review{width:100%;max-width:1600px;margin:0 auto;padding:28px 32px 40px;min-width:0}
.connection-review>.cr-header{margin-bottom:24px;align-items:flex-start}
.cr-header,.cr-bottom{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.cr-header p{margin-bottom:0}.cr-header .ru-button{gap:8px;flex-shrink:0}
.cr-summary{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;padding:20px 24px;background:#fff;border:1px solid #e0e9e3;border-radius:16px;margin-bottom:26px}
.cr-count{display:flex;gap:14px;align-items:center}.cr-count-icon,.cr-empty-icon{display:flex;align-items:center;justify-content:center;background:#eaf7ef;color:#078749;border-radius:12px;width:46px;height:46px;font-size:19px;flex-shrink:0}
.cr-count strong{display:block;font-size:26px;line-height:1.2;font-weight:700;color:#203c30}.cr-count .ru-muted{display:block;margin-top:4px}.cr-summary p{margin:0}
.cr-list-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}.cr-list-heading h2{margin:0;font-size:16px}
.cr-badge{padding:6px 10px;background:#edf7f1;color:#17633c;border-radius:20px;font-size:12px;font-weight:600}
.cr-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;padding:16px;background:#f7faf8;border-radius:10px;margin:16px 0}
.cr-bottom{border-top:1px solid #e0e9e3;padding-top:16px}.cr-bottom p{flex:1 1 280px;margin:0!important}.cr-bottom .ru-actions{margin:0}
.connection-review button.cr-secondary{background:#fff;color:#52645a;border-color:#ccd9d1}
.cr-alert{padding:14px 16px;background:#edf9f1;border:1px solid #cbe4d3;border-radius:10px;margin:16px 0}.cr-error{background:#fff4f2;color:#a12a21;border-color:#efc7c1}
.connection-review .ru-card{margin-top:0;margin-bottom:16px}.connection-review .cr-empty{text-align:center;padding:40px 24px}.cr-empty-icon{margin:0 auto 16px;width:52px;height:52px;border-radius:50%;font-size:22px}.cr-empty p{max-width:460px;margin:10px auto 20px}.cr-empty h2{font-size:17px}
@media(max-width:768px){.connection-review{padding:20px 16px 28px}.cr-summary{padding:18px}.cr-summary>p{flex-basis:100%}.cr-details{grid-template-columns:1fr}.cr-bottom form,.cr-bottom button{width:100%}.cr-bottom .ru-actions{display:grid}.cr-header{align-items:flex-start}.cr-list-heading{align-items:flex-start;flex-wrap:wrap}.connection-review .cr-empty{padding:32px 18px}}
</style>
<script>
document.querySelectorAll('[data-connection-form]').forEach(form=>form.addEventListener('submit',event=>{
 if(form.dataset.busy){event.preventDefault();return;}
 form.dataset.busy='true';form.setAttribute('aria-busy','true');
 const button=event.submitter;
 if(button?.name){const field=document.createElement('input');field.type='hidden';field.name=button.name;field.value=button.value;form.appendChild(field);}
 form.querySelectorAll('button').forEach(b=>b.disabled=true);
 if(button)button.textContent='Please wait...';
}));
</script>
@endsection
