@php
$followUp = $session->requires_adviser_review && !in_array($session->session_status, ['completed','evaluated','cancelled','no_show'], true)
    ? $session->screeningResponses()->oldest('id')->first() : null;
@endphp
@if(($followUp ?? null)?->instrument_version === \App\Services\CompactScreening::SINGLE_VERSION && $followUp->follow_up_requested_at)
<style>
.seeker-follow-up{margin-top:20px;padding:16px;border:1px solid #dce8e1;border-radius:12px;background:#fff;min-width:0}
.seeker-follow-up h2{font-size:16px;font-weight:600}.seeker-follow-up p{margin:8px 0;font-size:14px;overflow-wrap:anywhere}
.seeker-follow-up fieldset{border:0;padding:0;min-width:0}.seeker-follow-up legend{font-size:14px;font-weight:500}
.seeker-follow-up .follow-up-choices{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}
.seeker-follow-up .follow-up-choice{display:flex;align-items:center;gap:8px;min-height:44px;padding:8px 12px;border:1px solid #dce8e1;border-radius:8px;font-size:14px;cursor:pointer}
.seeker-follow-up .follow-up-choice:has(input:checked){background:#edf9f1;border-color:#009b58}
.seeker-follow-up button{padding:10px 16px;border:0;border-radius:8px;background:#009b58;color:#fff;font-size:14px;font-weight:600}
</style>
<section class="seeker-follow-up" aria-labelledby="follow-up-heading">
<h2 id="follow-up-heading" class="font-semibold">Your Adviser's follow-up</h2>
@if($followUp->follow_up_note)<p>{{ $followUp->follow_up_note }}</p>@endif
@if(!$followUp->follow_up_answered_at)
<p>You may answer this follow-up or choose not to say. Matching continues while staff review.</p>
<form method="POST" action="{{ route('request.follow-up.respond',$session) }}" data-followup-form>
@csrf
<input type="hidden" name="screening_id" value="{{ $followUp->id }}">
<fieldset>
<legend>{{ \App\Services\CompactScreening::SINGLE_QUESTION }}</legend>
<div class="follow-up-choices">
@foreach(['yes'=>'Yes','no'=>'No','prefer_not_to_say'=>'Prefer not to say'] as $value=>$label)
<label class="follow-up-choice"><input type="radio" name="follow_up_answer" value="{{ $value }}" required @checked(old('follow_up_answer') === $value)> {{ $label }}</label>
@endforeach
</div>
</fieldset>
@error('follow_up_answer')<p class="text-red-700" role="alert">{{ $message }}</p>@enderror
<button type="submit" class="seeker-button">Send response</button>
</form>
@else
<p><strong>Response saved:</strong> {{ match($followUp->follow_up_answer) { 'yes'=>'Yes','no'=>'No',default=>'Prefer not to say' } }}</p>
<p>{{ $followUp->follow_up_answer === 'prefer_not_to_say' ? 'Your choice was saved. No safety classification was assumed.' : 'Response saved. Staff review continues alongside matching.' }}</p>
@endif
</section>
@endif
