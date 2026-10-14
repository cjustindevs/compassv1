const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function room(){
    class Node{
        constructor(){this.handlers={};this.children=[];this.hidden=false;this.textContent='';this.value='preserved draft';}
        addEventListener(name,fn){this.handlers[name]=fn;}setAttribute(){}focus(){this.focused=true;}
        append(child){this.children.push(child);}prepend(child){this.children.unshift(child);}before(child){this.feedback=child;}
        replaceChildren(){this.children=[];}reportValidity(){return true;}showModal(){this.open=true;}close(){this.open=false;}
        querySelector(selector){return selector==='button[type="submit"]'?this.button:this.input;}
        querySelectorAll(selector){return selector==='form'?this.forms||[]:[];}
    }
    const ids={};for(const id of ['sessionId','referralModal','emergencyModal','referralBtn','emergencyBtn','referralConsentForm','referralClarificationForm','referralStateChip','referralClarificationQuestion','emergencyReferralState'])ids[id]=new Node();
    ids.sessionId.value='55';const emergencyForm=new Node();
    for(const form of [ids.referralConsentForm,ids.referralClarificationForm,emergencyForm]){form.button=new Node();form.button.textContent='Submit';form.input=new Node();form.action='/save';}
    ids.referralModal.forms=[ids.referralConsentForm,ids.referralClarificationForm];ids.emergencyModal.forms=[emergencyForm];
    const card=new Node(),intervals=[],requests=[],events=[];let state={referral:null,emergency:null},postResult={ok:true,json:async()=>({success:true})},deferred=null;
    const context={document:{hidden:false,getElementById:id=>ids[id],createElement:()=>new Node(),querySelector:s=>s==='.room-card'?card:null,addEventListener(){}},
        navigator:{onLine:true},window:{dispatchEvent:event=>events.push(event.type),addEventListener(){}},location:{search:''},URLSearchParams,Event,FormData:class{},setInterval:fn=>intervals.push(fn),
        fetch:async(url,opts)=>{requests.push({url,opts});if(opts.method==='POST'){if(deferred)return deferred;return postResult;}return{ok:true,json:async()=>state};}};
    vm.runInNewContext(fs.readFileSync('public/js/chat-workflow.js','utf8'),context);
    return {ids,emergencyForm,requests,events,card,poll:()=>intervals[0](),setState:s=>{state=s;},setPost:r=>{postResult=r;},defer:p=>{deferred=p;},flush:()=>new Promise(r=>setImmediate(r)),submit:form=>form.handlers.submit({preventDefault(){}})};
}

test('chat validation errors stay in the open form and preserve entered answers',async()=>{
    const r=room();await r.flush();r.ids.referralBtn.handlers.click();
    r.setPost({ok:false,status:422,json:async()=>({errors:{summary:['A summary is required.']}})});
    await r.submit(r.ids.referralConsentForm);
    assert.equal(r.ids.referralModal.open,true);assert.equal(r.ids.referralConsentForm.input.value,'preserved draft');
    assert.equal(r.ids.referralConsentForm.children[0].children[0].textContent,'A summary is required.');
    assert.equal(r.ids.referralConsentForm.button.disabled,false);
});

test('duplicate emergency submits are blocked and successful save closes only the action dialog',async()=>{
    const r=room();await r.flush();r.ids.emergencyBtn.handlers.click();let resolve;r.defer(new Promise(done=>{resolve=done;}));
    const first=r.submit(r.emergencyForm);await r.submit(r.emergencyForm);
    assert.equal(r.requests.filter(q=>q.opts.method==='POST').length,1);
    resolve({ok:true,json:async()=>({success:true,message:'Emergency saved.'})});await first;
    assert.equal(r.ids.emergencyModal.open,false);assert.equal(r.card.feedback.textContent,'Emergency saved.');
    assert.deepEqual(r.events,['compass-chat-workflow-changed']);assert.equal(r.emergencyForm.input.value,'preserved draft');
});

test('revision state exposes the Adviser response form and polling requires no socket',async()=>{
    const r=room();await r.flush();r.setState({referral:{id:8,status:'pending_adviser',clarification_requested:true,clarification_question:'Explain the support provided.',clarification_url:'/helper/referral/8/clarification'},emergency:{active:true,acknowledged:true}});
    await r.poll();assert.equal(r.ids.referralConsentForm.hidden,true);assert.equal(r.ids.referralClarificationForm.hidden,false);
    assert.equal(r.ids.referralClarificationForm.action,'/helper/referral/8/clarification');assert.match(r.ids.emergencyReferralState.textContent,/acknowledged/);
    assert.equal(r.requests[0].opts.cache,'no-store');
});

test('an HTML or unconfirmed successful response cannot falsely close the form',async()=>{
    const r=room();await r.flush();r.ids.referralBtn.handlers.click();r.setPost({ok:true,json:async()=>{throw new Error('HTML response');}});
    await r.submit(r.ids.referralConsentForm);assert.equal(r.ids.referralModal.open,true);assert.match(r.ids.referralConsentForm.children[0].textContent,/could not be confirmed/);
});
