const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');

function room(storage=new Map()) {
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
        fetch:async(url,options)=>{requests.push({url,options});return {ok:success};}};
    vm.runInNewContext(fs.readFileSync('public/js/important-notice.js','utf8'),context);
    return {api:context.window.CompassImportantNotices,dialog:body.children[0],requests,storage,tick:()=>ticks[0](),another:value=>another=value,success:value=>success=value,flush:()=>new Promise(resolve=>setImmediate(resolve))};
}

test('important actions show one centered dialog; acknowledgement only reads the notification',async()=>{
    const r=room();r.api.enqueue({id:1,title:'Referral review',message:'Review this case',link:'/adviser/referral/1',type:'referral'});
    r.api.enqueue({id:2,title:'Assignment',message:'Accept the assignment',link:'/helper/cases',type:'assignment'});
    assert.equal(r.dialog.open,true);assert.equal(r.dialog.children[0].textContent,'Referral review');
    await r.dialog.children[3].children[1].onclick();await r.flush();
    assert.equal(r.requests[0].url,'/notifications/1/read');assert.equal(r.requests[0].options.method,'POST');
    assert.equal(r.requests.some(item=>/resolve|complete/.test(item.url||'')),false);
    r.tick();assert.equal(r.dialog.children[0].textContent,'Assignment');
});

test('sensitive dialogs defer presentation and emergency preemption preserves the pending notice',()=>{
    const r=room();r.another(true);r.api.enqueue({id:1,title:'Review',type:'referral'});assert.equal(r.dialog.open,false);
    r.another(false);r.tick();assert.equal(r.dialog.open,true);r.api.suspend();assert.equal(r.dialog.open,false);
    r.another(true);r.tick();assert.equal(r.dialog.open,false);r.another(false);r.tick();assert.equal(r.dialog.open,true);
    const resumed=room(r.storage);assert.equal(resumed.dialog.children[0].textContent,'Review');
});

test('resolved emergency notices close and failed acknowledgement preserves pending work',async()=>{
    const r=room();r.api.enqueue({id:3,title:'Emergency',type:'emergency'});r.api.reconcile([]);assert.equal(r.dialog.open,false);
    r.api.enqueue({id:4,title:'Referral',type:'referral'});r.success(false);await r.dialog.children[3].children[1].onclick();await r.flush();
    assert.equal(r.dialog.open,true);assert.match(r.dialog.children[2].textContent,/try again/);
    r.api.reconcileUnread([]);assert.equal(r.dialog.open,false);
});
