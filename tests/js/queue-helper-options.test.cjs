const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');

function queuePage(){
    const select={value:'7',options:[],replaceChildren(...nodes){this.options=nodes;},append(node){this.options.push(node);}};
    const note={},button={},requests=[],ticks=[];
    let data={requests:[{id:4,helpers:[{id:7,label:'Ready Helper'},{id:8,label:'Another Helper'}],reason:'Eligible Helpers'}]};
    const form={dataset:{queueId:'4'},querySelector:selector=>selector.startsWith('select')?select:selector.startsWith('button')?button:note};
    const document={hidden:false,activeElement:null,querySelector:()=>({dataset:{queueHelperOptions:'/moderator/queue/helper-options'}}),querySelectorAll:()=>[form],addEventListener(){}};
    const context={document,window:{addEventListener(){}},navigator:{onLine:true},URLSearchParams,Option:function(label,value){this.label=label;this.value=value;},
        setInterval:fn=>ticks.push(fn),fetch:async(url,options)=>{requests.push({url,options});return {ok:true,json:async()=>data};}};
    vm.runInNewContext(fs.readFileSync('public/js/queue-helper-options.js','utf8'),context);
    return {select,note,button,document,requests,poll:()=>ticks[0](),data:value=>data=value,flush:()=>new Promise(resolve=>setImmediate(resolve))};
}

test('queue choices refresh without losing a still-eligible selected Helper',async()=>{
    const room=queuePage();await room.flush();
    assert.equal(room.select.value,'7');assert.equal(room.select.options.length,3);
    assert.equal(room.requests[0].url,'/moderator/queue/helper-options?queue_ids%5B%5D=4');
    assert.equal(room.requests[0].options.cache,'no-store');
    room.data({requests:[{id:4,helpers:[{id:8,label:'Another Helper'}],reason:'Eligible Helpers'}]});
    await room.poll();assert.equal(room.select.value,'');assert.equal(room.button.disabled,false);
});

test('an unavailable or removed request disables assignment and shows the reason',async()=>{
    const room=queuePage();await room.flush();
    room.data({requests:[{id:4,helpers:[],reason:'No eligible Helper. Readiness required: 1.'}]});
    await room.poll();assert.equal(room.button.disabled,true);assert.match(room.note.textContent,/Readiness required/);
    room.data({requests:[]});await room.poll();assert.match(room.note.textContent,/request changed/);
});

test('focused Helper selection is left untouched until the user finishes',async()=>{
    const room=queuePage();await room.flush();room.document.activeElement=room.select;
    room.data({requests:[]});await room.poll();assert.equal(room.select.value,'7');
    room.document.activeElement=null;await room.poll();assert.equal(room.button.disabled,true);
});
