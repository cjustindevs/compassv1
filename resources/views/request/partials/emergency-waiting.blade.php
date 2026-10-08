@php
    $alert = $session->supportEmergencyAlert ?? $session->emergencyAlerts()->latest('triggered_at')->first();
    $reviewClosed = $alert && in_array($alert->status, ['resolved', 'closed'], true);
    $acknowledged = $alert?->acknowledged_at !== null;
    $startedAt = $alert?->triggered_at;
    $endedAt = $reviewClosed ? $alert->resolved_at : now();
    $elapsedMinutes = $startedAt && $endedAt && $startedAt->lte($endedAt)
        ? (int) floor($startedAt->diffInSeconds($endedAt) / 60) : null;
@endphp
<style>
.emergency-wait {margin-top:20px;min-width:0}
.emergency-wait .ew-heading {display:flex;justify-content:space-between;align-items:start;gap:20px;flex-wrap:wrap;margin-bottom:20px}
.emergency-wait .ew-heading h2 {font-size:22px;line-height:1.35;margin:8px 0}
.emergency-wait .ew-heading p {margin:0;max-width:560px}
.emergency-wait .ew-time {padding:14px 18px;background:#f8faf9;border:1px solid #e2e8e5;border-radius:12px;min-width:180px}
.emergency-wait .ew-time span {display:block;font-size:12px;color:#64748b}
.emergency-wait .ew-time strong {display:block;font-size:24px;line-height:1.4;color:#163b2d;font-variant-numeric:tabular-nums}
.emergency-wait .ew-grid {display:grid;grid-template-columns:1fr 1fr;gap:16px}
.emergency-wait .ew-panel {padding:20px;border:1px solid #e2e8e5;border-radius:14px;min-width:0}
.emergency-wait .ew-panel h3 {font-size:15px;font-weight:600;margin:0 0 8px}
.emergency-wait .ew-panel p {margin:0 0 12px}
.emergency-wait .ew-status {display:inline-flex;font-size:12px;font-weight:600;color:#027039;background:#eaf8f0;border-radius:20px;padding:5px 10px;margin-bottom:12px}
.emergency-wait .ew-resources {margin-top:16px;background:#fffbeb;border:1px solid #fde6ab;border-radius:14px;padding:20px;display:flex;justify-content:space-between;gap:20px;align-items:center}
.emergency-wait .ew-resources h3 {font-size:15px;font-weight:600;margin:0 0 8px;color:#78350f}
.emergency-wait .ew-resources p {margin:0;max-width:680px;color:#785438}
.emergency-wait a {flex-shrink:0}
@media(max-width:768px){.emergency-wait .ew-grid{grid-template-columns:1fr}.emergency-wait .ew-resources{align-items:stretch;flex-direction:column}.emergency-wait .ew-panel,.emergency-wait .ew-resources{padding:16px}}
@media(max-width:480px){.emergency-wait .ew-heading{gap:14px}.emergency-wait .ew-time{width:100%}.emergency-wait .ew-heading h2{font-size:20px}.emergency-wait .seeker-button{width:100%;min-height:44px;white-space:normal}}
</style>
<section class="emergency-wait" aria-label="Emergency support status">
    <header class="ew-heading">
        <div>
            <span class="seeker-badge">Emergency support request</span>
            <h2>{{ $reviewClosed ? 'Your emergency review has ended' : 'Your request is awaiting support' }}</h2>
            <p>Staff review and temporary peer support are tracked separately below.</p>
        </div>
        <div class="ew-time">
            <span>{{ $reviewClosed ? 'Time to review closure' : 'Time since alert' }}</span>
            <strong>{{ $elapsedMinutes === null ? 'Not recorded' : ($elapsedMinutes < 1 ? 'Less than a minute' : (intdiv($elapsedMinutes, 60) > 0 ? intdiv($elapsedMinutes, 60).' hr '.($elapsedMinutes % 60).' min' : $elapsedMinutes.' min')) }}</strong>
            @if($startedAt)<span>Recorded {{ $startedAt->copy()->timezone('Asia/Manila')->format('M d, g:i A') }} PHT</span>@endif
        </div>
    </header>
    <div class="ew-grid">
        <article class="ew-panel">
            <h3>Staff review</h3>
            <span class="ew-status">{{ $reviewClosed ? 'Review closed' : ($acknowledged ? 'Acknowledged' : 'Awaiting acknowledgment') }}</span>
            <p>{{ $reviewClosed ? 'The emergency review has been closed. Your peer-support session has its own status.' : ($acknowledged ? 'A staff acknowledgment has been recorded. The Adviser coordinates further support.' : 'An alert has been recorded for adviser review. The Moderator coordinates temporary peer support.') }}</p>
        </article>
        <article class="ew-panel">
            <h3>Temporary peer support</h3>
            @if($session->session_status === 'active' && $session->helper_accepted_at)
                <span class="ew-status">Helper connected</span>
                <p>Your Helper is connected while the Adviser coordinates further support.</p>
                <a class="seeker-button" href="{{ route('session.chat') }}">Open chat</a>
            @elseif($session->helper_id)
                <span class="ew-status">{{ $session->helper_accepted_at ? 'Preparing your chat' : 'Waiting for acceptance' }}</span>
                <p>{{ $session->helper_accepted_at ? 'Your Helper has accepted and is preparing the chat.' : 'An eligible Helper has been invited. Waiting for acceptance.' }}</p>
            @else
                <span class="ew-status">Waiting for a Helper</span>
                <p>No Helper connection is confirmed yet. {{ $reviewClosed ? 'You can access the resources below if you still need support.' : 'Your emergency review remains open while the Moderator coordinates support.' }}</p>
            @endif
        </article>
    </div>
    <aside class="ew-resources">
        <div><h3>Emergency resources are available</h3><p>COMPASS cannot promise an immediate emergency response and does not replace emergency or professional services. If you are in immediate danger, use your local emergency service now.</p></div>
        <a class="seeker-button secondary" href="{{ route('emergency') }}">View emergency resources</a>
    </aside>
</section>
