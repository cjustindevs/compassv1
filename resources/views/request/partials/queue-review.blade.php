@if($staffAlert || $session->requires_adviser_review)
@php
    $reviewClosed = $staffAlert && (in_array($staffAlert->status, \App\Services\ModeratorEmergencyCases::TERMINAL, true) || $staffAlert->archived_at);
    $reviewer = $staffAlert ? $staffAlert->adviser : $session->reviewAdviser;
    $reviewerName = trim(($reviewer?->first_name ?? '').' '.($reviewer?->last_name ?? ''));
@endphp
<section class="queue-review" aria-labelledby="queue-review-title" data-queue-review>
    <div><h2 id="queue-review-title">{{ $staffAlert ? 'Urgent staff review' : 'Staff review' }}</h2>
        <p>{{ $reviewClosed ? 'Review closed' : ($staffAlert?->acknowledged_at ? 'Responding' : 'Awaiting staff review') }}</p>
    </div>
    <div><span>{{ $staffAlert ? 'Responsible emergency coordinator' : 'Assigned screening reviewer' }}</span><strong>{{ $reviewerName ?: 'Awaiting assignment' }}</strong></div>
    <p class="queue-review-note">
        @if($reviewClosed)
            Your peer-support request has its own status below.
        @elseif($session->isActive())
            Staff review continues alongside your support session.
        @elseif($session->helper_id)
            Staff review continues alongside the Helper assignment.
        @else
            {{ $matchingAllowed ? 'Finding a Helper continues while staff review.' : 'Staff review is required before peer matching can continue.' }}
        @endif
    </p>
</section>
@endif
