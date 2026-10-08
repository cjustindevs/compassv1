@extends('layouts.app')
@section('title', 'Transcript access - COMPASS')
@section('content')
<div class="adviser-page-content av-page at-page">
    <header><div><h1>Transcript access</h1><p class="av-muted">Review completed conversations from Helpers you currently supervise.</p></div><a class="av-button" href="{{ route('adviser.reports',['tab'=>'cases']) }}">View session records</a></header>
    @if(session('success'))<p class="av-note" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="at-error" role="alert"><strong>Check your access request</strong><p>Correct the highlighted fields in the expanded session below.</p></div>@endif
    <div class="at-access-note"><strong>Purpose-based access</strong><p>Choose a review purpose and explain why you need the conversation. Access is logged and authorization lasts ten minutes. Seeker consent is captured at the start of the support flow.</p></div>
    <section class="av-panel at-queue">
        <header class="av-heading"><div><h2>Awaiting transcript verification</h2><p class="av-muted">{{ count($unverifiedTranscripts) }} {{ count($unverifiedTranscripts)===1 ? 'session' : 'sessions' }} / Dates are Philippine Time.</p></div><span class="av-muted">Select a session to request access</span></header>
        <div class="at-records">
        @forelse($unverifiedTranscripts as $item)
            @php
                $id = $item['session_id'];
                $failed = $errors->any() && (string) old('access_session_id') === (string) $id;
                $date = $item['session_date']?->copy()->timezone('Asia/Manila');
            @endphp
            <details class="at-record" name="transcript-access" @if($failed) open @endif>
                <summary>
                    <span class="at-record-main"><strong>{{ $item['reference_number'] }}</strong><span>{{ $item['helper_name'] }}</span></span>
                    <span class="at-record-date">@if($date)<time datetime="{{ $date->toIso8601String() }}">{{ $date->format('M d, Y') }}<span>{{ $date->format('g:i A') }} PHT</span></time>@else Date not recorded @endif</span>
                    <span class="av-badge {{ $item['eligible'] ? '' : 'av-badge-muted' }}">{{ ucwords(str_replace('_',' ',$item['session_status'])) }}</span>
                    <span class="at-record-control"><span class="at-open-label">{{ $item['eligible'] ? 'Request access' : 'View availability' }}</span><span class="at-close-label">Close form</span></span>
                </summary>
                <div class="at-record-body">
                @if($item['eligible'])
                    <form method="POST" action="{{ route('adviser.transcript.access', $id) }}" class="at-access-form">
                        @csrf
                        <div class="at-purpose-field">
                            <label for="purpose-{{ $id }}">Review purpose <span aria-hidden="true">*</span></label>
                            <select id="purpose-{{ $id }}" name="purpose" required class="av-field" @if($failed && $errors->has('purpose')) aria-invalid="true" @endif aria-describedby="purpose-hint-{{ $id }}">
                                <option value="">Select a review purpose</option>
                                @foreach(\App\Services\AdviserTranscriptAccess::PURPOSES as $purpose)<option value="{{ $purpose }}" @selected($failed && old('purpose')===$purpose)>{{ ucwords(str_replace('_',' ',$purpose)) }}</option>@endforeach
                            </select>
                            <p id="purpose-hint-{{ $id }}" class="av-muted">Choose the purpose that matches your review.</p>
                            @if($failed)@error('purpose')<p class="at-field-error">{{ $message }}</p>@enderror @endif
                        </div>
                        <div class="at-reason-field">
                            <label for="reason-{{ $id }}">Reason for access <span aria-hidden="true">*</span></label>
                            <textarea id="reason-{{ $id }}" name="reason" required minlength="10" maxlength="500" rows="4" class="av-field" placeholder="Explain what you need to clarify from the conversation." aria-describedby="reason-hint-{{ $id }}" @if($failed && $errors->has('reason')) aria-invalid="true" @endif>{{ $failed ? old('reason') : '' }}</textarea>
                            <p id="reason-hint-{{ $id }}" class="av-muted">10-500 characters. Keep the reason relevant to the review and avoid identity details.</p>
                            @if($failed)@error('reason')<p class="at-field-error">{{ $message }}</p>@enderror @endif
                        </div>
                        <div class="at-form-footer"><p class="av-muted">Opening the transcript records this access request.</p><button type="submit" class="av-button av-button-primary">Open transcript</button></div>
                    </form>
                @else
                    <p class="av-muted">Access is unavailable. Transcript review requires a completed chat session and current supervision of its Helper.</p>
                @endif
                </div>
            </details>
        @empty
            <div class="av-empty at-empty"><h2>No transcripts awaiting verification</h2><p>Completed sessions requiring transcript review will appear here.</p><a class="av-button" href="{{ route('adviser.reports',['tab'=>'cases']) }}">View session records</a></div>
        @endforelse
        </div>
    </section>
</div>
@endsection
@push('scripts')
<script>
(() => {
 const records=Array.from(document.querySelectorAll('.at-record'));
 records.forEach(record=>record.addEventListener('toggle',()=>{
  if(record.open) records.forEach(other=>{if(other!==record) other.open=false;});
 }));
})();
</script>
@endpush
