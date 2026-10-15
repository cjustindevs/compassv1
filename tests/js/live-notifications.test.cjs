const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
function notices(storage=new Map(),interval=5000){
    class Node{
        constructor(tag){this.tag=tag;this.children=[];this.attributes={};this.handlers={};this.hidden=false;this.textContent='';}
        append(...children){children.forEach(child=>{child.parent=this;this.children.push(child);});}
        setAttribute(key,value){this.attributes[key]=value;}
        addEventListener(name,handler){this.handlers[name]=handler;}
        replaceChildren(...children){this.children=[];this.append(...children);}
        remove(){if(this.parent)this.parent.children=this.parent.children.filter(child=>child!==this);}
        get firstElementChild(){return this.children[0];}
        contains(target){return target===this||this.children.some(child=>child.contains(target));}
        focus(){}
    }
    const body=new Node('body'),requests=[],intervals=[],timings=[],handlers={};let dialog=false;
    let data={count:1,cursor:12,recent:[{id:12,title:'Assigned case',message:'Open the case',link:'/helper/cases',type:'assignment'}],fresh:[{id:12,title:'<img onerror=bad()>',message:'Safe message',link:'//evil.test',type:'assignment'}]};
    const context={document:{body,hidden:false,getElementById:()=>null,createElement:tag=>new Node(tag),createElementNS:(ns,tag)=>new Node(tag),
        querySelector:selector=>selector==='meta[name="compass-notices"]'?{content:JSON.stringify({user:5,feed:'/notifications/feed',interval,center:'/notifications'})}:selector==='dialog[open]'&&dialog?{}:null,
        querySelectorAll:()=>[],addEventListener:(name,handler)=>{handlers[name]=handler;}},window:{addEventListener(){}},navigator:{onLine:true},
        sessionStorage:{getItem:key=>storage.get(key)??null,setItem:(key,value)=>storage.set(key,value)},setTimeout(){},setInterval:(callback,ms)=>{intervals.push(callback);timings.push(ms);},
        fetch:async(url,options)=>{requests.push({url,options});return{ok:true,json:async()=>data};},console};
    vm.runInNewContext(fs.readFileSync('public/js/live-notifications.js','utf8'),context);
    return {body,requests,storage,timings,poll:()=>intervals[0](),dialog:value=>{dialog=value;},setData:value=>{data=value;},flush:()=>new Promise(resolve=>setImmediate(resolve))};
}
test('shared bell and popups work without Echo, render safe text, and do not repeat across navigation',async()=>{
    const first=notices();await first.flush();const root=first.body.children[0],toasts=first.body.children[1];
    assert.equal(root.children[0].attributes['aria-label'],'1 unread notifications');
    assert.equal(toasts.children.length,1);assert.equal(toasts.children[0].children[0].textContent,'<img onerror=bad()>');
    assert.equal(toasts.children[0].children[2].href,'/notifications');
    assert.equal(first.requests[0].options.cache,'no-store');
    first.poll();await first.flush();assert.equal(toasts.children.length,1);
    const second=notices(first.storage);await second.flush();assert.equal(second.body.children[1].children.length,0);
    assert.equal(second.requests[0].url,'/notifications/feed?after=12');
});
test('an open confirmation dialog defers popups without discarding the pending cursor',async()=>{
    const room=notices();room.dialog(true);await room.flush();assert.equal(room.body.children[1].children.length,0);
    assert.equal(room.storage.has('compass-notices:5'),false);
    room.dialog(false);room.poll();await room.flush();assert.equal(room.body.children[1].children.length,1);
    assert.equal(room.storage.get('compass-notices:5'),'12');
});

test('staff emergency feeds poll every two seconds without needing a WebSocket connection',async()=>{
    const room=notices(new Map(),2000);await room.flush();
    assert.equal(room.timings[0],2000);
    const regular=notices();await regular.flush();assert.equal(regular.timings[0],5000);
});
