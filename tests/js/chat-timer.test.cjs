const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function room() {
    const timer = { textContent: '' };
    let time = 0;
    const context = vm.createContext({
        document: { addEventListener() {}, getElementById: () => timer },
        performance: { now: () => time },
        clearInterval() {}, setTimeout() {}, window: {},
        Date,
    });
    // Evaluate both application modules in the VM; retain the real icon implementation.
    vm.runInContext(fs.readFileSync('resources/js/ui-icon.js', 'utf8').replace('export function uiIcon', 'function uiIcon'), context);
    vm.runInContext(fs.readFileSync('resources/js/chat.js', 'utf8').replace(/^import .*;\r?\n/gm, ''), context);
    const app = Object.create(vm.runInContext('ChatApp.prototype', context));
    Object.assign(app, { ended: false, deadlineReached: false, messageInput: {}, sendButton: {}, remainingSeconds: 1, statusReceivedAt: 0 });
    return { app, timer, advance: milliseconds => time += milliseconds };
}

test('deadline locks composer and checks server; send completion cannot unlock it', () => {
    const { app, timer, advance } = room();
    let checks = 0;
    app.checkSessionStatus = () => checks++;
    app.updateSessionTimer();
    assert.equal(timer.textContent, '00:01 remaining');
    advance(1000);
    app.updateSessionTimer();
    assert.equal(checks, 1);
    assert.equal(timer.textContent, '00:00 remaining');
    app.setInputDisabled(false);
    assert.equal(app.messageInput.disabled, true);
    assert.equal(app.sendButton.disabled, true);
});

test('message safety polling runs for failed private subscriptions even if the socket is connected',()=>{
    const intervals=[];const context=vm.createContext({document:{hidden:false,addEventListener(){}},window:{Echo:{connector:{pusher:{connection:{state:'connected'}}}},addEventListener(){}},Date,setInterval:callback=>{intervals.push(callback);return 1;}});
    vm.runInContext(fs.readFileSync('resources/js/chat.js','utf8').replace(/^import .*;\r?\n/gm,''),context);
    const app=Object.create(vm.runInContext('ChatApp.prototype',context));let fetched=0;
    Object.assign(app,{ended:false,channelReady:false,lastMessageSync:Date.now(),loadMessages(){fetched++;}});
    app.enablePollingFallback();intervals[0]();assert.equal(fetched,1);
    app.channelReady=true;intervals[0]();assert.equal(fetched,1);
    app.lastMessageSync=Date.now()-15000;intervals[0]();assert.equal(fetched,2);
    app.ended=true;intervals[0]();assert.equal(fetched,2);
});

test('polling and websocket end notices trigger only one overlay and preserve lock', () => {
    const { app } = room();
    let overlays = 0;
    app.showTyping = () => {};
    app.showEndedOverlay = () => overlays++;
    app.handleSessionEnded({ message: '90-minute limit reached' });
    app.handleSessionEnded({ message: 'duplicate websocket notice' });
    app.setInputDisabled(false);
    assert.equal(overlays, 1);
    assert.equal(app.messageInput.disabled, true);
});


test('a delayed end event for an older session cannot end the current chat', async () => {
    const { app } = room(); app.sessionId='42';
    let overlays=0; app.showTyping=()=>{}; app.showEndedOverlay=()=>overlays++;
    app.handleSessionEnded({session_id:41, seeker_redirect:'/session/evaluation?session_id=41'});
    await app.applySessionState({session_id:41, ended:true});
    assert.equal(app.ended,false); assert.equal(overlays,0);
    app.handleSessionEnded({session_id:42}); assert.equal(app.ended,true); assert.equal(overlays,1);
});
