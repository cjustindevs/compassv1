@once<link rel="stylesheet" href="{{ asset('css/referral-completion.css') }}?v={{ filemtime(public_path('css/referral-completion.css')) }}">@endonce
@php($reports = $referral->completionReviews()->with('professional','reviewer')->latest('id')->get())
@if($reports->isNotEmpty())
<section class="completion-card" id="completionReview"><h2>Professional completion review</h2>
@php($currentReport=$reports->first())
@if($currentReport->review_status==='pending' && in_array($referral->status,\App\Models\Referral::ACTIVE_STATUSES,true) && $referral->help_seeker_consent && $currentReport->professional_id===$referral->professional_id)
<span class="completion-status pending">Completion review pending</span>
<p>Submitted by {{ $currentReport->professional?->full_name }} &middot; {{ $currentReport->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</p>
<h3>Outcome summary</h3><p class="completion-note">{{ $currentReport->summary }}</p><p>Follow-up needed: {{ $currentReport->follow_up_required ? 'Yes' : 'No' }}</p><p class="completion-note">{{ $currentReport->follow_up_notes }}</p>
@if((int)$referral->adviser_id===(int)auth()->user()->adviser->id)
<form class="completion-form" method="POST" action="{{ route('referrals.completion.review',$currentReport) }}" data-confirm="Record this completion decision?" data-confirm-message="Referral completion and emergency resolution remain separate decisions." data-confirm-text="Record decision">@csrf
<input type="hidden" name="decision" value="">
<label>Decision notes / follow-up instructions<textarea name="review_notes" required minlength="10" maxlength="2000">{{ old('review_notes') }}</textarea></label>
@php($openAppointments=$referral->appointments()->where('status','scheduled')->get())
@if($openAppointments->isNotEmpty())<p>Outstanding appointments:</p>@foreach($openAppointments as $appointment)<p>{{ $appointment->starts_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT &middot; {{ ucwords(str_replace('_',' ',$appointment->seeker_response ?? 'awaiting confirmation')) }}</p>@endforeach
<label>Disposition of outstanding appointments (for confirmation)<select name="appointments_action"><option value="">Choose disposition</option><option value="completed">Completed (past appointments only)</option><option value="cancelled">Cancel remaining appointments</option></select></label>@endif
@php($outstandingFollowUps=$referral->professionalNotes()->whereDate('follow_up_date','>=',now('Asia/Manila')->toDateString())->orderBy('follow_up_date')->get())
@if($outstandingFollowUps->isNotEmpty())
    <p>Outstanding documented follow-up dates:</p>
    <ul>@foreach($outstandingFollowUps as $followUp)<li>{{ $followUp->follow_up_date->format('M d, Y') }}</li>@endforeach</ul>
@endif
<label>Appointment / outstanding follow-up disposition reason<textarea name="appointments_reason" maxlength="1000">{{ old('appointments_reason') }}</textarea></label><p class="completion-muted">Required when confirming a report with outstanding appointments or follow-up. A future appointment cannot be marked completed.</p>
<div class="completion-actions">
    <button type="submit" class="completion-button primary" onclick="this.form.elements.decision.value='confirmed'">Confirm referral completion</button>
    <button type="submit" class="completion-button" onclick="this.form.elements.decision.value='follow_up_requested'">Request follow-up</button>
</div>
</form>
@else<p>Only the assigned referral Adviser can confirm completion. Coordinate with {{ $referral->adviser?->full_name ?? 'the assignment coordinator' }}.</p>@endif
@else<p>Status: {{ ucwords(str_replace('_',' ',$currentReport->review_status)) }}. Referral: {{ ucwords(str_replace('_',' ',$referral->status)) }}.</p>@endif
@foreach(\App\Models\EmergencyAlert::where('referral_id',$referral->id)->latest('id')->get() as $completionEmergency)
<p><strong>Emergency #{{ $completionEmergency->id }}:</strong> {{ ucfirst($completionEmergency->status) }}</p>
@if((int)$completionEmergency->adviser_id===(int)auth()->user()->adviser->id || (int)$completionEmergency->session?->helper?->adviser_id===(int)auth()->user()->adviser->id)<a class="completion-button" href="{{ route('adviser.emergencies.show',$completionEmergency) }}">Review emergency for resolution</a>@else<p class="completion-muted">Emergency resolution remains with its responsible reviewer.</p>@endif
@endforeach
<details><summary>Completion report history</summary>@foreach($reports as $report)<article><strong>{{ ucwords(str_replace('_',' ',$report->review_status)) }}</strong><p class="completion-note">{{ $report->summary }}</p><p class="completion-note">{{ $report->review_notes }}</p><p class="completion-note">{{ $report->appointment_disposition_notes }}</p><p class="completion-muted">Submitted {{ $report->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT @if($report->reviewed_at)&middot; Reviewed {{ $report->reviewed_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT by {{ $report->reviewer?->name }}@endif</p></article>@endforeach</details>
</section>@endif
