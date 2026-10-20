const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');

function room(storage=new Map(),present=true) {
    class Element {
        constructor(tag){this.tag=tag;this.children=[];this.handlers={};this.open=false;}
        append(...nodes){this.children.push(...nodes);}
        setAttribute(){}
        addEventListener(name,fn){this.handlers[name]=fn;}
        showModal(){this.open=true;}
        close(){this.open=false;}
    }
    const body=new Element('body'), requests=[],ticks=[];let another=false, success=true;
    const context={window:{},document:{body,hidden:false,createElement:tag=>new Element(tag),querySelector:selector=>
        selector==='meta[name="compass-notices"]'?{content:JSON.stringify({user:7,center:'/notifications'})}:
        selector==='meta[name="csrf-token"]'?{content:'csrf'}:selector==='dialog[open]'?(another||body.children.some(node=>node.open)?{}:null):null},
        sessionStorage:{getItem:key=>storage.get(key)||null,setItem:(key,value)=>storage.set(key,value)},
        location:{assign:url=>requests.push({navigation:url})},setInterval:fn=>ticks.push(fn),
        fetch:async(url,options)=>{requests.push({url,options});return {ok:success,json:async()=>({present})};}};
    context.localStorage = context.sessionStorage;
    vm.runInNewContext(fs.readFileSync('public/js/notification-presentation.js','utf8'),context);
    vm.runInNewContext(fs.readFileSync('public/js/important-notice.js','utf8'),context);
    return {api:context.window.CompassImportantNotices,dialog:body.children[0],requests,storage,tick:()=>ticks[0](),another:value=>another=value,success:value=>success=value,flush:()=>new Promise(resolve=>setImmediate(resolve))};
}

test('important actions show one centered dialog; Later keeps the unread record without completing work',async()=>{
    const r=room();r.api.enqueue({id:1,title:'Referral review',message:'Review this case',link:'/adviser/referral/1',type:'referral'});
    r.api.enqueue({id:2,title:'Assignment',message:'Accept the assignment',link:'/helper/cases',type:'assignment'});await r.flush();
    assert.equal(r.dialog.open,true);assert.equal(r.dialog.children[0].textContent,'Referral review');
    await r.dialog.children[3].children[1].onclick();await r.flush();
    assert.equal(r.requests[0].url,'/notifications/1/present');assert.equal(r.requests.some(item=>/read$/.test(item.url||'')),false);
    assert.equal(r.requests.some(item=>/resolve|complete/.test(item.url||'')),false);
    r.tick();await r.flush();assert.equal(r.dialog.children[0].textContent,'Assignment');
});

test('an old browser queue must pass the server freshness check before it can replay a popup',async()=>{
    const storage=new Map([['compass-important:7',JSON.stringify([{id:40,title:'Old referral',type:'referral'}])]]);
    const r=room(storage,false);await r.flush();
    assert.equal(r.requests[0].url,'/notifications/40/present');
    assert.equal(r.dialog.open,false);assert.equal(JSON.parse(storage.get('compass-important:7')).length,0);
});

test('sensitive dialogs defer presentation; an already displayed notice does not reopen after preemption or navigation',async()=>{
    const r=room();r.another(true);r.api.enqueue({id:1,title:'Review',type:'referral'});assert.equal(r.dialog.open,false);
    r.another(false);r.tick();await r.flush();assert.equal(r.dialog.open,true);r.api.suspend();assert.equal(r.dialog.open,false);
    r.another(true);r.tick();assert.equal(r.dialog.open,false);r.another(false);r.tick();assert.equal(r.dialog.open,false);
    const resumed=room(r.storage);resumed.api.enqueue({id:1,title:'Review',type:'referral'});resumed.tick();assert.equal(resumed.dialog.open,false);
});

test('resolved emergency notices close and failed acknowledgement preserves pending work',async()=>{
    const r=room();r.api.enqueue({id:3,title:'Emergency',type:'emergency'});await r.flush();r.api.reconcile([]);assert.equal(r.dialog.open,false);
    r.api.enqueue({id:4,title:'Referral',type:'referral'});await r.flush();r.success(false);await r.dialog.children[3].children[0].onclick();await r.flush();
    assert.equal(r.dialog.open,true);assert.match(r.dialog.children[2].textContent,/try again/);
    r.api.reconcileUnread([]);assert.equal(r.dialog.open,false);
});

test('recovered unread referrals are deduplicated until acknowledged',async()=>{
    const r=room(), notice={id:5,title:'Referral review',type:'referral'};
    r.api.enqueue(notice);r.api.enqueue(notice);r.api.enqueue(notice);await r.flush();
    assert.equal(JSON.parse(r.storage.get('compass-important:7')).length,1);
    await r.dialog.children[3].children[1].onclick();r.tick();
    assert.equal(r.dialog.open,false);
    assert.equal(JSON.parse(r.storage.get('compass-important:7')).length,0);
});

test('a shown but unread referral is not repeated by stale polling or a fresh page',async()=>{
    const r=room(),notice={id:11,title:'Referral review',type:'referral'};
    r.api.enqueue(notice);await r.flush();
    const next=room(r.storage);next.api.enqueue(notice);next.tick();assert.equal(next.dialog.open,false);
    await r.dialog.children[3].children[1].onclick();r.api.enqueue(notice);r.tick();assert.equal(r.dialog.open,false);
    r.api.enqueue({id:12,title:'New referral',type:'referral'});await r.flush();assert.equal(r.dialog.open,true);
});
