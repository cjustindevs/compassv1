(() => {
    const sessionId=document.getElementById('sessionId')?.value;
    if(!sessionId)return;
    const referralDialog=document.getElementById('referralModal'),emergencyDialog=document.getElementById('emergencyModal');
    if(!referralDialog||!emergencyDialog)return;
    const initialForm=document.getElementById('referralConsentForm'),clarifyForm=document.getElementById('referralClarificationForm');
    const stateUrl='/session/'+sessionId+'/referral-prompt';
    let polling=false;
    const status=document.createElement('p');status.className='chat-workflow-feedback';status.setAttribute('role','status');
    document.querySelector('.room-card')?.before(status);
    function open(dialog,trigger){if(dialog.open)return;dialog._trigger=trigger;if(window.CompassDialogs){if(!window.CompassDialogs.open(dialog,trigger))return;}else dialog.showModal();dialog.querySelector('textarea,input:not([type="hidden"]),button')?.focus();}
    const openers={referralBtn:referralDialog,emergencyBtn:emergencyDialog};
    Object.entries(openers).forEach(([id,dialog])=>document.getElementById(id)?.addEventListener('click',()=>{open(dialog,document.getElementById(id));refresh();}));
    for(const dialog of [referralDialog,emergencyDialog]){
        dialog.querySelectorAll('.modal-close').forEach(button=>button.addEventListener('click',()=>window.CompassDialogs?window.CompassDialogs.close(dialog):dialog.close()));
        dialog.addEventListener('close',()=>dialog._trigger?.focus());
        dialog.addEventListener('click',event=>{if(event.target!==dialog)return;const box=dialog.getBoundingClientRect();if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom){if(window.CompassDialogs)window.CompassDialogs.close(dialog);else dialog.close();}});
        dialog.querySelectorAll('form').forEach(form=>{
            let submitting=false;
            const error=document.createElement('div');error.className='chat-action-errors';error.setAttribute('role','alert');error.setAttribute('tabindex','-1');const body=form.querySelector('.workflow-form-body');(body?.classList?.contains('workflow-form-body')?body:form).prepend(error);
            form.addEventListener('submit',async event=>{
                event.preventDefault();if(submitting||!form.reportValidity())return;submitting=true;error.replaceChildren();
                const button=form.querySelector('button[type="submit"]'),label=button.textContent;button.disabled=true;button.textContent='Saving...';
                try{
                    const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(form)});
                    let data={};try{data=await response.json();}catch{}
                    if(response.ok && data.success!==true){error.textContent='The saved status could not be confirmed. Your entries are retained; check the session status before retrying.';return;}
                    if(!response.ok){
                        const messages=response.status===422&&data.errors?Object.values(data.errors).flat():[response.status>=500?'Unable to save right now. Your entries are retained. Please try again.':data.message||'Unable to save. Please try again.'];
                        for(const message of messages){const p=document.createElement('p');p.textContent=message;error.append(p);}error.focus();return;
                    }
                    status.textContent=data.message||(form===initialForm?'Recommendation saved. Awaiting Adviser review.':form===clarifyForm?'Revision saved for Adviser review.':'Emergency report saved for staff review.');
                    if(form.dataset)delete form.dataset.dirty;dialog.close();await refresh();window.dispatchEvent(new Event('compass-chat-workflow-changed'));
                }catch{error.textContent='The connection was interrupted. Your entries are retained; retry to confirm the saved status.';}
                finally{submitting=false;button.disabled=false;button.textContent=label;}
            });
        });
    }
    function state(data){
        const referral=data.referral,chip=document.getElementById('referralStateChip');
        initialForm.hidden=!!referral&&!['closed','declined','completed','cancelled'].includes(referral.status);
        clarifyForm.hidden=!referral?.clarification_requested;
        if(referral?.clarification_requested){clarifyForm.action=referral.clarification_url;document.getElementById('referralClarificationQuestion').textContent=referral.clarification_question||'Provide the clarification requested by your Adviser.';}
        chip.hidden=!referral;
        if(referral)chip.textContent=referral.clarification_requested?'Returned for revision. Please respond below.':'Referral #'+referral.id+': '+referral.status.replaceAll('_',' ')+'.';
        const emergency=data.emergency,panel=document.getElementById('emergencyReferralState');panel.hidden=!emergency;
        if(emergency)panel.textContent=emergency.active?(emergency.acknowledged?'Staff acknowledged the emergency. Coordination is ongoing.':'Emergency reported. Awaiting staff acknowledgment. A response is not yet confirmed.'):'The previous emergency review is '+emergency.status+'. A new report will be recorded as a new incident.';
    }
    async function refresh(){if(polling||document.hidden||!navigator.onLine)return;polling=true;try{const response=await fetch(stateUrl,{headers:{Accept:'application/json'},cache:'no-store'});if(response.ok)state(await response.json());}catch{}finally{polling=false;}}
    refresh();setInterval(refresh,5000);document.addEventListener('visibilitychange',refresh);window.addEventListener('online',refresh);
    const initial=document.querySelector('[data-chat-open-modal]')?.dataset.chatOpenModal;
    if(initial==='referral'||new URLSearchParams(location.search).get('referral')==='1')open(referralDialog,document.getElementById('referralBtn'));
    else if(initial==='emergency')open(emergencyDialog,document.getElementById('emergencyBtn'));
})();
