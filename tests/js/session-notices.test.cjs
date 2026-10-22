const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function screen() {
    const notices=[], requests=[], handlers={}, timeouts=[];
    const config={dataset:{sessionId:'17',claimUrl:'/api/session/17/notices'}};
    function node() { return {children:[],setAttribute(){},append(...items){this.children.push(...items)},addEventListener(name,fn){this[name]=fn},remove(){this.removed=true}}; }
    const document={visibilityState:'visible',querySelector(selector){return selector==='[data-session-notices]' ? config : {content:'csrf'}},
        createElement:node,body:{append(item){notices.push(item)}}};
    const window={addEventListener(name,fn){handlers[name]=fn}};
    vm.runInNewContext(fs.readFileSync('public/js/session-notices.js','utf8'),{window,document,Set,
        fetch:async(url,options)=>{requests.push({url,options});return {ok:true,json:async()=>({show:true})}},
        setTimeout(fn){timeouts.push(fn)}});
    return {document,window,notices,requests,timeouts,state:detail=>handlers['compass-session-state']({detail})};
}

test('warning appears once during the last five minutes and dismisses without ending the session',async()=>{
    const s=screen();
    await s.state({session_id:17,remaining_seconds:301});
    await s.state({session_id:19,remaining_seconds:300});
    assert.equal(s.requests.length,0);
    await Promise.all([s.state({session_id:17,remaining_seconds:300}),s.state({session_id:17,remaining_seconds:299})]);
    assert.equal(s.requests.length,1);assert.equal(s.notices.length,1);
    assert.equal(s.notices[0].children[0].children[0].textContent,'5 minutes remaining');
    s.notices[0].children[1].click();assert.equal(s.notices[0].removed,true);
    await s.state({session_id:17,remaining_seconds:250});assert.equal(s.requests.length,1);
    assert.equal(s.requests[0].url,'/api/session/17/notices');
    assert.deepEqual(JSON.parse(s.requests[0].options.body),{event:'ending_soon'});
});

test('hidden or ended sessions never consume a warning and a visible live session can recover it',async()=>{
    const s=screen();s.document.visibilityState='hidden';
    await s.state({session_id:17,remaining_seconds:250});assert.equal(s.requests.length,0);
    s.document.visibilityState='visible';
    await s.state({session_id:17,remaining_seconds:0});await s.state({session_id:17,remaining_seconds:250,ended:true});
    assert.equal(s.requests.length,0);
    await s.state({session_id:17,remaining_seconds:250});assert.equal(s.notices.length,1);
    s.timeouts[0]();assert.equal(s.notices[0].removed,true);
});
