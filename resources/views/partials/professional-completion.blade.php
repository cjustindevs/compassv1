@once
<link rel="stylesheet" href="{{ asset('css/referral-completion.css') }}?v={{ filemtime(public_path('css/referral-completion.css')) }}">
@endonce
@php
    $completionReports = $referral->completionReviews()->with('professional','reviewer')->latest('id')->get();
    $latestCompletion = $completionReports->first();
    $completionPending = $latestCompletion && $latestCompletion->review_status === 'pending' && $latestCompletion->professional_id === $referral->professional_id;
    $completionActive = in_array($referral->status, \App\Models\Referral::ACTIVE_STATUSES, true) && $referral->help_seeker_consent;
@endphp
<section class="completion-card" id="professionalCompletion">
    <h2>Completion and Adviser review</h2>
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($completionPending && $completionActive)
        <span class="completion-status pending">Awaiting Adviser resolution</span>
        <p>Your completion report is saved. The Adviser must review it before the referral is completed. Any emergency is resolved separately.</p>
    @elseif($completionActive)
        @if($latestCompletion?->review_status === 'follow_up_requested')
            <span class="completion-status pending">Follow-up requested</span><p class="completion-note">{{ $latestCompletion->review_notes }}</p>
        @endif
        <p>When your work is finished, submit an outcome summary for the assigned Adviser's review.</p>
        <button type="button" class="completion-button primary" onclick="document.getElementById('completionReportDialog').showModal()">{{ $latestCompletion?->review_status === 'follow_up_requested' ? 'Submit updated completion report' : 'Submit completion for Adviser review' }}</button>
    @elseif($referral->status === 'completed')<span class="completion-status">Completed</span><p>Referral support has concluded. Linked emergency status is recorded separately.</p>
    @else<p>Completion reporting is available after acceptance and while referral consent remains active.</p>@endif
    @if($completionReports->isNotEmpty())<details><summary>View submitted reports and decisions</summary>
        @foreach($completionReports as $report)<article><strong>{{ ucwords(str_replace('_',' ',$report->review_status)) }}</strong><p class="completion-muted">Submitted {{ $report->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT by {{ $report->professional?->full_name }}</p><p class="completion-note">{{ $report->summary }}</p><p>Follow-up needed: {{ $report->follow_up_required ? 'Yes' : 'No' }}</p>@if($report->follow_up_notes)<p class="completion-note">{{ $report->follow_up_notes }}</p>@endif @if($report->reviewed_at)<p class="completion-note">Adviser decision: {{ $report->review_notes }}</p>@if($report->appointment_disposition_notes)<p class="completion-note">Appointment / follow-up disposition: {{ $report->appointment_disposition_notes }}</p>@endif<p class="completion-muted">{{ $report->reviewed_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</p>@endif</article>@endforeach
    </details>@endif
</section>
@if($completionActive && !$completionPending)
<dialog id="completionReportDialog" class="completion-dialog" aria-labelledby="completionReportTitle">
<header><h2 id="completionReportTitle">Submit completion report #{{ $referral->id }}</h2><button type="button" class="completion-button" aria-label="Close completion report" onclick="this.closest('dialog').close()">Close</button></header>
<form method="POST" action="{{ route('referrals.completion.submit',$referral) }}" onsubmit="return window.confirm('Submit completion for Adviser review? This does not close the referral or emergency.')">@csrf
<div class="completion-body completion-form">
<p>Record the work completed and the next steps. Include only information necessary for the authorized review.</p>
<label>Outcome summary<textarea name="outcome" required minlength="10" maxlength="2000">{{ old('outcome') }}</textarea></label>
<label>Further support or follow-up needed?<select name="follow_up_required" required><option value="">Select</option><option value="0" @selected((string)old('follow_up_required')==='0')>No</option><option value="1" @selected((string)old('follow_up_required')==='1')>Yes</option></select></label>
<label>Follow-up instructions (required when Yes)<textarea name="follow_up_notes" maxlength="2000">{{ old('follow_up_notes') }}</textarea></label>
<p class="completion-muted">Appointments remain in the record. The Adviser must document any outstanding appointments before confirming completion.</p>
</div><footer><button type="button" class="completion-button" onclick="this.closest('dialog').close()">Cancel</button><button type="submit" class="completion-button primary">Submit and notify Adviser</button></footer>
</form></dialog>
@if($errors->has('outcome') || $errors->has('follow_up_required') || $errors->has('follow_up_notes'))<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('completionReportDialog').showModal());</script>@endif
@endif
