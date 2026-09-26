@if($referral->recommendation_form)
<section class="border rounded-xl p-4 my-4" aria-label="Referral recommendation">
    <h3 class="font-semibold mb-3">Referral recommendation — Appendix O</h3>
    <p class="text-sm mb-3">Session #{{ $referral->session_id }} · Submitted {{ $referral->referral_date?->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
    <dl class="space-y-3 text-sm">
    @foreach(['indicators'=>'Reason indicators','session_summary'=>'Session summary','observations'=>'Relevant observations','actions_taken'=>'Actions already taken','receiving_office'=>'Recommended receiving office or professional','referral_explained'=>'Referral explained','recommended_urgency'=>'Recommended urgency','helper_remarks'=>'Helper remarks'] as $field=>$label)
        @if(!empty($referral->recommendation_form[$field]))
            <div><dt class="font-semibold">{{ $label }}</dt><dd class="whitespace-pre-line">{{ is_array($referral->recommendation_form[$field]) ? implode('; ', $referral->recommendation_form[$field]) : $referral->recommendation_form[$field] }}</dd></div>
        @endif
    @endforeach
    </dl>
</section>
@endif
