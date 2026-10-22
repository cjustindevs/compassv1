(() => {
    const configNode = document.getElementById('chatReferralConfig');
    if (!configNode) return;
    const config = JSON.parse(configNode.content);
    const cardSlot = document.querySelector('[data-professional-support-slot]') || document.querySelector('.chat-header');
    if (cardSlot) {
        cardSlot.insertAdjacentElement('afterend', document.getElementById('professionalSupportCard'));
        cardSlot.insertAdjacentElement('afterend', document.getElementById('chatEmergencyBanner'));
        document.getElementById('professionalSupportCard').append(document.getElementById('chatReferralOpen'), document.getElementById('chatIdentityOpen'));
    }
    const dialog = document.getElementById('referralConsentDialog');
    if (!dialog) return;
    const isSeeker = config.isSeeker;
    const check = document.getElementById('referralConsentCheck');
    const acceptBtn = document.getElementById('referralAccept');
    const declineBtn = document.getElementById('referralDecline');
    const dismissBtn = document.getElementById('referralDismiss');
    const terms = document.getElementById('referralConsentTerms');
    const errorEl = document.getElementById('referralConsentError');
    const stateUrl = config.stateUrl;
    const identityDialog = document.getElementById('chatIdentityDialog');
    const identityButton = document.getElementById('chatIdentityOpen');
    let referral = null, seen = null, deciding = false, manualOpen = false, polling = false;
    let identityReferralId = null, identityDirty = false;
    const promptedIdentity = new Set();
    const presentation = window.CompassPopupPresentation;
    const promptKey = (stage,id) => `workflow:${stage}:${id}`;
    function showConsent() {
        if (window.CompassDialogs) return window.CompassDialogs.open(dialog, document.getElementById('chatReferralOpen'));
        dialog.showModal(); return true;
    }
    function openIdentity() {
        if (!isSeeker || !referral?.can_provide_identity || !referral.identity_url) return;
        if (identityDialog.open || (document.querySelector('dialog[open]') && !dialog.open)) return;
        dialog.close();
        identityDialog.removeAttribute('data-support');
        const outerClose = document.getElementById('chatEmbeddedClose'); if (outerClose) outerClose.hidden = true;
        identityReferralId = referral.id; identityDirty = false;
        identityDialog.querySelector('iframe').src = referral.identity_url;
        if(window.CompassDialogs)window.CompassDialogs.open(identityDialog,identityButton);else identityDialog.showModal();
        presentation?.claim(promptKey('identity',referral.id));
        promptedIdentity.add(referral.id);
    }
    window.addEventListener('message', event => {
        if (event.origin !== location.origin || event.source !== identityDialog.querySelector('iframe').contentWindow) return;
        if(event.data?.type === 'identity-form-dirty')identityDirty=true;
        if (event.data?.type === 'identity-modal-closed') {identityDirty=false;identityDialog.close();}
        if (event.data?.type === 'identity-details-saved') { identityDirty=false;seen = null; poll(); }
    });
    identityDialog.addEventListener('cancel',event=>{if(identityDirty && !window.confirm('Discard your unsaved contact details?'))event.preventDefault();});
    identityDialog.addEventListener('close', () => { identityReferralId = null; seen = null; poll(); });

    function enabledButtons() {
        acceptBtn.disabled = deciding || (isSeeker && !check?.checked);
        declineBtn.disabled = deciding;
    }

    function handleState(data) {
        const emergency=data.emergency,banner=document.getElementById('chatEmergencyBanner');
        banner.hidden=!emergency?.active;
        if(emergency){document.getElementById('chatEmergencyTitle').textContent=emergency.chat_reported?'Emergency reported':'Urgent support review';
            document.getElementById('chatEmergencyStatus').textContent=emergency.active?(emergency.acknowledged?'Staff acknowledged the report. Coordination is ongoing.':'Awaiting staff acknowledgment. A response is not yet confirmed.'):'Emergency review '+emergency.status+'.';
            if(emergency.active&&emergency.professional_referred)document.getElementById('chatEmergencyStatus').textContent+=' A linked referral has been sent to an assigned Professional.';}
        referral = data?.referral || null;
        if (identityReferralId !== null && (!referral || !referral.can_provide_identity || referral.id !== identityReferralId)) {
            identityDialog.close();
            identityDialog.querySelector('iframe').src = 'about:blank';
        }
        identityButton.hidden = !isSeeker || !referral?.needs_identity_disclosure;
        if (!referral) {
            document.getElementById('professionalSupportCard').hidden = true;
            dialog.close();
            seen = null;
            return;
        }
        document.getElementById('chatReferralOpen').hidden = !isSeeker;
        const card = document.getElementById('professionalSupportCard');
        card.hidden = !isSeeker;
        document.getElementById('chatSupportOpen').hidden = !referral.help_seeker_consent;
        document.getElementById('professionalSupportStatus').textContent = (referral.professional_name ? referral.professional_name + ' ? ' : '') + (referral.appointment_time ? 'Appointment: ' + referral.appointment_time + ' Philippine Time.' : ['accepted','in_progress'].includes(referral.status) ? 'Referral accepted. View your appointment or scheduling updates.' : referral.professional_name ? 'Awaiting professional acceptance.' : 'Your Adviser is coordinating your referral.');
        if (isSeeker && referral.needs_identity_disclosure) {
            document.getElementById('professionalSupportStatus').textContent = 'Referral accepted. Review identity-disclosure consent and provide your contact details here without leaving chat.';
        }
        if(document.querySelector('dialog[open]') && !dialog.open)return;
        if (isSeeker && referral.needs_identity_disclosure && (manualOpen || (!promptedIdentity.has(referral.id) && !presentation?.has(promptKey('identity',referral.id))))) {
            manualOpen = false;
            openIdentity();
            return;
        }
        const key = referral.id + ':' + referral.status + ':' + (data.consent?.decision ?? 'none') + ':' + referral.needs_identity_disclosure;
        if (key === seen) return;
        if (!manualOpen && !dialog.open && referral.status === 'pending_consent' && presentation?.has(promptKey('referral-consent',referral.id))) { seen = key; return; }
        seen = key;
        errorEl.textContent = '';
        if (!isSeeker) {
            const awaitingReview = ['pending_adviser', 'consent_requested'];
            document.getElementById('referralConsentText').textContent =
                referral.status === 'pending_adviser_assignment' ? 'Your recommendation is with a Moderator, who will assign an Adviser so it can be reviewed.'
                : awaitingReview.includes(referral.status) ? 'Your recommendation is awaiting Adviser review.'
                : referral.status === 'pending_consent' ? 'The Adviser approved your recommendation. The seeker is deciding whether to proceed.'
                : 'Referral status: ' + referral.status.replaceAll('_', ' ') + '.';
            terms.hidden = true;
            acceptBtn.hidden = true;
            declineBtn.hidden = true;
            dismissBtn.hidden = false;
            if (manualOpen && !dialog.open) { showConsent(); manualOpen=false; }
            return;
        }

        const alreadyDecided = data.consent && ['accepted', 'declined', 'withdrawn'].includes(data.consent.decision);
        const isConsentRequest = referral.status === 'pending_consent';
        if (isConsentRequest) {
            document.getElementById('referralConsentText').textContent = 'Your Adviser approved a referral. Review the complete recommendation and consent form before deciding.';
            terms.hidden = false;
            check.checked = false;
            acceptBtn.hidden = false;
            acceptBtn.textContent = 'Accept referral';
            enabledButtons();
            declineBtn.hidden = false;
            let recommendation = document.getElementById('chatReferralRecommendation');
            if (!recommendation) { recommendation = document.createElement('div'); recommendation.id = 'chatReferralRecommendation'; terms.prepend(recommendation); }
            recommendation.innerHTML = referral.recommendation_html || '';
            dismissBtn.hidden = false;
            if (!dialog.open) { showConsent(); if(referral.status==='pending_consent')presentation?.claim(promptKey('referral-consent',referral.id)); }
            manualOpen=false;
            return;
        }

        document.getElementById('referralConsentText').textContent =
            referral.status === 'pending_consent'
                ? 'An adviser approved your referral. You can review consent and securely provide contact details for the assigned professional.'
                : isConsentRequest
                    ? 'Your helper recommended connecting you with a professional. Please review the referral consent below.'
                    : 'Referral status: ' + referral.status.replaceAll('_', ' ') + '.';

        if (referral.help_seeker_consent && ['pending_professional','no_professional_available','accepted','in_progress'].includes(referral.status)) return;
        if (alreadyDecided || !isConsentRequest) {
            terms.hidden = true;
            acceptBtn.hidden = true;
            declineBtn.hidden = true;
            dismissBtn.hidden = false;
            if (!dialog.open) { showConsent(); if(referral.status==='pending_consent')presentation?.claim(promptKey('referral-consent',referral.id)); }
            manualOpen=false;
            return;
        }

        terms.hidden = false;
        check.checked = false;
        acceptBtn.hidden = false;
        declineBtn.hidden = false;
        dismissBtn.hidden = true;
        enabledButtons();
        if (!dialog.open) { showConsent(); if(referral.status==='pending_consent')presentation?.claim(promptKey('referral-consent',referral.id)); }
            manualOpen=false;
    }

    async function poll() {
        if (polling || document.hidden || !navigator.onLine) return;
        polling = true;
        try {
            const response = await fetch(stateUrl, {headers: {'Accept': 'application/json'},cache:'no-store'});
            if (!response.ok) return;
            handleState(await response.json());
        } catch (_) { /* Retry when the connection returns. */ }
        finally { polling = false; }
    }

    async function decide(accepted) {
        if (deciding) return;
        if(!window.confirm(accepted?'Confirm that you agree to this referral and its consent terms?':'Confirm that you decline this referral?'))return;
        deciding = true;
        enabledButtons();
        try {
            const body = {accepted: accepted};
            if (accepted && isSeeker && !acceptBtn.hidden) {
                if (!check?.checked) {
                    errorEl.textContent = 'Please tick the consent checkbox to accept.';
                    deciding = false; enabledButtons();
                    check.focus();
                    return;
                }
            }
            const response = await fetch(referral.consent_url, {
                method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf},
                body: JSON.stringify(body)
            });
            if (!response.ok) throw new Error('Your decision could not be saved. Please try again.');
            const saved = await response.json();
            dialog.close();
            if (accepted) { seen = null; promptedIdentity.delete(referral.id); await poll(); }
            else { document.getElementById('referralConsentText').textContent = 'Your decision is respected. Self-help resources remain available whenever you need them.'; terms.hidden = true; acceptBtn.hidden = true; declineBtn.hidden = true; dismissBtn.hidden = false; showConsent(); }
        } catch (error) {
            errorEl.textContent = error.message;
        } finally {
            deciding = false;
            enabledButtons();
        }
    }

    check?.addEventListener('change', enabledButtons);
    acceptBtn.addEventListener('click', () => decide(true));
    declineBtn.addEventListener('click', () => decide(false));
    dismissBtn.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const box = dialog.getBoundingClientRect();
        const outside = event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom;
        if (outside) dialog.close();
    });

    identityButton.addEventListener('click', () => { manualOpen = true; seen = null; poll(); });
    document.getElementById('chatSupportOpen').addEventListener('click', () => { if (!referral) return; identityReferralId = null; identityDialog.setAttribute('data-support',''); const outerClose = document.getElementById('chatEmbeddedClose'); if (outerClose) outerClose.hidden = false; identityDialog.querySelector('iframe').src = referral.support_url; identityDialog.showModal(); });
    document.getElementById('chatReferralOpen').addEventListener('click', () => { manualOpen=true; seen = null; poll(); });
    window.addEventListener('compass-chat-workflow-changed',()=>{seen=null;poll();});
    poll();
    setInterval(poll, 2000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    window.addEventListener('online', poll);

    if (window.Echo) {
        const channel = window.Echo.private('session.' + config.sessionId);
        channel.listen('.ReferralConsentRequested', () => poll());
        channel.listen('.ReferralConsentUpdated', () => poll());
    }
})();
