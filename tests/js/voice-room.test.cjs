const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

function room() {
    class Node {
        constructor(){this.handlers={};this.attributes={};this.dataset={};this.hidden=true;this.textContent='';}
        addEventListener(name,fn){this.handlers[name]=fn;}
        setAttribute(name,value){this.attributes[name]=value;}
        focus(){this.focused=true;}
    }
    const panel=new Node(),toggle=new Node(),close=new Node(),count=new Node(),list=new Node(),input=new Node();
    list.scrollHeight=240;list.scrollTop=0;
    let mutation;
    const context={document:{getElementById:id=>({voiceMessages:panel,chatMessages:list,messageInput:input,currentUserId:{value:'7'}}[id]),
        querySelector:selector=>({'[data-messages-toggle]':toggle,'[data-messages-close]':close,'[data-message-count]':count}[selector])},
        MutationObserver:class{constructor(callback){mutation=callback;}observe(){}},
        // Opening the drawer must not touch microphone or call lifecycle APIs.
        window:{},fetch(){throw Error('Unexpected network request');}};
    vm.runInNewContext(fs.readFileSync('public/js/voice-room.js','utf8'),context);
    return {panel,toggle,close,count,list,input,append(senderId){mutation([{addedNodes:[{matches:selector=>selector==='.message',dataset:{senderId}}]}]);}};
}

test('Messages opens and closes in place, focuses the composer, and preserves call state',()=>{
    const r=room();
    r.toggle.handlers.click();
    assert.equal(r.panel.hidden,false);assert.equal(r.toggle.attributes['aria-expanded'],'true');
    assert.equal(r.input.focused,true);assert.equal(r.list.scrollTop,240);
    r.close.handlers.click();assert.equal(r.panel.hidden,true);assert.equal(r.toggle.focused,true);
    r.toggle.handlers.click();r.panel.handlers.keydown({key:'Escape'});assert.equal(r.panel.hidden,true);
});

test('old history and your own messages do not create an unread badge; new partner messages do',()=>{
    const r=room();r.list.dataset.historyLoaded='true';r.append('8');assert.equal(r.count.hidden,true);
    r.append('7');assert.equal(r.count.hidden,true);
    r.append('8');assert.equal(r.count.hidden,false);assert.equal(r.count.textContent,'1');
    r.toggle.handlers.click();assert.equal(r.count.hidden,true);
    r.append('8');assert.equal(r.count.hidden,true);
});
