<section class="screening-follow-up screening-decision-control rounded-xl border border-gray-200">
    <h3 class="screening-decision-heading">One-question follow-up</h3>
    <div class="p-4 space-y-3">
        <p class="text-sm text-gray-600">Send an optional follow-up. Matching continues during review.</p>
        <p class="text-sm font-medium">{{ \App\Services\CompactScreening::SINGLE_QUESTION }}</p>
        @if(!$screening->follow_up_requested_at)
            <form method="POST" action="{{ route('adviser.screenings.follow-up',$session) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="_review_session" value="{{ $session->id }}">
                <label class="block text-sm font-medium" for="follow-up-note-{{ $session->id }}">Short note to the Seeker <span class="text-gray-500">(optional)</span></label>
                <textarea id="follow-up-note-{{ $session->id }}" name="follow_up_note" rows="2" maxlength="300" class="block w-full rounded-lg border-gray-300" placeholder="Briefly explain why you are following up. Avoid private staff notes.">{{ $retry ? old('follow_up_note') : '' }}</textarea>
                <p class="text-xs text-gray-500">The note is visible to the Seeker. They may still choose not to answer.</p>
                <button type="submit" class="inline-flex rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white">Send follow-up</button>
            </form>
        @else
            @if($screening->follow_up_note)<p class="screening-follow-up-note text-sm text-gray-600">Your note: {{ $screening->follow_up_note }}</p>@endif
            <p class="text-xs text-gray-500">Sent {{ $screening->follow_up_requested_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</p>
            @if(!$screening->follow_up_answered_at)
                <p class="text-sm font-medium text-amber-800" role="status">Waiting for the Seeker's response. This request stays in Screening reviews.</p>
            @elseif($screening->follow_up_answer === 'prefer_not_to_say')
                <p class="text-sm font-medium text-amber-800" role="status">The Seeker still prefers not to say. The classification remains undetermined and the request stays pending.</p>
            @else
                @php($followUpResult = app(\App\Services\CompactScreening::class)->classifySingle(['suicidal_thoughts'=>$screening->follow_up_answer]))
                <p class="text-sm">Seeker response: <strong>{{ $screening->follow_up_answer === 'yes' ? 'Yes' : 'No' }}</strong> &middot; {{ $screening->follow_up_answered_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</p>
                <p class="text-sm">Routing classification: <strong>{{ ucfirst($followUpResult['risk_level']) }}</strong> <span class="text-xs text-gray-500">&mdash; calculated from the saved response using the existing rules.</span></p>
                @if($followUpResult['risk_level'] === 'high')<p class="text-sm text-amber-800">This response requires Adviser review. Existing professional referral review applies; approval is not automatic.</p>@endif
                <form method="POST" action="{{ route('adviser.screenings.review',$session) }}" data-screening-review-form class="space-y-3">
                    @csrf
                    <input type="hidden" name="_review_session" value="{{ $session->id }}">
                    <input type="hidden" name="use_seeker_follow_up" value="1">
                    <label class="block text-sm font-medium" for="follow-up-reason-{{ $session->id }}">Decision reason</label>
                    <textarea id="follow-up-reason-{{ $session->id }}" name="reason" required minlength="10" maxlength="1000" rows="3" class="block w-full rounded-lg border-gray-300" placeholder="Explain your support decision based on the saved response.">{{ $retry ? old('reason') : '' }}</textarea>
                    <p class="text-xs text-gray-500">Approve only when appropriate under the existing protocol. Approval returns the Seeker to Preferences and matching; it does not start a session.</p>
                    <div class="screening-decision-actions flex justify-end">
                        <button type="submit" name="review_action" value="approve" class="inline-flex rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white">Approve peer support</button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</section>
