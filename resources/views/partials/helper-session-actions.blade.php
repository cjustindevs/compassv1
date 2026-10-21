<dialog class="chat-action-dialog" id="referralModal" aria-labelledby="referralModalTitle" data-chat-open-modal="{{ $errors->any() ? old('_chat_action','') : '' }}">
        <div class="modal-box">
            <h3 id="referralModalTitle"> Recommend a Referral</h3>
            <p class="modal-sub">Your Adviser reviews this recommendation before Seeker consent.</p>

            <div id="referralStateChip" hidden style="margin-bottom:14px;padding:10px 14px;border-radius:12px;font-size:13px;font-weight:600;"></div>

            <form method="POST" action="{{ route('helper.session.referral.consent', ['id' => $session->id]) }}" id="referralConsentForm"><div class="workflow-form-body">
                @csrf
                <input type="hidden" name="_chat_action" value="referral">
                <input type="hidden" name="form_version" value="referral-v4">
                <div class="referral-fields">
                <p class="text-sm mb-3">Session #{{ $session->id }} · {{ $session->seeker?->generated_alias }} · {{ ucfirst($session->risk_level ?? 'Pending review') }} risk. Identity is requested separately after Adviser approval.</p>
                <fieldset class="mb-3"><legend class="form-label">Reason indicators</legend>
                    @foreach(\App\Services\ReferralForm::INDICATORS as $indicator)
                        <label class="block text-sm mb-1"><input type="checkbox" name="indicators[]" value="{{ $indicator }}" @checked(in_array($indicator, old('indicators', [])))> {{ $indicator }}</label>
                    @endforeach
                </fieldset>
                <div class="referral-field"><label class="form-label" for="referralSummary">Referral recommendation for adviser review</label>
                <textarea id="referralSummary" name="summary" rows="3" required maxlength="500" placeholder="Short summary of why a professional referral may help, written for the seeker.">{{ old('summary') }}</textarea>
                @error('summary')<p role="alert" class="text-red-700">{{ $message }}</p>@enderror
                </div>
                @foreach(['session_summary' => 'Session summary', 'observations' => 'Relevant observations', 'actions_taken' => 'Actions already taken'] as $field => $label)
                    <div class="referral-field">
                    <label class="form-label" for="referral-{{ $field }}">{{ $label }}</label>
                    <textarea id="referral-{{ $field }}" name="{{ $field }}" required maxlength="2000" rows="3">{{ old($field) }}</textarea>
                    @error($field)<p role="alert" class="text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div class="referral-field">
                <label class="form-label" for="referral-office">Recommended receiving office or professional</label>
                <input id="referral-office" name="receiving_office" required maxlength="200" value="{{ old('receiving_office') }}">
                </div><div class="referral-field">
                <label class="form-label" for="referral-explained">Was the referral explained to the seeker?</label>
                <select id="referral-explained" name="referral_explained" required><option value="">Select</option>@foreach(['yes'=>'Yes','no'=>'No','emergency'=>'Not applicable due to emergency'] as $value=>$label)<option value="{{ $value }}" @selected(old('referral_explained') === $value)>{{ $label }}</option>@endforeach</select>
                </div><div class="referral-field"><label class="form-label" for="referral-urgency">Recommended urgency</label>
                <select id="referral-urgency" name="recommended_urgency" required>@foreach(['routine','priority','urgent','emergency'] as $urgency)<option value="{{ $urgency }}" @selected(old('recommended_urgency') === $urgency)>{{ ucfirst($urgency) }}</option>@endforeach</select>
                </div><div class="referral-field"><label class="form-label" for="referral-remarks">Helper remarks (optional)</label>
                <textarea id="referral-remarks" name="helper_remarks" maxlength="1000" rows="2">{{ old('helper_remarks') }}</textarea>
                </div><p class="text-sm">Seeker decision: pending. Only the seeker can record consent after approval.</p>
                </div>
                </div><div class="modal-actions">
                    <button type="button" class="btn btn-cancel modal-close" data-modal="referralModal">Cancel</button>
                    <button type="submit" class="btn btn-info" id="requestConsentBtn"><x-ui-icon name="send" style="margin-right:6px;" /> Submit for Adviser Review</button>
                </div>
            </form>
            <form method="POST" id="referralClarificationForm" hidden><div class="workflow-form-body">@csrf
                <p id="referralClarificationQuestion"></p>
                <label class="form-label" for="referralClarification">Response to Adviser</label><textarea id="referralClarification" name="response" required minlength="10" maxlength="2000" rows="4">{{ old('response') }}</textarea>
                </div><div class="modal-actions"><button type="button" class="modal-close" data-modal="referralModal">Cancel</button><button type="submit" class="btn-info">Submit revision</button></div>
            </form>


        </div>
    </dialog>

    <!-- ═══════════════ EMERGENCY MODAL ═══════════════ -->
    <dialog class="chat-action-dialog" id="emergencyModal" aria-labelledby="emergencyModalTitle">
        <div class="modal-box">
            <h3 id="emergencyModalTitle"> Flag as Emergency</h3>
            <p class="modal-sub">Report immediate risk to staff.</p>
            <div id="emergencyReferralState" hidden style="margin-bottom:14px;padding:10px 14px;border-radius:12px;font-size:13px;font-weight:600;background:#fffbeb;color:#92400e;"></div>
            <form method="POST" action="{{ route('helper.session.emergency', ['id' => $session->id]) }}"><div class="workflow-form-body">
                @csrf
                <input type="hidden" name="_chat_action" value="emergency">
                <div class="emergency-fields">
                <label class="form-label" for="emergencyDesc">What is happening?</label>
                <textarea id="emergencyDesc" name="description" rows="4" required maxlength="1000" placeholder="Describe the situation and any immediate risk...">{{ old('description') }}</textarea>
                <label class="form-label" style="margin-top:14px;" for="emergencyAction">Immediate action taken (optional)</label>
                <textarea id="emergencyAction" name="immediate_action" rows="2" maxlength="500" placeholder="Record the immediate support or escalation steps you took...">{{ old('immediate_action') }}</textarea>
                </div>
                </div><div class="modal-actions">
                    <button type="button" class="btn btn-cancel modal-close" data-modal="emergencyModal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><x-ui-icon name="warning" style="margin-right:6px;" /> Flag Emergency</button>
                </div>
            </form>
        </div>
    </dialog>

    <script src="{{ asset('js/chat-workflow.js') }}?v={{ filemtime(public_path('js/chat-workflow.js')) }}" defer></script>
