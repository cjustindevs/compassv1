@extends('layouts.app')
@section('content')
<style>
.seeker-page:has(.request-status) .seeker-heading {margin-bottom:18px}
.seeker-page:has(.request-status) .seeker-card {padding:20px;border-radius:12px}
.request-status {min-width:0}
.request-status .seeker-toolbar {margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #edf2ef;gap:12px}
.request-status .seeker-toolbar p {margin:0;font-size:13px}
.request-status .seeker-button {min-height:44px;padding:10px 14px;white-space:normal;text-align:center}
.request-status .review-layout {display:grid;grid-template-columns:minmax(0,1fr) minmax(220px,320px);gap:24px;align-items:start}
.request-status .review-label {display:block;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#027039;margin-bottom:8px}
.request-status .review-copy h2 {font-size:20px;line-height:1.4;margin:0 0 8px}
.request-status .review-copy p {max-width:620px;margin:0;line-height:1.6}
.request-status .review-assignment {padding:16px;background:#f0faf4;border:1px solid #d4eee0;border-radius:10px;overflow-wrap:anywhere}
.request-status .review-assignment p {margin:0;line-height:1.5}
.request-status .review-assignment strong {display:block;font-size:16px;color:#163b2d;margin-top:6px}
.request-status .review-actions {display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}
.request-status .review-actions .danger {background:#fff;border-color:#fecaca}
.request-status .status-footer {margin-top:20px;padding-top:16px;border-top:1px solid #edf2ef}
.request-status .status-refresh {display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.request-status .status-refresh p {margin:0;font-size:12px}
.request-status .status-history {margin-top:14px}
.request-status .status-history summary {cursor:pointer;font-size:13px;font-weight:600;padding:8px 0}
.request-status .status-history ol {list-style:none;padding:0;margin:8px 0 0;display:grid;gap:12px}
.request-status .status-history li {border-left:2px solid #cce8d9;padding-left:12px;font-size:13px;overflow-wrap:anywhere}
.request-status .status-history time {display:block;font-size:12px;color:#64748b;margin-top:3px}
.request-status .seeker-follow-up{margin-top:20px;padding-top:16px;border-top:1px solid #edf2ef;min-width:0}
.request-status .seeker-follow-up p{margin:8px 0;overflow-wrap:anywhere;white-space:pre-wrap}
.request-status .seeker-follow-up fieldset{min-width:0;border:0;padding:0;margin:12px 0}
.request-status .follow-up-choices{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.request-status .follow-up-choice{display:flex;align-items:center;gap:8px;min-height:44px;padding:10px 14px;border:1px solid #d4e4da;border-radius:8px;cursor:pointer}
.request-status .follow-up-choice:has(input:checked){background:#edf9f1;border-color:#009b58}
.request-status .follow-up-choice input{accent-color:#009b58}
@media(max-width:768px){.request-status .review-layout{grid-template-columns:minmax(0,1fr);gap:16px}.seeker-page:has(.request-status) .seeker-card{padding:16px}.request-status .review-copy h2{font-size:18px}}
@media(max-width:480px){.request-status .seeker-toolbar{align-items:stretch;flex-direction:column}.request-status .review-actions{flex-direction:column}.request-status .review-actions .seeker-button{width:100%}.request-status .status-refresh{align-items:stretch;flex-direction:column}}
</style>
<x-seeker-step title="Your support request">
<div class="request-status">
<div class="seeker-toolbar"><p class="font-semibold">Reference {{ $session->reference_number }}</p><a class="seeker-button secondary" href="{{ route('seeker.requests.show',$session) }}">View request details</a></div>
@switch($session->permitsEmergencySupport() ? 'emergency_escalated' : $session->workflow_state)
@case('emergency_escalated')
@include('request.partials.emergency-waiting')
@break
@case('adviser_review_required')
<section class="review-layout" aria-labelledby="review-heading">
<div class="review-copy">
<span class="review-label">Staff review</span>
<h2 id="review-heading">Awaiting adviser review</h2>
<p>Your responses need review before matching can continue. This page updates when a decision is recorded.</p>
<div class="review-actions">
<a class="seeker-button secondary" href="{{ route('selfhelp') }}">Browse self-help</a>
<a class="seeker-button danger" href="{{ route('emergency') }}">Emergency resources</a>
</div>
</div>
<aside class="review-assignment" aria-label="Staff review status">
<span class="review-label">Staff review</span>
<p>Your request is under staff review. You will be notified when a decision is recorded.</p>
</aside>
</section>

@break
@case('closed')<h2 class="font-semibold">This request has ended</h2>@if($session->session_status === \App\Models\Session::STATUS_NO_SHOW)<p>The helper was not able to join, and this request has been closed. If you still need support, you can start a new request.</p>@elseif($session->session_status === \App\Models\Session::STATUS_CANCELLED)<p>This request was cancelled. If you still need support, you can start a new request.</p>@else<p>This conversation has been closed. If you still need support, you can start a new request.</p>@endif@break
@case('session_ready')<h2 class="font-semibold">Your helper is preparing</h2><p>Your request has been accepted. Chat opens when the helper starts the session.</p>@break
@case('session_active')<h2 class="font-semibold">Session status</h2>@if($session->helper_accepted_at && $session->helper)<a class="seeker-button" href="{{ route('session.chat', ['session_id'=>$session->id]) }}">Open chat</a>@elseif(!$session->helper_accepted_at)<p>This older request has no recorded helper acceptance. Please contact your adviser for assistance with this request.</p>@else<p>The helper connection for this request cannot be confirmed. Please contact your support team for assistance.</p>@endif@break
@case('helper_pending_acceptance')<h2 class="font-semibold">Waiting for helper acceptance</h2><p>{{ $session->helper?->public_alias ?? 'Peer Helper' }} has been invited. Chat opens after acceptance when the helper starts the session.</p>@break
@default
@if(in_array($session->workflow_state, ['screening_required', 'concern_required', 'session_preferences_required', 'ready_for_submission'], true))
<h2 class="font-semibold">Your request is not submitted yet</h2><p>Finish the remaining steps to be matched with a trained peer helper.</p><a class="seeker-button" href="{{ route('request.matching') }}">Continue setting up your request</a>
@elseif($session->workflow_state === 'evaluation_pending')
<h2 class="font-semibold">Your conversation has ended</h2><p>Thank you for using COMPASS. Your feedback helps us keep improving.</p>
@else
<h2 class="font-semibold">Waiting for an available helper</h2><p>{{ app(\App\Services\OperatingHoursService::class)->message() }}</p>
@endif
@endswitch
@include('request.partials.screening-follow-up')
<footer class="status-footer">
<div class="status-refresh"><p>This page refreshes every 15 seconds while you wait.</p><a class="seeker-button secondary" href="{{ route('request.matching') }}">Refresh status</a></div>
<details class="status-history"><summary>Status history</summary><ol>@forelse($events as $event)<li>{{ \App\Services\SeekerRequestPresentation::STATES[$event->to_state] ?? 'Request updated' }}<time>{{ \Illuminate\Support\Carbon::parse($event->occurred_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</time></li>@empty<li>No status updates recorded yet.</li>@endforelse</ol></details>
</footer>
</div>
</x-seeker-step>
@push('scripts')<script>
setInterval(() => {
    const form = document.querySelector('[data-followup-form]');
    const responding = form && (form.contains(document.activeElement) || form.querySelector('input[name="follow_up_answer"]:checked'));
    if (document.visibilityState === 'visible' && !responding && !document.querySelector('dialog[open]')) location.reload();
}, 15000);
</script>@endpush
@endsection
